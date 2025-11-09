<?php
/**
 * Single Watch Template (Simple IWMDB-style)
 * Displays a watch page with film appearances
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // Get watch sightings for this watch
    $sightings = FWW_Sightings::get_sightings_by_watch($post_id);

    // Count total appearances
    $total_appearances = count($sightings);

    // Get unique movies count
    $movies = array();
    foreach ($sightings as $sighting) {
        $movies[$sighting->movie_id] = true;
    }
    $total_movies = count($movies);

    // Group sightings by movie
    $movies_data = array();
    foreach ($sightings as $sighting) {
        if (!isset($movies_data[$sighting->movie_id])) {
            $movies_data[$sighting->movie_id] = array(
                'movie_title' => $sighting->movie_title,
                'movie_year' => $sighting->movie_year,
                'movie_id' => $sighting->movie_id,
                'actors' => array()
            );
        }
        $movies_data[$sighting->movie_id]['actors'][] = $sighting;
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('fww-simple-layout'); ?>>

        <div class="fww-simple-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>

            <?php
            // Get brand info from first sighting
            $brand_name = null;
            $brand_id = null;
            if (!empty($sightings)) {
                $brand_name = $sightings[0]->brand_name;
                $brand_id = $sightings[0]->brand_id;
            }
            ?>

            <?php if (!empty($brand_name)) : ?>
                <p class="watch-brand-link">
                    <strong>Brand:</strong> <a href="<?php echo get_permalink($brand_id); ?>"><?php echo esc_html($brand_name); ?></a>
                </p>
            <?php endif; ?>
        </div>

        <div class="entry-content">
            <?php if (!empty($movies_data)) : ?>
                <h2>Films</h2>
                <ul class="fww-simple-list">
                    <?php foreach ($movies_data as $movie) : ?>
                        <li>
                            <strong><a href="<?php echo get_permalink($movie['movie_id']); ?>">
                                <?php echo esc_html($movie['movie_title']); ?>
                                <?php if (!empty($movie['movie_year'])) : ?>
                                    (<?php echo esc_html($movie['movie_year']); ?>)
                                <?php endif; ?>
                            </a></strong>
                            <?php
                            $actor_list = array();
                            foreach ($movie['actors'] as $sighting) {
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
                <p><em>No film appearances documented yet for this watch.</em></p>
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
