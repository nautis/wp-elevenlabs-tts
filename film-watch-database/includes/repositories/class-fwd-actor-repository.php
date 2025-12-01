<?php
/**
 * Actor Repository
 * Handles all database operations for actors
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Actor_Repository {

    private $table_name;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'fwd_actors';
    }

    /**
     * Find actor by ID
     */
    public function find($actor_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE actor_id = %d AND deleted_at IS NULL",
            $actor_id
        ), ARRAY_A);
    }

    /**
     * Find actor by name
     */
    public function find_by_name($name) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE actor_name = %s AND deleted_at IS NULL",
            $name
        ), ARRAY_A);
    }

    /**
     * Search actors by name (LIKE query)
     */
    public function search($name) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE actor_name LIKE %s AND deleted_at IS NULL
             ORDER BY actor_name
             LIMIT 100",
            '%' . $this->wpdb->esc_like($name) . '%'
        ), ARRAY_A);
    }

    /**
     * Get or create actor
     * Returns actor_id
     */
    public function get_or_create($name) {
        // Try to find existing
        $actor = $this->find_by_name($name);

        if ($actor) {
            return $actor['actor_id'];
        }

        // Create new
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_name} (actor_name) VALUES (%s)",
            $name
        ));

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT actor_id FROM {$this->table_name} WHERE actor_name = %s",
            $name
        ));
    }

    /**
     * Soft delete an actor
     */
    public function delete($actor_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => current_time('mysql')),
            array('actor_id' => $actor_id)
        );
    }

    /**
     * Restore a soft-deleted actor
     */
    public function restore($actor_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => null),
            array('actor_id' => $actor_id)
        );
    }

    /**
     * Get all actors
     */
    public function all($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY actor_name",
            ARRAY_A
        );
    }

    /**
     * Count actors
     */
    public function count($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
    }
}
