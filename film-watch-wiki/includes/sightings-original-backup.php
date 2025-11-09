<?php
/**
 * Watch Sightings Management
 * Handles the many-to-many-to-many relationships between movies, actors, and watches
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Sightings {

    /**
     * Database table name (without prefix)
     */
    const TABLE_NAME = 'fww_sightings';

    /**
     * Initialize
     */
    public static function init() {
        // Hook for AJAX handlers if needed
    }

    /**
     * Create database table for sightings
     * Called on plugin activation
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            movie_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            character_name varchar(255) DEFAULT NULL,
            watch_id bigint(20) unsigned NOT NULL,
            brand_id bigint(20) unsigned NOT NULL,
            scene_description text DEFAULT NULL,
            verification_level varchar(50) DEFAULT 'unverified',
            timestamp_start varchar(50) DEFAULT NULL,
            timestamp_end varchar(50) DEFAULT NULL,
            screenshot_url varchar(500) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY movie_id (movie_id),
            KEY actor_id (actor_id),
            KEY watch_id (watch_id),
            KEY brand_id (brand_id),
            KEY verification_level (verification_level)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add a new watch sighting
     *
     * @param array $data Sighting data
     * @return int|false Sighting ID on success, false on failure
     */
    public static function add_sighting($data) {
        global $wpdb;

        // Validate required fields
        if (empty($data['movie_id']) || empty($data['actor_id']) || empty($data['watch_id']) || empty($data['brand_id'])) {
            return false;
        }

        $defaults = array(
            'character_name' => '',
            'scene_description' => '',
            'verification_level' => 'unverified',
            'timestamp_start' => '',
            'timestamp_end' => '',
            'screenshot_url' => '',
            'notes' => ''
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME,
            array(
                'movie_id' => intval($data['movie_id']),
                'actor_id' => intval($data['actor_id']),
                'character_name' => sanitize_text_field($data['character_name']),
                'watch_id' => intval($data['watch_id']),
                'brand_id' => intval($data['brand_id']),
                'scene_description' => sanitize_textarea_field($data['scene_description']),
                'verification_level' => sanitize_text_field($data['verification_level']),
                'timestamp_start' => sanitize_text_field($data['timestamp_start']),
                'timestamp_end' => sanitize_text_field($data['timestamp_end']),
                'screenshot_url' => esc_url_raw($data['screenshot_url']),
                'notes' => sanitize_textarea_field($data['notes'])
            ),
            array('%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a watch sighting
     *
     * @param int $sighting_id Sighting ID
     * @param array $data Updated data
     * @return bool Success
     */
    public static function update_sighting($sighting_id, $data) {
        global $wpdb;

        $update_data = array();
        $update_format = array();

        // Only update provided fields
        $allowed_fields = array(
            'movie_id' => '%d',
            'actor_id' => '%d',
            'character_name' => '%s',
            'watch_id' => '%d',
            'brand_id' => '%d',
            'scene_description' => '%s',
            'verification_level' => '%s',
            'timestamp_start' => '%s',
            'timestamp_end' => '%s',
            'screenshot_url' => '%s',
            'notes' => '%s'
        );

        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field])) {
                if (strpos($format, 'd') !== false) {
                    $update_data[$field] = intval($data[$field]);
                } elseif ($field === 'screenshot_url') {
                    $update_data[$field] = esc_url_raw($data[$field]);
                } elseif (in_array($field, array('scene_description', 'notes'))) {
                    $update_data[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
                $update_format[] = $format;
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . self::TABLE_NAME,
            $update_data,
            array('id' => intval($sighting_id)),
            $update_format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a watch sighting
     *
     * @param int $sighting_id Sighting ID
     * @return bool Success
     */
    public static function delete_sighting($sighting_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . self::TABLE_NAME,
            array('id' => intval($sighting_id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get sightings for a specific movie
     *
     * @param int $movie_id Movie post ID
     * @return array Array of sighting objects
     */
    public static function get_sightings_by_movie($movie_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare("
            SELECT s.*,
                   a.post_title as actor_name,
                   w.post_title as watch_name,
                   b.post_title as brand_name
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} a ON s.actor_id = a.ID
            LEFT JOIN {$wpdb->posts} w ON s.watch_id = w.ID
            LEFT JOIN {$wpdb->posts} b ON s.brand_id = b.ID
            WHERE s.movie_id = %d
            ORDER BY a.post_title ASC
        ", intval($movie_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get sightings for a specific actor
     *
     * @param int $actor_id Actor post ID
     * @return array Array of sighting objects
     */
    public static function get_sightings_by_actor($actor_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare("
            SELECT s.*,
                   m.post_title as movie_title,
                   w.post_title as watch_name,
                   b.post_title as brand_name
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} m ON s.movie_id = m.ID
            LEFT JOIN {$wpdb->posts} w ON s.watch_id = w.ID
            LEFT JOIN {$wpdb->posts} b ON s.brand_id = b.ID
            WHERE s.actor_id = %d
            ORDER BY m.post_title ASC
        ", intval($actor_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get sightings for a specific watch
     *
     * @param int $watch_id Watch post ID
     * @return array Array of sighting objects
     */
    public static function get_sightings_by_watch($watch_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare("
            SELECT s.*,
                   m.post_title as movie_title,
                   a.post_title as actor_name,
                   b.post_title as brand_name
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} m ON s.movie_id = m.ID
            LEFT JOIN {$wpdb->posts} a ON s.actor_id = a.ID
            LEFT JOIN {$wpdb->posts} b ON s.brand_id = b.ID
            WHERE s.watch_id = %d
            ORDER BY m.post_title ASC
        ", intval($watch_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get sightings for a specific brand
     *
     * @param int $brand_id Brand post ID
     * @return array Array of sighting objects
     */
    public static function get_sightings_by_brand($brand_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare("
            SELECT s.*,
                   m.post_title as movie_title,
                   a.post_title as actor_name,
                   w.post_title as watch_name
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} m ON s.movie_id = m.ID
            LEFT JOIN {$wpdb->posts} a ON s.actor_id = a.ID
            LEFT JOIN {$wpdb->posts} w ON s.watch_id = w.ID
            WHERE s.brand_id = %d
            ORDER BY m.post_title ASC
        ", intval($brand_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get a single sighting by ID
     *
     * @param int $sighting_id Sighting ID
     * @return object|null Sighting object or null
     */
    public static function get_sighting($sighting_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare("
            SELECT s.*,
                   m.post_title as movie_title,
                   a.post_title as actor_name,
                   w.post_title as watch_name,
                   b.post_title as brand_name
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} m ON s.movie_id = m.ID
            LEFT JOIN {$wpdb->posts} a ON s.actor_id = a.ID
            LEFT JOIN {$wpdb->posts} w ON s.watch_id = w.ID
            LEFT JOIN {$wpdb->posts} b ON s.brand_id = b.ID
            WHERE s.id = %d
        ", intval($sighting_id));

        return $wpdb->get_row($query);
    }

    /**
     * Format sighting as sentence
     * Example: "Daniel Craig as James Bond wears Omega Seamaster in Skyfall (2012)"
     *
     * @param object $sighting Sighting object from database
     * @return string Formatted sentence
     */
    public static function format_as_sentence($sighting) {
        $parts = array();

        // Actor name
        if (!empty($sighting->actor_name)) {
            $parts[] = $sighting->actor_name;
        }

        // Character name (if provided)
        if (!empty($sighting->character_name)) {
            $parts[0] .= ' as ' . $sighting->character_name;
        }

        // Watch brand and name
        $watch_text = '';
        if (!empty($sighting->brand_name)) {
            $watch_text = $sighting->brand_name;
        }
        if (!empty($sighting->watch_name)) {
            $watch_text .= ' ' . $sighting->watch_name;
        }

        if (!empty($watch_text)) {
            $parts[] = 'wears ' . trim($watch_text);
        }

        // Movie title
        if (!empty($sighting->movie_title)) {
            $parts[] = 'in ' . $sighting->movie_title;
        }

        return implode(' ', $parts);
    }
}

// Initialize
FWW_Sightings::init();
