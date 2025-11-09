<?php
/**
 * Single Movie Template
 * Displays a movie page with TMDB data and watch sightings
 */

get_header(); ?>

<!-- FWW CUSTOM TEMPLATE LOADED -->
<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();
    $movie_data = fww_get_movie_data($post_id);
    $tmdb_data = $movie_data['tmdb_data'];
    $film_id = $movie_data['film_id'];

    // Get watch sightings from new sightings table
    $watch_sightings = FWW_Sightings::get_sightings_by_movie($post_id);

    // Fallback: Get watch sightings from legacy database if no new sightings
    if (empty($watch_sightings) && !empty($film_id)) {
        $legacy_sightings = fww_get_movie_watch_sightings($film_id);
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

        <div class="movie-header-wrapper">
            <?php
            // Try featured image first, then TMDB poster
            $poster_url = null;

            if (has_post_thumbnail()) {
                $poster_url = get_the_post_thumbnail_url($post_id, 'fww-poster');
            } elseif (!empty($tmdb_data) && !empty($tmdb_data['poster_path'])) {
                // Use TMDB poster URL (w500 size for good quality)
                $poster_url = 'https://image.tmdb.org/t/p/w500' . $tmdb_data['poster_path'];
            } elseif (!empty($tmdb_data) && !empty($tmdb_data['poster_url'])) {
                // Fallback to pre-built poster_url
                $poster_url = $tmdb_data['poster_url'];
            }

            if ($poster_url) : ?>
                <div class="movie-poster">
                    <img src="<?php echo esc_url($poster_url); ?>"
                         alt="<?php echo esc_attr(get_the_title()); ?> poster"
                         class="fww-movie-poster"
                         width="250"
                         height="375" />
                </div>
            <?php endif; ?>

            <div class="movie-header-content">
                <h1 class="entry-title">
                    <?php the_title(); ?>
                    <?php if (!empty($movie_data['year'])) : ?>
                        <span class="movie-year">(<?php echo esc_html($movie_data['year']); ?>)</span>
                    <?php endif; ?>
                </h1>

                <?php if (!empty($tmdb_data['tagline'])) : ?>
                    <p class="movie-tagline"><?php echo esc_html($tmdb_data['tagline']); ?></p>
                <?php endif; ?>

                <div class="movie-meta">
                    <?php if (!empty($tmdb_data['certification'])) : ?>
                        <span class="movie-certification"><?php echo esc_html($tmdb_data['certification']); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($tmdb_data['release_date'])) : ?>
                        <span class="movie-release"><?php echo date('m/d/Y', strtotime($tmdb_data['release_date'])); ?> (US)</span>
                    <?php endif; ?>

                    <?php if (!empty($tmdb_data['genres'])) : ?>
                        <span class="movie-genres">
                            <?php
                            $genre_names = array_map(function($g) { return $g['name']; }, $tmdb_data['genres']);
                            echo esc_html(implode(', ', $genre_names));
                            ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($tmdb_data['runtime'])) : ?>
                        <span class="movie-runtime"><?php echo fww_format_runtime($tmdb_data['runtime']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($tmdb_data['overview'])) : ?>
                    <div class="movie-overview">
                        <h2>Overview</h2>
                        <p><?php echo esc_html($tmdb_data['overview']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="entry-content">

            <!-- Watch Sightings Section -->
            <?php if (!empty($watch_sightings)) : ?>
                <div class="movie-watches">
                    <h2>Watches Worn in This Film</h2>
                    <div class="watch-list">
                        <?php foreach ($watch_sightings as $sighting) : ?>
                            <div class="watch-item">
                                <?php if (!empty($sighting->screenshot_url)) : ?>
                                    <div class="watch-image">
                                        <img src="<?php echo esc_url($sighting->screenshot_url); ?>"
                                             alt="<?php echo esc_attr($sighting->actor_name . ' wearing ' . $sighting->watch_name); ?>"
                                             class="fww-sighting-screenshot" />
                                    </div>
                                <?php endif; ?>

                                <div class="watch-details">
                                    <h3 class="watch-model">
                                        <a href="<?php echo get_permalink($sighting->brand_id); ?>">
                                            <?php echo esc_html($sighting->brand_name); ?>
                                        </a>
                                        <a href="<?php echo get_permalink($sighting->watch_id); ?>">
                                            <?php echo esc_html($sighting->watch_name); ?>
                                        </a>
                                    </h3>

                                    <p class="watch-worn-by">
                                        Worn by <a href="<?php echo get_permalink($sighting->actor_id); ?>"><strong><?php echo esc_html($sighting->actor_name); ?></strong></a>
                                        <?php if (!empty($sighting->character_name)) : ?>
                                            as <em><?php echo esc_html($sighting->character_name); ?></em>
                                        <?php endif; ?>
                                    </p>

                                    <?php if (!empty($sighting->scene_description)) : ?>
                                        <div class="watch-scene">
                                            <p><?php echo esc_html($sighting->scene_description); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($sighting->verification_level)) : ?>
                                        <span class="watch-verification watch-verification-<?php echo esc_attr(strtolower($sighting->verification_level)); ?>">
                                            <?php echo esc_html(ucfirst($sighting->verification_level)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (!empty($legacy_sightings)) : ?>
                <div class="movie-watches">
                    <h2>Watches Worn in This Film (Legacy Data)</h2>
                    <div class="watch-list">
                        <?php foreach ($legacy_sightings as $sighting) : ?>
                            <div class="watch-item">
                                <div class="watch-details">
                                    <h3 class="watch-model">
                                        <?php echo esc_html($sighting->brand_name); ?>
                                        <?php if (!empty($sighting->model_reference)) : ?>
                                            <?php echo esc_html($sighting->model_reference); ?>
                                        <?php endif; ?>
                                    </h3>

                                    <p class="watch-worn-by">
                                        Worn by <strong><?php echo esc_html($sighting->actor_name); ?></strong>
                                        <?php if (!empty($sighting->character_name)) : ?>
                                            as <em><?php echo esc_html($sighting->character_name); ?></em>
                                        <?php endif; ?>
                                    </p>

                                    <?php if (!empty($sighting->verification_level)) : ?>
                                        <span class="watch-verification watch-verification-<?php echo esc_attr(strtolower($sighting->verification_level)); ?>">
                                            <?php echo esc_html($sighting->verification_level); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- .entry-content -->

    </article>

    <?php
endwhile;
?>

</div><!-- #primary -->
</main><!-- #main -->

<?php
get_sidebar();
get_footer();
