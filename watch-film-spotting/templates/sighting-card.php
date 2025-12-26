<?php
/**
 * Template: Sighting card (for grids/lists)
 * 
 * Variables: $sighting
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<article class="ws-sighting-card" data-faw-id="<?php echo esc_attr($sighting->faw_id); ?>">
    <?php if ($sighting->image_url): ?>
    <div class="ws-card-image">
        <a href="<?php echo esc_url(add_query_arg('ws_sighting', $sighting->faw_id, get_permalink())); ?>">
            <img src="<?php echo esc_url(ws_get_thumbnail_url($sighting->image_url, 768)); ?>"
                 alt="<?php echo esc_attr($sighting->get_title()); ?>"
                 loading="lazy">
        </a>
    </div>
    <?php endif; ?>
    
    <div class="ws-card-content">
        <h3 class="ws-card-title">
            <a href="<?php echo esc_url(add_query_arg('ws_sighting', $sighting->faw_id, get_permalink())); ?>">
                <?php echo esc_html($sighting->actor_name); ?> in <?php echo esc_html($sighting->film_title); ?> (<?php echo esc_html($sighting->film_year); ?>)
            </a>
        </h3>
        
        <p class="ws-card-meta">
            <?php echo esc_html($sighting->brand_name); ?> <?php echo esc_html($sighting->model_reference); ?>
        </p>
        
        <div class="ws-card-footer">
            <?php if ($sighting->vote_score != 0 || $sighting->vote_count > 0): ?>
            <span class="ws-card-votes">
                <?php echo $sighting->vote_score > 0 ? '+' : ''; ?><?php echo (int) $sighting->vote_score; ?> votes
            </span>
            <?php endif; ?>
            
            <?php if ($sighting->comment_count > 0): ?>
            <span class="ws-card-comments">
                <?php echo (int) $sighting->comment_count; ?> comments
            </span>
            <?php endif; ?>
        </div>
    </div>
</article>
