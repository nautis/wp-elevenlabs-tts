<?php
/**
 * Archive template for Actors
 * Simple alphabetical list like IWMDB
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

    <header class="page-header">
        <h1 class="page-title">Actors</h1>
        <p class="archive-description">Browse all actors.</p>
    </header>

    <?php
    // Get all actors
    $actors = get_posts(array(
        'post_type' => 'fww_actor',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => 'publish'
    ));

    // Group actors by first letter
    $grouped_actors = array();
    foreach ($actors as $actor) {
        $first_char = strtoupper(substr($actor->post_title, 0, 1));
        // Handle numeric entries
        if (is_numeric($first_char)) {
            $first_char = '#';
        }
        if (!isset($grouped_actors[$first_char])) {
            $grouped_actors[$first_char] = array();
        }
        $grouped_actors[$first_char][] = $actor;
    }

    // Sort by letter
    ksort($grouped_actors);

    // Display alphabetically
    foreach ($grouped_actors as $letter => $letter_actors) : ?>
        <div class="letter-group">
            <h2 id="<?php echo strtolower($letter); ?>"><?php echo $letter; ?></h2>
            <ul class="fww-archive-list">
                <?php foreach ($letter_actors as $actor) : ?>
                    <li>
                        <a href="<?php echo get_permalink($actor->ID); ?>">
                            <?php echo esc_html($actor->post_title); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

</div><!-- #primary -->
</main><!-- #main -->

<?php
get_sidebar();
get_footer();
