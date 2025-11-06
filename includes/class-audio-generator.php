<?php
/**
 * Audio Generator Class
 * Handles audio generation and file management
 */

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabs_TTS_Audio_Generator {

    private $api;
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ElevenLabs_TTS_API();
        $this->settings = get_option('elevenlabs_tts_settings', array());
    }

    /**
     * Generate audio for a post
     *
     * @param int $post_id Post ID
     * @param bool $force Force regeneration even if audio exists
     * @return string|WP_Error Audio URL or error
     */
    public function generate_audio_for_post($post_id, $force = false) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post ID');
        }

        // Check if audio already exists
        $existing_audio = get_post_meta($post_id, '_elevenlabs_audio_url', true);
        if (!$force && !empty($existing_audio)) {
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $existing_audio);
            if (file_exists($file_path)) {
                return $existing_audio;
            }
        }

        // Get filtered content
        $content = ElevenLabs_TTS_Content_Filter::get_post_content_for_tts($post);

        if (is_wp_error($content)) {
            return $content;
        }

        if (empty($content)) {
            return new WP_Error('empty_content', 'Post content is empty');
        }

        // Get voice ID from settings
        $voice_id = isset($this->settings['voice_id']) ? $this->settings['voice_id'] : '';

        if (empty($voice_id)) {
            return new WP_Error('no_voice_id', 'No voice selected. Please configure the plugin settings.');
        }

        // Get model ID
        $model_id = isset($this->settings['model_id']) ? $this->settings['model_id'] : 'eleven_multilingual_v2';

        // Get voice settings
        $voice_settings = array(
            'stability' => isset($this->settings['stability']) ? floatval($this->settings['stability']) : 0.5,
            'similarity_boost' => isset($this->settings['similarity_boost']) ? floatval($this->settings['similarity_boost']) : 0.75,
            'style' => isset($this->settings['style']) ? floatval($this->settings['style']) : 0.0,
            'use_speaker_boost' => isset($this->settings['use_speaker_boost']) ? (bool)$this->settings['use_speaker_boost'] : true
        );

        // Prepare options
        $options = array(
            'model_id' => $model_id,
            'voice_settings' => $voice_settings,
            'output_format' => 'mp3_44100_128'
        );

        // Log the generation attempt
        error_log("ElevenLabs TTS: Generating audio for post {$post_id}");
        error_log("Content length: " . strlen($content) . " characters");

        // Generate audio
        $audio_data = $this->api->text_to_speech($content, $voice_id, $options);

        if (is_wp_error($audio_data)) {
            error_log("ElevenLabs TTS Error: " . $audio_data->get_error_message());
            return $audio_data;
        }

        // Save audio file
        $file_url = $this->save_audio_file($audio_data, $post_id);

        if (is_wp_error($file_url)) {
            return $file_url;
        }

        // Delete old audio file if it exists
        if (!empty($existing_audio) && $existing_audio !== $file_url) {
            $old_file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $existing_audio);
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }

        // Save audio URL to post meta
        update_post_meta($post_id, '_elevenlabs_audio_url', $file_url);

        // Save generation metadata
        update_post_meta($post_id, '_elevenlabs_audio_generated', current_time('mysql'));
        update_post_meta($post_id, '_elevenlabs_content_hash', md5($content));
        update_post_meta($post_id, '_elevenlabs_character_count', strlen($content));

        // Estimate and save duration
        $estimated_duration = ElevenLabs_TTS_Content_Filter::estimate_duration($content);
        update_post_meta($post_id, '_elevenlabs_estimated_duration', $estimated_duration);

        error_log("ElevenLabs TTS: Audio generated successfully for post {$post_id}");

        return $file_url;
    }

    /**
     * Save audio file to uploads directory
     *
     * @param string $audio_data Raw audio data
     * @param int $post_id Post ID
     * @return string|WP_Error File URL or error
     */
    private function save_audio_file($audio_data, $post_id) {
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $audio_dir = $upload_dir['basedir'] . '/elevenlabs-audio';

        // Create directory if it doesn't exist
        if (!file_exists($audio_dir)) {
            wp_mkdir_p($audio_dir);
        }

        // Generate filename
        $filename = 'post-' . $post_id . '-' . time() . '.mp3';
        $file_path = $audio_dir . '/' . $filename;

        // Write file
        $result = file_put_contents($file_path, $audio_data);

        if ($result === false) {
            return new WP_Error('file_write_error', 'Failed to write audio file');
        }

        // Generate URL
        $file_url = $upload_dir['baseurl'] . '/elevenlabs-audio/' . $filename;

        return $file_url;
    }

    /**
     * Delete audio for a post
     *
     * @param int $post_id Post ID
     * @return bool Success
     */
    public function delete_audio_for_post($post_id) {
        $audio_url = get_post_meta($post_id, '_elevenlabs_audio_url', true);

        if (empty($audio_url)) {
            return true;
        }

        // Delete file
        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $audio_url);
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete metadata
        delete_post_meta($post_id, '_elevenlabs_audio_url');
        delete_post_meta($post_id, '_elevenlabs_audio_generated');
        delete_post_meta($post_id, '_elevenlabs_content_hash');
        delete_post_meta($post_id, '_elevenlabs_character_count');
        delete_post_meta($post_id, '_elevenlabs_estimated_duration');

        return true;
    }

    /**
     * Check if post content has changed since audio generation
     *
     * @param int $post_id Post ID
     * @return bool True if content has changed
     */
    public function has_content_changed($post_id) {
        $stored_hash = get_post_meta($post_id, '_elevenlabs_content_hash', true);

        if (empty($stored_hash)) {
            return true;
        }

        $current_content = ElevenLabs_TTS_Content_Filter::get_post_content_for_tts($post_id);

        if (is_wp_error($current_content)) {
            return true;
        }

        $current_hash = md5($current_content);

        return $stored_hash !== $current_hash;
    }

    /**
     * Get audio info for a post
     *
     * @param int $post_id Post ID
     * @return array|null Audio info or null
     */
    public function get_audio_info($post_id) {
        $audio_url = get_post_meta($post_id, '_elevenlabs_audio_url', true);

        if (empty($audio_url)) {
            return null;
        }

        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $audio_url);

        if (!file_exists($file_path)) {
            return null;
        }

        return array(
            'url' => $audio_url,
            'file_path' => $file_path,
            'file_size' => filesize($file_path),
            'generated' => get_post_meta($post_id, '_elevenlabs_audio_generated', true),
            'character_count' => get_post_meta($post_id, '_elevenlabs_character_count', true),
            'estimated_duration' => get_post_meta($post_id, '_elevenlabs_estimated_duration', true),
            'content_changed' => $this->has_content_changed($post_id)
        );
    }

    /**
     * Batch generate audio for multiple posts
     *
     * @param array $post_ids Array of post IDs
     * @return array Results
     */
    public function batch_generate($post_ids) {
        $results = array(
            'success' => array(),
            'failed' => array()
        );

        foreach ($post_ids as $post_id) {
            $result = $this->generate_audio_for_post($post_id);

            if (is_wp_error($result)) {
                $results['failed'][$post_id] = $result->get_error_message();
            } else {
                $results['success'][$post_id] = $result;
            }

            // Small delay to avoid rate limiting
            sleep(1);
        }

        return $results;
    }
}
