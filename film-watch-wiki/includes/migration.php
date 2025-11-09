<?php
/**
 * Legacy Data Migration Class
 * Migrates data from wp_fwd_* tables to new WordPress post types
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Migration {

    /**
     * Dry run mode - if true, nothing is written to database
     */
    private $dry_run = true;

    /**
     * Verbose mode - if true, outputs detailed logs
     */
    private $verbose = true;

    /**
     * Migration mappings (legacy_id => post_id)
     */
    private $mappings = array(
        'brands' => array(),
        'watches' => array(),
        'actors' => array(),
        'movies' => array()
    );

    /**
     * Migration statistics
     */
    private $stats = array(
        'brands_created' => 0,
        'brands_skipped' => 0,
        'watches_created' => 0,
        'watches_skipped' => 0,
        'actors_created' => 0,
        'actors_skipped' => 0,
        'movies_created' => 0,
        'movies_skipped' => 0,
        'sightings_created' => 0,
        'sightings_skipped' => 0,
        'errors' => array()
    );

    /**
     * Constructor
     *
     * @param bool $dry_run Run in dry-run mode (default: true)
     * @param bool $verbose Enable verbose logging (default: true)
     */
    public function __construct($dry_run = true, $verbose = true) {
        $this->dry_run = $dry_run;
        $this->verbose = $verbose;
    }

    /**
     * Run complete migration
     *
     * @return array Migration statistics
     */
    public function run_migration() {
        $this->log("=== FILM WATCH WIKI MIGRATION ===");
        $this->log("Mode: " . ($this->dry_run ? "DRY RUN (no changes will be made)" : "LIVE (changes will be written)"));
        $this->log("");

        // Step 1: Migrate Brands
        $this->log("Step 1/5: Migrating Brands...");
        $this->migrate_brands();

        // Step 2: Migrate Watches
        $this->log("Step 2/5: Migrating Watches...");
        $this->migrate_watches();

        // Step 3: Migrate Actors
        $this->log("Step 3/5: Migrating Actors...");
        $this->migrate_actors();

        // Step 4: Migrate Movies
        $this->log("Step 4/5: Migrating Movies...");
        $this->migrate_movies();

        // Step 5: Migrate Sightings (relationships)
        $this->log("Step 5/5: Migrating Watch Sightings...");
        $this->migrate_sightings();

        // Print final report
        $this->print_report();

        return $this->stats;
    }

    /**
     * Migrate brands from wp_fwd_brands to fww_brand posts
     */
    private function migrate_brands() {
        global $wpdb;

        $brands = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fwd_brands ORDER BY brand_id");

        if (empty($brands)) {
            $this->log("  No brands found in legacy database.");
            return;
        }

        $this->log("  Found " . count($brands) . " brands to migrate.");

        foreach ($brands as $brand) {
            // Check if brand already exists by name
            $existing = get_page_by_title($brand->brand_name, OBJECT, 'fww_brand');

            if ($existing) {
                $this->stats['brands_skipped']++;
                $this->mappings['brands'][$brand->brand_id] = $existing->ID;
                $this->log("  SKIP: Brand '{$brand->brand_name}' already exists (ID: {$existing->ID})", 'verbose');
                continue;
            }

            if (!$this->dry_run) {
                $post_id = wp_insert_post(array(
                    'post_title' => sanitize_text_field($brand->brand_name),
                    'post_content' => !empty($brand->description) ? wp_kses_post($brand->description) : '',
                    'post_status' => 'publish',
                    'post_type' => 'fww_brand',
                    'post_author' => 1
                ));

                if (is_wp_error($post_id)) {
                    $this->log_error("Failed to create brand: {$brand->brand_name}", $post_id->get_error_message());
                    continue;
                }

                // Store legacy ID as post meta for reference
                update_post_meta($post_id, '_fww_legacy_brand_id', $brand->brand_id);

                $this->mappings['brands'][$brand->brand_id] = $post_id;
                $this->stats['brands_created']++;
                $this->log("  CREATE: Brand '{$brand->brand_name}' (ID: {$post_id})", 'verbose');
            } else {
                $this->stats['brands_created']++;
                $this->log("  WOULD CREATE: Brand '{$brand->brand_name}'", 'verbose');
            }
        }

        $this->log("  Brands: {$this->stats['brands_created']} created, {$this->stats['brands_skipped']} skipped");
        $this->log("");
    }

    /**
     * Migrate watches from wp_fwd_watches to fww_watch posts
     */
    private function migrate_watches() {
        global $wpdb;

        $watches = $wpdb->get_results("
            SELECT w.*, b.brand_name
            FROM {$wpdb->prefix}fwd_watches w
            LEFT JOIN {$wpdb->prefix}fwd_brands b ON w.brand_id = b.brand_id
            ORDER BY w.watch_id
        ");

        if (empty($watches)) {
            $this->log("  No watches found in legacy database.");
            return;
        }

        $this->log("  Found " . count($watches) . " watches to migrate.");

        foreach ($watches as $watch) {
            // Build watch title (Brand + Model)
            $watch_title = trim($watch->brand_name . ' ' . $watch->model_reference);

            // Check if watch already exists
            $existing = get_page_by_title($watch_title, OBJECT, 'fww_watch');

            if ($existing) {
                $this->stats['watches_skipped']++;
                $this->mappings['watches'][$watch->watch_id] = $existing->ID;
                $this->log("  SKIP: Watch '{$watch_title}' already exists (ID: {$existing->ID})", 'verbose');
                continue;
            }

            if (!$this->dry_run) {
                // Build post content from watch details
                $content = '';
                if (!empty($watch->model_description)) {
                    $content .= wpautop($watch->model_description);
                }
                if (!empty($watch->specifications)) {
                    $content .= "\n\n<h3>Specifications</h3>\n" . wpautop($watch->specifications);
                }

                $post_id = wp_insert_post(array(
                    'post_title' => sanitize_text_field($watch_title),
                    'post_content' => wp_kses_post($content),
                    'post_excerpt' => !empty($watch->model_description) ? wp_trim_words($watch->model_description, 30) : '',
                    'post_status' => 'publish',
                    'post_type' => 'fww_watch',
                    'post_author' => 1
                ));

                if (is_wp_error($post_id)) {
                    $this->log_error("Failed to create watch: {$watch_title}", $post_id->get_error_message());
                    continue;
                }

                // Store legacy IDs and metadata
                update_post_meta($post_id, '_fww_legacy_watch_id', $watch->watch_id);
                update_post_meta($post_id, '_fww_legacy_brand_id', $watch->brand_id);

                // Store brand relationship (if we have the mapped brand post ID)
                if (isset($this->mappings['brands'][$watch->brand_id])) {
                    update_post_meta($post_id, '_fww_brand_id', $this->mappings['brands'][$watch->brand_id]);
                }

                $this->mappings['watches'][$watch->watch_id] = $post_id;
                $this->stats['watches_created']++;
                $this->log("  CREATE: Watch '{$watch_title}' (ID: {$post_id})", 'verbose');
            } else {
                $this->stats['watches_created']++;
                $this->log("  WOULD CREATE: Watch '{$watch_title}'", 'verbose');
            }
        }

        $this->log("  Watches: {$this->stats['watches_created']} created, {$this->stats['watches_skipped']} skipped");
        $this->log("");
    }

    /**
     * Migrate actors from wp_fwd_actors to fww_actor posts
     */
    private function migrate_actors() {
        global $wpdb;

        $actors = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fwd_actors ORDER BY actor_id");

        if (empty($actors)) {
            $this->log("  No actors found in legacy database.");
            return;
        }

        $this->log("  Found " . count($actors) . " actors to migrate.");

        foreach ($actors as $actor) {
            // Check if actor already exists
            $existing = get_page_by_title($actor->actor_name, OBJECT, 'fww_actor');

            if ($existing) {
                $this->stats['actors_skipped']++;
                $this->mappings['actors'][$actor->actor_id] = $existing->ID;
                $this->log("  SKIP: Actor '{$actor->actor_name}' already exists (ID: {$existing->ID})", 'verbose');
                continue;
            }

            if (!$this->dry_run) {
                $content = !empty($actor->biography) ? wp_kses_post($actor->biography) : '';

                $post_id = wp_insert_post(array(
                    'post_title' => sanitize_text_field($actor->actor_name),
                    'post_content' => $content,
                    'post_excerpt' => !empty($actor->biography) ? wp_trim_words($actor->biography, 30) : '',
                    'post_status' => 'publish',
                    'post_type' => 'fww_actor',
                    'post_author' => 1
                ));

                if (is_wp_error($post_id)) {
                    $this->log_error("Failed to create actor: {$actor->actor_name}", $post_id->get_error_message());
                    continue;
                }

                // Store legacy ID
                update_post_meta($post_id, '_fww_legacy_actor_id', $actor->actor_id);

                // Store TMDB ID if available
                if (!empty($actor->tmdb_id)) {
                    update_post_meta($post_id, '_fww_tmdb_id', $actor->tmdb_id);
                }

                $this->mappings['actors'][$actor->actor_id] = $post_id;
                $this->stats['actors_created']++;
                $this->log("  CREATE: Actor '{$actor->actor_name}' (ID: {$post_id})", 'verbose');
            } else {
                $this->stats['actors_created']++;
                $this->log("  WOULD CREATE: Actor '{$actor->actor_name}'", 'verbose');
            }
        }

        $this->log("  Actors: {$this->stats['actors_created']} created, {$this->stats['actors_skipped']} skipped");
        $this->log("");
    }

    /**
     * Migrate movies from wp_fwd_films to fww_movie posts
     */
    private function migrate_movies() {
        global $wpdb;

        $movies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fwd_films ORDER BY film_id");

        if (empty($movies)) {
            $this->log("  No movies found in legacy database.");
            return;
        }

        $this->log("  Found " . count($movies) . " movies to migrate.");

        foreach ($movies as $movie) {
            // Build movie title with year
            $movie_title = $movie->title;
            if (!empty($movie->year)) {
                $movie_title .= " ({$movie->year})";
            }

            // Check if movie already exists
            $existing = get_page_by_title($movie->title, OBJECT, 'fww_movie');

            if ($existing) {
                $this->stats['movies_skipped']++;
                $this->mappings['movies'][$movie->film_id] = $existing->ID;
                $this->log("  SKIP: Movie '{$movie_title}' already exists (ID: {$existing->ID})", 'verbose');
                continue;
            }

            if (!$this->dry_run) {
                $post_id = wp_insert_post(array(
                    'post_title' => sanitize_text_field($movie->title),
                    'post_content' => !empty($movie->description) ? wp_kses_post($movie->description) : '',
                    'post_excerpt' => !empty($movie->description) ? wp_trim_words($movie->description, 30) : '',
                    'post_status' => 'publish',
                    'post_type' => 'fww_movie',
                    'post_author' => 1
                ));

                if (is_wp_error($post_id)) {
                    $this->log_error("Failed to create movie: {$movie_title}", $post_id->get_error_message());
                    continue;
                }

                // Store legacy ID and metadata
                update_post_meta($post_id, '_fww_legacy_film_id', $movie->film_id);
                update_post_meta($post_id, '_fww_film_id', $movie->film_id); // For backward compatibility

                if (!empty($movie->year)) {
                    update_post_meta($post_id, '_fww_year', $movie->year);
                }

                if (!empty($movie->tmdb_id)) {
                    update_post_meta($post_id, '_fww_tmdb_id', $movie->tmdb_id);

                    // Optionally fetch TMDB data
                    // $tmdb_data = FWW_TMDB_API::get_movie($movie->tmdb_id);
                    // if (!is_wp_error($tmdb_data)) {
                    //     update_post_meta($post_id, '_fww_tmdb_data', $tmdb_data);
                    // }
                }

                $this->mappings['movies'][$movie->film_id] = $post_id;
                $this->stats['movies_created']++;
                $this->log("  CREATE: Movie '{$movie_title}' (ID: {$post_id})", 'verbose');
            } else {
                $this->stats['movies_created']++;
                $this->log("  WOULD CREATE: Movie '{$movie_title}'", 'verbose');
            }
        }

        $this->log("  Movies: {$this->stats['movies_created']} created, {$this->stats['movies_skipped']} skipped");
        $this->log("");
    }

    /**
     * Migrate watch sightings from wp_fwd_film_actor_watch to wp_fww_sightings
     */
    private function migrate_sightings() {
        global $wpdb;

        $sightings = $wpdb->get_results("
            SELECT faw.*,
                   f.title as film_title,
                   a.actor_name,
                   c.character_name,
                   w.model_reference,
                   w.brand_id,
                   b.brand_name
            FROM {$wpdb->prefix}fwd_film_actor_watch faw
            LEFT JOIN {$wpdb->prefix}fwd_films f ON faw.film_id = f.film_id
            LEFT JOIN {$wpdb->prefix}fwd_actors a ON faw.actor_id = a.actor_id
            LEFT JOIN {$wpdb->prefix}fwd_characters c ON faw.character_id = c.character_id
            LEFT JOIN {$wpdb->prefix}fwd_watches w ON faw.watch_id = w.watch_id
            LEFT JOIN {$wpdb->prefix}fwd_brands b ON w.brand_id = b.brand_id
            ORDER BY faw.faw_id
        ");

        if (empty($sightings)) {
            $this->log("  No sightings found in legacy database.");
            return;
        }

        $this->log("  Found " . count($sightings) . " sightings to migrate.");

        foreach ($sightings as $sighting) {
            // Get mapped WordPress post IDs
            $movie_id = isset($this->mappings['movies'][$sighting->film_id]) ? $this->mappings['movies'][$sighting->film_id] : null;
            $actor_id = isset($this->mappings['actors'][$sighting->actor_id]) ? $this->mappings['actors'][$sighting->actor_id] : null;
            $watch_id = isset($this->mappings['watches'][$sighting->watch_id]) ? $this->mappings['watches'][$sighting->watch_id] : null;
            $brand_id = isset($this->mappings['brands'][$sighting->brand_id]) ? $this->mappings['brands'][$sighting->brand_id] : null;

            // If dry run, try to find existing mappings
            if ($this->dry_run) {
                if (!$movie_id) {
                    $movie_post = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fww_legacy_film_id' AND meta_value = %d",
                        $sighting->film_id
                    ));
                    if ($movie_post) $movie_id = $movie_post;
                }

                if (!$actor_id) {
                    $actor_post = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fww_legacy_actor_id' AND meta_value = %d",
                        $sighting->actor_id
                    ));
                    if ($actor_post) $actor_id = $actor_post;
                }

                if (!$watch_id) {
                    $watch_post = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fww_legacy_watch_id' AND meta_value = %d",
                        $sighting->watch_id
                    ));
                    if ($watch_post) $watch_id = $watch_post;
                }

                if (!$brand_id) {
                    $brand_post = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fww_legacy_brand_id' AND meta_value = %d",
                        $sighting->brand_id
                    ));
                    if ($brand_post) $brand_id = $brand_post;
                }
            }

            // Validate we have all required IDs
            if (!$movie_id || !$actor_id || !$watch_id || !$brand_id) {
                $missing = array();
                if (!$movie_id) $missing[] = "movie";
                if (!$actor_id) $missing[] = "actor";
                if (!$watch_id) $missing[] = "watch";
                if (!$brand_id) $missing[] = "brand";

                $this->log_error(
                    "Missing mapped IDs for sighting {$sighting->faw_id}",
                    "Missing: " . implode(', ', $missing) . " (Film: {$sighting->film_title}, Actor: {$sighting->actor_name})"
                );
                $this->stats['sightings_skipped']++;
                continue;
            }

            // Map confidence_level to verification_level
            $verification_level = 'unverified';
            if (!empty($sighting->confidence_level)) {
                $conf = strtolower($sighting->confidence_level);
                if (strpos($conf, 'confirmed') !== false || strpos($conf, 'high') !== false) {
                    $verification_level = 'confirmed';
                } elseif (strpos($conf, 'verified') !== false || strpos($conf, 'medium') !== false) {
                    $verification_level = 'verified';
                }
            }

            if (!$this->dry_run) {
                $sighting_data = array(
                    'movie_id' => $movie_id,
                    'actor_id' => $actor_id,
                    'character_name' => !empty($sighting->character_name) ? $sighting->character_name : '',
                    'watch_id' => $watch_id,
                    'brand_id' => $brand_id,
                    'scene_description' => !empty($sighting->narrative_role) ? $sighting->narrative_role : '',
                    'verification_level' => $verification_level,
                    'screenshot_url' => !empty($sighting->image_url) ? $sighting->image_url : '',
                    'source_url' => !empty($sighting->source_url) ? $sighting->source_url : '',
                    'legacy_id' => $sighting->faw_id
                );

                $result = FWW_Sightings::add_sighting($sighting_data);

                if (is_wp_error($result)) {
                    $this->log_error(
                        "Failed to create sighting {$sighting->faw_id}",
                        $result->get_error_message() . " (Film: {$sighting->film_title}, Actor: {$sighting->actor_name})"
                    );
                    $this->stats['sightings_skipped']++;
                } else {
                    $this->stats['sightings_created']++;
                    $this->log("  CREATE: Sighting '{$sighting->actor_name}' in '{$sighting->film_title}' (ID: {$result})", 'verbose');
                }
            } else {
                $this->stats['sightings_created']++;
                $this->log("  WOULD CREATE: Sighting '{$sighting->actor_name}' in '{$sighting->film_title}'", 'verbose');
            }
        }

        $this->log("  Sightings: {$this->stats['sightings_created']} created, {$this->stats['sightings_skipped']} skipped");
        $this->log("");
    }

    /**
     * Print final migration report
     */
    private function print_report() {
        $this->log("=== MIGRATION COMPLETE ===");
        $this->log("");
        $this->log("Summary:");
        $this->log("  Brands:    {$this->stats['brands_created']} created, {$this->stats['brands_skipped']} skipped");
        $this->log("  Watches:   {$this->stats['watches_created']} created, {$this->stats['watches_skipped']} skipped");
        $this->log("  Actors:    {$this->stats['actors_created']} created, {$this->stats['actors_skipped']} skipped");
        $this->log("  Movies:    {$this->stats['movies_created']} created, {$this->stats['movies_skipped']} skipped");
        $this->log("  Sightings: {$this->stats['sightings_created']} created, {$this->stats['sightings_skipped']} skipped");
        $this->log("");

        if (!empty($this->stats['errors'])) {
            $this->log("Errors encountered: " . count($this->stats['errors']));
            foreach ($this->stats['errors'] as $error) {
                $this->log("  ERROR: " . $error);
            }
            $this->log("");
        }

        if ($this->dry_run) {
            $this->log("*** THIS WAS A DRY RUN - NO CHANGES WERE MADE ***");
            $this->log("To perform the actual migration, run with dry_run = false");
        } else {
            $this->log("Migration completed successfully!");
        }
    }

    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level Log level (default, verbose)
     */
    private function log($message, $level = 'default') {
        if ($level === 'verbose' && !$this->verbose) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log($message);
        } else {
            echo $message . "\n";
        }
    }

    /**
     * Log an error
     *
     * @param string $context Error context
     * @param string $message Error message
     */
    private function log_error($context, $message) {
        $error_msg = "{$context}: {$message}";
        $this->stats['errors'][] = $error_msg;
        $this->log("  ERROR: " . $error_msg);
    }

    /**
     * Get migration statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Get ID mappings
     *
     * @return array Mappings
     */
    public function get_mappings() {
        return $this->mappings;
    }
}
