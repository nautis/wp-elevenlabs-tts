<?php
/**
 * Single Actor Template (Simple IWMDB-style)
 * Displays an actor page with watch sightings
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // Get watch sightings for this actor
    $sightings = FWW_Sightings::get_sightings_by_actor($post_id);

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
                'movie_id' => $sighting->movie_id,
                'watches' => array()
            );
        }
        $movies_data[$sighting->movie_id]['watches'][] = $sighting;
    }
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('fww-simple-layout'); ?>>

        <div class="fww-simple-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>

            <?php if (!empty($sightings)) : ?>
                <div class="fww-stats">
                    <p><strong>Total Watch Sightings:</strong> <?php echo $total_appearances; ?></p>
                    <p><strong>Films:</strong> <?php echo $total_movies; ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="entry-content">
            <?php
            $content = get_the_content();
            if (!empty(trim($content))) : ?>
                <div class="fww-description">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($movies_data)) : ?>
                <h2>Watch Sightings by Film</h2>
                <ul class="fww-simple-list">
                    <?php foreach ($movies_data as $movie) : ?>
                        <li>
                            <strong><a href="<?php echo get_permalink($movie['movie_id']); ?>">
                                <?php echo esc_html($movie['movie_title']); ?>
                            </a></strong>
                            <?php
                            $watch_list = array();
                            foreach ($movie['watches'] as $sighting) {
                                // Check if watch name already starts with brand name
                                if (stripos($sighting->watch_name, $sighting->brand_name) === 0) {
                                    // Watch name includes brand, just show watch name
                                    $watch_info = '<a href="' . get_permalink($sighting->watch_id) . '">' . esc_html($sighting->watch_name) . '</a>';
                                } else {
                                    // Watch name doesn't include brand, show both
                                    $watch_info = '<a href="' . get_permalink($sighting->brand_id) . '">' . esc_html($sighting->brand_name) . '</a> ';
                                    $watch_info .= '<a href="' . get_permalink($sighting->watch_id) . '">' . esc_html($sighting->watch_name) . '</a>';
                                }
                                if (!empty($sighting->character_name)) {
                                    $watch_info .= ' (as ' . esc_html($sighting->character_name) . ')';
                                }
                                $watch_list[] = $watch_info;
                            }
                            if (!empty($watch_list)) {
                                echo ' - ' . implode(', ', $watch_list);
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><em>No watch sightings documented yet for this actor.</em></p>
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
