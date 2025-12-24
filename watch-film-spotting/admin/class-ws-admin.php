<?php
/**
 * Admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Admin {
    
    private static $initiated = false;
    
    public static function init() {
        if (self::$initiated) {
            return;
        }
        
        self::$initiated = true;
        
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'handle_actions']);
    }
    
    public static function register_menu() {
        // Main menu
        add_menu_page(
            'WatchSpotting',
            'WatchSpotting',
            'moderate_comments',
            'watchspotting',
            [__CLASS__, 'render_dashboard'],
            'dashicons-visibility',
            30
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            'watchspotting',
            'Dashboard',
            'Dashboard',
            'moderate_comments',
            'watchspotting',
            [__CLASS__, 'render_dashboard']
        );
        
        // Moderation queue
        $pending_count = WS_Moderation_Service::count_pending();
        $menu_title = 'Moderation';
        if ($pending_count > 0) {
            $menu_title .= ' <span class="awaiting-mod count-' . $pending_count . '"><span class="pending-count">' . $pending_count . '</span></span>';
        }
        
        add_submenu_page(
            'watchspotting',
            'Comment Moderation',
            $menu_title,
            'moderate_comments',
            'watchspotting-moderation',
            [__CLASS__, 'render_moderation']
        );
        
        // Settings
        add_submenu_page(
            'watchspotting',
            'Settings',
            'Settings',
            'manage_options',
            'watchspotting-settings',
            [__CLASS__, 'render_settings']
        );
    }
    
    public static function handle_actions() {
        if (!current_user_can('moderate_comments')) {
            return;
        }
        
        // Handle single comment actions
        if (isset($_GET['ws_action'], $_GET['comment_id'], $_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'ws_moderate_comment')) {
                return;
            }
            
            $action = sanitize_text_field($_GET['ws_action']);
            $comment_id = (int) $_GET['comment_id'];
            
            switch ($action) {
                case 'approve':
                    WS_Comment_Service::approve($comment_id);
                    break;
                case 'spam':
                    WS_Comment_Service::spam($comment_id);
                    break;
                case 'trash':
                    WS_Comment_Service::trash($comment_id);
                    break;
            }
            
            wp_redirect(remove_query_arg(['ws_action', 'comment_id', '_wpnonce']));
            exit;
        }
        
        // Handle bulk actions
        if (isset($_POST['ws_bulk_action'], $_POST['comment_ids'], $_POST['_wpnonce'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'ws_bulk_moderate')) {
                return;
            }
            
            $action = sanitize_text_field($_POST['ws_bulk_action']);
            $ids = array_map('intval', $_POST['comment_ids']);
            
            switch ($action) {
                case 'approve':
                    WS_Moderation_Service::bulk_approve($ids);
                    break;
                case 'spam':
                    WS_Moderation_Service::bulk_spam($ids);
                    break;
                case 'trash':
                    WS_Moderation_Service::bulk_trash($ids);
                    break;
            }
            
            wp_redirect(remove_query_arg(['ws_bulk_action']));
            exit;
        }
    }
    
    public static function render_dashboard() {
        $pending_count = WS_Moderation_Service::count_pending();
        
        include WS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public static function render_moderation() {
        $comments = WS_Moderation_Service::get_queue(50);
        $total = WS_Moderation_Service::count_pending();
        
        include WS_PLUGIN_DIR . 'admin/views/moderation-queue.php';
    }
    
    public static function render_settings() {
        // Handle settings save
        if (isset($_POST['ws_save_settings'], $_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'ws_save_settings')) {
                update_option('watchspotting_comments_require_approval', 
                    isset($_POST['comments_require_approval']) ? true : false);
                update_option('watchspotting_items_per_page', 
                    max(1, min(100, (int) $_POST['items_per_page'])));
                
                add_settings_error('watchspotting', 'settings_saved', 'Settings saved.', 'success');
            }
        }
        
        $require_approval = get_option('watchspotting_comments_require_approval', true);
        $items_per_page = get_option('watchspotting_items_per_page', 20);
        
        include WS_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
