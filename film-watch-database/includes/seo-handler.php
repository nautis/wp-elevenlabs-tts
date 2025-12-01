<?php
/**
 * SEO Handler for Film Watch Database
 * Handles dynamic SEO meta tags for search result pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add SEO meta tags for search result pages
 * Runs on template_redirect to ensure WordPress is fully loaded
 */
function fwd_add_seo_meta_tags() {
    // Only process if we have search parameters
    if (!isset($_GET['type']) || !isset($_GET['q'])) {
        return;
    }

    $search_type = sanitize_text_field($_GET['type']);
    $search_query = sanitize_text_field($_GET['q']);

    $valid_types = array('actor', 'brand', 'film', 'all');
    if (!in_array($search_type, $valid_types, true)) {
        return;
    }

    // Generate SEO content
    $seo_title = '';
    $seo_description = '';

    switch ($search_type) {
        case 'actor':
            $seo_title = $search_query . ' Watches in Movies | Film Watch Database';
            $seo_description = 'Discover the watches worn by ' . $search_query . ' in films. Complete database of timepieces seen on screen.';
            break;
        case 'brand':
            $seo_title = $search_query . ' Watches in Film | Film Watch Database';
            $seo_description = 'See all films featuring ' . $search_query . ' watches. Comprehensive database of watch appearances in movies.';
            break;
        case 'film':
            $seo_title = 'Watches in ' . $search_query . ' | Film Watch Database';
            $seo_description = 'Discover all the watches featured in ' . $search_query . '. Complete watch spotting database.';
            break;
        case 'all':
            $seo_title = $search_query . ' | Film Watch Database';
            $seo_description = 'Search results for ' . $search_query . ' in the Film Watch Database. Find watches worn in movies by actors and brands.';
            break;
    }

    // Store in global for title filter
    global $fwd_seo_title, $fwd_seo_description, $fwd_seo_canonical;
    $fwd_seo_title = $seo_title;
    $fwd_seo_description = $seo_description;

    // Build canonical URL
    $base_url = trailingslashit(home_url()) . 'watches-in-film/';
    $fwd_seo_canonical = add_query_arg(array('type' => $search_type, 'q' => $search_query), $base_url);

    // Debug log
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("FWD SEO: Setting meta tags for $search_type: $search_query");
    }

    // Add title filter
    add_filter('pre_get_document_title', 'fwd_override_document_title', 999);

    // Add meta tags to wp_head
    add_action('wp_head', 'fwd_output_meta_tags', 1);
}
add_action('template_redirect', 'fwd_add_seo_meta_tags');

/**
 * Override document title for search pages
 */
function fwd_override_document_title($title) {
    global $fwd_seo_title;
    return $fwd_seo_title ? $fwd_seo_title : $title;
}

/**
 * Output SEO meta tags
 */
function fwd_output_meta_tags() {
    global $fwd_seo_title, $fwd_seo_description, $fwd_seo_canonical;

    if (!$fwd_seo_title) {
        return;
    }

    echo "\n" . '<!-- Film Watch Database SEO Meta Tags -->' . "\n";
    echo '<meta name="description" content="' . esc_attr($fwd_seo_description) . '" />' . "\n";
    echo '<link rel="canonical" href="' . esc_url($fwd_seo_canonical) . '" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($fwd_seo_title) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($fwd_seo_description) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($fwd_seo_canonical) . '" />' . "\n";
    echo '<meta property="og:type" content="website" />' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($fwd_seo_title) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($fwd_seo_description) . '" />' . "\n";
}
