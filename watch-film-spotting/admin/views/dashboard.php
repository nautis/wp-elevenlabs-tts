<?php
/**
 * Admin: Dashboard view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>WatchSpotting Dashboard</h1>
    
    <div class="ws-admin-cards">
        <div class="ws-admin-card">
            <h2>Pending Comments</h2>
            <p class="ws-big-number"><?php echo (int) $pending_count; ?></p>
            <?php if ($pending_count > 0): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=watchspotting-moderation')); ?>" class="button button-primary">
                Review Comments
            </a>
            <?php else: ?>
            <p>All caught up!</p>
            <?php endif; ?>
        </div>
        
        <div class="ws-admin-card">
            <h2>Quick Links</h2>
            <ul>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=watchspotting-moderation')); ?>">Moderation Queue</a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=watchspotting-settings')); ?>">Settings</a></li>
            </ul>
        </div>
        
        <div class="ws-admin-card">
            <h2>Shortcodes</h2>
            <ul>
                <li><code>[ws_search]</code> - Search form</li>
                <li><code>[ws_browse type="actor"]</code> - Browse by actor</li>
                <li><code>[ws_browse type="brand"]</code> - Browse by brand</li>
                <li><code>[ws_browse type="film"]</code> - Browse by film</li>
                <li><code>[ws_recent limit="10"]</code> - Recent sightings</li>
                <li><code>[ws_top_voted limit="20"]</code> - Top voted</li>
                <li><code>[ws_sighting id="123"]</code> - Single sighting</li>
                <li><code>[ws_user_profile]</code> - User profile</li>
            </ul>
        </div>
    </div>
</div>

<style>
.ws-admin-cards {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 20px;
}
.ws-admin-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    min-width: 250px;
    flex: 1;
}
.ws-admin-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.ws-big-number {
    font-size: 48px;
    font-weight: bold;
    margin: 20px 0;
}
.ws-admin-card code {
    background: #f0f0f0;
    padding: 2px 6px;
    font-size: 12px;
}
</style>
