<?php
/**
 * Single Brand Template (Simple IWMDB-style)
 * Displays a brand page with watch models and appearance counts
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

<?php
while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // Get watch sightings for this brand
    $sightings = FWW_Sightings::get_sightings_by_brand($post_id);

    // Count total appearances
    $total_appearances = count($sightings);

    // Group by watch model and count appearances
    $watch_models = array();
    foreach ($sightings as $sighting) {
        if (!isset($watch_models[$sighting->watch_id])) {
            $watch_models[$sighting->watch_id] = array(
                'name' => $sighting->watch_name,
                'id' => $sighting->watch_id,
                'count' => 0
            );
        }
        $watch_models[$sighting->watch_id]['count']++;
    }

    // Sort by appearance count (descending)
    usort($watch_models, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('fww-simple-layout'); ?>>

        <div class="fww-simple-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
        </div>

        <div class="entry-content">
            <?php if (!empty($watch_models)) : ?>
                <h2>Models</h2>
                <ul class="fww-simple-list">
                    <?php
                    $brand_name = get_the_title();
                    foreach ($watch_models as $watch) :
                        // Strip brand name from watch name if it starts with it
                        $display_name = $watch['name'];
                        if (stripos($display_name, $brand_name) === 0) {
                            $display_name = trim(substr($display_name, strlen($brand_name)));
                        }
                    ?>
                        <li>
                            <a href="<?php echo get_permalink($watch['id']); ?>">
                                <?php echo esc_html($display_name); ?>
                            </a>
                            - <?php echo $watch['count']; ?> <?php echo $watch['count'] === 1 ? 'appearance' : 'appearances'; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><em>No watch models documented yet for this brand.</em></p>
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
