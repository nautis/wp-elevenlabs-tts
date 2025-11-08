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
 */
function fwd_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'all', // all, actor, brand, film
        'placeholder' => 'Search movies, actors, or watch brands...',
    ), $atts);

    ob_start();
    ?>
    <div class="fwd-search-container">
        <div class="fwd-search-form">
            <?php if ($atts['type'] === 'all'): ?>
            <select id="fwd-search-type" class="fwd-select">
                <option value="actor">Actor</option>
                <option value="brand">Watch Brand</option>
                <option value="film">Film Title</option>
            </select>
            <?php else: ?>
            <input type="hidden" id="fwd-search-type" value="<?php echo esc_attr($atts['type']); ?>">
            <?php endif; ?>

            <input
                type="text"
                id="fwd-search-input"
                class="fwd-input"
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
            >
            <button id="fwd-search-btn" class="fwd-button">Search</button>
        </div>

        <div id="fwd-search-results" class="fwd-results-container"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_search', 'fwd_search_shortcode');

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
                <div class="fwd-stat-label">Total Entries</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_stats', 'fwd_stats_shortcode');

/**
 * Shortcode: [film_watch_top_brands]
 * Displays top watch brands by film count
 */
function fwd_top_brands_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 10,
        'title' => 'Top Watch Brands',
    ), $atts);

    $stats_data = fwd_get_stats();

    if (!isset($stats_data['success']) || !$stats_data['success']) {
        return '<div class="fwd-error">Unable to load top brands.</div>';
    }

    $stats = $stats_data['stats'];

    if (empty($stats['top_brands'])) {
        return '<div class="fwd-no-results">No brand data available.</div>';
    }

    // Limit to specified number
    $brands = array_slice($stats['top_brands'], 0, $atts['limit']);

    ob_start();
    ?>
    <div class="fwd-top-brands-container">
        <div class="fwd-top-brands">
            <?php if (!empty($atts['title'])): ?>
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            <div class="fwd-brands-list">
                <?php foreach ($brands as $index => $brand): ?>
                <div class="fwd-brand-item">
                    <span class="fwd-brand-rank"><?php echo ($index + 1); ?>.</span>
                    <span class="fwd-brand-name"><?php echo esc_html($brand['brand']); ?></span>
                    <span class="fwd-brand-count"><?php echo esc_html($brand['count']); ?> film<?php echo $brand['count'] !== 1 ? 's' : ''; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_top_brands', 'fwd_top_brands_shortcode');


/**
 * Shortcode: [film_watch_actor name="Tom Cruise"]
 * Displays watches for a specific actor
 */
function fwd_actor_shortcode($atts) {
    $atts = shortcode_atts(array(
        'name' => '',
    ), $atts);

    if (empty($atts['name'])) {
        return '<div class="fwd-error">Please specify an actor name using the "name" attribute.</div>';
    }

    $result = fwd_query_actor($atts['name']);

    if (!isset($result['success']) || !$result['success']) {
        return '<div class="fwd-error">Unable to load data for ' . esc_html($atts['name']) . '.</div>';
    }

    if ($result['count'] === 0) {
        return '<div class="fwd-no-results">No watches found for ' . esc_html($atts['name']) . '.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-actor-results">
        <h3><?php echo esc_html($atts['name']); ?>'s Watches in Film</h3>
        <p>Found <?php echo esc_html($result['count']); ?> result(s)</p>

        <?php foreach ($result['films'] as $film): ?>
        <div class="fwd-entry">
            <?php if (!empty($film['image_url'])): ?>
            <figure>
                <img src="<?php echo esc_url($film['image_url']); ?>"
                     alt="<?php echo esc_attr($film['brand'] . ' ' . $film['model']); ?>">
                <?php
                $caption = fwd_get_image_caption($film['image_url']);
                if ($caption): ?>
                <figcaption class="wp-element-caption"><?php echo esc_html($caption); ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endif; ?>

            <p>
                The <strong class="fwd-watch"><?php echo esc_html($film['brand']); ?> <?php echo esc_html($film['model']); ?></strong>
                appears in the <?php echo esc_html($film['year']); ?> film
                <strong><?php echo esc_html($film['title']); ?></strong>,
                worn by <strong><?php echo esc_html($film['actor']); ?></strong>
                as <strong><?php echo esc_html($film['character']); ?></strong>.
                <?php if (!empty($film['narrative'])): ?>
                    <?php echo wp_kses_post($film['narrative']); ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($film['confidence_level']) || !empty($film['source'])): ?>
            <p class="fwd-metadata" style="font-size: 0.9em; color: #666;">
                <?php if (!empty($film['confidence_level'])): ?>
                    <span class="fwd-confidence">Confidence score: <?php echo esc_html($film['confidence_level']); ?></span>
                <?php endif; ?>
                <?php if (!empty($film['source'])): ?>
                    <?php if (!empty($film['confidence_level'])) echo ' | '; ?>
                    <a href="<?php echo esc_url($film['source']); ?>" target="_blank" rel="noopener">Source ↗</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <hr>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_actor', 'fwd_actor_shortcode');

/**
 * Shortcode: [film_watch_brand name="Rolex"]
 * Displays films featuring a specific watch brand
 */
function fwd_brand_shortcode($atts) {
    $atts = shortcode_atts(array(
        'name' => '',
    ), $atts);

    if (empty($atts['name'])) {
        return '<div class="fwd-error">Please specify a brand name using the "name" attribute.</div>';
    }

    $result = fwd_query_brand($atts['name']);

    if (!isset($result['success']) || !$result['success']) {
        return '<div class="fwd-error">Unable to load data for ' . esc_html($atts['name']) . '.</div>';
    }

    if ($result['count'] === 0) {
        return '<div class="fwd-no-results">No films found featuring ' . esc_html($atts['name']) . ' watches.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-brand-results">
        <h3><?php echo esc_html($atts['name']); ?> in Film</h3>
        <p>Found <?php echo esc_html($result['count']); ?> result(s)</p>

        <?php foreach ($result['films'] as $film): ?>
        <div class="fwd-entry">
            <?php if (!empty($film['image_url'])): ?>
            <figure>
                <img src="<?php echo esc_url($film['image_url']); ?>"
                     alt="<?php echo esc_attr($atts['name'] . ' ' . $film['model']); ?>">
                <?php
                $caption = fwd_get_image_caption($film['image_url']);
                if ($caption): ?>
                <figcaption class="wp-element-caption"><?php echo esc_html($caption); ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endif; ?>

            <p>
                The <strong class="fwd-watch"><?php echo esc_html($atts['name']); ?> <?php echo esc_html($film['model']); ?></strong>
                appears in the <?php echo esc_html($film['year']); ?> film
                <strong><?php echo esc_html($film['title']); ?></strong>,
                worn by <strong><?php echo esc_html($film['actor']); ?></strong>
                as <strong><?php echo esc_html($film['character']); ?></strong>.
                <?php if (!empty($film['narrative'])): ?>
                    <?php echo wp_kses_post($film['narrative']); ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($film['confidence_level']) || !empty($film['source'])): ?>
            <p class="fwd-metadata" style="font-size: 0.9em; color: #666;">
                <?php if (!empty($film['confidence_level'])): ?>
                    <span class="fwd-confidence">Confidence score: <?php echo esc_html($film['confidence_level']); ?></span>
                <?php endif; ?>
                <?php if (!empty($film['source'])): ?>
                    <?php if (!empty($film['confidence_level'])) echo ' | '; ?>
                    <a href="<?php echo esc_url($film['source']); ?>" target="_blank" rel="noopener">Source ↗</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <hr>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_brand', 'fwd_brand_shortcode');

/**
 * Shortcode: [film_watch_film title="Casino Royale"]
 * Displays watches featured in a specific film
 */
function fwd_film_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
    ), $atts);

    if (empty($atts['title'])) {
        return '<div class="fwd-error">Please specify a film title using the "title" attribute.</div>';
    }

    $result = fwd_query_film($atts['title']);

    if (!isset($result['success']) || !$result['success']) {
        return '<div class="fwd-error">Unable to load data for ' . esc_html($atts['title']) . '.</div>';
    }

    if ($result['count'] === 0) {
        return '<div class="fwd-no-results">No watches found in ' . esc_html($atts['title']) . '.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-film-results">
        <h3>Watches in <?php echo esc_html($atts['title']); ?></h3>
        <p>Found <?php echo esc_html($result['count']); ?> watch(es)</p>

        <?php foreach ($result['watches'] as $watch): ?>
        <div class="fwd-entry">
            <?php if (!empty($watch['image_url'])): ?>
            <figure>
                <img src="<?php echo esc_url($watch['image_url']); ?>"
                     alt="<?php echo esc_attr($watch['brand'] . ' ' . $watch['model']); ?>">
                <?php
                $caption = fwd_get_image_caption($watch['image_url']);
                if ($caption): ?>
                <figcaption class="wp-element-caption"><?php echo esc_html($caption); ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endif; ?>

            <p>
                The <strong class="fwd-watch"><?php echo esc_html($watch['brand']); ?> <?php echo esc_html($watch['model']); ?></strong>
                appears in the <?php echo esc_html($watch['year']); ?> film
                <strong><?php echo esc_html($watch['title']); ?></strong>,
                worn by <strong><?php echo esc_html($watch['actor']); ?></strong>
                as <strong><?php echo esc_html($watch['character']); ?></strong>.
                <?php if (!empty($watch['narrative'])): ?>
                    <?php echo wp_kses_post($watch['narrative']); ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($watch['confidence_level']) || !empty($watch['source'])): ?>
            <p class="fwd-metadata" style="font-size: 0.9em; color: #666;">
                <?php if (!empty($watch['confidence_level'])): ?>
                    <span class="fwd-confidence">Confidence score: <?php echo esc_html($watch['confidence_level']); ?></span>
                <?php endif; ?>
                <?php if (!empty($watch['source'])): ?>
                    <?php if (!empty($watch['confidence_level'])) echo ' | '; ?>
                    <a href="<?php echo esc_url($watch['source']); ?>" target="_blank" rel="noopener">Source ↗</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <hr>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_film', 'fwd_film_shortcode');

/**
 * Shortcode: [film_watch_add]
 * Admin-only form to add new entries (requires manage_options capability)
 */
function fwd_add_shortcode($atts) {
    if (!current_user_can('manage_options')) {
        return '<div class="fwd-error">You do not have permission to add entries.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-add-container">
        <h3>Add New Entry</h3>

        <!-- Tab Navigation -->
        <div class="fwd-tabs">
            <button class="fwd-tab-btn active" data-tab="form">Structured Form</button>
            <button class="fwd-tab-btn" data-tab="quick">Quick Entry</button>
            <button class="fwd-tab-btn" data-tab="bulk">Bulk CSV Import</button>
        </div>

        <!-- Tab 1: Structured Form -->
        <div class="fwd-tab-content active" id="fwd-tab-form">
            <div class="fwd-examples">
                <strong>Examples:</strong><br>
                • "Jakob Cedergren wears Citizen Eco-Drive Divers 200M in The Guilty (2018)"<br>
                • "Tom Cruise wears Breitling Navitimer in Top Gun: Maverick (2022)"<br>
                • "In Interstellar (2014), Matthew McConaughey as Cooper wears Hamilton Khaki Pilot"
            </div>

            <div class="fwd-form-group">
                <label for="fwd-entry-text">Entry Text:</label>
                <input
                    type="text"
                    id="fwd-entry-text"
                    class="fwd-input"
                    placeholder="Actor wears Brand Model in Film (Year)"
                >
            </div>

            <div class="fwd-form-group">
                <label for="fwd-narrative">Narrative Role (optional):</label>
                <textarea
                    id="fwd-narrative"
                    class="fwd-textarea"
                    placeholder="Describe the watch's role in the film..."
                ></textarea>
            </div>

            <div class="fwd-form-group">
                <label for="fwd-image-url">Image URL (optional):</label>
                <input
                    type="url"
                    id="fwd-image-url"
                    class="fwd-input"
                    placeholder="https://example.com/watch-image.jpg"
                >
                <button type="button" id="fwd-upload-image-btn" class="button">Upload Image</button>
            </div>

            <div class="fwd-form-group">
                <label for="fwd-source">Source (optional):</label>
                <input
                    type="text"
                    id="fwd-source"
                    class="fwd-input"
                    placeholder="e.g., IMDB, Watch Spotting Blog, etc."
                >
            </div>

            <button id="fwd-add-btn" class="fwd-button">Add to Database</button>
            <div id="fwd-add-result" class="fwd-result"></div>
        </div>

        <!-- Tab 2: Quick Entry (Pipe-Delimited) -->
        <div class="fwd-tab-content" id="fwd-tab-quick">
            <div class="fwd-examples">
                <strong>Format:</strong> Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Source<br><br>
                <strong>Example:</strong><br>
                <code>Ed Harris|Virgil "Bud" Brigman|Seiko|6309 "Turtle"|The Abyss|1989|Bud's trusted dive watch|https://example.com/seiko.jpg|https://example.com</code>
            </div>

            <div class="fwd-form-group">
                <label for="fwd-quick-entry">Pipe-Delimited Entry:</label>
                <textarea
                    id="fwd-quick-entry"
                    class="fwd-textarea"
                    rows="3"
                    placeholder="Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Source"
                ></textarea>
            </div>

            <button id="fwd-quick-add-btn" class="fwd-button">Add to Database</button>
            <div id="fwd-quick-result" class="fwd-result"></div>
        </div>

        <!-- Tab 3: Bulk CSV Import -->
        <div class="fwd-tab-content" id="fwd-tab-bulk">
            <div class="fwd-examples">
                <strong>CSV Format (pipe-delimited, one entry per line):</strong><br>
                <code>Actor|Character|Brand|Model|Title|Year|Narrative|ImageURL|Source</code><br><br>
                <strong>Example CSV:</strong><br>
                <textarea readonly class="fwd-textarea" rows="3" style="font-family: monospace;">Ed Harris|Virgil "Bud" Brigman|Seiko|6309 "Turtle"|The Abyss|1989|Bud's trusted dive watch|https://example.com/seiko.jpg|https://example.com
Sean Connery|James Bond|Rolex|Submariner 6538|Dr. No|1962|Bond's iconic watch|https://example.com/rolex.jpg|https://example.com</textarea>
            </div>

            <div class="fwd-form-group">
                <label for="fwd-csv-file">Upload CSV File:</label>
                <input
                    type="file"
                    id="fwd-csv-file"
                    class="fwd-input"
                    accept=".csv,.txt"
                >
                <p><small>Maximum file size: 5MB. Use pipe (|) delimiters, UTF-8 encoding.</small></p>
            </div>

            <button id="fwd-csv-upload-btn" class="fwd-button">Import CSV</button>
            <div id="fwd-csv-result" class="fwd-result"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_add', 'fwd_add_shortcode');

/**
 * Shortcode: [film_watch_movies_list]
 * Displays an alphabetical list of all movies in the database
 */
function fwd_movies_list_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(array(
        'base_url' => '/watches-in-film/',
    ), $atts);

    $table_films = $wpdb->prefix . 'fwd_films';
    $table_film_actor_watch = $wpdb->prefix . 'fwd_film_actor_watch';

    // Get all distinct films that have at least one watch entry
    $films = $wpdb->get_results("
        SELECT DISTINCT f.title, f.year
        FROM {$table_films} f
        INNER JOIN {$table_film_actor_watch} faw ON f.film_id = faw.film_id
        ORDER BY f.title ASC
    ", ARRAY_A);

    if (empty($films)) {
        return '<p>No movies found in the database.</p>';
    }

    // Group films by first letter
    $grouped_films = array();
    foreach ($films as $film) {
        $first_letter = strtoupper(substr($film['title'], 0, 1));

        // Handle numbers and special characters
        if (is_numeric($first_letter)) {
            $first_letter = '#';
        } elseif (!preg_match('/[A-Z]/', $first_letter)) {
            $first_letter = '#';
        }

        if (!isset($grouped_films[$first_letter])) {
            $grouped_films[$first_letter] = array();
        }

        $grouped_films[$first_letter][] = $film;
    }

    // Sort groups by key
    ksort($grouped_films);

    ob_start();
    ?>
    <div class="fwd-movies-list-container">
        <style>
            .fwd-movies-list-container {
                max-width: 1200px;
                margin: 0 auto;
            }
            .fwd-movies-letter-group {
                margin-bottom: 3rem;
            }
            .fwd-movies-letter-header {
                font-size: 2rem;
                font-weight: 700;
                color: var(--fwd-primary, #2c3e50);
                border-bottom: 3px solid var(--fwd-primary, #2c3e50);
                padding-bottom: 0.5rem;
                margin-bottom: 1.5rem;
            }
            .fwd-movies-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 0.75rem;
            }
            .fwd-movie-item {
                padding: 0.5rem;
            }
            .fwd-movie-link {
                color: var(--fwd-text, #333);
                text-decoration: none;
                transition: color 0.2s ease;
            }
            .fwd-movie-link:hover {
                color: var(--fwd-primary, #2c3e50);
                text-decoration: underline;
            }
            .fwd-movie-year {
                color: var(--fwd-text-light, #666);
                font-size: 0.9em;
            }

            @media (max-width: 768px) {
                .fwd-movies-grid {
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                }
                .fwd-movies-letter-header {
                    font-size: 1.5rem;
                }
            }
        </style>

        <?php foreach ($grouped_films as $letter => $films_in_group): ?>
            <div class="fwd-movies-letter-group">
                <h2 class="fwd-movies-letter-header"><?php echo esc_html($letter); ?></h2>
                <div class="fwd-movies-grid">
                    <?php foreach ($films_in_group as $film):
                        $search_url = add_query_arg(
                            array(
                                'type' => 'film',
                                'q' => urlencode($film['title'])
                            ),
                            $atts['base_url']
                        );
                    ?>
                        <div class="fwd-movie-item">
                            <a href="<?php echo esc_url($search_url); ?>" class="fwd-movie-link">
                                <?php echo esc_html($film['title']); ?>
                                <span class="fwd-movie-year">(<?php echo esc_html($film['year']); ?>)</span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_movies_list', 'fwd_movies_list_shortcode');
