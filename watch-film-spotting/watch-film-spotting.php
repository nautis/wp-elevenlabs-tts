<?php
/**
 * Plugin Name: WatchSpotting
 * Plugin URI: https://tellingtime.com
 * Description: A crowd-sourced database of watches worn by actors in films. Browse, search, vote, and comment on watch sightings.
 * Version: 1.0.1
 * Author: TellingTime
 * Author URI: https://tellingtime.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: watchspotting
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WS_VERSION', '1.2.8');
define('WS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Database table names (without prefix)
define('WS_TABLE_COMMENTS', 'fwd_comments');
define('WS_TABLE_USER_VOTES', 'fwd_user_votes');
define('WS_TABLE_SIGHTING_META', 'fwd_sighting_meta');

// Existing FWD tables we read from
define('WS_TABLE_SIGHTINGS', 'fwd_film_actor_watch');
define('WS_TABLE_FILMS', 'fwd_films');
define('WS_TABLE_ACTORS', 'fwd_actors');
define('WS_TABLE_WATCHES', 'fwd_watches');
define('WS_TABLE_BRANDS', 'fwd_brands');
define('WS_TABLE_CHARACTERS', 'fwd_characters');
define('WS_TABLE_SEARCH_INDEX', 'fwd_search_index');

/**
 * Main plugin class
 */
class WatchSpotting {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        // Models
        require_once WS_PLUGIN_DIR . 'includes/models/class-ws-sighting.php';
        require_once WS_PLUGIN_DIR . 'includes/models/class-ws-comment.php';
        require_once WS_PLUGIN_DIR . 'includes/models/class-ws-vote.php';
        
        // Repositories
        require_once WS_PLUGIN_DIR . 'includes/repositories/class-ws-sighting-repository.php';
        require_once WS_PLUGIN_DIR . 'includes/repositories/class-ws-comment-repository.php';
        require_once WS_PLUGIN_DIR . 'includes/repositories/class-ws-vote-repository.php';
        require_once WS_PLUGIN_DIR . 'includes/repositories/class-ws-meta-repository.php';
        
        // Services
        require_once WS_PLUGIN_DIR . 'includes/services/class-ws-comment-service.php';
        require_once WS_PLUGIN_DIR . 'includes/services/class-ws-vote-service.php';
        require_once WS_PLUGIN_DIR . 'includes/services/class-ws-search-service.php';
        require_once WS_PLUGIN_DIR . 'includes/services/class-ws-moderation-service.php';
        
        // API
        require_once WS_PLUGIN_DIR . 'includes/api/class-ws-rest-controller.php';
        require_once WS_PLUGIN_DIR . 'includes/api/class-ws-sightings-api.php';
        require_once WS_PLUGIN_DIR . 'includes/api/class-ws-comments-api.php';
        require_once WS_PLUGIN_DIR . 'includes/api/class-ws-votes-api.php';
        
        // Shortcodes
        require_once WS_PLUGIN_DIR . 'includes/shortcodes/class-ws-shortcodes.php';
        
        // Admin
        if (is_admin()) {
            require_once WS_PLUGIN_DIR . 'admin/class-ws-admin.php';
        }
    }
    
    private function init_hooks() {
        // Activation/deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Initialize components
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    public function activate() {
        require_once WS_PLUGIN_DIR . 'includes/class-ws-activator.php';
        WS_Activator::activate();
    }
    
    public function deactivate() {
        require_once WS_PLUGIN_DIR . 'includes/class-ws-deactivator.php';
        WS_Deactivator::deactivate();
    }
    
    public function init() {
        // Initialize shortcodes
        WS_Shortcodes::init();
        
        // Initialize admin
        if (is_admin()) {
            WS_Admin::init();
        }
    }
    
    public function register_rest_routes() {
        WS_Sightings_API::register_routes();
        WS_Comments_API::register_routes();
        WS_Votes_API::register_routes();
    }
    
    public function enqueue_public_assets() {
        wp_enqueue_style(
            'watchspotting-public',
            WS_PLUGIN_URL . 'assets/css/public.css',
            [],
            WS_VERSION
        );
        
        wp_enqueue_script(
            'watchspotting-public',
            WS_PLUGIN_URL . 'assets/js/public.js',
            ['jquery'],
            WS_VERSION,
            true
        );
        
        wp_localize_script('watchspotting-public', 'wsData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('watchspotting/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
        ]);
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'watchspotting') === false) {
            return;
        }
        
        wp_enqueue_style(
            'watchspotting-admin',
            WS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WS_VERSION
        );
        
        wp_enqueue_script(
            'watchspotting-admin',
            WS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WS_VERSION,
            true
        );
    }
}

// Template helper function
function ws_get_template($template_name, $args = []) {
    $theme_path = get_stylesheet_directory() . '/watch-film-spotting/' . $template_name;
    $plugin_path = WS_PLUGIN_DIR . 'templates/' . $template_name;
    
    $template = file_exists($theme_path) ? $theme_path : $plugin_path;
    
    if ($args) {
        extract($args);
    }
    
    include $template;
}

// Table name helper
function ws_table($table) {
    global $wpdb;
    return $wpdb->prefix . $table;
}

// Initialize plugin
WatchSpotting::get_instance();
