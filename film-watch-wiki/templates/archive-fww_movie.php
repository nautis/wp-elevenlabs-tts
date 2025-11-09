<?php
/**
 * Archive template for Movies
 * Simple alphabetical list like IWMDB
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

    <header class="page-header">
        <h1 class="page-title">Films</h1>
        <p class="archive-description">Browse all films.</p>
    </header>

    <?php
    // Get all movies
    $movies = get_posts(array(
        'post_type' => 'fww_movie',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => 'publish'
    ));

    // Group movies by first letter
    $grouped_movies = array();
    foreach ($movies as $movie) {
        $first_char = strtoupper(substr($movie->post_title, 0, 1));
        // Handle numeric entries
        if (is_numeric($first_char)) {
            $first_char = '#';
        }
        if (!isset($grouped_movies[$first_char])) {
            $grouped_movies[$first_char] = array();
        }
        $grouped_movies[$first_char][] = $movie;
    }

    // Sort by letter
    ksort($grouped_movies);

    // Display alphabetically
    foreach ($grouped_movies as $letter => $letter_movies) : ?>
        <div class="letter-group">
            <h2 id="<?php echo strtolower($letter); ?>"><?php echo $letter; ?></h2>
            <ul class="fww-archive-list">
                <?php foreach ($letter_movies as $movie) : ?>
                    <li>
                        <a href="<?php echo get_permalink($movie->ID); ?>">
                            <?php echo esc_html($movie->post_title); ?>
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
