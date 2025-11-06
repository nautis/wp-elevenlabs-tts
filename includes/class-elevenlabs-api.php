<?php
/**
 * ElevenLabs API Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabs_TTS_API {

    private $api_key;
    private $base_url = 'https://api.elevenlabs.io/v1';

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('elevenlabs_tts_settings');
        $this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
    }

    /**
     * Set API key
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Get available voices
     */
    public function get_voices() {
        $response = $this->make_request('/voices', 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['voices']) ? $response['voices'] : array();
    }

    /**
     * Convert text to speech
     *
     * @param string $text The text to convert
     * @param string $voice_id The voice ID to use
     * @param array $options Additional options
     * @return string|WP_Error Audio content or error
     */
    public function text_to_speech($text, $voice_id, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'ElevenLabs API key is not configured');
        }

        if (empty($voice_id)) {
            return new WP_Error('no_voice_id', 'Voice ID is required');
        }

        // Default options
        $defaults = array(
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => array(
                'stability' => 0.5,
                'similarity_boost' => 0.75,
                'style' => 0.0,
                'use_speaker_boost' => true
            ),
            'output_format' => 'mp3_44100_128',
            'pronunciation_dictionary_locators' => array()
        );

        $options = wp_parse_args($options, $defaults);

        // Prepare request body
        $body = array(
            'text' => $text,
            'model_id' => $options['model_id'],
            'voice_settings' => $options['voice_settings']
        );

        // Add pronunciation dictionary if provided
        if (!empty($options['pronunciation_dictionary_locators'])) {
            $body['pronunciation_dictionary_locators'] = $options['pronunciation_dictionary_locators'];
        }

        // Make request
        $endpoint = "/text-to-speech/{$voice_id}";

        // Add output format as query parameter
        $endpoint .= '?output_format=' . $options['output_format'];

        // Use longer timeout for TTS requests (they can take a while for long text)
        $response = $this->make_request($endpoint, 'POST', $body, true, 180);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Upload pronunciation dictionary from PLS file
     *
     * @param string $file_path Path to PLS file
     * @param string $name Dictionary name
     * @return array|WP_Error Dictionary data or error
     */
    public function upload_pronunciation_dictionary($file_path, $name) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'ElevenLabs API key is not configured');
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'PLS file not found: ' . $file_path);
        }

        $url = $this->base_url . '/pronunciation-dictionaries/add-from-file';

        // Read file content
        $file_content = file_get_contents($file_path);
        $boundary = wp_generate_password(24);

        // Build multipart form data
        $body = "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="name"' . "\r\n\r\n";
        $body .= $name . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n";
        $body .= 'Content-Type: application/pls+xml' . "\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'xi-api-key' => $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary
            ),
            'body' => $body,
            'timeout' => 30
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['detail']['message'])
                ? $error_data['detail']['message']
                : (isset($error_data['detail']) && is_string($error_data['detail'])
                    ? $error_data['detail']
                    : 'Failed to upload dictionary with status ' . $status_code);

            return new WP_Error('upload_failed', $error_message, array('status' => $status_code));
        }

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to decode API response');
        }

        return $data;
    }

    /**
     * Get list of pronunciation dictionaries
     *
     * @return array|WP_Error List of dictionaries or error
     */
    public function get_pronunciation_dictionaries() {
        return $this->make_request('/pronunciation-dictionaries', 'GET');
    }

    /**
     * Get pronunciation dictionary by ID
     *
     * @param string $dictionary_id Dictionary ID
     * @return array|WP_Error Dictionary data or error
     */
    public function get_pronunciation_dictionary($dictionary_id) {
        return $this->make_request("/pronunciation-dictionaries/{$dictionary_id}", 'GET');
    }

    /**
     * Delete pronunciation dictionary
     *
     * @param string $dictionary_id Dictionary ID
     * @return array|WP_Error Response or error
     */
    public function delete_pronunciation_dictionary($dictionary_id) {
        return $this->make_request("/pronunciation-dictionaries/{$dictionary_id}", 'DELETE');
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $body Request body
     * @param bool $return_raw Return raw response body
     * @param int $timeout Timeout in seconds (default 60)
     * @return array|string|WP_Error Response data or error
     */
    private function make_request($endpoint, $method = 'GET', $body = null, $return_raw = false, $timeout = 60) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'ElevenLabs API key is not configured');
        }

        $url = $this->base_url . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'xi-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => $timeout
        );

        if ($body !== null && $method !== 'GET') {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle errors
        if ($status_code < 200 || $status_code >= 300) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['detail']['message'])
                ? $error_data['detail']['message']
                : (isset($error_data['detail']) && is_string($error_data['detail'])
                    ? $error_data['detail']
                    : 'API request failed with status ' . $status_code);

            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        // Return raw body for audio data
        if ($return_raw) {
            return $body;
        }

        // Decode JSON response
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to decode API response');
        }

        return $data;
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        $response = $this->make_request('/user', 'GET');

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        return array(
            'success' => true,
            'message' => 'Connection successful',
            'data' => $response
        );
    }

    /**
     * Get user subscription info
     */
    public function get_subscription_info() {
        $response = $this->make_request('/user/subscription', 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Get available models
     */
    public function get_models() {
        $response = $this->make_request('/models', 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }
}
