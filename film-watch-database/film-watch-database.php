<?php
/**
 * Plugin Name: Film Watch Database
 * Plugin URI: https://github.com/nautis/watch-utils
 * Description: WordPress plugin for cataloging watches worn by actors in films, with TMDB API integration for autocomplete and AI-powered parsing.
 * Version: 3.9.5
 * Author: Your Name
 * Author URI: https://github.com/nautis
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: film-watch-database
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FWD_VERSION', '3.9.5');
define('FWD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FWD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FWD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Film_Watch_Database {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once FWD_PLUGIN_DIR . 'includes/database.php';
        require_once FWD_PLUGIN_DIR . 'includes/ai-parser.php';
        require_once FWD_PLUGIN_DIR . 'includes/tmdb-api.php';
        require_once FWD_PLUGIN_DIR . 'includes/api-functions.php';
        require_once FWD_PLUGIN_DIR . 'includes/shortcodes.php';

        // Load admin files only in admin
        if (is_admin()) {
            require_once FWD_PLUGIN_DIR . 'includes/admin-settings.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . FWD_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Initialize database tables and seed data
        $db = fwd_db();
        $db->init();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients with proper SQL escaping
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_fwd_') . '%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_fwd_') . '%'
        ));

        flush_rewrite_rules();
    }

    /**
     * Enqueue frontend styles and scripts
     * Only load when shortcodes are present to improve performance
     */
    public function enqueue_frontend_assets() {
        // Check if any of our shortcodes are present in the post content
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $shortcodes = array(
            'film_watch_search',
            'film_watch_stats',
            'film_watch_top_brands',
            'film_watch_actor',
            'film_watch_brand',
            'film_watch_film',
            'film_watch_add'
        );

        $has_shortcode = false;
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }

        // Don't load assets if no shortcodes are present
        if (!$has_shortcode) {
            return;
        }

        wp_enqueue_style(
            'fwd-frontend',
            FWD_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            FWD_VERSION
        );

        // Enqueue WordPress media library for image uploads
        if (current_user_can('manage_options')) {
            wp_enqueue_media();
        }

        wp_enqueue_script(
            'fwd-frontend',
            FWD_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            FWD_VERSION,
            true
        );

        // Pass AJAX URL and nonce to JavaScript
        wp_localize_script('fwd-frontend', 'fwdAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fwd_ajax_nonce')
        ));
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets($hook) {
        // Check if we're on our settings page (works for both regular and network admin)
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        if ($page !== 'film-watch-database') {
            return;
        }

        // Enqueue WordPress media library for image uploads
        wp_enqueue_media();

        // Enqueue frontend CSS and JS (needed for entry form on admin page)
        wp_enqueue_style(
            'fwd-frontend',
            FWD_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            FWD_VERSION
        );

        wp_enqueue_script(
            'fwd-frontend',
            FWD_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            FWD_VERSION,
            true
        );

        // Pass AJAX URL and nonce to JavaScript
        wp_localize_script('fwd-frontend', 'fwdAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fwd_ajax_nonce')
        ));

        // Also load admin-specific assets
        wp_enqueue_style(
            'fwd-admin',
            FWD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FWD_VERSION
        );

        wp_enqueue_script(
            'fwd-admin',
            FWD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'fwd-frontend'),
            FWD_VERSION,
            true
        );
    }

    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=film-watch-database">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Initialize the plugin
 */
function fwd_init() {
    return Film_Watch_Database::get_instance();
}

// Start the plugin
fwd_init();
