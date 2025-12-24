<?php
/**
 * Vote service - business logic for voting
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Vote_Service {
    
    /**
     * Cast a vote
     */
    public static function cast_vote($faw_id, $user_id, $vote) {
        // Validate user is logged in
        if (!$user_id || !get_user_by('ID', $user_id)) {
            return new WP_Error('unauthorized', 'You must be logged in to vote.');
        }
        
        // Validate sighting exists
        $sighting = WS_Sighting_Repository::get_by_id($faw_id);
        if (!$sighting) {
            return new WP_Error('not_found', 'Sighting not found.');
        }
        
        // Validate vote value
        $vote = (int) $vote;
        if (!WS_Vote::is_valid_vote($vote)) {
            return new WP_Error('invalid_vote', 'Vote must be +1 or -1.');
        }
        
        // Cast the vote
        $result = WS_Vote_Repository::cast($faw_id, $user_id, $vote);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to record vote.');
        }
        
        // Trigger action
        do_action('watchspotting_vote_cast', $faw_id, $user_id, $vote);
        
        return $result;
    }
    
    /**
     * Remove a vote
     */
    public static function remove_vote($faw_id, $user_id) {
        // Validate user is logged in
        if (!$user_id || !get_user_by('ID', $user_id)) {
            return new WP_Error('unauthorized', 'You must be logged in.');
        }
        
        // Check if vote exists
        $existing_vote = WS_Vote_Repository::get_user_vote($faw_id, $user_id);
        if ($existing_vote === null) {
            return new WP_Error('not_found', 'No vote to remove.');
        }
        
        $result = WS_Vote_Repository::remove($faw_id, $user_id);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to remove vote.');
        }
        
        do_action('watchspotting_vote_removed', $faw_id, $user_id);
        
        return $result;
    }
    
    /**
     * Get vote status for a sighting (including user's vote)
     */
    public static function get_vote_status($faw_id, $user_id = null) {
        $score = WS_Vote_Repository::get_score($faw_id);
        $user_vote = null;
        
        if ($user_id) {
            $user_vote = WS_Vote_Repository::get_user_vote($faw_id, $user_id);
        }
        
        return [
            'score' => $score['score'],
            'count' => $score['count'],
            'user_vote' => $user_vote,
        ];
    }
}
