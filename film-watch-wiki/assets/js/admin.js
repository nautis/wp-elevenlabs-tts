/**
 * Film Watch Wiki - Admin JavaScript
 */

(function($) {
    'use strict';

    let searchTimeout;

    $(document).ready(function() {
        initMovieSearch();
        initChangeMovieButton();
    });

    /**
     * Initialize TMDB movie search autocomplete
     */
    function initMovieSearch() {
        const $searchInput = $('#fww-movie-search');
        const $resultsDiv = $('#fww-movie-search-results');

        if ($searchInput.length === 0) {
            return;
        }

        $searchInput.on('input', function() {
            const query = $(this).val().trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                $resultsDiv.hide().empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                searchMovies(query, $resultsDiv);
            }, 300);
        });

        // Close results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#fww-movie-search, #fww-movie-search-results').length) {
                $resultsDiv.hide();
            }
        });
    }

    /**
     * Search for movies via TMDB API
     */
    function searchMovies(query, $resultsDiv) {
        $resultsDiv.html('<div class="fww-search-loading">Searching TMDB...</div>').show();

        $.post(ajaxurl, {
            action: 'fww_search_movies',
            nonce: fwwAjax.nonce,
            query: query
        }, function(response) {
            if (response.success && response.data.length > 0) {
                displayMovieResults(response.data, $resultsDiv);
            } else {
                $resultsDiv.html('<div class="fww-search-empty">No movies found</div>');
            }
        }).fail(function() {
            $resultsDiv.html('<div class="fww-search-error">Error searching movies</div>');
        });
    }

    /**
     * Display movie search results
     */
    function displayMovieResults(movies, $resultsDiv) {
        const $list = $('<div class="fww-movie-results-list"></div>');

        movies.forEach(function(movie) {
            const $item = $('<div class="fww-movie-result-item"></div>');

            let html = '<div class="fww-movie-result-content">';

            if (movie.poster_url) {
                html += '<img src="' + movie.poster_url + '" alt="' + escapeHtml(movie.title) + '" class="fww-result-poster">';
            } else {
                html += '<div class="fww-result-poster fww-result-poster-placeholder"></div>';
            }

            html += '<div class="fww-result-info">';
            html += '<div class="fww-result-title">' + escapeHtml(movie.title) + '</div>';
            html += '<div class="fww-result-year">' + escapeHtml(movie.year) + '</div>';
            if (movie.overview) {
                html += '<div class="fww-result-overview">' + escapeHtml(truncate(movie.overview, 100)) + '</div>';
            }
            html += '</div></div>';

            $item.html(html);
            $item.data('movie', movie);

            $item.on('click', function() {
                selectMovie($(this).data('movie'));
                $resultsDiv.hide();
            });

            $list.append($item);
        });

        $resultsDiv.empty().append($list).show();
    }

    /**
     * Handle movie selection
     */
    function selectMovie(movie) {
        console.log('Movie selected:', movie);

        // Set hidden fields
        $('#fww_tmdb_id').val(movie.id);
        $('#fww_year').val(movie.year);

        // Check if we're in the block editor (Gutenberg)
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
            console.log('Block editor detected, using wp.data API');

            // Use WordPress block editor API to set title and content
            wp.data.dispatch('core/editor').editPost({
                title: movie.title
            });

            // Add a simple paragraph block with movie info
            const blocks = wp.data.select('core/block-editor').getBlocks();
            if (blocks.length === 0) {
                const paragraph = wp.blocks.createBlock('core/paragraph', {
                    content: 'Film from ' + movie.year
                });
                wp.data.dispatch('core/block-editor').insertBlocks(paragraph);
            }

        } else {
            console.log('Classic editor detected, using jQuery');

            // Classic editor fallback
            const $titleField = $('#title');
            $titleField.val(movie.title);
            $titleField.trigger('input').trigger('change').trigger('keyup');

            // Update the title-prompt-text visibility
            $('#title-prompt-text').addClass('screen-reader-text');

            // If there's a classic editor, add a brief description
            if ($('#content').length) {
                if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                    tinymce.get('content').setContent('<p>Film from ' + escapeHtml(movie.year) + '</p>');
                } else {
                    $('#content').val('Film from ' + movie.year);
                }
            }
        }

        // Hide the search interface
        $('.fww-tmdb-search-section').hide();

        // Show a success message
        const $metabox = $('.fww-metabox');
        $metabox.prepend(
            '<div class="notice notice-success inline" style="margin: 0 0 15px 0; padding: 10px;">' +
            '<p><strong>Movie Selected:</strong> ' + escapeHtml(movie.title) + ' (' + movie.year + ')</p>' +
            '<p style="margin: 5px 0 0 0;"><em>Now you can click "Publish" to save and fetch full movie data from TMDB.</em></p>' +
            '</div>'
        );

        // Scroll to top to show the filled title
        $('html, body').animate({ scrollTop: 0 }, 300);
    }

    /**
     * Initialize change movie button
     */
    function initChangeMovieButton() {
        $(document).on('click', '#fww-change-movie', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to change the movie? This will clear the current TMDB data.')) {
                return;
            }

            // Clear the hidden fields
            $('#fww_tmdb_id').val('');
            $('#fww_year').val('');

            // Remove the selected movie display
            $('.fww-selected-movie').remove();

            // Show the search interface again
            $('.fww-tmdb-search-section').show();

            // Clear the search input
            $('#fww-movie-search').val('').focus();
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Truncate text
     */
    function truncate(text, length) {
        if (!text) return '';
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    }

})(jQuery);
