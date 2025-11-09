<?php
/**
 * AJAX Handlers
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler for movie search autocomplete
 */
function fww_ajax_search_movies() {
    check_ajax_referer('fww_ajax_nonce', 'nonce');

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query) || strlen($query) < 2) {
        wp_send_json_success(array());
        return;
    }

    $results = FWW_TMDB_API::search_movies($query);

    if (is_wp_error($results)) {
        wp_send_json_error(array('message' => $results->get_error_message()));
        return;
    }

    // Limit to first 10 results for autocomplete
    $results = array_slice($results, 0, 10);

    wp_send_json_success($results);
}
add_action('wp_ajax_fww_search_movies', 'fww_ajax_search_movies');

/**
 * AJAX handler for getting movie details with cast
 */
function fww_ajax_get_movie_details() {
    check_ajax_referer('fww_ajax_nonce', 'nonce');

    $movie_id = isset($_POST['movie_id']) ? intval($_POST['movie_id']) : 0;

    if (empty($movie_id)) {
        wp_send_json_error(array('message' => 'Invalid movie ID'));
        return;
    }

    $movie = FWW_TMDB_API::get_movie($movie_id);

    if (is_wp_error($movie)) {
        wp_send_json_error(array('message' => $movie->get_error_message()));
        return;
    }

    // Get cast
    $cast = FWW_TMDB_API::get_movie_credits($movie_id, 10);

    if (!is_wp_error($cast)) {
        $movie['cast'] = $cast;
    }

    wp_send_json_success($movie);
}
add_action('wp_ajax_fww_get_movie_details', 'fww_ajax_get_movie_details');
