/**
 * ElevenLabs TTS Player JavaScript
 *
 * @package ElevenLabs_TTS
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Helper function to show messages (XSS-safe)
     *
     * @param {jQuery} $container Container element
     * @param {string} message Message text
     * @param {string} type Message type ('success' or 'error')
     */
    function showMessage($container, message, type) {
        var messageClass = type === 'success' ? 'elevenlabs-success' : 'elevenlabs-error';

        // Use .text() instead of HTML to prevent XSS
        var $message = $('<div></div>')
            .addClass(messageClass)
            .text(message);

        // Remove any existing messages
        $container.find('.elevenlabs-success, .elevenlabs-error').remove();

        // Add new message
        $container.append($message);

        // Auto-remove error messages after 5 seconds
        if (type === 'error') {
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    /**
     * Initialize on DOM ready
     */
    $(function() {

        // Handle generate button click
        $('.elevenlabs-generate-btn').on('click', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var $container = $button.closest('.elevenlabs-generate-container, .elevenlabs-player');
            var $progress = $container.find('.elevenlabs-progress');

            // Disable button and show progress
            $button.prop('disabled', true).text('Generating...');
            $progress.show();

            // Make AJAX request
            $.ajax({
                url: elevenlabsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elevenlabs_generate_audio',
                    post_id: postId,
                    nonce: elevenlabsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showMessage($container, 'Audio generated successfully! Reloading page...', 'success');

                        // Reload page after 1 second with cache busting
                        setTimeout(function() {
                            location.href = location.href.split('?')[0] + '?t=' + Date.now();
                        }, 1000);
                    } else {
                        // Show error message (safely escaped by showMessage)
                        var errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Failed to generate audio';
                        showMessage($container, errorMsg, 'error');

                        // Re-enable button
                        $button.prop('disabled', false).text('Generate Audio');
                        $progress.hide();
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message (safely escaped by showMessage)
                    showMessage($container, 'An error occurred: ' + error, 'error');

                    // Re-enable button
                    $button.prop('disabled', false).text('Generate Audio');
                    $progress.hide();
                }
            });
        });

        // Handle regenerate button click
        $('.elevenlabs-regenerate-btn').on('click', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var $container = $button.closest('.elevenlabs-player');

            if (!confirm('Are you sure you want to regenerate the audio? This will replace the existing audio file.')) {
                return;
            }

            // Find or create progress container
            var $progress = $container.find('.elevenlabs-progress');
            if ($progress.length === 0) {
                $progress = $('<div class="elevenlabs-progress"></div>')
                    .append($('<span class="elevenlabs-spinner"></span>'))
                    .append($('<span class="elevenlabs-status-text"></span>').text('Generating audio...'));
                $container.append($progress);
            }

            // Disable button and show progress
            $button.prop('disabled', true).text('Regenerating...');
            $progress.show();

            // Make AJAX request
            $.ajax({
                url: elevenlabsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elevenlabs_generate_audio',
                    post_id: postId,
                    force: true,
                    nonce: elevenlabsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showMessage($container, 'Audio regenerated successfully! Reloading page...', 'success');

                        // Reload page after 1 second with cache busting
                        setTimeout(function() {
                            location.href = location.href.split('?')[0] + '?t=' + Date.now();
                        }, 1000);
                    } else {
                        // Show error message (safely escaped by showMessage)
                        var errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Failed to regenerate audio';
                        showMessage($container, errorMsg, 'error');

                        // Re-enable button
                        $button.prop('disabled', false).text('Regenerate');
                        $progress.hide();
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message (safely escaped by showMessage)
                    showMessage($container, 'An error occurred: ' + error, 'error');

                    // Re-enable button
                    $button.prop('disabled', false).text('Regenerate');
                    $progress.hide();
                }
            });
        });

        // Add keyboard accessibility
        $('.elevenlabs-generate-btn, .elevenlabs-regenerate-btn').on('keypress', function(e) {
            if (e.which === 13 || e.which === 32) { // Enter or Space
                e.preventDefault();
                $(this).click();
            }
        });
    });

})(jQuery);
