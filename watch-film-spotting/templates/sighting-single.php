<?php
/**
 * Template: Single sighting view
 * 
 * Variables: $sighting, $comments
 * Override: Copy to theme/watch-film-spotting/sighting-single.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<article class="ws-sighting ws-sighting-single" data-faw-id="<?php echo esc_attr($sighting->faw_id); ?>">
    
    <header class="ws-sighting-header">
        <p class="ws-sighting-intro">
            The <strong><?php echo esc_html($sighting->brand_name); ?> <?php echo esc_html($sighting->model_reference); ?></strong>
            appears in the <?php echo esc_html($sighting->film_year); ?> film
            <em><?php echo esc_html($sighting->film_title); ?></em>,
            worn by <?php echo esc_html($sighting->actor_name); ?> as <?php echo esc_html($sighting->character_name); ?>.
        </p>
    </header>
    
    <?php if ($sighting->image_url): ?>
    <div class="ws-sighting-image">
        <img src="<?php echo esc_url($sighting->image_url); ?>" 
             alt="<?php echo esc_attr($sighting->get_title()); ?>"
             loading="lazy">
    </div>
    <?php endif; ?>
    
    <div class="ws-sighting-content">
        <?php if ($sighting->narrative_role): ?>
        <div class="ws-sighting-narrative">
            <?php echo wp_kses_post(wpautop($sighting->narrative_role)); ?>
        </div>
        <?php endif; ?>
        
        <div class="ws-sighting-meta">
            <?php if ($sighting->editorial_confidence): ?>
            <span class="ws-confidence ws-confidence-<?php echo esc_attr($sighting->editorial_confidence); ?>">
                <?php echo esc_html(ucfirst($sighting->editorial_confidence)); ?> Match
            </span>
            <?php endif; ?>
            
            <?php if ($sighting->source_url): ?>
            <a href="<?php echo esc_url($sighting->source_url); ?>" class="ws-source-link" target="_blank" rel="noopener">
                Source
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Voting Section -->
    <div class="ws-voting" data-faw-id="<?php echo esc_attr($sighting->faw_id); ?>">
        <span class="ws-vote-label">Is this identification correct?</span>
        <div class="ws-vote-buttons">
            <button type="button" 
                    class="ws-vote-btn ws-vote-up <?php echo $sighting->user_vote === 1 ? 'active' : ''; ?>"
                    data-vote="1"
                    <?php echo !is_user_logged_in() ? 'disabled title="Log in to vote"' : ''; ?>>
                <span class="ws-vote-icon">👍</span>
                <span class="ws-vote-text">Yes</span>
            </button>
            <button type="button" 
                    class="ws-vote-btn ws-vote-down <?php echo $sighting->user_vote === -1 ? 'active' : ''; ?>"
                    data-vote="-1"
                    <?php echo !is_user_logged_in() ? 'disabled title="Log in to vote"' : ''; ?>>
                <span class="ws-vote-icon">👎</span>
                <span class="ws-vote-text">No</span>
            </button>
        </div>
        <div class="ws-vote-score">
            <span class="ws-score-value"><?php echo (int) $sighting->vote_score; ?></span>
            <span class="ws-score-label">votes</span>
        </div>
    </div>
    
    <!-- Comments Section -->
    <section class="ws-comments" id="ws-comments">
        <h2 class="ws-comments-title">
            Discussion
            <span class="ws-comment-count">(<?php echo count($comments); ?>)</span>
        </h2>
        
        <?php if (is_user_logged_in()): ?>
        <form class="ws-comment-form" data-faw-id="<?php echo esc_attr($sighting->faw_id); ?>">
            <div class="ws-form-group">
                <label for="ws-comment-content">Add a comment</label>
                <textarea id="ws-comment-content" 
                          name="content" 
                          rows="3" 
                          placeholder="Share your thoughts, sources, or alternative identifications..."></textarea>
            </div>
            <div class="ws-form-row">
                <div class="ws-form-group">
                    <label for="ws-comment-type">Comment type</label>
                    <select id="ws-comment-type" name="comment_type">
                        <option value="general">General</option>
                        <option value="correction">Correction</option>
                        <option value="source">Source/Evidence</option>
                        <option value="alternative">Alternative ID</option>
                    </select>
                </div>
                <button type="submit" class="ws-btn ws-btn-primary">Submit</button>
            </div>
        </form>
        <?php else: ?>
        <p class="ws-login-prompt">
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Log in</a> to join the discussion.
        </p>
        <?php endif; ?>
        
        <div class="ws-comment-list">
            <?php if (empty($comments)): ?>
            <p class="ws-no-comments">No comments yet. Be the first to share your thoughts!</p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <?php ws_get_template('comment-item.php', ['comment' => $comment, 'depth' => 0]); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    
</article>
