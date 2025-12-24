<?php
/**
 * Admin: Settings view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>WatchSpotting Settings</h1>
    
    <?php settings_errors('watchspotting'); ?>
    
    <form method="post">
        <?php wp_nonce_field('ws_save_settings', '_wpnonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">Comment Moderation</th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="comments_require_approval" 
                               value="1" 
                               <?php checked($require_approval); ?>>
                        Comments must be manually approved before appearing
                    </label>
                    <p class="description">
                        Administrators and editors can always post comments immediately.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">Items Per Page</th>
                <td>
                    <input type="number" 
                           name="items_per_page" 
                           value="<?php echo (int) $items_per_page; ?>"
                           min="1" 
                           max="100"
                           class="small-text">
                    <p class="description">
                        Number of sightings to show per page in search results and browse views.
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="ws_save_settings" class="button button-primary" value="Save Changes">
        </p>
    </form>
</div>
