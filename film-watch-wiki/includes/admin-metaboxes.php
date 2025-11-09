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
        add_action('save_post', array(__CLASS__, 'save_watch_sightings'), 10, 2);
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

        // Watch sightings meta box
        add_meta_box(
            'fww_watch_sightings',
            'Watch Sightings',
            array(__CLASS__, 'watch_sightings_meta_box'),
            'fww_movie',
            'normal',
            'default'
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

    /**
     * Watch sightings meta box
     */
    public static function watch_sightings_meta_box($post) {
        wp_nonce_field('fww_sightings_nonce', 'fww_sightings_nonce');

        // Get existing sightings for this movie
        $sightings = FWW_Sightings::get_sightings_by_movie($post->ID);

        // Get all actors, watches, and brands for dropdowns
        $actors = get_posts(array(
            'post_type' => 'fww_actor',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $watches = get_posts(array(
            'post_type' => 'fww_watch',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $brands = get_posts(array(
            'post_type' => 'fww_brand',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ?>
        <div class="fww-sightings-metabox">

            <div id="fww-sightings-list">
                <?php if (!empty($sightings)) : ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                        <thead>
                            <tr>
                                <th>Actor</th>
                                <th>Character</th>
                                <th>Brand</th>
                                <th>Watch</th>
                                <th>Scene</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sightings as $sighting) : ?>
                                <tr data-sighting-id="<?php echo esc_attr($sighting->id); ?>">
                                    <td><?php echo esc_html($sighting->actor_name); ?></td>
                                    <td><?php echo esc_html($sighting->character_name); ?></td>
                                    <td><?php echo esc_html($sighting->brand_name); ?></td>
                                    <td><?php echo esc_html($sighting->watch_name); ?></td>
                                    <td><?php echo esc_html(wp_trim_words($sighting->scene_description, 10)); ?></td>
                                    <td>
                                        <button type="button" class="button fww-edit-sighting" data-id="<?php echo esc_attr($sighting->id); ?>">Edit</button>
                                        <button type="button" class="button fww-delete-sighting" data-id="<?php echo esc_attr($sighting->id); ?>">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="color: #666; font-style: italic;">No watch sightings added yet.</p>
                <?php endif; ?>
            </div>

            <div class="fww-add-sighting-form" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 15px;">
                <h4 style="margin-top: 0;">Add New Watch Sighting</h4>

                <table class="form-table">
                    <tr>
                        <th><label for="fww_sighting_actor">Actor *</label></th>
                        <td>
                            <select id="fww_sighting_actor" class="regular-text" required>
                                <option value="">Select Actor...</option>
                                <?php foreach ($actors as $actor) : ?>
                                    <option value="<?php echo esc_attr($actor->ID); ?>">
                                        <?php echo esc_html($actor->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">The actor who wears the watch in this film.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fww_sighting_character">Character Name</label></th>
                        <td>
                            <input type="text" id="fww_sighting_character" class="regular-text" placeholder="e.g., James Bond">
                            <p class="description">The character name (optional).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fww_sighting_brand">Watch Brand *</label></th>
                        <td>
                            <select id="fww_sighting_brand" class="regular-text" required>
                                <option value="">Select Brand...</option>
                                <?php foreach ($brands as $brand) : ?>
                                    <option value="<?php echo esc_attr($brand->ID); ?>">
                                        <?php echo esc_html($brand->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">The watch brand (e.g., Omega, Rolex).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fww_sighting_watch">Watch Model *</label></th>
                        <td>
                            <select id="fww_sighting_watch" class="regular-text" required>
                                <option value="">Select Watch...</option>
                                <?php foreach ($watches as $watch) : ?>
                                    <option value="<?php echo esc_attr($watch->ID); ?>">
                                        <?php echo esc_html($watch->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">The specific watch model.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fww_sighting_scene">Scene Description</label></th>
                        <td>
                            <textarea id="fww_sighting_scene" class="large-text" rows="3" placeholder="e.g., Casino scene, diving sequence..."></textarea>
                            <p class="description">Optional description of when the watch appears.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fww_sighting_verification">Verification</label></th>
                        <td>
                            <select id="fww_sighting_verification" class="regular-text">
                                <option value="unverified">Unverified</option>
                                <option value="verified">Verified</option>
                                <option value="confirmed">Confirmed (Official Source)</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" id="fww-add-sighting-btn" class="button button-primary">Add Watch Sighting</button>
                    <span id="fww-sighting-status" style="margin-left: 10px;"></span>
                </p>
            </div>

            <!-- Hidden field to track sightings to delete -->
            <input type="hidden" name="fww_delete_sightings" id="fww_delete_sightings" value="">
        </div>

        <style>
            .fww-sightings-metabox table th { font-weight: 600; }
            .fww-delete-sighting { color: #b32d2e; }
            .fww-delete-sighting:hover { color: #dc3232; border-color: #dc3232; }
            #fww-sighting-status.success { color: #46b450; }
            #fww-sighting-status.error { color: #dc3232; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var deletedSightings = [];

            // Add sighting
            $('#fww-add-sighting-btn').on('click', function() {
                var actor = $('#fww_sighting_actor').val();
                var character = $('#fww_sighting_character').val();
                var brand = $('#fww_sighting_brand').val();
                var watch = $('#fww_sighting_watch').val();
                var scene = $('#fww_sighting_scene').val();
                var verification = $('#fww_sighting_verification').val();

                if (!actor || !brand || !watch) {
                    $('#fww-sighting-status').removeClass('success').addClass('error').text('Please fill in all required fields.');
                    return;
                }

                var data = {
                    action: 'fww_add_sighting',
                    nonce: fwwAjax.nonce,
                    movie_id: <?php echo intval($post->ID); ?>,
                    actor_id: actor,
                    character_name: character,
                    brand_id: brand,
                    watch_id: watch,
                    scene_description: scene,
                    verification_level: verification
                };

                $.post(fwwAjax.ajaxurl, data, function(response) {
                    if (response.success) {
                        $('#fww-sighting-status').removeClass('error').addClass('success').text('Sighting added! Save the post to confirm.');

                        // Clear form
                        $('#fww_sighting_actor, #fww_sighting_brand, #fww_sighting_watch').val('');
                        $('#fww_sighting_character, #fww_sighting_scene').val('');
                        $('#fww_sighting_verification').val('unverified');

                        // Reload page to show new sighting
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $('#fww-sighting-status').removeClass('success').addClass('error').text('Error: ' + response.data);
                    }
                });
            });

            // Delete sighting
            $(document).on('click', '.fww-delete-sighting', function() {
                if (!confirm('Are you sure you want to delete this watch sighting?')) {
                    return;
                }

                var sightingId = $(this).data('id');
                var $row = $(this).closest('tr');

                var data = {
                    action: 'fww_delete_sighting',
                    nonce: fwwAjax.nonce,
                    sighting_id: sightingId
                };

                $.post(fwwAjax.ajaxurl, data, function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error deleting sighting: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Save watch sightings
     * This handles the legacy save method (now handled by AJAX)
     */
    public static function save_watch_sightings($post_id, $post) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['fww_sightings_nonce']) || !wp_verify_nonce($_POST['fww_sightings_nonce'], 'fww_sightings_nonce')) {
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

        // Sightings are now handled via AJAX, so this is just a placeholder
        // for any future direct save functionality
    }
}

// Initialize
FWW_Admin_Metaboxes::init();
