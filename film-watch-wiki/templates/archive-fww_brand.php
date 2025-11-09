<?php
/**
 * Archive template for Brands
 * Simple alphabetical list like IWMDB
 */

get_header(); ?>

<main id="main" class="site-main fww-custom-template">
<div id="primary" class="content-area fww-content-area">

    <header class="page-header">
        <h1 class="page-title">Brands</h1>
        <p class="archive-description">Browse all watch brands.</p>
    </header>

    <?php
    // Get all brands
    $brands = get_posts(array(
        'post_type' => 'fww_brand',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => 'publish'
    ));

    // Group brands by first letter
    $grouped_brands = array();
    foreach ($brands as $brand) {
        $first_char = strtoupper(substr($brand->post_title, 0, 1));
        // Handle numeric entries
        if (is_numeric($first_char)) {
            $first_char = '#';
        }
        if (!isset($grouped_brands[$first_char])) {
            $grouped_brands[$first_char] = array();
        }
        $grouped_brands[$first_char][] = $brand;
    }

    // Sort by letter
    ksort($grouped_brands);

    // Display alphabetically
    foreach ($grouped_brands as $letter => $letter_brands) : ?>
        <div class="letter-group">
            <h2 id="<?php echo strtolower($letter); ?>"><?php echo $letter; ?></h2>
            <ul class="fww-archive-list">
                <?php foreach ($letter_brands as $brand) : ?>
                    <li>
                        <a href="<?php echo get_permalink($brand->ID); ?>">
                            <?php echo esc_html($brand->post_title); ?>
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
