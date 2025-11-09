<?php
/**
 * TMDB API Wrapper Class
 * Handles all interactions with The Movie Database (TMDB) API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_TMDB_API {

    /**
     * TMDB API base URL
     */
    const API_BASE_URL = 'https://api.themoviedb.org/3/';

    /**
     * TMDB Image base URL
     */
    const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/';

    /**
     * Configuration constants
     */
    const DEFAULT_CACHE_DURATION = 86400; // 24 hours in seconds
    const API_TIMEOUT = 20; // seconds
    const DEFAULT_CAST_LIMIT = 10;
    const LOCK_TIMEOUT = 30; // seconds for transient locks

    /**
     * Get API key from WordPress options
     */
    private static function get_api_key() {
        return get_option('fww_tmdb_api_key', '');
    }

    /**
     * Get language from WordPress options
     */
    private static function get_language() {
        return get_option('fww_tmdb_language', 'en-US');
    }

    /**
     * Get cache duration from WordPress options (in seconds)
     */
    private static function get_cache_duration() {
        return intval(get_option('fww_cache_duration', self::DEFAULT_CACHE_DURATION));
    }

    /**
     * Make an API request to TMDB
     *
     * @param string $endpoint API endpoint (e.g., 'search/movie')
     * @param array $params Query parameters
     * @return array|WP_Error Response data or error
     */
    private static function make_request($endpoint, $params = array()) {
        $api_key = self::get_api_key();

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'TMDB API key not configured');
        }

        // Add language parameter if not set
        if (!isset($params['language'])) {
            $params['language'] = self::get_language();
        }

        // Build URL with query parameters
        $url = self::API_BASE_URL . $endpoint . '?' . http_build_query($params);

        // Check cache first
        $cache_key = 'fww_tmdb_' . md5($url);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Make API request
        $response = wp_remote_get($url, array(
            'timeout' => self::API_TIMEOUT,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle HTTP errors
        if ($response_code !== 200) {
            if ($response_code === 401) {
                return new WP_Error('unauthorized', 'Invalid TMDB API key');
            } elseif ($response_code === 429) {
                return new WP_Error('rate_limit', 'TMDB API rate limit exceeded');
            } else {
                return new WP_Error('api_error', 'TMDB API error: HTTP ' . $response_code);
            }
        }

        // Decode JSON response
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from TMDB API');
        }

        // Cache the response
        $cache_duration = self::get_cache_duration();
        set_transient($cache_key, $data, $cache_duration);

        return $data;
    }

    /**
     * Search for movies
     *
     * @param string $query Search query
     * @param int $page Page number (default: 1)
     * @return array|WP_Error Array of movie results or error
     */
    public static function search_movies($query, $page = 1) {
        if (empty($query)) {
            return new WP_Error('empty_query', 'Search query cannot be empty');
        }

        $response = self::make_request('search/movie', array(
            'query' => $query,
            'page' => $page,
            'include_adult' => false
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        // Format results
        $movies = array();
        if (isset($response['results']) && is_array($response['results'])) {
            foreach ($response['results'] as $movie) {
                $movies[] = array(
                    'id' => $movie['id'],
                    'title' => $movie['title'],
                    'original_title' => isset($movie['original_title']) ? $movie['original_title'] : '',
                    'year' => !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '',
                    'release_date' => isset($movie['release_date']) ? $movie['release_date'] : '',
                    'overview' => isset($movie['overview']) ? $movie['overview'] : '',
                    'poster_path' => isset($movie['poster_path']) ? $movie['poster_path'] : null,
                    'poster_url' => isset($movie['poster_path']) ? self::get_image_url($movie['poster_path'], 'w500') : null,
                    'backdrop_path' => isset($movie['backdrop_path']) ? $movie['backdrop_path'] : null,
                    'vote_average' => isset($movie['vote_average']) ? $movie['vote_average'] : 0
                );
            }
        }

        return $movies;
    }

    /**
     * Search for a specific movie by title and optional year
     *
     * @param string $title Movie title
     * @param int|null $year Release year (optional but highly recommended for accuracy)
     * @return array|WP_Error Search results with 'results' array or error
     */
    public static function search_movie($title, $year = null) {
        if (empty($title)) {
            return new WP_Error('empty_title', 'Movie title cannot be empty');
        }

        $params = array(
            'query' => $title,
            'include_adult' => false
        );

        // Add year if provided for more accurate results
        if (!empty($year) && is_numeric($year)) {
            $params['year'] = $year;
        }

        $response = self::make_request('search/movie', $params);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response; // Return full response including 'results' array
    }

    /**
     * Get movie details by ID
     *
     * @param int $movie_id TMDB movie ID
     * @return array|WP_Error Movie details or error
     */
    public static function get_movie($movie_id) {
        // Validate movie ID
        if (empty($movie_id) || !is_numeric($movie_id) || $movie_id <= 0) {
            return new WP_Error('invalid_movie_id', 'Invalid movie ID provided');
        }

        $movie_id = intval($movie_id);
        $response = self::make_request('movie/' . $movie_id);

        if (is_wp_error($response)) {
            return $response;
        }

        // Get US certification
        $certification = self::get_us_certification($movie_id);

        return array(
            'id' => $response['id'],
            'title' => $response['title'],
            'original_title' => isset($response['original_title']) ? $response['original_title'] : '',
            'year' => !empty($response['release_date']) ? substr($response['release_date'], 0, 4) : '',
            'release_date' => isset($response['release_date']) ? $response['release_date'] : '',
            'overview' => isset($response['overview']) ? $response['overview'] : '',
            'tagline' => isset($response['tagline']) ? $response['tagline'] : '',
            'runtime' => isset($response['runtime']) ? $response['runtime'] : 0,
            'poster_path' => isset($response['poster_path']) ? $response['poster_path'] : null,
            'poster_url' => isset($response['poster_path']) ? self::get_image_url($response['poster_path'], 'w500') : null,
            'backdrop_path' => isset($response['backdrop_path']) ? $response['backdrop_path'] : null,
            'backdrop_url' => isset($response['backdrop_path']) ? self::get_image_url($response['backdrop_path'], 'original') : null,
            'vote_average' => isset($response['vote_average']) ? $response['vote_average'] : 0,
            'genres' => isset($response['genres']) ? $response['genres'] : array(),
            'certification' => $certification
        );
    }

    /**
     * Get US certification/rating for a movie (e.g., PG-13, R)
     *
     * @param int $movie_id TMDB movie ID
     * @return string Certification or empty string
     */
    public static function get_us_certification($movie_id) {
        // Validate movie ID
        if (empty($movie_id) || !is_numeric($movie_id) || $movie_id <= 0) {
            return '';
        }

        $movie_id = intval($movie_id);
        $response = self::make_request('movie/' . $movie_id . '/release_dates');

        if (is_wp_error($response) || !isset($response['results'])) {
            return '';
        }

        // Look for US certification
        foreach ($response['results'] as $country) {
            if ($country['iso_3166_1'] === 'US' && !empty($country['release_dates'])) {
                foreach ($country['release_dates'] as $release) {
                    if (!empty($release['certification'])) {
                        return $release['certification'];
                    }
                }
            }
        }

        return '';
    }

    /**
     * Get movie credits (cast and crew)
     *
     * @param int $movie_id TMDB movie ID
     * @param int $limit Maximum number of cast members to return (default: 10)
     * @return array|WP_Error Array of cast members or error
     */
    public static function get_movie_credits($movie_id, $limit = self::DEFAULT_CAST_LIMIT) {
        // Validate movie ID
        if (empty($movie_id) || !is_numeric($movie_id) || $movie_id <= 0) {
            return new WP_Error('invalid_movie_id', 'Invalid movie ID provided');
        }

        $movie_id = intval($movie_id);
        $response = self::make_request('movie/' . $movie_id . '/credits');

        if (is_wp_error($response)) {
            return $response;
        }

        $cast = array();
        if (isset($response['cast']) && is_array($response['cast'])) {
            $count = 0;
            foreach ($response['cast'] as $actor) {
                if ($count >= $limit) {
                    break;
                }

                $cast[] = array(
                    'id' => $actor['id'],
                    'name' => $actor['name'],
                    'character' => isset($actor['character']) ? $actor['character'] : '',
                    'order' => isset($actor['order']) ? $actor['order'] : 999,
                    'profile_path' => isset($actor['profile_path']) ? $actor['profile_path'] : null,
                    'profile_url' => isset($actor['profile_path']) ? self::get_image_url($actor['profile_path'], 'w185') : null
                );

                $count++;
            }
        }

        return $cast;
    }

    /**
     * Search for people (actors, directors, etc.)
     *
     * @param string $query Search query
     * @param int $page Page number (default: 1)
     * @return array|WP_Error Array of person results or error
     */
    public static function search_people($query, $page = 1) {
        if (empty($query)) {
            return new WP_Error('empty_query', 'Search query cannot be empty');
        }

        $response = self::make_request('search/person', array(
            'query' => $query,
            'page' => $page,
            'include_adult' => false
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        // Format results
        $people = array();
        if (isset($response['results']) && is_array($response['results'])) {
            foreach ($response['results'] as $person) {
                $people[] = array(
                    'id' => $person['id'],
                    'name' => $person['name'],
                    'known_for_department' => isset($person['known_for_department']) ? $person['known_for_department'] : '',
                    'profile_path' => isset($person['profile_path']) ? $person['profile_path'] : null,
                    'profile_url' => isset($person['profile_path']) ? self::get_image_url($person['profile_path'], 'w185') : null,
                    'known_for' => isset($person['known_for']) ? self::format_known_for($person['known_for']) : array(),
                    'popularity' => isset($person['popularity']) ? $person['popularity'] : 0
                );
            }
        }

        return $people;
    }

    /**
     * Get person details by ID
     *
     * @param int $person_id TMDB person ID
     * @return array|WP_Error Person details or error
     */
    public static function get_person($person_id) {
        // Validate person ID
        if (empty($person_id) || !is_numeric($person_id) || $person_id <= 0) {
            return new WP_Error('invalid_person_id', 'Invalid person ID provided');
        }

        $person_id = intval($person_id);
        $response = self::make_request('person/' . $person_id);

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'id' => $response['id'],
            'name' => $response['name'],
            'biography' => isset($response['biography']) ? $response['biography'] : '',
            'birthday' => isset($response['birthday']) ? $response['birthday'] : '',
            'place_of_birth' => isset($response['place_of_birth']) ? $response['place_of_birth'] : '',
            'profile_path' => isset($response['profile_path']) ? $response['profile_path'] : null,
            'profile_url' => isset($response['profile_path']) ? self::get_image_url($response['profile_path'], 'w185') : null,
            'known_for_department' => isset($response['known_for_department']) ? $response['known_for_department'] : ''
        );
    }

    /**
     * Get person's movie credits
     *
     * @param int $person_id TMDB person ID
     * @return array|WP_Error Array of movie credits or error
     */
    public static function get_person_movie_credits($person_id) {
        // Validate person ID
        if (empty($person_id) || !is_numeric($person_id) || $person_id <= 0) {
            return new WP_Error('invalid_person_id', 'Invalid person ID provided');
        }

        $person_id = intval($person_id);
        $response = self::make_request('person/' . $person_id . '/movie_credits');

        if (is_wp_error($response)) {
            return $response;
        }

        $movies = array();
        if (isset($response['cast']) && is_array($response['cast'])) {
            foreach ($response['cast'] as $movie) {
                $movies[] = array(
                    'id' => $movie['id'],
                    'title' => $movie['title'],
                    'character' => isset($movie['character']) ? $movie['character'] : '',
                    'year' => !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '',
                    'release_date' => isset($movie['release_date']) ? $movie['release_date'] : '',
                    'poster_path' => isset($movie['poster_path']) ? $movie['poster_path'] : null,
                    'poster_url' => isset($movie['poster_path']) ? self::get_image_url($movie['poster_path'], 'w185') : null
                );
            }
        }

        // Sort by release date (newest first)
        usort($movies, function($a, $b) {
            return strcmp($b['release_date'], $a['release_date']);
        });

        return $movies;
    }

    /**
     * Format "known for" movies/TV shows
     *
     * @param array $known_for Array of known for items
     * @return array Formatted array
     */
    private static function format_known_for($known_for) {
        $formatted = array();

        foreach ($known_for as $item) {
            if (isset($item['media_type']) && $item['media_type'] === 'movie') {
                $formatted[] = array(
                    'title' => $item['title'],
                    'year' => !empty($item['release_date']) ? substr($item['release_date'], 0, 4) : ''
                );
            }
        }

        return $formatted;
    }

    /**
     * Get image URL from path
     *
     * @param string $path Image path from TMDB
     * @param string $size Image size (w92, w185, w500, original, etc.)
     * @return string Full image URL
     */
    public static function get_image_url($path, $size = 'original') {
        if (empty($path)) {
            return null;
        }

        return self::IMAGE_BASE_URL . $size . $path;
    }

    /**
     * Clear all TMDB cache
     */
    public static function clear_cache() {
        global $wpdb;

        // Delete all transients starting with 'fww_tmdb_'
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             OR option_name LIKE %s",
            $wpdb->esc_like('_transient_fww_tmdb_') . '%',
            $wpdb->esc_like('_transient_timeout_fww_tmdb_') . '%'
        ));

        return true;
    }
}
