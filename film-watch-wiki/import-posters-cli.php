<?php
/**
 * Import TMDB Poster Images via WP-CLI
 *
 * Usage: wp eval-file import-posters-cli.php [dry_run]
 */

// Check if this is a dry run
$dry_run = !empty($args) && $args[0] === 'dry_run';

if ($dry_run) {
    WP_CLI::line("🔍 DRY RUN MODE - No changes will be made\n");
}

// Get all movies
$movies = get_posts(array(
    'post_type' => 'fww_movie',
    'posts_per_page' => -1,
    'post_status' => 'publish'
));

WP_CLI::line("Found " . count($movies) . " movies to process\n");

$stats = array(
    'total' => count($movies),
    'already_has_thumbnail' => 0,
    'no_tmdb_poster' => 0,
    'downloaded' => 0,
    'failed' => 0,
    'skipped_dry_run' => 0
);

$progress = \WP_CLI\Utils\make_progress_bar('Importing posters', count($movies));

foreach ($movies as $movie) {
    $title = get_the_title($movie->ID);

    // Check if already has featured image
    if (has_post_thumbnail($movie->ID)) {
        $stats['already_has_thumbnail']++;
        $progress->tick();
        continue;
    }

    // Get TMDB data
    $tmdb_id = get_post_meta($movie->ID, '_fww_tmdb_id', true);
    if (empty($tmdb_id)) {
        WP_CLI::debug("No TMDB ID for: {$title}");
        $stats['no_tmdb_poster']++;
        $progress->tick();
        continue;
    }

    // Get movie details from TMDB
    $movie_data = FWW_TMDB_API::get_movie($tmdb_id);
    if (is_wp_error($movie_data) || empty($movie_data['poster_path'])) {
        WP_CLI::debug("No poster for: {$title}");
        $stats['no_tmdb_poster']++;
        $progress->tick();
        continue;
    }

    $poster_path = $movie_data['poster_path'];
    $poster_url = 'https://image.tmdb.org/t/p/w500' . $poster_path;

    if ($dry_run) {
        WP_CLI::debug("[DRY RUN] Would download: {$title}");
        $stats['skipped_dry_run']++;
        $progress->tick();
        continue;
    }

    // Download the image
    $tmp_file = download_url($poster_url);

    if (is_wp_error($tmp_file)) {
        WP_CLI::warning("Failed to download {$title}: " . $tmp_file->get_error_message());
        $stats['failed']++;
        $progress->tick();
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
        WP_CLI::warning("Failed to import {$title}: " . $attachment_id->get_error_message());
        $stats['failed']++;
        $progress->tick();
        continue;
    }

    // Set as featured image
    set_post_thumbnail($movie->ID, $attachment_id);

    $stats['downloaded']++;
    $progress->tick();

    // Small delay to be nice
    usleep(100000); // 0.1 seconds
}

$progress->finish();

// Summary
WP_CLI::line("\n" . str_repeat("=", 60));
WP_CLI::success("IMPORT SUMMARY");
WP_CLI::line(str_repeat("=", 60));
WP_CLI::line("Total movies:              " . $stats['total']);
WP_CLI::line("Already had thumbnail:     " . $stats['already_has_thumbnail']);
WP_CLI::line("No TMDB poster available:  " . $stats['no_tmdb_poster']);
WP_CLI::line("Successfully downloaded:   " . $stats['downloaded']);
WP_CLI::line("Failed:                    " . $stats['failed']);
if ($dry_run) {
    WP_CLI::line("Skipped (dry run):         " . $stats['skipped_dry_run']);
}
WP_CLI::line(str_repeat("=", 60));

if ($dry_run) {
    WP_CLI::line("\n💡 Run without 'dry_run' to actually import images");
}
