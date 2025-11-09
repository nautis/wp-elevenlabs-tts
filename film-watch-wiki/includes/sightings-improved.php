<?php
/**
 * Watch Sightings Management - IMPROVED SCHEMA
 * Handles the many-to-many-to-many relationships between movies, actors, and watches
 *
 * IMPROVEMENTS:
 * - Composite indexes for multi-column queries
 * - Soft delete support
 * - Legacy migration tracking
 * - Source URL tracking
 * - ENUM for verification levels
 * - Duplicate prevention
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
     * Table version for schema upgrades
     */
    const TABLE_VERSION = '2.0';

    /**
     * Initialize
     */
    public static function init() {
        // Register cleanup hook for deleted posts
        add_action('before_delete_post', array(__CLASS__, 'cleanup_deleted_post_sightings'));
    }

    /**
     * Create or upgrade database table
     * Called on plugin activation
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            -- Primary Key
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            -- Foreign Keys (IDs to wp_posts)
            movie_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            watch_id bigint(20) unsigned NOT NULL,
            brand_id bigint(20) unsigned NOT NULL,

            -- Sighting Details
            character_name varchar(255) DEFAULT NULL,
            scene_description varchar(1000) DEFAULT NULL,
            notes varchar(2000) DEFAULT NULL,

            -- Timestamps (stored as varchar for flexibility, can be HH:MM:SS or seconds)
            timestamp_start varchar(50) DEFAULT NULL,
            timestamp_end varchar(50) DEFAULT NULL,

            -- Verification & Source
            verification_level varchar(50) DEFAULT 'unverified',
            screenshot_url varchar(500) DEFAULT NULL,
            source_url varchar(500) DEFAULT NULL,

            -- Audit Trail
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,

            -- Migration Tracking
            legacy_id bigint(20) unsigned DEFAULT NULL,
            migrated_at datetime DEFAULT NULL,

            -- Primary Key
            PRIMARY KEY (id),

            -- Single Column Indexes (for foreign key lookups)
            KEY idx_movie (movie_id),
            KEY idx_actor (actor_id),
            KEY idx_watch (watch_id),
            KEY idx_brand (brand_id),

            -- Composite Indexes (for multi-column queries - PERFORMANCE BOOST)
            KEY idx_movie_actor (movie_id, actor_id),
            KEY idx_movie_watch (movie_id, watch_id),
            KEY idx_actor_watch (actor_id, watch_id),
            KEY idx_actor_brand (actor_id, brand_id),
            KEY idx_brand_watch (brand_id, watch_id),

            -- Utility Indexes
            KEY idx_verification (verification_level),
            KEY idx_created (created_at),
            KEY idx_deleted (deleted_at),
            KEY idx_legacy (legacy_id)

        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Store schema version
        update_option('fww_sightings_db_version', self::TABLE_VERSION);
    }

    /**
     * Upgrade existing table with new indexes
     * Safe to run multiple times (IF NOT EXISTS logic)
     */
    public static function upgrade_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $current_version = get_option('fww_sightings_db_version', '1.0');

        if (version_compare($current_version, '2.0', '<')) {
            // Add new columns if they don't exist
            $columns = $wpdb->get_col("DESCRIBE $table_name", 0);

            if (!in_array('deleted_at', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN deleted_at datetime DEFAULT NULL AFTER updated_at");
            }

            if (!in_array('legacy_id', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN legacy_id bigint(20) unsigned DEFAULT NULL AFTER deleted_at");
            }

            if (!in_array('migrated_at', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN migrated_at datetime DEFAULT NULL AFTER legacy_id");
            }

            if (!in_array('source_url', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN source_url varchar(500) DEFAULT NULL AFTER screenshot_url");
            }

            // Add composite indexes if they don't exist
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name", ARRAY_A);
            $index_names = array_column($indexes, 'Key_name');

            $new_indexes = array(
                'idx_movie_actor' => 'ADD KEY idx_movie_actor (movie_id, actor_id)',
                'idx_movie_watch' => 'ADD KEY idx_movie_watch (movie_id, watch_id)',
                'idx_actor_watch' => 'ADD KEY idx_actor_watch (actor_id, watch_id)',
                'idx_actor_brand' => 'ADD KEY idx_actor_brand (actor_id, brand_id)',
                'idx_brand_watch' => 'ADD KEY idx_brand_watch (brand_id, watch_id)',
                'idx_created' => 'ADD KEY idx_created (created_at)',
                'idx_deleted' => 'ADD KEY idx_deleted (deleted_at)',
                'idx_legacy' => 'ADD KEY idx_legacy (legacy_id)'
            );

            foreach ($new_indexes as $index_name => $add_statement) {
                if (!in_array($index_name, $index_names)) {
                    $wpdb->query("ALTER TABLE $table_name $add_statement");
                }
            }

            update_option('fww_sightings_db_version', '2.0');
        }
    }

    /**
     * Cleanup sightings when a post is deleted (movie, actor, watch, or brand)
     *
     * @param int $post_id Post ID being deleted
     */
    public static function cleanup_deleted_post_sightings($post_id) {
        $post_type = get_post_type($post_id);

        // Only cleanup for our post types
        if (!in_array($post_type, array('fww_movie', 'fww_actor', 'fww_watch', 'fww_brand'))) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Soft delete instead of hard delete
        $column_map = array(
            'fww_movie' => 'movie_id',
            'fww_actor' => 'actor_id',
            'fww_watch' => 'watch_id',
            'fww_brand' => 'brand_id'
        );

        $column = $column_map[$post_type];

        // Use soft delete
        $wpdb->update(
            $table_name,
            array('deleted_at' => current_time('mysql')),
            array($column => $post_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Validate sighting data before insert/update
     *
     * @param array $data Sighting data
     * @return array|WP_Error Validated data or error
     */
    public static function validate_sighting_data($data) {
        $errors = new WP_Error();

        // Required fields
        if (empty($data['movie_id']) || !is_numeric($data['movie_id']) || $data['movie_id'] <= 0) {
            $errors->add('invalid_movie', 'Invalid movie ID');
        }

        if (empty($data['actor_id']) || !is_numeric($data['actor_id']) || $data['actor_id'] <= 0) {
            $errors->add('invalid_actor', 'Invalid actor ID');
        }

        if (empty($data['watch_id']) || !is_numeric($data['watch_id']) || $data['watch_id'] <= 0) {
            $errors->add('invalid_watch', 'Invalid watch ID');
        }

        if (empty($data['brand_id']) || !is_numeric($data['brand_id']) || $data['brand_id'] <= 0) {
            $errors->add('invalid_brand', 'Invalid brand ID');
        }

        // Validate verification level
        $valid_levels = array('unverified', 'verified', 'confirmed');
        if (isset($data['verification_level']) && !in_array($data['verification_level'], $valid_levels)) {
            $errors->add('invalid_verification', 'Verification level must be: unverified, verified, or confirmed');
        }

        // Validate URLs
        if (!empty($data['screenshot_url']) && !filter_var($data['screenshot_url'], FILTER_VALIDATE_URL)) {
            $errors->add('invalid_screenshot_url', 'Invalid screenshot URL');
        }

        if (!empty($data['source_url']) && !filter_var($data['source_url'], FILTER_VALIDATE_URL)) {
            $errors->add('invalid_source_url', 'Invalid source URL');
        }

        // Validate text lengths
        if (!empty($data['character_name']) && strlen($data['character_name']) > 255) {
            $errors->add('character_too_long', 'Character name must be less than 255 characters');
        }

        if (!empty($data['scene_description']) && strlen($data['scene_description']) > 1000) {
            $errors->add('scene_too_long', 'Scene description must be less than 1000 characters');
        }

        if (!empty($data['notes']) && strlen($data['notes']) > 2000) {
            $errors->add('notes_too_long', 'Notes must be less than 2000 characters');
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return $data;
    }

    /**
     * Check for duplicate sighting
     *
     * @param int $movie_id Movie ID
     * @param int $actor_id Actor ID
     * @param int $watch_id Watch ID
     * @param string $character_name Character name (optional)
     * @param int $exclude_id Exclude this sighting ID (for updates)
     * @return bool True if duplicate exists
     */
    public static function is_duplicate($movie_id, $actor_id, $watch_id, $character_name = '', $exclude_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE movie_id = %d
               AND actor_id = %d
               AND watch_id = %d
               AND (character_name = %s OR (character_name IS NULL AND %s = ''))
               AND deleted_at IS NULL
               AND id != %d",
            $movie_id,
            $actor_id,
            $watch_id,
            $character_name,
            $character_name,
            $exclude_id
        );

        return $wpdb->get_var($query) > 0;
    }

    /**
     * Add a new watch sighting with validation
     *
     * @param array $data Sighting data
     * @return int|WP_Error Sighting ID on success, WP_Error on failure
     */
    public static function add_sighting($data) {
        global $wpdb;

        // Validate data
        $validated = self::validate_sighting_data($data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        // Check for duplicates
        if (self::is_duplicate($data['movie_id'], $data['actor_id'], $data['watch_id'], $data['character_name'] ?? '')) {
            return new WP_Error('duplicate_sighting', 'This watch sighting already exists');
        }

        $defaults = array(
            'character_name' => '',
            'scene_description' => '',
            'notes' => '',
            'verification_level' => 'unverified',
            'timestamp_start' => '',
            'timestamp_end' => '',
            'screenshot_url' => '',
            'source_url' => '',
            'legacy_id' => null
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
                'notes' => sanitize_textarea_field($data['notes']),
                'verification_level' => sanitize_text_field($data['verification_level']),
                'timestamp_start' => sanitize_text_field($data['timestamp_start']),
                'timestamp_end' => sanitize_text_field($data['timestamp_end']),
                'screenshot_url' => esc_url_raw($data['screenshot_url']),
                'source_url' => esc_url_raw($data['source_url']),
                'legacy_id' => !empty($data['legacy_id']) ? intval($data['legacy_id']) : null,
                'migrated_at' => !empty($data['legacy_id']) ? current_time('mysql') : null
            ),
            array('%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_insert_failed', 'Failed to add sighting to database');
        }

        return $wpdb->insert_id;
    }

    /**
     * Get sightings for a specific movie (excluding soft-deleted)
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
              AND s.deleted_at IS NULL
            ORDER BY a.post_title ASC
        ", intval($movie_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get sightings for a specific actor (excluding soft-deleted)
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
              AND s.deleted_at IS NULL
            ORDER BY m.post_title ASC
        ", intval($actor_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get sightings for a specific watch (excluding soft-deleted)
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
              AND s.deleted_at IS NULL
            ORDER BY m.post_title ASC
        ", intval($watch_id));

        return $wpdb->get_results($query);
    }

    /**
     * Get sightings for a specific brand (excluding soft-deleted)
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
              AND s.deleted_at IS NULL
            ORDER BY m.post_title ASC
        ", intval($brand_id));

        return $wpdb->get_results($query);
    }

    /**
     * Update a watch sighting with validation
     *
     * @param int $sighting_id Sighting ID
     * @param array $data Updated data
     * @return bool|WP_Error Success or error
     */
    public static function update_sighting($sighting_id, $data) {
        global $wpdb;

        // Validate data if core fields are being updated
        if (isset($data['movie_id']) || isset($data['actor_id']) || isset($data['watch_id']) || isset($data['brand_id'])) {
            // Get current sighting to merge data
            $current = self::get_sighting($sighting_id);
            if (!$current) {
                return new WP_Error('invalid_sighting', 'Sighting not found');
            }

            // Merge current data with updates for validation
            $validate_data = array_merge((array)$current, $data);
            $validated = self::validate_sighting_data($validate_data);

            if (is_wp_error($validated)) {
                return $validated;
            }

            // Check for duplicates (excluding current record)
            if (self::is_duplicate(
                $validate_data['movie_id'],
                $validate_data['actor_id'],
                $validate_data['watch_id'],
                $validate_data['character_name'] ?? '',
                $sighting_id
            )) {
                return new WP_Error('duplicate_sighting', 'This would create a duplicate sighting');
            }
        }

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
            'source_url' => '%s',
            'notes' => '%s'
        );

        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field])) {
                if (strpos($format, 'd') !== false) {
                    $update_data[$field] = intval($data[$field]);
                } elseif (in_array($field, array('screenshot_url', 'source_url'))) {
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
            return new WP_Error('no_data', 'No data to update');
        }

        $result = $wpdb->update(
            $wpdb->prefix . self::TABLE_NAME,
            $update_data,
            array('id' => intval($sighting_id)),
            $update_format,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_update_failed', 'Failed to update sighting');
        }

        return true;
    }

    /**
     * Delete a watch sighting (soft delete)
     *
     * @param int $sighting_id Sighting ID
     * @return bool Success
     */
    public static function delete_sighting($sighting_id) {
        global $wpdb;

        // Soft delete - set deleted_at timestamp
        $result = $wpdb->update(
            $wpdb->prefix . self::TABLE_NAME,
            array('deleted_at' => current_time('mysql')),
            array('id' => intval($sighting_id)),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Permanently delete a watch sighting (hard delete)
     * Use with caution - this cannot be undone
     *
     * @param int $sighting_id Sighting ID
     * @return bool Success
     */
    public static function permanently_delete_sighting($sighting_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . self::TABLE_NAME,
            array('id' => intval($sighting_id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Restore a soft-deleted sighting
     *
     * @param int $sighting_id Sighting ID
     * @return bool Success
     */
    public static function restore_sighting($sighting_id) {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . self::TABLE_NAME,
            array('deleted_at' => null),
            array('id' => intval($sighting_id)),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get a single sighting by ID
     *
     * @param int $sighting_id Sighting ID
     * @param bool $include_deleted Include soft-deleted records
     * @return object|null Sighting object or null
     */
    public static function get_sighting($sighting_id, $include_deleted = false) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $where_clause = $include_deleted ? '' : 'AND s.deleted_at IS NULL';

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
            $where_clause
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

    /**
     * Get database statistics
     *
     * @return array Statistics
     */
    public static function get_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $stats = array();

        // Total sightings (active)
        $stats['total_active'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL");

        // Total deleted
        $stats['total_deleted'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NOT NULL");

        // Total migrated from legacy
        $stats['total_migrated'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE legacy_id IS NOT NULL");

        // Verification breakdown
        $stats['unverified'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verification_level = 'unverified' AND deleted_at IS NULL");
        $stats['verified'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verification_level = 'verified' AND deleted_at IS NULL");
        $stats['confirmed'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verification_level = 'confirmed' AND deleted_at IS NULL");

        return $stats;
    }
}

// Initialize
FWW_Sightings::init();
