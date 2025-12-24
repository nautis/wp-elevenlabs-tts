<?php
/**
 * Comment repository
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Comment_Repository {
    
    /**
     * Get comment by ID
     */
    public static function get_by_id($comment_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.display_name as user_display_name
            FROM $table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.comment_id = %d",
            $comment_id
        ));
        
        if (!$row) {
            return null;
        }
        
        $comment = WS_Comment::from_row($row);
        $comment->user_avatar_url = get_avatar_url($comment->user_id, ['size' => 48]);
        
        return $comment;
    }
    
    /**
     * Get comments for a sighting (with threading)
     */
    public static function get_for_sighting($faw_id, $include_pending = false) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        $status_clause = $include_pending 
            ? "c.status IN ('approved', 'pending')" 
            : "c.status = 'approved'";
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as user_display_name
            FROM $table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.faw_id = %d AND $status_clause
            ORDER BY c.created_at ASC",
            $faw_id
        ));
        
        // Build threaded structure
        $comments = [];
        $replies = [];
        
        foreach ($rows as $row) {
            $comment = WS_Comment::from_row($row);
            $comment->user_avatar_url = get_avatar_url($comment->user_id, ['size' => 48]);
            
            if ($comment->parent_id) {
                $replies[$comment->parent_id][] = $comment;
            } else {
                $comments[$comment->comment_id] = $comment;
            }
        }
        
        // Attach replies to parent comments
        foreach ($comments as &$comment) {
            if (isset($replies[$comment->comment_id])) {
                $comment->replies = $replies[$comment->comment_id];
            }
        }
        
        return array_values($comments);
    }
    
    /**
     * Count comments for a sighting
     */
    public static function count_for_sighting($faw_id, $status = 'approved') {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE faw_id = %d AND status = %s",
            $faw_id,
            $status
        ));
    }
    
    /**
     * Create a new comment
     */
    public static function create($data) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        $result = $wpdb->insert($table, [
            'faw_id' => $data['faw_id'],
            'user_id' => $data['user_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'content' => $data['content'],
            'comment_type' => $data['comment_type'] ?? 'general',
            'status' => $data['status'] ?? 'pending',
        ], ['%d', '%d', '%d', '%s', '%s', '%s']);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update comment status
     */
    public static function update_status($comment_id, $status) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        return $wpdb->update(
            $table,
            ['status' => $status],
            ['comment_id' => $comment_id],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Delete comment
     */
    public static function delete($comment_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        // Also delete replies
        $wpdb->delete($table, ['parent_id' => $comment_id], ['%d']);
        
        return $wpdb->delete($table, ['comment_id' => $comment_id], ['%d']);
    }
    
    /**
     * Get pending comments for moderation
     */
    public static function get_pending($limit = 50, $offset = 0) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as user_display_name,
                    s.film_title, s.actor_name, s.brand_name
            FROM $table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            LEFT JOIN $search_table s ON c.faw_id = s.faw_id
            WHERE c.status = 'pending'
            ORDER BY c.created_at ASC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        
        $comments = [];
        foreach ($rows as $row) {
            $comment = WS_Comment::from_row($row);
            $comment->user_avatar_url = get_avatar_url($comment->user_id, ['size' => 48]);
            // Add sighting context
            $comment->sighting_context = [
                'film_title' => $row->film_title,
                'actor_name' => $row->actor_name,
                'brand_name' => $row->brand_name,
            ];
            $comments[] = $comment;
        }
        
        return $comments;
    }
    
    /**
     * Count pending comments
     */
    public static function count_pending() {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending'"
        );
    }
    
    /**
     * Get comments by user
     */
    public static function get_by_user($user_id, $limit = 50) {
        global $wpdb;
        $table = ws_table(WS_TABLE_COMMENTS);
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, s.film_title, s.actor_name, s.brand_name
            FROM $table c
            LEFT JOIN $search_table s ON c.faw_id = s.faw_id
            WHERE c.user_id = %d AND c.status = 'approved'
            ORDER BY c.created_at DESC
            LIMIT %d",
            $user_id,
            $limit
        ));
        
        $comments = [];
        foreach ($rows as $row) {
            $comment = WS_Comment::from_row($row);
            $comment->sighting_context = [
                'film_title' => $row->film_title,
                'actor_name' => $row->actor_name,
                'brand_name' => $row->brand_name,
            ];
            $comments[] = $comment;
        }
        
        return $comments;
    }
}
