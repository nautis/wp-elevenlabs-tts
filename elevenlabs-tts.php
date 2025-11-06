<?php
/**
 * Plugin Name: ElevenLabs Text-to-Speech
 * Plugin URI: https://github.com/nautis/studious-palm-tree
 * Description: Convert blog posts to audio using ElevenLabs AI text-to-speech API
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elevenlabs-tts
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELEVENLABS_TTS_VERSION', '1.0.0');
define('ELEVENLABS_TTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ELEVENLABS_TTS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'includes/class-elevenlabs-api.php';
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'includes/class-content-filter.php';
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'includes/class-audio-generator.php';
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'admin/class-admin-settings.php';

/**
 * Main plugin class
 */
class ElevenLabs_TTS {

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
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin hooks
        if (is_admin()) {
            new ElevenLabs_TTS_Admin_Settings();
        }

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_filter('the_content', array($this, 'inject_audio_player'), 20);

        // AJAX hooks
        add_action('wp_ajax_elevenlabs_generate_audio', array($this, 'ajax_generate_audio'));
        add_action('wp_ajax_elevenlabs_delete_audio', array($this, 'ajax_delete_audio'));

        // Add meta box for post editing
        add_action('add_meta_boxes', array($this, 'add_audio_meta_box'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create upload directory for audio files
        $upload_dir = wp_upload_dir();
        $audio_dir = $upload_dir['basedir'] . '/elevenlabs-audio';

        if (!file_exists($audio_dir)) {
            wp_mkdir_p($audio_dir);
        }

        // Add default options
        if (false === get_option('elevenlabs_tts_settings')) {
            add_option('elevenlabs_tts_settings', array(
                'api_key' => '',
                'voice_id' => '',
                'model_id' => 'eleven_multilingual_v2',
                'auto_generate' => false,
                'player_position' => 'before_content'
            ));
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup tasks if needed
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        if (is_single()) {
            wp_enqueue_style(
                'elevenlabs-tts-player',
                ELEVENLABS_TTS_PLUGIN_URL . 'assets/css/player.css',
                array(),
                ELEVENLABS_TTS_VERSION
            );

            wp_enqueue_script(
                'elevenlabs-tts-player',
                ELEVENLABS_TTS_PLUGIN_URL . 'assets/js/player.js',
                array('jquery'),
                ELEVENLABS_TTS_VERSION,
                true
            );

            wp_localize_script('elevenlabs-tts-player', 'elevenlabsData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elevenlabs_tts_nonce'),
                'postId' => get_the_ID()
            ));
        }
    }

    /**
     * Inject audio player into post content
     */
    public function inject_audio_player($content) {
        if (!is_single() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        $audio_url = get_post_meta($post_id, '_elevenlabs_audio_url', true);

        $player_html = '<div class="elevenlabs-audio-player-container">';

        if ($audio_url && file_exists(str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $audio_url))) {
            // Audio exists - show player
            $player_html .= $this->get_player_html($audio_url, $post_id);
        } else {
            // No audio - show generate button
            $player_html .= $this->get_generate_button_html($post_id);
        }

        $player_html .= '</div>';

        // Get player position setting
        $settings = get_option('elevenlabs_tts_settings');
        $position = isset($settings['player_position']) ? $settings['player_position'] : 'before_content';

        // Inject based on position setting
        switch ($position) {
            case 'after_content':
                return $content . $player_html;
            case 'before_content':
            default:
                return $player_html . $content;
        }
    }

    /**
     * Get HTML for audio player
     */
    private function get_player_html($audio_url, $post_id) {
        $html = '<div class="elevenlabs-player">';
        $html .= '<div class="elevenlabs-player-icon">🎧</div>';
        $html .= '<div class="elevenlabs-player-content">';
        $html .= '<p class="elevenlabs-player-title">Listen to this article</p>';
        $html .= '<audio controls controlsList="nodownload" class="elevenlabs-audio-element">';
        $html .= '<source src="' . esc_url($audio_url) . '" type="audio/mpeg">';
        $html .= 'Your browser does not support the audio element.';
        $html .= '</audio>';
        $html .= '</div>';

        // Add regenerate button for admins
        if (current_user_can('edit_post', $post_id)) {
            $html .= '<button class="elevenlabs-regenerate-btn" data-post-id="' . esc_attr($post_id) . '">Regenerate</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get HTML for generate button
     */
    private function get_generate_button_html($post_id) {
        // Only show to users who can edit the post
        if (!current_user_can('edit_post', $post_id)) {
            return '';
        }

        $html = '<div class="elevenlabs-generate-container">';
        $html .= '<p>Audio version not yet generated.</p>';
        $html .= '<button class="elevenlabs-generate-btn" data-post-id="' . esc_attr($post_id) . '">Generate Audio</button>';
        $html .= '<div class="elevenlabs-progress" style="display:none;">';
        $html .= '<span class="elevenlabs-spinner"></span>';
        $html .= '<span class="elevenlabs-status-text">Generating audio...</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * AJAX handler for generating audio
     */
    public function ajax_generate_audio() {
        check_ajax_referer('elevenlabs_tts_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $force = isset($_POST['force']) ? (bool)$_POST['force'] : false;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $generator = new ElevenLabs_TTS_Audio_Generator();
        $result = $generator->generate_audio_for_post($post_id, $force);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Audio generated successfully',
            'audio_url' => $result
        ));
    }

    /**
     * AJAX handler for deleting audio
     */
    public function ajax_delete_audio() {
        check_ajax_referer('elevenlabs_tts_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $audio_url = get_post_meta($post_id, '_elevenlabs_audio_url', true);

        if ($audio_url) {
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $audio_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            delete_post_meta($post_id, '_elevenlabs_audio_url');
        }

        wp_send_json_success(array('message' => 'Audio deleted successfully'));
    }

    /**
     * Add meta box to post editor
     */
    public function add_audio_meta_box() {
        add_meta_box(
            'elevenlabs_audio_meta_box',
            'ElevenLabs Audio',
            array($this, 'render_audio_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render meta box content
     */
    public function render_audio_meta_box($post) {
        wp_nonce_field('elevenlabs_meta_box', 'elevenlabs_meta_box_nonce');

        $audio_url = get_post_meta($post->ID, '_elevenlabs_audio_url', true);

        if ($audio_url && file_exists(str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $audio_url))) {
            echo '<p><strong>Status:</strong> Audio generated</p>';
            echo '<audio controls style="width:100%; margin: 10px 0;">';
            echo '<source src="' . esc_url($audio_url) . '" type="audio/mpeg">';
            echo '</audio>';
            echo '<button type="button" class="button elevenlabs-regenerate-btn" data-post-id="' . esc_attr($post->ID) . '">Regenerate Audio</button>';
            echo '<button type="button" class="button elevenlabs-delete-btn" data-post-id="' . esc_attr($post->ID) . '" style="margin-left:5px;">Delete Audio</button>';
        } else {
            echo '<p><strong>Status:</strong> No audio generated</p>';
            echo '<button type="button" class="button button-primary elevenlabs-generate-btn" data-post-id="' . esc_attr($post->ID) . '">Generate Audio</button>';
        }

        echo '<div class="elevenlabs-progress" style="display:none; margin-top:10px;">';
        echo '<span class="elevenlabs-status-text">Processing...</span>';
        echo '</div>';

        // Add inline script for meta box
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var postId = <?php echo $post->ID; ?>;
            var nonce = '<?php echo wp_create_nonce('elevenlabs_tts_nonce'); ?>';

            $('.elevenlabs-generate-btn, .elevenlabs-regenerate-btn').on('click', function() {
                var $progress = $('.elevenlabs-progress');
                $(this).prop('disabled', true);
                $progress.show();

                $.post(ajaxurl, {
                    action: 'elevenlabs_generate_audio',
                    post_id: postId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        $progress.hide();
                        $('.elevenlabs-generate-btn, .elevenlabs-regenerate-btn').prop('disabled', false);
                    }
                });
            });

            $('.elevenlabs-delete-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete this audio file?')) {
                    return;
                }

                $(this).prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'elevenlabs_delete_audio',
                    post_id: postId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        $('.elevenlabs-delete-btn').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize the plugin
function elevenlabs_tts_init() {
    return ElevenLabs_TTS::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'elevenlabs_tts_init');
