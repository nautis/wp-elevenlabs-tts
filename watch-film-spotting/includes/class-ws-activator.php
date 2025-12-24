<?php
/**
 * Plugin activation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Activator {
    
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        flush_rewrite_rules();
    }
    
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Comments table
        $table_comments = $wpdb->prefix . WS_TABLE_COMMENTS;
        $sql_comments = "CREATE TABLE IF NOT EXISTS $table_comments (
            comment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            faw_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            content TEXT NOT NULL,
            comment_type ENUM('general', 'correction', 'source', 'alternative') DEFAULT 'general',
            status ENUM('pending', 'approved', 'spam', 'trash') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_faw_id (faw_id),
            INDEX idx_user_id (user_id),
            INDEX idx_parent_id (parent_id),
            INDEX idx_status_created (status, created_at)
        ) $charset_collate;";
        
        // User votes table
        $table_votes = $wpdb->prefix . WS_TABLE_USER_VOTES;
        $sql_votes = "CREATE TABLE IF NOT EXISTS $table_votes (
            vote_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            faw_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            vote TINYINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY uniq_user_sighting (faw_id, user_id),
            INDEX idx_faw_id (faw_id),
            INDEX idx_user_id (user_id)
        ) $charset_collate;";
        
        // Sighting meta table
        $table_meta = $wpdb->prefix . WS_TABLE_SIGHTING_META;
        $sql_meta = "CREATE TABLE IF NOT EXISTS $table_meta (
            meta_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            faw_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY uniq_faw_key (faw_id, meta_key),
            INDEX idx_faw_id (faw_id),
            INDEX idx_meta_key (meta_key)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta($sql_comments);
        dbDelta($sql_votes);
        dbDelta($sql_meta);
        
        // Store plugin version
        update_option('watchspotting_version', WS_VERSION);
    }
    
    private static function set_default_options() {
        $defaults = [
            'watchspotting_comments_require_approval' => true,
            'watchspotting_allow_anonymous_voting' => false,
            'watchspotting_items_per_page' => 20,
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}
