/**
 * WatchSpotting - Public JavaScript
 */

(function($) {
    'use strict';
    
    // Voting functionality
    function initVoting() {
        $('.ws-voting').on('click', '.ws-vote-btn', function(e) {
            e.preventDefault();
            
            if (!wsData.isLoggedIn) {
                alert('Please log in to vote.');
                return;
            }
            
            var $btn = $(this);
            var $voting = $btn.closest('.ws-voting');
            var fawId = $voting.data('faw-id');
            var vote = parseInt($btn.data('vote'));
            var currentVote = $voting.find('.ws-vote-btn.active').data('vote');
            
            // If clicking the same vote, remove it
            if ($btn.hasClass('active')) {
                removeVote(fawId, $voting);
            } else {
                castVote(fawId, vote, $voting);
            }
        });
    }
    
    function castVote(fawId, vote, $voting) {
        $.ajax({
            url: wsData.restUrl + 'sightings/' + fawId + '/vote',
            method: 'POST',
            headers: {
                'X-WP-Nonce': wsData.nonce
            },
            data: { vote: vote },
            success: function(response) {
                if (response.success) {
                    updateVoteUI($voting, response.data);
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to record vote.';
                alert(msg);
            }
        });
    }
    
    function removeVote(fawId, $voting) {
        $.ajax({
            url: wsData.restUrl + 'sightings/' + fawId + '/vote',
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': wsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateVoteUI($voting, response.data);
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to remove vote.';
                alert(msg);
            }
        });
    }
    
    function updateVoteUI($voting, data) {
        $voting.find('.ws-vote-btn').removeClass('active');
        
        if (data.user_vote === 1) {
            $voting.find('.ws-vote-up').addClass('active');
        } else if (data.user_vote === -1) {
            $voting.find('.ws-vote-down').addClass('active');
        }
        
        $voting.find('.ws-score-value').text(data.score);
    }
    
    // Comment functionality
    function initComments() {
        // Submit new comment
        $('.ws-comment-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var fawId = $form.data('faw-id');
            var content = $form.find('textarea[name="content"]').val().trim();
            var commentType = $form.find('select[name="comment_type"]').val();
            
            if (!content) {
                alert('Please enter a comment.');
                return;
            }
            
            submitComment(fawId, content, commentType, null, $form);
        });
        
        // Show reply form
        $('.ws-comment-list').on('click', '.ws-reply-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $comment = $btn.closest('.ws-comment');
            var $replyForm = $comment.find('.ws-reply-form');
            
            // Hide all other reply forms
            $('.ws-reply-form').not($replyForm).hide();
            
            $replyForm.toggle();
            if ($replyForm.is(':visible')) {
                $replyForm.find('textarea').focus();
            }
        });
        
        // Cancel reply
        $('.ws-comment-list').on('click', '.ws-btn-cancel', function(e) {
            e.preventDefault();
            $(this).closest('.ws-reply-form').hide();
        });
        
        // Submit reply
        $('.ws-comment-list').on('submit', '.ws-reply-form', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var parentId = $form.data('parent-id');
            var content = $form.find('textarea').val().trim();
            
            if (!content) {
                alert('Please enter a reply.');
                return;
            }
            
            submitReply(parentId, content, $form);
        });
    }
    
    function submitComment(fawId, content, commentType, parentId, $form) {
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: wsData.restUrl + 'sightings/' + fawId + '/comments',
            method: 'POST',
            headers: {
                'X-WP-Nonce': wsData.nonce
            },
            data: {
                content: content,
                comment_type: commentType
            },
            success: function(response) {
                if (response.success) {
                    $form.find('textarea').val('');
                    alert(response.message || 'Comment submitted! It will appear after moderation.');
                    // Optionally reload to show the comment
                    // location.reload();
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to submit comment.';
                alert(msg);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Submit');
            }
        });
    }
    
    function submitReply(parentId, content, $form) {
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: wsData.restUrl + 'comments/' + parentId + '/reply',
            method: 'POST',
            headers: {
                'X-WP-Nonce': wsData.nonce
            },
            data: {
                content: content
            },
            success: function(response) {
                if (response.success) {
                    $form.find('textarea').val('');
                    $form.hide();
                    alert(response.message || 'Reply submitted! It will appear after moderation.');
                    // Optionally reload to show the reply
                    // location.reload();
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to submit reply.';
                alert(msg);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Reply');
            }
        });
    }
    
    // Search suggestions (autocomplete)
    function initSearch() {
        var $input = $('.ws-search-input');
        var $suggestions = $('.ws-search-suggestions');
        var debounceTimer;
        
        $input.on('input', function() {
            var query = $(this).val().trim();
            
            clearTimeout(debounceTimer);
            
            if (query.length < 2) {
                $suggestions.hide().empty();
                return;
            }
            
            debounceTimer = setTimeout(function() {
                fetchSuggestions(query, $suggestions);
            }, 300);
        });
        
        $input.on('blur', function() {
            // Delay to allow click on suggestion
            setTimeout(function() {
                $suggestions.hide();
            }, 200);
        });
        
        $suggestions.on('click', '.ws-suggestion-item', function(e) {
            e.preventDefault();
            var value = $(this).data('value');
            $input.val(value);
            $suggestions.hide();
            $input.closest('form').submit();
        });
    }
    
    function fetchSuggestions(query, $container) {
        $.ajax({
            url: wsData.restUrl + 'sightings/suggestions',
            method: 'GET',
            data: { query: query },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    response.data.forEach(function(item) {
                        var label = item.value;
                        if (item.year) {
                            label += ' (' + item.year + ')';
                        }
                        html += '<div class="ws-suggestion-item" data-value="' + escapeHtml(item.value) + '">';
                        html += '<span class="ws-suggestion-type">' + item.type + '</span>';
                        html += '<span class="ws-suggestion-value">' + escapeHtml(label) + '</span>';
                        html += '</div>';
                    });
                    $container.html(html).show();
                } else {
                    $container.hide().empty();
                }
            }
        });
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initVoting();
        initComments();
        initSearch();
    });
    
})(jQuery);
