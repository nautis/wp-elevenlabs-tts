<?php
/**
 * Comments REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Comments_API extends WS_REST_Controller {
    
    public static function register_routes() {
        // Get comments for a sighting
        register_rest_route(self::NAMESPACE, '/sightings/(?P<id>\d+)/comments', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_comments'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
        
        // Add comment to a sighting
        register_rest_route(self::NAMESPACE, '/sightings/(?P<id>\d+)/comments', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'add_comment'],
            'permission_callback' => [__CLASS__, 'check_logged_in'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'content' => ['required' => true, 'type' => 'string'],
                'comment_type' => ['type' => 'string', 'default' => 'general'],
            ],
        ]);
        
        // Reply to a comment
        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)/reply', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'add_reply'],
            'permission_callback' => [__CLASS__, 'check_logged_in'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'content' => ['required' => true, 'type' => 'string'],
            ],
        ]);
        
        // Delete comment
        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [__CLASS__, 'delete_comment'],
            'permission_callback' => [__CLASS__, 'check_logged_in'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
        
        // Moderation endpoints
        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)/status', [
            'methods' => 'PATCH',
            'callback' => [__CLASS__, 'update_status'],
            'permission_callback' => [__CLASS__, 'check_can_moderate'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'status' => ['required' => true, 'type' => 'string'],
            ],
        ]);
        
        // Moderation queue
        register_rest_route(self::NAMESPACE, '/admin/moderation-queue', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_moderation_queue'],
            'permission_callback' => [__CLASS__, 'check_can_moderate'],
        ]);
        
        // Bulk moderation
        register_rest_route(self::NAMESPACE, '/admin/moderation-bulk', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'bulk_moderate'],
            'permission_callback' => [__CLASS__, 'check_can_moderate'],
            'args' => [
                'action' => ['required' => true, 'type' => 'string'],
                'comment_ids' => ['required' => true, 'type' => 'array'],
            ],
        ]);
    }
    
    public static function get_comments($request) {
        $comments = WS_Comment_Service::get_for_sighting(
            $request->get_param('id'),
            get_current_user_id()
        );
        
        return self::success(array_map(function($c) { return $c->to_array(); }, $comments));
    }
    
    public static function add_comment($request) {
        $result = WS_Comment_Service::add_comment(
            $request->get_param('id'),
            get_current_user_id(),
            $request->get_param('content'),
            $request->get_param('comment_type')
        );
        
        if (is_wp_error($result)) {
            return self::wp_error_response($result);
        }
        
        return self::success($result->to_array(), 'Comment submitted successfully.');
    }
    
    public static function add_reply($request) {
        $parent = WS_Comment_Repository::get_by_id($request->get_param('id'));
        
        if (!$parent) {
            return self::error('Comment not found.', 404);
        }
        
        $result = WS_Comment_Service::add_comment(
            $parent->faw_id,
            get_current_user_id(),
            $request->get_param('content'),
            'general',
            $parent->comment_id
        );
        
        if (is_wp_error($result)) {
            return self::wp_error_response($result);
        }
        
        return self::success($result->to_array(), 'Reply submitted successfully.');
    }
    
    public static function delete_comment($request) {
        $result = WS_Comment_Service::delete(
            $request->get_param('id'),
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            return self::wp_error_response($result);
        }
        
        return self::success(null, 'Comment deleted.');
    }
    
    public static function update_status($request) {
        $status = $request->get_param('status');
        $comment_id = $request->get_param('id');
        
        switch ($status) {
            case 'approved':
                $result = WS_Comment_Service::approve($comment_id);
                break;
            case 'spam':
                $result = WS_Comment_Service::spam($comment_id);
                break;
            case 'trash':
                $result = WS_Comment_Service::trash($comment_id);
                break;
            default:
                return self::error('Invalid status.', 400);
        }
        
        if (is_wp_error($result)) {
            return self::wp_error_response($result);
        }
        
        return self::success(null, 'Status updated.');
    }
    
    public static function get_moderation_queue($request) {
        $comments = WS_Moderation_Service::get_queue();
        $count = WS_Moderation_Service::count_pending();
        
        if (is_wp_error($comments)) {
            return self::wp_error_response($comments);
        }
        
        return self::success([
            'comments' => array_map(function($c) { 
                $arr = $c->to_array();
                $arr['sighting_context'] = $c->sighting_context ?? null;
                return $arr;
            }, $comments),
            'total' => $count,
        ]);
    }
    
    public static function bulk_moderate($request) {
        $action = $request->get_param('action');
        $ids = $request->get_param('comment_ids');
        
        switch ($action) {
            case 'approve':
                $count = WS_Moderation_Service::bulk_approve($ids);
                break;
            case 'spam':
                $count = WS_Moderation_Service::bulk_spam($ids);
                break;
            case 'trash':
                $count = WS_Moderation_Service::bulk_trash($ids);
                break;
            default:
                return self::error('Invalid action.', 400);
        }
        
        if (is_wp_error($count)) {
            return self::wp_error_response($count);
        }
        
        return self::success(['count' => $count], "$count comments updated.");
    }
}
