<?php
/**
 * Populate TMDB IDs for all movies
 *
 * This script searches TMDB for each movie by title + year
 * and stores the TMDB ID in post meta.
 *
 * Usage: php populate-tmdb-ids.php [--dry-run] [--limit=N]
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Parse command line arguments
$options = getopt('', ['dry-run', 'limit::']);
$dry_run = isset($options['dry-run']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 0;

echo "====================================\n";
echo "TMDB ID Population Script\n";
echo "====================================\n";
if ($dry_run) {
    echo "MODE: DRY RUN (no changes will be made)\n";
}
if ($limit > 0) {
    echo "LIMIT: Processing only $limit movies\n";
}
echo "\n";

// Get all movies without TMDB IDs
$args = array(
    'post_type' => 'fww_movie',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_fww_tmdb_id',
            'compare' => 'NOT EXISTS'
        ),
        array(
            'key' => '_fww_tmdb_id',
            'value' => '',
            'compare' => '='
        )
    ),
    'orderby' => 'title',
    'order' => 'ASC'
);

$movies = get_posts($args);
$total = count($movies);

echo "Found $total movies without TMDB IDs\n";
echo "====================================\n\n";

if ($total === 0) {
    echo "✓ All movies already have TMDB IDs!\n";
    exit(0);
}

if ($limit > 0) {
    $movies = array_slice($movies, 0, $limit);
    echo "Processing first $limit movies...\n\n";
}

$stats = array(
    'processed' => 0,
    'found' => 0,
    'not_found' => 0,
    'errors' => 0,
    'skipped' => 0
);

$start_time = time();
$api_calls = 0;
$last_api_reset = time();

foreach ($movies as $movie) {
    $stats['processed']++;

    $title = get_the_title($movie->ID);
    $year = get_post_meta($movie->ID, '_fww_year', true);

    echo "[$stats[processed]/$total] $title";
    if ($year) {
        echo " ($year)";
    }
    echo "\n";

    if (empty($title)) {
        echo "  ⚠ Skipping: No title\n\n";
        $stats['skipped']++;
        continue;
    }

    // Decode HTML entities for API search
    $search_title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Rate limiting: TMDB allows 40 requests per 10 seconds
    $current_time = time();
    if ($current_time - $last_api_reset >= 10) {
        // Reset counter every 10 seconds
        $api_calls = 0;
        $last_api_reset = $current_time;
    }

    if ($api_calls >= 35) {
        // Stay safely under limit
        $wait_time = 10 - ($current_time - $last_api_reset);
        if ($wait_time > 0) {
            echo "  ⏳ Rate limit: waiting {$wait_time}s...\n";
            sleep($wait_time);
            $api_calls = 0;
            $last_api_reset = time();
        }
    }

    // Search TMDB (use decoded title)
    $api_calls++;
    $results = FWW_TMDB_API::search_movie($search_title, $year);

    if (is_wp_error($results)) {
        echo "  ✗ API Error: " . $results->get_error_message() . "\n\n";
        $stats['errors']++;
        continue;
    }

    if (empty($results) || empty($results['results'])) {
        echo "  ✗ Not found on TMDB\n\n";
        $stats['not_found']++;
        continue;
    }

    // Get the first result (usually the best match)
    $match = $results['results'][0];
    $tmdb_id = $match['id'];
    $matched_title = $match['title'];
    $matched_year = !empty($match['release_date']) ? substr($match['release_date'], 0, 4) : 'Unknown';

    echo "  ✓ Found: TMDB ID $tmdb_id\n";
    echo "    Title: $matched_title ($matched_year)\n";

    // Check if year matches (if we have one)
    if ($year && $matched_year !== 'Unknown' && $matched_year != $year) {
        $year_diff = abs($matched_year - $year);
        if ($year_diff > 1) {
            echo "    ⚠ Year mismatch: Expected $year, got $matched_year (diff: $year_diff years)\n";
        }
    }

    if (!$dry_run) {
        // Store TMDB ID
        update_post_meta($movie->ID, '_fww_tmdb_id', $tmdb_id);
        echo "    → Saved to database\n";
    } else {
        echo "    → Would save (dry run)\n";
    }

    echo "\n";
    $stats['found']++;

    // Brief pause between requests to be respectful
    usleep(100000); // 0.1 seconds
}

$elapsed = time() - $start_time;
$minutes = floor($elapsed / 60);
$seconds = $elapsed % 60;

echo "====================================\n";
echo "SUMMARY\n";
echo "====================================\n";
echo "Total processed: $stats[processed]\n";
echo "Found on TMDB:   $stats[found]\n";
echo "Not found:       $stats[not_found]\n";
echo "Errors:          $stats[errors]\n";
echo "Skipped:         $stats[skipped]\n";
echo "\n";
echo "Time elapsed:    {$minutes}m {$seconds}s\n";
echo "====================================\n";

if ($dry_run) {
    echo "\n⚠ DRY RUN: No changes were made to the database.\n";
    echo "Run without --dry-run to actually save the TMDB IDs.\n";
} else {
    $success_rate = $stats['processed'] > 0 ? round(($stats['found'] / $stats['processed']) * 100, 1) : 0;
    echo "\n✓ Successfully populated TMDB IDs for $stats[found] movies ($success_rate% success rate)\n";

    if ($stats['not_found'] > 0) {
        echo "\n⚠ $stats[not_found] movies were not found on TMDB.\n";
        echo "These may need manual research or have different titles.\n";
    }
}
