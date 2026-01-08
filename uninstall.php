<?php
/**
 * ElevenLabs TTS Plugin Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package ElevenLabs_TTS
 * @since 1.1.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 */
function elevenlabs_tts_uninstall() {
    // Delete plugin options.
    delete_option( 'elevenlabs_tts_settings' );
    delete_option( 'elevenlabs_pronunciation_dictionary_id' );
    delete_option( 'elevenlabs_pronunciation_dictionary_version' );

    // Delete all transients.
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_elevenlabs_tts_%',
            '_transient_timeout_elevenlabs_tts_%'
        )
    );

    // Delete post meta for all posts.
    delete_post_meta_by_key( '_elevenlabs_audio_url' );
    delete_post_meta_by_key( '_elevenlabs_audio_generated' );
    delete_post_meta_by_key( '_elevenlabs_content_hash' );
    delete_post_meta_by_key( '_elevenlabs_character_count' );
    delete_post_meta_by_key( '_elevenlabs_estimated_duration' );

    // Delete audio files and directories.
    $upload_dir = wp_upload_dir();
    $audio_dir  = $upload_dir['basedir'] . '/elevenlabs-audio';

    if ( is_dir( $audio_dir ) ) {
        elevenlabs_tts_delete_directory( $audio_dir );
    }
}

/**
 * Recursively delete a directory and its contents
 *
 * @param string $dir Directory path.
 * @return bool Success status.
 */
function elevenlabs_tts_delete_directory( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return false;
    }

    $files = array_diff( scandir( $dir ), array( '.', '..' ) );

    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        if ( is_dir( $path ) ) {
            elevenlabs_tts_delete_directory( $path );
        } else {
            wp_delete_file( $path );
        }
    }

    return rmdir( $dir );
}

// Run uninstall.
elevenlabs_tts_uninstall();
