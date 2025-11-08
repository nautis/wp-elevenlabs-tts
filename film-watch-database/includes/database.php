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
     *
     * Initializes table names with WordPress prefix and runs
     * lightweight migration check (cached to avoid performance impact).
     * Note: Heavy operations moved to init() method which is only
     * called during plugin activation.
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

        // Only run migrations if needed (version-based, lightweight check)
        $this->maybe_migrate_database();
    }

    /**
     * Initialize database tables and seed data
     *
     * Called only during plugin activation. Creates all necessary
     * tables, runs migrations, and seeds initial brand data.
     *
     * @return void
     */
    public function init() {
        $this->create_tables();
        $this->migrate_database();
        $this->seed_brands();
    }

    /**
     * Create database tables if they don't exist
     *
     * Creates all plugin tables with proper charset, indexes, and constraints.
     * Uses dbDelta for safe table creation/updates. Logs any errors encountered.
     *
     * @return void
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
            image_url varchar(2083) DEFAULT NULL,
            confidence_level text,
            source_url varchar(2083) DEFAULT NULL,
            PRIMARY KEY (faw_id),
            UNIQUE KEY unique_entry (film_id, actor_id, character_id, watch_id),
            KEY film_id (film_id),
            KEY actor_id (actor_id),
            KEY character_id (character_id),
            KEY watch_id (watch_id),
            KEY duplicate_check (film_id, actor_id, watch_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($sql as $query) {
            $result = dbDelta($query);
            if ($wpdb->last_error) {
                error_log('FWD Database: Table creation error - ' . $wpdb->last_error);
            }
        }
    }

    /**
     * Check if migration is needed and run it
     *
     * Uses transient cache to avoid checking on every page load.
     * Cache expires after 1 hour. Compares current DB version to
     * determine if migration is needed.
     *
     * @return void
     */
    private function maybe_migrate_database() {
        // Check transient cache first (expires in 1 hour)
        $migration_checked = get_transient('fwd_migration_checked');
        if ($migration_checked === FWD_VERSION) {
            return; // Already checked for this version
        }

        // Check if migration is needed
        $db_version = get_option('fwd_db_version', '1.0');
        if (version_compare($db_version, '3.1', '>=')) {
            // No migration needed, cache this result
            set_transient('fwd_migration_checked', FWD_VERSION, HOUR_IN_SECONDS);
            return;
        }

        // Migration needed, run it
        $this->migrate_database();
        set_transient('fwd_migration_checked', FWD_VERSION, HOUR_IN_SECONDS);
    }

    /**
     * Migrate database schema for upgrades
     *
     * Handles versioned migrations:
     * - 1.0 → 2.0: Add image_url and source_url columns
     * - 2.0 → 3.0: Remove deprecated verification_level and source columns
     * - 3.0 → 3.1: Optimize URL columns (TEXT → VARCHAR) and add composite index
     *
     * @return void
     */
    private function migrate_database() {
        global $wpdb;

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

            // Migration 2.0 -> 3.0: Remove verification_level and source column
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

                // Remove source column (not source_url) from film_actor_watch table
                foreach ($faw_columns as $column) {
                    if ($column->Field === 'source') {
                        $wpdb->query("ALTER TABLE {$this->table_film_actor_watch} DROP COLUMN source");
                        error_log('FWD Database: Removed source column from film_actor_watch');
                        break;
                    }
                }

                update_option('fwd_db_version', '3.0');
            }

            // Migration 3.0 -> 3.1: Optimize URL columns and add composite index
            if (version_compare($db_version, '3.1', '<')) {
                // Change TEXT to VARCHAR for URL columns (better performance and indexing)
                $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                              MODIFY COLUMN image_url VARCHAR(2083) DEFAULT NULL");
                $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                              MODIFY COLUMN source_url VARCHAR(2083) DEFAULT NULL");

                // Add composite index for duplicate detection queries
                // Check if index already exists
                $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_film_actor_watch} WHERE Key_name = 'duplicate_check'");
                if (empty($indexes)) {
                    $wpdb->query("ALTER TABLE {$this->table_film_actor_watch}
                                  ADD KEY duplicate_check (film_id, actor_id, watch_id)");
                    error_log('FWD Database: Added composite index for duplicate checks');
                }

                update_option('fwd_db_version', '3.1');
                error_log('FWD Database: Migrated to version 3.1 (optimized URL columns and indexes)');
            }

        } catch (Exception $e) {
            error_log('FWD Database Migration Error: ' . $e->getMessage());
        }
    }

    /**
     * Seed database with watch brands from file
     *
     * Reads watch-brands.txt and imports brands into database.
     * Only runs once - uses a WordPress option to track seeding status.
     * Converts HTML entities and deduplicates brands before insertion.
     * Sorts brands by length (longest first) for optimal parser matching.
     *
     * @return void
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
     * Parse natural language entry into structured data
     *
     * Supports multiple patterns:
     * - "Actor wears Brand Model in Year Film"
     * - "Actor wears Brand Model in Film (Year)"
     * - "In Film (Year), Actor as Character wears Brand Model"
     *
     * @param string $text Natural language entry to parse
     * @return array Parsed entry with keys: actor, character, brand, model, title, year, narrative, image_url
     * @throws Exception If entry cannot be parsed
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

        // Check if actor field contains "as" or "plays" and extract character
        if (!$character && preg_match('/^(.+?)\s+(?:as|plays)\s+(.+)$/i', $actor, $actor_match)) {
            $actor = trim($actor_match[1]);
            $character = trim($actor_match[2]);
        }

        // Get brands from database (sorted by length DESC for proper matching)
        // Use transient cache to avoid repeated queries
        global $wpdb;
        $brands = get_transient('fwd_brands_list');
        if (false === $brands) {
            $brands = $wpdb->get_col("SELECT brand_name FROM {$this->table_brands} ORDER BY LENGTH(brand_name) DESC");
            // Cache for 24 hours (brands don't change often)
            set_transient('fwd_brands_list', $brands, DAY_IN_SECONDS);
        }

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
            'narrative' => 'Watch worn in film.',
            'image_url' => ''
        );
    }

    /**
     * Insert entry into database
     *
     * Inserts all related entities (film, actor, brand, watch, character) and
     * creates the relationship in film_actor_watch table. Uses transactions
     * for data integrity. Detects duplicates and can either throw error or
     * update existing record based on $force_overwrite parameter.
     *
     * @param array $data Entry data with keys: actor, character, brand, model, title, year, narrative, image_url, confidence_level, source_url
     * @param bool $force_overwrite If true, update existing duplicate instead of throwing error
     * @return bool True on success
     * @throws Exception On duplicate entry (message contains "DUPLICATE:" prefix with JSON data) or database error
     */
    public function insert_entry($data, $force_overwrite = false) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Insert film
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_films} (title, year) VALUES (%s, %d)",
                $data['title'], $data['year']
            ));

            // Insert brand
            $result = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_brands} (brand_name) VALUES (%s)",
                $data['brand']
            ));

            // If a new brand was inserted, invalidate the brands cache
            if ($result) {
                delete_transient('fwd_brands_list');
            }

            // Get brand_id
            $brand_id = $wpdb->get_var($wpdb->prepare(
                "SELECT brand_id FROM {$this->table_brands} WHERE brand_name = %s",
                $data['brand']
            ));

            if (!$brand_id) {
                $wpdb->query('ROLLBACK');
                throw new Exception("Failed to retrieve brand_id for brand: {$data['brand']}");
            }

            // Insert watch
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_watches} (brand_id, model_reference) VALUES (%d, %s)",
                $brand_id, $data['model']
            ));

            if ($wpdb->last_error) {
                error_log('FWD Database: Watch insert error - ' . $wpdb->last_error);
            }

            // Insert actor
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_actors} (actor_name) VALUES (%s)",
                $data['actor']
            ));

            if ($wpdb->last_error) {
                error_log('FWD Database: Actor insert error - ' . $wpdb->last_error);
            }

            // Get IDs
            $film_id = $wpdb->get_var($wpdb->prepare(
                "SELECT film_id FROM {$this->table_films} WHERE title = %s AND year = %d",
                $data['title'], $data['year']
            ));

            if (!$film_id) {
                $wpdb->query('ROLLBACK');
                throw new Exception("Failed to retrieve film_id for: {$data['title']} ({$data['year']})");
            }

            $actor_id = $wpdb->get_var($wpdb->prepare(
                "SELECT actor_id FROM {$this->table_actors} WHERE actor_name = %s",
                $data['actor']
            ));

            if (!$actor_id) {
                $wpdb->query('ROLLBACK');
                throw new Exception("Failed to retrieve actor_id for actor: {$data['actor']}");
            }

            $watch_id = $wpdb->get_var($wpdb->prepare(
                "SELECT w.watch_id FROM {$this->table_watches} w
                 JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
                 WHERE b.brand_name = %s AND w.model_reference = %s",
                $data['brand'], $data['model']
            ));

            if (!$watch_id) {
                $wpdb->query('ROLLBACK');
                throw new Exception("Failed to retrieve watch_id for: {$data['brand']} {$data['model']}");
            }

            // Check for duplicates
            $existing_record = $wpdb->get_row($wpdb->prepare(
                "SELECT faw.*, c.character_name, faw.narrative_role, faw.image_url, faw.confidence_level, faw.source_url
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

                    // Update the existing relationship
                    $wpdb->update(
                        $this->table_film_actor_watch,
                        array(
                            'character_id' => $character_id,
                            'narrative_role' => $data['narrative'],
                            'image_url' => isset($data['image_url']) ? $data['image_url'] : '',
                            'confidence_level' => isset($data['confidence_level']) ? $data['confidence_level'] : '',
                            'source_url' => isset($data['source_url']) ? $data['source_url'] : ''
                        ),
                        array('faw_id' => $existing_record['faw_id'])
                    );

                    $wpdb->query('COMMIT');
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
                        'confidence_level' => $existing_record['confidence_level'],
                        'source' => $existing_record['source_url']
                    );
                    throw new Exception("DUPLICATE:" . json_encode($duplicate_info));
                }
            }

            // Check if character exists
            $character_id = $wpdb->get_var($wpdb->prepare(
                "SELECT character_id FROM {$this->table_characters} WHERE character_name = %s LIMIT 1",
                $data['character']
            ));

            if (!$character_id) {
                $wpdb->insert($this->table_characters, array('character_name' => $data['character']));
                $character_id = $wpdb->insert_id;
            }

            // Insert relationship
            $wpdb->insert($this->table_film_actor_watch, array(
                'film_id' => $film_id,
                'actor_id' => $actor_id,
                'character_id' => $character_id,
                'watch_id' => $watch_id,
                'narrative_role' => $data['narrative'],
                'image_url' => isset($data['image_url']) ? $data['image_url'] : '',
                'confidence_level' => isset($data['confidence_level']) ? $data['confidence_level'] : '',
                'source_url' => isset($data['source_url']) ? $data['source_url'] : ''
            ));

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Unified query function for all search types
     * Reduces code duplication across query_actor, query_brand, query_film
     *
     * @param string $search_term The term to search for
     * @param string $search_type Type: 'actor', 'brand', or 'film'
     * @return array Result array with success, count, and data
     */
    private function query_unified($search_term, $search_type) {
        global $wpdb;

        // Configure search based on type
        $config = array(
            'actor' => array(
                'where_column' => 'a.actor_name',
                'order_by' => 'f.year DESC',
                'result_key' => 'actor',
                'result_field' => 'actor_name',
                'list_key' => 'films'
            ),
            'brand' => array(
                'where_column' => 'b.brand_name',
                'order_by' => 'f.year DESC',
                'result_key' => 'brand',
                'result_field' => 'brand_name',
                'list_key' => 'films'
            ),
            'film' => array(
                'where_column' => 'f.title',
                'order_by' => 'a.actor_name',
                'result_key' => 'film',
                'result_field' => 'title',
                'list_key' => 'watches'
            )
        );

        $cfg = $config[$search_type];

        // Clear any previous errors
        $wpdb->last_error = '';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT f.title, f.year, a.actor_name, b.brand_name, w.model_reference,
                    c.character_name, faw.narrative_role, faw.image_url, faw.confidence_level, faw.source_url
             FROM {$this->table_film_actor_watch} faw
             JOIN {$this->table_films} f ON faw.film_id = f.film_id
             JOIN {$this->table_actors} a ON faw.actor_id = a.actor_id
             JOIN {$this->table_characters} c ON faw.character_id = c.character_id
             JOIN {$this->table_watches} w ON faw.watch_id = w.watch_id
             JOIN {$this->table_brands} b ON w.brand_id = b.brand_id
             WHERE {$cfg['where_column']} LIKE %s
             ORDER BY {$cfg['order_by']}",
            '%' . $wpdb->esc_like($search_term) . '%'
        ), ARRAY_A);

        // Check for query errors (only if query actually failed)
        if ($results === null && $wpdb->last_error) {
            error_log("FWD Database: Query error in {$search_type} search - " . $wpdb->last_error);
            return array(
                'success' => false,
                'error' => 'Database query failed',
                'count' => 0,
                $cfg['list_key'] => array()
            );
        }

        // Treat null as empty array (no results, but not an error)
        if ($results === null) {
            $results = array();
        }

        // Batch load all image captions in a single query (fixes N+1 problem)
        $image_urls = array_filter(array_column($results, 'image_url'));
        $captions = fwd_get_image_captions_batch($image_urls);

        $items = array();
        $actual_search_value = null;
        foreach ($results as $row) {
            if (!$actual_search_value && isset($row[$cfg['result_field']])) {
                $actual_search_value = $row[$cfg['result_field']];
            }
            $image_caption = isset($captions[$row['image_url']]) ? $captions[$row['image_url']] : '';
            $items[] = array(
                'title' => $row['title'],
                'year' => $row['year'],
                'actor' => $row['actor_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_reference'],
                'character' => $row['character_name'],
                'narrative' => $row['narrative_role'],
                'image_url' => $row['image_url'],
                'image_caption' => $image_caption,
                'confidence_level' => $row['confidence_level'],
                'source' => $row['source_url']
            );
        }

        return array(
            'success' => true,
            $cfg['result_key'] => $actual_search_value ? $actual_search_value : $search_term,
            'count' => count($items),
            $cfg['list_key'] => $items
        );
    }

    /**
     * Query watches by actor
     *
     * @param string $actor_name Actor name to search for
     * @return array Result array with films
     */
    public function query_actor($actor_name) {
        return $this->query_unified($actor_name, 'actor');
    }

    /**
     * Query films by brand
     *
     * @param string $brand_name Brand name to search for
     * @return array Result array with films
     */
    public function query_brand($brand_name) {
        return $this->query_unified($brand_name, 'brand');
    }

    /**
     * Query watches by film
     *
     * @param string $film_title Film title to search for
     * @return array Result array with watches
     */
    public function query_film($film_title) {
        return $this->query_unified($film_title, 'film');
    }

    /**
     * Get database statistics
     *
     * @return array Statistics array with success flag and stats data
     */
    public function get_stats() {
        global $wpdb;

        // Clear any previous errors
        $wpdb->last_error = '';

        $film_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_films}");
        if ($film_count === null && $wpdb->last_error) {
            error_log('FWD Database: Stats query error (films) - ' . $wpdb->last_error);
            return array('success' => false, 'error' => 'Failed to retrieve film count');
        }

        $actor_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_actors}");
        if ($actor_count === null && $wpdb->last_error) {
            error_log('FWD Database: Stats query error (actors) - ' . $wpdb->last_error);
            return array('success' => false, 'error' => 'Failed to retrieve actor count');
        }

        $brand_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_brands}");
        if ($brand_count === null && $wpdb->last_error) {
            error_log('FWD Database: Stats query error (brands) - ' . $wpdb->last_error);
            return array('success' => false, 'error' => 'Failed to retrieve brand count');
        }

        $entry_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_film_actor_watch}");
        if ($entry_count === null && $wpdb->last_error) {
            error_log('FWD Database: Stats query error (entries) - ' . $wpdb->last_error);
            return array('success' => false, 'error' => 'Failed to retrieve entry count');
        }

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

        if ($top_brands === null && $wpdb->last_error) {
            error_log('FWD Database: Stats query error (top_brands) - ' . $wpdb->last_error);
            return array('success' => false, 'error' => 'Failed to retrieve top brands');
        }

        $brands_list = array();
        if (is_array($top_brands)) {
            foreach ($top_brands as $row) {
                $brands_list[] = array(
                    'brand' => $row['brand_name'],
                    'count' => $row['count']
                );
            }
        }

        return array(
            'success' => true,
            'stats' => array(
                'films' => (int)$film_count,
                'actors' => (int)$actor_count,
                'brands' => (int)$brand_count,
                'entries' => (int)$entry_count,
                'top_brands' => $brands_list
            )
        );
    }
}

/**
 * Get global database instance (singleton pattern)
 *
 * Returns a single shared instance of the database handler
 * throughout the plugin lifecycle. Instance is created on
 * first call and reused on subsequent calls.
 *
 * @return FWD_Database Database handler instance
 */
function fwd_db() {
    static $instance = null;
    if ($instance === null) {
        $instance = new FWD_Database();
    }
    return $instance;
}
