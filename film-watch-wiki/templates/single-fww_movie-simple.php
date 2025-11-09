<?php
/**
 * Single Movie Template (Simple IWMDB-style)
 * Displays a movie page with watch sightings
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();
    $movie_data = fww_get_movie_data($post_id);
    $tmdb_data = $movie_data['tmdb_data'];

    // Get watch sightings
    $watch_sightings = FWW_Sightings::get_sightings_by_movie($post_id);

    // Count totals
    $total_sightings = count($watch_sightings);

    // Get unique watches and actors
    $unique_watches = array();
    $unique_actors = array();
    foreach ($watch_sightings as $sighting) {
        $unique_watches[$sighting->watch_id] = true;
        $unique_actors[$sighting->actor_id] = true;
    }
    $total_watches = count($unique_watches);
    $total_actors = count($unique_actors);

    // Group sightings by watch
    $watches_data = array();
    foreach ($watch_sightings as $sighting) {
        if (!isset($watches_data[$sighting->watch_id])) {
            $watches_data[$sighting->watch_id] = array(
                'watch_name' => $sighting->watch_name,
                'watch_id' => $sighting->watch_id,
                'brand_name' => $sighting->brand_name,
                'brand_id' => $sighting->brand_id,
                'actors' => array()
            );
        }
        $watches_data[$sighting->watch_id]['actors'][] = $sighting;
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('fww-simple-layout'); ?>>

        <div class="fww-simple-header">
            <h1 class="entry-title">
                <?php the_title(); ?>
                <?php if (!empty($movie_data['year'])) : ?>
                    <span class="movie-year">(<?php echo esc_html($movie_data['year']); ?>)</span>
                <?php endif; ?>
            </h1>

            <?php if (!empty($tmdb_data['tagline'])) : ?>
                <p class="movie-tagline"><?php echo esc_html($tmdb_data['tagline']); ?></p>
            <?php endif; ?>

            <?php if (!empty($watch_sightings)) : ?>
                <div class="fww-stats">
                    <p><strong>Watch Sightings:</strong> <?php echo $total_sightings; ?></p>
                    <p><strong>Unique Watches:</strong> <?php echo $total_watches; ?></p>
                    <p><strong>Actors:</strong> <?php echo $total_actors; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($tmdb_data)) : ?>
                <div class="movie-meta-simple">
                    <?php if (!empty($tmdb_data['release_date'])) : ?>
                        <p><strong>Released:</strong> <?php echo date('F j, Y', strtotime($tmdb_data['release_date'])); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($tmdb_data['runtime'])) : ?>
                        <p><strong>Runtime:</strong> <?php echo fww_format_runtime($tmdb_data['runtime']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($tmdb_data['genres'])) : ?>
                        <p><strong>Genres:</strong> <?php
                            $genre_names = array_map(function($g) { return $g['name']; }, $tmdb_data['genres']);
                            echo esc_html(implode(', ', $genre_names));
                        ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="entry-content">
            <?php if (!empty($tmdb_data['overview'])) : ?>
                <div class="fww-description">
                    <h2>Overview</h2>
                    <p><?php echo esc_html($tmdb_data['overview']); ?></p>
                </div>
            <?php endif; ?>

            <?php
            $content = get_the_content();
            if (!empty(trim($content))) : ?>
                <div class="fww-description">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($watches_data)) : ?>
                <h2>Watches in This Film</h2>
                <ul class="fww-simple-list">
                    <?php foreach ($watches_data as $watch) : ?>
                        <li>
                            <?php
                            // Check if watch name already starts with brand name to avoid duplication
                            if (stripos($watch['watch_name'], $watch['brand_name']) === 0) {
                                // Watch name includes brand, just show watch name
                                ?>
                                <strong><a href="<?php echo get_permalink($watch['watch_id']); ?>">
                                    <?php echo esc_html($watch['watch_name']); ?>
                                </a></strong>
                                <?php
                            } else {
                                // Watch name doesn't include brand, show both
                                ?>
                                <strong><a href="<?php echo get_permalink($watch['brand_id']); ?>">
                                    <?php echo esc_html($watch['brand_name']); ?>
                                </a>
                                <a href="<?php echo get_permalink($watch['watch_id']); ?>">
                                    <?php echo esc_html($watch['watch_name']); ?>
                                </a></strong>
                                <?php
                            }

                            // Show actors who wore this watch
                            $actor_list = array();
                            foreach ($watch['actors'] as $sighting) {
                                $actor_info = '<a href="' . get_permalink($sighting->actor_id) . '">' . esc_html($sighting->actor_name) . '</a>';
                                if (!empty($sighting->character_name)) {
                                    $actor_info .= ' (as ' . esc_html($sighting->character_name) . ')';
                                }
                                $actor_list[] = $actor_info;
                            }
                            if (!empty($actor_list)) {
                                echo ' - Worn by ' . implode(', ', $actor_list);
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><em>No watch sightings documented yet for this film.</em></p>
            <?php endif; ?>
        </div>

    </article>

    <?php
endwhile;
?>

</div><!-- #primary -->
</main><!-- #main -->

<?php
get_sidebar();
get_footer();
