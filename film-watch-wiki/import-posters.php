<?php
/**
 * Import TMDB Poster Images to WordPress Media Library
 *
 * This script downloads poster images from TMDB and imports them
 * into the WordPress media library, setting them as featured images.
 *
 * Usage: php import-posters.php [--dry-run]
 */

// Load WordPress
define('WP_USE_THEMES', false);
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Cannot find WordPress. Please run from plugin directory.\n");
}
require_once($wp_load_path);

// Check if this is a dry run
$dry_run = in_array('--dry-run', $argv);

if ($dry_run) {
    echo "🔍 DRY RUN MODE - No changes will be made\n\n";
}

// Get all movies
$movies = get_posts(array(
    'post_type' => 'fww_movie',
    'posts_per_page' => -1,
    'post_status' => 'publish'
));

echo "Found " . count($movies) . " movies to process\n\n";

$stats = array(
    'total' => count($movies),
    'already_has_thumbnail' => 0,
    'no_tmdb_poster' => 0,
    'downloaded' => 0,
    'failed' => 0,
    'skipped_dry_run' => 0
);

foreach ($movies as $movie) {
    $title = get_the_title($movie->ID);
    echo "\nProcessing: {$title} (ID: {$movie->ID})\n";

    // Check if already has featured image
    if (has_post_thumbnail($movie->ID)) {
        echo "  ✓ Already has featured image\n";
        $stats['already_has_thumbnail']++;
        continue;
    }

    // Get TMDB data
    $tmdb_id = get_post_meta($movie->ID, '_fww_tmdb_id', true);
    if (empty($tmdb_id)) {
        echo "  ⚠ No TMDB ID found\n";
        $stats['no_tmdb_poster']++;
        continue;
    }

    // Get movie details from TMDB
    $movie_data = FWW_TMDB_API::get_movie($tmdb_id);
    if (is_wp_error($movie_data) || empty($movie_data['poster_path'])) {
        echo "  ⚠ No poster available from TMDB\n";
        $stats['no_tmdb_poster']++;
        continue;
    }

    $poster_path = $movie_data['poster_path'];
    $poster_url = 'https://image.tmdb.org/t/p/w500' . $poster_path;

    echo "  📥 Downloading: {$poster_url}\n";

    if ($dry_run) {
        echo "  [DRY RUN] Would download and set as featured image\n";
        $stats['skipped_dry_run']++;
        continue;
    }

    // Download the image
    $tmp_file = download_url($poster_url);

    if (is_wp_error($tmp_file)) {
        echo "  ❌ Failed to download: " . $tmp_file->get_error_message() . "\n";
        $stats['failed']++;
        continue;
    }

    // Prepare file array for media upload
    $file_array = array(
        'name' => basename($poster_path),
        'tmp_name' => $tmp_file
    );

    // Import into media library
    $attachment_id = media_handle_sideload($file_array, $movie->ID, $title . ' - Poster');

    // Clean up temp file
    @unlink($tmp_file);

    if (is_wp_error($attachment_id)) {
        echo "  ❌ Failed to import: " . $attachment_id->get_error_message() . "\n";
        $stats['failed']++;
        continue;
    }

    // Set as featured image
    set_post_thumbnail($movie->ID, $attachment_id);

    echo "  ✓ Successfully imported and set as featured image (Attachment ID: {$attachment_id})\n";
    $stats['downloaded']++;

    // Be nice to the server - small delay
    usleep(100000); // 0.1 seconds
}

// Summary
echo "\n\n" . str_repeat("=", 60) . "\n";
echo "IMPORT SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "Total movies:              {$stats['total']}\n";
echo "Already had thumbnail:     {$stats['already_has_thumbnail']}\n";
echo "No TMDB poster available:  {$stats['no_tmdb_poster']}\n";
echo "Successfully downloaded:   {$stats['downloaded']}\n";
echo "Failed:                    {$stats['failed']}\n";
if ($dry_run) {
    echo "Skipped (dry run):         {$stats['skipped_dry_run']}\n";
}
echo str_repeat("=", 60) . "\n";

if ($dry_run) {
    echo "\n💡 Run without --dry-run to actually import images\n";
}
