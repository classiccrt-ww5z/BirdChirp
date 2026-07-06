<?php

$hasLiked = $post['has_liked'] ?? false;
$likesCount = $post['likes_count'] ?? 0;

$hasRetweeted = $post['has_retweeted'] ?? false;
$retweetCount = $post['retweet_count'] ?? 0;

$quotedPost = null;
if (!empty($post['retweet_of_id'])) {
    $qStmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin
        FROM posts p JOIN users u ON p.user_id = u.id
        LEFT JOIN bans b ON p.user_id = b.user_id
        WHERE p.id = ? AND b.user_id IS NULL");
    $qStmt->execute([$post['retweet_of_id']]);
    $quotedPost = $qStmt->fetch(PDO::FETCH_ASSOC);
}

?>
<div class="row post" style="margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;" data-id="<?= $post['id'] ?>">
    <div class="span1">
        <a href="/u/<?= $post['user_id'] ?>">
            <img src="/images/avatars/<?= htmlspecialchars($post['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:43px; height:43px;">
        </a>
    </div>
    <div class="span9">
        <div style="float:right;">
            <?php if ($post['user_id'] != $uid): ?>
                <?php if (isFollowing($uid, $post['user_id'])): ?>
                    <a href="/backend/users/unfollow.php?id=<?= $post['user_id'] ?>&csrf=<?= $csrf ?>" class="btn small">Unfollow</a>
                <?php else: ?>
                    <a href="/backend/users/follow.php?id=<?= $post['user_id'] ?>&csrf=<?= $csrf ?>" class="btn small success">Follow</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div style="line-height: 1.2; margin-bottom: 5px;">
            <strong><a href="/u/<?= $post['user_id'] ?>" style="text-decoration:none; color:inherit;"><?= !empty($post['display_name']) ? htmlspecialchars($post['display_name']) : htmlspecialchars($post['username']) ?><?php if(!empty($post['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?><?php if(!empty($post['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?></a></strong>
            <span class="muted" style="font-size:12px; margin-left:5px; font-weight:normal;">@<?= htmlspecialchars($post['username']) ?></span>
            <span class="muted" style="margin-left: 10px;">&middot;</span>
            <small class="muted" style="margin-left:5px;"><?= date('M d, g:i a', strtotime($post['created_at'])) ?></small>
        </div>
        
        <a href="/post/<?= $post['id'] ?>" style="text-decoration:none; color:inherit;">
            <div class="post-content" style="margin-top:5px; word-wrap: break-word;">
<?= parsePostContent($post['content']) ?>            </div>

            <?php if (!empty($post['image'])): ?>
                <a href="/post/<?= $post['id'] ?>">
                    <img src="/images/posts/<?= htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" 
                         style="margin-top:10px; max-width:100%; max-height:400px; display:block;">
                </a>
            <?php endif; ?>
            
            <?php if (!empty($post['video'])): ?>
                <video controls style="margin-top:10px; max-width:100%; height:auto; display:block;" preload="metadata">
                    <source src="/videos/posts/<?= htmlspecialchars($post['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </a>

        <?php if ($quotedPost): ?>
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 10px; margin-top: 10px; background: #f9f9f9;">
                <div style="margin-bottom: 4px;">
                    <strong><a href="/u/<?= $quotedPost['user_id'] ?>" style="text-decoration:none; color:inherit;"><?= !empty($quotedPost['display_name']) ? htmlspecialchars($quotedPost['display_name']) : htmlspecialchars($quotedPost['username']) ?></a></strong>
                    <span class="muted" style="font-size:12px;">@<?= htmlspecialchars($quotedPost['username']) ?></span>
                </div>
                <div style="font-size:13px; word-wrap: break-word;"><?= parsePostContent($quotedPost['content']) ?></div>
                <?php if (!empty($quotedPost['image'])): ?>
                    <img src="/images/posts/<?= htmlspecialchars($quotedPost['image'], ENT_QUOTES, 'UTF-8') ?>" style="max-width:100%; max-height:200px; margin-top:6px; border-radius:4px;">
                <?php endif; ?>
                <?php if (!empty($quotedPost['video'])): ?>
                    <video controls style="max-width:100%; max-height:200px; margin-top:6px; border-radius:4px;" preload="metadata">
                        <source src="/videos/posts/<?= htmlspecialchars($quotedPost['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                    </video>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../functions/polls.php'; ?>
        <?php include __DIR__ . '/poll_display.php'; ?>
        
        <div class="post-actions" style="margin-top: 10px; display: flex; gap: 50px; padding-bottom: 5px;">
            <a href="/post/<?= $post['id'] ?>#reply-area" style="color:#657786; font-size:12px; display:flex; align-items:center;">
                <?= svg_icon('chat', '', 16, 'vertical-align:middle;margin-right:4px') ?>
                <span>Reply</span>
            </a>
            <a href="/backend/posts/retweet.php?id=<?= $post['id'] ?>&csrf=<?= $csrf ?>" class="btn-retweet<?= $hasRetweeted ? ' retweeted' : '' ?>" data-post-id="<?= $post['id'] ?>">
                <?= svg_icon('loop-circular', '', 16, 'vertical-align:middle;margin-right:4px') ?>
                <span class="count"><?= intval($retweetCount) ?></span>
            </a>
            <a href="/?quote=<?= $post['id'] ?>" style="color:#657786;">
                <?= svg_icon('double-quote-sans-left', '', 14, 'vertical-align:middle;margin-right:4px') ?>
                <span>Quote</span>
            </a>
            <a href="/backend/posts/like_post.php?id=<?= $post['id'] ?>&csrf=<?= $csrf ?>" class="btn-like<?= $hasLiked ? ' liked' : '' ?>" data-post-id="<?= $post['id'] ?>">
                <?= svg_icon('heart', 'heart-svg', 16, 'vertical-align:middle;margin-right:4px') ?>
                <span class="count"><?= intval($likesCount) ?></span>
            </a>
        </div>
    </div>
</div>
