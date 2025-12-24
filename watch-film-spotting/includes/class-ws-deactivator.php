<?php
/**
 * Plugin deactivation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_Deactivator {
    
    public static function deactivate() {
        flush_rewrite_rules();
        // Note: We do NOT drop tables on deactivation
        // Tables are preserved so data is not lost
    }
}
