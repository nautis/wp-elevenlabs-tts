<?php
/**
 * API Functions - Native PHP Database Implementation
 * No external Flask backend required - all logic runs in WordPress
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate AJAX request with nonce and capability check
 * Consolidates duplicate security validation across all AJAX handlers
 *
 * @param bool $require_admin Whether to require admin capabilities
 * @return bool True if valid, exits with JSON error if not
 */
function fwd_validate_ajax_request($require_admin = false) {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if ($require_admin && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        exit;
    }

    return true;
}

/**
 * Validate entry data with comprehensive checks
 *
 * @param array $data Entry data to validate
 * @return array|WP_Error Array of errors or WP_Error on validation failure
 */
function fwd_validate_entry_data($data) {
    $errors = array();

    // Validate actor name (required, 1-255 chars)
    if (empty($data['actor']) || strlen($data['actor']) > 255) {
        $errors[] = 'Actor name must be between 1 and 255 characters';
    }

    // Validate character name (required, 1-255 chars)
    if (empty($data['character']) || strlen($data['character']) > 255) {
        $errors[] = 'Character name must be between 1 and 255 characters';
    }

    // Validate brand name (required, 1-100 chars)
    if (empty($data['brand']) || strlen($data['brand']) > 100) {
        $errors[] = 'Brand name must be between 1 and 100 characters';
    }

    // Validate model reference (required, 1-255 chars)
    if (empty($data['model']) || strlen($data['model']) > 255) {
        $errors[] = 'Model reference must be between 1 and 255 characters';
    }

    // Validate title (required, 1-255 chars)
    if (empty($data['title']) || strlen($data['title']) > 255) {
        $errors[] = 'Film title must be between 1 and 255 characters';
    }

    // Validate year (1888 to next year)
    $current_year = (int) date('Y');
    $min_year = 1888; // First motion picture
    $max_year = $current_year + 1; // Allow next year for announced films

    if (empty($data['year']) || !is_numeric($data['year'])) {
        $errors[] = 'Film year is required and must be a number';
    } elseif ($data['year'] < $min_year || $data['year'] > $max_year) {
        $errors[] = sprintf('Film year must be between %d and %d', $min_year, $max_year);
    }

    // Validate narrative (optional, max 65535 chars for TEXT field)
    if (isset($data['narrative']) && strlen($data['narrative']) > 65535) {
        $errors[] = 'Narrative text is too long (maximum 65,535 characters)';
    }

    // Validate image_url (optional, max 2083 chars, must be valid URL)
    if (!empty($data['image_url'])) {
        if (strlen($data['image_url']) > 2083) {
            $errors[] = 'Image URL is too long (maximum 2,083 characters)';
        } elseif (!filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Image URL is not a valid URL';
        } elseif (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $data['image_url'])) {
            $errors[] = 'Image URL must end with a valid image extension (.jpg, .jpeg, .png, .gif, .webp)';
        }
    }

    // Validate source_url (optional, max 2083 chars, must be valid URL)
    if (!empty($data['source_url'])) {
        if (strlen($data['source_url']) > 2083) {
            $errors[] = 'Source URL is too long (maximum 2,083 characters)';
        } elseif (!filter_var($data['source_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Source URL is not a valid URL';
        }
    }

    // Validate confidence_level (optional, max 65535 chars)
    if (isset($data['confidence_level']) && strlen($data['confidence_level']) > 65535) {
        $errors[] = 'Confidence level text is too long (maximum 65,535 characters)';
    }

    // Return errors array or WP_Error
    if (!empty($errors)) {
        return new WP_Error('validation_failed', 'Entry validation failed', $errors);
    }

    return array();
}

/**
 * Get database statistics
 */
function fwd_get_stats() {
    return fwd_db()->get_stats();
}

/**
 * Query by actor name
 */
function fwd_query_actor($actor_name) {
    return fwd_db()->query_actor($actor_name);
}

/**
 * Query by brand name
 */
function fwd_query_brand($brand_name) {
    return fwd_db()->query_brand($brand_name);
}

/**
 * Query by film title
 */
function fwd_query_film($film_title) {
    return fwd_db()->query_film($film_title);
}

/**
 * Get image caption from WordPress media library
 * Returns the caption for an image URL if it exists in the media library
 */
function fwd_get_image_caption($image_url) {
    if (empty($image_url)) {
        return '';
    }

    global $wpdb;

    // Get attachment ID from URL
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
        $image_url
    ));

    if (!$attachment_id) {
        return '';
    }

    // Get the caption from post_excerpt
    $caption = $wpdb->get_var($wpdb->prepare(
        "SELECT post_excerpt FROM {$wpdb->posts} WHERE ID = %d",
        $attachment_id
    ));

    return $caption ? trim($caption) : '';
}

/**
 * Get image captions for multiple URLs at once (batch query)
 * Solves N+1 query problem when displaying multiple results
 *
 * @param array $image_urls Array of image URLs
 * @return array Associative array mapping URL => caption
 */
function fwd_get_image_captions_batch($image_urls) {
    if (empty($image_urls)) {
        return array();
    }

    global $wpdb;

    // Remove empty URLs and get unique values
    $image_urls = array_filter(array_unique($image_urls));

    if (empty($image_urls)) {
        return array();
    }

    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($image_urls), '%s'));

    // Single query to get all captions at once
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT guid, post_excerpt FROM {$wpdb->posts}
         WHERE guid IN ($placeholders) AND post_type = 'attachment'",
        ...$image_urls
    ), ARRAY_A);

    // Map URLs to captions
    $captions = array();
    foreach ($results as $row) {
        $captions[$row['guid']] = trim($row['post_excerpt']);
    }

    return $captions;
}

/**
 * Add new entry to database
 */
function fwd_add_entry($entry_text, $narrative = '', $image_url = '', $confidence_level = '', $source_url = '', $force_overwrite = false) {
    $parsed = null;

    try {
        // Use smart parser with AI fallback
        $parsed = fwd_smart_parse($entry_text);

        // Get database instance
        $db = fwd_db();

        if ($narrative) {
            $parsed['narrative'] = $narrative;
        }
        if ($image_url) {
            $parsed['image_url'] = $image_url;
        }
        if ($confidence_level) {
            $parsed['confidence_level'] = $confidence_level;
        }
        if ($source_url) {
            $parsed['source_url'] = $source_url;
        }

        // Validate entry data before inserting
        $validation_result = fwd_validate_entry_data($parsed);
        if (is_wp_error($validation_result)) {
            $errors = $validation_result->get_error_data();
            throw new Exception('Validation failed: ' . implode('; ', $errors));
        }

        $db->insert_entry($parsed, $force_overwrite);

        $message = $force_overwrite
            ? "Successfully updated: {$parsed['actor']} wearing {$parsed['brand']} {$parsed['model']} in {$parsed['title']} ({$parsed['year']})"
            : "Successfully added: {$parsed['actor']} wearing {$parsed['brand']} {$parsed['model']} in {$parsed['title']} ({$parsed['year']})";

        return array(
            'success' => true,
            'message' => $message,
            'data' => $parsed
        );
    } catch (Exception $e) {
        $error_message = $e->getMessage();

        // Check if this is a duplicate error
        if (strpos($error_message, 'DUPLICATE:') === 0) {
            $existing_data = json_decode(substr($error_message, 10), true);
            $result = array(
                'success' => false,
                'is_duplicate' => true,
                'error' => "Duplicate entry found",
                'existing' => $existing_data
            );

            // Only include 'new' if parsing succeeded
            if ($parsed !== null) {
                $result['new'] = $parsed;
            }

            return $result;
        }

        return array(
            'success' => false,
            'error' => $error_message
        );
    }
}

/**
 * AJAX handler for search requests
 */
function fwd_ajax_search() {
    fwd_validate_ajax_request(false);

    // Use empty() instead of isset() for better validation
    if (empty($_POST['query_type']) || empty($_POST['search_term'])) {
        wp_send_json_error(array('message' => 'Missing required parameters'));
    }

    $query_type = sanitize_text_field($_POST['query_type']);
    $search_term = sanitize_text_field($_POST['search_term']);

    $result = null;
    switch ($query_type) {
        case 'actor':
            $result = fwd_query_actor($search_term);
            break;
        case 'brand':
            $result = fwd_query_brand($search_term);
            break;
        case 'film':
            $result = fwd_query_film($search_term);
            break;
        default:
            wp_send_json_error(array('message' => 'Invalid query type'));
    }

    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_fwd_search', 'fwd_ajax_search');
add_action('wp_ajax_nopriv_fwd_search', 'fwd_ajax_search');

/**
 * AJAX handler for adding entries (admin only)
 */
function fwd_ajax_add_entry() {
    fwd_validate_ajax_request(true);

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $entry_text = sanitize_text_field(wp_unslash($_POST['entry_text']));
    $narrative = sanitize_textarea_field(wp_unslash($_POST['narrative']));
    $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
    $confidence_level = isset($_POST['confidence_level']) ? sanitize_textarea_field(wp_unslash($_POST['confidence_level'])) : '';
    $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
    $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true';

    if (empty($entry_text)) {
        wp_send_json_error(array('message' => 'Entry text is required'));
    }

    $result = fwd_add_entry($entry_text, $narrative, $image_url, $confidence_level, $source_url, $force_overwrite);

    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_fwd_add_entry', 'fwd_ajax_add_entry');

/**
 * Parse pipe-delimited entry
 * Format: Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence|SourceURL
 */
function fwd_parse_pipe_entry($pipe_entry) {
    $parts = explode('|', $pipe_entry);

    if (count($parts) < 6) {
        throw new Exception('Invalid format. Expected: Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence|SourceURL');
    }

    return array(
        'actor' => trim($parts[0]),
        'character' => trim($parts[1]),
        'brand' => trim($parts[2]),
        'model' => trim($parts[3]),
        'title' => trim($parts[4]),
        'year' => intval(trim($parts[5])),
        'narrative' => isset($parts[6]) ? trim($parts[6]) : '',
        'image_url' => isset($parts[7]) ? trim($parts[7]) : '',
        'confidence_level' => isset($parts[8]) ? trim($parts[8]) : '',
        'source_url' => isset($parts[9]) ? trim($parts[9]) : ''
    );
}

/**
 * AJAX handler for quick entry (pipe-delimited)
 */
function fwd_ajax_add_quick_entry() {
    fwd_validate_ajax_request(true);

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $quick_entry = sanitize_textarea_field(wp_unslash($_POST['quick_entry']));
    $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true';

    if (empty($quick_entry)) {
        wp_send_json_error(array('message' => 'Quick entry is required'));
    }

    try {
        $parsed = fwd_parse_pipe_entry($quick_entry);

        // Validate entry data before inserting
        $validation_result = fwd_validate_entry_data($parsed);
        if (is_wp_error($validation_result)) {
            $errors = $validation_result->get_error_data();
            throw new Exception('Validation failed: ' . implode('; ', $errors));
        }

        $db = fwd_db();
        $db->insert_entry($parsed, $force_overwrite);

        $message = $force_overwrite
            ? "Successfully updated: {$parsed['actor']} wearing {$parsed['brand']} {$parsed['model']} in {$parsed['title']} ({$parsed['year']})"
            : "Successfully added: {$parsed['actor']} wearing {$parsed['brand']} {$parsed['model']} in {$parsed['title']} ({$parsed['year']})";

        wp_send_json_success(array('message' => $message));
    } catch (Exception $e) {
        $error_message = $e->getMessage();

        // Check if this is a duplicate error
        if (strpos($error_message, 'DUPLICATE:') === 0) {
            $existing_data = json_decode(substr($error_message, 10), true);
            $parsed = fwd_parse_pipe_entry($quick_entry);
            wp_send_json_error(array(
                'is_duplicate' => true,
                'error' => 'Duplicate entry found',
                'existing' => $existing_data,
                'new' => $parsed
            ));
        } else {
            wp_send_json_error(array('error' => $error_message));
        }
    }
}
add_action('wp_ajax_fwd_add_quick_entry', 'fwd_ajax_add_quick_entry');

/**
 * AJAX handler for CSV bulk import
 */
function fwd_ajax_import_csv() {
    fwd_validate_ajax_request(true);

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $csv_content = sanitize_textarea_field(wp_unslash($_POST['csv_content']));

    if (empty($csv_content)) {
        wp_send_json_error(array('message' => 'CSV content is required'));
    }

    try {
        $lines = explode("\n", $csv_content);
        $lines = array_filter(array_map('trim', $lines)); // Remove empty lines

        $db = fwd_db();
        $success_count = 0;
        $error_count = 0;
        $errors = array();

        foreach ($lines as $line_num => $line) {
            try {
                $parsed = fwd_parse_pipe_entry($line);
                $db->insert_entry($parsed);
                $success_count++;
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Line " . ($line_num + 1) . ": " . $e->getMessage();
            }
        }

        $message = "Import complete: {$success_count} successful, {$error_count} errors.";
        if (count($errors) > 0 && count($errors) <= 10) {
            $message .= "\n\nErrors:\n" . implode("\n", $errors);
        } elseif (count($errors) > 10) {
            $message .= "\n\nShowing first 10 errors:\n" . implode("\n", array_slice($errors, 0, 10));
        }

        wp_send_json_success(array('message' => $message));
    } catch (Exception $e) {
        wp_send_json_error(array('error' => $e->getMessage()));
    }
}
add_action('wp_ajax_fwd_import_csv', 'fwd_ajax_import_csv');

/**
 * AJAX handler for testing TMDB connection
 */
function fwd_ajax_test_tmdb() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query)) {
        wp_send_json_error(array('message' => 'Please enter a movie title'));
        return;
    }

    $results = FWD_TMDB_API::search_movies($query);

    if (is_wp_error($results)) {
        wp_send_json_error(array('message' => $results->get_error_message()));
        return;
    }

    // Limit to first 5 results for testing
    $results = array_slice($results, 0, 5);

    // Format for display
    $formatted = array();
    foreach ($results as $movie) {
        $formatted[] = array(
            'title' => $movie['title'],
            'year' => $movie['year'],
            'poster' => $movie['poster_url']
        );
    }

    wp_send_json_success($formatted);
}
add_action('wp_ajax_fwd_test_tmdb', 'fwd_ajax_test_tmdb');

/**
 * AJAX handler for movie search autocomplete
 */
function fwd_ajax_search_movies() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query) || strlen($query) < 2) {
        wp_send_json_success(array());
        return;
    }

    $results = FWD_TMDB_API::search_movies($query);

    if (is_wp_error($results)) {
        wp_send_json_error(array('message' => $results->get_error_message()));
        return;
    }

    // Limit to first 10 results for autocomplete
    $results = array_slice($results, 0, 10);

    wp_send_json_success($results);
}
add_action('wp_ajax_fwd_search_movies', 'fwd_ajax_search_movies');
add_action('wp_ajax_nopriv_fwd_search_movies', 'fwd_ajax_search_movies');

/**
 * AJAX handler for actor search autocomplete
 */
function fwd_ajax_search_actors() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query) || strlen($query) < 2) {
        wp_send_json_success(array());
        return;
    }

    $results = FWD_TMDB_API::search_people($query);

    if (is_wp_error($results)) {
        wp_send_json_error(array('message' => $results->get_error_message()));
        return;
    }

    // Limit to first 10 results for autocomplete
    $results = array_slice($results, 0, 10);

    wp_send_json_success($results);
}
add_action('wp_ajax_fwd_search_actors', 'fwd_ajax_search_actors');
add_action('wp_ajax_nopriv_fwd_search_actors', 'fwd_ajax_search_actors');

/**
 * AJAX handler for getting movie details with cast
 */
function fwd_ajax_get_movie_details() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $movie_id = isset($_POST['movie_id']) ? intval($_POST['movie_id']) : 0;

    if (empty($movie_id)) {
        wp_send_json_error(array('message' => 'Invalid movie ID'));
        return;
    }

    $movie = FWD_TMDB_API::get_movie($movie_id);

    if (is_wp_error($movie)) {
        wp_send_json_error(array('message' => $movie->get_error_message()));
        return;
    }

    // Get cast
    $cast = FWD_TMDB_API::get_movie_credits($movie_id, 10);

    if (!is_wp_error($cast)) {
        $movie['cast'] = $cast;
    }

    wp_send_json_success($movie);
}
add_action('wp_ajax_fwd_get_movie_details', 'fwd_ajax_get_movie_details');
add_action('wp_ajax_nopriv_fwd_get_movie_details', 'fwd_ajax_get_movie_details');

/**
 * AJAX handler for getting actor's movies
 */
function fwd_ajax_get_actor_movies() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $actor_id = isset($_POST['actor_id']) ? intval($_POST['actor_id']) : 0;

    if (empty($actor_id)) {
        wp_send_json_error(array('message' => 'Invalid actor ID'));
        return;
    }

    $movies = FWD_TMDB_API::get_person_movie_credits($actor_id);

    if (is_wp_error($movies)) {
        wp_send_json_error(array('message' => $movies->get_error_message()));
        return;
    }

    // Limit to first 20 movies
    $movies = array_slice($movies, 0, 20);

    wp_send_json_success($movies);
}
add_action('wp_ajax_fwd_get_actor_movies', 'fwd_ajax_get_actor_movies');
add_action('wp_ajax_nopriv_fwd_get_actor_movies', 'fwd_ajax_get_actor_movies');
