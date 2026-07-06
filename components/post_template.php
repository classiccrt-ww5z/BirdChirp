
<div class="row post" style="margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;" data-id="<?= $post['id'] ?>">
    <div class="span1">
        <a href="/u/<?= $post['user_id'] ?>">
            <img src="/images/avatars/<?= htmlspecialchars($post['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:49px; height:48px;">
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
        
        <strong><a href="/u/<?= $post['user_id'] ?>"><?= e($post['username']) ?></a></strong>
        <small class="muted"><?= date('M d, g:i a', strtotime($post['created_at'])) ?></small>
        
        <div class="post-content" style="margin-top:5px; word-wrap: break-word;">
            <?= parsePostContent(e($post['content'])) ?>
        </div>

        <?php if ($post['image']): ?>
            <img src="/images/posts/<?= htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="margin-top:10px;">
        <?php endif; ?>
    </div>
</div>