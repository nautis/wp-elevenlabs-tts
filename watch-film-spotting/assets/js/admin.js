/**
 * WatchSpotting - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Select all checkbox in moderation queue
    $('#cb-select-all').on('change', function() {
        $('input[name="comment_ids[]"]').prop('checked', this.checked);
    });
    
})(jQuery);
