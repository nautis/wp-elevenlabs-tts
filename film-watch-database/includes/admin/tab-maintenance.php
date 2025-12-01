<?php
/**
 * Admin Tab: Database Maintenance
 * Tools for cleaning, merging, and maintaining the database
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Database Maintenance tab content
 */
function fwd_render_tab_maintenance() {
    global $wpdb;
    ?>
    <div class="fwd-admin-tab-content" id="fwd-admin-tab-maintenance">
        <h2>Database Maintenance</h2>

        <!-- Global Cleanup Tool -->
        <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0;">Clean Escaped Characters</h3>
            <p>Remove backslashes before quotes from ALL database tables (films, actors, brands, watches, characters, narratives).</p>
            <p><strong>This is safe to run.</strong> It will clean entries like <code>Tom\'s</code> → <code>Tom's</code> and <code>\"stealth\"</code> → <code>"stealth"</code>.</p>
            <form method="post" style="margin: 10px 0;">
                <?php wp_nonce_field('fwd_global_cleanup_action', 'fwd_global_cleanup_nonce'); ?>
                <button type="submit" name="fwd_global_cleanup" class="button button-primary">Clean All Escaped Data Now</button>
            </form>
        </div>

        <!-- Rebuild Search Index -->
        <div style="background: #e7f5ff; padding: 20px; border-left: 4px solid #0073aa; margin: 20px 0;">
            <h3 style="margin-top: 0;">Rebuild Search Index</h3>
            <p>Rebuild the search index to ensure all entries are searchable. Run this if new entries aren't showing up in search results.</p>
            <p><strong>This is safe to run.</strong> It will recreate the search index from the current database.</p>
            <form method="post" style="margin: 10px 0;">
                <?php wp_nonce_field('fwd_rebuild_index_action', 'fwd_rebuild_index_nonce'); ?>
                <button type="submit" name="fwd_rebuild_index" class="button button-primary">Rebuild Search Index Now</button>
            </form>
        </div>

        <!-- Fix Actor/Character Split -->
        <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0;">Fix Actor Names with Embedded Characters</h3>
            <p>Find and fix actor names that contain character names like <code>"Rudolph Valentino as Ahmed Ben Hassan"</code></p>
            <p><strong>This will:</strong></p>
            <ul>
                <li>Split actor names into proper actor + character fields</li>
                <li>Merge with existing clean actor records if they exist</li>
                <li>Update all related entries to use correct actor and character IDs</li>
            </ul>
            <form method="post" style="margin: 10px 0;">
                <?php wp_nonce_field('fwd_fix_actor_split_action', 'fwd_fix_actor_split_nonce'); ?>
                <button type="submit" name="fwd_fix_actor_split" class="button button-primary">Fix Actor/Character Split Now</button>
            </form>
        </div>

        <!-- Duplicate Finder -->
        <div style="background: #e7f5ff; padding: 20px; border-left: 4px solid #0073aa; margin: 20px 0;">
            <h3 style="margin-top: 0;">Find and Remove Duplicates</h3>
            <p>Find entries where the same actor playing the same character appears multiple times in the same film.</p>

            <?php
            $duplicate_groups = fwd_find_duplicates();

            if (empty($duplicate_groups)) {
                echo '<p style="color: #46b450;"><strong>✓ No duplicates found!</strong></p>';
            } else {
                echo '<p style="color: #dc3232;"><strong>Found ' . count($duplicate_groups) . ' duplicate groups:</strong></p>';

                foreach ($duplicate_groups as $group_index => $group) {
                    echo '<div style="background: #fff; padding: 15px; margin: 15px 0; border: 1px solid #ccd0d4;">';
                    echo '<h4 style="margin-top: 0;">' . esc_html($group[0]['actor_name']) . ' as ' .
                         esc_html($group[0]['character_name']) .
                         ' in ' . esc_html($group[0]['title']) . ' (' . esc_html($group[0]['year']) . ')</h4>';

                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>ID</th><th>Watch</th><th>Narrative</th><th>Image</th><th>Source</th><th>Action</th>';
                    echo '</tr></thead><tbody>';

                    foreach ($group as $entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($entry['faw_id']) . '</td>';
                        echo '<td><strong>' . esc_html($entry['brand_name']) . ' ' . esc_html($entry['model_reference']) . '</strong></td>';
                        echo '<td>' . (empty($entry['narrative_role']) ? '<em>none</em>' : esc_html(substr($entry['narrative_role'], 0, 100)) . '...') . '</td>';
                        echo '<td>' . (empty($entry['image_url']) ? '<em>none</em>' : '✓ Has image') . '</td>';
                        echo '<td>' . (empty($entry['source_url']) ? '<em>none</em>' : '<a href="' . esc_url($entry['source_url']) . '" target="_blank">link</a>') . '</td>';
                        echo '<td>';
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_duplicate_action', 'fwd_duplicate_nonce');
                        echo '<input type="hidden" name="faw_id" value="' . esc_attr($entry['faw_id']) . '">';
                        echo '<button type="submit" name="fwd_delete_duplicate" class="button button-small" onclick="return confirm(\'Delete entry #' . esc_js($entry['faw_id']) . '?\');">Delete</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <!-- Brand Merge Tool -->
        <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ff9800; margin: 20px 0;">
            <h3 style="margin-top: 0;">Merge Brands</h3>
            <p>Merge one brand into another. All watches from the source brand will be reassigned to the target brand, and the source brand will be deleted.</p>

            <?php
            $table_brands = $wpdb->prefix . 'fwd_brands';
            $table_watches = $wpdb->prefix . 'fwd_watches';
            $all_brands = $wpdb->get_results("
                SELECT b.brand_id, b.brand_name, COUNT(w.watch_id) as watch_count
                FROM {$table_brands} b
                LEFT JOIN {$table_watches} w ON b.brand_id = w.brand_id
                GROUP BY b.brand_id, b.brand_name
                ORDER BY b.brand_name ASC
            ");

            if (!empty($all_brands)):
            ?>
            <form method="post" style="margin: 10px 0;">
                <?php wp_nonce_field('fwd_brand_merge_action', 'fwd_brand_merge_nonce'); ?>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th scope="row"><label for="wrong_brand_id">Merge this brand:</label></th>
                        <td>
                            <select name="wrong_brand_id" id="wrong_brand_id" required style="min-width: 300px;">
                                <option value="">-- Select source brand to merge --</option>
                                <?php foreach ($all_brands as $brand): ?>
                                    <option value="<?php echo esc_attr($brand->brand_id); ?>">
                                        <?php echo esc_html($brand->brand_name); ?> (<?php echo intval($brand->watch_count); ?> watches)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="correct_brand_id">Into this brand:</label></th>
                        <td>
                            <select name="correct_brand_id" id="correct_brand_id" required style="min-width: 300px;">
                                <option value="">-- Select target brand --</option>
                                <?php foreach ($all_brands as $brand): ?>
                                    <option value="<?php echo esc_attr($brand->brand_id); ?>">
                                        <?php echo esc_html($brand->brand_name); ?> (<?php echo intval($brand->watch_count); ?> watches)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p style="margin-top: 15px;">
                    <button type="submit" name="fwd_merge_brands" class="button button-primary" onclick="return confirm('Are you sure you want to merge these brands? This cannot be undone.');">
                        Merge Brands
                    </button>
                </p>
            </form>
            <script>
            jQuery(document).ready(function($) {
                $('#wrong_brand_id, #correct_brand_id').on('change', function() {
                    var wrongId = $('#wrong_brand_id').val();
                    var correctId = $('#correct_brand_id').val();
                    if (wrongId && correctId && wrongId === correctId) {
                        alert('Cannot merge a brand into itself. Please select different brands.');
                        $(this).val('');
                    }
                });
            });
            </script>
            <?php else: ?>
                <p style="color: #46b450;"><strong>✓ No brands found in database.</strong></p>
            <?php endif; ?>
        </div>

        <!-- Duplicate Watch Models Finder -->
        <div style="background: #e7f5ff; padding: 20px; border-left: 4px solid #2196f3; margin: 20px 0;">
            <h3 style="margin-top: 0;">Find and Merge Duplicate Watch Models</h3>
            <p>Find watches with the same brand and similar model names that appear in the same film (e.g., "Chronograph" and "Chronograph 1").</p>

            <?php
            $duplicate_watches = fwd_find_duplicate_watches();

            if (empty($duplicate_watches)) {
                echo '<p style="color: #46b450;"><strong>✓ No duplicate watch models found!</strong></p>';
            } else {
                echo '<p style="color: #dc3232;"><strong>Found ' . count($duplicate_watches) . ' potential duplicate watch pairs:</strong></p>';

                foreach ($duplicate_watches as $pair) {
                    $watch1 = $pair[0];
                    $watch2 = $pair[1];

                    echo '<div style="background: #fff; padding: 15px; margin: 15px 0; border: 1px solid #ccd0d4;">';
                    echo '<h4 style="margin-top: 0;">' . esc_html($watch1->brand_name) . '</h4>';

                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>Watch ID</th><th>Model</th><th>Used in # Films</th><th>Action</th>';
                    echo '</tr></thead><tbody>';

                    // Watch 1
                    echo '<tr>';
                    echo '<td>' . esc_html($watch1->watch_id) . '</td>';
                    echo '<td><strong>' . esc_html($watch1->model_reference) . '</strong></td>';
                    echo '<td>' . esc_html($watch1->usage_count) . '</td>';
                    echo '<td>';
                    if ($watch1->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_watch_merge_action', 'fwd_watch_merge_nonce');
                        echo '<input type="hidden" name="wrong_watch_id" value="' . esc_attr($watch1->watch_id) . '">';
                        echo '<input type="hidden" name="correct_watch_id" value="' . esc_attr($watch2->watch_id) . '">';
                        echo '<button type="submit" name="fwd_merge_watches" class="button button-small" onclick="return confirm(\'Merge into #' . esc_js($watch2->watch_id) . '?\');">Merge into #' . esc_html($watch2->watch_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    // Watch 2
                    echo '<tr>';
                    echo '<td>' . esc_html($watch2->watch_id) . '</td>';
                    echo '<td><strong>' . esc_html($watch2->model_reference) . '</strong></td>';
                    echo '<td>' . esc_html($watch2->usage_count) . '</td>';
                    echo '<td>';
                    if ($watch2->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_watch_merge_action', 'fwd_watch_merge_nonce');
                        echo '<input type="hidden" name="wrong_watch_id" value="' . esc_attr($watch2->watch_id) . '">';
                        echo '<input type="hidden" name="correct_watch_id" value="' . esc_attr($watch1->watch_id) . '">';
                        echo '<button type="submit" name="fwd_merge_watches" class="button button-small" onclick="return confirm(\'Merge into #' . esc_js($watch1->watch_id) . '?\');">Merge into #' . esc_html($watch1->watch_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    echo '</tbody></table>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <!-- Duplicate Characters Finder -->
        <div style="background: #f3e5f5; padding: 20px; border-left: 4px solid #9c27b0; margin: 20px 0;">
            <h3 style="margin-top: 0;">Find and Merge Duplicate Characters</h3>
            <p>Find characters with similar or identical names that appear in the same film. Review appearances to confirm they're the same character.</p>

            <?php
            $duplicate_characters = fwd_find_duplicate_characters();

            if (empty($duplicate_characters)) {
                echo '<p style="color: #46b450;"><strong>✓ No duplicate characters found!</strong></p>';
            } else {
                echo '<p style="color: #dc3232;"><strong>Found ' . count($duplicate_characters) . ' potential duplicate character pairs:</strong></p>';

                foreach ($duplicate_characters as $item) {
                    $char1 = $item['char1'];
                    $char1_films = $item['char1_films'];
                    $char2 = $item['char2'];
                    $char2_films = $item['char2_films'];

                    echo '<div style="background: #fff; padding: 15px; margin: 15px 0; border: 1px solid #ccd0d4;">';

                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th style="width: 80px;">ID</th><th>Character Name</th><th>Appears In (Actor / Film / Year)</th><th style="width: 150px;">Action</th>';
                    echo '</tr></thead><tbody>';

                    // Character 1
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($char1->character_id) . '</strong></td>';
                    echo '<td><strong>' . esc_html($char1->character_name) . '</strong></td>';
                    echo '<td>';
                    if (!empty($char1_films)) {
                        echo '<ul style="margin: 0; padding-left: 20px;">';
                        foreach ($char1_films as $film) {
                            echo '<li>' . esc_html($film->actor_name) . ' / ' . esc_html($film->title) . ' (' . esc_html($film->year) . ')</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em>No films</em>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($char1->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_character_merge_action', 'fwd_character_merge_nonce');
                        echo '<input type="hidden" name="wrong_character_id" value="' . esc_attr($char1->character_id) . '">';
                        echo '<input type="hidden" name="correct_character_id" value="' . esc_attr($char2->character_id) . '">';
                        echo '<button type="submit" name="fwd_merge_characters" class="button button-small" onclick="return confirm(\'Merge into #' . esc_js($char2->character_id) . '?\');">Merge into #' . esc_html($char2->character_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    // Character 2
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($char2->character_id) . '</strong></td>';
                    echo '<td><strong>' . esc_html($char2->character_name) . '</strong></td>';
                    echo '<td>';
                    if (!empty($char2_films)) {
                        echo '<ul style="margin: 0; padding-left: 20px;">';
                        foreach ($char2_films as $film) {
                            echo '<li>' . esc_html($film->actor_name) . ' / ' . esc_html($film->title) . ' (' . esc_html($film->year) . ')</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em>No films</em>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($char2->usage_count > 0) {
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('fwd_character_merge_action', 'fwd_character_merge_nonce');
                        echo '<input type="hidden" name="wrong_character_id" value="' . esc_attr($char2->character_id) . '">';
                        echo '<input type="hidden" name="correct_character_id" value="' . esc_attr($char1->character_id) . '">';
                        echo '<button type="submit" name="fwd_merge_characters" class="button button-small" onclick="return confirm(\'Merge into #' . esc_js($char1->character_id) . '?\');">Merge into #' . esc_html($char1->character_id) . '</button>';
                        echo '</form>';
                    } else {
                        echo '<em>Unused</em>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    echo '</tbody></table>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
    <?php
}
