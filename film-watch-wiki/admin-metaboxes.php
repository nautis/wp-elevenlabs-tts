<?php
/**
 * Admin Metaboxes
 * Handles custom metaboxes for our custom post types
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Admin_Metaboxes {

    /**
     * Initialize
     */
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_movie_meta'), 10, 2);
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        // Movie meta box
        add_meta_box(
            'fww_movie_details',
            'Movie Details',
            array(__CLASS__, 'movie_details_meta_box'),
            'fww_movie',
            'normal',
            'high'
        );
    }

    /**
     * Movie details meta box
     */
    public static function movie_details_meta_box($post) {
        wp_nonce_field('fww_movie_meta_nonce', 'fww_movie_meta_nonce');

        $tmdb_id = get_post_meta($post->ID, '_fww_tmdb_id', true);
        $year = get_post_meta($post->ID, '_fww_year', true);
        $film_id = get_post_meta($post->ID, '_fww_film_id', true);
        $tmdb_data = get_post_meta($post->ID, '_fww_tmdb_data', true);
        ?>

        <div class="fww-metabox">

            <?php if (empty($tmdb_id)) : ?>
                <!-- TMDB Search Interface -->
                <div class="fww-tmdb-search-section">
                    <p>
                        <label for="fww-movie-search"><strong>Search TMDB for Movie:</strong></label><br>
                        <input type="text" id="fww-movie-search" class="regular-text" placeholder="Type movie title to search...">
                    </p>
                    <div id="fww-movie-search-results"></div>
                </div>

                <p style="margin-top: 20px; color: #666; font-style: italic;">
                    Or manually enter TMDB ID below (not recommended):
                </p>
            <?php endif; ?>

            <!-- Hidden fields for data -->
            <input type="hidden" id="fww_tmdb_id" name="fww_tmdb_id" value="<?php echo esc_attr($tmdb_id); ?>">
            <input type="hidden" id="fww_year" name="fww_year" value="<?php echo esc_attr($year); ?>">

            <?php if (!empty($tmdb_id) && !empty($tmdb_data)) : ?>
                <!-- Display selected movie info -->
                <div class="fww-selected-movie" style="background: #f0f6fc; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                    <h4 style="margin-top: 0;">Selected Movie:</h4>
                    <div style="display: flex; gap: 15px; align-items: flex-start;">
                        <?php if (!empty($tmdb_data['poster_path'])) : ?>
                            <img src="<?php echo esc_url(FWW_TMDB_API::get_image_url($tmdb_data['poster_path'], 'w154')); ?>"
                                 style="width: 100px; border-radius: 4px;">
                        <?php endif; ?>
                        <div>
                            <p style="margin: 0 0 5px 0;">
                                <strong><?php echo esc_html($tmdb_data['title']); ?></strong>
                                (<?php echo esc_html($year); ?>)
                            </p>
                            <p style="margin: 0 0 5px 0; font-size: 13px; color: #666;">
                                TMDB ID: <?php echo esc_html($tmdb_id); ?>
                            </p>
                            <?php if (!empty($tmdb_data['overview'])) : ?>
                                <p style="margin: 5px 0 0 0; font-size: 13px;">
                                    <?php echo esc_html(wp_trim_words($tmdb_data['overview'], 30)); ?>
                                </p>
                            <?php endif; ?>
                            <p style="margin: 10px 0 0 0;">
                                <button type="button" class="button" id="fww-change-movie">Change Movie</button>
                            </p>
                        </div>
                    </div>
                </div>
            <?php elseif (!empty($tmdb_id)) : ?>
                <p style="background: #fff3cd; padding: 10px; border-radius: 4px;">
                    <strong>TMDB ID:</strong> <?php echo esc_html($tmdb_id); ?><br>
                    <small>Save the post to fetch movie data from TMDB.</small>
                </p>
            <?php endif; ?>

            <p>
                <label for="fww_film_id"><strong>Legacy Film ID (Optional):</strong></label><br>
                <input type="number" id="fww_film_id" name="fww_film_id" value="<?php echo esc_attr($film_id); ?>" class="regular-text">
                <br><small>Link to the film_id from wp_fwd_films table to display watch sightings.</small>
            </p>

        </div>

        <?php
    }

    /**
     * Save movie meta
     */
    public static function save_movie_meta($post_id, $post) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['fww_movie_meta_nonce']) || !wp_verify_nonce($_POST['fww_movie_meta_nonce'], 'fww_movie_meta_nonce')) {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only for movie post type
        if ($post->post_type !== 'fww_movie') {
            return;
        }

        // Save TMDB ID
        if (isset($_POST['fww_tmdb_id'])) {
            $tmdb_id = sanitize_text_field($_POST['fww_tmdb_id']);
            update_post_meta($post_id, '_fww_tmdb_id', $tmdb_id);

            // If TMDB ID is provided, fetch and cache the data
            if (!empty($tmdb_id)) {
                $tmdb_data = FWW_TMDB_API::get_movie($tmdb_id);
                if ($tmdb_data && !is_wp_error($tmdb_data)) {
                    update_post_meta($post_id, '_fww_tmdb_data', $tmdb_data);

                    // Update post title if it's auto-draft or empty
                    if (empty($post->post_title) || $post->post_title === 'Auto Draft') {
                        wp_update_post(array(
                            'ID' => $post_id,
                            'post_title' => sanitize_text_field($tmdb_data['title'])
                        ));
                    }

                    // Download and set poster as featured image
                    if (!empty($tmdb_data['poster_path'])) {
                        fww_download_and_set_poster($post_id, $tmdb_data['poster_path'], $tmdb_data['title']);
                    }
                }
            }
        }

        // Save year
        if (isset($_POST['fww_year'])) {
            $year = sanitize_text_field($_POST['fww_year']);
            update_post_meta($post_id, '_fww_year', $year);
        }

        // Save film ID (only if not empty)
        if (isset($_POST['fww_film_id']) && !empty(trim($_POST['fww_film_id']))) {
            $film_id = sanitize_text_field($_POST['fww_film_id']);
            update_post_meta($post_id, '_fww_film_id', $film_id);
        }
    }
}

// Initialize
FWW_Admin_Metaboxes::init();
