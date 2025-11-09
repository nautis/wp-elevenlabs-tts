<?php
/**
 * Admin Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Admin_Settings {

    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Load migration functions
        require_once FWW_PLUGIN_DIR . 'includes/migration.php';
    }

    /**
     * Add settings page to WordPress admin
     */
    public static function add_settings_page() {
        add_options_page(
            'Film Watch Wiki Settings',
            'Film Watch Wiki',
            'manage_options',
            'film-watch-wiki',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('fww_settings', 'fww_tmdb_api_key');
        register_setting('fww_settings', 'fww_tmdb_language');
        register_setting('fww_settings', 'fww_cache_duration');
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        // Handle migration
        if (isset($_POST['fww_run_migration'])) {
            check_admin_referer('fww_migration_nonce');
            $results = FWW_Migration::migrate_films();

            echo '<div class="notice notice-success"><p>';
            echo sprintf(
                'Migration complete! Created: %d, Skipped: %d, Total: %d',
                $results['created'],
                $results['skipped'],
                $results['total']
            );
            if (!empty($results['errors'])) {
                echo '<br>Errors: ' . implode('<br>', array_map('esc_html', $results['errors']));
            }
            echo '</p></div>';
        }

        // Save settings
        if (isset($_POST['fww_save_settings'])) {
            check_admin_referer('fww_settings_nonce');

            update_option('fww_tmdb_api_key', sanitize_text_field($_POST['fww_tmdb_api_key']));
            update_option('fww_tmdb_language', sanitize_text_field($_POST['fww_tmdb_language']));
            update_option('fww_cache_duration', absint($_POST['fww_cache_duration']));

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $tmdb_api_key = get_option('fww_tmdb_api_key', '');
        $tmdb_language = get_option('fww_tmdb_language', 'en-US');
        $cache_duration = get_option('fww_cache_duration', 86400);
        ?>

        <div class="wrap">
            <h1>Film Watch Wiki Settings</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=film-watch-wiki&tab=settings"
                   class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    Settings
                </a>
                <a href="?page=film-watch-wiki&tab=migration"
                   class="nav-tab <?php echo $tab === 'migration' ? 'nav-tab-active' : ''; ?>">
                    Migration
                </a>
            </h2>

            <?php if ($tab === 'migration') : ?>
                <?php self::render_migration_tab(); ?>
            <?php else : ?>
                <?php self::render_settings_tab($tmdb_api_key, $tmdb_language, $cache_duration); ?>
            <?php endif; ?>

        </div>

        <?php
    }

    /**
     * Render settings tab
     */
    private static function render_settings_tab($tmdb_api_key, $tmdb_language, $cache_duration) {
        ?>

        <div class="fww-settings-tab" style="margin-top: 20px;">

            <form method="post" action="">
                <?php wp_nonce_field('fww_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fww_tmdb_api_key">TMDB API Key (Bearer Token)</label></th>
                        <td>
                            <input type="text" id="fww_tmdb_api_key" name="fww_tmdb_api_key"
                                   value="<?php echo esc_attr($tmdb_api_key); ?>"
                                   class="regular-text">
                            <p class="description">
                                Enter your TMDB API Read Access Token from
                                <a href="https://www.themoviedb.org/settings/api" target="_blank">TMDB API Settings</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="fww_tmdb_language">Language</label></th>
                        <td>
                            <input type="text" id="fww_tmdb_language" name="fww_tmdb_language"
                                   value="<?php echo esc_attr($tmdb_language); ?>"
                                   class="regular-text">
                            <p class="description">Language code for TMDB data (e.g., en-US, fr-FR)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="fww_cache_duration">Cache Duration (seconds)</label></th>
                        <td>
                            <input type="number" id="fww_cache_duration" name="fww_cache_duration"
                                   value="<?php echo esc_attr($cache_duration); ?>"
                                   class="regular-text">
                            <p class="description">How long to cache TMDB data (default: 86400 = 24 hours)</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="fww_save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>

        </div>

        <?php
    }

    /**
     * Render migration tab
     */
    private static function render_migration_tab() {
        global $wpdb;

        // Get counts
        $total_films = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fwd_films");
        $migrated_films = wp_count_posts('fww_movie')->publish;
        ?>

        <div class="fww-migration-tab" style="margin-top: 20px;">

            <div class="fww-migration-status" style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">Migration Status</h3>
                <p>
                    <strong>Films in Database:</strong> <?php echo esc_html($total_films); ?><br>
                    <strong>Migrated to Custom Posts:</strong> <?php echo esc_html($migrated_films); ?>
                </p>
            </div>

            <h3>Step 1: Migrate Films</h3>
            <p>
                This will create a <code>fww_movie</code> custom post for each film in the <code>wp_fwd_films</code> table.
                Films that have already been migrated will be skipped.
            </p>

            <form method="post" action="" onsubmit="return confirm('Are you sure you want to run the migration? This will create posts for all films.');">
                <?php wp_nonce_field('fww_migration_nonce'); ?>
                <p>
                    <input type="submit" name="fww_run_migration" class="button button-primary" value="Run Migration">
                </p>
            </form>

            <hr>

            <h3>Step 2: Add TMDB IDs (Manual)</h3>
            <p>
                After migration, you need to manually add TMDB IDs to each movie post:
            </p>
            <ol>
                <li>Go to <a href="<?php echo admin_url('edit.php?post_type=fww_movie'); ?>">Movies</a></li>
                <li>Edit each movie</li>
                <li>Find the TMDB ID from <a href="https://www.themoviedb.org/" target="_blank">themoviedb.org</a></li>
                <li>Enter it in the "Movie Details" metabox</li>
                <li>Save the post</li>
            </ol>
            <p>The plugin will automatically fetch and cache movie data from TMDB when you save.</p>

        </div>

        <?php
    }
}

// Initialize
FWW_Admin_Settings::init();
