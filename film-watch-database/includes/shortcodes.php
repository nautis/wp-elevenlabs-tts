<?php
/**
 * Shortcodes for Film Watch Database
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [film_watch_search]
 * Displays a search form for the database
 * Supports server-side rendering for SEO when URL parameters are present
 */
function fwd_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'all', // all, actor, brand, film
        'placeholder' => 'Search movies, actors, or watch brands...',
    ), $atts);

    // Check for URL parameters (server-side rendering for SEO)
    $search_type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
    $search_query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

    $server_rendered_results = null;

    // If we have URL parameters, perform server-side search for SEO
    // Always use unified 'all' search regardless of type parameter
    if ($search_query) {
        $server_rendered_results = fwd_query_all($search_query);
        // SEO meta tags are handled by includes/seo-handler.php
    }

    ob_start();
    ?>
    <div class="fwd-search-container" data-initial-search='<?php echo $search_type && $search_query ? esc_attr(json_encode(array('type' => $search_type, 'query' => $search_query))) : ''; ?>'>
        <div class="fwd-search-form">
            <?php if ($atts['type'] === 'all'): ?>
            <input type="hidden" id="fwd-search-type" value="all">
            <?php else: ?>
            <input type="hidden" id="fwd-search-type" value="<?php echo esc_attr($atts['type']); ?>">
            <?php endif; ?>

            <input
                type="text"
                id="fwd-search-input"
                class="fwd-input"
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                value="<?php echo esc_attr($search_query); ?>"
            >
            <button id="fwd-search-btn" class="fwd-button">Search</button>
        </div>

        <div id="fwd-search-results" class="fwd-results-container">
            <?php if ($server_rendered_results && isset($server_rendered_results['success']) && $server_rendered_results['success']): ?>
                <?php echo fwd_render_search_results($server_rendered_results, $search_type); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_search', 'fwd_search_shortcode');

/**
 * Helper function: Render search results as HTML
 * Used for both server-side rendering (SEO) and consistent output
 */
function fwd_render_search_results($data, $search_type) {
    if (!isset($data['success']) || !$data['success']) {
        return '<div class="fwd-no-results">No results found.</div>';
    }

    // Only handle unified 'all' search now
    if (isset($data['total_count'])) {
        if ($data['total_count'] === 0) {
            return '<div class="fwd-no-results">No results found.</div>';
        }
        return fwd_render_unified_results($data);
    }

    // Fallback for unexpected data format
    return '<div class="fwd-error">Invalid search results format.</div>';
}


/**
 * Helper function: Render unified search results organized by film only
 * All searches return results grouped by film (film is the unique key)
 */
function fwd_render_unified_results($data) {
    ob_start();

    // Pagination settings
    $per_page = 10; // Number of film groups per page
    $current_page = max(1, get_query_var('paged', 1));

    // Only show films section - film is always the unique key
    if (isset($data['films']) && count($data['films']) > 0):
        $all_films = $data['films'];
        $total_films = count($all_films);
        $total_watches = 0;

        // Count total watches
        foreach ($all_films as $film_group) {
            $total_watches += count($film_group['watches']);
        }

        // Calculate pagination
        $total_pages = ceil($total_films / $per_page);
        $offset = ($current_page - 1) * $per_page;
        $paged_films = array_slice($all_films, $offset, $per_page);
        ?>
    <div class="fwd-results-section">
        <h3 class="fwd-section-title">
            <?php
            $film_text = $total_films === 1 ? 'film' : 'films';
            $watch_text = $total_watches === 1 ? 'watch' : 'watches';
            echo esc_html($total_films) . ' ' . $film_text . ', ' . esc_html($total_watches) . ' ' . $watch_text;

            if ($total_pages > 1) {
                echo ' <span class="fwd-page-info">(Page ' . $current_page . ' of ' . $total_pages . ')</span>';
            }
            ?>
        </h3>
        <div class="fwd-results-list">
            <?php foreach ($paged_films as $film_group): ?>
                <div class="fwd-film-group">
                    <h4 class="fwd-film-title"><?php echo esc_html($film_group['title']); ?> (<?php echo esc_html($film_group['year']); ?>) - <?php echo count($film_group['watches']); ?> watch<?php echo count($film_group['watches']) !== 1 ? 'es' : ''; ?></h4>
                    <div class="fwd-film-watches">
                        <?php foreach ($film_group['watches'] as $watch): ?>
                            <?php echo fwd_render_entry_html($watch, 'film', null); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="fwd-pagination">
            <?php
            // Get current URL params (preserve search query)
            $url_params = array(
                'type' => isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '',
                'q' => isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : ''
            );

            // Previous button
            if ($current_page > 1):
                $prev_url = add_query_arg(array_merge($url_params, array('paged' => $current_page - 1)));
            ?>
                <a href="<?php echo esc_url($prev_url); ?>" class="fwd-page-btn fwd-prev-btn">&laquo; Previous</a>
            <?php endif; ?>

            <span class="fwd-page-numbers">
                <?php
                // Show page numbers (max 5 pages visible)
                $range = 2; // Pages to show on each side of current page
                $start = max(1, $current_page - $range);
                $end = min($total_pages, $current_page + $range);

                for ($i = $start; $i <= $end; $i++):
                    if ($i === $current_page): ?>
                        <span class="fwd-page-number fwd-current-page"><?php echo $i; ?></span>
                    <?php else:
                        $page_url = add_query_arg(array_merge($url_params, array('paged' => $i)));
                    ?>
                        <a href="<?php echo esc_url($page_url); ?>" class="fwd-page-number"><?php echo $i; ?></a>
                    <?php endif;
                endfor;
                ?>
            </span>

            <?php
            // Next button
            if ($current_page < $total_pages):
                $next_url = add_query_arg(array_merge($url_params, array('paged' => $current_page + 1)));
            ?>
                <a href="<?php echo esc_url($next_url); ?>" class="fwd-page-btn fwd-next-btn">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <div class="fwd-no-results">No results found.</div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Helper function: Render gallery using RegGallery-style markup
 */
function fwd_render_regallery($gallery_ids, $entry) {
    if (empty($gallery_ids) || !is_array($gallery_ids)) {
        return;
    }

    // Enqueue RegGallery CSS if available
    if (wp_style_is('reacg_general', 'registered')) {
        wp_enqueue_style('reacg_general');
    }

    $unique_id = 'fwd-gallery-' . md5(json_encode($gallery_ids));
    ?>
    <div class="reacg-gallery fwd-watch-gallery" id="<?php echo esc_attr($unique_id); ?>">
        <div class="reacg-thumbnails-view">
            <div class="reacg-thumbnails-grid" style="--columns: 3;">
                <?php foreach ($gallery_ids as $index => $attachment_id):
                    $attachment_id = intval($attachment_id);
                    $image_data = wp_get_attachment_image_src($attachment_id, 'medium');
                    $full_image = wp_get_attachment_image_src($attachment_id, 'full');
                    $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                    $caption = wp_get_attachment_caption($attachment_id);

                    if (!$image_data) continue;

                    $image_url = $image_data[0];
                    $full_url = $full_image[0];
                ?>
                <div class="reacg-thumbnail-item" data-index="<?php echo esc_attr($index); ?>">
                    <a href="<?php echo esc_url($full_url); ?>"
                       class="reacg-thumbnail-link"
                       data-lightbox="<?php echo esc_attr($unique_id); ?>"
                       data-title="<?php echo esc_attr($caption ? $caption : $alt_text); ?>">
                        <img src="<?php echo esc_url($image_url); ?>"
                             alt="<?php echo esc_attr($alt_text ? $alt_text : $entry['brand'] . ' ' . $entry['model']); ?>"
                             loading="lazy"
                             class="reacg-thumbnail-image">
                    </a>
                    <?php if ($caption): ?>
                    <div class="reacg-thumbnail-caption">
                        <?php echo esc_html($caption); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Helper function: Render individual entry as HTML
 */
function fwd_render_entry_html($entry, $type, $search_name = null) {
    ob_start();
    ?>
    <div class="fwd-entry" itemscope itemtype="https://schema.org/Movie">
        <?php
        // Check for RegGallery ID first (proper RegGallery integration)
        if (!empty($entry['regallery_id'])) {
            // Use RegGallery's shortcode for full functionality
            echo do_shortcode('[REACG id="' . intval($entry['regallery_id']) . '"]');
        } elseif (!empty($entry['gallery_ids'])) {
            // Fallback: render with custom gallery markup if RegGallery post doesn't exist
            $gallery_ids = json_decode($entry['gallery_ids'], true);

            if (is_array($gallery_ids) && !empty($gallery_ids)) {
                fwd_render_regallery($gallery_ids, $entry);
            }
        } elseif (!empty($entry['image_url'])) {
            // Fallback to single image_url for backwards compatibility
            ?>
        <figure>
            <img src="<?php echo esc_url($entry['image_url']); ?>"
                 alt="<?php echo esc_attr($entry['brand'] . ' ' . $entry['model']); ?>"
                 itemprop="image">
            <?php if (!empty($entry['image_caption'])): ?>
            <figcaption class="wp-element-caption"><?php echo esc_html($entry['image_caption']); ?></figcaption>
            <?php endif; ?>
        </figure>
        <?php } ?>
        </p><p>
        <p>
            The <strong class="fwd-watch" itemprop="name">
                <?php echo esc_html($entry['brand']); ?> <?php echo esc_html($entry['model']); ?>
            </strong>
            appears in the <span itemprop="copyrightYear"><?php echo esc_html($entry['year']); ?></span> film
            <strong itemprop="name"><?php echo esc_html($entry['title']); ?></strong>,
            worn by <strong itemprop="actor" itemscope itemtype="https://schema.org/Person">
                <span itemprop="name"><?php echo esc_html($entry['actor']); ?></span>
            </strong>
            as <strong><?php echo esc_html($entry['character']); ?></strong>.<?php if (!empty($entry['narrative'])): ?> <?php echo wp_kses_post($entry['narrative']); ?><?php endif; ?>
        </p>
        <?php if (!empty($entry['confidence_level']) || !empty($entry['source'])): ?>
        <p>
            <?php if (!empty($entry['confidence_level'])): ?><em class="fwd-confidence">Confidence: <?php echo esc_html($entry['confidence_level']); ?></em><?php endif; ?>
            <?php if (!empty($entry['source'])): ?>
                <a href="<?php echo esc_url($entry['source']); ?>" target="_blank" rel="noopener">Source ↗</a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [film_watch_stats]
 * Displays database statistics (counts only)
 */
function fwd_stats_shortcode($atts) {
    $stats_data = fwd_get_stats();

    if (!isset($stats_data['success']) || !$stats_data['success']) {
        return '<div class="fwd-error">Unable to load statistics.</div>';
    }

    $stats = $stats_data['stats'];

    ob_start();
    ?>
    <div class="fwd-stats-container">
        <div class="fwd-stat-grid">
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['films']); ?></div>
                <div class="fwd-stat-label">Films</div>
            </div>
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['actors']); ?></div>
                <div class="fwd-stat-label">Actors</div>
            </div>
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['brands']); ?></div>
                <div class="fwd-stat-label">Brands</div>
            </div>
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['entries']); ?></div>
                <div class="fwd-stat-label">Watches</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_stats', 'fwd_stats_shortcode');

/** Shortcode: [film_watch_recently_added]
 * Displays three columns showing recently added watches, actors, and films
 * Only shows on landing page (hides when search is active)
 */
function fwd_recently_added_shortcode($atts) {
    global $wpdb;
    
    // Hide when search is active
    $search_type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
    $search_query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    
    if ($search_type && $search_query) {
        return '';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 10,
    ), $atts);
    
    $limit = intval($atts['limit']);
    
    $base_url = get_permalink();
    
    $recent_watches = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT b.brand_name, w.model_reference, faw.faw_id
         FROM {$wpdb->prefix}fwd_film_actor_watch faw
         JOIN {$wpdb->prefix}fwd_watches w ON faw.watch_id = w.watch_id
         JOIN {$wpdb->prefix}fwd_brands b ON w.brand_id = b.brand_id
         ORDER BY faw.created_at DESC
         LIMIT %d",
        $limit
    ), ARRAY_A);
    
    $recent_actors = $wpdb->get_results($wpdb->prepare(
        "SELECT actor_name, actor_id
         FROM {$wpdb->prefix}fwd_actors
         ORDER BY actor_id DESC
         LIMIT %d",
        $limit
    ), ARRAY_A);
    
    $recent_films = $wpdb->get_results($wpdb->prepare(
        "SELECT title, year, film_id
         FROM {$wpdb->prefix}fwd_films
         ORDER BY film_id DESC
         LIMIT %d",
        $limit
    ), ARRAY_A);
    
    ob_start();
    ?>
    <div class="fwd-recently-added-container">
        <div class="fwd-recently-added-grid">
            <div class="fwd-recently-added-column">
                <h3>Recently Added Watches</h3>
                <ul class="fwd-recently-added-list">
                    <?php foreach ($recent_watches as $watch): 
                        $watch_url = add_query_arg(array('type' => 'all', 'q' => $watch['brand_name']), $base_url);
                    ?>
                    <li><a href="<?php echo esc_url($watch_url); ?>"><?php echo esc_html($watch['brand_name'] . ' ' . $watch['model_reference']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="fwd-recently-added-column">
                <h3>Recently Added Actors</h3>
                <ul class="fwd-recently-added-list">
                    <?php foreach ($recent_actors as $actor): 
                        $actor_url = add_query_arg(array('type' => 'all', 'q' => $actor['actor_name']), $base_url);
                    ?>
                    <li><a href="<?php echo esc_url($actor_url); ?>"><?php echo esc_html($actor['actor_name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="fwd-recently-added-column">
                <h3>Recently Added Movies</h3>
                <ul class="fwd-recently-added-list">
                    <?php foreach ($recent_films as $film): 
                        $film_url = add_query_arg(array('type' => 'all', 'q' => $film['title']), $base_url);
                    ?>
                    <li><a href="<?php echo esc_url($film_url); ?>"><?php echo esc_html($film['title'] . ' (' . $film['year'] . ')'); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_recently_added', 'fwd_recently_added_shortcode');
