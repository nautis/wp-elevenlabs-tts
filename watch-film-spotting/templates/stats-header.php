<?php
/**
 * Template: Stats header with counts and browse links
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get counts from database
global $wpdb;
$search_table = ws_table(WS_TABLE_SEARCH_INDEX);

$films_count = $wpdb->get_var("SELECT COUNT(DISTINCT film_title, film_year) FROM $search_table WHERE deleted_at IS NULL");
$actors_count = $wpdb->get_var("SELECT COUNT(DISTINCT actor_name) FROM $search_table WHERE deleted_at IS NULL");
$brands_count = $wpdb->get_var("SELECT COUNT(DISTINCT brand_name) FROM $search_table WHERE deleted_at IS NULL");
$sightings_count = $wpdb->get_var("SELECT COUNT(*) FROM $search_table WHERE deleted_at IS NULL");

$base_url = get_permalink();
?>

<div class="ws-stats-header">
    <div class="ws-stats-boxes">
        <div class="ws-stat-box">
            <span class="ws-stat-number"><?php echo number_format($films_count); ?></span>
            <span class="ws-stat-label">FILMS</span>
        </div>
        <div class="ws-stat-box">
            <span class="ws-stat-number"><?php echo number_format($actors_count); ?></span>
            <span class="ws-stat-label">ACTORS</span>
        </div>
        <div class="ws-stat-box">
            <span class="ws-stat-number"><?php echo number_format($brands_count); ?></span>
            <span class="ws-stat-label">BRANDS</span>
        </div>
        <div class="ws-stat-box ws-stat-highlight">
            <span class="ws-stat-number"><?php echo number_format($sightings_count); ?></span>
            <span class="ws-stat-label">WATCHES</span>
        </div>
    </div>
    
    <div class="ws-browse-links">
        <a href="<?php echo esc_url(add_query_arg('ws_browse', 'film', $base_url)); ?>">Browse by movie</a>
        <span class="ws-separator">|</span>
        <a href="<?php echo esc_url(add_query_arg('ws_browse', 'brand', $base_url)); ?>">Browse by watch brand</a>
        <span class="ws-separator">|</span>
        <a href="<?php echo esc_url(add_query_arg('ws_browse', 'actor', $base_url)); ?>">Browse by actor</a>
    </div>
</div>
