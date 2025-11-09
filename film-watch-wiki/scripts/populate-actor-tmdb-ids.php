#!/usr/bin/env php
<?php
/**
 * Script to populate TMDB Person IDs for all actors
 *
 * Usage: php populate-actor-tmdb-ids.php [--dry-run] [--limit=N]
 *
 * Options:
 *   --dry-run    Show what would be updated without making changes
 *   --limit=N    Only process N actors (default: all)
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('/var/www/wordpress/wp-load.php');

// Parse command line arguments
$dry_run = in_array('--dry-run', $argv);
$limit = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = intval(substr($arg, 8));
    }
}

// Get TMDB API key
$api_key = get_option('fww_tmdb_api_key', '');
if (empty($api_key)) {
    echo "Error: TMDB API key not configured\n";
    exit(1);
}

// Get all actors without TMDB person IDs
$args = array(
    'post_type' => 'fww_actor',
    'posts_per_page' => $limit ? $limit : -1,
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_fww_tmdb_person_id',
            'compare' => 'NOT EXISTS'
        ),
        array(
            'key' => '_fww_tmdb_person_id',
            'value' => '',
            'compare' => '='
        )
    ),
    'orderby' => 'title',
    'order' => 'ASC'
);

$actors = get_posts($args);

echo "Found " . count($actors) . " actors without TMDB person IDs\n";
if ($dry_run) {
    echo "DRY RUN MODE - No changes will be made\n";
}
echo "\n";

$updated = 0;
$skipped = 0;
$failed = 0;

foreach ($actors as $actor) {
    $actor_name = $actor->post_title;
    echo "Processing: $actor_name (ID: {$actor->ID})... ";

    // Search TMDB for this person
    $search_url = 'https://api.themoviedb.org/3/search/person?query=' . urlencode($actor_name) . '&language=en-US';

    $response = wp_remote_get($search_url, array(
        'timeout' => 10,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        )
    ));

    if (is_wp_error($response)) {
        echo "FAILED (API error)\n";
        $failed++;
        continue;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['results'])) {
        echo "SKIPPED (no results)\n";
        $skipped++;
        continue;
    }

    // Get the first result (best match)
    $person = $data['results'][0];
    $person_id = $person['id'];
    $person_name = $person['name'];

    // Check if name is similar enough (basic check)
    $similarity = 0;
    similar_text(strtolower($actor_name), strtolower($person_name), $similarity);

    if ($similarity < 80) {
        echo "SKIPPED (low similarity: {$similarity}% - found '$person_name')\n";
        $skipped++;
        continue;
    }

    if ($dry_run) {
        echo "WOULD UPDATE to TMDB ID: $person_id ($person_name)\n";
        $updated++;
    } else {
        // Update the post meta
        update_post_meta($actor->ID, '_fww_tmdb_person_id', $person_id);
        echo "UPDATED to TMDB ID: $person_id ($person_name)\n";
        $updated++;
    }

    // Rate limiting - be nice to TMDB API
    usleep(250000); // 250ms delay = 4 requests per second
}

echo "\n";
echo "Summary:\n";
echo "  Updated: $updated\n";
echo "  Skipped: $skipped\n";
echo "  Failed: $failed\n";

if ($dry_run) {
    echo "\nRun without --dry-run to apply changes\n";
}
