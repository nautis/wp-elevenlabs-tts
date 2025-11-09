<?php
/**
 * Plugin Name: Film Watch Wiki
 * Plugin URI: https://github.com/nautis/watch-utils
 * Description: Wiki-style WordPress plugin for movies, actors, and watches with TMDB API integration. Creates dedicated pages for each entity with rich relationships.
 * Version: 1.1.2
 * Author: Your Name
 * Author URI: https://github.com/nautis
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: film-watch-wiki
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FWW_VERSION', '1.1.2');
define('FWW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FWW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FWW_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Film_Watch_Wiki {

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
        require_once FWW_PLUGIN_DIR . 'includes/post-types.php';
        require_once FWW_PLUGIN_DIR . 'includes/tmdb-api.php';
        require_once FWW_PLUGIN_DIR . 'includes/movie-functions.php';
        require_once FWW_PLUGIN_DIR . 'includes/template-loader.php';
        require_once FWW_PLUGIN_DIR . 'includes/ajax-handlers.php';

        // Load admin files only in admin
        if (is_admin()) {
            require_once FWW_PLUGIN_DIR . 'includes/admin-settings.php';
            require_once FWW_PLUGIN_DIR . 'includes/admin-metaboxes.php';
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

        // Add custom image sizes
        add_action('after_setup_theme', array($this, 'add_image_sizes'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . FWW_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Add custom image sizes for movie posters
     */
    public function add_image_sizes() {
        // Movie poster size (2:3 aspect ratio)
        add_image_size('fww-poster', 320, 480, true);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register post types for flush_rewrite_rules() to work
        FWW_Post_Types::register_post_types();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Enqueue frontend styles and scripts
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'fww-frontend',
            FWW_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            FWW_VERSION
        );

        wp_enqueue_script(
            'fww-frontend',
            FWW_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            FWW_VERSION,
            true
        );
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our post types
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, array('fww_movie', 'fww_actor', 'fww_watch'))) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'fww-admin',
            FWW_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FWW_VERSION
        );

        wp_enqueue_script(
            'fww-admin',
            FWW_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            FWW_VERSION,
            true
        );

        // Pass AJAX URL and nonce to JavaScript
        wp_localize_script('fww-admin', 'fwwAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fww_ajax_nonce')
        ));
    }

    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=film-watch-wiki">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Initialize the plugin
 */
function fww_init() {
    return Film_Watch_Wiki::get_instance();
}

// Start the plugin
fww_init();
