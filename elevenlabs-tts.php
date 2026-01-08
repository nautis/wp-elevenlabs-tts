<?php
/**
 * Plugin Name: ElevenLabs Text-to-Speech
 * Plugin URI: https://github.com/nautis/studious-palm-tree
 * Description: Convert blog posts to audio using ElevenLabs AI text-to-speech API
 * Version: 1.2.5
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elevenlabs-tts
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package ElevenLabs_TTS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'ELEVENLABS_TTS_VERSION', '1.2.5' );
define( 'ELEVENLABS_TTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEVENLABS_TTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'includes/class-elevenlabs-api.php';
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'includes/class-content-filter.php';
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'includes/class-audio-generator.php';
require_once ELEVENLABS_TTS_PLUGIN_DIR . 'admin/class-admin-settings.php';

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class ElevenLabs_TTS {

    /**
     * Singleton instance
     *
     * @var ElevenLabs_TTS|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return ElevenLabs_TTS
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
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
        // Activation/Deactivation hooks.
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Admin hooks.
        if ( is_admin() ) {
            new ElevenLabs_TTS_Admin_Settings();
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_scripts' ) );
        }

        // Frontend hooks.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        add_filter( 'the_content', array( $this, 'inject_audio_player' ), 20 );

        // AJAX hooks.
        add_action( 'wp_ajax_elevenlabs_generate_audio', array( $this, 'ajax_generate_audio' ) );
        add_action( 'wp_ajax_elevenlabs_delete_audio', array( $this, 'ajax_delete_audio' ) );

        // Add meta box for post editing.
        add_action( 'add_meta_boxes', array( $this, 'add_audio_meta_box' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create upload directory for audio files.
        $upload_dir = wp_upload_dir();
        $audio_dir  = $upload_dir['basedir'] . '/elevenlabs-audio';

        if ( ! file_exists( $audio_dir ) ) {
            wp_mkdir_p( $audio_dir );

            // Prevent directory listing.
            file_put_contents( $audio_dir . '/index.php', '<?php // Silence is golden' );
        }

        // Create temp directory.
        $temp_dir = $audio_dir . '/temp';
        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
            file_put_contents( $temp_dir . '/index.php', '<?php // Silence is golden' );
        }

        // Add default options.
        if ( false === get_option( 'elevenlabs_tts_settings' ) ) {
            add_option( 'elevenlabs_tts_settings', array(
                'api_key'         => '',
                'voice_id'        => '',
                'model_id'        => 'eleven_multilingual_v2',
                'auto_generate'   => false,
                'player_position' => 'before_content',
            ) );
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any transients.
        delete_transient( 'elevenlabs_tts_voices' );
        delete_transient( 'elevenlabs_tts_models' );
    }

    /**
     * Get validated audio file path from URL
     * Prevents path traversal attacks
     *
     * @param string $audio_url The audio URL.
     * @return string|false Valid file path or false if invalid.
     */
    private function get_validated_audio_path( $audio_url ) {
        if ( empty( $audio_url ) ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $audio_url );

        // Resolve real path and validate it's within the audio directory.
        $real_path = realpath( $file_path );
        $audio_dir = realpath( $upload_dir['basedir'] . '/elevenlabs-audio' );

        // If realpath fails (file doesn't exist), validate the intended path.
        if ( false === $real_path ) {
            // Normalize path and check it starts with audio directory.
            $normalized = wp_normalize_path( $file_path );
            $expected_base = wp_normalize_path( $upload_dir['basedir'] . '/elevenlabs-audio/' );

            if ( strpos( $normalized, $expected_base ) !== 0 ) {
                return false;
            }

            return $file_path;
        }

        // Validate real path is within audio directory.
        if ( false === $audio_dir || strpos( $real_path, $audio_dir ) !== 0 ) {
            return false;
        }

        return $real_path;
    }

    /**
     * Enqueue frontend scripts and styles
     * Only loads when needed (post has audio or user can generate)
     */
    public function enqueue_frontend_scripts() {
        if ( ! is_singular( 'post' ) || ! is_main_query() ) {
            return;
        }

        $post_id     = get_the_ID();
        $audio_url   = get_post_meta( $post_id, '_elevenlabs_audio_url', true );
        $can_edit    = current_user_can( 'edit_post', $post_id );

        // Only load assets if there's audio or user can generate.
        if ( ! $audio_url && ! $can_edit ) {
            return;
        }

        wp_enqueue_style(
            'elevenlabs-tts-player',
            ELEVENLABS_TTS_PLUGIN_URL . 'assets/css/player.css',
            array(),
            ELEVENLABS_TTS_VERSION
        );

        wp_enqueue_script(
            'elevenlabs-tts-player',
            ELEVENLABS_TTS_PLUGIN_URL . 'assets/js/player.js',
            array( 'jquery' ),
            ELEVENLABS_TTS_VERSION,
            true
        );

        // Prepare localization data.
        $localize_data = array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'elevenlabs_tts_nonce' ),
            'postId'  => $post_id,
        );

        // Add word timestamps if available (for synchronized highlighting).
        if ( $audio_url ) {
            $word_timestamps = get_post_meta( $post_id, '_elevenlabs_word_timestamps', true );
            if ( ! empty( $word_timestamps ) ) {
                // Sanitize timestamps to fix any smart quotes that break JSON parsing.
                $localize_data['wordTimestamps'] = $this->sanitize_timestamps_for_output( $word_timestamps );
            }
        }

        wp_localize_script( 'elevenlabs-tts-player', 'elevenlabsData', $localize_data );
    }

    /**
     * Enqueue metabox scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_metabox_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        global $post;
        if ( ! $post || 'post' !== $post->post_type ) {
            return;
        }

        wp_enqueue_script(
            'elevenlabs-tts-metabox',
            ELEVENLABS_TTS_PLUGIN_URL . 'assets/js/metabox.js',
            array( 'jquery' ),
            ELEVENLABS_TTS_VERSION,
            true
        );

        wp_localize_script( 'elevenlabs-tts-metabox', 'elevenlabsMetabox', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'elevenlabs_tts_nonce' ),
            'postId'  => $post->ID,
        ) );
    }

    /**
     * Inject audio player into post content
     *
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function inject_audio_player( $content ) {
        if ( ! is_singular( 'post' ) || ! is_main_query() || ! in_the_loop() ) {
            return $content;
        }

        $post_id   = get_the_ID();
        $audio_url = get_post_meta( $post_id, '_elevenlabs_audio_url', true );

        $player_html = '<div class="elevenlabs-audio-player-container">';

        // Validate file path before checking existence.
        $file_path = $this->get_validated_audio_path( $audio_url );

        if ( $audio_url && $file_path && file_exists( $file_path ) ) {
            // Audio exists - show player.
            $player_html .= $this->get_player_html( $audio_url, $post_id );
        } else {
            // No audio - show generate button.
            $player_html .= $this->get_generate_button_html( $post_id );
        }

        $player_html .= '</div>';

        // Get player position setting.
        $settings = get_option( 'elevenlabs_tts_settings' );
        $position = isset( $settings['player_position'] ) ? $settings['player_position'] : 'before_content';

        // Inject based on position setting.
        switch ( $position ) {
            case 'after_content':
                return $content . $player_html;
            case 'before_content':
            default:
                return $player_html . $content;
        }
    }

    /**
     * Get HTML for audio player
     *
     * @param string $audio_url Audio file URL.
     * @param int    $post_id   Post ID.
     * @return string Player HTML.
     */
    private function get_player_html( $audio_url, $post_id ) {
        $html  = '<div class="elevenlabs-player">';
        $html .= '<div class="elevenlabs-player-icon">🎧</div>';
        $html .= '<div class="elevenlabs-player-content">';
        $html .= '<p class="elevenlabs-player-title">' . esc_html__( 'Listen to this article', 'elevenlabs-tts' ) . '</p>';
        $html .= '<audio controls controlsList="nodownload" class="elevenlabs-audio-element">';
        $html .= '<source src="' . esc_url( $audio_url ) . '" type="audio/mpeg">';
        $html .= esc_html__( 'Your browser does not support the audio element.', 'elevenlabs-tts' );
        $html .= '</audio>';
        $html .= '</div>';

        // Add regenerate button for admins.
        if ( current_user_can( 'edit_post', $post_id ) ) {
            $html .= '<button class="elevenlabs-regenerate-btn" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Regenerate', 'elevenlabs-tts' ) . '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get HTML for generate button
     *
     * @param int $post_id Post ID.
     * @return string Button HTML.
     */
    private function get_generate_button_html( $post_id ) {
        // Only show to users who can edit the post.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return '';
        }

        $html  = '<div class="elevenlabs-generate-container">';
        $html .= '<p>' . esc_html__( 'Audio version not yet generated.', 'elevenlabs-tts' ) . '</p>';
        $html .= '<button class="elevenlabs-generate-btn" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Generate Audio', 'elevenlabs-tts' ) . '</button>';
        $html .= '<div class="elevenlabs-progress" style="display:none;">';
        $html .= '<span class="elevenlabs-spinner"></span>';
        $html .= '<span class="elevenlabs-status-text">' . esc_html__( 'Generating audio...', 'elevenlabs-tts' ) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * AJAX handler for generating audio
     */
    public function ajax_generate_audio() {
        check_ajax_referer( 'elevenlabs_tts_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $force   = isset( $_POST['force'] ) ? (bool) $_POST['force'] : false;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'elevenlabs-tts' ) ) );
        }

        $generator = new ElevenLabs_TTS_Audio_Generator();
        $result    = $generator->generate_audio_for_post( $post_id, $force );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'   => __( 'Audio generated successfully', 'elevenlabs-tts' ),
            'audio_url' => $result,
        ) );
    }

    /**
     * AJAX handler for deleting audio
     */
    public function ajax_delete_audio() {
        check_ajax_referer( 'elevenlabs_tts_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'elevenlabs-tts' ) ) );
        }

        $audio_url = get_post_meta( $post_id, '_elevenlabs_audio_url', true );

        if ( $audio_url ) {
            $file_path = $this->get_validated_audio_path( $audio_url );
            if ( $file_path && file_exists( $file_path ) ) {
                wp_delete_file( $file_path );
            }
            delete_post_meta( $post_id, '_elevenlabs_audio_url' );
            delete_post_meta( $post_id, '_elevenlabs_audio_generated' );
            delete_post_meta( $post_id, '_elevenlabs_content_hash' );
        }

        wp_send_json_success( array( 'message' => __( 'Audio deleted successfully', 'elevenlabs-tts' ) ) );
    }

    /**
     * Add meta box to post editor
     */
    public function add_audio_meta_box() {
        add_meta_box(
            'elevenlabs_audio_meta_box',
            __( 'ElevenLabs Audio', 'elevenlabs-tts' ),
            array( $this, 'render_audio_meta_box' ),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post Current post object.
     */
    public function render_audio_meta_box( $post ) {
        $audio_url = get_post_meta( $post->ID, '_elevenlabs_audio_url', true );
        $file_path = $this->get_validated_audio_path( $audio_url );

        if ( $audio_url && $file_path && file_exists( $file_path ) ) {
            echo '<p><strong>' . esc_html__( 'Status:', 'elevenlabs-tts' ) . '</strong> ' . esc_html__( 'Audio generated', 'elevenlabs-tts' ) . '</p>';
            echo '<audio controls style="width:100%; margin: 10px 0;">';
            echo '<source src="' . esc_url( $audio_url ) . '" type="audio/mpeg">';
            echo '</audio>';
            echo '<button type="button" class="button elevenlabs-regenerate-btn" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Regenerate Audio', 'elevenlabs-tts' ) . '</button>';
            echo '<button type="button" class="button elevenlabs-delete-btn" data-post-id="' . esc_attr( $post->ID ) . '" style="margin-left:5px;">' . esc_html__( 'Delete Audio', 'elevenlabs-tts' ) . '</button>';
        } else {
            echo '<p><strong>' . esc_html__( 'Status:', 'elevenlabs-tts' ) . '</strong> ' . esc_html__( 'No audio generated', 'elevenlabs-tts' ) . '</p>';
            echo '<button type="button" class="button button-primary elevenlabs-generate-btn" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Generate Audio', 'elevenlabs-tts' ) . '</button>';
        }

        echo '<div class="elevenlabs-progress" style="display:none; margin-top:10px;">';
        echo '<span class="elevenlabs-status-text">' . esc_html__( 'Processing...', 'elevenlabs-tts' ) . '</span>';
        echo '</div>';
    }

    /**
     * Sanitize word timestamps for safe JSON output
     * Fixes smart/curly quotes and other characters that break JSON parsing
     *
     * @param string $timestamps_json JSON string of word timestamps.
     * @return string Sanitized JSON string.
     */
    private function sanitize_timestamps_for_output( $timestamps_json ) {
        // First, replace curly/smart quotes with straight quotes in the raw string
        $replacements = array(
            "\xE2\x80\x9C" => '"',  // Left double quotation mark "
            "\xE2\x80\x9D" => '"',  // Right double quotation mark "
            "\xE2\x80\x98" => "'",  // Left single quotation mark '
            "\xE2\x80\x99" => "'",  // Right single quotation mark '
            "\xE2\x80\x9E" => '"',  // Double low-9 quotation mark „
            "\xE2\x80\x9F" => '"',  // Double high-reversed-9 quotation mark ‟
            "\xE2\x80\x9A" => "'",  // Single low-9 quotation mark ‚
            "\xE2\x80\x9B" => "'",  // Single high-reversed-9 quotation mark ‛
        );
        $timestamps_json = strtr( $timestamps_json, $replacements );

        // Try to decode, sanitize word values, and re-encode to fix any broken JSON
        $data = json_decode( $timestamps_json, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            // Data decoded successfully, re-encode to ensure proper escaping
            return wp_json_encode( $data );
        }

        // If JSON is malformed, try regex-based fix for common issues
        // Match "word":" followed by anything up to ","start" or ","end"
        $fixed = preg_replace_callback(
            '/"word":"(.*?)","(start|end)"/',
            function( $matches ) {
                $word = $matches[1];
                $next_key = $matches[2];
                // Strip any leading/trailing quote characters from the word
                $word = trim( $word, '"\'' );
                // Also remove any internal unescaped quotes
                $word = str_replace( array( '"', "'" ), '', $word );
                return '"word":"' . $word . '","' . $next_key . '"';
            },
            $timestamps_json
        );

        return $fixed ? $fixed : $timestamps_json;
    }
}

/**
 * Initialize the plugin on plugins_loaded hook
 *
 * @return ElevenLabs_TTS
 */
function elevenlabs_tts_init() {
    return ElevenLabs_TTS::get_instance();
}

add_action( 'plugins_loaded', 'elevenlabs_tts_init' );
