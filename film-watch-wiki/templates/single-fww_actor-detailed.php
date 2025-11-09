<?php
/**
 * Single Actor Template
 * Displays an actor page with all their movies and watches
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // Get watch sightings for this actor
    $sightings = FWW_Sightings::get_sightings_by_actor($post_id);

    // Group sightings by movie
    $movies_data = array();
    foreach ($sightings as $sighting) {
        if (!isset($movies_data[$sighting->movie_id])) {
            $movies_data[$sighting->movie_id] = array(
                'movie_title' => $sighting->movie_title,
                'movie_id' => $sighting->movie_id,
                'watches' => array()
            );
        }
        $movies_data[$sighting->movie_id]['watches'][] = $sighting;
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

        <header class="entry-header">
            <?php if (has_post_thumbnail()) : ?>
                <div class="actor-photo">
                    <?php the_post_thumbnail('medium'); ?>
                </div>
            <?php endif; ?>

            <div class="actor-header-content">
                <h1 class="entry-title"><?php the_title(); ?></h1>

                <?php if (has_excerpt()) : ?>
                    <div class="actor-bio">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>

            <!-- Movies & Watches Section -->
            <?php if (!empty($movies_data)) : ?>
                <div class="actor-filmography">
                    <h2>Watch Sightings by Film</h2>
                    <div class="filmography-list">
                        <?php foreach ($movies_data as $movie) : ?>
                            <div class="filmography-item">
                                <h3>
                                    <a href="<?php echo get_permalink($movie['movie_id']); ?>">
                                        <?php echo esc_html($movie['movie_title']); ?>
                                    </a>
                                </h3>

                                <ul class="watches-in-film">
                                    <?php foreach ($movie['watches'] as $sighting) : ?>
                                        <li>
                                            <?php if (!empty($sighting->screenshot_url)) : ?>
                                                <div class="watch-screenshot-thumb">
                                                    <img src="<?php echo esc_url($sighting->screenshot_url); ?>"
                                                         alt="<?php echo esc_attr($sighting->watch_name); ?>"
                                                         class="fww-sighting-thumbnail" />
                                                </div>
                                            <?php endif; ?>

                                            <div class="watch-info">
                                                <a href="<?php echo get_permalink($sighting->brand_id); ?>">
                                                    <?php echo esc_html($sighting->brand_name); ?>
                                                </a>
                                                <a href="<?php echo get_permalink($sighting->watch_id); ?>">
                                                    <?php echo esc_html($sighting->watch_name); ?>
                                                </a>
                                                <?php if (!empty($sighting->character_name)) : ?>
                                                    <span class="character-note">
                                                        (as <?php echo esc_html($sighting->character_name); ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <p><em>No watch sightings documented yet for this actor.</em></p>
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
