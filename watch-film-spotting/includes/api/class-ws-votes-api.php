<?php
/**
 * Votes REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Votes_API extends WS_REST_Controller {
    
    public static function register_routes() {
        // Cast vote on a sighting
        register_rest_route(self::NAMESPACE, '/sightings/(?P<id>\d+)/vote', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'cast_vote'],
            'permission_callback' => [__CLASS__, 'check_logged_in'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'vote' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
        
        // Remove vote
        register_rest_route(self::NAMESPACE, '/sightings/(?P<id>\d+)/vote', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [__CLASS__, 'remove_vote'],
            'permission_callback' => [__CLASS__, 'check_logged_in'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
        
        // Get vote status
        register_rest_route(self::NAMESPACE, '/sightings/(?P<id>\d+)/vote', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_vote_status'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
        
        // Get user's votes
        register_rest_route(self::NAMESPACE, '/users/(?P<id>\d+)/votes', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_user_votes'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }
    
    public static function cast_vote($request) {
        $result = WS_Vote_Service::cast_vote(
            $request->get_param('id'),
            get_current_user_id(),
            $request->get_param('vote')
        );
        
        if (is_wp_error($result)) {
            return self::wp_error_response($result);
        }
        
        return self::success([
            'score' => $result['score'],
            'count' => $result['count'],
            'user_vote' => $request->get_param('vote'),
        ], 'Vote recorded.');
    }
    
    public static function remove_vote($request) {
        $result = WS_Vote_Service::remove_vote(
            $request->get_param('id'),
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            return self::wp_error_response($result);
        }
        
        return self::success([
            'score' => $result['score'],
            'count' => $result['count'],
            'user_vote' => null,
        ], 'Vote removed.');
    }
    
    public static function get_vote_status($request) {
        $status = WS_Vote_Service::get_vote_status(
            $request->get_param('id'),
            get_current_user_id() ?: null
        );
        
        return self::success($status);
    }
    
    public static function get_user_votes($request) {
        $votes = WS_Vote_Repository::get_by_user($request->get_param('id'));
        
        return self::success($votes);
    }
}
