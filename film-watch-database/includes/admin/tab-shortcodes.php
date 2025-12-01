<?php
/**
 * Admin Tab: Shortcode Usage
 * Documentation for available shortcodes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Shortcode Usage tab content
 */
function fwd_render_tab_shortcodes() {
    ?>
    <div class="fwd-admin-tab-content" id="fwd-admin-tab-shortcodes">
        <h2>Shortcode Usage</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
            <h3>Available Shortcodes:</h3>

            <h4>[film_watch_search]</h4>
            <p>Display a search form for the database.</p>
            <code>[film_watch_search]</code>
            <p><strong>Parameters:</strong></p>
            <ul>
                <li><code>type</code> - Search type: "all", "actor", "brand", or "film" (default: "all")</li>
                <li><code>placeholder</code> - Custom placeholder text</li>
            </ul>
            <p><strong>Example:</strong> <code>[film_watch_search type="actor" placeholder="Search for an actor..."]</code></p>

            <hr>

            <h4>[film_watch_stats]</h4>
            <p>Display database statistics (film count, actor count, brand count, total entries).</p>
            <code>[film_watch_stats]</code>
            <p><strong>Parameters:</strong> None</p>

            <hr>

            <h4>[film_watch_top_brands]</h4>
            <p>Display top watch brands list by film count.</p>
            <code>[film_watch_top_brands]</code>
            <p><strong>Parameters:</strong></p>
            <ul>
                <li><code>limit</code> - Number of brands to show (default: 10)</li>
                <li><code>title</code> - Custom heading text (default: "Top Watch Brands")</li>
            </ul>
            <p><strong>Example:</strong> <code>[film_watch_top_brands limit="15" title="Most Featured Brands"]</code></p>

            <hr>

            <h4>[film_watch_actor name="Tom Cruise"]</h4>
            <p>Display watches for a specific actor.</p>
            <code>[film_watch_actor name="Tom Cruise"]</code>

            <hr>

            <h4>[film_watch_brand name="Rolex"]</h4>
            <p>Display films featuring a specific watch brand.</p>
            <code>[film_watch_brand name="Rolex"]</code>

            <hr>

            <h4>[film_watch_film title="Casino Royale"]</h4>
            <p>Display watches featured in a specific film.</p>
            <code>[film_watch_film title="Casino Royale"]</code>

            <hr>

            <h4>[film_watch_add]</h4>
            <p>Display a form to add new entries (admin only).</p>
            <code>[film_watch_add]</code>
        </div>
    </div>
    <?php
}
