<?php
/**
 * Single Brand Template
 * Displays a brand page with all films where the brand appears
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // Get watch sightings for this brand
    $sightings = FWW_Sightings::get_sightings_by_brand($post_id);

    // Group sightings by movie
    $movies_data = array();
    foreach ($sightings as $sighting) {
        if (!isset($movies_data[$sighting->movie_id])) {
            $movies_data[$sighting->movie_id] = array(
                'movie_title' => $sighting->movie_title,
                'movie_id' => $sighting->movie_id,
                'sightings' => array()
            );
        }
        $movies_data[$sighting->movie_id]['sightings'][] = $sighting;
    }

    // Count unique watches and actors
    $unique_watches = array();
    $unique_actors = array();
    foreach ($sightings as $sighting) {
        $unique_watches[$sighting->watch_id] = $sighting->watch_name;
        $unique_actors[$sighting->actor_id] = $sighting->actor_name;
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

        <header class="entry-header">
            <?php if (has_post_thumbnail()) : ?>
                <div class="brand-logo">
                    <?php the_post_thumbnail('medium'); ?>
                </div>
            <?php endif; ?>

            <div class="brand-header-content">
                <h1 class="entry-title"><?php the_title(); ?></h1>

                <?php if (!empty($movies_data)) : ?>
                    <div class="brand-stats">
                        <span class="stat-item"><?php echo count($movies_data); ?> films</span>
                        <span class="stat-item"><?php echo count($unique_watches); ?> watches</span>
                        <span class="stat-item"><?php echo count($unique_actors); ?> actors</span>
                    </div>
                <?php endif; ?>

                <?php if (has_excerpt()) : ?>
                    <div class="brand-description">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>

            <!-- Film Appearances Section -->
            <?php if (!empty($movies_data)) : ?>
                <div class="brand-appearances">
                    <h2>Appearances in Films</h2>
                    <div class="appearances-list">
                        <?php foreach ($movies_data as $movie) : ?>
                            <div class="appearance-item">
                                <h3>
                                    <a href="<?php echo get_permalink($movie['movie_id']); ?>">
                                        <?php echo esc_html($movie['movie_title']); ?>
                                    </a>
                                </h3>

                                <ul class="sightings-in-film">
                                    <?php foreach ($movie['sightings'] as $sighting) : ?>
                                        <li>
                                            <a href="<?php echo get_permalink($sighting->watch_id); ?>">
                                                <strong><?php echo esc_html($sighting->watch_name); ?></strong>
                                            </a>
                                            worn by
                                            <a href="<?php echo get_permalink($sighting->actor_id); ?>">
                                                <?php echo esc_html($sighting->actor_name); ?>
                                            </a>
                                            <?php if (!empty($sighting->character_name)) : ?>
                                                <span class="character-note">
                                                    (as <?php echo esc_html($sighting->character_name); ?>)
                                                </span>
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
                <p><em>No film appearances documented yet for this brand.</em></p>
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
