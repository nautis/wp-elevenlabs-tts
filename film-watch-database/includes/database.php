<?php
/**
 * Database Handler - WordPress MySQL Implementation
 * Uses WordPress $wpdb for all database operations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Database {

    private $table_films;
    private $table_brands;
    private $table_watches;
    private $table_actors;
    private $table_characters;
    private $table_film_actor_watch;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        // Define table names with WordPress prefix
        $this->table_films = $wpdb->prefix . 'fwd_films';
        $this->table_brands = $wpdb->prefix . 'fwd_brands';
        $this->table_watches = $wpdb->prefix . 'fwd_watches';
        $this->table_actors = $wpdb->prefix . 'fwd_actors';
        $this->table_characters = $wpdb->prefix . 'fwd_characters';
        $this->table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

        // Only run schema setup if version has changed or tables don't exist
        $current_version = get_option('fwd_db_version', '0');
        $target_version = '4.0';

        if (version_compare($current_version, $target_version, '<') || !$this->tables_exist()) {
            $this->create_tables();
            $this->migrate_database();
            $this->add_performance_indexes();
            $this->seed_brands();
        }
    }

    /**
     * Check if database tables exist
     */
    private function tables_exist() {
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_films}'");
        return !empty($table_exists);
    }

    /**
     * Create database tables if they don't exist
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE {$this->table_films} (
            film_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            year int(4) NOT NULL,
            PRIMARY KEY (film_id),
            UNIQUE KEY title_year (title, year)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$this->table_brands} (
            brand_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            brand_name varchar(100) NOT NULL,
            PRIMARY KEY (brand_id),
            UNIQUE KEY brand_name (brand_name)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$this->table_watches} (
            watch_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) UNSIGNED NOT NULL,
            model_reference varchar(255) NOT NULL,
            verification_level varchar(50) DEFAULT NULL,
            PRIMARY KEY (watch_id),
            UNIQUE KEY brand_model (brand_id, model_reference),
            KEY brand_id (brand_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$this->table_actors} (
            actor_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_name varchar(255) NOT NULL,
            PRIMARY KEY (actor_id),
            UNIQUE KEY actor_name (actor_name)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$this->table_characters} (
            character_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            character_name varchar(255) NOT NULL,
            PRIMARY KEY (character_id),
            KEY character_name (character_name)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$this->table_film_actor_watch} (
            faw_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            film_id bigint(20) UNSIGNED NOT NULL,
            actor_id bigint(20) UNSIGNED NOT NULL,
            character_id bigint(20) UNSIGNED NOT NULL,
            watch_id bigint(20) UNSIGNED NOT NULL,
            narrative_role text,
            image_url text,
            source_url text,
            PRIMARY KEY (faw_id),
            UNIQUE KEY unique_entry (film_id, actor_id, character_id, watch_id),
            KEY film_id (film_id),
            KEY actor_id (actor_id),
            KEY character_id (character_id),
            KEY watch_id (watch_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    /**
     * Migrate database schema for upgrades
     * Adds new columns to existing databases
     */
    private function migrate_database() {
        global $wpdb;

        // Use transient-based locking to prevent concurrent migrations
        $lock_key = 'fwd_migration_lock';
        if (get_transient($lock_key)) {
            return; // Another process is running migrations
        }
        set_transient($lock_key, true, 60); // Lock for 60 seconds max

        try {
            $db_version = get_option('fwd_db_version', '1.0');

            // Migration 1.0 -> 2.0: Add image_url and source_url
            if (version_compare($db_version, '2.0', '<')) {
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_film_actor_watch}");
                $has_image_url = false;
                $has_source_url = false;

                foreach ($columns as $column) {
                    if ($column->Field === 'image_url') {
                        $has_image_url = true;
                    }
                    if ($column->Field === 'source_url') {
                        $has_source_url = true;
                    }
                }

                if (!$has_image_url) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} ADD COLUMN image_url text");
                    error_log('FWD Database: Added image_url column');
                }

                if (!$has_source_url) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} ADD COLUMN source_url text");
                    error_log('FWD Database: Added source_url column');
                }

                update_option('fwd_db_version', '2.0');
            }

            // Migration 2.0 -> 3.0: Remove verification_level, remove source_url, add confidence_level
            if (version_compare($db_version, '3.0', '<')) {
                // Check current columns
                $watch_columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_watches}");
                $faw_columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_film_actor_watch}");

                // Remove verification_level from watches table
                foreach ($watch_columns as $column) {
                    if ($column->Field === 'verification_level') {
                        $wpdb->query("ALTER TABLE {$this->table_watches} DROP COLUMN verification_level");
                        error_log('FWD Database: Removed verification_level column from watches');
                        break;
                    }
                }

                $has_source_url = false;
                $has_confidence_level = false;

                foreach ($faw_columns as $column) {
                    if ($column->Field === 'source_url') {
                        $has_source_url = true;
                    }
                    if ($column->Field === 'confidence_level') {
                        $has_confidence_level = true;
                    }
                }

                // Remove source_url from film_actor_watch table
                if ($has_source_url) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} DROP COLUMN source_url");
                    error_log('FWD Database: Removed source_url column from film_actor_watch');
                }

                // Add confidence_level to film_actor_watch table
                if (!$has_confidence_level) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} ADD COLUMN confidence_level text");
                    error_log('FWD Database: Added confidence_level column to film_actor_watch');
                }

                update_option('fwd_db_version', '3.0');
            }

            // Migration 3.0 -> 3.1: Add timestamp columns for recently_added shortcode
            if (version_compare($db_version, '3.1', '<')) {
                $faw_columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_film_actor_watch}");
                $has_created_at = false;
                $has_updated_at = false;

                foreach ($faw_columns as $column) {
                    if ($column->Field === 'created_at') {
                        $has_created_at = true;
                    }
                    if ($column->Field === 'updated_at') {
                        $has_updated_at = true;
                    }
                }

                if (!$has_created_at) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    error_log('FWD Migration 3.1: Added created_at column');
                }

                if (!$has_updated_at) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    error_log('FWD Migration 3.1: Added updated_at column');
                }

                update_option('fwd_db_version', '3.1');
            }

            // Migration 3.1 -> 3.2: Add foreign key constraints for referential integrity
            if (version_compare($db_version, '3.2', '<')) {
                // Check if foreign keys already exist
                $fks = $wpdb->get_results("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$this->table_film_actor_watch}'
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ");

                $existing_fks = array();
                foreach ($fks as $fk) {
                    $existing_fks[] = $fk->CONSTRAINT_NAME;
                }

                // Add foreign key constraints only if they don't exist
                if (!in_array('fk_faw_film', $existing_fks)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD CONSTRAINT fk_faw_film
                        FOREIGN KEY (film_id) REFERENCES {$this->table_films}(film_id)
                        ON DELETE CASCADE");
                    error_log('FWD Migration 3.2: Added foreign key fk_faw_film');
                }

                if (!in_array('fk_faw_actor', $existing_fks)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD CONSTRAINT fk_faw_actor
                        FOREIGN KEY (actor_id) REFERENCES {$this->table_actors}(actor_id)
                        ON DELETE CASCADE");
                    error_log('FWD Migration 3.2: Added foreign key fk_faw_actor');
                }

                if (!in_array('fk_faw_character', $existing_fks)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD CONSTRAINT fk_faw_character
                        FOREIGN KEY (character_id) REFERENCES {$this->table_characters}(character_id)
                        ON DELETE CASCADE");
                    error_log('FWD Migration 3.2: Added foreign key fk_faw_character');
                }

                if (!in_array('fk_faw_watch', $existing_fks)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD CONSTRAINT fk_faw_watch
                        FOREIGN KEY (watch_id) REFERENCES {$this->table_watches}(watch_id)
                        ON DELETE CASCADE");
                    error_log('FWD Migration 3.2: Added foreign key fk_faw_watch');
                }

                // Add foreign key for watches -> brands
                $watch_fks = $wpdb->get_results("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$this->table_watches}'
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ");

                $watch_existing_fks = array();
                foreach ($watch_fks as $fk) {
                    $watch_existing_fks[] = $fk->CONSTRAINT_NAME;
                }

                if (!in_array('fk_watch_brand', $watch_existing_fks)) {
                    $wpdb->query("ALTER TABLE {$this->table_watches}
                        ADD CONSTRAINT fk_watch_brand
                        FOREIGN KEY (brand_id) REFERENCES {$this->table_brands}(brand_id)
                        ON DELETE CASCADE");
                    error_log('FWD Migration 3.2: Added foreign key fk_watch_brand');
                }

                update_option('fwd_db_version', '3.2');
            }

            // Migration 3.2 -> 3.3: Remove redundant indexes and add CHECK constraints
            if (version_compare($db_version, '3.3', '<')) {
                // Remove redundant indexes from film_actor_watch
                $faw_indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_film_actor_watch}");
                $index_names = array();
                foreach ($faw_indexes as $idx) {
                    $index_names[] = $idx->Key_name;
                }

                // Drop redundant single-column indexes (covered by composite indexes)
                if (in_array('actor_id', $index_names)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} DROP INDEX actor_id");
                    error_log('FWD Migration 3.3: Dropped redundant actor_id index');
                }

                if (in_array('film_id', $index_names)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} DROP INDEX film_id");
                    error_log('FWD Migration 3.3: Dropped redundant film_id index');
                }

                // Add CHECK constraint for year range
                $wpdb->query("ALTER TABLE {$this->table_films}
                    ADD CONSTRAINT check_year_range
                    CHECK (year >= 1900 AND year <= 2100)");
                error_log('FWD Migration 3.3: Added year range constraint');

                // Add covering index for actor queries
                if (!in_array('idx_actor_watch_cover', $index_names)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD INDEX idx_actor_watch_cover (actor_id, watch_id, film_id, created_at)");
                    error_log('FWD Migration 3.3: Added covering index for actor queries');
                }

                // Add descending index for recently added queries
                if (!in_array('idx_created_desc', $index_names)) {
                    $wpdb->query("CREATE INDEX idx_created_desc ON {$this->table_film_actor_watch} (created_at DESC)");
                    error_log('FWD Migration 3.3: Added descending created_at index');
                }

                update_option('fwd_db_version', '3.3');
            }

            // Migration 3.3 -> 3.4: Add soft delete support
            if (version_compare($db_version, '3.4', '<')) {
                // Add deleted_at columns to all tables
                $tables_to_update = array(
                    $this->table_films,
                    $this->table_actors,
                    $this->table_brands,
                    $this->table_watches,
                    $this->table_characters,
                    $this->table_film_actor_watch
                );

                foreach ($tables_to_update as $table) {
                    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
                    $has_deleted_at = false;

                    foreach ($columns as $column) {
                        if ($column->Field === 'deleted_at') {
                            $has_deleted_at = true;
                            break;
                        }
                    }

                    if (!$has_deleted_at) {
                        $wpdb->query("ALTER TABLE {$table} ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
                        error_log("FWD Migration 3.4: Added deleted_at to {$table}");
                    }
                }

                // Add index on deleted_at for filtering
                $wpdb->query("CREATE INDEX idx_deleted_at ON {$this->table_film_actor_watch} (deleted_at)");
                error_log('FWD Migration 3.4: Added deleted_at index');

                update_option('fwd_db_version', '3.4');
            }

            // Migration 3.4 -> 3.5: Add audit columns
            if (version_compare($db_version, '3.5', '<')) {
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_film_actor_watch}");
                $has_created_by = false;
                $has_updated_by = false;

                foreach ($columns as $column) {
                    if ($column->Field === 'created_by') $has_created_by = true;
                    if ($column->Field === 'updated_by') $has_updated_by = true;
                }

                if (!$has_created_by) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD COLUMN created_by bigint(20) UNSIGNED NULL DEFAULT NULL");
                    error_log('FWD Migration 3.5: Added created_by column');
                }

                if (!$has_updated_by) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD COLUMN updated_by bigint(20) UNSIGNED NULL DEFAULT NULL");
                    error_log('FWD Migration 3.5: Added updated_by column');
                }

                update_option('fwd_db_version', '3.5');
            }

            // Migration 3.5 -> 3.6: Create audit history table
            if (version_compare($db_version, '3.6', '<')) {
                $audit_table = $wpdb->prefix . 'fwd_audit_log';

                $wpdb->query("CREATE TABLE IF NOT EXISTS {$audit_table} (
                    log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    table_name varchar(50) NOT NULL,
                    record_id bigint(20) UNSIGNED NOT NULL,
                    action enum('INSERT', 'UPDATE', 'DELETE') NOT NULL,
                    old_values JSON,
                    new_values JSON,
                    user_id bigint(20) UNSIGNED,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (log_id),
                    INDEX idx_table_record (table_name, record_id),
                    INDEX idx_created (created_at),
                    INDEX idx_user (user_id)
                ) {$wpdb->get_charset_collate()}");

                error_log('FWD Migration 3.6: Created audit log table');
                update_option('fwd_db_version', '3.6');
            }

            // Migration 3.6 -> 3.7: Create denormalized search index with FULLTEXT
            if (version_compare($db_version, '3.7', '<')) {
                $search_table = $wpdb->prefix . 'fwd_search_index';

                $wpdb->query("CREATE TABLE IF NOT EXISTS {$search_table} (
                    index_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    faw_id bigint(20) UNSIGNED NOT NULL,
                    film_title varchar(255) NOT NULL,
                    film_year int(4) NOT NULL,
                    actor_name varchar(255) NOT NULL,
                    brand_name varchar(100) NOT NULL,
                    model_reference varchar(255) NOT NULL,
                    character_name varchar(255) NOT NULL,
                    narrative_role text,
                    image_url text,
                    source_url text,
                    confidence_level text,
                    search_text text NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    deleted_at TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (index_id),
                    UNIQUE KEY unique_faw (faw_id),
                    FULLTEXT KEY ft_search (search_text),
                    INDEX idx_film (film_title),
                    INDEX idx_actor (actor_name),
                    INDEX idx_brand (brand_name),
                    INDEX idx_created (created_at DESC),
                    INDEX idx_deleted (deleted_at)
                ) {$wpdb->get_charset_collate()}");

                error_log('FWD Migration 3.7: Created search index table with FULLTEXT');

                // Populate search index from existing data
                $faw_table = $wpdb->prefix . 'fwd_film_actor_watch';

                $count = $wpdb->query("
                    INSERT INTO {$search_table}
                        (faw_id, film_title, film_year, actor_name, brand_name,
                         model_reference, character_name, narrative_role,
                         image_url, source_url, confidence_level, search_text,
                         created_at, deleted_at)
                    SELECT
                        faw.faw_id,
                        f.title,
                        f.year,
                        a.actor_name,
                        b.brand_name,
                        w.model_reference,
                        c.character_name,
                        faw.narrative_role,
                        faw.image_url,
                        faw.source_url,
                        faw.confidence_level,
                        CONCAT_WS(' ',
                            f.title,
                            f.year,
                            a.actor_name,
                            b.brand_name,
                            w.model_reference,
                            c.character_name,
                            faw.narrative_role
                        ),
                        faw.created_at,
                        faw.deleted_at
                    FROM {$faw_table} faw
                    JOIN {$this->table_films} f ON faw.film_id = f.film_id
                    JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
                    JOIN {$this->table_characters} c ON faw.character_id = c.character_id
                    JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
                    JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
                ");

                error_log("FWD Migration 3.7: Populated search index with {$count} entries");
                update_option('fwd_db_version', '3.7');
            }


            // Migration 3.7 -> 3.8: Add gallery_ids column for multiple images
            if (version_compare($db_version, '3.8', '<')) {
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_film_actor_watch}");
                $has_gallery_ids = false;

                foreach ($columns as $column) {
                    if ($column->Field === 'gallery_ids') {
                        $has_gallery_ids = true;
                        break;
                    }
                }

                if (!$has_gallery_ids) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD COLUMN gallery_ids TEXT NULL AFTER image_url");
                    error_log('FWD Migration 3.8: Added gallery_ids column');
                }

                // Migrate existing image_url to gallery_ids
                // Get entries with image_url but no gallery_ids
                $entries = $wpdb->get_results("
                    SELECT faw_id, image_url
                    FROM {$this->table_film_actor_watch}
                    WHERE image_url IS NOT NULL
                    AND image_url != ''
                    AND (gallery_ids IS NULL OR gallery_ids = '')
                ");

                $migrated = 0;
                foreach ($entries as $entry) {
                    // Use WordPress's built-in function to find attachment ID
                    $attachment_id = attachment_url_to_postid($entry->image_url);

                    if ($attachment_id) {
                        // Create JSON array with single attachment ID
                        $gallery_json = json_encode([$attachment_id]);

                        $wpdb->update(
                            $this->table_film_actor_watch,
                            ['gallery_ids' => $gallery_json],
                            ['faw_id' => $entry->faw_id],
                            ['%s'],
                            ['%d']
                        );
                        $migrated++;
                    }
                }

                error_log("FWD Migration 3.8: Migrated {$migrated} image URLs to gallery_ids");
                update_option('fwd_db_version', '3.8');
            }

            // Migration 3.8 -> 3.9: Add gallery_ids to search index table
            if (version_compare($db_version, '3.9', '<')) {
                $search_table = $wpdb->prefix . 'fwd_search_index';
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$search_table}");
                $has_gallery_ids = false;

                foreach ($columns as $column) {
                    if ($column->Field === 'gallery_ids') {
                        $has_gallery_ids = true;
                        break;
                    }
                }

                if (!$has_gallery_ids) {
                    $wpdb->query("ALTER TABLE {$search_table}
                        ADD COLUMN gallery_ids TEXT NULL AFTER image_url");
                    error_log('FWD Migration 3.9: Added gallery_ids column to search index');

                    // Rebuild search index to populate gallery_ids
                    $wpdb->query("TRUNCATE TABLE {$search_table}");

                    $count = $wpdb->query("
                        INSERT INTO {$search_table}
                            (faw_id, film_title, film_year, actor_name, brand_name,
                             model_reference, character_name, narrative_role,
                             image_url, gallery_ids, source_url, confidence_level, search_text,
                             created_at, deleted_at)
                        SELECT
                            faw.faw_id,
                            f.title,
                            f.year,
                            a.actor_name,
                            b.brand_name,
                            w.model_reference,
                            c.character_name,
                            faw.narrative_role,
                            faw.image_url,
                            faw.gallery_ids,
                            faw.source_url,
                            faw.confidence_level,
                            CONCAT_WS(' ',
                                f.title,
                                f.year,
                                a.actor_name,
                                b.brand_name,
                                w.model_reference,
                                c.character_name,
                                faw.narrative_role
                            ),
                            faw.created_at,
                            faw.deleted_at
                        FROM {$this->table_film_actor_watch} faw
                        JOIN {$this->table_films} f ON faw.film_id = f.film_id
                        JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
                        JOIN {$this->table_characters} c ON faw.character_id = c.character_id
                        JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
                        JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
                    ");

                    error_log("FWD Migration 3.9: Rebuilt search index with gallery_ids ({$count} entries)");
                }

                update_option('fwd_db_version', '3.9');
            }

            // Migration 3.9 -> 4.0: Add regallery_id column for RegGallery integration
            if (version_compare($db_version, '4.0', '<')) {
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_film_actor_watch}");
                $has_regallery_id = false;

                foreach ($columns as $column) {
                    if ($column->Field === 'regallery_id') {
                        $has_regallery_id = true;
                        break;
                    }
                }

                if (!$has_regallery_id) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                        ADD COLUMN regallery_id BIGINT(20) UNSIGNED NULL AFTER gallery_ids");
                    error_log('FWD Migration 4.0: Added regallery_id column');
                }

                // Also add to search index table
                $search_table = $wpdb->prefix . 'fwd_search_index';
                $search_columns = $wpdb->get_results("SHOW COLUMNS FROM {$search_table}");
                $search_has_regallery_id = false;

                foreach ($search_columns as $column) {
                    if ($column->Field === 'regallery_id') {
                        $search_has_regallery_id = true;
                        break;
                    }
                }

                if (!$search_has_regallery_id) {
                    $wpdb->query("ALTER TABLE {$search_table}
                        ADD COLUMN regallery_id BIGINT(20) UNSIGNED NULL AFTER gallery_ids");
                    error_log('FWD Migration 4.0: Added regallery_id column to search index');
                }

                update_option('fwd_db_version', '4.0');
            }
            // Release lock
            delete_transient($lock_key);
        } catch (Exception $e) {
            delete_transient($lock_key);
            error_log('FWD Database Migration Error: ' . $e->getMessage());
        }
    }

    /**
     * Add performance indexes for frequently queried columns
     * Only runs once per index - checks if index exists before adding
     */
    private function add_performance_indexes() {
        global $wpdb;

        // Check if indexes have already been added
        if (get_option('fwd_performance_indexes_added', false)) {
            return;
        }

        try {
            // Get existing indexes
            $existing_indexes = array();

            // Check actors table indexes
            $actor_indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_actors}");
            foreach ($actor_indexes as $idx) {
                $existing_indexes[$this->table_actors][] = $idx->Key_name;
            }

            // Check brands table indexes
            $brand_indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_brands}");
            foreach ($brand_indexes as $idx) {
                $existing_indexes[$this->table_brands][] = $idx->Key_name;
            }

            // Check films table indexes
            $film_indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_films}");
            foreach ($film_indexes as $idx) {
                $existing_indexes[$this->table_films][] = $idx->Key_name;
            }

            // Check film_actor_watch table indexes
            $faw_indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_film_actor_watch}");
            foreach ($faw_indexes as $idx) {
                $existing_indexes[$this->table_film_actor_watch][] = $idx->Key_name;
            }

            // Add actor_name index for LIKE searches
            if (!isset($existing_indexes[$this->table_actors]) || !in_array('idx_actor_name', $existing_indexes[$this->table_actors])) {
                $wpdb->query("ALTER TABLE {$this->table_actors} ADD INDEX idx_actor_name (actor_name(50))");
                error_log('FWD: Added index idx_actor_name to actors table');
            }

            // Add brand_name index for LIKE searches
            if (!isset($existing_indexes[$this->table_brands]) || !in_array('idx_brand_name', $existing_indexes[$this->table_brands])) {
                $wpdb->query("ALTER TABLE {$this->table_brands} ADD INDEX idx_brand_name (brand_name(50))");
                error_log('FWD: Added index idx_brand_name to brands table');
            }

            // Add title index for LIKE searches
            if (!isset($existing_indexes[$this->table_films]) || !in_array('idx_title', $existing_indexes[$this->table_films])) {
                $wpdb->query("ALTER TABLE {$this->table_films} ADD INDEX idx_title (title(100))");
                error_log('FWD: Added index idx_title to films table');
            }

            // Add year index for range queries
            if (!isset($existing_indexes[$this->table_films]) || !in_array('idx_year', $existing_indexes[$this->table_films])) {
                $wpdb->query("ALTER TABLE {$this->table_films} ADD INDEX idx_year (year)");
                error_log('FWD: Added index idx_year to films table');
            }

            // Add composite indexes for JOIN performance
            if (!isset($existing_indexes[$this->table_film_actor_watch]) || !in_array('idx_film_actor', $existing_indexes[$this->table_film_actor_watch])) {
                $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} ADD INDEX idx_film_actor (film_id, actor_id)");
                error_log('FWD: Added composite index idx_film_actor');
            }

            if (!isset($existing_indexes[$this->table_film_actor_watch]) || !in_array('idx_film_watch', $existing_indexes[$this->table_film_actor_watch])) {
                $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} ADD INDEX idx_film_watch (film_id, watch_id)");
                error_log('FWD: Added composite index idx_film_watch');
            }

            // Mark as completed
            update_option('fwd_performance_indexes_added', true);
            error_log('FWD: Performance indexes added successfully');

        } catch (Exception $e) {
            error_log('FWD Performance Index Error: ' . $e->getMessage());
        }
    }

    /**
     * Seed database with watch brands from file
     * Only runs once - uses a WordPress option to track seeding status
     */
    private function seed_brands() {
        global $wpdb;

        // Check if brands have already been seeded
        if (get_option('fwd_brands_seeded', false)) {
            return;
        }

        $brands_file = FWD_PLUGIN_DIR . 'watch-brands.txt';

        if (!file_exists($brands_file)) {
            error_log('FWD: watch-brands.txt file not found');
            return;
        }

        try {
            // Read brands from file
            $brands_raw = file($brands_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Clean and deduplicate
            $brands = array();
            foreach ($brands_raw as $brand) {
                // Convert HTML entities to proper characters
                $brand = html_entity_decode(trim($brand), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Skip empty lines
                if (empty($brand)) {
                    continue;
                }

                // Store in array to deduplicate
                $brands[$brand] = true;
            }

            // Get unique brands sorted by length (longest first for parser)
            $unique_brands = array_keys($brands);
            usort($unique_brands, function($a, $b) {
                return strlen($b) - strlen($a);
            });

            // Insert brands into database
            $inserted = 0;
            foreach ($unique_brands as $brand) {
                $result = $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$this->table_brands} (brand_name) VALUES (%s)",
                    $brand
                ));

                if ($result) {
                    $inserted++;
                }
            }

            // Mark as seeded
            update_option('fwd_brands_seeded', true);
            error_log("FWD: Seeded {$inserted} brands into database");

        } catch (Exception $e) {
            error_log('FWD Brand Seeding Error: ' . $e->getMessage());
        }
    }

    /**
     * Get brands list with caching
     * @return array List of brand names sorted by length (longest first)
     */
    private function get_brands_cached() {
        static $brands = null;

        if ($brands === null) {
            // Try to get from WordPress transient cache (expires in 1 hour)
            $brands = get_transient('fwd_brands_list');

            if ($brands === false) {
                global $wpdb;
                $brands = $wpdb->get_col("SELECT brand_name FROM {$this->table_brands} ORDER BY LENGTH(brand_name) DESC");
                set_transient('fwd_brands_list', $brands, HOUR_IN_SECONDS);
            }
        }

        return $brands;
    }

    /**
     * Parse natural language entry into structured data
     */
    public function parse_entry($text) {
        // Remove trailing periods but preserve them in abbreviations
        $text = rtrim($text, '.');

        // Pattern 1: "Actor wears Brand Model in Year Film"
        $pattern1 = '/(.+?)\s+(?:wears?|wearing)\s+(?:(?:a|an|the)\s+)?(.+?)\s+(?:watch\s+)?in\s+(?:the\s+)?(\d{4})\s+(?:\w+\s+)?(.+?)$/i';

        // Pattern 2: "Actor wears Brand Model in Film (Year)"
        $pattern2 = '/(.+?)\s+(?:wears?|wearing)\s+(?:(?:a|an|the)\s+)?(.+?)\s+in\s+(.+?)\s+\((\d{4})\)$/i';

        // Pattern 3: "In Film (Year), Actor as Character wears Brand Model"
        $pattern3 = '/In\s+(.+?)\s+\((\d{4})\),\s+(.+?)\s+(?:as|plays)\s+(.+?)\s+(?:wears?|wearing)\s+(?:(?:a|an|the)\s+)?(.+?)$/i';

        $actor = null;
        $watch_full = null;
        $year = null;
        $title = null;
        $character = null;

        if (preg_match($pattern1, $text, $match)) {
            $actor = trim($match[1]);
            $watch_full = trim($match[2]);
            $year = intval($match[3]);
            $title = trim($match[4]);
        } elseif (preg_match($pattern2, $text, $match)) {
            $actor = trim($match[1]);
            $watch_full = trim($match[2]);
            $title = trim($match[3]);
            $year = intval($match[4]);
        } elseif (preg_match($pattern3, $text, $match)) {
            $title = trim($match[1]);
            $year = intval($match[2]);
            $actor = trim($match[3]);
            $character = trim($match[4]);
            $watch_full = trim($match[5]);
        } else {
            throw new Exception("Could not parse entry");
        }

        // Get brands from cache (sorted by length DESC for proper matching)
        $brands = $this->get_brands_cached();

        $brand = null;
        $model = $watch_full;

        // First, check for "by [Brand]" or "from [Brand]" patterns
        foreach ($brands as $b) {
            $by_pattern = ' by ' . $b;
            $from_pattern = ' from ' . $b;

            if (stripos($watch_full, $by_pattern) !== false) {
                $brand = $b;
                $model = trim(preg_replace('/' . preg_quote($by_pattern, '/') . '/i', '', $watch_full));
                break;
            } elseif (stripos($watch_full, $from_pattern) !== false) {
                $brand = $b;
                $model = trim(preg_replace('/' . preg_quote($from_pattern, '/') . '/i', '', $watch_full));
                break;
            }
        }

        // If no "by/from" pattern found, check if it starts with a brand name
        if (!$brand) {
            foreach ($brands as $b) {
                if (stripos($watch_full, $b) === 0) {
                    $brand = $b;
                    $model = trim(substr($watch_full, strlen($b)));
                    break;
                }
            }
        }

        // Fallback: use first word as brand
        if (!$brand) {
            $parts = explode(' ', $watch_full, 2);
            if (count($parts) === 2) {
                $brand = $parts[0];
                $model = $parts[1];
            } else {
                $brand = $watch_full;
                $model = $watch_full;
            }
        }

        // Default character to actor's last name if not provided
        if (!$character) {
            $name_parts = explode(' ', $actor);
            $character = end($name_parts);
        }

        return array(
            'actor' => $actor,
            'character' => $character,
            'brand' => $brand,
            'model' => $model,
            'title' => $title,
            'year' => $year,
            'verification' => 'Confirmed',
            'narrative' => 'Watch worn in film.',
            'image_url' => '',
            'source' => ''
        );
    }

    /**
     * Get attachment ID from image URL
     * @param string $image_url Image URL
     * @return int|false Attachment ID or false if not found
     */
    private function get_attachment_id_from_url($image_url) {
        global $wpdb;

        if (empty($image_url)) {
            return false;
        }

        // Try standard WordPress function first
        $attachment_id = attachment_url_to_postid($image_url);
        if ($attachment_id) {
            return $attachment_id;
        }

        // Try without -scaled suffix
        $clean_url = preg_replace('/-scaled(\.[^.]+)$/', '$1', $image_url);
        $attachment_id = attachment_url_to_postid($clean_url);
        if ($attachment_id) {
            return $attachment_id;
        }

        // Try searching by filename in post_title
        $filename = basename($image_url);
        $filename = preg_replace('/-scaled(\.[^.]+)$/', '$1', $filename);
        $filename = preg_replace('/\.[^.]+$/', '', $filename); // Remove extension

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($filename) . '%'
        ));

        return $attachment_id ? (int)$attachment_id : false;
    }

    /**
     * Insert entry into database
     * @param array $data Entry data to insert
     * @param bool $force_overwrite If true, update existing duplicate instead of throwing error
     */
    public function insert_entry($data, $force_overwrite = false) {
        global $wpdb;

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Insert or get existing entities first
            // Insert film
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_films} (title, year) VALUES (%s, %d)",
                $data['title'], $data['year']
            ));

            $film_id = $wpdb->get_var($wpdb->prepare(
                "SELECT film_id FROM {$this->table_films} WHERE title = %s AND year = %d",
                $data['title'], $data['year']
            ));

            // Insert brand
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_brands} (brand_name) VALUES (%s)",
                $data['brand']
            ));

            $brand_id = $wpdb->get_var($wpdb->prepare(
                "SELECT brand_id FROM {$this->table_brands} WHERE brand_name = %s",
                $data['brand']
            ));

            // Insert watch (verification_level removed in v3.0)
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_watches} (brand_id, model_reference) VALUES (%d, %s)",
                $brand_id, $data['model']
            ));

            $watch_id = $wpdb->get_var($wpdb->prepare(
                "SELECT w.watch_id FROM {$this->table_watches} w
                 JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
                 WHERE b.brand_name = %s AND w.model_reference = %s",
                $data['brand'], $data['model']
            ));

            // Insert actor
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_actors} (actor_name) VALUES (%s)",
                $data['actor']
            ));

            $actor_id = $wpdb->get_var($wpdb->prepare(
                "SELECT actor_id FROM {$this->table_actors} WHERE actor_name = %s",
                $data['actor']
            ));

            // Check for duplicate BEFORE doing character operations
            // This saves unnecessary work if duplicate exists
            $existing_record = $wpdb->get_row($wpdb->prepare(
                "SELECT faw.*, c.character_name, faw.narrative_role, faw.image_url, faw.confidence_level
                 FROM {$this->table_film_actor_watch} faw
                 JOIN {$this->table_characters} c ON faw.character_id = c.character_id
                 WHERE faw.film_id = %d AND faw.actor_id = %d AND faw.watch_id = %d",
                $film_id, $actor_id, $watch_id
            ), ARRAY_A);

            if ($existing_record) {
                // If force_overwrite is true, update the existing record
                if ($force_overwrite) {
                    // Check if character exists
                    $character_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT character_id FROM {$this->table_characters} WHERE character_name = %s LIMIT 1",
                        $data['character']
                    ));

                    if (!$character_id) {
                        $wpdb->insert($this->table_characters, array('character_name' => $data['character']));
                        $character_id = $wpdb->insert_id;
                    }

                    // Prepare update data
                    $update_data = array(
                        'character_id' => $character_id,
                        'narrative_role' => $data['narrative'],
                        'image_url' => isset($data['image_url']) ? $data['image_url'] : '',
                        'source_url' => isset($data['source_url']) ? $data['source_url'] : '',
                        'confidence_level' => isset($data['confidence_level']) ? $data['confidence_level'] : ''
                    );

                    // Handle gallery_ids if provided
                    if (isset($data['gallery_ids']) && !empty($data['gallery_ids'])) {
                        $update_data['gallery_ids'] = $data['gallery_ids'];
                    }
                    // Auto-create gallery_ids from image_url if not provided
                    elseif (isset($data['image_url']) && !empty($data['image_url'])) {
                        $attachment_id = $this->get_attachment_id_from_url($data['image_url']);
                        if ($attachment_id) {
                            $update_data['gallery_ids'] = json_encode(array($attachment_id));
                        }
                    }

                    // Update the existing relationship
                    $wpdb->update(
                        $this->table_film_actor_watch,
                        $update_data,
                        array('faw_id' => $existing_record['faw_id'])
                    );

                    // Create or update RegGallery post if gallery_ids is available
                    $gallery_ids_to_use = isset($update_data['gallery_ids']) ? $update_data['gallery_ids'] : null;
                    if ($gallery_ids_to_use) {
                        $gallery_ids_array = json_decode($gallery_ids_to_use, true);
                        if (is_array($gallery_ids_array) && !empty($gallery_ids_array) && function_exists('fwd_create_regallery_post')) {
                            $existing_regallery_id = isset($existing_record['regallery_id']) ? $existing_record['regallery_id'] : null;
                            $regallery_id = fwd_create_regallery_post($gallery_ids_array, $data, $existing_regallery_id);
                            if ($regallery_id) {
                                $wpdb->update(
                                    $this->table_film_actor_watch,
                                    array('regallery_id' => $regallery_id),
                                    array('faw_id' => $existing_record['faw_id'])
                                );

                                // Set correct gallery options (justified with captions)
                                $correct_options = json_encode(array(
                                    'title' => 'Default',
                                    'template_id' => 0,
                                    'templateType' => '',
                                    'css' => '',
                                    'custom_css' => '',
                                    'type' => 'justified',
                                    'justified' => array(
                                        'showCaption' => true,
                                        'captionSource' => 'caption',
                                        'captionVisibility' => 'alwaysShown',
                                        'captionPosition' => 'bottom'
                                    )
                                ));
                                update_option('reacg_options' . $regallery_id, $correct_options, false);
                            }
                        }
                    }

                    $wpdb->query('COMMIT');

                    // Update search index
                    if (function_exists('fwd_search')) {
                        fwd_search()->update_entry($existing_record['faw_id']);
                    }

                    return true;
                } else {
                    // Return duplicate information in exception
                    $wpdb->query('ROLLBACK');
                    $duplicate_info = array(
                        'actor' => $data['actor'],
                        'character' => $existing_record['character_name'],
                        'brand' => $data['brand'],
                        'model' => $data['model'],
                        'title' => $data['title'],
                        'year' => $data['year'],
                        'narrative' => $existing_record['narrative_role'],
                        'image_url' => $existing_record['image_url'],
                        'confidence_level' => isset($existing_record['confidence_level']) ? $existing_record['confidence_level'] : ''
                    );
                    throw new Exception("DUPLICATE:" . json_encode($duplicate_info));
                }
            }

            // Clear brands cache since we may have added a new brand
            delete_transient('fwd_brands_list');

            // Check if character exists
            $character_id = $wpdb->get_var($wpdb->prepare(
                "SELECT character_id FROM {$this->table_characters} WHERE character_name = %s LIMIT 1",
                $data['character']
            ));

            if (!$character_id) {
                $wpdb->insert($this->table_characters, array('character_name' => $data['character']));
                $character_id = $wpdb->insert_id;
            }

            // Get current user ID for audit trail
            $user_id = get_current_user_id();

            // Prepare insert data
            $insert_data = array(
                'film_id' => $film_id,
                'actor_id' => $actor_id,
                'character_id' => $character_id,
                'watch_id' => $watch_id,
                'narrative_role' => $data['narrative'],
                'image_url' => isset($data['image_url']) ? $data['image_url'] : '',
                'source_url' => isset($data['source_url']) ? $data['source_url'] : '',
                'confidence_level' => isset($data['confidence_level']) ? $data['confidence_level'] : '',
                'created_by' => $user_id ? $user_id : null
            );

            // Handle gallery_ids if provided
            if (isset($data['gallery_ids']) && !empty($data['gallery_ids'])) {
                $insert_data['gallery_ids'] = $data['gallery_ids'];
            }
            // Auto-create gallery_ids from image_url if not provided
            elseif (isset($data['image_url']) && !empty($data['image_url'])) {
                $attachment_id = $this->get_attachment_id_from_url($data['image_url']);
                if ($attachment_id) {
                    $insert_data['gallery_ids'] = json_encode(array($attachment_id));
                }
            }

            // Insert relationship
            $wpdb->insert($this->table_film_actor_watch, $insert_data);

            $faw_id = $wpdb->insert_id;

            // Create RegGallery post if gallery_ids is available
            $regallery_id = null;
            $gallery_ids_to_use = isset($insert_data['gallery_ids']) ? $insert_data['gallery_ids'] : null;
            if ($gallery_ids_to_use) {
                $gallery_ids_array = json_decode($gallery_ids_to_use, true);
                if (is_array($gallery_ids_array) && !empty($gallery_ids_array) && function_exists('fwd_create_regallery_post')) {
                    $regallery_id = fwd_create_regallery_post($gallery_ids_array, $data);
                    if ($regallery_id) {
                        $wpdb->update(
                            $this->table_film_actor_watch,
                            array('regallery_id' => $regallery_id),
                            array('faw_id' => $faw_id)
                        );

                        // Set correct gallery options (justified with captions)
                        $correct_options = json_encode(array(
                            'title' => 'Default',
                            'template_id' => 0,
                            'templateType' => '',
                            'css' => '',
                            'custom_css' => '',
                            'type' => 'justified',
                            'justified' => array(
                                'showCaption' => true,
                                'captionSource' => 'caption',
                                'captionVisibility' => 'alwaysShown',
                                'captionPosition' => 'bottom'
                            )
                        ));
                        update_option('reacg_options' . $regallery_id, $correct_options, false);
                    }
                }
            }

            $wpdb->query('COMMIT');

            // Add to search index
            if (function_exists('fwd_search')) {
                fwd_search()->index_entry($faw_id);
            }

            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Query watches by actor
     *
     * Uses fast FULLTEXT search service if available, falls back to JOIN-based search otherwise.
     */
    public function query_actor($actor_name) {
        // Use fast search service if available
        if (function_exists('fwd_search')) {
            $search_results = fwd_search()->search($actor_name, false);

            if ($search_results['success'] && isset($search_results['actors'])) {
                // actors is associative array: actor_name => array(watches)
                foreach ($search_results['actors'] as $actor => $films) {
                    // Return the first actor match
                    if (stripos($actor, $actor_name) !== false) {
                        return array(
                            'success' => true,
                            'actor' => $actor,
                            'count' => count($films),
                            'films' => $films
                        );
                    }
                }
            }
        }

        // Fallback to JOIN-based search if search service unavailable
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT f.title, f.year, b.brand_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.image_url, faw.source_url, faw.confidence_level, a.actor_name
             FROM {$this->table_film_actor_watch} faw
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
            '%' . $wpdb->esc_like($actor_name) . '%'
        ), ARRAY_A);

        $films = array();
        $actual_actor_name = null;
        foreach ($results as $row) {
            if (!$actual_actor_name) {
                $actual_actor_name = $row['actor_name'];
            }
            $image_caption = !empty($row['image_url']) ? fwd_get_image_caption($row['image_url']) : '';
            $films[] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'actor' => $row['actor_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role'],
                'image_url' => $row['image_url'],
                'image_caption' => $image_caption,
                'source' => $row['source_url'],
                'confidence_level' => $row['confidence_level']
            );
        }

        return array(
            'success' => true,
            'actor' => $actual_actor_name ? $actual_actor_name : $actor_name,
            'count' => count($films),
            'films' => $films
        );
    }

    /**
     * Query films by brand
     *
     * Uses fast FULLTEXT search service if available, falls back to JOIN-based search otherwise.
     */
    public function query_brand($brand_name) {
        // Use fast search service if available
        if (function_exists('fwd_search')) {
            $search_results = fwd_search()->search($brand_name, false);

            if ($search_results['success'] && isset($search_results['brands'])) {
                // brands is a numeric array
                foreach ($search_results['brands'] as $brand_data) {
                    // Return the first brand match
                    if (stripos($brand_data['brand'], $brand_name) !== false) {
                        return array(
                            'success' => true,
                            'brand' => $brand_data['brand'],
                            'count' => $brand_data['count'],
                            'film_count' => $brand_data['film_count'],
                            'films' => $brand_data['films']
                        );
                    }
                }
            }
        }

        // Fallback to JOIN-based search if search service unavailable
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT f.film_id, f.title, f.year, a.actor_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.image_url, faw.source_url, faw.confidence_level, b.brand_name
             FROM {$this->table_film_actor_watch} faw
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
            '%' . $wpdb->esc_like($brand_name) . '%'
        ), ARRAY_A);

        // Group watches by film
        $films_grouped = array();
        $actual_brand_name = null;
        $total_watches = 0;

        foreach ($results as $row) {
            if (!$actual_brand_name) {
                $actual_brand_name = $row['brand_name'];
            }

            $film_key = $row['film_id'];
            if (!isset($films_grouped[$film_key])) {
                $films_grouped[$film_key] = array(
                    'title' => $row['title'],
                    'year' => $row['year'],
                    'watches' => array()
                );
            }

            $image_caption = !empty($row['image_url']) ? fwd_get_image_caption($row['image_url']) : '';
            $films_grouped[$film_key]['watches'][] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'actor' => $row['actor_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role'],
                'image_url' => $row['image_url'],
                'image_caption' => $image_caption,
                'source' => $row['source_url'],
                'confidence_level' => $row['confidence_level']
            );
            $total_watches++;
        }

        return array(
            'success' => true,
            'brand' => $actual_brand_name ? $actual_brand_name : $brand_name,
            'count' => $total_watches,
            'film_count' => count($films_grouped),
            'films' => array_values($films_grouped)
        );
    }

    /**
     * Query watches by film
     *
     * Uses fast FULLTEXT search service if available, falls back to JOIN-based search otherwise.
     */
    public function query_film($film_title) {
        // Use fast search service if available
        if (function_exists('fwd_search')) {
            $search_results = fwd_search()->search($film_title, false);

            if ($search_results['success'] && isset($search_results['films'])) {
                // films is a numeric array
                foreach ($search_results['films'] as $film_data) {
                    // Return the first film match
                    if (stripos($film_data['title'], $film_title) !== false) {
                        // Count watches in this film
                        $watch_count = isset($film_data['watches']) ? count($film_data['watches']) : 0;
                        return array(
                            'success' => true,
                            'count' => $watch_count,
                            'film_count' => 1,
                            'films' => array($film_data)
                        );
                    }
                }
            }
        }

        // Fallback to JOIN-based search if search service unavailable
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT f.title, f.year, f.film_id, a.actor_name, b.brand_name,
                    w.model_reference, c.character_name, faw.narrative_role, faw.image_url, faw.source_url, faw.confidence_level
             FROM {$this->table_film_actor_watch} faw
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
            '%' . $wpdb->esc_like($film_title) . '%'
        ), ARRAY_A);

        // Group watches by film
        $films_grouped = array();
        $total_watches = 0;

        foreach ($results as $row) {
            $film_key = $row['film_id'];

            if (!isset($films_grouped[$film_key])) {
                $films_grouped[$film_key] = array(
                    'title' => $row['title'],
                    'year' => $row['year'],
                    'watches' => array()
                );
            }

            $image_caption = !empty($row['image_url']) ? fwd_get_image_caption($row['image_url']) : '';
            $films_grouped[$film_key]['watches'][] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'actor' => $row['actor_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role'],
                'image_url' => $row['image_url'],
                'image_caption' => $image_caption,
                'source' => $row['source_url'],
                'confidence_level' => $row['confidence_level']
            );
            $total_watches++;
        }

        // Convert to indexed array
        $films = array_values($films_grouped);

        return array(
            'success' => true,
            'count' => $total_watches,
            'film_count' => count($films),
            'films' => $films
        );
    }
    /**
     * Query all categories (actor, brand, film)
     *
     * Uses fast FULLTEXT search service if available, which searches all categories in one query.
     * Falls back to calling three separate query methods if search service unavailable.
     */
    public function query_all($search_term) {
        // Use fast unified search if available (searches everything in one query)
        if (function_exists('fwd_search')) {
            $search_results = fwd_search()->search($search_term, false);

            if ($search_results['success']) {
                // Only return films - film is the unique key for all search results
                $films = isset($search_results['films']) ? $search_results['films'] : array();
                $total_watches = 0;
                foreach ($films as $film_data) {
                    $total_watches += isset($film_data['watches']) ? count($film_data['watches']) : 0;
                }

                return array(
                    'success' => true,
                    'query' => $search_term,
                    'films' => $films,
                    'total_count' => $total_watches
                );
            }
        }

        // Fallback: Use query_film which already returns film-grouped results
        return $this->query_film($search_term);
    }

    /**
     * Get database statistics
     */
    public function get_stats() {
        global $wpdb;

        $film_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_films}");
        $actor_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_actors}");
        $brand_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_brands}");
        $entry_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_film_actor_watch}");

        $top_brands = $wpdb->get_results(
            "SELECT b.brand_name, COUNT(*) as count
             FROM {$this->table_film_actor_watch} faw
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             GROUP BY b.brand_name
             ORDER BY count DESC
             LIMIT 10",
            ARRAY_A
        );

        $brands_list = array();
        foreach ($top_brands as $row) {
            $brands_list[] = array(
                'brand' => $row['brand_name'],
                'count' => $row['count']
            );
        }

        return array(
            'success' => true,
            'stats' => array(
                'films' => $film_count,
                'actors' => $actor_count,
                'brands' => $brand_count,
                'entries' => $entry_count,
                'top_brands' => $brands_list
            )
        );
    }
}

/**
 * Get global database instance
 */
function fwd_db() {
    static $instance = null;
    if ($instance === null) {
        $instance = new FWD_Database();
    }
    return $instance;
}
