<?php
/**
 * Shortcodes handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Shortcodes {

    public static function init() {
        add_shortcode('ws_main', [__CLASS__, 'render_main']);
        add_shortcode('ws_sighting', [__CLASS__, 'render_sighting']);
        add_shortcode('ws_user_profile', [__CLASS__, 'render_user_profile']);
    }

    /**
     * Main shortcode - handles all routing
     */
    public static function render_main($atts) {
        $atts = shortcode_atts([
            'recent_limit' => 6,
        ], $atts, 'ws_main');

        // Determine what to render based on URL
        if (isset($_GET['ws_sighting'])) {
            return self::render_single_view((int) $_GET['ws_sighting']);
        }

        if (isset($_GET['ws_query']) && !empty($_GET['ws_query'])) {
            return self::render_search_view($atts);
        }

        if (isset($_GET['ws_browse'])) {
            return self::render_browse_view($atts);
        }

        // Default: landing page
        return self::render_landing_view($atts);
    }

    /**
     * Landing page view
     */
    private static function render_landing_view($atts) {
        ob_start();

        // Stats header
        ws_get_template('stats-header.php');

        // Search form (no results)
        ws_get_template('search-form.php', [
            'query' => '',
            'placeholder' => 'Search for...',
            'results' => null,
        ]);

        // Recent sightings
        $recent = WS_Search_Service::get_recent((int) $atts['recent_limit']);
        ws_get_template('sighting-list.php', [
            'sightings' => $recent['sightings'],
            'title' => 'Recent Sightings',
        ]);

        return ob_get_clean();
    }

    /**
     * Search results view
     */
    private static function render_search_view($atts) {
        $query = sanitize_text_field($_GET['ws_query']);
        $page = isset($_GET['ws_page']) ? max(1, (int) $_GET['ws_page']) : 1;

        $results = WS_Search_Service::search($query, [
            'page' => $page,
            'per_page' => 21,
        ]);

        ob_start();

        // Stats header
        ws_get_template('stats-header.php');

        // Search form with results
        ws_get_template('search-form.php', [
            'query' => $query,
            'placeholder' => 'Search for...',
            'results' => $results,
        ]);

        return ob_get_clean();
    }

    /**
     * Browse view (list or results)
     */
    private static function render_browse_view($atts) {
        $type = sanitize_text_field($_GET['ws_browse']);
        $value = isset($_GET['ws_value']) ? sanitize_text_field($_GET['ws_value']) : '';
        $page = isset($_GET['ws_page']) ? max(1, (int) $_GET['ws_page']) : 1;

        ob_start();

        // Stats header
        ws_get_template('stats-header.php');

        // Search form (empty, for navigation)
        ws_get_template('search-form.php', [
            'query' => '',
            'placeholder' => 'Search for...',
            'results' => null,
        ]);

        if ($value) {
            // Show results for specific value
            $results = self::get_browse_results($type, $value, $page);
            ws_get_template('browse-grid.php', [
                'type' => $type,
                'value' => $value,
                'list' => null,
                'results' => $results,
            ]);
        } else {
            // Show list to browse
            $list = self::get_browse_list($type);
            ws_get_template('browse-grid.php', [
                'type' => $type,
                'value' => '',
                'list' => $list,
                'results' => null,
            ]);
        }

        return ob_get_clean();
    }

    /**
     * Single sighting view
     */
    private static function render_single_view($faw_id) {
        $sighting = WS_Sighting_Repository::get_by_id($faw_id, get_current_user_id());
        if (!$sighting) {
            return '<p class="ws-error">Sighting not found.</p>';
        }

        $comments = WS_Comment_Service::get_for_sighting($faw_id);

        ob_start();
        ws_get_template('sighting-single.php', [
            'sighting' => $sighting,
            'comments' => $comments,
        ]);
        return ob_get_clean();
    }

    /**
     * Get browse list by type
     */
    private static function get_browse_list($type) {
        switch ($type) {
            case 'actor':
                return WS_Sighting_Repository::get_actors_list();
            case 'film':
                return WS_Sighting_Repository::get_films_list();
            case 'brand':
                return WS_Sighting_Repository::get_brands_list();
            default:
                return [];
        }
    }

    /**
     * Get browse results by type and value
     */
    private static function get_browse_results($type, $value, $page) {
        switch ($type) {
            case 'actor':
                return WS_Search_Service::browse_by_actor($value, ['page' => $page]);
            case 'film':
                return WS_Search_Service::browse_by_film($value, ['page' => $page]);
            case 'brand':
                return WS_Search_Service::browse_by_brand($value, ['page' => $page]);
            default:
                return ['sightings' => [], 'total' => 0, 'pages' => 0];
        }
    }

    /**
     * Standalone sighting shortcode (for embedding specific sightings)
     */
    public static function render_sighting($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'ws_sighting');

        $faw_id = (int) $atts['id'];
        if (!$faw_id) {
            return '<p class="ws-error">Invalid sighting ID.</p>';
        }

        return self::render_single_view($faw_id);
    }

    /**
     * User profile shortcode
     */
    public static function render_user_profile($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'ws_user_profile');

        $user_id = (int) $atts['id'] ?: get_current_user_id();

        if (!$user_id) {
            return '<p class="ws-error">Please log in to view your profile.</p>';
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return '<p class="ws-error">User not found.</p>';
        }

        $comments = WS_Comment_Repository::get_by_user($user_id, 20);
        $votes = WS_Vote_Repository::get_by_user($user_id, 20);
        $vote_count = WS_Vote_Repository::count_by_user($user_id);

        ob_start();
        ws_get_template('user-profile.php', [
            'user' => $user,
            'comments' => $comments,
            'votes' => $votes,
            'vote_count' => $vote_count,
        ]);
        return ob_get_clean();
    }
}
