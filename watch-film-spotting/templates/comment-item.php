<?php
/**
 * Template: Single comment item
 *
 * Variables: $comment, $depth
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_reply = $depth > 0;
?>

<div class="ws-comment <?php echo $is_reply ? 'ws-comment-reply' : ''; ?> ws-comment-type-<?php echo esc_attr($comment->comment_type); ?>"
     id="comment-<?php echo esc_attr($comment->comment_id); ?>"
     data-comment-id="<?php echo esc_attr($comment->comment_id); ?>">

    <div class="ws-comment-avatar">
        <img src="<?php echo esc_url($comment->user_avatar_url); ?>"
             alt="<?php echo esc_attr($comment->user_display_name); ?>"
             width="48" height="48">
    </div>

    <div class="ws-comment-body">
        <header class="ws-comment-header">
            <span class="ws-comment-author"><?php echo esc_html($comment->user_display_name); ?></span>
            <?php if ($comment->comment_type !== 'general'): ?>
            <span class="ws-comment-type-badge ws-type-<?php echo esc_attr($comment->comment_type); ?>">
                <?php echo esc_html(ucfirst($comment->comment_type)); ?>
            </span>
            <?php endif; ?>
            <time class="ws-comment-date" datetime="<?php echo esc_attr($comment->created_at); ?>">
                <?php echo esc_html(human_time_diff(strtotime($comment->created_at), current_time('timestamp')) . ' ago'); ?>
            </time>
            <?php if ($comment->status === 'pending'): ?>
            <span class="ws-comment-pending">Awaiting approval</span>
            <?php endif; ?>
        </header>

        <div class="ws-comment-content">
            <?php echo wp_kses_post(wpautop($comment->content)); ?>
        </div>

        <?php if (!$is_reply && is_user_logged_in()): ?>
        <footer class="ws-comment-footer">
            <button type="button" class="ws-reply-btn" data-comment-id="<?php echo esc_attr($comment->comment_id); ?>">
                Reply
            </button>
        </footer>
        <?php endif; ?>

        <!-- Reply form (hidden by default) -->
        <?php if (!$is_reply && is_user_logged_in()): ?>
        <form class="ws-reply-form" data-parent-id="<?php echo esc_attr($comment->comment_id); ?>" style="display: none;">
            <textarea name="content" rows="2" placeholder="Write a reply..."></textarea>
            <div class="ws-reply-actions">
                <button type="submit" class="ws-btn ws-btn-small">Reply</button>
                <button type="button" class="ws-btn ws-btn-small ws-btn-cancel">Cancel</button>
            </div>
        </form>
        <?php endif; ?>

        <!-- Nested replies -->
        <?php if (!empty($comment->replies)): ?>
        <div class="ws-comment-replies">
            <?php foreach ($comment->replies as $reply): ?>
                <?php ws_get_template('comment-item.php', ['comment' => $reply, 'depth' => 1]); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
