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

/**
 * AJAX handler for adding a watch sighting
 */
function fww_ajax_add_sighting() {
    check_ajax_referer('fww_ajax_nonce', 'nonce');

    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Get and validate data
    $movie_id = isset($_POST['movie_id']) ? intval($_POST['movie_id']) : 0;
    $actor_id = isset($_POST['actor_id']) ? intval($_POST['actor_id']) : 0;
    $watch_id = isset($_POST['watch_id']) ? intval($_POST['watch_id']) : 0;
    $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;

    if (empty($movie_id) || empty($actor_id) || empty($watch_id) || empty($brand_id)) {
        wp_send_json_error('Missing required fields');
        return;
    }

    // Prepare sighting data
    $sighting_data = array(
        'movie_id' => $movie_id,
        'actor_id' => $actor_id,
        'character_name' => isset($_POST['character_name']) ? sanitize_text_field($_POST['character_name']) : '',
        'watch_id' => $watch_id,
        'brand_id' => $brand_id,
        'scene_description' => isset($_POST['scene_description']) ? sanitize_textarea_field($_POST['scene_description']) : '',
        'verification_level' => isset($_POST['verification_level']) ? sanitize_text_field($_POST['verification_level']) : 'unverified'
    );

    // Add the sighting
    $sighting_id = FWW_Sightings::add_sighting($sighting_data);

    if ($sighting_id === false) {
        wp_send_json_error('Failed to add sighting');
        return;
    }

    wp_send_json_success(array(
        'sighting_id' => $sighting_id,
        'message' => 'Watch sighting added successfully'
    ));
}
add_action('wp_ajax_fww_add_sighting', 'fww_ajax_add_sighting');

/**
 * AJAX handler for deleting a watch sighting
 */
function fww_ajax_delete_sighting() {
    check_ajax_referer('fww_ajax_nonce', 'nonce');

    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $sighting_id = isset($_POST['sighting_id']) ? intval($_POST['sighting_id']) : 0;

    if (empty($sighting_id)) {
        wp_send_json_error('Invalid sighting ID');
        return;
    }

    $result = FWW_Sightings::delete_sighting($sighting_id);

    if (!$result) {
        wp_send_json_error('Failed to delete sighting');
        return;
    }

    wp_send_json_success(array(
        'message' => 'Watch sighting deleted successfully'
    ));
}
add_action('wp_ajax_fww_delete_sighting', 'fww_ajax_delete_sighting');
