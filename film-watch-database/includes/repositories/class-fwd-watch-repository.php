<?php
/**
 * Watch Repository
 * Handles all database operations for watches
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Watch_Repository {

    private $table_name;
    private $table_brands;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'fwd_watches';
        $this->table_brands = $wpdb->prefix . 'fwd_brands';
    }

    /**
     * Find watch by ID
     */
    public function find($watch_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT w.*, b.brand_name
             FROM {$this->table_name} w
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE w.watch_id = %d AND w.deleted_at IS NULL",
            $watch_id
        ), ARRAY_A);
    }

    /**
     * Find watch by brand name and model
     */
    public function find_by_brand_model($brand_name, $model) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT w.*, b.brand_name
             FROM {$this->table_name} w
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE b.brand_name = %s AND w.model_reference = %s
             AND w.deleted_at IS NULL",
            $brand_name, $model
        ), ARRAY_A);
    }

    /**
     * Get or create watch
     * Returns watch_id
     */
    public function get_or_create($brand_id, $model) {
        // Try to find existing
        $watch_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT watch_id FROM {$this->table_name}
             WHERE brand_id = %d AND model_reference = %s AND deleted_at IS NULL",
            $brand_id, $model
        ));

        if ($watch_id) {
            return $watch_id;
        }

        // Create new
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_name} (brand_id, model_reference) VALUES (%d, %s)",
            $brand_id, $model
        ));

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT watch_id FROM {$this->table_name}
             WHERE brand_id = %d AND model_reference = %s",
            $brand_id, $model
        ));
    }

    /**
     * Get all watches for a brand
     */
    public function find_by_brand($brand_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE brand_id = %d AND deleted_at IS NULL
             ORDER BY model_reference",
            $brand_id
        ), ARRAY_A);
    }

    /**
     * Soft delete a watch
     */
    public function delete($watch_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => current_time('mysql')),
            array('watch_id' => $watch_id)
        );
    }

    /**
     * Restore a soft-deleted watch
     */
    public function restore($watch_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => null),
            array('watch_id' => $watch_id)
        );
    }

    /**
     * Count watches
     */
    public function count($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
    }
}
