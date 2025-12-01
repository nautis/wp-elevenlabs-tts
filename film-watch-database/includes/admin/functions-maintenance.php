<?php
/**
 * Database Maintenance Functions
 * Functions for cleaning, merging, and maintaining the Film Watch Database
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

    return "Cleaned {$total_cleaned} records across all tables.";
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

    return "Fixed {$fixed_count} actor records with embedded character names.";
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
    $table_search_index = $wpdb->prefix . 'fwd_search_index';

    // Get brand names for confirmation message
    $wrong_brand = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$table_brands} WHERE brand_id = %d", $wrong_brand_id));
    $correct_brand = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$table_brands} WHERE brand_id = %d", $correct_brand_id));

    // Count watches that will be affected
    $watch_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_watches} WHERE brand_id = %d", $wrong_brand_id));

    if ($watch_count > 0) {
        // Update all watches to use correct brand
        $wpdb->update($table_watches, array('brand_id' => $correct_brand_id), array('brand_id' => $wrong_brand_id));

        // Update search index - change brand name for all affected entries
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_search_index} SET brand_name = %s WHERE brand_name = %s",
            $correct_brand, $wrong_brand
        ));
    }

    // Delete the wrong brand
    $wpdb->delete($table_brands, array('brand_id' => $wrong_brand_id), array('%d'));

    // Clear search caches
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fwd_%' OR option_name LIKE '_transient_timeout_fwd_%'");

    return "Merged {$watch_count} watches from '{$wrong_brand}' into '{$correct_brand}' and deleted incorrect brand.";
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
    $table_search_index = $wpdb->prefix . 'fwd_search_index';

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

    // Get affected faw_ids before update
    $affected_faw_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT faw_id FROM {$table_film_actor_watch} WHERE watch_id = %d",
        $wrong_watch_id
    ));

    // Count entries that will be affected
    $entry_count = count($affected_faw_ids);

    if ($entry_count > 0) {
        // Update all entries to use correct watch
        $wpdb->update($table_film_actor_watch, array('watch_id' => $correct_watch_id), array('watch_id' => $wrong_watch_id));

        // Update search index for affected entries
        $faw_ids_placeholder = implode(',', array_map('intval', $affected_faw_ids));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_search_index} SET model_reference = %s WHERE faw_id IN ({$faw_ids_placeholder})",
            $correct_watch->model_reference
        ));
    }

    // Delete the wrong watch
    $wpdb->delete($table_watches, array('watch_id' => $wrong_watch_id), array('%d'));

    // Clear search caches
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fwd_%' OR option_name LIKE '_transient_timeout_fwd_%'");

    return "Merged {$entry_count} entries from '{$wrong_watch->brand_name} {$wrong_watch->model_reference}' into '{$correct_watch->brand_name} {$correct_watch->model_reference}' and deleted duplicate watch.";
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

    // Collect all unique watch IDs first (performance optimization)
    $watch_ids = array();
    foreach ($potential_dupes as $pair) {
        $watch_ids[] = $pair->watch1_id;
        $watch_ids[] = $pair->watch2_id;
    }
    $watch_ids = array_unique($watch_ids);

    // Fetch all watch details in a single query with usage counts
    $watch_details = array();
    if (!empty($watch_ids)) {
        $placeholders = implode(',', array_fill(0, count($watch_ids), '%d'));
        $watches = $wpdb->get_results($wpdb->prepare("
            SELECT w.watch_id, w.model_reference, b.brand_name,
                   COUNT(faw.watch_id) as usage_count
            FROM {$table_watches} w
            JOIN {$table_brands} b ON w.brand_id = b.brand_id
            LEFT JOIN {$table_film_actor_watch} faw ON w.watch_id = faw.watch_id
            WHERE w.watch_id IN ($placeholders)
            GROUP BY w.watch_id, w.model_reference, b.brand_name
        ", ...$watch_ids));

        // Index by watch_id for fast lookup
        foreach ($watches as $watch) {
            $watch_details[$watch->watch_id] = $watch;
        }
    }

    foreach ($potential_dupes as $pair) {
        $model1 = strtolower($pair->watch1_model);
        $model2 = strtolower($pair->watch2_model);

        // Check if one model name contains the other (e.g., "Chronograph" and "Chronograph 1")
        if (strpos($model1, $model2) !== false || strpos($model2, $model1) !== false) {

            // Get watch details from cached array
            $watch1 = isset($watch_details[$pair->watch1_id]) ? $watch_details[$pair->watch1_id] : null;
            $watch2 = isset($watch_details[$pair->watch2_id]) ? $watch_details[$pair->watch2_id] : null;

            if ($watch1 && $watch2) {
                // Create unique key to avoid duplicate pairs
                $key = min($pair->watch1_id, $pair->watch2_id) . '_' . max($pair->watch1_id, $pair->watch2_id);

                if (!isset($duplicates[$key])) {
                    $duplicates[$key] = array($watch1, $watch2);
                }
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
    $table_search_index = $wpdb->prefix . 'fwd_search_index';

    // Get character names for confirmation message
    $wrong_char = $wpdb->get_var($wpdb->prepare("SELECT character_name FROM {$table_characters} WHERE character_id = %d", $wrong_character_id));
    $correct_char = $wpdb->get_var($wpdb->prepare("SELECT character_name FROM {$table_characters} WHERE character_id = %d", $correct_character_id));

    // Get affected faw_ids before update
    $affected_faw_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT faw_id FROM {$table_film_actor_watch} WHERE character_id = %d",
        $wrong_character_id
    ));

    // Count entries that will be affected
    $entry_count = count($affected_faw_ids);

    if ($entry_count > 0) {
        // Update all entries to use correct character
        $wpdb->update($table_film_actor_watch, array('character_id' => $correct_character_id), array('character_id' => $wrong_character_id));

        // Update search index for affected entries
        $faw_ids_placeholder = implode(',', array_map('intval', $affected_faw_ids));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_search_index} SET character_name = %s WHERE faw_id IN ({$faw_ids_placeholder})",
            $correct_char
        ));
    }

    // Delete the wrong character
    $wpdb->delete($table_characters, array('character_id' => $wrong_character_id), array('%d'));

    // Clear search caches
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fwd_%' OR option_name LIKE '_transient_timeout_fwd_%'");

    return "Merged {$entry_count} entries from '{$wrong_char}' into '{$correct_char}' and deleted duplicate character.";
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

    // Collect all unique character IDs first (performance optimization)
    $character_ids = array();
    foreach ($potential_dupes as $pair) {
        $character_ids[] = $pair->char1_id;
        $character_ids[] = $pair->char2_id;
    }
    $character_ids = array_unique($character_ids);

    // Fetch all character details in a single query
    $character_details = array();
    if (!empty($character_ids)) {
        $placeholders = implode(',', array_fill(0, count($character_ids), '%d'));
        $characters = $wpdb->get_results($wpdb->prepare("
            SELECT c.character_id, c.character_name,
                   COUNT(faw.character_id) as usage_count
            FROM {$table_characters} c
            LEFT JOIN {$table_film_actor_watch} faw ON c.character_id = faw.character_id
            WHERE c.character_id IN ($placeholders)
            GROUP BY c.character_id, c.character_name
        ", ...$character_ids));

        foreach ($characters as $char) {
            $character_details[$char->character_id] = $char;
        }
    }

    // Fetch all film appearances in a single query
    $film_appearances = array();
    if (!empty($character_ids)) {
        $placeholders = implode(',', array_fill(0, count($character_ids), '%d'));
        $all_films = $wpdb->get_results($wpdb->prepare("
            SELECT faw.character_id, f.title, f.year, a.actor_name
            FROM {$table_film_actor_watch} faw
            JOIN {$wpdb->prefix}fwd_films f ON faw.film_id = f.film_id
            JOIN {$wpdb->prefix}fwd_actors a ON faw.actor_id = a.actor_id
            WHERE faw.character_id IN ($placeholders)
            ORDER BY f.year, f.title
        ", ...$character_ids));

        // Group by character_id
        foreach ($all_films as $film) {
            if (!isset($film_appearances[$film->character_id])) {
                $film_appearances[$film->character_id] = array();
            }
            $film_appearances[$film->character_id][] = $film;
        }
    }

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

            // Get character details and films from cached arrays
            $char1 = isset($character_details[$pair->char1_id]) ? $character_details[$pair->char1_id] : null;
            $char2 = isset($character_details[$pair->char2_id]) ? $character_details[$pair->char2_id] : null;
            $char1_films = isset($film_appearances[$pair->char1_id]) ? $film_appearances[$pair->char1_id] : array();
            $char2_films = isset($film_appearances[$pair->char2_id]) ? $film_appearances[$pair->char2_id] : array();

            if ($char1 && $char2) {
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
    }

    return array_values($duplicates);
}
