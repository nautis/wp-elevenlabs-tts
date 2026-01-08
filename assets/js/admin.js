/**
 * ElevenLabs TTS Admin JavaScript
 *
 * @package ElevenLabs_TTS
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Escape HTML entities for safe display
     *
     * @param {string} text Text to escape
     * @return {string} Escaped text
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(function() {

        // Update slider value displays in real-time
        $('#elevenlabs_stability').on('input', function() {
            $('#elevenlabs_stability_value').text($(this).val());
        });

        $('#elevenlabs_similarity').on('input', function() {
            $('#elevenlabs_similarity_value').text($(this).val());
        });

        $('#elevenlabs_style').on('input', function() {
            $('#elevenlabs_style_value').text($(this).val());
        });

        // Test API connection
        $('#elevenlabs_test_connection').on('click', function() {
            var $button = $(this);
            var $status = $('#elevenlabs_connection_status');
            var apiKey = $('#elevenlabs_api_key').val();

            // Check for existing saved key if field is empty
            var hasExistingKey = $('#elevenlabs_api_key').data('has-key') === '1';

            if (!apiKey && !hasExistingKey) {
                $status.removeClass('success').addClass('error').text('Please enter an API key');
                return;
            }

            // Show loading state
            $button.prop('disabled', true).addClass('loading');
            $status.removeClass('success error').text('Testing connection...');

            // Make AJAX request
            $.ajax({
                url: elevenlabsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elevenlabs_test_connection',
                    api_key: apiKey,
                    nonce: elevenlabsAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('loading');

                    if (response.success) {
                        $status.removeClass('error').addClass('success').text(response.data.message);
                    } else {
                        // Use .text() to prevent XSS
                        $status.removeClass('success').addClass('error').text('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).removeClass('loading');
                    // Use .text() to prevent XSS
                    $status.removeClass('success').addClass('error').text('Connection failed: ' + error);
                }
            });
        });

        // Fetch available voices
        $('#elevenlabs_fetch_voices').on('click', function() {
            var $button = $(this);
            var $voicesList = $('#elevenlabs_voices_list');
            var $voiceSelect = $('#elevenlabs_voice_id');
            var apiKey = $('#elevenlabs_api_key').val();

            // Check for existing saved key if field is empty
            var hasExistingKey = $('#elevenlabs_api_key').data('has-key') === '1';

            if (!apiKey && !hasExistingKey) {
                alert('Please enter an API key first');
                return;
            }

            // Show loading state
            $button.prop('disabled', true).addClass('loading');
            $voicesList.empty().append($('<p></p>').text('Loading voices...')).addClass('loaded');

            // Make AJAX request
            $.ajax({
                url: elevenlabsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elevenlabs_fetch_voices',
                    api_key: apiKey,
                    nonce: elevenlabsAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('loading');

                    if (response.success && response.data.voices) {
                        var voices = response.data.voices;
                        var currentVoiceId = $voiceSelect.val();

                        // Clear existing options except the first one
                        $voiceSelect.find('option:not(:first)').remove();

                        // Clear voices list
                        $voicesList.empty();

                        if (voices.length === 0) {
                            $voicesList.append($('<p></p>').text('No voices found in your account.'));
                            return;
                        }

                        // Add voices to both select and visual list
                        voices.forEach(function(voice) {
                            // Add to select dropdown (using text() for safety)
                            var $option = $('<option></option>')
                                .val(voice.voice_id)
                                .text(voice.name);

                            if (voice.voice_id === currentVoiceId) {
                                $option.prop('selected', true);
                            }

                            $voiceSelect.append($option);

                            // Add to visual list
                            var labels = [];
                            if (voice.labels) {
                                for (var key in voice.labels) {
                                    if (voice.labels.hasOwnProperty(key)) {
                                        labels.push(key + ': ' + voice.labels[key]);
                                    }
                                }
                            }

                            var $voiceItem = $('<div class="elevenlabs-voice-item"></div>')
                                .attr('data-voice-id', voice.voice_id);

                            if (voice.voice_id === currentVoiceId) {
                                $voiceItem.addClass('selected');
                            }

                            // Use .text() for all user-provided content
                            var $voiceName = $('<span class="elevenlabs-voice-name"></span>')
                                .text(voice.name);

                            var $voiceDescription = $('<span class="elevenlabs-voice-description"></span>')
                                .text(voice.description || 'No description available');

                            $voiceItem.append($voiceName).append($voiceDescription);

                            if (labels.length > 0) {
                                var $labelsContainer = $('<div class="elevenlabs-voice-labels"></div>');
                                labels.forEach(function(label) {
                                    // Use .text() for label content
                                    $labelsContainer.append(
                                        $('<span class="elevenlabs-voice-label"></span>').text(label)
                                    );
                                });
                                $voiceItem.append($labelsContainer);
                            }

                            $voicesList.append($voiceItem);
                        });

                        // Add click handler for voice items
                        $('.elevenlabs-voice-item').on('click', function() {
                            var voiceId = $(this).data('voice-id');

                            // Update visual selection
                            $('.elevenlabs-voice-item').removeClass('selected');
                            $(this).addClass('selected');

                            // Update select dropdown
                            $voiceSelect.val(voiceId);
                        });

                    } else {
                        var errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Failed to fetch voices';
                        // Use .text() to prevent XSS
                        $voicesList.empty().append(
                            $('<p class="elevenlabs-error-box"></p>').text('Error: ' + errorMsg)
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).removeClass('loading');
                    // Use .text() to prevent XSS
                    $voicesList.empty().append(
                        $('<p class="elevenlabs-error-box"></p>').text('Failed to fetch voices: ' + error)
                    );
                }
            });
        });

        // Update visual list selection when dropdown changes
        $('#elevenlabs_voice_id').on('change', function() {
            var selectedVoiceId = $(this).val();

            $('.elevenlabs-voice-item').removeClass('selected');
            $('.elevenlabs-voice-item[data-voice-id="' + selectedVoiceId + '"]').addClass('selected');
        });

        // Add info boxes to settings page
        if ($('.elevenlabs-info-box').length === 0) {
            // Add info box after API section
            var $apiSection = $('#elevenlabs_tts_api_section').next('table');
            if ($apiSection.length) {
                $apiSection.after(
                    '<div class="elevenlabs-info-box">' +
                    '<p><strong>Getting Started:</strong> Enter your API key above, click "Test Connection" to verify it works, then "Fetch Voices" to load your available voices.</p>' +
                    '</div>'
                );
            }
        }
    });

})(jQuery);
