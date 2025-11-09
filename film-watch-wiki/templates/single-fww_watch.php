<?php
/**
 * Single Watch Template
 * Displays a watch page with all movies and actors it appears with
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // Get watch sightings for this watch
    $sightings = FWW_Sightings::get_sightings_by_watch($post_id);

    // Group sightings by movie
    $movies_data = array();
    foreach ($sightings as $sighting) {
        if (!isset($movies_data[$sighting->movie_id])) {
            $movies_data[$sighting->movie_id] = array(
                'movie_title' => $sighting->movie_title,
                'movie_id' => $sighting->movie_id,
                'actors' => array()
            );
        }
        $movies_data[$sighting->movie_id]['actors'][] = $sighting;
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

        <header class="entry-header">
            <?php if (has_post_thumbnail()) : ?>
                <div class="watch-photo">
                    <?php the_post_thumbnail('medium'); ?>
                </div>
            <?php endif; ?>

            <div class="watch-header-content">
                <h1 class="entry-title"><?php the_title(); ?></h1>

                <?php if (has_excerpt()) : ?>
                    <div class="watch-description">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>

            <!-- Film Appearances Section -->
            <?php if (!empty($movies_data)) : ?>
                <div class="watch-appearances">
                    <h2>Appearances in Films</h2>
                    <div class="appearances-list">
                        <?php foreach ($movies_data as $movie) : ?>
                            <div class="appearance-item">
                                <h3>
                                    <a href="<?php echo get_permalink($movie['movie_id']); ?>">
                                        <?php echo esc_html($movie['movie_title']); ?>
                                    </a>
                                </h3>

                                <ul class="actors-in-film">
                                    <?php foreach ($movie['actors'] as $sighting) : ?>
                                        <li>
                                            Worn by <a href="<?php echo get_permalink($sighting->actor_id); ?>">
                                                <strong><?php echo esc_html($sighting->actor_name); ?></strong>
                                            </a>
                                            <?php if (!empty($sighting->character_name)) : ?>
                                                as <em><?php echo esc_html($sighting->character_name); ?></em>
                                            <?php endif; ?>

                                            <?php if (!empty($sighting->scene_description)) : ?>
                                                <p class="scene-note"><?php echo esc_html($sighting->scene_description); ?></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <p><em>No film appearances documented yet for this watch.</em></p>
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
