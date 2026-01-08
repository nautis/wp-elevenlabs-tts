<?php
/**
 * ElevenLabs API Integration Class
 *
 * @package ElevenLabs_TTS
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ElevenLabs TTS API class
 */
class ElevenLabs_TTS_API {

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * API base URL
     *
     * @var string
     */
    private $base_url = 'https://api.elevenlabs.io/v1';

    /**
     * Cache duration for voices/models (1 hour)
     *
     * @var int
     */
    const CACHE_DURATION = HOUR_IN_SECONDS;

    /**
     * Constructor
     */
    public function __construct() {
        $settings      = get_option( 'elevenlabs_tts_settings' );
        $this->api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
    }

    /**
     * Set API key
     *
     * @param string $api_key API key.
     */
    public function set_api_key( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * Get cache key for current API key
     *
     * @param string $type Cache type (voices, models, etc.).
     * @return string Cache key.
     */
    private function get_cache_key( $type ) {
        return 'elevenlabs_tts_' . $type . '_' . md5( $this->api_key );
    }

    /**
     * Get available voices (with caching)
     *
     * @param bool $force_refresh Force refresh from API.
     * @return array|WP_Error Voices array or error.
     */
    public function get_voices( $force_refresh = false ) {
        $cache_key = $this->get_cache_key( 'voices' );

        // Check cache first (unless force refresh).
        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $response = $this->make_request( '/voices', 'GET' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $voices = isset( $response['voices'] ) ? $response['voices'] : array();

        // Cache for 1 hour.
        set_transient( $cache_key, $voices, self::CACHE_DURATION );

        return $voices;
    }

    /**
     * Get available models (with caching)
     *
     * @param bool $force_refresh Force refresh from API.
     * @return array|WP_Error Models array or error.
     */
    public function get_models( $force_refresh = false ) {
        $cache_key = $this->get_cache_key( 'models' );

        // Check cache first (unless force refresh).
        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $response = $this->make_request( '/models', 'GET' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Cache for 1 hour.
        set_transient( $cache_key, $response, self::CACHE_DURATION );

        return $response;
    }

    /**
     * Clear cached voices and models
     */
    public function clear_cache() {
        delete_transient( $this->get_cache_key( 'voices' ) );
        delete_transient( $this->get_cache_key( 'models' ) );
    }

    /**
     * Convert text to speech
     *
     * @param string $text     The text to convert.
     * @param string $voice_id The voice ID to use.
     * @param array  $options  Additional options.
     * @return string|WP_Error Audio content or error.
     */
    public function text_to_speech( $text, $voice_id, $options = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'ElevenLabs API key is not configured', 'elevenlabs-tts' ) );
        }

        if ( empty( $voice_id ) ) {
            return new WP_Error( 'no_voice_id', __( 'Voice ID is required', 'elevenlabs-tts' ) );
        }

        // Default options.
        $defaults = array(
            'model_id'                          => 'eleven_multilingual_v2',
            'voice_settings'                    => array(
                'stability'        => 0.5,
                'similarity_boost' => 0.75,
                'style'            => 0.0,
                'use_speaker_boost' => true,
            ),
            'output_format'                     => 'mp3_44100_128',
            'pronunciation_dictionary_locators' => array(),
        );

        $options = wp_parse_args( $options, $defaults );

        // Prepare request body.
        $body = array(
            'text'           => $text,
            'model_id'       => $options['model_id'],
            'voice_settings' => $options['voice_settings'],
        );

        // Add pronunciation dictionary if provided.
        if ( ! empty( $options['pronunciation_dictionary_locators'] ) ) {
            $body['pronunciation_dictionary_locators'] = $options['pronunciation_dictionary_locators'];
        }

        // Make request.
        $endpoint = "/text-to-speech/{$voice_id}";

        // Add output format as query parameter.
        $endpoint .= '?output_format=' . $options['output_format'];

        // Use longer timeout for TTS requests (they can take a while for long text).
        $response = $this->make_request( $endpoint, 'POST', $body, true, 180 );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $response;
    }

    /**
     * Upload pronunciation dictionary from PLS file
     *
     * @param string $file_path Path to PLS file.
     * @param string $name      Dictionary name.
     * @return array|WP_Error Dictionary data or error.
     */
    public function upload_pronunciation_dictionary( $file_path, $name ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'ElevenLabs API key is not configured', 'elevenlabs-tts' ) );
        }

        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'PLS file not found', 'elevenlabs-tts' ) . ': ' . $file_path );
        }

        $url = $this->base_url . '/pronunciation-dictionaries/add-from-file';

        // Read file content.
        $file_content = file_get_contents( $file_path );
        $boundary     = wp_generate_password( 24, false );

        // Build multipart form data.
        $body  = "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="name"' . "\r\n\r\n";
        $body .= $name . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file_path ) . '"' . "\r\n";
        $body .= 'Content-Type: application/pls+xml' . "\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'xi-api-key'   => $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body'    => $body,
            'timeout' => 30,
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code   = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_data    = json_decode( $response_body, true );
            $error_message = isset( $error_data['detail']['message'] )
                ? $error_data['detail']['message']
                : ( isset( $error_data['detail'] ) && is_string( $error_data['detail'] )
                    ? $error_data['detail']
                    : sprintf( __( 'Failed to upload dictionary with status %d', 'elevenlabs-tts' ), $status_code ) );

            return new WP_Error( 'upload_failed', $error_message, array( 'status' => $status_code ) );
        }

        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Failed to decode API response', 'elevenlabs-tts' ) );
        }

        return $data;
    }

    /**
     * Get list of pronunciation dictionaries
     *
     * @return array|WP_Error List of dictionaries or error.
     */
    public function get_pronunciation_dictionaries() {
        return $this->make_request( '/pronunciation-dictionaries', 'GET' );
    }

    /**
     * Get pronunciation dictionary by ID
     *
     * @param string $dictionary_id Dictionary ID.
     * @return array|WP_Error Dictionary data or error.
     */
    public function get_pronunciation_dictionary( $dictionary_id ) {
        return $this->make_request( "/pronunciation-dictionaries/{$dictionary_id}", 'GET' );
    }

    /**
     * Delete pronunciation dictionary
     *
     * @param string $dictionary_id Dictionary ID.
     * @return array|WP_Error Response or error.
     */
    public function delete_pronunciation_dictionary( $dictionary_id ) {
        return $this->make_request( "/pronunciation-dictionaries/{$dictionary_id}", 'DELETE' );
    }

    /**
     * Make API request
     *
     * @param string $endpoint   API endpoint.
     * @param string $method     HTTP method.
     * @param array  $body       Request body.
     * @param bool   $return_raw Return raw response body.
     * @param int    $timeout    Timeout in seconds (default 60).
     * @return array|string|WP_Error Response data or error.
     */
    private function make_request( $endpoint, $method = 'GET', $body = null, $return_raw = false, $timeout = 60 ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'ElevenLabs API key is not configured', 'elevenlabs-tts' ) );
        }

        $url = $this->base_url . $endpoint;

        $args = array(
            'method'  => $method,
            'headers' => array(
                'xi-api-key'   => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => $timeout,
        );

        if ( null !== $body && 'GET' !== $method ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code  = wp_remote_retrieve_response_code( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $body         = wp_remote_retrieve_body( $response );

        // Handle errors.
        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_data    = json_decode( $body, true );
            $error_message = isset( $error_data['detail']['message'] )
                ? $error_data['detail']['message']
                : ( isset( $error_data['detail'] ) && is_string( $error_data['detail'] )
                    ? $error_data['detail']
                    : sprintf( __( 'API request failed with status %d', 'elevenlabs-tts' ), $status_code ) );

            return new WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
        }

        // Return raw body for audio data.
        if ( $return_raw ) {
            // Validate we got audio content.
            if ( strpos( $content_type, 'audio/' ) === false && strpos( $content_type, 'application/octet-stream' ) === false ) {
                return new WP_Error( 'invalid_response', sprintf( __( 'Expected audio response, got: %s', 'elevenlabs-tts' ), $content_type ) );
            }
            return $body;
        }

        // Decode JSON response.
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Failed to decode API response', 'elevenlabs-tts' ) );
        }

        return $data;
    }

    /**
     * Test API connection
     *
     * @return array Test result with success status and message.
     */
    public function test_connection() {
        $response = $this->make_request( '/user', 'GET' );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Connection successful', 'elevenlabs-tts' ),
            'data'    => $response,
        );
    }

    /**
     * Get user subscription info
     *
     * @return array|WP_Error Subscription info or error.
     */
    public function get_subscription_info() {
        $response = $this->make_request( '/user/subscription', 'GET' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $response;
    }
}
