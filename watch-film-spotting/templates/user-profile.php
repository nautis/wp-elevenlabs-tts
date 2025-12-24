<?php
/**
 * Template: User profile / contributions
 * 
 * Variables: $user, $comments, $votes, $vote_count
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ws-user-profile">
    <header class="ws-profile-header">
        <div class="ws-profile-avatar">
            <?php echo get_avatar($user->ID, 96); ?>
        </div>
        <div class="ws-profile-info">
            <h1 class="ws-profile-name"><?php echo esc_html($user->display_name); ?></h1>
            <p class="ws-profile-stats">
                <span><?php echo count($comments); ?> comments</span>
                <span><?php echo (int) $vote_count; ?> votes</span>
            </p>
        </div>
    </header>
    
    <div class="ws-profile-sections">
        <!-- Comments -->
        <section class="ws-profile-section">
            <h2>Recent Comments</h2>
            <?php if (empty($comments)): ?>
            <p class="ws-empty">No comments yet.</p>
            <?php else: ?>
            <ul class="ws-contribution-list">
                <?php foreach ($comments as $comment): ?>
                <li class="ws-contribution-item">
                    <div class="ws-contribution-content">
                        <?php echo wp_trim_words(esc_html($comment->content), 20); ?>
                    </div>
                    <div class="ws-contribution-meta">
                        on <a href="<?php echo esc_url(add_query_arg('ws_sighting', $comment->faw_id, home_url())); ?>">
                            <?php echo esc_html($comment->sighting_context['actor_name']); ?> in <?php echo esc_html($comment->sighting_context['film_title']); ?>
                        </a>
                        <time><?php echo esc_html(human_time_diff(strtotime($comment->created_at), current_time('timestamp')) . ' ago'); ?></time>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        
        <!-- Votes -->
        <section class="ws-profile-section">
            <h2>Recent Votes</h2>
            <?php if (empty($votes)): ?>
            <p class="ws-empty">No votes yet.</p>
            <?php else: ?>
            <ul class="ws-contribution-list">
                <?php foreach ($votes as $vote): ?>
                <li class="ws-contribution-item">
                    <span class="ws-vote-indicator <?php echo $vote->vote > 0 ? 'up' : 'down'; ?>">
                        <?php echo $vote->vote > 0 ? '👍' : '👎'; ?>
                    </span>
                    <a href="<?php echo esc_url(add_query_arg('ws_sighting', $vote->faw_id, home_url())); ?>">
                        <?php echo esc_html($vote->actor_name); ?> wearing <?php echo esc_html($vote->brand_name); ?>
                        in <?php echo esc_html($vote->film_title); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
