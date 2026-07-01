<?php

$hasLiked = $post['has_liked'] ?? false;
$likesCount = $post['likes_count'] ?? 0;
$likeStyle = $hasLiked ? 'color:rgb(5, 190, 5); font-weight: bold;' : 'color: #657786;';

?>
<div class="row post" style="margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;" data-id="<?= $post['id'] ?>">
    <div class="span1">
        <a href="/u/<?= $post['user_id'] ?>">
            <img src="/images/avatars/<?= htmlspecialchars($post['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:43px; height:43px;">
        </a>
    </div>
    <div class="span9">
        <div style="float:right;">
            <?php 
            $isFollowing = $post['is_following'] ?? false; 
            ?>
            <?php if ($post['user_id'] != $uid): ?>
                <?php if ($isFollowing): ?>
                    <a href="/backend/users/unfollow_user.php?id=<?= $post['user_id'] ?>&csrf=<?= $csrf ?>" class="btn small">Unfollow</a>
                <?php else: ?>
                    <a href="/backend/users/follow_user.php?id=<?= $post['user_id'] ?>&csrf=<?= $csrf ?>" class="btn small success">Follow</a>
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
        
        <div class="post-actions" style="margin-top: 10px; display: flex; gap: 50px; padding-bottom: 5px;">
            <a href="/post/<?= $post['id'] ?>#reply-area" style="color:#657786;">
                <img src="/images/misc/reply.png" style="opacity:0.6"> 
                <span>Reply</span>
            </a>
            <a href="/backend/posts/like_post.php?id=<?= $post['id'] ?>&csrf=<?= $csrf ?>" style="<?= $likeStyle ?>">
                <img src="/images/misc/likes.png" style="opacity: <?= ($hasLiked ? '1' : '0.6') ?>"> 
                <span><?= intval($likesCount) ?></span>
            </a>
        </div>
    </div>
</div>