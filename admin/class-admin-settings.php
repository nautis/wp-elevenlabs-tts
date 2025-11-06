<?php
/**
 * Admin Settings Class
 * Handles admin settings page and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabs_TTS_Admin_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_elevenlabs_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_elevenlabs_fetch_voices', array($this, 'ajax_fetch_voices'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'ElevenLabs Text-to-Speech Settings',
            'ElevenLabs TTS',
            'manage_options',
            'elevenlabs-tts',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('elevenlabs_tts_settings', 'elevenlabs_tts_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // API Settings Section
        add_settings_section(
            'elevenlabs_tts_api_section',
            'API Configuration',
            array($this, 'render_api_section'),
            'elevenlabs-tts'
        );

        add_settings_field(
            'api_key',
            'API Key',
            array($this, 'render_api_key_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_api_section'
        );

        // Voice Settings Section
        add_settings_section(
            'elevenlabs_tts_voice_section',
            'Voice Settings',
            array($this, 'render_voice_section'),
            'elevenlabs-tts'
        );

        add_settings_field(
            'voice_id',
            'Voice',
            array($this, 'render_voice_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_voice_section'
        );

        add_settings_field(
            'model_id',
            'Model',
            array($this, 'render_model_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_voice_section'
        );

        // Voice Parameters Section
        add_settings_section(
            'elevenlabs_tts_parameters_section',
            'Voice Parameters',
            array($this, 'render_parameters_section'),
            'elevenlabs-tts'
        );

        add_settings_field(
            'stability',
            'Stability',
            array($this, 'render_stability_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_parameters_section'
        );

        add_settings_field(
            'similarity_boost',
            'Clarity + Similarity',
            array($this, 'render_similarity_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_parameters_section'
        );

        add_settings_field(
            'style',
            'Style Exaggeration',
            array($this, 'render_style_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_parameters_section'
        );

        add_settings_field(
            'use_speaker_boost',
            'Speaker Boost',
            array($this, 'render_speaker_boost_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_parameters_section'
        );

        // Display Settings Section
        add_settings_section(
            'elevenlabs_tts_display_section',
            'Display Settings',
            array($this, 'render_display_section'),
            'elevenlabs-tts'
        );

        add_settings_field(
            'player_position',
            'Player Position',
            array($this, 'render_player_position_field'),
            'elevenlabs-tts',
            'elevenlabs_tts_display_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }

        if (isset($input['voice_id'])) {
            $sanitized['voice_id'] = sanitize_text_field($input['voice_id']);
        }

        if (isset($input['model_id'])) {
            $sanitized['model_id'] = sanitize_text_field($input['model_id']);
        }

        if (isset($input['stability'])) {
            $sanitized['stability'] = floatval($input['stability']);
        }

        if (isset($input['similarity_boost'])) {
            $sanitized['similarity_boost'] = floatval($input['similarity_boost']);
        }

        if (isset($input['style'])) {
            $sanitized['style'] = floatval($input['style']);
        }

        if (isset($input['use_speaker_boost'])) {
            $sanitized['use_speaker_boost'] = (bool)$input['use_speaker_boost'];
        }

        if (isset($input['player_position'])) {
            $sanitized['player_position'] = sanitize_text_field($input['player_position']);
        }

        return $sanitized;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_elevenlabs-tts' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'elevenlabs-tts-admin',
            ELEVENLABS_TTS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ELEVENLABS_TTS_VERSION
        );

        wp_enqueue_script(
            'elevenlabs-tts-admin',
            ELEVENLABS_TTS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ELEVENLABS_TTS_VERSION,
            true
        );

        wp_localize_script('elevenlabs-tts-admin', 'elevenlabsAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elevenlabs_admin_nonce')
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle messages
        if (isset($_GET['settings-updated'])) {
            add_settings_error('elevenlabs_tts_messages', 'elevenlabs_tts_message', 'Settings saved successfully', 'updated');
        }

        settings_errors('elevenlabs_tts_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields('elevenlabs_tts_settings');
                do_settings_sections('elevenlabs-tts');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>Configure your ElevenLabs API credentials. You can get your API key from <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank">ElevenLabs Dashboard</a>.</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        ?>
        <input type="password"
               name="elevenlabs_tts_settings[api_key]"
               id="elevenlabs_api_key"
               value="<?php echo esc_attr($api_key); ?>"
               class="regular-text"
               placeholder="sk_...">
        <button type="button" id="elevenlabs_test_connection" class="button">Test Connection</button>
        <button type="button" id="elevenlabs_fetch_voices" class="button">Fetch Voices</button>
        <p class="description">Enter your ElevenLabs API key</p>
        <div id="elevenlabs_connection_status"></div>
        <?php
    }

    /**
     * Render voice section
     */
    public function render_voice_section() {
        echo '<p>Select the voice and model to use for text-to-speech conversion.</p>';
    }

    /**
     * Render voice field
     */
    public function render_voice_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $voice_id = isset($settings['voice_id']) ? $settings['voice_id'] : '';
        ?>
        <select name="elevenlabs_tts_settings[voice_id]" id="elevenlabs_voice_id" class="regular-text">
            <option value="">Select a voice...</option>
            <?php if (!empty($voice_id)) : ?>
                <option value="<?php echo esc_attr($voice_id); ?>" selected>Current Voice</option>
            <?php endif; ?>
        </select>
        <p class="description">Click "Fetch Voices" to load available voices from your account</p>
        <div id="elevenlabs_voices_list"></div>
        <?php
    }

    /**
     * Render model field
     */
    public function render_model_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $model_id = isset($settings['model_id']) ? $settings['model_id'] : 'eleven_multilingual_v2';
        ?>
        <select name="elevenlabs_tts_settings[model_id]" id="elevenlabs_model_id" class="regular-text">
            <option value="eleven_multilingual_v2" <?php selected($model_id, 'eleven_multilingual_v2'); ?>>Eleven Multilingual v2 (Highest Quality)</option>
            <option value="eleven_turbo_v2_5" <?php selected($model_id, 'eleven_turbo_v2_5'); ?>>Eleven Turbo v2.5 (Fastest, Low Latency)</option>
            <option value="eleven_turbo_v2" <?php selected($model_id, 'eleven_turbo_v2'); ?>>Eleven Turbo v2</option>
            <option value="eleven_monolingual_v1" <?php selected($model_id, 'eleven_monolingual_v1'); ?>>Eleven Monolingual v1</option>
        </select>
        <p class="description">Choose the AI model for speech generation</p>
        <?php
    }

    /**
     * Render parameters section
     */
    public function render_parameters_section() {
        echo '<p>Fine-tune the voice characteristics. Hover over each parameter for more information.</p>';
    }

    /**
     * Render stability field
     */
    public function render_stability_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $stability = isset($settings['stability']) ? $settings['stability'] : 0.5;
        ?>
        <input type="range"
               name="elevenlabs_tts_settings[stability]"
               id="elevenlabs_stability"
               min="0"
               max="1"
               step="0.05"
               value="<?php echo esc_attr($stability); ?>">
        <span id="elevenlabs_stability_value"><?php echo esc_html($stability); ?></span>
        <p class="description">Higher values make the voice more consistent, lower values add more variation (Default: 0.5)</p>
        <?php
    }

    /**
     * Render similarity field
     */
    public function render_similarity_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $similarity = isset($settings['similarity_boost']) ? $settings['similarity_boost'] : 0.75;
        ?>
        <input type="range"
               name="elevenlabs_tts_settings[similarity_boost]"
               id="elevenlabs_similarity"
               min="0"
               max="1"
               step="0.05"
               value="<?php echo esc_attr($similarity); ?>">
        <span id="elevenlabs_similarity_value"><?php echo esc_html($similarity); ?></span>
        <p class="description">Enhances similarity to the original voice and improves clarity (Default: 0.75)</p>
        <?php
    }

    /**
     * Render style field
     */
    public function render_style_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $style = isset($settings['style']) ? $settings['style'] : 0.0;
        ?>
        <input type="range"
               name="elevenlabs_tts_settings[style]"
               id="elevenlabs_style"
               min="0"
               max="1"
               step="0.05"
               value="<?php echo esc_attr($style); ?>">
        <span id="elevenlabs_style_value"><?php echo esc_html($style); ?></span>
        <p class="description">Controls how expressive the voice is. Higher values add more emotion (Default: 0.0)</p>
        <?php
    }

    /**
     * Render speaker boost field
     */
    public function render_speaker_boost_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $speaker_boost = isset($settings['use_speaker_boost']) ? $settings['use_speaker_boost'] : true;
        ?>
        <label>
            <input type="checkbox"
                   name="elevenlabs_tts_settings[use_speaker_boost]"
                   id="elevenlabs_speaker_boost"
                   value="1"
                   <?php checked($speaker_boost, true); ?>>
            Enable speaker boost
        </label>
        <p class="description">Boosts similarity to the original speaker (recommended for most use cases)</p>
        <?php
    }

    /**
     * Render display section
     */
    public function render_display_section() {
        echo '<p>Configure how the audio player appears on your posts.</p>';
    }

    /**
     * Render player position field
     */
    public function render_player_position_field() {
        $settings = get_option('elevenlabs_tts_settings');
        $position = isset($settings['player_position']) ? $settings['player_position'] : 'before_content';
        ?>
        <select name="elevenlabs_tts_settings[player_position]" id="elevenlabs_player_position" class="regular-text">
            <option value="before_content" <?php selected($position, 'before_content'); ?>>Before Content</option>
            <option value="after_content" <?php selected($position, 'after_content'); ?>>After Content</option>
        </select>
        <p class="description">Position relative to the post content. Note: Title, featured image, author, and excerpt are controlled by your theme and appear before the content area.</p>
        <?php
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('elevenlabs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required'));
        }

        $api = new ElevenLabs_TTS_API();
        $api->set_api_key($api_key);
        $result = $api->test_connection();

        if ($result['success']) {
            wp_send_json_success(array('message' => 'Connection successful!'));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Fetch available voices
     */
    public function ajax_fetch_voices() {
        check_ajax_referer('elevenlabs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required'));
        }

        $api = new ElevenLabs_TTS_API();
        $api->set_api_key($api_key);
        $voices = $api->get_voices();

        if (is_wp_error($voices)) {
            wp_send_json_error(array('message' => $voices->get_error_message()));
        }

        wp_send_json_success(array('voices' => $voices));
    }
}
