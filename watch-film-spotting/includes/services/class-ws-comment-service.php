<?php
/**
 * Comment service - business logic for comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Comment_Service {
    
    /**
     * Add a new comment
     */
    public static function add_comment($faw_id, $user_id, $content, $type = 'general', $parent_id = null) {
        // Validate user is logged in
        if (!$user_id || !get_user_by('ID', $user_id)) {
            return new WP_Error('unauthorized', 'You must be logged in to comment.');
        }
        
        // Validate sighting exists
        $sighting = WS_Sighting_Repository::get_by_id($faw_id);
        if (!$sighting) {
            return new WP_Error('not_found', 'Sighting not found.');
        }
        
        // Validate parent comment (if replying)
        if ($parent_id) {
            $parent = WS_Comment_Repository::get_by_id($parent_id);
            if (!$parent) {
                return new WP_Error('not_found', 'Parent comment not found.');
            }
            
            // Ensure parent is top-level (enforce single nesting)
            if ($parent->parent_id) {
                return new WP_Error('invalid_parent', 'Cannot reply to a reply.');
            }
            
            // Ensure parent belongs to same sighting
            if ($parent->faw_id != $faw_id) {
                return new WP_Error('invalid_parent', 'Parent comment does not belong to this sighting.');
            }
        }
        
        // Create comment model for validation
        $comment = new WS_Comment();
        $comment->content = sanitize_textarea_field($content);
        $comment->comment_type = $type;
        
        $errors = $comment->validate();
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }
        
        // Determine initial status
        $require_approval = get_option('watchspotting_comments_require_approval', true);
        $status = $require_approval ? 'pending' : 'approved';
        
        // Auto-approve for admins/editors
        if (current_user_can('moderate_comments')) {
            $status = 'approved';
        }
        
        // Create the comment
        $comment_id = WS_Comment_Repository::create([
            'faw_id' => $faw_id,
            'user_id' => $user_id,
            'parent_id' => $parent_id,
            'content' => $comment->content,
            'comment_type' => $comment->comment_type,
            'status' => $status,
        ]);
        
        if (!$comment_id) {
            return new WP_Error('db_error', 'Failed to create comment.');
        }
        
        // Trigger action for notifications, etc.
        do_action('watchspotting_comment_created', $comment_id, $faw_id, $user_id);
        
        return WS_Comment_Repository::get_by_id($comment_id);
    }
    
    /**
     * Get comments for display on a sighting
     */
    public static function get_for_sighting($faw_id, $user_id = null) {
        // Include pending if user is admin
        $include_pending = current_user_can('moderate_comments');
        
        return WS_Comment_Repository::get_for_sighting($faw_id, $include_pending);
    }
    
    /**
     * Approve a comment
     */
    public static function approve($comment_id) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        $comment = WS_Comment_Repository::get_by_id($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', 'Comment not found.');
        }
        
        WS_Comment_Repository::update_status($comment_id, 'approved');
        
        do_action('watchspotting_comment_approved', $comment_id);
        
        return true;
    }
    
    /**
     * Mark comment as spam
     */
    public static function spam($comment_id) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        $comment = WS_Comment_Repository::get_by_id($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', 'Comment not found.');
        }
        
        WS_Comment_Repository::update_status($comment_id, 'spam');
        
        return true;
    }
    
    /**
     * Trash a comment
     */
    public static function trash($comment_id) {
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You do not have permission to moderate comments.');
        }
        
        $comment = WS_Comment_Repository::get_by_id($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', 'Comment not found.');
        }
        
        WS_Comment_Repository::update_status($comment_id, 'trash');
        
        return true;
    }
    
    /**
     * Delete a comment (user can delete own, admin can delete any)
     */
    public static function delete($comment_id, $user_id) {
        $comment = WS_Comment_Repository::get_by_id($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', 'Comment not found.');
        }
        
        // Check permissions
        if ($comment->user_id != $user_id && !current_user_can('moderate_comments')) {
            return new WP_Error('unauthorized', 'You can only delete your own comments.');
        }
        
        WS_Comment_Repository::delete($comment_id);
        
        return true;
    }
}
