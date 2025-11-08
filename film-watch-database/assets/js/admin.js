/**
 * Film Watch Database - Admin JavaScript
 * Handles TMDB autocomplete and enhanced entry forms
 */

(function($) {
    'use strict';

    // Autocomplete configuration
    const AUTOCOMPLETE_CONFIG = {
        minLength: 2,
        delay: 300,
        maxResults: 10
    };

    /**
     * Initialize movie autocomplete
     */
    function initMovieAutocomplete() {
        const $movieInput = $('#fwd-movie-autocomplete');
        if ($movieInput.length === 0) {
            console.log('FWD Admin: Movie input not found');
            return;
        }

        console.log('FWD Admin: Initializing movie autocomplete');

        let searchTimeout;
        let currentResults = [];

        // Wrap input for positioning FIRST
        if (!$movieInput.parent().hasClass('fwd-autocomplete-wrapper')) {
            $movieInput.wrap('<div class="fwd-autocomplete-wrapper" style="position: relative;"></div>');
        }

        // Create autocomplete container and insert into wrapper
        const $autocomplete = $('<div class="fwd-autocomplete-results"></div>');
        $movieInput.parent().append($autocomplete);

        // Handle input
        $movieInput.on('input', function() {
            const query = $(this).val().trim();
            console.log('FWD Admin: Movie input event, query:', query);

            clearTimeout(searchTimeout);

            if (query.length < AUTOCOMPLETE_CONFIG.minLength) {
                $autocomplete.hide().empty();
                return;
            }

            console.log('FWD Admin: Searching for movies:', query);
            searchTimeout = setTimeout(function() {
                searchMovies(query, $autocomplete, $movieInput);
            }, AUTOCOMPLETE_CONFIG.delay);
        });

        // Handle clicks outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.fwd-autocomplete-wrapper').length) {
                $autocomplete.hide();
            }
        });
    }

    /**
     * Search movies via TMDB API
     */
    function searchMovies(query, $autocomplete, $input) {
        $autocomplete.html('<div class="fwd-autocomplete-loading">Searching...</div>').show();

        $.post(ajaxurl, {
            action: 'fwd_search_movies',
            nonce: fwdAjax.nonce,
            query: query
        }, function(response) {
            if (response.success && response.data.length > 0) {
                displayMovieResults(response.data, $autocomplete, $input);
            } else {
                $autocomplete.html('<div class="fwd-autocomplete-empty">No movies found</div>');
            }
        }).fail(function() {
            $autocomplete.html('<div class="fwd-autocomplete-error">Error searching movies</div>');
        });
    }

    /**
     * Display movie autocomplete results
     */
    function displayMovieResults(movies, $autocomplete, $input) {
        const $list = $('<div class="fwd-autocomplete-list"></div>');

        movies.forEach(function(movie) {
            const $item = $('<div class="fwd-autocomplete-item"></div>');

            let html = '<div class="fwd-autocomplete-item-content">';

            if (movie.poster_url) {
                html += '<img src="' + movie.poster_url + '" alt="' + movie.title + '" class="fwd-autocomplete-poster">';
            }

            html += '<div class="fwd-autocomplete-info">';
            html += '<div class="fwd-autocomplete-title">' + escapeHtml(movie.title) + '</div>';
            html += '<div class="fwd-autocomplete-meta">' + movie.year + '</div>';
            html += '</div></div>';

            $item.html(html);
            $item.data('movie', movie);

            $item.on('click', function() {
                selectMovie($(this).data('movie'));
                $autocomplete.hide();
            });

            $list.append($item);
        });

        $autocomplete.empty().append($list).show();
    }

    /**
     * Handle movie selection
     */
    function selectMovie(movie) {
        console.log('FWD Admin: Movie selected:', movie);

        // Populate hidden fields
        $('#fwd-movie-id').val(movie.id);
        $('#fwd-movie-title').val(movie.title);
        $('#fwd-movie-year').val(movie.year);

        // Update display input
        $('#fwd-movie-autocomplete').val(movie.title + ' (' + movie.year + ')');

        // Show movie poster if available
        if (movie.poster_url) {
            showMoviePoster(movie.poster_url, movie.title);
        }

        // Fetch and display cast
        if (movie.id) {
            console.log('FWD Admin: Fetching cast for movie ID:', movie.id);
            fetchMovieCast(movie.id);
        } else {
            console.log('FWD Admin: No movie ID, cannot fetch cast');
        }

        // Trigger custom event
        $(document).trigger('fwd:movie-selected', [movie]);
    }

    /**
     * Show movie poster preview
     */
    function showMoviePoster(posterUrl, title) {
        const $preview = $('#fwd-movie-poster-preview');
        if ($preview.length === 0) {
            $('#fwd-movie-autocomplete').after(
                '<div id="fwd-movie-poster-preview" style="margin-top: 10px;"></div>'
            );
        }

        $('#fwd-movie-poster-preview').html(
            '<img src="' + posterUrl + '" alt="' + escapeHtml(title) + '" style="max-width: 154px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">'
        );
    }

    /**
     * Fetch movie cast
     */
    function fetchMovieCast(movieId) {
        console.log('FWD Admin: Making AJAX request for movie details, ID:', movieId);

        $.post(ajaxurl, {
            action: 'fwd_get_movie_details',
            nonce: fwdAjax.nonce,
            movie_id: movieId
        }, function(response) {
            console.log('FWD Admin: Movie details response:', response);

            if (response.success && response.data.cast) {
                console.log('FWD Admin: Cast found, count:', response.data.cast.length);
                displayMovieCast(response.data.cast);
            } else {
                console.log('FWD Admin: No cast data in response');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('FWD Admin: AJAX error fetching movie details:', textStatus, errorThrown);
        });
    }

    /**
     * Display movie cast for selection
     */
    function displayMovieCast(cast) {
        console.log('FWD Admin: displayMovieCast called with', cast.length, 'actors');

        let $castContainer = $('#fwd-movie-cast-list');
        console.log('FWD Admin: Cast container exists:', $castContainer.length > 0);

        // Check if container exists and has proper structure
        if ($castContainer.length === 0) {
            const $posterPreview = $('#fwd-movie-poster-preview');
            console.log('FWD Admin: Poster preview exists:', $posterPreview.length > 0);

            if ($posterPreview.length > 0) {
                $posterPreview.after(
                    '<div id="fwd-movie-cast-list" style="margin-top: 15px;"><strong>Cast:</strong><div class="fwd-cast-items" style="margin-top: 10px;"></div></div>'
                );
                console.log('FWD Admin: Cast container created after poster');
            } else {
                // If no poster preview, insert after the movie input wrapper
                $('.fwd-autocomplete-wrapper').first().after(
                    '<div id="fwd-movie-cast-list" style="margin-top: 15px;"><strong>Cast:</strong><div class="fwd-cast-items" style="margin-top: 10px;"></div></div>'
                );
                console.log('FWD Admin: Cast container created after input wrapper');
            }
            $castContainer = $('#fwd-movie-cast-list');
        }

        // Ensure the container has the proper inner structure
        let $castItems = $castContainer.find('.fwd-cast-items');
        if ($castItems.length === 0) {
            console.log('FWD Admin: Cast items div missing, adding it');
            $castContainer.html('<strong>Cast:</strong><div class="fwd-cast-items" style="margin-top: 10px;"></div>');
            $castItems = $castContainer.find('.fwd-cast-items');
        }

        console.log('FWD Admin: Cast items container found:', $castItems.length > 0);
        $castItems.empty();

        cast.forEach(function(actor) {
            const $actorBtn = $('<button type="button" class="button button-small" style="margin: 0 5px 5px 0;"></button>');
            $actorBtn.text(actor.name + (actor.character ? ' (' + actor.character + ')' : ''));
            $actorBtn.data('actor', actor);

            $actorBtn.on('click', function() {
                const actorData = $(this).data('actor');
                // Update both the hidden field and visible autocomplete input
                $('#fwd-actor-name').val(actorData.name);
                $('#fwd-actor-autocomplete').val(actorData.name);
                $('#fwd-actor-id').val(actorData.id || '');

                if (actorData.character) {
                    $('#fwd-character-name').val(actorData.character);
                }
                // Highlight selected
                $('.fwd-cast-items .button').removeClass('button-primary');
                $(this).addClass('button-primary');
            });

            $castItems.append($actorBtn);
        });
    }

    /**
     * Initialize actor autocomplete
     */
    function initActorAutocomplete() {
        const $actorInput = $('#fwd-actor-autocomplete');
        if ($actorInput.length === 0) return;

        let searchTimeout;

        // Wrap input for positioning FIRST
        if (!$actorInput.parent().hasClass('fwd-autocomplete-wrapper')) {
            $actorInput.wrap('<div class="fwd-autocomplete-wrapper" style="position: relative;"></div>');
        }

        // Create autocomplete container and insert into wrapper
        const $autocomplete = $('<div class="fwd-autocomplete-results"></div>');
        $actorInput.parent().append($autocomplete);

        // Handle input
        $actorInput.on('input', function() {
            const query = $(this).val().trim();

            clearTimeout(searchTimeout);

            if (query.length < AUTOCOMPLETE_CONFIG.minLength) {
                $autocomplete.hide().empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                searchActors(query, $autocomplete, $actorInput);
            }, AUTOCOMPLETE_CONFIG.delay);
        });

        // Handle clicks outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.fwd-autocomplete-wrapper').length) {
                $autocomplete.hide();
            }
        });
    }

    /**
     * Search actors via TMDB API
     */
    function searchActors(query, $autocomplete, $input) {
        $autocomplete.html('<div class="fwd-autocomplete-loading">Searching...</div>').show();

        $.post(ajaxurl, {
            action: 'fwd_search_actors',
            nonce: fwdAjax.nonce,
            query: query
        }, function(response) {
            if (response.success && response.data.length > 0) {
                displayActorResults(response.data, $autocomplete, $input);
            } else {
                $autocomplete.html('<div class="fwd-autocomplete-empty">No actors found</div>');
            }
        }).fail(function() {
            $autocomplete.html('<div class="fwd-autocomplete-error">Error searching actors</div>');
        });
    }

    /**
     * Display actor autocomplete results
     */
    function displayActorResults(actors, $autocomplete, $input) {
        const $list = $('<div class="fwd-autocomplete-list"></div>');

        actors.forEach(function(actor) {
            const $item = $('<div class="fwd-autocomplete-item"></div>');

            let html = '<div class="fwd-autocomplete-item-content">';

            if (actor.profile_url) {
                html += '<img src="' + actor.profile_url + '" alt="' + actor.name + '" class="fwd-autocomplete-poster">';
            }

            html += '<div class="fwd-autocomplete-info">';
            html += '<div class="fwd-autocomplete-title">' + escapeHtml(actor.name) + '</div>';

            if (actor.known_for && actor.known_for.length > 0) {
                const knownForText = actor.known_for.slice(0, 2).map(m => m.title).join(', ');
                html += '<div class="fwd-autocomplete-meta">Known for: ' + escapeHtml(knownForText) + '</div>';
            }

            html += '</div></div>';

            $item.html(html);
            $item.data('actor', actor);

            $item.on('click', function() {
                selectActor($(this).data('actor'));
                $autocomplete.hide();
            });

            $list.append($item);
        });

        $autocomplete.empty().append($list).show();
    }

    /**
     * Handle actor selection
     */
    function selectActor(actor) {
        // Populate fields
        $('#fwd-actor-id').val(actor.id);
        $('#fwd-actor-name').val(actor.name);

        // Update display input
        $('#fwd-actor-autocomplete').val(actor.name);

        // Fetch actor's movies if needed
        if (actor.id) {
            fetchActorMovies(actor.id);
        }

        // Trigger custom event
        $(document).trigger('fwd:actor-selected', [actor]);
    }

    /**
     * Fetch actor's movies
     */
    function fetchActorMovies(actorId) {
        $.post(ajaxurl, {
            action: 'fwd_get_actor_movies',
            nonce: fwdAjax.nonce,
            actor_id: actorId
        }, function(response) {
            if (response.success && response.data.length > 0) {
                displayActorMovies(response.data);
            }
        });
    }

    /**
     * Display actor's movies for selection
     */
    function displayActorMovies(movies) {
        const $moviesContainer = $('#fwd-actor-movies-list');
        if ($moviesContainer.length === 0) {
            $('#fwd-actor-autocomplete').closest('.fwd-autocomplete-wrapper').after(
                '<div id="fwd-actor-movies-list" style="margin-top: 15px;"><strong>Known Movies:</strong><div class="fwd-movie-items" style="margin-top: 10px;"></div></div>'
            );
        }

        const $movieItems = $('.fwd-movie-items');
        $movieItems.empty();

        // Show first 10 movies
        movies.slice(0, 10).forEach(function(movie) {
            const $movieBtn = $('<button type="button" class="button button-small" style="margin: 0 5px 5px 0;"></button>');
            $movieBtn.text(movie.title + ' (' + movie.year + ')');
            $movieBtn.data('movie', movie);

            $movieBtn.on('click', function() {
                const movieData = $(this).data('movie');
                $('#fwd-movie-title').val(movieData.title);
                $('#fwd-movie-year').val(movieData.year);
                $('#fwd-movie-id').val(movieData.id);

                // Update movie autocomplete field if it exists
                if ($('#fwd-movie-autocomplete').length) {
                    $('#fwd-movie-autocomplete').val(movieData.title + ' (' + movieData.year + ')');
                }

                // Highlight selected
                $('.fwd-movie-items .button').removeClass('button-primary');
                $(this).addClass('button-primary');

                // Show poster
                if (movieData.poster_url) {
                    showMoviePoster(movieData.poster_url, movieData.title);
                }
            });

            $movieItems.append($movieBtn);
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        console.log('FWD Admin: Initializing TMDB autocomplete...');
        console.log('FWD Admin: Movie input exists:', $('#fwd-movie-autocomplete').length > 0);
        console.log('FWD Admin: Actor input exists:', $('#fwd-actor-autocomplete').length > 0);
        console.log('FWD Admin: fwdAjax defined:', typeof fwdAjax !== 'undefined');
        console.log('FWD Admin: ajaxurl defined:', typeof ajaxurl !== 'undefined');

        initMovieAutocomplete();
        initActorAutocomplete();

        console.log('FWD Admin: Autocomplete initialized');
    });

})(jQuery);
