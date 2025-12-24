<?php
/**
 * Template: Search form and results
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ws-search">
    <form class="ws-search-form" method="get" action="">
        <div class="ws-search-input-wrap">
            <input type="search" 
                   name="ws_query" 
                   value="<?php echo esc_attr($query); ?>"
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="ws-search-input"
                   autocomplete="off">
            <button type="submit" class="ws-search-btn">Search</button>
        </div>
        <div class="ws-search-suggestions" style="display: none;"></div>
    </form>
    
    <?php if ($results !== null): ?>
    <div class="ws-search-results">
        <p class="ws-results-info">
            Found <?php echo (int) $results['total']; ?> results for "<?php echo esc_html($query); ?>"
        </p>
        
        <?php if (!empty($results['sightings'])): ?>
        <div class="ws-sighting-grid">
            <?php foreach ($results['sightings'] as $sighting): ?>
                <?php ws_get_template('sighting-card.php', ['sighting' => $sighting]); ?>
            <?php endforeach; ?>
        </div>
        
        <?php if ($results['pages'] > 1): ?>
        <nav class="ws-pagination">
            <?php
            $current_url = remove_query_arg('ws_page');
            for ($i = 1; $i <= $results['pages']; $i++):
                $page_url = add_query_arg('ws_page', $i, $current_url);
                $is_current = $i === $results['page'];
            ?>
            <a href="<?php echo esc_url($page_url); ?>" 
               class="ws-page-link <?php echo $is_current ? 'current' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <p class="ws-no-results">No sightings match your search.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
