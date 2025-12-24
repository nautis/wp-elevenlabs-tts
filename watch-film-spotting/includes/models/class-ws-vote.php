<?php
/**
 * Vote model
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Vote {
    
    const UPVOTE = 1;
    const DOWNVOTE = -1;
    
    public $vote_id;
    public $faw_id;
    public $user_id;
    public $vote;
    public $created_at;
    public $updated_at;
    
    /**
     * Create from database row
     */
    public static function from_row($row) {
        $vote = new self();
        
        foreach ($row as $key => $value) {
            if (property_exists($vote, $key)) {
                $vote->{$key} = $value;
            }
        }
        
        return $vote;
    }
    
    /**
     * Validate vote value
     */
    public static function is_valid_vote($value) {
        return in_array((int) $value, [self::UPVOTE, self::DOWNVOTE], true);
    }
    
    /**
     * Convert to array for JSON response
     */
    public function to_array() {
        return [
            'vote_id' => (int) $this->vote_id,
            'faw_id' => (int) $this->faw_id,
            'user_id' => (int) $this->user_id,
            'vote' => (int) $this->vote,
            'created_at' => $this->created_at,
        ];
    }
}
