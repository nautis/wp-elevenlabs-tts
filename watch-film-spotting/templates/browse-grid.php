<?php
/**
 * Template: Browse by category
 *
 * Variables: $type, $value, $list, $results
 */

if (!defined('ABSPATH')) {
    exit;
}

$type_labels = [
    'actor' => 'Actors',
    'film' => 'Films',
    'brand' => 'Brands',
];

$type_singular = [
    'actor' => 'actor',
    'film' => 'movie',
    'brand' => 'watch brand',
];
?>

<div class="ws-browse ws-browse-<?php echo esc_attr($type); ?>">

    <?php if ($value && $results): ?>
    <!-- Showing results for selected value -->
    <header class="ws-browse-header">
        <a href="<?php echo esc_url(remove_query_arg('ws_value')); ?>" class="ws-back-link">
            &larr; All <?php echo esc_html($type_labels[$type]); ?>
        </a>
        <h2 class="ws-section-title">
            <?php echo esc_html($value); ?>
            <span class="ws-count">(<?php echo (int) $results['total']; ?> sightings)</span>
        </h2>
    </header>

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

    <?php elseif ($list): ?>
    <!-- Showing alphabetical list of categories to browse -->

    <?php
    // Group items by first letter
    $grouped = [];
    foreach ($list as $item) {
        if ($type === 'film') {
            $name = $item->film_title;
        } elseif ($type === 'actor') {
            $name = $item->actor_name;
        } else {
            $name = $item->brand_name;
        }

        // Get first character, handle "The " prefix
        $sort_name = preg_replace('/^(The|A|An)\s+/i', '', $name);
        $first_char = strtoupper(mb_substr($sort_name, 0, 1));

        // Group numbers under #
        if (is_numeric($first_char)) {
            $first_char = '#';
        }

        if (!isset($grouped[$first_char])) {
            $grouped[$first_char] = [];
        }
        $grouped[$first_char][] = $item;
    }

    // Sort groups alphabetically (# first, then A-Z)
    uksort($grouped, function($a, $b) {
        if ($a === '#') return -1;
        if ($b === '#') return 1;
        return strcmp($a, $b);
    });
    ?>

    <?php foreach ($grouped as $letter => $items): ?>
    <div class="ws-browse-letter-group">
        <h3 class="ws-letter-header"><?php echo esc_html($letter); ?></h3>
        <div class="ws-browse-list">
            <?php foreach ($items as $item): ?>
            <?php
            if ($type === 'film') {
                $label = $item->film_title . ' (' . $item->film_year . ')';
                $url_value = $item->film_title;
            } elseif ($type === 'actor') {
                $label = $item->actor_name;
                $url_value = $item->actor_name;
            } else {
                $label = $item->brand_name;
                $url_value = $item->brand_name;
            }
            ?>
            <a href="<?php echo esc_url(add_query_arg(['ws_browse' => $type, 'ws_value' => $url_value], remove_query_arg(['ws_browse', 'ws_value']))); ?>" class="ws-browse-item">
                <?php echo esc_html($label); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <p class="ws-no-results">No items to display.</p>
    <?php endif; ?>

</div>
