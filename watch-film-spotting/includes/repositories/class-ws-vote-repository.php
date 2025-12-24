<?php
/**
 * Vote repository
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Vote_Repository {
    
    /**
     * Get vote score for a sighting
     */
    public static function get_score($faw_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(vote) as score, COUNT(*) as count FROM $table WHERE faw_id = %d",
            $faw_id
        ));
        
        return [
            'score' => (int) ($result->score ?? 0),
            'count' => (int) ($result->count ?? 0),
        ];
    }
    
    /**
     * Get user's vote for a sighting
     */
    public static function get_user_vote($faw_id, $user_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        
        $vote = $wpdb->get_var($wpdb->prepare(
            "SELECT vote FROM $table WHERE faw_id = %d AND user_id = %d",
            $faw_id,
            $user_id
        ));
        
        return $vote !== null ? (int) $vote : null;
    }
    
    /**
     * Cast or update a vote (upsert)
     */
    public static function cast($faw_id, $user_id, $vote) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        $sql = $wpdb->prepare(
            "INSERT INTO $table (faw_id, user_id, vote) 
            VALUES (%d, %d, %d) 
            ON DUPLICATE KEY UPDATE vote = %d, updated_at = CURRENT_TIMESTAMP",
            $faw_id,
            $user_id,
            $vote,
            $vote
        );
        
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            return false;
        }
        
        // Return new score
        return self::get_score($faw_id);
    }
    
    /**
     * Remove a vote
     */
    public static function remove($faw_id, $user_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        
        $result = $wpdb->delete(
            $table,
            ['faw_id' => $faw_id, 'user_id' => $user_id],
            ['%d', '%d']
        );
        
        if ($result === false) {
            return false;
        }
        
        return self::get_score($faw_id);
    }
    
    /**
     * Get votes by user
     */
    public static function get_by_user($user_id, $limit = 50) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, s.film_title, s.actor_name, s.brand_name
            FROM $table v
            LEFT JOIN $search_table s ON v.faw_id = s.faw_id
            WHERE v.user_id = %d
            ORDER BY v.created_at DESC
            LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Count votes by user
     */
    public static function count_by_user($user_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get top voted sightings
     */
    public static function get_top_voted($limit = 20) {
        global $wpdb;
        $table = ws_table(WS_TABLE_USER_VOTES);
        $search_table = ws_table(WS_TABLE_SEARCH_INDEX);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, SUM(v.vote) as vote_score, COUNT(v.vote_id) as vote_count
            FROM $search_table s
            LEFT JOIN $table v ON s.faw_id = v.faw_id
            WHERE s.deleted_at IS NULL
            GROUP BY s.faw_id
            HAVING vote_score > 0
            ORDER BY vote_score DESC, vote_count DESC
            LIMIT %d",
            $limit
        ));
    }
}
