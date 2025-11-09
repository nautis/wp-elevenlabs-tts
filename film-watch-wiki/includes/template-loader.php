<?php
/**
 * Template Loader
 * Loads custom templates for our custom post types
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Template_Loader {

    /**
     * Initialize
     */
    public static function init() {
        add_filter('single_template', array(__CLASS__, 'load_single_template'), 99);
        add_filter('archive_template', array(__CLASS__, 'load_archive_template'), 99);
        // Use very high priority to override theme template
        add_filter('template_include', array(__CLASS__, 'force_template_include'), PHP_INT_MAX - 1);
    }

    /**
     * Load custom single template for our post types
     */
    public static function load_single_template($template) {
        global $post;

        if (!$post) {
            return $template;
        }

        // Check if it's one of our post types
        $post_types = array('fww_movie', 'fww_actor', 'fww_watch', 'fww_brand');

        if (in_array($post->post_type, $post_types)) {
            // Try to load template from theme first
            $theme_template = locate_template(array(
                'single-' . $post->post_type . '.php',
                'film-watch-wiki/single-' . $post->post_type . '.php'
            ));

            if ($theme_template) {
                return $theme_template;
            }

            // Fall back to plugin template
            $plugin_template = FWW_PLUGIN_DIR . 'templates/single-' . $post->post_type . '.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Load custom archive template for our post types
     */
    public static function load_archive_template($template) {
        if (is_post_type_archive(array('fww_movie', 'fww_actor', 'fww_watch', 'fww_brand'))) {
            $post_type = get_query_var('post_type');

            // Try to load template from theme first
            $theme_template = locate_template(array(
                'archive-' . $post_type . '.php',
                'film-watch-wiki/archive-' . $post_type . '.php'
            ));

            if ($theme_template) {
                return $theme_template;
            }

            // Fall back to plugin template
            $plugin_template = FWW_PLUGIN_DIR . 'templates/archive-' . $post_type . '.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Force template include for our post types
     * This runs later in the template hierarchy as a fallback
     */
    public static function force_template_include($template) {
        // Debug logging (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FWW Template Filter Running - Template: ' . $template);
            error_log('FWW is_singular check: ' . (is_singular(array('fww_movie', 'fww_actor', 'fww_watch')) ? 'YES' : 'NO'));
            error_log('FWW post type: ' . get_post_type());
        }

        if (is_singular(array('fww_movie', 'fww_actor', 'fww_watch', 'fww_brand'))) {
            $post_type = get_post_type();

            $plugin_template = FWW_PLUGIN_DIR . 'templates/single-' . $post_type . '.php';

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FWW Plugin template path: ' . $plugin_template);
                error_log('FWW Template exists: ' . (file_exists($plugin_template) ? 'YES' : 'NO'));
            }

            if (file_exists($plugin_template)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FWW Returning plugin template');
                }
                return $plugin_template;
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FWW Returning original template');
        }
        return $template;
    }
}

// Initialize
FWW_Template_Loader::init();
