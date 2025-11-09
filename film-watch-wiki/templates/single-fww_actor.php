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

    // Get TMDB actor data
    $actor_data = fww_get_actor_data($post_id);
    $tmdb_data = $actor_data['tmdb_data'];

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

    <article id="post-<?php the_ID(); ?>" <?php post_class('fww-actor-layout'); ?>>

        <div class="actor-header-wrapper">
            <?php if (!empty($tmdb_data['profile_url'])) : ?>
                <div class="actor-profile">
                    <img src="<?php echo esc_url($tmdb_data['profile_url']); ?>"
                         alt="<?php echo esc_attr(get_the_title()); ?> profile photo"
                         class="actor-profile-photo"
                         width="250"
                         height="375" />
                </div>
            <?php endif; ?>

            <div class="actor-header-content">
                <h1 class="entry-title"><?php the_title(); ?></h1>

                <?php if (!empty($tmdb_data['birthday']) || !empty($tmdb_data['place_of_birth'])) : ?>
                    <div class="actor-bio-info">
                        <?php if (!empty($tmdb_data['birthday'])) :
                            $birth_date = date('F j, Y', strtotime($tmdb_data['birthday']));
                            ?>
                            <p class="actor-born">
                                <strong>Born:</strong> <?php echo esc_html($birth_date); ?>
                                <?php if (!empty($tmdb_data['place_of_birth'])) : ?>
                                    in <?php echo esc_html($tmdb_data['place_of_birth']); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($tmdb_data['deathday'])) :
                            $death_date = date('F j, Y', strtotime($tmdb_data['deathday']));
                            ?>
                            <p class="actor-died">
                                <strong>Died:</strong> <?php echo esc_html($death_date); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($tmdb_data['biography'])) : ?>
                    <div class="actor-biography">
                        <h2>Biography</h2>
                        <div id="actor-bio-<?php echo $post_id; ?>" class="bio-container" data-word-limit="200">
                            <?php echo nl2br(esc_html($tmdb_data['biography'])); ?>
                        </div>
                        <button class="bio-toggle-btn hidden" data-target="actor-bio-<?php echo $post_id; ?>" aria-expanded="false">
                            Read More
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="entry-content">
            <?php if (!empty($movies_data)) : ?>
                <h2>Films</h2>
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
