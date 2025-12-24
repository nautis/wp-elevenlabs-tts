<?php
/**
 * Admin: Moderation queue view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Comment Moderation</h1>
    
    <?php if (empty($comments)): ?>
    <p>No comments awaiting moderation. 🎉</p>
    <?php else: ?>
    
    <form method="post" id="ws-moderation-form">
        <?php wp_nonce_field('ws_bulk_moderate', '_wpnonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="ws_bulk_action" id="bulk-action-selector">
                    <option value="">Bulk Actions</option>
                    <option value="approve">Approve</option>
                    <option value="spam">Mark as Spam</option>
                    <option value="trash">Move to Trash</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo (int) $total; ?> items</span>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="manage-column">Author</th>
                    <th class="manage-column">Comment</th>
                    <th class="manage-column">In Response To</th>
                    <th class="manage-column">Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $comment): ?>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" name="comment_ids[]" value="<?php echo (int) $comment->comment_id; ?>">
                    </th>
                    <td>
                        <img src="<?php echo esc_url($comment->user_avatar_url); ?>" width="32" height="32" style="float:left; margin-right:10px;">
                        <strong><?php echo esc_html($comment->user_display_name); ?></strong>
                    </td>
                    <td>
                        <div class="ws-comment-content">
                            <?php if ($comment->comment_type !== 'general'): ?>
                            <span class="ws-type-badge"><?php echo esc_html(ucfirst($comment->comment_type)); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html(wp_trim_words($comment->content, 30)); ?>
                        </div>
                        <div class="row-actions">
                            <?php
                            $approve_url = wp_nonce_url(add_query_arg([
                                'ws_action' => 'approve',
                                'comment_id' => $comment->comment_id,
                            ]), 'ws_moderate_comment');
                            $spam_url = wp_nonce_url(add_query_arg([
                                'ws_action' => 'spam',
                                'comment_id' => $comment->comment_id,
                            ]), 'ws_moderate_comment');
                            $trash_url = wp_nonce_url(add_query_arg([
                                'ws_action' => 'trash',
                                'comment_id' => $comment->comment_id,
                            ]), 'ws_moderate_comment');
                            ?>
                            <span class="approve"><a href="<?php echo esc_url($approve_url); ?>" class="vim-a">Approve</a></span> |
                            <span class="spam"><a href="<?php echo esc_url($spam_url); ?>" class="vim-s">Spam</a></span> |
                            <span class="trash"><a href="<?php echo esc_url($trash_url); ?>" class="vim-d">Trash</a></span>
                        </div>
                    </td>
                    <td>
                        <?php if (isset($comment->sighting_context)): ?>
                        <strong><?php echo esc_html($comment->sighting_context['actor_name']); ?></strong><br>
                        <?php echo esc_html($comment->sighting_context['brand_name']); ?><br>
                        <em><?php echo esc_html($comment->sighting_context['film_title']); ?></em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html(human_time_diff(strtotime($comment->created_at), current_time('timestamp')) . ' ago'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    
    <?php endif; ?>
</div>

<style>
.ws-type-badge {
    background: #0073aa;
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    margin-right: 5px;
}
.ws-comment-content {
    margin-bottom: 5px;
}
</style>
