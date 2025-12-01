/**
 * Film Watch Database - Admin JavaScript
 * TMDB Autocomplete functionality for movie and actor search
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Film Watch Database admin loaded');

        // Check if we're on the TMDB tab and autocomplete is available
        if (typeof $.fn.autocomplete === 'undefined') {
            console.log('jQuery UI Autocomplete not loaded');
            return;
        }

        // Initialize Movie Autocomplete
        initMovieAutocomplete();

        // Initialize Actor Autocomplete
        initActorAutocomplete();
    });

    /**
     * Initialize movie search autocomplete
     */
    function initMovieAutocomplete() {
        var $movieInput = $('#fwd-movie-autocomplete');

        if ($movieInput.length === 0) {
            return;
        }

        $movieInput.autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'fwd_tmdb_search_movies',
                        term: request.term,
                        nonce: fwdAjax.nonce
                    },
                    success: function(data) {
                        if (data.success === false) {
                            console.error('TMDB movie search error:', data.data.message);
                            response([]);
                            return;
                        }
                        response(data);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        response([]);
                    }
                });
            },
            minLength: 2,
            delay: 300,
            select: function(event, ui) {
                // Store selected movie data
                $('#fwd-movie-id').val(ui.item.id);
                $('#fwd-movie-title').val(ui.item.title);
                $('#fwd-movie-year').val(ui.item.year);

                // Show poster preview if available
                if (ui.item.poster_url) {
                    $('#fwd-movie-poster-preview').html(
                        '<div class="fwd-poster-preview" style="margin-top: 10px;">' +
                        '<img src="' + ui.item.poster_url + '" alt="' + ui.item.title + '" style="max-width: 150px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">' +
                        '<p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">' + ui.item.title + ' (' + ui.item.year + ')</p>' +
                        '</div>'
                    );
                } else {
                    $('#fwd-movie-poster-preview').html(
                        '<div class="fwd-poster-preview" style="margin-top: 10px;">' +
                        '<p style="color: #666;">' + ui.item.title + ' (' + ui.item.year + ') - No poster available</p>' +
                        '</div>'
                    );
                }

                // Fetch and display cast list
                fetchMovieCast(ui.item.id);

                return false;
            },
            focus: function(event, ui) {
                $movieInput.val(ui.item.title);
                return false;
            }
        }).autocomplete('instance')._renderItem = function(ul, item) {
            // Custom rendering with poster thumbnail
            var $li = $('<li>');
            var content = '<div class="fwd-autocomplete-item" style="display: flex; align-items: center; padding: 5px;">';

            if (item.poster_url) {
                content += '<img src="' + item.poster_url.replace('w500', 'w92') + '" style="width: 40px; height: 60px; margin-right: 10px; object-fit: cover; border-radius: 2px;">';
            } else {
                content += '<div style="width: 40px; height: 60px; margin-right: 10px; background: #ddd; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">No img</div>';
            }

            content += '<div>';
            content += '<strong>' + item.title + '</strong>';
            if (item.year) {
                content += ' <span style="color: #666;">(' + item.year + ')</span>';
            }
            if (item.overview) {
                var shortOverview = item.overview.length > 100 ? item.overview.substring(0, 100) + '...' : item.overview;
                content += '<br><small style="color: #888;">' + shortOverview + '</small>';
            }
            content += '</div></div>';

            return $li.append(content).appendTo(ul);
        };
    }

    /**
     * Initialize actor search autocomplete
     */
    function initActorAutocomplete() {
        var $actorInput = $('#fwd-actor-autocomplete');

        if ($actorInput.length === 0) {
            return;
        }

        $actorInput.autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'fwd_tmdb_search_actors',
                        term: request.term,
                        nonce: fwdAjax.nonce
                    },
                    success: function(data) {
                        if (data.success === false) {
                            console.error('TMDB actor search error:', data.data.message);
                            response([]);
                            return;
                        }
                        response(data);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        response([]);
                    }
                });
            },
            minLength: 2,
            delay: 300,
            select: function(event, ui) {
                // Store selected actor data
                $('#fwd-actor-id').val(ui.item.id);
                $('#fwd-actor-name').val(ui.item.name);

                // If a movie is already selected, check if this actor is in the cast
                var movieId = $('#fwd-movie-id').val();
                if (movieId && window.fwdMovieCast) {
                    var castMember = window.fwdMovieCast.find(function(c) {
                        return c.id === ui.item.id;
                    });
                    if (castMember && castMember.character) {
                        $('#fwd-character-name').val(castMember.character);
                    }
                }

                return false;
            },
            focus: function(event, ui) {
                $actorInput.val(ui.item.name);
                return false;
            }
        }).autocomplete('instance')._renderItem = function(ul, item) {
            // Custom rendering with profile photo
            var $li = $('<li>');
            var content = '<div class="fwd-autocomplete-item" style="display: flex; align-items: center; padding: 5px;">';

            if (item.profile_url) {
                content += '<img src="' + item.profile_url + '" style="width: 40px; height: 60px; margin-right: 10px; object-fit: cover; border-radius: 2px;">';
            } else {
                content += '<div style="width: 40px; height: 60px; margin-right: 10px; background: #ddd; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">No img</div>';
            }

            content += '<div>';
            content += '<strong>' + item.name + '</strong>';
            if (item.known_for && item.known_for.length > 0) {
                var knownTitles = item.known_for.slice(0, 2).map(function(m) { return m.title; }).join(', ');
                content += '<br><small style="color: #888;">Known for: ' + knownTitles + '</small>';
            }
            content += '</div></div>';

            return $li.append(content).appendTo(ul);
        };
    }

    /**
     * Fetch movie cast and display clickable list
     */
    function fetchMovieCast(movieId) {
        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            data: {
                action: 'fwd_tmdb_get_movie_credits',
                movie_id: movieId,
                nonce: fwdAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data.cast) {
                    window.fwdMovieCast = response.data.cast;
                    displayCastList(response.data.cast);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch movie cast:', error);
            }
        });
    }

    /**
     * Display clickable cast list below movie selection
     */
    function displayCastList(cast) {
        // Remove any existing cast list
        $('#fwd-movie-cast-list').remove();

        if (!cast || cast.length === 0) {
            return;
        }

        var $castSection = $('<div id="fwd-movie-cast-list" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">');
        $castSection.append('<p style="margin: 0 0 10px 0; font-weight: bold;">Click to select actor:</p>');

        var $castItems = $('<div class="fwd-cast-items" style="display: flex; flex-wrap: wrap; gap: 8px;">');

        cast.forEach(function(member) {
            var $btn = $('<button type="button" class="button" style="display: flex; align-items: center; gap: 5px; padding: 5px 10px;">');

            if (member.profile_url) {
                $btn.append('<img src="' + member.profile_url + '" style="width: 24px; height: 36px; object-fit: cover; border-radius: 2px;">');
            }

            var label = member.name;
            if (member.character) {
                label += ' as ' + member.character;
            }
            $btn.append('<span>' + label + '</span>');

            $btn.on('click', function() {
                // Set actor fields
                $('#fwd-actor-autocomplete').val(member.name);
                $('#fwd-actor-id').val(member.id);
                $('#fwd-actor-name').val(member.name);

                // Set character if available
                if (member.character) {
                    $('#fwd-character-name').val(member.character);
                }

                // Highlight selected button
                $castItems.find('.button').removeClass('button-primary');
                $(this).addClass('button-primary');
            });

            $castItems.append($btn);
        });

        $castSection.append($castItems);
        $('#fwd-movie-poster-preview').after($castSection);
    }

})(jQuery);
