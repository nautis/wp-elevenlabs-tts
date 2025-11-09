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

            <?php if (!empty($watch_models)) : ?>
                <div class="fww-stats">
                    <p><strong><?php echo get_the_title(); ?></strong> has <strong><?php echo count($watch_models); ?></strong> watch <?php echo count($watch_models) === 1 ? 'model' : 'models'; ?> documented in Film Watch Wiki.</p>
                    <p><strong>Total Film Appearances:</strong> <?php echo $total_appearances; ?></p>
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

            <?php if (!empty($watch_models)) : ?>
                <h2>Watch Models</h2>
                <ul class="fww-simple-list">
                    <?php foreach ($watch_models as $watch) : ?>
                        <li>
                            <a href="<?php echo get_permalink($watch['id']); ?>">
                                <?php echo esc_html($watch['name']); ?>
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
