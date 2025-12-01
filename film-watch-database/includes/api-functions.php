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
 * Get database statistics (cached for 5 minutes)
 */
function fwd_get_stats() {
    return fwd_cache()->remember('stats', function() {
        return fwd_db()->get_stats();
    }, 5 * MINUTE_IN_SECONDS);
}

/**
 * Unified search across all categories (cached for 15 minutes)
 * This is the only search method we need - it searches actors, brands, and films in one query
 */
function fwd_query_all($search_term) {
    $cache_key = 'all_' . md5(strtolower(trim($search_term)));
    return fwd_cache()->remember($cache_key, function() use ($search_term) {
        return fwd_db()->query_all($search_term);
    });
}

/**
 * Get image caption from WordPress media library
 * Returns the caption for an image URL if it exists in the media library
 */
function fwd_get_image_caption($image_url) {
    if (empty($image_url)) {
        return '';
    }

    // Try WordPress's built-in function first
    $attachment_id = attachment_url_to_postid($image_url);

    // If that fails, try searching by filename (handles format conversions like jpeg->avif)
    if (!$attachment_id) {
        global $wpdb;

        // Extract filename without extension and size suffix
        $filename = basename($image_url);
        $filename = preg_replace('/\.(jpeg|jpg|png|gif|webp|avif)$/i', '', $filename);
        $filename = preg_replace('/-\d+x\d+$/', '', $filename); // Remove size suffix like -150x150
        $filename = str_replace('-scaled', '', $filename); // Remove -scaled

        // Search for attachment by title or guid pattern
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND (post_title LIKE %s OR guid LIKE %s)
             ORDER BY ID DESC
             LIMIT 1",
            '%' . $wpdb->esc_like($filename) . '%',
            '%' . $wpdb->esc_like($filename) . '%'
        ));
    }

    if (!$attachment_id) {
        return '';
    }

    // Get the caption from post_excerpt
    $caption = get_post_field('post_excerpt', $attachment_id);

    return $caption ? trim($caption) : '';
}

/**
 * Add new entry to database
 */
function fwd_add_entry($entry_text, $narrative = '', $image_url = '', $gallery_ids = '', $confidence_level = '', $force_overwrite = false) {
    try {
        $db = fwd_db();
        $parsed = $db->parse_entry($entry_text);

        if ($narrative) {
            $parsed['narrative'] = $narrative;
        }
        if ($image_url) {
            $parsed['image_url'] = $image_url;
        }
        if ($gallery_ids) {
            $parsed['gallery_ids'] = $gallery_ids;
        }
        if ($confidence_level) {
            $parsed['confidence_level'] = $confidence_level;
        }

        $db->insert_entry($parsed, $force_overwrite);

        // Clear relevant caches
        delete_transient('fwd_stats');
        delete_transient('fwd_actor_' . md5(strtolower(trim($parsed['actor']))));
        delete_transient('fwd_brand_' . md5(strtolower(trim($parsed['brand']))));
        delete_transient('fwd_film_' . md5(strtolower(trim($parsed['title']))));

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
            return array(
                'success' => false,
                'is_duplicate' => true,
                'error' => "Duplicate entry found",
                'existing' => $existing_data,
                'new' => $parsed
            );
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
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $query_type = sanitize_text_field($_POST['query_type']);
    $search_term = sanitize_text_field($_POST['search_term']);

    if (empty($search_term)) {
        wp_send_json_error(array('message' => 'Search term is required'));
    }

    // Always use unified 'all' search (ignore query_type parameter for backwards compat)
    $result = fwd_query_all($search_term);

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
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $entry_text = sanitize_text_field(wp_unslash($_POST['entry_text']));
    $narrative = sanitize_textarea_field(wp_unslash($_POST['narrative']));
    $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
    $gallery_ids = isset($_POST['gallery_ids']) ? sanitize_text_field(wp_unslash($_POST['gallery_ids'])) : '';
    $confidence_level = isset($_POST['confidence_level']) ? sanitize_textarea_field(wp_unslash($_POST['confidence_level'])) : '';
    $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true';

    if (empty($entry_text)) {
        wp_send_json_error(array('message' => 'Entry text is required'));
    }

    $result = fwd_add_entry($entry_text, $narrative, $image_url, $gallery_ids, $confidence_level, $force_overwrite);

    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_fwd_add_entry', 'fwd_ajax_add_entry');

/**
 * Parse pipe-delimited entry
 * Format: Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence
 */
function fwd_parse_pipe_entry($pipe_entry) {
    $parts = explode('|', $pipe_entry);

    if (count($parts) < 6) {
        throw new Exception('Invalid format. Expected: Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Confidence');
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
        'confidence_level' => isset($parts[8]) ? trim($parts[8]) : ''
    );
}

/**
 * AJAX handler for quick entry (pipe-delimited)
 */
function fwd_ajax_add_quick_entry() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $quick_entry = sanitize_textarea_field(wp_unslash($_POST['quick_entry']));
    $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true';

    if (empty($quick_entry)) {
        wp_send_json_error(array('message' => 'Quick entry is required'));
    }

    try {
        $parsed = fwd_parse_pipe_entry($quick_entry);
        $db = fwd_db();
        $db->insert_entry($parsed, $force_overwrite);

        // Clear relevant caches
        delete_transient('fwd_stats');
        delete_transient('fwd_actor_' . md5(strtolower(trim($parsed['actor']))));
        delete_transient('fwd_brand_' . md5(strtolower(trim($parsed['brand']))));
        delete_transient('fwd_film_' . md5(strtolower(trim($parsed['title']))));

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
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Use wp_unslash to remove any magic quotes or unwanted slashes
    $csv_content = sanitize_textarea_field(wp_unslash($_POST['csv_content']));

    if (empty($csv_content)) {
        wp_send_json_error(array('message' => 'CSV content is required'));
    }

    try {
        global $wpdb;

        $lines = explode("\n", $csv_content);
        $lines = array_filter(array_map('trim', $lines)); // Remove empty lines

        $db = fwd_db();
        $success_count = 0;
        $error_count = 0;
        $errors = array();

        // Start transaction for atomic CSV import
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($lines as $line_num => $line) {
                try {
                    $parsed = fwd_parse_pipe_entry($line);
                    $db->insert_entry($parsed);
                    $success_count++;
                } catch (Exception $e) {
                    // Individual entry errors don't rollback the whole import
                    $error_count++;
                    $errors[] = "Line " . ($line_num + 1) . ": " . $e->getMessage();
                }
            }

            // Commit the transaction if we got here
            $wpdb->query('COMMIT');

            // Clear all caches after bulk import
            fwd_cache()->forget('stats');
            fwd_cache()->forget('brands_list');
            // Note: We don't clear individual actor/brand/film caches here as there could be hundreds
            // They will expire naturally after 15 minutes

        } catch (Exception $e) {
            // Rollback on catastrophic failure
            $wpdb->query('ROLLBACK');
            throw $e;
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
 * Create or update a RegGallery post for a watch entry
 *
 * @param array $gallery_ids Array of WordPress attachment IDs
 * @param array $entry_data Watch entry data for naming the gallery
 * @param int|null $existing_regallery_id Existing RegGallery post ID to update (optional)
 * @return int|false RegGallery post ID on success, false on failure
 */
function fwd_create_regallery_post($gallery_ids, $entry_data, $existing_regallery_id = null) {
    // Check if RegGallery plugin is active
    if (!class_exists('REACG')) {
        error_log('FWD: RegGallery plugin not active, cannot create gallery');
        return false;
    }

    if (empty($gallery_ids) || !is_array($gallery_ids)) {
        error_log('FWD: No gallery IDs provided');
        return false;
    }

    // Create gallery title from entry data
    $title = sprintf(
        '%s %s - %s (%s)',
        $entry_data['brand'] ?? 'Watch',
        $entry_data['model'] ?? '',
        $entry_data['title'] ?? 'Film',
        $entry_data['year'] ?? ''
    );

    $post_data = array(
        'post_type' => 'reacg',
        'post_title' => $title,
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    );

    // If updating existing gallery
    if ($existing_regallery_id) {
        $post_data['ID'] = $existing_regallery_id;
        $post_id = wp_update_post($post_data);
    } else {
        $post_id = wp_insert_post($post_data);
    }

    if (is_wp_error($post_id) || !$post_id) {
        error_log('FWD: Failed to create/update RegGallery post: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error'));
        return false;
    }

    // Store image IDs in RegGallery's expected format (JSON array)
    update_post_meta($post_id, 'images_ids', json_encode($gallery_ids));

    // Set default RegGallery options
    update_post_meta($post_id, 'reacg_options', json_encode(array(
        'type' => 'thumbnails',
        'columns' => 3,
        'lightbox' => 'true',
        'show_caption' => 'true',
        'caption_source' => 'caption'
    )));

    error_log("FWD: Created/updated RegGallery post ID {$post_id} for: {$title}");
    return $post_id;
}

/**
 * Delete a RegGallery post
 *
 * @param int $regallery_id RegGallery post ID to delete
 * @return bool True on success, false on failure
 */
function fwd_delete_regallery_post($regallery_id) {
    if (!$regallery_id) {
        return false;
    }

    $result = wp_delete_post($regallery_id, true); // true = force delete, skip trash
    return $result !== false;
}
