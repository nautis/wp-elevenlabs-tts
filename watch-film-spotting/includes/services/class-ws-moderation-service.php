<?php
/**
 * Moderation service - handles comment moderation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Moderation_Service {
    
    /**
     * Get pending comments for moderation queue
     */
    public static function get_queue($limit = 50, $offset = 0) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        return WS_Comment_Repository::get_pending($limit, $offset);
    }
    
    /**
     * Count pending comments
     */
    public static function count_pending() {
        return WS_Comment_Repository::count_pending();
    }
    
    /**
     * Bulk approve comments
     */
    public static function bulk_approve($comment_ids) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        $count = 0;
        foreach ($comment_ids as $id) {
            $result = WS_Comment_Service::approve($id);
            if (!is_wp_error($result)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Bulk spam comments
     */
    public static function bulk_spam($comment_ids) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        $count = 0;
        foreach ($comment_ids as $id) {
            $result = WS_Comment_Service::spam($id);
            if (!is_wp_error($result)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Bulk trash comments
     */
    public static function bulk_trash($comment_ids) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        $count = 0;
        foreach ($comment_ids as $id) {
            $result = WS_Comment_Service::trash($id);
            if (!is_wp_error($result)) {
                $count++;
            }
        }
        
        return $count;
    }
}
