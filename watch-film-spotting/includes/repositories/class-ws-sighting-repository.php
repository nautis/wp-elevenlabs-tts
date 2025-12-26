<?php
/**
 * Sighting repository - reads from existing FWD tables
 */

// TOGGLE: Set to false to show sightings without images
define("WS_REQUIRE_IMAGE", true);

if (!defined("ABSPATH")) {
    exit;
}

class WS_Sighting_Repository {
    
    /**
     * Get a single sighting by ID
     */
    public static function get_by_id($faw_id, $user_id = null) {
        global $wpdb;
        
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        $faw_table = ws_table(WS_TABLE_SIGHTINGS);
        $faw_table = ws_table(WS_TABLE_SIGHTINGS);
        
        $sql = $wpdb->prepare(
            "SELECT 
                s.*,
                f.film_id,
                f.actor_id,
                f.character_id,
                f.watch_id,
                f.created_at,
                f.created_by,
                f.image_url as image_url
            FROM $search_table s
            JOIN $faw_table f ON s.faw_id = f.faw_id
            WHERE s.faw_id = %d
            AND s.deleted_at IS NULL",
            $faw_id
        );
        
        $row = $wpdb->get_row($sql);
        
        if (!$row) {
            return null;
        }
        
        $sighting = WS_Sighting::from_row($row);
        
        // Add vote data
        $vote_data = WS_Vote_Repository::get_score($faw_id);
        $sighting->vote_score = $vote_data['score'];
        $sighting->vote_count = $vote_data['count'];
        
        if ($user_id) {
            $sighting->user_vote = WS_Vote_Repository::get_user_vote($faw_id, $user_id);
        }
        
        // Add comment count
        $sighting->comment_count = WS_Comment_Repository::count_for_sighting($faw_id);
        
        // Add editorial confidence from meta
        $sighting->editorial_confidence = WS_Meta_Repository::get($faw_id, 'editorial_confidence');
        
        return $sighting;
    }
    
    /**
     * Search sightings
     */
    public static function search($args = []) {
        global $wpdb;
        
        $defaults = [
            'query' => '',
            'actor' => '',
            'film' => '',
            'brand' => '',
            'actor_id' => null,
            'film_id' => null,
            'brand_id' => null,
            'year' => null,
            'per_page' => 21,
            'page' => 1,
            'orderby' => 'film_title',
            'order' => 'ASC',
            'user_id' => null,
        ];
        
        $args = wp_parse_args($args, $defaults);
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        $faw_table = ws_table(WS_TABLE_SIGHTINGS);
        
        $where = ['s.deleted_at IS NULL'];

        // Filter out sightings without images if required
        if (defined('WS_REQUIRE_IMAGE') && WS_REQUIRE_IMAGE) {
            $where[] = "f.image_url IS NOT NULL AND f.image_url != ''";
        }
        $values = [];
        
        // Full-text search
        if (!empty($args['query'])) {
            $where[] = 'MATCH(s.search_text) AGAINST(%s IN NATURAL LANGUAGE MODE)';
            $values[] = $args['query'];
        }
        
        // Filter by actor
        if (!empty($args['actor'])) {
            $where[] = 's.actor_name LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['actor']) . '%';
        }
        
        // Filter by film
        if (!empty($args['film'])) {
            $where[] = 's.film_title LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['film']) . '%';
        }
        
        // Filter by brand
        if (!empty($args['brand'])) {
            $where[] = 's.brand_name LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['brand']) . '%';
        }

        // Filter by actor_id (direct lookup)
        if ($args['actor_id']) {
            $where[] = 's.actor_id = %d';
            $values[] = $args['actor_id'];
        }

        // Filter by film_id (direct lookup)
        if ($args['film_id']) {
            $where[] = 's.film_id = %d';
            $values[] = $args['film_id'];
        }

        // Filter by brand_id (direct lookup)
        if ($args['brand_id']) {
            $where[] = 's.brand_id = %d';
            $values[] = $args['brand_id'];
        }

        // Filter by year
        if ($args['year']) {
            $where[] = 's.film_year = %d';
            $values[] = $args['year'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Allowed order columns
        $allowed_orderby = ['created_at', 'film_title', 'actor_name', 'brand_name', 'film_year'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Pagination
        $per_page = max(1, min(100, (int) $args['per_page']));
        $offset = ($args['page'] - 1) * $per_page;
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM $search_table s JOIN $faw_table f ON s.faw_id = f.faw_id WHERE $where_clause";
        if ($values) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        $total = (int) $wpdb->get_var($count_sql);
        
        // Get results
        $sql = "SELECT s.*, f.image_url as image_url FROM $search_table s JOIN $faw_table f ON s.faw_id = f.faw_id WHERE $where_clause ORDER BY s.$orderby $order LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;
        
        $sql = $wpdb->prepare($sql, $values);
        $rows = $wpdb->get_results($sql);
        
        $sightings = [];
        foreach ($rows as $row) {
            $sighting = WS_Sighting::from_row($row);
            
            // Add vote data
            $vote_data = WS_Vote_Repository::get_score($sighting->faw_id);
            $sighting->vote_score = $vote_data['score'];
            $sighting->vote_count = $vote_data['count'];
            
            if ($args['user_id']) {
                $sighting->user_vote = WS_Vote_Repository::get_user_vote($sighting->faw_id, $args['user_id']);
            }
            
            $sightings[] = $sighting;
        }
        
        return [
            'sightings' => $sightings,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'page' => (int) $args['page'],
            'per_page' => $per_page,
        ];
    }
    
    /**
     * Get recent sightings
     */
    public static function get_recent($limit = 10, $user_id = null) {
        return self::search([
            'per_page' => $limit,
            'page' => 1,
            'orderby' => 'film_title',
            'order' => 'ASC',
            'user_id' => $user_id,
        ]);
    }
    
    /**
     * Get sightings by actor
     */
    public static function get_by_actor($actor_name, $args = []) {
        $args['actor'] = $actor_name;
        return self::search($args);
    }
    
    /**
     * Get sightings by film
     */
    public static function get_by_film($film_title, $args = []) {
        $args['film'] = $film_title;
        return self::search($args);
    }
    
    /**
     * Get sightings by brand
     */
    public static function get_by_brand($brand_name, $args = []) {
        $args['brand'] = $brand_name;
        return self::search($args);
    }
    
    /**
     * Get list of unique actors with counts
     */
    public static function get_actors_list() {
        global $wpdb;
        $table = ws_table(WS_TABLE_SEARCH_INDEX);

        return $wpdb->get_results(
            "SELECT actor_id, actor_name, COUNT(*) as count
            FROM $table
            WHERE deleted_at IS NULL
            GROUP BY actor_id, actor_name
            ORDER BY actor_name ASC"
        );
    }

    /**
     * Get list of unique brands with counts
     */
    public static function get_brands_list() {
        global $wpdb;
        $table = ws_table(WS_TABLE_SEARCH_INDEX);

        return $wpdb->get_results(
            "SELECT brand_id, brand_name, COUNT(*) as count
            FROM $table
            WHERE deleted_at IS NULL
            GROUP BY brand_id, brand_name
            ORDER BY brand_name ASC"
        );
    }

    /**
     * Get list of unique films with counts
     */
    public static function get_films_list() {
        global $wpdb;
        $table = ws_table(WS_TABLE_SEARCH_INDEX);

        return $wpdb->get_results(
            "SELECT film_id, film_title, film_year, COUNT(*) as count
            FROM $table
            WHERE deleted_at IS NULL
            GROUP BY film_id, film_title, film_year
            ORDER BY film_title ASC, film_year ASC"
        );
    }

    /**
     * Get sightings by actor ID
     */
    public static function get_by_actor_id($actor_id, $args = []) {
        $args['actor_id'] = $actor_id;
        return self::search($args);
    }

    /**
     * Get sightings by film ID
     */
    public static function get_by_film_id($film_id, $args = []) {
        $args['film_id'] = $film_id;
        return self::search($args);
    }

    /**
     * Get sightings by brand ID
     */
    public static function get_by_brand_id($brand_id, $args = []) {
        $args['brand_id'] = $brand_id;
        return self::search($args);
    }
}
