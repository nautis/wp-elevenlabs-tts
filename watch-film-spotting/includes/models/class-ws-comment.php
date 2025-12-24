<?php
/**
 * Comment model
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Comment {
    
    const TYPE_GENERAL = 'general';
    const TYPE_CORRECTION = 'correction';
    const TYPE_SOURCE = 'source';
    const TYPE_ALTERNATIVE = 'alternative';
    
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_SPAM = 'spam';
    const STATUS_TRASH = 'trash';
    
    public $comment_id;
    public $faw_id;
    public $user_id;
    public $parent_id;
    public $content;
    public $comment_type;
    public $status;
    public $created_at;
    public $updated_at;
    
    // User data (joined)
    public $user_display_name;
    public $user_avatar_url;
    
    // Nested replies
    public $replies = [];
    
    /**
     * Valid comment types
     */
    public static function get_types() {
        return [
            self::TYPE_GENERAL => 'General',
            self::TYPE_CORRECTION => 'Correction',
            self::TYPE_SOURCE => 'Source',
            self::TYPE_ALTERNATIVE => 'Alternative',
        ];
    }
    
    /**
     * Valid statuses
     */
    public static function get_statuses() {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_SPAM => 'Spam',
            self::STATUS_TRASH => 'Trash',
        ];
    }
    
    /**
     * Create from database row
     */
    public static function from_row($row) {
        $comment = new self();
        
        foreach ($row as $key => $value) {
            if (property_exists($comment, $key)) {
                $comment->{$key} = $value;
            }
        }
        
        return $comment;
    }
    
    /**
     * Validate content
     */
    public function validate() {
        $errors = [];
        
        if (empty($this->content)) {
            $errors[] = 'Comment content is required.';
        }
        
        if (strlen($this->content) < 3) {
            $errors[] = 'Comment must be at least 3 characters.';
        }
        
        if (strlen($this->content) > 5000) {
            $errors[] = 'Comment must be less than 5000 characters.';
        }
        
        if (!in_array($this->comment_type, array_keys(self::get_types()))) {
            $errors[] = 'Invalid comment type.';
        }
        
        return $errors;
    }
    
    /**
     * Check if comment is a reply
     */
    public function is_reply() {
        return !empty($this->parent_id);
    }
    
    /**
     * Convert to array for JSON response
     */
    public function to_array() {
        return [
            'comment_id' => (int) $this->comment_id,
            'faw_id' => (int) $this->faw_id,
            'parent_id' => $this->parent_id ? (int) $this->parent_id : null,
            'content' => $this->content,
            'comment_type' => $this->comment_type,
            'status' => $this->status,
            'user' => [
                'id' => (int) $this->user_id,
                'display_name' => $this->user_display_name,
                'avatar_url' => $this->user_avatar_url,
            ],
            'replies' => array_map(function($reply) {
                return $reply->to_array();
            }, $this->replies),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
