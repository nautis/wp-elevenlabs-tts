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
