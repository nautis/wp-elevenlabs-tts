<?php
/**
 * Sightings REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Sightings_API extends WS_REST_Controller {
    
    public static function register_routes() {
        // List/search sightings
        register_rest_route(self::NAMESPACE, '/sightings', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_sightings'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => ['type' => 'string', 'default' => ''],
                'actor' => ['type' => 'string', 'default' => ''],
                'film' => ['type' => 'string', 'default' => ''],
                'brand' => ['type' => 'string', 'default' => ''],
                'year' => ['type' => 'integer'],
                'page' => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
                'orderby' => ['type' => 'string', 'default' => 'film_title'],
                'order' => ['type' => 'string', 'default' => 'DESC'],
            ],
        ]);
        
        // Get single sighting
        register_rest_route(self::NAMESPACE, '/sightings/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_sighting'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
        
        // Get suggestions for autocomplete
        register_rest_route(self::NAMESPACE, '/sightings/suggestions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_suggestions'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => ['required' => true, 'type' => 'string'],
                'type' => ['type' => 'string', 'default' => 'all'],
            ],
        ]);
        
        // Get lists for browsing
        register_rest_route(self::NAMESPACE, '/actors', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_actors'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/brands', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_brands'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/films', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_films'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public static function get_sightings($request) {
        $result = WS_Sighting_Repository::search([
            'query' => $request->get_param('query'),
            'actor' => $request->get_param('actor'),
            'film' => $request->get_param('film'),
            'brand' => $request->get_param('brand'),
            'year' => $request->get_param('year'),
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'user_id' => get_current_user_id() ?: null,
        ]);
        
        return self::success([
            'sightings' => array_map(function($s) { return $s->to_array(); }, $result['sightings']),
            'pagination' => [
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }
    
    public static function get_sighting($request) {
        $sighting = WS_Sighting_Repository::get_by_id(
            $request->get_param('id'),
            get_current_user_id() ?: null
        );
        
        if (!$sighting) {
            return self::error('Sighting not found.', 404);
        }
        
        return self::success($sighting->to_array());
    }
    
    public static function get_suggestions($request) {
        $suggestions = WS_Search_Service::get_suggestions(
            $request->get_param('query'),
            $request->get_param('type'),
            10
        );
        
        return self::success($suggestions);
    }
    
    public static function get_actors($request) {
        $actors = WS_Sighting_Repository::get_actors_list(100);
        return self::success($actors);
    }
    
    public static function get_brands($request) {
        $brands = WS_Sighting_Repository::get_brands_list(100);
        return self::success($brands);
    }
    
    public static function get_films($request) {
        $films = WS_Sighting_Repository::get_films_list(100);
        return self::success($films);
    }
}
