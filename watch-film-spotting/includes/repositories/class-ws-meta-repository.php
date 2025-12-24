<?php
/**
 * Sighting meta repository
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Meta_Repository {
    
    /**
     * Get meta value for a sighting
     */
    public static function get($faw_id, $meta_key) {
        global $wpdb;
        $table = ws_table(WS_TABLE_SIGHTING_META);
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $table WHERE faw_id = %d AND meta_key = %s",
            $faw_id,
            $meta_key
        ));
    }
    
    /**
     * Get all meta for a sighting
     */
    public static function get_all($faw_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_SIGHTING_META);
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM $table WHERE faw_id = %d",
            $faw_id
        ));
        
        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->meta_key] = $row->meta_value;
        }
        
        return $meta;
    }
    
    /**
     * Set meta value (upsert)
     */
    public static function set($faw_id, $meta_key, $meta_value) {
        global $wpdb;
        $table = ws_table(WS_TABLE_SIGHTING_META);
        
        $sql = $wpdb->prepare(
            "INSERT INTO $table (faw_id, meta_key, meta_value) 
            VALUES (%d, %s, %s) 
            ON DUPLICATE KEY UPDATE meta_value = %s, updated_at = CURRENT_TIMESTAMP",
            $faw_id,
            $meta_key,
            $meta_value,
            $meta_value
        );
        
        return $wpdb->query($sql) !== false;
    }
    
    /**
     * Delete meta value
     */
    public static function delete($faw_id, $meta_key) {
        global $wpdb;
        $table = ws_table(WS_TABLE_SIGHTING_META);
        
        return $wpdb->delete(
            $table,
            ['faw_id' => $faw_id, 'meta_key' => $meta_key],
            ['%d', '%s']
        );
    }
    
    /**
     * Delete all meta for a sighting
     */
    public static function delete_all($faw_id) {
        global $wpdb;
        $table = ws_table(WS_TABLE_SIGHTING_META);
        
        return $wpdb->delete($table, ['faw_id' => $faw_id], ['%d']);
    }
    
    /**
     * Get sightings by meta value
     */
    public static function get_sightings_by_meta($meta_key, $meta_value, $limit = 50) {
        global $wpdb;
        $table = ws_table(WS_TABLE_SIGHTING_META);
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT faw_id FROM $table WHERE meta_key = %s AND meta_value = %s LIMIT %d",
            $meta_key,
            $meta_value,
            $limit
        ));
    }
}
