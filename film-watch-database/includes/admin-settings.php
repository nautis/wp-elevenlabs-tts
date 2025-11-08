<?php
/**
 * Admin Settings Page
 * Native PHP Implementation - No Flask backend required
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clean all escaped characters from database
 */
function fwd_clean_all_escaped_data() {
    global $wpdb;

    // Define table names
    $table_films = $wpdb->prefix . 'fwd_films';
    $table_actors = $wpdb->prefix . 'fwd_actors';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    $total_cleaned = 0;

    // Clean films table
    $films = $wpdb->get_results("SELECT film_id, title FROM {$table_films}");
    foreach ($films as $film) {
        $cleaned = stripslashes($film->title);
        if ($cleaned !== $film->title) {
            $wpdb->update($table_films, array('title' => $cleaned), array('film_id' => $film->film_id));
            $total_cleaned++;
        }
    }

    // Clean actors table
    $actors = $wpdb->get_results("SELECT actor_id, actor_name FROM {$table_actors}");
    foreach ($actors as $actor) {
        $cleaned = stripslashes($actor->actor_name);
        if ($cleaned !== $actor->actor_name) {
            $wpdb->update($table_actors, array('actor_name' => $cleaned), array('actor_id' => $actor->actor_id));
            $total_cleaned++;
        }
    }

    // Clean brands table
    $brands = $wpdb->get_results("SELECT brand_id, brand_name FROM {$table_brands}");
    foreach ($brands as $brand) {
        $cleaned = stripslashes($brand->brand_name);
        if ($cleaned !== $brand->brand_name) {
            $wpdb->update($table_brands, array('brand_name' => $cleaned), array('brand_id' => $brand->brand_id));
            $total_cleaned++;
        }
    }

    // Clean watches table
    $watches = $wpdb->get_results("SELECT watch_id, model_reference FROM {$table_watches}");
    foreach ($watches as $watch) {
        $cleaned = stripslashes($watch->model_reference);
        if ($cleaned !== $watch->model_reference) {
            $wpdb->update($table_watches, array('model_reference' => $cleaned), array('watch_id' => $watch->watch_id));
            $total_cleaned++;
        }
    }

    // Clean characters table
    $characters = $wpdb->get_results("SELECT character_id, character_name FROM {$table_characters}");
    foreach ($characters as $character) {
        $cleaned = stripslashes($character->character_name);
        if ($cleaned !== $character->character_name) {
            $wpdb->update($table_characters, array('character_name' => $cleaned), array('character_id' => $character->character_id));
            $total_cleaned++;
        }
    }

    // Clean film_actor_watch table
    $entries = $wpdb->get_results("SELECT faw_id, narrative_role, source_url FROM {$table_film_actor_watch}");
    foreach ($entries as $entry) {
        $updated = false;
        $data = array();

        if (!empty($entry->narrative_role)) {
            $cleaned = stripslashes($entry->narrative_role);
            if ($cleaned !== $entry->narrative_role) {
                $data['narrative_role'] = $cleaned;
                $updated = true;
            }
        }

        if (!empty($entry->source_url)) {
            $cleaned = stripslashes($entry->source_url);
            if ($cleaned !== $entry->source_url) {
                $data['source_url'] = $cleaned;
                $updated = true;
            }
        }

        if ($updated) {
            $wpdb->update($table_film_actor_watch, $data, array('faw_id' => $entry->faw_id));
            $total_cleaned++;
        }
    }

    return "✓ Cleaned {$total_cleaned} records across all tables.";
}

/**
 * Fix actor names that contain character names
 * Splits "Actor as Character" into separate actor and character records
 */
function fwd_fix_actor_character_split() {
    global $wpdb;

    $table_actors = $wpdb->prefix . 'fwd_actors';
    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    $fixed_count = 0;
    $actors = $wpdb->get_results("SELECT actor_id, actor_name FROM {$table_actors} WHERE actor_name LIKE '%as %' OR actor_name LIKE '%plays %'");

    foreach ($actors as $actor_row) {
        // Try to split "Actor as Character" or "Actor plays Character"
        if (preg_match('/^(.+?)\s+(?:as|plays)\s+(.+)$/i', $actor_row->actor_name, $matches)) {
            $clean_actor_name = trim($matches[1]);
            $character_name = trim($matches[2]);

            // Check if clean actor already exists
            $existing_actor_id = $wpdb->get_var($wpdb->prepare(
                "SELECT actor_id FROM {$table_actors} WHERE actor_name = %s AND actor_id != %d",
                $clean_actor_name, $actor_row->actor_id
            ));

            if ($existing_actor_id) {
                // Clean actor exists - update all references to use it
                $wpdb->update(
                    $table_film_actor_watch,
                    array('actor_id' => $existing_actor_id),
                    array('actor_id' => $actor_row->actor_id)
                );

                // Delete the corrupted actor record
                $wpdb->delete($table_actors, array('actor_id' => $actor_row->actor_id));
            } else {
                // Just rename the corrupted actor
                $wpdb->update(
                    $table_actors,
                    array('actor_name' => $clean_actor_name),
                    array('actor_id' => $actor_row->actor_id)
                );
            }

            // Now update character references in film_actor_watch
            $entries = $wpdb->get_results($wpdb->prepare(
                "SELECT faw.faw_id, faw.character_id, c.character_name
                 FROM {$table_film_actor_watch} faw
                 JOIN {$table_characters} c ON faw.character_id = c.character_id
                 WHERE faw.actor_id = %d OR faw.actor_id = %d",
                $actor_row->actor_id,
                $existing_actor_id ?: $actor_row->actor_id
            ));

            foreach ($entries as $entry) {
                // Check if this is a generic character name (like last name)
                // If so, update to the specific character name we found
                $name_parts = explode(' ', $clean_actor_name);
                $last_name = end($name_parts);

                if ($entry->character_name === $last_name || empty($entry->character_name)) {
                    // Find or create the specific character
                    $char_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT character_id FROM {$table_characters} WHERE character_name = %s LIMIT 1",
                        $character_name
                    ));

                    if (!$char_id) {
                        $wpdb->insert($table_characters, array('character_name' => $character_name));
                        $char_id = $wpdb->insert_id;
                    }

                    // Update the entry
                    $wpdb->update(
                        $table_film_actor_watch,
                        array('character_id' => $char_id),
                        array('faw_id' => $entry->faw_id)
                    );
                }
            }

            $fixed_count++;
        }
    }

    // Invalidate brands cache (in case any brand was in actor name by mistake)
    delete_transient('fwd_brands_list');

    return "✓ Fixed {$fixed_count} actor records with embedded character names.";
}

/**
 * Find duplicate entries
 */
function fwd_find_duplicates() {
    global $wpdb;

    // Define table names
    $table_films = $wpdb->prefix . 'fwd_films';
    $table_actors = $wpdb->prefix . 'fwd_actors';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    // Find duplicate film+actor+character combinations (same character in same film shouldn't have multiple watches)
    $duplicates = $wpdb->get_results("
        SELECT film_id, actor_id, character_id, COUNT(*) as count
        FROM {$table_film_actor_watch}
        GROUP BY film_id, actor_id, character_id
        HAVING count > 1
    ");

    $duplicate_groups = array();

    foreach ($duplicates as $dup) {
        $entries = $wpdb->get_results($wpdb->prepare("
            SELECT faw.*, f.title, f.year, a.actor_name, b.brand_name,
                   w.model_reference, c.character_name
            FROM {$table_film_actor_watch} faw
            JOIN {$table_films} f ON faw.film_id = f.film_id
            JOIN {$table_actors} a ON faw.actor_id = a.actor_id
            JOIN {$table_watches} w ON faw.watch_id = w.watch_id
            JOIN {$table_brands} b ON w.brand_id = b.brand_id
            JOIN {$table_characters} c ON faw.character_id = c.character_id
            WHERE faw.film_id = %d AND faw.actor_id = %d AND faw.character_id = %d
            ORDER BY faw.faw_id
        ", $dup->film_id, $dup->actor_id, $dup->character_id), ARRAY_A);

        $duplicate_groups[] = $entries;
    }

    return $duplicate_groups;
}

/**
 * Delete duplicate entry by ID
 */
function fwd_delete_duplicate($faw_id) {
    global $wpdb;

    // Define table name
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    return $wpdb->delete($table_film_actor_watch, array('faw_id' => $faw_id), array('%d'));
}

/**
 * Delete a film by title and year
 * Removes the film and all its relationships
 */
function fwd_delete_film_by_title($title, $year) {
    global $wpdb;

    $table_films = $wpdb->prefix . 'fwd_films';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    // Find the film_id
    $film_id = $wpdb->get_var($wpdb->prepare(
        "SELECT film_id FROM {$table_films} WHERE title = %s AND year = %d",
        $title, $year
    ));

    if (!$film_id) {
        return array('success' => false, 'message' => "Film not found: {$title} ({$year})");
    }

    // Delete all relationships first
    $relationships_deleted = $wpdb->delete(
        $table_film_actor_watch,
        array('film_id' => $film_id),
        array('%d')
    );

    // Delete the film
    $film_deleted = $wpdb->delete(
        $table_films,
        array('film_id' => $film_id),
        array('%d')
    );

    if ($film_deleted) {
        return array(
            'success' => true,
            'message' => "Deleted film: {$title} ({$year}). Removed {$relationships_deleted} relationship(s)."
        );
    } else {
        return array('success' => false, 'message' => "Failed to delete film: {$title} ({$year})");
    }
}

/**
 * Merge incorrect brand into correct brand
 * Reassigns all watches from wrong_brand_id to correct_brand_id, then deletes wrong brand
 */
function fwd_merge_brands($wrong_brand_id, $correct_brand_id) {
    global $wpdb;

    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_watches = $wpdb->prefix . 'fwd_watches';

    // Get brand names for confirmation message
    $wrong_brand = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$table_brands} WHERE brand_id = %d", $wrong_brand_id));
    $correct_brand = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$table_brands} WHERE brand_id = %d", $correct_brand_id));

    // Count watches that will be affected
    $watch_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_watches} WHERE brand_id = %d", $wrong_brand_id));

    if ($watch_count > 0) {
        // Update all watches to use correct brand
        $wpdb->update($table_watches, array('brand_id' => $correct_brand_id), array('brand_id' => $wrong_brand_id));
    }

    // Delete the wrong brand
    $wpdb->delete($table_brands, array('brand_id' => $wrong_brand_id), array('%d'));

    return "✓ Merged {$watch_count} watches from '{$wrong_brand}' into '{$correct_brand}' and deleted incorrect brand.";
}

/**
 * Merge duplicate watch models
 * Reassigns all entries from wrong_watch_id to correct_watch_id, then deletes wrong watch
 */
function fwd_merge_watches($wrong_watch_id, $correct_watch_id) {
    global $wpdb;

    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    // Get watch details for confirmation message
    $wrong_watch = $wpdb->get_row($wpdb->prepare("
        SELECT w.model_reference, b.brand_name
        FROM {$table_watches} w
        JOIN {$table_brands} b ON w.brand_id = b.brand_id
        WHERE w.watch_id = %d
    ", $wrong_watch_id));

    $correct_watch = $wpdb->get_row($wpdb->prepare("
        SELECT w.model_reference, b.brand_name
        FROM {$table_watches} w
        JOIN {$table_brands} b ON w.brand_id = b.brand_id
        WHERE w.watch_id = %d
    ", $correct_watch_id));

    // Count entries that will be affected
    $entry_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_film_actor_watch} WHERE watch_id = %d", $wrong_watch_id));

    if ($entry_count > 0) {
        // Update all entries to use correct watch
        $wpdb->update($table_film_actor_watch, array('watch_id' => $correct_watch_id), array('watch_id' => $wrong_watch_id));
    }

    // Delete the wrong watch
    $wpdb->delete($table_watches, array('watch_id' => $wrong_watch_id), array('%d'));

    return "✓ Merged {$entry_count} entries from '{$wrong_watch->brand_name} {$wrong_watch->model_reference}' into '{$correct_watch->brand_name} {$correct_watch->model_reference}' and deleted duplicate watch.";
}

/**
 * Find potential duplicate watches (same brand, similar model name, within same film)
 */
function fwd_find_duplicate_watches() {
    global $wpdb;

    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    $duplicates = array();

    // Get all watch pairs that appear in the same film with the same brand
    $potential_dupes = $wpdb->get_results("
        SELECT DISTINCT
            faw1.watch_id as watch1_id,
            faw2.watch_id as watch2_id,
            w1.model_reference as watch1_model,
            w2.model_reference as watch2_model,
            w1.brand_id,
            b.brand_name
        FROM {$table_film_actor_watch} faw1
        JOIN {$table_film_actor_watch} faw2
            ON faw1.film_id = faw2.film_id
            AND faw1.watch_id < faw2.watch_id
        JOIN {$table_watches} w1 ON faw1.watch_id = w1.watch_id
        JOIN {$table_watches} w2 ON faw2.watch_id = w2.watch_id
            AND w1.brand_id = w2.brand_id
        JOIN {$table_brands} b ON w1.brand_id = b.brand_id
    ");

    foreach ($potential_dupes as $pair) {
        $model1 = strtolower($pair->watch1_model);
        $model2 = strtolower($pair->watch2_model);

        // Check if one model name contains the other (e.g., "Chronograph" and "Chronograph 1")
        if (strpos($model1, $model2) !== false || strpos($model2, $model1) !== false) {

            // Get watch details with usage counts
            $watch1 = $wpdb->get_row($wpdb->prepare("
                SELECT w.watch_id, w.model_reference, b.brand_name,
                       (SELECT COUNT(*) FROM {$table_film_actor_watch} WHERE watch_id = w.watch_id) as usage_count
                FROM {$table_watches} w
                JOIN {$table_brands} b ON w.brand_id = b.brand_id
                WHERE w.watch_id = %d
            ", $pair->watch1_id));

            $watch2 = $wpdb->get_row($wpdb->prepare("
                SELECT w.watch_id, w.model_reference, b.brand_name,
                       (SELECT COUNT(*) FROM {$table_film_actor_watch} WHERE watch_id = w.watch_id) as usage_count
                FROM {$table_watches} w
                JOIN {$table_brands} b ON w.brand_id = b.brand_id
                WHERE w.watch_id = %d
            ", $pair->watch2_id));

            // Create unique key to avoid duplicate pairs
            $key = min($pair->watch1_id, $pair->watch2_id) . '_' . max($pair->watch1_id, $pair->watch2_id);

            if (!isset($duplicates[$key])) {
                $duplicates[$key] = array($watch1, $watch2);
            }
        }
    }

    return array_values($duplicates);
}

/**
 * Merge duplicate characters
 * Reassigns all entries from wrong_character_id to correct_character_id, then deletes wrong character
 */
function fwd_merge_characters($wrong_character_id, $correct_character_id) {
    global $wpdb;

    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    // Get character names for confirmation message
    $wrong_char = $wpdb->get_var($wpdb->prepare("SELECT character_name FROM {$table_characters} WHERE character_id = %d", $wrong_character_id));
    $correct_char = $wpdb->get_var($wpdb->prepare("SELECT character_name FROM {$table_characters} WHERE character_id = %d", $correct_character_id));

    // Count entries that will be affected
    $entry_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_film_actor_watch} WHERE character_id = %d", $wrong_character_id));

    if ($entry_count > 0) {
        // Update all entries to use correct character
        $wpdb->update($table_film_actor_watch, array('character_id' => $correct_character_id), array('character_id' => $wrong_character_id));
    }

    // Delete the wrong character
    $wpdb->delete($table_characters, array('character_id' => $wrong_character_id), array('%d'));

    return "✓ Merged {$entry_count} entries from '{$wrong_char}' into '{$correct_char}' and deleted duplicate character.";
}

/**
 * Find potential duplicate characters (similar names within the same film)
 */
function fwd_find_duplicate_characters() {
    global $wpdb;

    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    // Find characters that appear in the same films
    $duplicates = array();

    // Get all character pairs that appear in the same film
    $potential_dupes = $wpdb->get_results("
        SELECT DISTINCT
            faw1.character_id as char1_id,
            faw2.character_id as char2_id,
            faw1.film_id,
            c1.character_name as char1_name,
            c2.character_name as char2_name
        FROM {$table_film_actor_watch} faw1
        JOIN {$table_film_actor_watch} faw2
            ON faw1.film_id = faw2.film_id
            AND faw1.character_id < faw2.character_id
        JOIN {$table_characters} c1 ON faw1.character_id = c1.character_id
        JOIN {$table_characters} c2 ON faw2.character_id = c2.character_id
    ");

    foreach ($potential_dupes as $pair) {
        $name1 = strtolower(stripslashes($pair->char1_name));
        $name2 = strtolower(stripslashes($pair->char2_name));

        // Remove quotes and normalize for comparison
        $name1_clean = str_replace(['"', "'", '\\'], '', $name1);
        $name2_clean = str_replace(['"', "'", '\\'], '', $name2);

        // Check if names are identical or one contains the other
        if ($name1_clean === $name2_clean ||
            strpos($name1_clean, $name2_clean) !== false ||
            strpos($name2_clean, $name1_clean) !== false) {

            // Get film appearances for both characters
            $char1_films = $wpdb->get_results($wpdb->prepare("
                SELECT f.title, f.year, a.actor_name
                FROM {$table_film_actor_watch} faw
                JOIN {$wpdb->prefix}fwd_films f ON faw.film_id = f.film_id
                JOIN {$wpdb->prefix}fwd_actors a ON faw.actor_id = a.actor_id
                WHERE faw.character_id = %d
                ORDER BY f.year, f.title
            ", $pair->char1_id));

            $char2_films = $wpdb->get_results($wpdb->prepare("
                SELECT f.title, f.year, a.actor_name
                FROM {$table_film_actor_watch} faw
                JOIN {$wpdb->prefix}fwd_films f ON faw.film_id = f.film_id
                JOIN {$wpdb->prefix}fwd_actors a ON faw.actor_id = a.actor_id
                WHERE faw.character_id = %d
                ORDER BY f.year, f.title
            ", $pair->char2_id));

            // Get character objects with usage counts
            $char1 = $wpdb->get_row($wpdb->prepare("
                SELECT c.character_id, c.character_name,
                       (SELECT COUNT(*) FROM {$table_film_actor_watch} WHERE character_id = c.character_id) as usage_count
                FROM {$table_characters} c
                WHERE c.character_id = %d
            ", $pair->char1_id));

            $char2 = $wpdb->get_row($wpdb->prepare("
                SELECT c.character_id, c.character_name,
                       (SELECT COUNT(*) FROM {$table_film_actor_watch} WHERE character_id = c.character_id) as usage_count
                FROM {$table_characters} c
                WHERE c.character_id = %d
            ", $pair->char2_id));

            // Create unique key to avoid duplicate pairs
            $key = min($pair->char1_id, $pair->char2_id) . '_' . max($pair->char1_id, $pair->char2_id);

            if (!isset($duplicates[$key])) {
                $duplicates[$key] = array(
                    'char1' => $char1,
                    'char1_films' => $char1_films,
                    'char2' => $char2,
                    'char2_films' => $char2_films
                );
            }
        }
    }

    return array_values($duplicates);
}

/**
 * Get all records with pagination and search
 */
function fwd_get_all_records($page = 1, $per_page = 20, $search = '', $sort_column = 'title', $sort_order = 'asc') {
    global $wpdb;

    $table_films = $wpdb->prefix . 'fwd_films';
    $table_actors = $wpdb->prefix . 'fwd_actors';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    $offset = ($page - 1) * $per_page;

    // Build WHERE clause for search
    $where = '';
    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where = $wpdb->prepare(
            " WHERE f.title LIKE %s OR a.actor_name LIKE %s OR b.brand_name LIKE %s OR c.character_name LIKE %s",
            $search_like, $search_like, $search_like, $search_like
        );
    }

    // Map column names to SQL table aliases (whitelist for security)
    $column_map = array(
        'faw_id' => 'faw.faw_id',
        'title' => 'f.title',
        'year' => 'f.year',
        'actor_name' => 'a.actor_name',
        'character_name' => 'c.character_name',
        'brand_name' => 'b.brand_name',
        'model_reference' => 'w.model_reference'
    );

    // Validate sort column and order
    $order_column = isset($column_map[$sort_column]) ? $column_map[$sort_column] : 'f.title';
    $order_direction = (strtolower($sort_order) === 'desc') ? 'DESC' : 'ASC';

    // Get total count
    $total = $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$table_film_actor_watch} faw
        JOIN {$table_films} f ON faw.film_id = f.film_id
        JOIN {$table_actors} a ON faw.actor_id = a.actor_id
        JOIN {$table_characters} c ON faw.character_id = c.character_id
        JOIN {$table_watches} w ON faw.watch_id = w.watch_id
        JOIN {$table_brands} b ON w.brand_id = b.brand_id
        {$where}
    ");

    // Get records
    $records = $wpdb->get_results($wpdb->prepare("
        SELECT faw.faw_id, f.title, f.year, a.actor_name, b.brand_name,
               w.model_reference, c.character_name, faw.narrative_role,
               faw.image_url, faw.confidence_level, faw.source_url
        FROM {$table_film_actor_watch} faw
        JOIN {$table_films} f ON faw.film_id = f.film_id
        JOIN {$table_actors} a ON faw.actor_id = a.actor_id
        JOIN {$table_characters} c ON faw.character_id = c.character_id
        JOIN {$table_watches} w ON faw.watch_id = w.watch_id
        JOIN {$table_brands} b ON w.brand_id = b.brand_id
        {$where}
        ORDER BY {$order_column} {$order_direction}
        LIMIT %d OFFSET %d
    ", $per_page, $offset), ARRAY_A);

    return array(
        'records' => $records,
        'total' => (int)$total,
        'page' => (int)$page,
        'per_page' => (int)$per_page
    );
}

/**
 * Get a single record by ID
 */
function fwd_get_single_record($faw_id) {
    global $wpdb;

    $table_films = $wpdb->prefix . 'fwd_films';
    $table_actors = $wpdb->prefix . 'fwd_actors';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    $record = $wpdb->get_row($wpdb->prepare("
        SELECT faw.faw_id, f.title, f.year, a.actor_name, b.brand_name,
               w.model_reference, c.character_name, faw.narrative_role,
               faw.image_url, faw.confidence_level, faw.source_url,
               f.film_id, a.actor_id, w.watch_id, c.character_id, w.brand_id
        FROM {$table_film_actor_watch} faw
        JOIN {$table_films} f ON faw.film_id = f.film_id
        JOIN {$table_actors} a ON faw.actor_id = a.actor_id
        JOIN {$table_characters} c ON faw.character_id = c.character_id
        JOIN {$table_watches} w ON faw.watch_id = w.watch_id
        JOIN {$table_brands} b ON w.brand_id = b.brand_id
        WHERE faw.faw_id = %d
    ", $faw_id), ARRAY_A);

    return $record;
}

/**
 * Update a record
 */
function fwd_update_record($faw_id, $data) {
    global $wpdb;

    $table_films = $wpdb->prefix . 'fwd_films';
    $table_actors = $wpdb->prefix . 'fwd_actors';
    $table_brands = $wpdb->prefix . 'fwd_brands';
    $table_watches = $wpdb->prefix . 'fwd_watches';
    $table_characters = $wpdb->prefix . 'fwd_characters';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    try {
        $wpdb->query('START TRANSACTION');

        // Get or create film
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table_films} (title, year) VALUES (%s, %d)",
            $data['film'], $data['year']
        ));
        $film_id = $wpdb->get_var($wpdb->prepare(
            "SELECT film_id FROM {$table_films} WHERE title = %s AND year = %d",
            $data['film'], $data['year']
        ));

        // Get or create actor
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table_actors} (actor_name) VALUES (%s)",
            $data['actor']
        ));
        $actor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT actor_id FROM {$table_actors} WHERE actor_name = %s",
            $data['actor']
        ));

        // Get or create character
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table_characters} (character_name) VALUES (%s)",
            $data['character']
        ));
        $character_id = $wpdb->get_var($wpdb->prepare(
            "SELECT character_id FROM {$table_characters} WHERE character_name = %s",
            $data['character']
        ));

        // Get or create brand
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table_brands} (brand_name) VALUES (%s)",
            $data['brand']
        ));
        if ($result) {
            delete_transient('fwd_brands_list');
        }
        $brand_id = $wpdb->get_var($wpdb->prepare(
            "SELECT brand_id FROM {$table_brands} WHERE brand_name = %s",
            $data['brand']
        ));

        // Get or create watch
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table_watches} (brand_id, model_reference) VALUES (%d, %s)",
            $brand_id, $data['model']
        ));
        $watch_id = $wpdb->get_var($wpdb->prepare(
            "SELECT watch_id FROM {$table_watches} WHERE brand_id = %d AND model_reference = %s",
            $brand_id, $data['model']
        ));

        // Check if this combination already exists in a different record
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT faw_id FROM {$table_film_actor_watch}
             WHERE film_id = %d AND actor_id = %d AND character_id = %d AND watch_id = %d
             AND faw_id != %d",
            $film_id, $actor_id, $character_id, $watch_id, $faw_id
        ));

        if ($duplicate) {
            $wpdb->query('ROLLBACK');
            throw new Exception("This combination already exists in record #" . $duplicate);
        }

        // Update the film_actor_watch record
        $wpdb->update(
            $table_film_actor_watch,
            array(
                'film_id' => $film_id,
                'actor_id' => $actor_id,
                'character_id' => $character_id,
                'watch_id' => $watch_id,
                'narrative_role' => $data['narrative'],
                'image_url' => $data['image_url'],
                'confidence_level' => $data['confidence_level'],
                'source_url' => $data['source_url']
            ),
            array('faw_id' => $faw_id)
        );

        $wpdb->query('COMMIT');
        return array('success' => true);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'message' => $e->getMessage());
    }
}

/**
 * Delete a record by ID
 */
function fwd_delete_record_by_id($faw_id) {
    global $wpdb;
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    return $wpdb->delete($table_film_actor_watch, array('faw_id' => $faw_id), array('%d'));
}

/**
 * AJAX handler: Get records
 */
function fwd_ajax_get_records() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $sort_column = isset($_POST['sort_column']) ? sanitize_text_field($_POST['sort_column']) : 'title';
    $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'asc';

    $result = fwd_get_all_records($page, $per_page, $search, $sort_column, $sort_order);

    // Format the records for display
    $formatted_records = array();
    foreach ($result['records'] as $record) {
        $formatted_records[] = array(
            'faw_id' => $record['faw_id'],
            'title' => $record['title'],
            'year' => $record['year'],
            'actor' => $record['actor_name'],
            'character' => $record['character_name'],
            'brand' => $record['brand_name'],
            'model' => $record['model_reference']
        );
    }

    wp_send_json_success(array(
        'records' => $formatted_records,
        'total' => $result['total'],
        'page' => $result['page'],
        'per_page' => $result['per_page']
    ));
}
add_action('wp_ajax_fwd_get_records', 'fwd_ajax_get_records');

/**
 * AJAX handler: Get single record
 */
function fwd_ajax_get_record() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $faw_id = isset($_POST['faw_id']) ? intval($_POST['faw_id']) : 0;

    if (!$faw_id) {
        wp_send_json_error(array('message' => 'Invalid record ID'));
        return;
    }

    $record = fwd_get_single_record($faw_id);

    if (!$record) {
        wp_send_json_error(array('message' => 'Record not found'));
        return;
    }

    wp_send_json_success(array(
        'faw_id' => $record['faw_id'],
        'title' => $record['title'],
        'year' => $record['year'],
        'actor' => $record['actor_name'],
        'character' => $record['character_name'],
        'brand' => $record['brand_name'],
        'model' => $record['model_reference'],
        'narrative' => $record['narrative_role'],
        'image_url' => $record['image_url'],
        'confidence_level' => $record['confidence_level'],
        'source_url' => $record['source_url']
    ));
}
add_action('wp_ajax_fwd_get_record', 'fwd_ajax_get_record');

/**
 * AJAX handler: Update record
 */
function fwd_ajax_update_record() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $faw_id = isset($_POST['faw_id']) ? intval($_POST['faw_id']) : 0;

    if (!$faw_id) {
        wp_send_json_error(array('message' => 'Invalid record ID'));
        return;
    }

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $data = array(
        'film' => sanitize_text_field(wp_unslash($_POST['film'])),
        'year' => intval($_POST['year']),
        'actor' => sanitize_text_field(wp_unslash($_POST['actor'])),
        'character' => sanitize_text_field(wp_unslash($_POST['character'])),
        'brand' => sanitize_text_field(wp_unslash($_POST['brand'])),
        'model' => sanitize_text_field(wp_unslash($_POST['model'])),
        'narrative' => sanitize_textarea_field(wp_unslash($_POST['narrative'])),
        'image_url' => esc_url_raw(wp_unslash($_POST['image_url'])),
        'confidence_level' => sanitize_textarea_field(wp_unslash($_POST['confidence_level'])),
        'source_url' => esc_url_raw(wp_unslash($_POST['source_url']))
    );

    $result = fwd_update_record($faw_id, $data);

    if ($result['success']) {
        wp_send_json_success(array('message' => 'Record updated successfully'));
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}
add_action('wp_ajax_fwd_update_record', 'fwd_ajax_update_record');

/**
 * AJAX handler: Delete record
 */
function fwd_ajax_delete_record() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $faw_id = isset($_POST['faw_id']) ? intval($_POST['faw_id']) : 0;

    if (!$faw_id) {
        wp_send_json_error(array('message' => 'Invalid record ID'));
        return;
    }

    $result = fwd_delete_record_by_id($faw_id);

    if ($result) {
        wp_send_json_success(array('message' => 'Record deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete record'));
    }
}
add_action('wp_ajax_fwd_delete_record', 'fwd_ajax_delete_record');

/**
 * Register admin menu
 */
function fwd_add_admin_menu() {
    add_options_page(
        'Film Watch Database Settings',
        'Film Watch DB',
        'manage_options',
        'film-watch-database',
        'fwd_settings_page'
    );
}
add_action('admin_menu', 'fwd_add_admin_menu');

/**
 * Settings page HTML
 */
function fwd_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Handle AI settings save
    if (isset($_POST['fwd_save_ai_settings']) && check_admin_referer('fwd_ai_settings_action', 'fwd_ai_settings_nonce')) {
        $api_key = sanitize_text_field($_POST['fwd_claude_api_key']);
        $threshold = floatval($_POST['fwd_ai_confidence_threshold']);
        $threshold = max(0.0, min(1.0, $threshold)); // Clamp between 0 and 1

        update_option('fwd_claude_api_key', $api_key);
        update_option('fwd_ai_confidence_threshold', $threshold);

        echo '<div class="notice notice-success is-dismissible"><p>✓ AI settings saved.</p></div>';
    }

    // Handle TMDB API settings save
    if (isset($_POST['fwd_save_tmdb_settings']) && check_admin_referer('fwd_tmdb_settings_action', 'fwd_tmdb_settings_nonce')) {
        $tmdb_api_key = sanitize_text_field($_POST['fwd_tmdb_api_key']);
        $tmdb_language = sanitize_text_field($_POST['fwd_tmdb_language']);
        $tmdb_cache_hours = intval($_POST['fwd_tmdb_cache_hours']);
        $tmdb_cache_hours = max(1, min(168, $tmdb_cache_hours)); // Clamp between 1-168 hours

        update_option('fwd_tmdb_api_key', $tmdb_api_key);
        update_option('fwd_tmdb_language', $tmdb_language);
        update_option('fwd_tmdb_cache_hours', $tmdb_cache_hours);

        echo '<div class="notice notice-success is-dismissible"><p>✓ TMDB API settings saved.</p></div>';
    }

    // Handle cleanup action
    if (isset($_POST['fwd_global_cleanup']) && check_admin_referer('fwd_global_cleanup_action', 'fwd_global_cleanup_nonce')) {
        $result = fwd_clean_all_escaped_data();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
    }

    // Handle actor/character split fix
    if (isset($_POST['fwd_fix_actor_split']) && check_admin_referer('fwd_fix_actor_split_action', 'fwd_fix_actor_split_nonce')) {
        $result = fwd_fix_actor_character_split();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
    }

    // Handle duplicate deletion
    if (isset($_POST['fwd_delete_duplicate']) && check_admin_referer('fwd_duplicate_action', 'fwd_duplicate_nonce')) {
        $faw_id = intval($_POST['faw_id']);
        fwd_delete_duplicate($faw_id);
        echo '<div class="notice notice-success is-dismissible"><p>✓ Duplicate entry deleted.</p></div>';
    }

    // Handle brand merge
    if (isset($_POST['fwd_merge_brands']) && check_admin_referer('fwd_brand_merge_action', 'fwd_brand_merge_nonce')) {
        $wrong_brand_id = intval($_POST['wrong_brand_id']);
        $correct_brand_id = intval($_POST['correct_brand_id']);
        $result = fwd_merge_brands($wrong_brand_id, $correct_brand_id);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
    }

    // Handle watch merge
    if (isset($_POST['fwd_merge_watches']) && check_admin_referer('fwd_watch_merge_action', 'fwd_watch_merge_nonce')) {
        $wrong_watch_id = intval($_POST['wrong_watch_id']);
        $correct_watch_id = intval($_POST['correct_watch_id']);
        $result = fwd_merge_watches($wrong_watch_id, $correct_watch_id);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
    }

    // Handle character merge
    if (isset($_POST['fwd_merge_characters']) && check_admin_referer('fwd_character_merge_action', 'fwd_character_merge_nonce')) {
        $wrong_character_id = intval($_POST['wrong_character_id']);
        $correct_character_id = intval($_POST['correct_character_id']);
        $result = fwd_merge_characters($wrong_character_id, $correct_character_id);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
    }

    // Get database stats
    global $wpdb;
    $stats = fwd_get_stats();
    $ai_stats = fwd_get_ai_stats();
    $api_key = get_option('fwd_claude_api_key', '');
    $threshold = get_option('fwd_ai_confidence_threshold', 0.7);

    // Get TMDB settings
    $tmdb_api_key = get_option('fwd_tmdb_api_key', '');
    $tmdb_language = get_option('fwd_tmdb_language', 'en');
    $tmdb_cache_hours = get_option('fwd_tmdb_cache_hours', 24);

    ?>
    <div class="wrap">
        <h1>Film Watch Database Settings</h1>

        <!-- Main Tab Navigation -->
        <style>
            .fwd-admin-tabs {
                border-bottom: 1px solid #ccd0d4;
                margin: 20px 0 0 0;
                padding: 0;
            }
            .fwd-admin-tabs button {
                padding: 10px 20px;
                margin: 0 5px 0 0;
                background: #f0f0f1;
                color: #2c3338;
                border: none;
                border-bottom: 2px solid transparent;
                cursor: pointer;
                font-size: 14px;
            }
            .fwd-admin-tabs button.active {
                background: #fff;
                color: #0073aa;
                border-bottom-color: #0073aa;
            }
            .fwd-admin-tabs button:hover {
                background: #fff;
            }
            .fwd-admin-tab-content {
                display: none;
                padding: 20px 0;
            }
            .fwd-admin-tab-content.active {
                display: block;
            }
        </style>

        <div class="fwd-admin-tabs">
            <button class="fwd-admin-tab-btn active" data-tab="add-entry">Add New Entry</button>
            <button class="fwd-admin-tab-btn" data-tab="manage-records">Manage Records</button>
            <button class="fwd-admin-tab-btn" data-tab="ai-parser">AI Parser Settings</button>
            <button class="fwd-admin-tab-btn" data-tab="shortcodes">Shortcode Usage</button>
            <button class="fwd-admin-tab-btn" data-tab="maintenance">Database Maintenance</button>
        </div>

        <!-- Tab 2: AI Parser Settings -->
        <div class="fwd-admin-tab-content" id="fwd-admin-tab-ai-parser">
        <!-- AI Parser Settings -->
        <div style="background: #f0f6fc; padding: 20px; border-left: 4px solid #0073aa; margin: 20px 0;">
            <h2 style="margin-top: 0;">🤖 AI-Powered Parser Settings</h2>
            <p>Enable AI fallback for natural language parsing. When regex parsing has low confidence, Claude AI will automatically parse the entry.</p>

            <form method="post" style="margin: 20px 0;">
                <?php wp_nonce_field('fwd_ai_settings_action', 'fwd_ai_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fwd_claude_api_key">Claude API Key</label></th>
                        <td>
                            <input type="password" id="fwd_claude_api_key" name="fwd_claude_api_key"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   class="regular-text"
                                   placeholder="sk-ant-api03-...">
                            <p class="description">
                                Get your API key from <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>
                                (free tier available, ~$5 for thousands of parses)
                                <?php if (!empty($api_key)): ?>
                                    <br><span style="color: #46b450;">✓ API key configured</span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fwd_ai_confidence_threshold">Confidence Threshold</label></th>
                        <td>
                            <input type="range" id="fwd_ai_confidence_threshold" name="fwd_ai_confidence_threshold"
                                   min="0" max="1" step="0.1"
                                   value="<?php echo esc_attr($threshold); ?>"
                                   oninput="this.nextElementSibling.textContent = this.value">
                            <span style="display: inline-block; width: 40px; text-align: center; font-weight: bold;"><?php echo esc_html($threshold); ?></span>
                            <p class="description">
                                If regex confidence is below this threshold, AI will be used.
                                <strong>0.7 recommended</strong> (lower = use AI more often)
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="fwd_save_ai_settings" class="button button-primary">Save AI Settings</button>
                </p>
            </form>

            <?php if (!empty($api_key)): ?>
            <!-- AI Usage Stats -->
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-top: 20px;">
                <h3 style="margin-top: 0;">API Usage Statistics</h3>
                <table style="width: 100%;">
                    <tr>
                        <td><strong>Total API Calls:</strong></td>
                        <td><?php echo intval($ai_stats['total_calls']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Successful:</strong></td>
                        <td><?php echo intval($ai_stats['successful_calls']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Failed:</strong></td>
                        <td><?php echo intval($ai_stats['failed_calls']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Estimated Cost:</strong></td>
                        <td>$<?php echo number_format($ai_stats['estimated_cost'], 4); ?></td>
                    </tr>
                    <?php if ($ai_stats['last_call']): ?>
                    <tr>
                        <td><strong>Last Call:</strong></td>
                        <td><?php echo esc_html($ai_stats['last_call']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Test Parser -->
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-top: 20px;">
                <h3 style="margin-top: 0;">Test Parser</h3>
                <p>Test the AI parser with example text:</p>
                <input type="text" id="fwd-test-parser-input" class="regular-text"
                       placeholder="Rudolph Valentino as Ahmed Ben Hassan wears Rolex in The Sheik (1921)"
                       style="width: 100%; max-width: 600px;">
                <button type="button" id="fwd-test-parser-btn" class="button" style="margin-left: 10px;">Test Parse</button>

                <div id="fwd-test-parser-result" style="margin-top: 15px; display: none;"></div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                $('#fwd-test-parser-btn').on('click', function() {
                    var text = $('#fwd-test-parser-input').val();
                    var $result = $('#fwd-test-parser-result');
                    var $btn = $(this);

                    if (!text) {
                        alert('Please enter text to parse');
                        return;
                    }

                    $btn.prop('disabled', true).text('Parsing...');
                    $result.html('').hide();

                    $.post(ajaxurl, {
                        action: 'fwd_test_ai_parser',
                        nonce: fwdAjax.nonce,
                        test_text: text
                    }, function(response) {
                        $btn.prop('disabled', false).text('Test Parse');

                        if (response.success) {
                            var data = response.data;
                            var html = '<div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #46b450;">';
                            html += '<h4 style="margin-top: 0;">✓ Parsed Successfully (' + data.parsed_by + ')</h4>';
                            html += '<table style="width: 100%;">';
                            html += '<tr><td><strong>Actor:</strong></td><td>' + data.actor + '</td></tr>';
                            html += '<tr><td><strong>Character:</strong></td><td>' + data.character + '</td></tr>';
                            html += '<tr><td><strong>Brand:</strong></td><td>' + data.brand + '</td></tr>';
                            html += '<tr><td><strong>Model:</strong></td><td>' + data.model + '</td></tr>';
                            html += '<tr><td><strong>Film:</strong></td><td>' + data.title + ' (' + data.year + ')</td></tr>';
                            html += '<tr><td><strong>Confidence score: </strong></td><td>' + (data.confidence || 'N/A') + '</td></tr>';
                            if (data.warning) {
                                html += '<tr><td colspan="2"><em style="color: #f0b849;">⚠ ' + data.warning + '</em></td></tr>';
                            }
                            html += '</table></div>';
                            $result.html(html).show();
                        } else {
                            $result.html('<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #dc3232;">❌ Error: ' + response.data.message + '</div>').show();
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>

            <!-- TMDB API Settings -->
            <div style="background: #f0f6fc; padding: 20px; border-left: 4px solid #0073aa; margin: 40px 0;">
                <h2 style="margin-top: 0;">🎬 TMDB API Settings</h2>
                <p>Connect to The Movie Database (TMDB) to enable autocomplete for actors, films, and automatic movie poster fetching.</p>

                <form method="post" style="margin: 20px 0;">
                    <?php wp_nonce_field('fwd_tmdb_settings_action', 'fwd_tmdb_settings_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="fwd_tmdb_api_key">TMDB API Key (Read Access Token)</label></th>
                            <td>
                                <input type="password" id="fwd_tmdb_api_key" name="fwd_tmdb_api_key"
                                       value="<?php echo esc_attr($tmdb_api_key); ?>"
                                       class="regular-text"
                                       placeholder="eyJhbGci...">
                                <p class="description">
                                    Get your free API Read Access Token from <a href="https://www.themoviedb.org/settings/api" target="_blank">TMDB API Settings</a>
                                    <?php if (!empty($tmdb_api_key)): ?>
                                        <br><span style="color: #46b450;">✓ API key configured</span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fwd_tmdb_language">Language</label></th>
                            <td>
                                <select id="fwd_tmdb_language" name="fwd_tmdb_language">
                                    <option value="en" <?php selected($tmdb_language, 'en'); ?>>English</option>
                                    <option value="es" <?php selected($tmdb_language, 'es'); ?>>Spanish</option>
                                    <option value="fr" <?php selected($tmdb_language, 'fr'); ?>>French</option>
                                    <option value="de" <?php selected($tmdb_language, 'de'); ?>>German</option>
                                    <option value="it" <?php selected($tmdb_language, 'it'); ?>>Italian</option>
                                    <option value="pt" <?php selected($tmdb_language, 'pt'); ?>>Portuguese</option>
                                    <option value="ja" <?php selected($tmdb_language, 'ja'); ?>>Japanese</option>
                                    <option value="ko" <?php selected($tmdb_language, 'ko'); ?>>Korean</option>
                                    <option value="zh" <?php selected($tmdb_language, 'zh'); ?>>Chinese</option>
                                </select>
                                <p class="description">Language for movie/actor search results</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fwd_tmdb_cache_hours">Cache Duration (hours)</label></th>
                            <td>
                                <input type="number" id="fwd_tmdb_cache_hours" name="fwd_tmdb_cache_hours"
                                       value="<?php echo esc_attr($tmdb_cache_hours); ?>"
                                       min="1" max="168" step="1"
                                       style="width: 80px;">
                                <p class="description">
                                    How long to cache TMDB API responses (1-168 hours). Default: 24 hours
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="submit" name="fwd_save_tmdb_settings" class="button button-primary">Save TMDB Settings</button>
                    </p>
                </form>

                <?php if (!empty($tmdb_api_key)): ?>
                <!-- TMDB Connection Test -->
                <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-top: 20px;">
                    <h3 style="margin-top: 0;">Test TMDB Connection</h3>
                    <p>Test your TMDB API key by searching for a movie:</p>
                    <input type="text" id="fwd-test-tmdb-input" class="regular-text"
                           placeholder="Casino Royale"
                           style="width: 100%; max-width: 400px;">
                    <button type="button" id="fwd-test-tmdb-btn" class="button" style="margin-left: 10px;">Search Movie</button>

                    <div id="fwd-test-tmdb-result" style="margin-top: 15px; display: none;"></div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#fwd-test-tmdb-btn').on('click', function() {
                        var query = $('#fwd-test-tmdb-input').val();
                        var $result = $('#fwd-test-tmdb-result');
                        var $btn = $(this);

                        if (!query) {
                            alert('Please enter a movie title');
                            return;
                        }

                        $btn.prop('disabled', true).text('Searching...');
                        $result.html('').hide();

                        $.post(ajaxurl, {
                            action: 'fwd_test_tmdb',
                            nonce: fwdAjax.nonce,
                            query: query
                        }, function(response) {
                            $btn.prop('disabled', false).text('Search Movie');

                            if (response.success) {
                                var movies = response.data;
                                var html = '<div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #46b450;">';
                                html += '<h4 style="margin-top: 0;">✓ Connection Successful</h4>';
                                html += '<p>Found ' + movies.length + ' results:</p>';
                                html += '<ul>';
                                movies.forEach(function(movie) {
                                    html += '<li><strong>' + movie.title + '</strong> (' + movie.year + ')';
                                    if (movie.poster) {
                                        html += '<br><img src="' + movie.poster + '" style="max-width: 92px; margin-top: 5px;">';
                                    }
                                    html += '</li>';
                                });
                                html += '</ul></div>';
                                $result.html(html).show();
                            } else {
                                $result.html('<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #dc3232;">❌ Error: ' + response.data.message + '</div>').show();
                            }
                        });
                    });
                });
                </script>
                <?php endif; ?>
            </div>

        </div>
        </div>
        <!-- End Tab 2: AI Parser Settings -->

        <!-- Tab 1: Add New Entry -->
        <div class="fwd-admin-tab-content active" id="fwd-admin-tab-add-entry">
        <!-- Add New Entry Section -->
        <div class="fwd-add-container" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0;">
            <h2 style="margin-top: 0;">Add New Entry</h2>

            <!-- Tab Navigation -->
            <div class="fwd-tabs" style="margin-bottom: 20px; border-bottom: 1px solid #ccd0d4;">
                <button class="fwd-tab-btn active" data-tab="form" style="padding: 10px 20px; margin-right: 5px; background: #0073aa; color: white; border: none; cursor: pointer;">Natural Language</button>
                <button class="fwd-tab-btn" data-tab="tmdb" style="padding: 10px 20px; margin-right: 5px; background: #f0f0f1; color: #2c3338; border: none; cursor: pointer;">TMDB-Powered</button>
                <button class="fwd-tab-btn" data-tab="quick" style="padding: 10px 20px; margin-right: 5px; background: #f0f0f1; color: #2c3338; border: none; cursor: pointer;">Quick Entry</button>
                <button class="fwd-tab-btn" data-tab="bulk" style="padding: 10px 20px; background: #f0f0f1; color: #2c3338; border: none; cursor: pointer;">Bulk CSV Import</button>
            </div>

            <!-- Tab 1: Structured Form -->
            <div class="fwd-tab-content active" id="fwd-tab-form">
                <div class="fwd-examples" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
                    <strong>Examples:</strong><br>
                    • "Jakob Cedergren wears Citizen Eco-Drive Divers 200M in The Guilty (2018)"<br>
                    • "Tom Cruise wears Breitling Navitimer in Top Gun: Maverick (2022)"<br>
                    • "In Interstellar (2014), Matthew McConaughey as Cooper wears Hamilton Khaki Pilot"
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fwd-entry-text">Entry Text:</label></th>
                        <td>
                            <textarea
                                id="fwd-entry-text"
                                class="large-text"
                                rows="2"
                                placeholder="Actor wears Brand Model in Film (Year)"
                            ></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fwd-narrative">Narrative Role (optional):</label></th>
                        <td>
                            <textarea
                                id="fwd-narrative"
                                class="large-text"
                                rows="3"
                                placeholder="Describe the watch's role in the film..."
                            ></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fwd-image-url">Image URL (optional):</label></th>
                        <td>
                            <input
                                type="url"
                                id="fwd-image-url"
                                class="regular-text"
                                placeholder="https://example.com/watch-image.jpg"
                            >
                            <button type="button" id="fwd-upload-image-btn" class="button">Upload Image</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fwd-confidence">Confidence Level (optional):</label></th>
                        <td>
                            <textarea
                                id="fwd-confidence"
                                class="large-text"
                                rows="3"
                                placeholder="e.g., Confidence Score: 70% – Grant's Tank ownership extensively documented; specific appearance in The Philadelphia Story mentioned but not independently verified across multiple sources."
                            ></textarea>
                            <p class="description">Describe verification level, source reliability, and any uncertainties.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fwd-source-url">Source URL (optional):</label></th>
                        <td>
                            <input
                                type="url"
                                id="fwd-source-url"
                                class="regular-text"
                                placeholder="https://example.com/source-article"
                            >
                            <p class="description">Link to the source where this information was verified.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button id="fwd-add-btn" class="button button-primary">Add to Database</button>
                </p>
                <div id="fwd-add-result" class="fwd-result"></div>
            </div>

            <!-- Tab 2: TMDB-Powered Entry -->
            <div class="fwd-tab-content" id="fwd-tab-tmdb" style="display: none;">
                <?php
                $has_tmdb_key = !empty(get_option('fwd_tmdb_api_key', ''));
                if (!$has_tmdb_key):
                ?>
                    <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #f0b849; margin-bottom: 20px;">
                        <strong>TMDB API Key Required</strong><br>
                        Please configure your TMDB API key in the <a href="#" onclick="jQuery('.fwd-admin-tab-btn[data-tab=ai-parser]').click(); return false;">AI Parser Settings tab</a> to use this feature.
                    </div>
                <?php else: ?>
                    <div class="fwd-examples" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
                        <strong>TMDB-Powered Entry:</strong> Search for movies and actors using The Movie Database autocomplete. Movie posters and cast information will be automatically loaded.
                    </div>

                    <div class="fwd-entry-section">
                        <h3>Movie Information</h3>

                        <div class="fwd-form-row">
                            <label for="fwd-movie-autocomplete">Search Movie</label>
                            <input type="text" id="fwd-movie-autocomplete" class="regular-text" placeholder="Start typing movie title...">
                            <p class="description">Search for a movie by title. Select from the dropdown to auto-fill details.</p>

                            <!-- Hidden fields for movie data -->
                            <input type="hidden" id="fwd-movie-id" value="">
                            <input type="hidden" id="fwd-movie-title" value="">
                            <input type="hidden" id="fwd-movie-year" value="">
                        </div>

                        <div id="fwd-movie-poster-preview"></div>
                    </div>

                    <div class="fwd-entry-section">
                        <h3>Actor & Character Information</h3>

                        <div class="fwd-form-row">
                            <label for="fwd-actor-autocomplete">Search Actor</label>
                            <input type="text" id="fwd-actor-autocomplete" class="regular-text" placeholder="Start typing actor name...">
                            <p class="description">Search for an actor by name. Select from the dropdown to see their filmography.</p>

                            <!-- Hidden fields for actor data -->
                            <input type="hidden" id="fwd-actor-id" value="">
                            <input type="hidden" id="fwd-actor-name" value="">
                        </div>

                        <div class="fwd-form-row">
                            <label for="fwd-character-name">Character Name (optional)</label>
                            <input type="text" id="fwd-character-name" class="regular-text" placeholder="e.g., James Bond">
                            <p class="description">The character the actor plays in the film. May be auto-filled from cast data.</p>
                        </div>
                    </div>

                    <div class="fwd-entry-section">
                        <h3>Watch Information</h3>

                        <div class="fwd-form-row">
                            <label for="fwd-watch-brand">Brand</label>
                            <input type="text" id="fwd-watch-brand" class="regular-text" placeholder="e.g., Rolex" required>
                        </div>

                        <div class="fwd-form-row">
                            <label for="fwd-watch-model">Model / Reference</label>
                            <input type="text" id="fwd-watch-model" class="regular-text" placeholder="e.g., Submariner 5513" required>
                        </div>

                        <div class="fwd-form-row">
                            <label for="fwd-tmdb-narrative">Narrative Role (optional)</label>
                            <textarea id="fwd-tmdb-narrative" class="large-text" rows="3" placeholder="Describe the watch's significance in the film..."></textarea>
                        </div>

                        <div class="fwd-form-row">
                            <label for="fwd-tmdb-image-url">Watch Image URL (optional)</label>
                            <input type="text" id="fwd-tmdb-image-url" class="regular-text" placeholder="https://...">
                            <button type="button" id="fwd-tmdb-upload-image" class="button">Upload Image</button>
                            <p class="description">Direct URL to watch image or upload from your computer.</p>
                        </div>

                        <div class="fwd-form-row">
                            <label for="fwd-tmdb-source-url">Source URL (optional)</label>
                            <input type="text" id="fwd-tmdb-source-url" class="regular-text" placeholder="https://...">
                            <p class="description">URL to your source for verification.</p>
                        </div>
                    </div>

                    <p>
                        <button type="button" id="fwd-tmdb-submit-btn" class="button button-primary button-large">
                            Add Entry
                        </button>
                        <button type="button" id="fwd-tmdb-clear-btn" class="button button-large" style="margin-left: 10px;">
                            Clear Form
                        </button>
                    </p>

                    <div id="fwd-tmdb-result" class="fwd-result"></div>

                    <script>
                    jQuery(document).ready(function($) {
                        // Media uploader for watch image
                        var mediaUploader;
                        $('#fwd-tmdb-upload-image').on('click', function(e) {
                            e.preventDefault();

                            if (mediaUploader) {
                                mediaUploader.open();
                                return;
                            }

                            mediaUploader = wp.media({
                                title: 'Select Watch Image',
                                button: { text: 'Use this image' },
                                multiple: false
                            });

                            mediaUploader.on('select', function() {
                                var attachment = mediaUploader.state().get('selection').first().toJSON();
                                $('#fwd-tmdb-image-url').val(attachment.url);
                            });

                            mediaUploader.open();
                        });

                        // Clear form
                        $('#fwd-tmdb-clear-btn').on('click', function() {
                            $('#fwd-tab-tmdb input[type="text"]').val('');
                            $('#fwd-tab-tmdb input[type="hidden"]').val('');
                            $('#fwd-tab-tmdb textarea').val('');
                            $('#fwd-movie-poster-preview').empty();
                            $('#fwd-movie-cast-list').remove();
                            $('#fwd-actor-movies-list').remove();
                            $('#fwd-tmdb-result').html('').hide();
                            // Remove button highlights
                            $('.fwd-cast-items .button, .fwd-movie-items .button').removeClass('button-primary');
                        });

                        // Submit entry
                        $('#fwd-tmdb-submit-btn').on('click', function() {
                            var $btn = $(this);
                            var $result = $('#fwd-tmdb-result');

                            // Get values
                            var movieTitle = $('#fwd-movie-title').val();
                            var movieYear = $('#fwd-movie-year').val();
                            var actorName = $('#fwd-actor-name').val();
                            var characterName = $('#fwd-character-name').val();
                            var brand = $('#fwd-watch-brand').val().trim();
                            var model = $('#fwd-watch-model').val().trim();
                            var narrative = $('#fwd-tmdb-narrative').val();
                            var imageUrl = $('#fwd-tmdb-image-url').val();
                            var sourceUrl = $('#fwd-tmdb-source-url').val();

                            // Validation
                            if (!movieTitle || !movieYear) {
                                alert('Please select a movie using the autocomplete search.');
                                return;
                            }
                            if (!actorName) {
                                alert('Please select an actor using the autocomplete search.');
                                return;
                            }
                            if (!brand) {
                                alert('Please enter a watch brand.');
                                $('#fwd-watch-brand').focus();
                                return;
                            }
                            if (!model) {
                                alert('Please enter a watch model.');
                                $('#fwd-watch-model').focus();
                                return;
                            }

                            // Construct entry text for the existing API
                            var entryText = actorName + ' wears ' + brand + ' ' + model + ' in ' + movieTitle + ' (' + movieYear + ')';
                            if (characterName) {
                                entryText = 'In ' + movieTitle + ' (' + movieYear + '), ' + actorName + ' as ' + characterName + ' wears ' + brand + ' ' + model;
                            }

                            $btn.prop('disabled', true).text('Adding...');
                            $result.html('').hide();

                            $.post(ajaxurl, {
                                action: 'fwd_add_entry',
                                nonce: fwdAjax.nonce,
                                entry_text: entryText,
                                narrative_role: narrative,
                                image_url: imageUrl,
                                source_url: sourceUrl
                            }, function(response) {
                                $btn.prop('disabled', false).text('Add Entry');

                                if (response.success) {
                                    $result.html('<div class="notice notice-success"><p>✓ Entry added successfully!</p></div>').show();
                                    // Clear form after success
                                    setTimeout(function() {
                                        $('#fwd-tmdb-clear-btn').click();
                                    }, 1500);
                                } else {
                                    var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to add entry';
                                    $result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>').show();
                                }
                            }).fail(function() {
                                $btn.prop('disabled', false).text('Add Entry');
                                $result.html('<div class="notice notice-error"><p>Connection error. Please try again.</p></div>').show();
                            });
                        });
                    });
                    </script>
                <?php endif; ?>
            </div>

            <!-- Tab 3: Quick Entry (Pipe-Delimited) -->
            <div class="fwd-tab-content" id="fwd-tab-quick" style="display: none;">
                <div class="fwd-examples" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
                    <strong>Format:</strong> Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence|SourceURL<br><br>
                    <strong>Example:</strong><br>
                    <code>Ed Harris|Virgil "Bud" Brigman|Seiko|6309 "Turtle"|The Abyss|1989|Bud's trusted dive watch|https://example.com/seiko.jpg|Confirmed via production stills|https://example.com/source</code>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fwd-quick-entry">Pipe-Delimited Entry:</label></th>
                        <td>
                            <textarea
                                id="fwd-quick-entry"
                                class="large-text code"
                                rows="3"
                                placeholder="Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence|SourceURL"
                            ></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button id="fwd-quick-add-btn" class="button button-primary">Add to Database</button>
                </p>
                <div id="fwd-quick-result" class="fwd-result"></div>
            </div>

            <!-- Tab 3: Bulk CSV Import -->
            <div class="fwd-tab-content" id="fwd-tab-bulk" style="display: none;">
                <div class="fwd-examples" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
                    <strong>CSV Format (pipe-delimited, one entry per line):</strong><br>
                    <code>Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence|SourceURL</code><br><br>
                    <strong>Example CSV:</strong><br>
                    <textarea readonly class="large-text code" rows="3" style="font-family: monospace;">Ed Harris|Virgil "Bud" Brigman|Seiko|6309 "Turtle"|The Abyss|1989|Bud's trusted dive watch|https://example.com/seiko.jpg|Confirmed via production stills|https://example.com/source1
Sean Connery|James Bond|Rolex|Submariner 6538|Dr. No|1962|Bond's iconic watch|https://example.com/rolex.jpg|80% - Multiple sources confirm|https://example.com/source2</textarea>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fwd-csv-file">Upload CSV File:</label></th>
                        <td>
                            <input
                                type="file"
                                id="fwd-csv-file"
                                accept=".csv,.txt"
                            >
                            <p class="description">Maximum file size: 5MB. Use pipe (|) delimiters, UTF-8 encoding.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button id="fwd-csv-upload-btn" class="button button-primary">Import CSV</button>
                </p>
                <div id="fwd-csv-result" class="fwd-result"></div>
            </div>
        </div>

        <style>
            .fwd-tab-btn.active {
                background: #0073aa !important;
                color: white !important;
            }
            .fwd-tab-content {
                animation: fadeIn 0.3s;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.fwd-tab-btn').on('click', function() {
                var tab = $(this).data('tab');

                // Update button states
                $('.fwd-tab-btn').removeClass('active').css({
                    'background': '#f0f0f1',
                    'color': '#2c3338'
                });
                $(this).addClass('active').css({
                    'background': '#0073aa',
                    'color': 'white'
                });

                // Show selected tab content
                $('.fwd-tab-content').hide();
                $('#fwd-tab-' + tab).show();
            });
        });
        </script>
        </div>
        <!-- End Tab 1: Add New Entry -->

        <!-- Tab 2: Manage Records -->
        <div class="fwd-admin-tab-content" id="fwd-admin-tab-manage-records">
            <h2>Manage Records</h2>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0;">
                <!-- Search and Filter Controls -->
                <div style="margin-bottom: 20px;">
                    <input type="text" id="fwd-record-search" class="regular-text" placeholder="Search by film, actor, brand, or character..." style="width: 400px;">
                    <button id="fwd-record-search-btn" class="button">Search</button>
                    <button id="fwd-record-clear-btn" class="button">Clear</button>
                    <span id="fwd-record-count" style="margin-left: 20px; font-weight: bold;"></span>
                </div>

                <!-- Records Table -->
                <div id="fwd-records-table-container">
                    <style>
                        .fwd-sortable {
                            cursor: pointer;
                            user-select: none;
                            position: relative;
                        }
                        .fwd-sortable:hover {
                            background: #f0f0f1;
                        }
                        .fwd-sortable::after {
                            content: '⇅';
                            margin-left: 5px;
                            opacity: 0.3;
                        }
                        .fwd-sortable.fwd-sort-asc::after {
                            content: '▲';
                            opacity: 1;
                        }
                        .fwd-sortable.fwd-sort-desc::after {
                            content: '▼';
                            opacity: 1;
                        }
                    </style>
                    <table class="wp-list-table widefat fixed striped" id="fwd-records-table">
                        <thead>
                            <tr>
                                <th class="fwd-sortable" data-column="faw_id" style="width: 50px;">ID</th>
                                <th class="fwd-sortable fwd-sort-asc" data-column="title" style="width: 150px;">Film</th>
                                <th class="fwd-sortable" data-column="year" style="width: 80px;">Year</th>
                                <th class="fwd-sortable" data-column="actor_name" style="width: 120px;">Actor</th>
                                <th class="fwd-sortable" data-column="character_name" style="width: 120px;">Character</th>
                                <th class="fwd-sortable" data-column="brand_name" style="width: 100px;">Brand</th>
                                <th class="fwd-sortable" data-column="model_reference" style="width: 120px;">Model</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fwd-records-tbody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <em>Loading records...</em>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="fwd-records-pagination" style="margin-top: 20px; text-align: center;">
                    <!-- Pagination controls will be inserted here -->
                </div>
            </div>

            <!-- Edit Modal -->
            <div id="fwd-edit-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
                <div style="background-color: #fff; margin: 5% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 4px;">
                    <div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f9f9f9;">
                        <h2 style="margin: 0;">Edit Record <span id="fwd-edit-record-id"></span></h2>
                        <button id="fwd-modal-close" style="float: right; margin-top: -30px; background: none; border: none; font-size: 28px; cursor: pointer;">&times;</button>
                    </div>
                    <div style="padding: 20px;">
                        <form id="fwd-edit-form">
                            <input type="hidden" id="edit-faw-id" name="faw_id">

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="edit-film">Film Title:</label></th>
                                    <td><input type="text" id="edit-film" name="film" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-year">Year:</label></th>
                                    <td><input type="number" id="edit-year" name="year" class="small-text" min="1800" max="2100" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-actor">Actor:</label></th>
                                    <td><input type="text" id="edit-actor" name="actor" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-character">Character:</label></th>
                                    <td><input type="text" id="edit-character" name="character" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-brand">Brand:</label></th>
                                    <td><input type="text" id="edit-brand" name="brand" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-model">Model:</label></th>
                                    <td><input type="text" id="edit-model" name="model" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-narrative">Narrative Role:</label></th>
                                    <td><textarea id="edit-narrative" name="narrative" class="large-text" rows="3"></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-image-url">Image URL:</label></th>
                                    <td><input type="url" id="edit-image-url" name="image_url" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-confidence">Confidence Level:</label></th>
                                    <td><textarea id="edit-confidence" name="confidence_level" class="large-text" rows="2"></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="edit-source-url">Source URL:</label></th>
                                    <td><input type="url" id="edit-source-url" name="source_url" class="regular-text"></td>
                                </tr>
                            </table>
                        </form>
                    </div>
                    <div style="padding: 20px; border-top: 1px solid #ddd; background: #f9f9f9; text-align: right;">
                        <button id="fwd-edit-cancel" class="button">Cancel</button>
                        <button id="fwd-edit-save" class="button button-primary">Save Changes</button>
                    </div>
                </div>
            </div>

            <style>
                #fwd-records-table td {
                    vertical-align: middle;
                }
                #fwd-records-table .fwd-record-actions {
                    white-space: nowrap;
                }
                #fwd-records-table .fwd-record-actions button {
                    margin-right: 5px;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                var currentPage = 1;
                var perPage = 20;
                var searchTerm = '';
                var sortColumn = 'title';
                var sortOrder = 'asc';

                // Load records
                function loadRecords(page, search) {
                    currentPage = page || 1;
                    searchTerm = search || '';

                    $('#fwd-records-tbody').html('<tr><td colspan="8" style="text-align: center; padding: 40px;"><em>Loading...</em></td></tr>');

                    $.post(ajaxurl, {
                        action: 'fwd_get_records',
                        nonce: fwdAjax.nonce,
                        page: currentPage,
                        per_page: perPage,
                        search: searchTerm,
                        sort_column: sortColumn,
                        sort_order: sortOrder
                    }, function(response) {
                        if (response.success) {
                            var data = response.data;
                            var html = '';

                            if (data.records.length === 0) {
                                html = '<tr><td colspan="8" style="text-align: center; padding: 40px;"><em>No records found</em></td></tr>';
                            } else {
                                $.each(data.records, function(i, record) {
                                    html += '<tr>';
                                    html += '<td>' + record.faw_id + '</td>';
                                    html += '<td>' + escapeHtml(record.title) + '</td>';
                                    html += '<td>' + record.year + '</td>';
                                    html += '<td>' + escapeHtml(record.actor) + '</td>';
                                    html += '<td>' + escapeHtml(record.character) + '</td>';
                                    html += '<td>' + escapeHtml(record.brand) + '</td>';
                                    html += '<td>' + escapeHtml(record.model) + '</td>';
                                    html += '<td class="fwd-record-actions">';
                                    html += '<button class="button button-small fwd-edit-record" data-id="' + record.faw_id + '">Edit</button>';
                                    html += '<button class="button button-small fwd-delete-record" data-id="' + record.faw_id + '">Delete</button>';
                                    html += '</td>';
                                    html += '</tr>';
                                });
                            }

                            $('#fwd-records-tbody').html(html);

                            // Update count
                            $('#fwd-record-count').text('Total: ' + data.total + ' records');

                            // Update pagination
                            updatePagination(data.total, data.page, data.per_page);
                        } else {
                            $('#fwd-records-tbody').html('<tr><td colspan="8" style="text-align: center; padding: 40px; color: #dc3232;"><em>Error loading records</em></td></tr>');
                        }
                    });
                }

                // Update pagination controls
                function updatePagination(total, page, perPage) {
                    var totalPages = Math.ceil(total / perPage);
                    var html = '';

                    if (totalPages > 1) {
                        if (page > 1) {
                            html += '<button class="button fwd-page-btn" data-page="' + (page - 1) + '">&laquo; Previous</button> ';
                        }

                        var startPage = Math.max(1, page - 2);
                        var endPage = Math.min(totalPages, page + 2);

                        for (var i = startPage; i <= endPage; i++) {
                            if (i === page) {
                                html += '<button class="button button-primary" disabled>' + i + '</button> ';
                            } else {
                                html += '<button class="button fwd-page-btn" data-page="' + i + '">' + i + '</button> ';
                            }
                        }

                        if (page < totalPages) {
                            html += '<button class="button fwd-page-btn" data-page="' + (page + 1) + '">Next &raquo;</button>';
                        }
                    }

                    $('#fwd-records-pagination').html(html);
                }

                // Escape HTML
                function escapeHtml(text) {
                    if (!text) return '';
                    var map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
                }

                // Search button
                $('#fwd-record-search-btn').on('click', function() {
                    var search = $('#fwd-record-search').val();
                    loadRecords(1, search);
                });

                // Clear button
                $('#fwd-record-clear-btn').on('click', function() {
                    $('#fwd-record-search').val('');
                    loadRecords(1, '');
                });

                // Search on Enter key
                $('#fwd-record-search').on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#fwd-record-search-btn').click();
                    }
                });

                // Pagination clicks
                $(document).on('click', '.fwd-page-btn', function() {
                    var page = $(this).data('page');
                    loadRecords(page, searchTerm);
                });

                // Sortable column clicks
                $(document).on('click', '.fwd-sortable', function() {
                    var column = $(this).data('column');

                    // Toggle sort order if same column, otherwise default to asc
                    if (sortColumn === column) {
                        sortOrder = (sortOrder === 'asc') ? 'desc' : 'asc';
                    } else {
                        sortColumn = column;
                        sortOrder = 'asc';
                    }

                    // Update visual indicators
                    $('.fwd-sortable').removeClass('fwd-sort-asc fwd-sort-desc');
                    $(this).addClass('fwd-sort-' + sortOrder);

                    // Reload with new sort
                    loadRecords(1, searchTerm);
                });

                // Edit button click
                $(document).on('click', '.fwd-edit-record', function() {
                    var fawId = $(this).data('id');

                    // Load record data
                    $.post(ajaxurl, {
                        action: 'fwd_get_record',
                        nonce: fwdAjax.nonce,
                        faw_id: fawId
                    }, function(response) {
                        if (response.success) {
                            var record = response.data;

                            $('#edit-faw-id').val(record.faw_id);
                            $('#fwd-edit-record-id').text('#' + record.faw_id);
                            $('#edit-film').val(record.title);
                            $('#edit-year').val(record.year);
                            $('#edit-actor').val(record.actor);
                            $('#edit-character').val(record.character);
                            $('#edit-brand').val(record.brand);
                            $('#edit-model').val(record.model);
                            $('#edit-narrative').val(record.narrative || '');
                            $('#edit-image-url').val(record.image_url || '');
                            $('#edit-confidence').val(record.confidence_level || '');
                            $('#edit-source-url').val(record.source_url || '');

                            $('#fwd-edit-modal').show();
                        } else {
                            alert('Error loading record: ' + response.data.message);
                        }
                    });
                });

                // Close modal
                $('#fwd-modal-close, #fwd-edit-cancel').on('click', function() {
                    $('#fwd-edit-modal').hide();
                });

                // Save changes
                $('#fwd-edit-save').on('click', function() {
                    var formData = {
                        action: 'fwd_update_record',
                        nonce: fwdAjax.nonce,
                        faw_id: $('#edit-faw-id').val(),
                        film: $('#edit-film').val(),
                        year: $('#edit-year').val(),
                        actor: $('#edit-actor').val(),
                        character: $('#edit-character').val(),
                        brand: $('#edit-brand').val(),
                        model: $('#edit-model').val(),
                        narrative: $('#edit-narrative').val(),
                        image_url: $('#edit-image-url').val(),
                        confidence_level: $('#edit-confidence').val(),
                        source_url: $('#edit-source-url').val()
                    };

                    $.post(ajaxurl, formData, function(response) {
                        if (response.success) {
                            $('#fwd-edit-modal').hide();
                            loadRecords(currentPage, searchTerm);
                            alert('Record updated successfully!');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                });

                // Delete button click
                $(document).on('click', '.fwd-delete-record', function() {
                    var fawId = $(this).data('id');

                    if (!confirm('Are you sure you want to delete this record? This cannot be undone.')) {
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'fwd_delete_record',
                        nonce: fwdAjax.nonce,
                        faw_id: fawId
                    }, function(response) {
                        if (response.success) {
                            loadRecords(currentPage, searchTerm);
                            alert('Record deleted successfully!');
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                });

                // Load initial records when tab is opened
                $('.fwd-admin-tab-btn[data-tab="manage-records"]').on('click', function() {
                    if ($('#fwd-records-tbody tr').length === 1 && $('#fwd-records-tbody td').text().includes('Loading records...')) {
                        loadRecords(1, '');
                    }
                });
            });
            </script>
        </div>
        <!-- End Tab 2: Manage Records -->

        <!-- Tab 3: Shortcode Usage -->
        <div class="fwd-admin-tab-content" id="fwd-admin-tab-shortcodes">
        <h2>Shortcode Usage</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
            <h3>Available Shortcodes:</h3>

            <h4>[film_watch_search]</h4>
            <p>Display a search form for the database.</p>
            <code>[film_watch_search]</code>
            <p><strong>Parameters:</strong></p>
            <ul>
                <li><code>type</code> - Search type: "all", "actor", "brand", or "film" (default: "all")</li>
                <li><code>placeholder</code> - Custom placeholder text</li>
            </ul>
            <p><strong>Example:</strong> <code>[film_watch_search type="actor" placeholder="Search for an actor..."]</code></p>

            <hr>

            <h4>[film_watch_stats]</h4>
            <p>Display database statistics (film count, actor count, brand count, total entries).</p>
            <code>[film_watch_stats]</code>
            <p><strong>Parameters:</strong> None</p>

            <hr>

            <h4>[film_watch_top_brands]</h4>
            <p>Display top watch brands list by film count.</p>
            <code>[film_watch_top_brands]</code>
            <p><strong>Parameters:</strong></p>
            <ul>
                <li><code>limit</code> - Number of brands to show (default: 10)</li>
                <li><code>title</code> - Custom heading text (default: "Top Watch Brands")</li>
            </ul>
            <p><strong>Example:</strong> <code>[film_watch_top_brands limit="15" title="Most Featured Brands"]</code></p>

            <hr>

            <h4>[film_watch_actor name="Tom Cruise"]</h4>
            <p>Display watches for a specific actor.</p>
            <code>[film_watch_actor name="Tom Cruise"]</code>

            <hr>

            <h4>[film_watch_brand name="Rolex"]</h4>
            <p>Display films featuring a specific watch brand.</p>
            <code>[film_watch_brand name="Rolex"]</code>

            <hr>

            <h4>[film_watch_film title="Casino Royale"]</h4>
            <p>Display watches featured in a specific film.</p>
            <code>[film_watch_film title="Casino Royale"]</code>

            <hr>

            <h4>[film_watch_add]</h4>
            <p>Display a form to add new entries (admin only).</p>
            <code>[film_watch_add]</code>
        </div>
        </div>
        <!-- End Tab 3: Shortcode Usage -->

        <!-- Tab 4: Database Maintenance -->
        <div class="fwd-admin-tab-content" id="fwd-admin-tab-maintenance">
        <h2>Database Maintenance</h2>

        <!-- Global Cleanup Tool -->
        <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0;">Clean Escaped Characters</h3>
            <p>Remove backslashes before quotes from ALL database tables (films, actors, brands, watches, characters, narratives).</p>
            <p><strong>This is safe to run.</strong> It will clean entries like <code>Tom\'s</code> → <code>Tom's</code> and <code>\"stealth\"</code> → <code>"stealth"</code>.</p>
            <form method="post" style="margin: 10px 0;">
                <?php wp_nonce_field('fwd_global_cleanup_action', 'fwd_global_cleanup_nonce'); ?>
                <button type="submit" name="fwd_global_cleanup" class="button button-primary">Clean All Escaped Data Now</button>
            </form>
        </div>

        <!-- Fix Actor/Character Split -->
        <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0;">Fix Actor Names with Embedded Characters</h3>
            <p>Find and fix actor names that contain character names like <code>"Rudolph Valentino as Ahmed Ben Hassan"</code></p>
            <p><strong>This will:</strong></p>
            <ul>
                <li>Split actor names into proper actor + character fields</li>
                <li>Merge with existing clean actor records if they exist</li>
                <li>Update all related entries to use correct actor and character IDs</li>
            </ul>
            <form method="post" style="margin: 10px 0;">
                <?php wp_nonce_field('fwd_fix_actor_split_action', 'fwd_fix_actor_split_nonce'); ?>
                <button type="submit" name="fwd_fix_actor_split" class="button button-primary">Fix Actor/Character Split Now</button>
            </form>
        </div>

        <!-- Duplicate Finder -->
        <div style="background: #e7f5ff; padding: 20px; border-left: 4px solid #0073aa; margin: 20px 0;">
            <h3 style="margin-top: 0;">Find and Remove Duplicates</h3>
            <p>Find entries where the same actor playing the same character appears multiple times in the same film.</p>

            <?php
            $duplicate_groups = fwd_find_duplicates();

            if (empty($duplicate_groups)) {
                echo '<p style="color: #46b450;"><strong>✓ No duplicates found!</strong></p>';
            } else {
                echo '<p style="color: #dc3232;"><strong>Found ' . count($duplicate_groups) . ' duplicate groups:</strong></p>';

                foreach ($duplicate_groups as $group_index => $group) {
                    echo '<div style="background: #fff; padding: 15px; margin: 15px 0; border: 1px solid #ccd0d4;">';
                    echo '<h4 style="margin-top: 0;">' . esc_html($group[0]['actor_name']) . ' as ' .
                         esc_html($group[0]['character_name']) .
                         ' in ' . esc_html($group[0]['title']) . ' (' . esc_html($group[0]['year']) . ')</h4>';

                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>ID</th><th>Watch</th><th>Narrative</th><th>Image</th><th>Source</th><th>Action</th>';
                    echo '</tr></thead><tbody>';

                    foreach ($group as $entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($entry['faw_id']) . '</td>';
                        echo '<td><strong>' . esc_html($entry['brand_name']) . ' ' . esc_html($entry['model_reference']) . '</strong></td>';
                        echo '<td>' . (empty($entry['narrative_role']) ? '<em>none</em>' : esc_html(substr($entry['narrative_role'], 0, 100)) . '...') . '</td>';
                        echo '<td>' . (empty($entry['image_url']) ? '<em>none</em>' : '✓ Has image') . '</td>';
                        echo '<td>' . (empty($entry['source_url']) ? '<em>none</em>' : '<a href="' . esc_url($entry['source_url']) . '" target="_blank">link</a>') . '</td>';
                        echo '<td>';
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_duplicate_action', 'fwd_duplicate_nonce');
                        echo '<input type="hidden" name="faw_id" value="' . esc_attr($entry['faw_id']) . '">';
                        echo '<button type="submit" name="fwd_delete_duplicate" class="button button-small" onclick="return confirm(\'Delete entry #' . esc_js($entry['faw_id']) . '?\');">Delete</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <!-- Brand Merge Tool -->
        <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ff9800; margin: 20px 0;">
            <h3 style="margin-top: 0;">Fix: Merge "Porsche" into "Porsche Design"</h3>
            <p>The brand "Porsche" was incorrectly created by the parser. It should be "Porsche Design".</p>
            <p>This tool will reassign all watches from "Porsche" (brand_id: 515) to "Porsche Design" (brand_id: 146) and delete the incorrect brand.</p>

            <?php
            // Check if Porsche brand exists
            $table_brands = $wpdb->prefix . 'fwd_brands';
            $porsche_exists = $wpdb->get_var("SELECT COUNT(*) FROM {$table_brands} WHERE brand_id = 515");

            if ($porsche_exists) {
                $watch_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fwd_watches WHERE brand_id = 515");
                echo '<p style="color: #dc3232;"><strong>⚠ Found incorrect "Porsche" brand with ' . intval($watch_count) . ' watches.</strong></p>';
                ?>
                <form method="post" style="margin: 10px 0;">
                    <?php wp_nonce_field('fwd_brand_merge_action', 'fwd_brand_merge_nonce'); ?>
                    <input type="hidden" name="wrong_brand_id" value="515">
                    <input type="hidden" name="correct_brand_id" value="146">
                    <button type="submit" name="fwd_merge_brands" class="button button-primary" onclick="return confirm('Merge <?php echo intval($watch_count); ?> watches from Porsche to Porsche Design?');">
                        Fix Porsche Brand Now
                    </button>
                </form>
                <?php
            } else {
                echo '<p style="color: #46b450;"><strong>✓ No "Porsche" brand found. All good!</strong></p>';
            }
            ?>
        </div>

        <!-- Duplicate Watch Models Finder -->
        <div style="background: #e7f5ff; padding: 20px; border-left: 4px solid #2196f3; margin: 20px 0;">
            <h3 style="margin-top: 0;">Find and Merge Duplicate Watch Models</h3>
            <p>Find watches with the same brand and similar model names that appear in the same film (e.g., "Chronograph" and "Chronograph 1").</p>

            <?php
            $duplicate_watches = fwd_find_duplicate_watches();

            if (empty($duplicate_watches)) {
                echo '<p style="color: #46b450;"><strong>✓ No duplicate watch models found!</strong></p>';
            } else {
                echo '<p style="color: #dc3232;"><strong>Found ' . count($duplicate_watches) . ' potential duplicate watch pairs:</strong></p>';

                foreach ($duplicate_watches as $pair) {
                    $watch1 = $pair[0];
                    $watch2 = $pair[1];

                    echo '<div style="background: #fff; padding: 15px; margin: 15px 0; border: 1px solid #ccd0d4;">';
                    echo '<h4 style="margin-top: 0;">' . esc_html($watch1->brand_name) . '</h4>';

                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>Watch ID</th><th>Model</th><th>Used in # Films</th><th>Action</th>';
                    echo '</tr></thead><tbody>';

                    // Watch 1
                    echo '<tr>';
                    echo '<td>' . esc_html($watch1->watch_id) . '</td>';
                    echo '<td><strong>' . esc_html($watch1->model_reference) . '</strong></td>';
                    echo '<td>' . esc_html($watch1->usage_count) . '</td>';
                    echo '<td>';
                    if ($watch1->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_watch_merge_action', 'fwd_watch_merge_nonce');
                        echo '<input type="hidden" name="wrong_watch_id" value="' . esc_attr($watch1->watch_id) . '">';
                        echo '<input type="hidden" name="correct_watch_id" value="' . esc_attr($watch2->watch_id) . '">';
                        echo '<button type="submit" name="fwd_merge_watches" class="button button-small" onclick="return confirm(\'Merge ' . esc_js($watch1->usage_count) . ' entries from &quot;' . esc_js($watch1->model_reference) . '&quot; into &quot;' . esc_js($watch2->model_reference) . '&quot;?\');">Merge into #' . esc_html($watch2->watch_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    // Watch 2
                    echo '<tr>';
                    echo '<td>' . esc_html($watch2->watch_id) . '</td>';
                    echo '<td><strong>' . esc_html($watch2->model_reference) . '</strong></td>';
                    echo '<td>' . esc_html($watch2->usage_count) . '</td>';
                    echo '<td>';
                    if ($watch2->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_watch_merge_action', 'fwd_watch_merge_nonce');
                        echo '<input type="hidden" name="wrong_watch_id" value="' . esc_attr($watch2->watch_id) . '">';
                        echo '<input type="hidden" name="correct_watch_id" value="' . esc_attr($watch1->watch_id) . '">';
                        echo '<button type="submit" name="fwd_merge_watches" class="button button-small" onclick="return confirm(\'Merge ' . esc_js($watch2->usage_count) . ' entries from &quot;' . esc_js($watch2->model_reference) . '&quot; into &quot;' . esc_js($watch1->model_reference) . '&quot;?\');">Merge into #' . esc_html($watch1->watch_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    echo '</tbody></table>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <!-- Duplicate Characters Finder -->
        <div style="background: #f3e5f5; padding: 20px; border-left: 4px solid #9c27b0; margin: 20px 0;">
            <h3 style="margin-top: 0;">Find and Merge Duplicate Characters</h3>
            <p>Find characters with similar or identical names that appear in the same film. Review appearances to confirm they're the same character.</p>

            <?php
            $duplicate_characters = fwd_find_duplicate_characters();

            if (empty($duplicate_characters)) {
                echo '<p style="color: #46b450;"><strong>✓ No duplicate characters found!</strong></p>';
            } else {
                echo '<p style="color: #dc3232;"><strong>Found ' . count($duplicate_characters) . ' potential duplicate character pairs:</strong></p>';

                foreach ($duplicate_characters as $item) {
                    $char1 = $item['char1'];
                    $char1_films = $item['char1_films'];
                    $char2 = $item['char2'];
                    $char2_films = $item['char2_films'];

                    echo '<div style="background: #fff; padding: 15px; margin: 15px 0; border: 1px solid #ccd0d4;">';

                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th style="width: 80px;">ID</th><th>Character Name</th><th>Appears In (Actor / Film / Year)</th><th style="width: 150px;">Action</th>';
                    echo '</tr></thead><tbody>';

                    // Character 1
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($char1->character_id) . '</strong></td>';
                    echo '<td><strong>' . esc_html($char1->character_name) . '</strong></td>';
                    echo '<td>';
                    if (!empty($char1_films)) {
                        echo '<ul style="margin: 0; padding-left: 20px;">';
                        foreach ($char1_films as $film) {
                            echo '<li>' . esc_html($film->actor_name) . ' / ' . esc_html($film->title) . ' (' . esc_html($film->year) . ')</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em>No films</em>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($char1->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_character_merge_action', 'fwd_character_merge_nonce');
                        echo '<input type="hidden" name="wrong_character_id" value="' . esc_attr($char1->character_id) . '">';
                        echo '<input type="hidden" name="correct_character_id" value="' . esc_attr($char2->character_id) . '">';
                        echo '<button type="submit" name="fwd_merge_characters" class="button button-small" onclick="return confirm(\'Merge ' . esc_js($char1->usage_count) . ' entries from #' . esc_js($char1->character_id) . ' into #' . esc_js($char2->character_id) . '?\');">Merge into #' . esc_html($char2->character_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    // Character 2
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($char2->character_id) . '</strong></td>';
                    echo '<td><strong>' . esc_html($char2->character_name) . '</strong></td>';
                    echo '<td>';
                    if (!empty($char2_films)) {
                        echo '<ul style="margin: 0; padding-left: 20px;">';
                        foreach ($char2_films as $film) {
                            echo '<li>' . esc_html($film->actor_name) . ' / ' . esc_html($film->title) . ' (' . esc_html($film->year) . ')</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em>No films</em>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($char2->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_character_merge_action', 'fwd_character_merge_nonce');
                        echo '<input type="hidden" name="wrong_character_id" value="' . esc_attr($char2->character_id) . '">';
                        echo '<input type="hidden" name="correct_character_id" value="' . esc_attr($char1->character_id) . '">';
                        echo '<button type="submit" name="fwd_merge_characters" class="button button-small" onclick="return confirm(\'Merge ' . esc_js($char2->usage_count) . ' entries from #' . esc_js($char2->character_id) . ' into #' . esc_js($char1->character_id) . '?\');">Merge into #' . esc_html($char1->character_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    echo '</tbody></table>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        </div>
        <!-- End Tab 4: Database Maintenance -->

        <!-- Tab Switching JavaScript -->
        <script>
        jQuery(document).ready(function($) {
            $('.fwd-admin-tab-btn').on('click', function() {
                var tab = $(this).data('tab');

                // Update tab buttons
                $('.fwd-admin-tab-btn').removeClass('active');
                $(this).addClass('active');

                // Update tab content
                $('.fwd-admin-tab-content').removeClass('active');
                $('#fwd-admin-tab-' + tab).addClass('active');
            });
        });
        </script>

    </div>
    <?php
}
