<?php
/**
 * Movie Functions
 * Functions for retrieving and displaying movie data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get movie data for a post
 *
 * @param int $post_id The movie post ID
 * @return array|false Movie data or false on failure
 */
function fww_get_movie_data($post_id) {
    $tmdb_id = get_post_meta($post_id, '_fww_tmdb_id', true);
    $year = get_post_meta($post_id, '_fww_year', true);
    $film_id = get_post_meta($post_id, '_fww_film_id', true); // Link to old database

    // Try to get cached TMDB data
    $tmdb_data = get_post_meta($post_id, '_fww_tmdb_data', true);

    // If no cached data or it's old, fetch from TMDB
    if (empty($tmdb_data) && !empty($tmdb_id)) {
        $tmdb_data = FWW_TMDB_API::get_movie($tmdb_id);

        if ($tmdb_data) {
            // Cache the data
            update_post_meta($post_id, '_fww_tmdb_data', $tmdb_data);
        }
    }

    return array(
        'tmdb_id' => $tmdb_id,
        'year' => $year,
        'film_id' => $film_id,
        'tmdb_data' => $tmdb_data
    );
}

/**
 * Get watch sightings for a movie
 * Queries the existing wp_fwd_film_actor_watch table
 *
 * @param int $film_id The film ID from the old database
 * @return array Array of watch sightings
 */
function fww_get_movie_watch_sightings($film_id) {
    if (empty($film_id)) {
        return array();
    }

    global $wpdb;

    $query = $wpdb->prepare("
        SELECT
            faw.*,
            f.title as film_title,
            f.year as film_year,
            a.actor_name,
            c.character_name,
            w.model_reference,
            w.verification_level,
            b.brand_name
        FROM {$wpdb->prefix}fwd_film_actor_watch faw
        LEFT JOIN {$wpdb->prefix}fwd_films f ON faw.film_id = f.film_id
        LEFT JOIN {$wpdb->prefix}fwd_actors a ON faw.actor_id = a.actor_id
        LEFT JOIN {$wpdb->prefix}fwd_characters c ON faw.character_id = c.character_id
        LEFT JOIN {$wpdb->prefix}fwd_watches w ON faw.watch_id = w.watch_id
        LEFT JOIN {$wpdb->prefix}fwd_brands b ON w.brand_id = b.brand_id
        WHERE faw.film_id = %d
        ORDER BY a.actor_name
    ", $film_id);

    return $wpdb->get_results($query);
}

/**
 * Display movie poster from TMDB
 *
 * @param array $tmdb_data TMDB movie data
 * @param string $size Poster size (w92, w154, w185, w342, w500, w780, original)
 * @return string HTML for poster image
 */
function fww_get_movie_poster($tmdb_data, $size = 'w342') {
    if (empty($tmdb_data['poster_path'])) {
        return '';
    }

    $poster_url = FWW_TMDB_API::get_image_url($tmdb_data['poster_path'], $size);
    $title = esc_attr($tmdb_data['title'] ?? 'Movie Poster');

    return sprintf(
        '<img src="%s" alt="%s" class="fww-movie-poster" loading="lazy">',
        esc_url($poster_url),
        $title
    );
}

/**
 * Display movie backdrop from TMDB
 *
 * @param array $tmdb_data TMDB movie data
 * @param string $size Backdrop size (w300, w780, w1280, original)
 * @return string HTML for backdrop image
 */
function fww_get_movie_backdrop($tmdb_data, $size = 'w1280') {
    if (empty($tmdb_data['backdrop_path'])) {
        return '';
    }

    $backdrop_url = FWW_TMDB_API::get_image_url($tmdb_data['backdrop_path'], $size);
    $title = esc_attr($tmdb_data['title'] ?? 'Movie Backdrop');

    return sprintf(
        '<img src="%s" alt="%s" class="fww-movie-backdrop" loading="lazy">',
        esc_url($backdrop_url),
        $title
    );
}

/**
 * Get formatted runtime string
 *
 * @param int $minutes Runtime in minutes
 * @return string Formatted runtime (e.g., "2h 23m")
 */
function fww_format_runtime($minutes) {
    if (empty($minutes)) {
        return '';
    }

    $hours = floor($minutes / 60);
    $mins = $minutes % 60;

    if ($hours > 0) {
        return sprintf('%dh %dm', $hours, $mins);
    }

    return sprintf('%dm', $mins);
}

/**
 * Get formatted budget or revenue
 *
 * @param int $amount Amount in USD
 * @return string Formatted amount (e.g., "$150M")
 */
function fww_format_money($amount) {
    if (empty($amount)) {
        return 'N/A';
    }

    if ($amount >= 1000000000) {
        return '$' . number_format($amount / 1000000000, 1) . 'B';
    } elseif ($amount >= 1000000) {
        return '$' . number_format($amount / 1000000, 1) . 'M';
    } elseif ($amount >= 1000) {
        return '$' . number_format($amount / 1000, 1) . 'K';
    }

    return '$' . number_format($amount);
}

/**
 * Get movie cast from TMDB data
 *
 * @param int $tmdb_id TMDB movie ID
 * @param int $limit Number of cast members to return
 * @return array Array of cast members
 */
function fww_get_movie_cast($tmdb_id, $limit = 10) {
    if (empty($tmdb_id)) {
        return array();
    }

    $cast = FWW_TMDB_API::get_movie_credits($tmdb_id, $limit);
    return $cast;
}

/**
 * Get director(s) from TMDB data
 *
 * @param array $tmdb_data TMDB movie data with credits
 * @return string Comma-separated list of directors
 */
function fww_get_directors($tmdb_data) {
    if (empty($tmdb_data['credits']['crew'])) {
        return '';
    }

    $directors = array();
    foreach ($tmdb_data['credits']['crew'] as $crew_member) {
        if ($crew_member['job'] === 'Director') {
            $directors[] = $crew_member['name'];
        }
    }

    return implode(', ', $directors);
}

/**
 * Download TMDB poster and set as featured image
 *
 * @param int $post_id Post ID
 * @param string $poster_path TMDB poster path
 * @param string $movie_title Movie title for alt text
 * @return int|false Attachment ID or false on failure
 */
function fww_download_and_set_poster($post_id, $poster_path, $movie_title) {
    if (empty($poster_path)) {
        return false;
    }

    // Check if post already has a featured image
    if (has_post_thumbnail($post_id)) {
        return get_post_thumbnail_id($post_id);
    }

    // Get the poster URL (use w500 for good quality)
    $poster_url = FWW_TMDB_API::get_image_url($poster_path, 'w500');

    if (empty($poster_url)) {
        return false;
    }

    // Download the image
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download to temp file
    $tmp = download_url($poster_url);

    if (is_wp_error($tmp)) {
        return false;
    }

    // Set up the file array
    $file_array = array(
        'name' => sanitize_file_name($movie_title) . '-poster.jpg',
        'tmp_name' => $tmp
    );

    // Upload to media library
    $attachment_id = media_handle_sideload($file_array, $post_id, $movie_title . ' Poster');

    // Clean up temp file
    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    if (is_wp_error($attachment_id)) {
        return false;
    }

    // Set as featured image
    set_post_thumbnail($post_id, $attachment_id);

    return $attachment_id;
}
