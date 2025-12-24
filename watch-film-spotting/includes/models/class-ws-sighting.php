<?php
/**
 * Sighting model - represents a watch sighting (film + actor + watch + character)
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Sighting {
    
    public $faw_id;
    public $film_id;
    public $actor_id;
    public $character_id;
    public $watch_id;
    
    // Denormalized data from search index
    public $film_title;
    public $film_year;
    public $actor_name;
    public $brand_name;
    public $model_reference;
    public $character_name;
    public $narrative_role;
    public $image_url;
    public $gallery_ids;
    public $source_url;
    public $confidence_level;
    
    // Computed fields
    public $vote_score = 0;
    public $vote_count = 0;
    public $comment_count = 0;
    public $user_vote = null;  // Current user's vote (+1, -1, or null)
    public $editorial_confidence = null;  // From meta table
    
    public $created_at;
    public $updated_at;
    public $created_by;
    
    /**
     * Create from database row
     */
    public static function from_row($row) {
        $sighting = new self();
        
        foreach ($row as $key => $value) {
            if (property_exists($sighting, $key)) {
                $sighting->{$key} = $value;
            }
        }
        
        // Parse gallery_ids if JSON
        if (!empty($sighting->gallery_ids) && is_string($sighting->gallery_ids)) {
            $decoded = json_decode($sighting->gallery_ids, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $sighting->gallery_ids = $decoded;
            }
        }
        
        return $sighting;
    }
    
    /**
     * Get display title for the sighting
     */
    public function get_title() {
        return sprintf(
            '%s wearing %s %s in %s (%d)',
            $this->actor_name,
            $this->brand_name,
            $this->model_reference,
            $this->film_title,
            $this->film_year
        );
    }
    
    /**
     * Get URL-friendly slug
     */
    public function get_slug() {
        return sanitize_title(sprintf(
            '%s-%s-%s-%d',
            $this->actor_name,
            $this->brand_name,
            $this->film_title,
            $this->faw_id
        ));
    }
    
    /**
     * Convert to array for JSON response
     */
    public function to_array() {
        return [
            'faw_id' => (int) $this->faw_id,
            'film' => [
                'id' => (int) $this->film_id,
                'title' => $this->film_title,
                'year' => (int) $this->film_year,
            ],
            'actor' => [
                'id' => (int) $this->actor_id,
                'name' => $this->actor_name,
            ],
            'character' => [
                'id' => (int) $this->character_id,
                'name' => $this->character_name,
            ],
            'watch' => [
                'id' => (int) $this->watch_id,
                'brand' => $this->brand_name,
                'model' => $this->model_reference,
            ],
            'narrative_role' => $this->narrative_role,
            'image_url' => $this->image_url,
            'gallery_ids' => $this->gallery_ids,
            'source_url' => $this->source_url,
            'confidence_level' => $this->confidence_level,
            'editorial_confidence' => $this->editorial_confidence,
            'votes' => [
                'score' => (int) $this->vote_score,
                'count' => (int) $this->vote_count,
                'user_vote' => $this->user_vote,
            ],
            'comment_count' => (int) $this->comment_count,
            'created_at' => $this->created_at,
        ];
    }
}
