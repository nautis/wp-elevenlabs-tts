<?php
/**
 * Search service - handles search and browse functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Search_Service {
    
    /**
     * Search sightings with query
     */
    public static function search($query, $args = []) {
        $args['query'] = $query;
        $args['user_id'] = get_current_user_id() ?: null;
        
        return WS_Sighting_Repository::search($args);
    }
    
    /**
     * Browse by actor
     */
    public static function browse_by_actor($actor_name, $args = []) {
        $args['user_id'] = get_current_user_id() ?: null;
        
        return WS_Sighting_Repository::get_by_actor($actor_name, $args);
    }
    
    /**
     * Browse by film
     */
    public static function browse_by_film($film_title, $args = []) {
        $args['user_id'] = get_current_user_id() ?: null;
        
        return WS_Sighting_Repository::get_by_film($film_title, $args);
    }
    
    /**
     * Browse by brand
     */
    public static function browse_by_brand($brand_name, $args = []) {
        $args['user_id'] = get_current_user_id() ?: null;
        
        return WS_Sighting_Repository::get_by_brand($brand_name, $args);
    }
    
    /**
     * Get recent sightings
     */
    public static function get_recent($limit = 10) {
        $user_id = get_current_user_id() ?: null;
        
        return WS_Sighting_Repository::get_recent($limit, $user_id);
    }
    
    /**
     * Get top voted sightings
     */
    public static function get_top_voted($limit = 20) {
        return WS_Vote_Repository::get_top_voted($limit);
    }
    
    /**
     * Get autocomplete suggestions
     */
    public static function get_suggestions($query, $type = 'all', $limit = 10) {
        global $wpdb;
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        $suggestions = [];
        
        if ($type === 'all' || $type === 'actor') {
            $actors = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT actor_name FROM $search_table 
                WHERE actor_name LIKE %s AND deleted_at IS NULL 
                ORDER BY actor_name LIMIT %d",
                $like,
                $limit
            ));
            foreach ($actors as $actor) {
                $suggestions[] = ['type' => 'actor', 'value' => $actor];
            }
        }
        
        if ($type === 'all' || $type === 'film') {
            $films = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT film_title, film_year FROM $search_table 
                WHERE film_title LIKE %s AND deleted_at IS NULL 
                ORDER BY film_title LIMIT %d",
                $like,
                $limit
            ));
            foreach ($films as $film) {
                $suggestions[] = [
                    'type' => 'film', 
                    'value' => $film->film_title,
                    'year' => $film->film_year,
                ];
            }
        }
        
        if ($type === 'all' || $type === 'brand') {
            $brands = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT brand_name FROM $search_table 
                WHERE brand_name LIKE %s AND deleted_at IS NULL 
                ORDER BY brand_name LIMIT %d",
                $like,
                $limit
            ));
            foreach ($brands as $brand) {
                $suggestions[] = ['type' => 'brand', 'value' => $brand];
            }
        }
        
        return $suggestions;
    }
}
