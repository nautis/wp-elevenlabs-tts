<?php
/**
 * Entry Repository
 * Handles all database operations for film_actor_watch entries
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Entry_Repository {

    private $table_name;
    private $table_films;
    private $table_actors;
    private $table_brands;
    private $table_watches;
    private $table_characters;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'fwd_film_actor_watch';
        $this->table_films = $wpdb->prefix . 'fwd_films';
        $this->table_actors = $wpdb->prefix . 'fwd_actors';
        $this->table_brands = $wpdb->prefix . 'fwd_brands';
        $this->table_watches = $wpdb->prefix . 'fwd_watches';
        $this->table_characters = $wpdb->prefix . 'fwd_characters';
    }

    /**
     * Find entry by ID
     */
    public function find($faw_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT faw.*, f.title, f.year, a.actor_name, b.brand_name,
                    w.model_reference, c.character_name
             FROM {$this->table_name} faw
             JOIN {$this->table_films} f ON faw.film_id = f.film_id
             JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
             JOIN {$this->table_characters} c ON faw.character_id = c.character_id
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE faw.faw_id = %d AND faw.deleted_at IS NULL",
            $faw_id
        ), ARRAY_A);
    }

    /**
     * Find existing entry by film, actor, and watch
     */
    public function find_by_film_actor_watch($film_id, $actor_id, $watch_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT faw.*, c.character_name
             FROM {$this->table_name} faw
             JOIN {$this->table_characters} c ON faw.character_id = c.character_id
             WHERE faw.film_id = %d AND faw.actor_id = %d AND faw.watch_id = %d
             AND faw.deleted_at IS NULL",
            $film_id, $actor_id, $watch_id
        ), ARRAY_A);
    }

    /**
     * Create new entry
     */
    public function create($data) {
        $user_id = get_current_user_id();

        $insert_data = array(
            'film_id' => $data['film_id'],
            'actor_id' => $data['actor_id'],
            'character_id' => $data['character_id'],
            'watch_id' => $data['watch_id'],
            'narrative_role' => $data['narrative'] ?? '',
            'image_url' => $data['image_url'] ?? '',
            'confidence_level' => $data['confidence_level'] ?? '',
            'created_by' => $user_id ? $user_id : null
        );

        // Handle gallery_ids if provided
        if (isset($data['gallery_ids'])) {
            if (is_array($data['gallery_ids'])) {
                $insert_data['gallery_ids'] = json_encode($data['gallery_ids']);
            } else {
                $insert_data['gallery_ids'] = $data['gallery_ids'];
            }
        }

        $result = $this->wpdb->insert($this->table_name, $insert_data);

        if ($result) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update existing entry
     */
    public function update($faw_id, $data) {
        $user_id = get_current_user_id();

        $update_data = array();

        if (isset($data['character_id'])) $update_data['character_id'] = $data['character_id'];
        if (isset($data['narrative'])) $update_data['narrative_role'] = $data['narrative'];
        if (isset($data['image_url'])) $update_data['image_url'] = $data['image_url'];
        if (isset($data['confidence_level'])) $update_data['confidence_level'] = $data['confidence_level'];
        
        // Handle gallery_ids if provided
        if (isset($data['gallery_ids'])) {
            if (is_array($data['gallery_ids'])) {
                $update_data['gallery_ids'] = json_encode($data['gallery_ids']);
            } else {
                $update_data['gallery_ids'] = $data['gallery_ids'];
            }
        }

        if ($user_id) {
            $update_data['updated_by'] = $user_id;
        }

        return $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('faw_id' => $faw_id)
        );
    }

    /**
     * Query entries by actor
     */
    public function query_by_actor($actor_name) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.title, f.year, b.brand_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.image_url, faw.gallery_ids,
                    faw.source_url, faw.confidence_level, a.actor_name
             FROM {$this->table_name} faw
             JOIN {$this->table_films} f ON faw.film_id = f.film_id
             JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
             JOIN {$this->table_characters} c ON faw.character_id = c.character_id
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE a.actor_name LIKE %s
             AND faw.deleted_at IS NULL
             AND f.deleted_at IS NULL
             AND a.deleted_at IS NULL
             ORDER BY f.year DESC
             LIMIT 100",
            '%' . $this->wpdb->esc_like($actor_name) . '%'
        ), ARRAY_A);
    }

    /**
     * Query entries by brand
     */
    public function query_by_brand($brand_name) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.film_id, f.title, f.year, a.actor_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.image_url, faw.gallery_ids,
                    faw.source_url, faw.confidence_level, b.brand_name
             FROM {$this->table_name} faw
             JOIN {$this->table_films} f ON faw.film_id = f.film_id
             JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
             JOIN {$this->table_characters} c ON faw.character_id = c.character_id
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE b.brand_name LIKE %s
             AND faw.deleted_at IS NULL
             AND f.deleted_at IS NULL
             AND b.deleted_at IS NULL
             ORDER BY f.year DESC, f.title, a.actor_name
             LIMIT 100",
            '%' . $this->wpdb->esc_like($brand_name) . '%'
        ), ARRAY_A);
    }

    /**
     * Query entries by film
     */
    public function query_by_film($film_title) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.title, f.year, f.film_id, a.actor_name, b.brand_name,
                    w.model_reference, c.character_name, faw.narrative_role,
                    faw.image_url, faw.gallery_ids, faw.source_url, faw.confidence_level
             FROM {$this->table_name} faw
             JOIN {$this->table_films} f ON faw.film_id = f.film_id
             JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
             JOIN {$this->table_characters} c ON faw.character_id = c.character_id
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE f.title LIKE %s
             AND faw.deleted_at IS NULL
             AND f.deleted_at IS NULL
             ORDER BY f.year DESC, f.title, a.actor_name
             LIMIT 100",
            '%' . $this->wpdb->esc_like($film_title) . '%'
        ), ARRAY_A);
    }

    /**
     * Get recently added entries
     */
    public function recently_added($limit = 10) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT b.brand_name, w.model_reference, faw.faw_id, faw.gallery_ids
             FROM {$this->table_name} faw
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE faw.deleted_at IS NULL
             ORDER BY faw.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Soft delete an entry
     */
    public function delete($faw_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => current_time('mysql')),
            array('faw_id' => $faw_id)
        );
    }

    /**
     * Restore a soft-deleted entry
     */
    public function restore($faw_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => null),
            array('faw_id' => $faw_id)
        );
    }

    /**
     * Count entries
     */
    public function count($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
    }
}
