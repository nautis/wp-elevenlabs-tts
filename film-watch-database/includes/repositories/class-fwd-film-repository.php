<?php
/**
 * Film Repository
 * Handles all database operations for films
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Film_Repository {

    private $table_name;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'fwd_films';
    }

    /**
     * Find film by ID
     */
    public function find($film_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE film_id = %d AND deleted_at IS NULL",
            $film_id
        ), ARRAY_A);
    }

    /**
     * Find film by title and year
     */
    public function find_by_title_year($title, $year) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE title = %s AND year = %d AND deleted_at IS NULL",
            $title, $year
        ), ARRAY_A);
    }

    /**
     * Search films by title (LIKE query)
     */
    public function search($title) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE title LIKE %s AND deleted_at IS NULL
             ORDER BY year DESC
             LIMIT 100",
            '%' . $this->wpdb->esc_like($title) . '%'
        ), ARRAY_A);
    }

    /**
     * Get or create film
     * Returns film_id
     */
    public function get_or_create($title, $year) {
        // Try to find existing
        $film = $this->find_by_title_year($title, $year);

        if ($film) {
            return $film['film_id'];
        }

        // Create new
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_name} (title, year) VALUES (%s, %d)",
            $title, $year
        ));

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT film_id FROM {$this->table_name} WHERE title = %s AND year = %d",
            $title, $year
        ));
    }

    /**
     * Soft delete a film
     */
    public function delete($film_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => current_time('mysql')),
            array('film_id' => $film_id)
        );
    }

    /**
     * Restore a soft-deleted film
     */
    public function restore($film_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => null),
            array('film_id' => $film_id)
        );
    }

    /**
     * Get all films (with optional filters)
     */
    public function all($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY year DESC, title",
            ARRAY_A
        );
    }

    /**
     * Count films
     */
    public function count($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
    }
}
