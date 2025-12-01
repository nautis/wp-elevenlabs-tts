<?php
/**
 * Brand Repository
 * Handles all database operations for brands
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Brand_Repository {

    private $table_name;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'fwd_brands';
    }

    /**
     * Find brand by ID
     */
    public function find($brand_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE brand_id = %d AND deleted_at IS NULL",
            $brand_id
        ), ARRAY_A);
    }

    /**
     * Find brand by name
     */
    public function find_by_name($name) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE brand_name = %s AND deleted_at IS NULL",
            $name
        ), ARRAY_A);
    }

    /**
     * Search brands by name (LIKE query)
     */
    public function search($name) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE brand_name LIKE %s AND deleted_at IS NULL
             ORDER BY brand_name
             LIMIT 100",
            '%' . $this->wpdb->esc_like($name) . '%'
        ), ARRAY_A);
    }

    /**
     * Get or create brand
     * Returns brand_id
     */
    public function get_or_create($name) {
        // Try to find existing
        $brand = $this->find_by_name($name);

        if ($brand) {
            return $brand['brand_id'];
        }

        // Create new
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_name} (brand_name) VALUES (%s)",
            $name
        ));

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT brand_id FROM {$this->table_name} WHERE brand_name = %s",
            $name
        ));
    }

    /**
     * Get all brands sorted by length (for parser)
     */
    public function all_sorted_by_length() {
        return $this->wpdb->get_col(
            "SELECT brand_name FROM {$this->table_name}
             WHERE deleted_at IS NULL
             ORDER BY LENGTH(brand_name) DESC"
        );
    }

    /**
     * Soft delete a brand
     */
    public function delete($brand_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => current_time('mysql')),
            array('brand_id' => $brand_id)
        );
    }

    /**
     * Restore a soft-deleted brand
     */
    public function restore($brand_id) {
        return $this->wpdb->update(
            $this->table_name,
            array('deleted_at' => null),
            array('brand_id' => $brand_id)
        );
    }

    /**
     * Get all brands
     */
    public function all($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY brand_name",
            ARRAY_A
        );
    }

    /**
     * Count brands
     */
    public function count($include_deleted = false) {
        $where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
    }
}
