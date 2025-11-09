<?php
/**
 * Migration Functions
 * Migrate data from Film Watch Database to Film Watch Wiki
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Migration {

    /**
     * Migrate all films from wp_fwd_films to fww_movie posts
     *
     * @return array Results with counts and any errors
     */
    public static function migrate_films() {
        global $wpdb;

        $results = array(
            'total' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        // Get all films from the old database
        $films = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fwd_films ORDER BY year DESC, title ASC");

        if (empty($films)) {
            $results['errors'][] = 'No films found in database';
            return $results;
        }

        $results['total'] = count($films);

        foreach ($films as $film) {
            try {
                // Check if a post already exists for this film_id
                $existing = get_posts(array(
                    'post_type' => 'fww_movie',
                    'meta_key' => '_fww_film_id',
                    'meta_value' => $film->film_id,
                    'posts_per_page' => 1,
                    'post_status' => 'any'
                ));

                if (!empty($existing)) {
                    $results['skipped']++;
                    continue;
                }

                // Create the post
                $post_data = array(
                    'post_title' => sanitize_text_field($film->title),
                    'post_type' => 'fww_movie',
                    'post_status' => 'publish',
                    'post_excerpt' => sprintf('Film from %d', $film->year),
                );

                $post_id = wp_insert_post($post_data);

                if (is_wp_error($post_id)) {
                    $results['errors'][] = sprintf(
                        'Error creating post for "%s": %s',
                        $film->title,
                        $post_id->get_error_message()
                    );
                    continue;
                }

                // Add metadata
                update_post_meta($post_id, '_fww_film_id', $film->film_id);
                update_post_meta($post_id, '_fww_year', $film->year);

                $results['created']++;

            } catch (Exception $e) {
                $results['errors'][] = sprintf(
                    'Exception for "%s": %s',
                    $film->title,
                    $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Find and suggest TMDB IDs for migrated films
     * This searches TMDB for each film and returns potential matches
     *
     * @param int $limit Number of films to process (default 50)
     * @return array Array of films with suggested TMDB matches
     */
    public static function suggest_tmdb_matches($limit = 50) {
        $films_without_tmdb = get_posts(array(
            'post_type' => 'fww_movie',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_fww_year',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_fww_tmdb_id',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_fww_tmdb_id',
                        'value' => '',
                        'compare' => '='
                    )
                )
            )
        ));

        $suggestions = array();

        foreach ($films_without_tmdb as $post) {
            $title = get_the_title($post->ID);
            $year = get_post_meta($post->ID, '_fww_year', true);

            // Search TMDB
            $search_results = FWW_TMDB_API::search_movies($title, 1);

            if (!empty($search_results)) {
                // Find best match (same year or closest year)
                $best_match = null;
                $min_year_diff = PHP_INT_MAX;

                foreach ($search_results as $movie) {
                    $movie_year = !empty($movie['year']) ? $movie['year'] : 0;
                    $year_diff = abs($movie_year - $year);

                    if ($year_diff < $min_year_diff) {
                        $min_year_diff = $year_diff;
                        $best_match = $movie;
                    }

                    // Exact year match - perfect!
                    if ($year_diff === 0) {
                        break;
                    }
                }

                $suggestions[] = array(
                    'post_id' => $post->ID,
                    'title' => $title,
                    'year' => $year,
                    'suggested_movie' => $best_match,
                    'year_match' => $min_year_diff === 0
                );
            }
        }

        return $suggestions;
    }

    /**
     * Apply TMDB ID to a post
     *
     * @param int $post_id Post ID
     * @param int $tmdb_id TMDB movie ID
     * @return bool Success
     */
    public static function apply_tmdb_id($post_id, $tmdb_id) {
        if (get_post_type($post_id) !== 'fww_movie') {
            return false;
        }

        update_post_meta($post_id, '_fww_tmdb_id', $tmdb_id);

        // Fetch and cache TMDB data
        $tmdb_data = FWW_TMDB_API::get_movie($tmdb_id);
        if ($tmdb_data) {
            update_post_meta($post_id, '_fww_tmdb_data', $tmdb_data);
            return true;
        }

        return false;
    }
}
