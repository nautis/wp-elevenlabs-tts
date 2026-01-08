/**
 * ElevenLabs TTS Metabox JavaScript
 *
 * @package ElevenLabs_TTS
 * @since 1.1.0
 */

(function($) {
    'use strict';

    $(function() {
        var postId = elevenlabsMetabox.postId;
        var nonce = elevenlabsMetabox.nonce;
        var ajaxurl = elevenlabsMetabox.ajaxurl;

        // Handle generate/regenerate button clicks
        $('.elevenlabs-generate-btn, .elevenlabs-regenerate-btn').on('click', function() {
            var $button = $(this);
            var $progress = $('.elevenlabs-progress');
            var isRegenerate = $button.hasClass('elevenlabs-regenerate-btn');

            if (isRegenerate && !confirm('Are you sure you want to regenerate the audio?')) {
                return;
            }

            $button.prop('disabled', true);
            $progress.show();

            $.post(ajaxurl, {
                action: 'elevenlabs_generate_audio',
                post_id: postId,
                force: isRegenerate,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    $progress.hide();
                    $button.prop('disabled', false);
                }
            }).fail(function() {
                alert('Error: Request failed');
                $progress.hide();
                $button.prop('disabled', false);
            });
        });

        // Handle delete button click
        $('.elevenlabs-delete-btn').on('click', function() {
            if (!confirm('Are you sure you want to delete this audio file?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'elevenlabs_delete_audio',
                post_id: postId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    $button.prop('disabled', false);
                }
            }).fail(function() {
                alert('Error: Request failed');
                $button.prop('disabled', false);
            });
        });
    });

})(jQuery);
