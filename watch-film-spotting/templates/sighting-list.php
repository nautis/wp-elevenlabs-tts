<?php
/**
 * Template: List of sighting cards
 * 
 * Variables: $sightings, $title (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ws-sighting-list">
    <?php if (!empty($title)): ?>
    <h2 class="ws-list-title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    
    <?php if (empty($sightings)): ?>
    <p class="ws-no-results">No sightings found.</p>
    <?php else: ?>
    <div class="ws-sighting-grid">
        <?php foreach ($sightings as $sighting): ?>
            <?php ws_get_template('sighting-card.php', ['sighting' => $sighting]); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
