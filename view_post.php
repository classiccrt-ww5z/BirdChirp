<?php
require_once "database/config.php";
require_once "functions/auth.php";
require_once "functions/posts.php";
require_once "functions/users.php";

$uid = $_SESSION['user_id'] ?? 0;
$post_id = intval($_GET['id'] ?? 0);

if($post_id <= 0){
    echo "<div class='container'><div class='alert-message error'>Invalid post ID.</div></div>";
    require_once "footer.php";
    exit;
}
$stmt = $pdo->prepare("
    SELECT p.*, u.id AS user_id, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
    (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
    (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked,
    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN bans b ON p.user_id = b.user_id
    WHERE p.id = ? AND b.user_id IS NULL
    ");
$stmt->execute([$uid, $uid, $post_id]);
$post = $stmt->fetch();

if (!$post) {
    require_once "header.php";
    echo "<div class='container' style='margin-top:20px;'><div class='alert-message error'>Post not found or user is banned.</div></div>";
    require_once "footer.php";
    exit;
}

$page_title = !empty($post['display_name']) ? $post['display_name'] : $post['username'];
$hasLiked = isset($post['has_liked']) && $post['has_liked'] > 0;
$hasRetweeted = isset($post['has_retweeted']) && $post['has_retweeted'] > 0;

require_once "header.php";


$stmt = $pdo->prepare("
    SELECT r.*, u.id AS user_id, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin 
    FROM replies r 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN bans b ON r.user_id = b.user_id
    WHERE r.post_id = ? AND b.user_id IS NULL
    ORDER BY r.created_at ASC
    ");
$stmt->execute([$post_id]);
$replies = $stmt->fetchAll();
$tagStmt = $pdo->query("SELECT p.content FROM posts p LEFT JOIN bans b ON p.user_id = b.user_id WHERE p.content LIKE '%#%' AND b.user_id IS NULL ORDER BY p.created_at DESC LIMIT 50");
$all_content = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
$tags_found = [];
foreach($all_content as $text) {
    preg_match_all('/#(\w+)/', $text, $matches);
    foreach($matches[0] as $tag) { $tags_found[$tag] = ($tags_found[$tag] ?? 0) + 1; }
}
arsort($tags_found);
$trending_tags = array_slice($tags_found, 0, 5);

if (isLoggedIn()) {
    $userStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar FROM users u LEFT JOIN bans b ON u.id = b.user_id WHERE u.id != ? AND b.user_id IS NULL AND u.id NOT IN (SELECT f.following_id FROM follows f WHERE f.follower_id = ?) ORDER BY RAND() LIMIT 5");
    $userStmt->execute([$uid, $uid]);
} else {
    $userStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar FROM users u LEFT JOIN bans b ON u.id = b.user_id WHERE b.user_id IS NULL ORDER BY RAND() LIMIT 5");
    $userStmt->execute();
}
$recommended_users = $userStmt->fetchAll();
?>
<style>
.pfp-mini { width: 32px !important; height: 32px !important; object-fit: cover; border: 1px solid #ddd; }
.post-img { max-width: 100%; height: auto; display: block; margin-top: 10px; border: 1px solid #eee;}
.sidebar-section { margin-bottom: 25px; }
.reply-box { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
.reply-item { padding: 10px 0; border-top: 1px solid #eee; }
.post-content {
    white-space: normal; 
    line-height: 1.4;
    text-align: left;
}
.post-content .post-embed { margin-top: 10px; margin-bottom: 10px; }
.post-actions a { text-decoration: none; font-size: 12px; display: flex; align-items: center; }
.post-actions a.btn-like { color: #657786; }
.post-actions a.btn-like.liked { color: rgb(224, 36, 94); }
.post-actions a.btn-retweet { color: #657786; }
.post-actions a.btn-retweet.retweeted { color: rgb(23, 191, 99); }
.post-actions img { width: 16px; height: 16px; margin-right: 8px; }
</style>

<div class="container">
    <div class="page-header">
        <h1>Post <small>Discussion Thread</small></h1>
    </div>

    <div class="row">
        <div class="span10">
            <div class="row">
                <div class="span1">
                    <a href="/u/<?=intval($post['user_id'])?>">
                        <img src="/images/avatars/<?=e($post['avatar'] ?: 'default.png')?>" style="width:43px; height:43px;" class="thumbnail">
                    </a>
                </div>
                <div class="span9">
                    <div style="line-height: 1.2; margin-bottom: 10px;">
                        <strong><a href="/u/<?=intval($post['user_id'])?>"><?= !empty($post['display_name']) ? htmlspecialchars($post['display_name']) : htmlspecialchars($post['username']) ?><?php if(!empty($post['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?><?php if(!empty($post['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?></a></strong>
                        <span class="muted" style="font-size:12px; margin-left:5px; font-weight:normal;">@<?= htmlspecialchars($post['username']) ?></span>
                        <span class="muted" style="margin-left: 10px;">&middot;</span>
                        <small class="muted" style="margin-left:5px;"><?=date('M d, g:i a', strtotime($post['created_at']))?></small>
                    </div>
                    
                    <div class="post-content"><?= parsePostContent($post['content']) ?></div>

                    <?php if($post['image']): ?>
                        <img src="/images/posts/<?= htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') ?>" style="max-width:100%; max-height:400px; display:block; margin-top:10px;">
                    <?php endif; ?>

                    <?php if($post['video']): ?>
                        <video controls style="max-width:100%; height:auto; display:block; margin-top:10px;" preload="metadata">
                            <source src="/videos/posts/<?= htmlspecialchars($post['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php endif; ?>

                    <?php if (!empty($post['retweet_of_id'])): ?>
                        <?php
                        $qStmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin
                            FROM posts p JOIN users u ON p.user_id = u.id
                            LEFT JOIN bans b ON p.user_id = b.user_id
                            WHERE p.id = ? AND b.user_id IS NULL");
                        $qStmt->execute([$post['retweet_of_id']]);
                        $quotedPost = $qStmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <?php if ($quotedPost): ?>
                        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-top: 12px; background: #f9f9f9;">
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
                    <?php endif; ?>

                    <div class="post-actions" style="margin-top: 20px; display: flex; gap: 50px; padding-bottom: 5px;">
                        <a href="#reply-area" style="color:#657786; font-size:12px; display:flex; align-items:center;">
                            <?= svg_icon('chat', '', 16, 'vertical-align:middle;margin-right:4px') ?>
                            <span>Reply</span>
                        </a>

                        <a href="/backend/posts/retweet.php?id=<?= $post['id'] ?>&csrf=<?= $csrf ?>" class="btn-retweet<?= $hasRetweeted ? ' retweeted' : '' ?>" data-post-id="<?= $post['id'] ?>">
                            <?= svg_icon('loop-circular', '', 16, 'vertical-align:middle;margin-right:4px') ?>
                            <span class="count"><?= intval($post['retweet_count']) ?></span>
                        </a>
                        <a href="/?quote=<?= $post['id'] ?>" style="color:#657786;">
                            <?= svg_icon('double-quote-sans-left', '', 14, 'vertical-align:middle;margin-right:4px') ?>
                            <span>Quote</span>
                        </a>

                        <a href="/backend/posts/like_post.php?id=<?= $post['id'] ?>&csrf=<?= $csrf ?>" class="btn-like<?= $hasLiked ? ' liked' : '' ?>" data-post-id="<?= $post['id'] ?>">
                            <?= svg_icon('heart', 'heart-svg', 16, 'vertical-align:middle;margin-right:4px') ?>
                            <span class="count"><?= intval($post['likes_count']) ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <hr id="reply-area">

            <?php if(isLoggedIn()): ?>
                <div class="reply-box">
                    <form action="/backend/posts/reply.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="post_id" value="<?=intval($post_id)?>">
                        <textarea name="content" style="width:98%; height:60px; margin-bottom:10px;" placeholder="Write a reply..."></textarea>
                        <button type="submit" class="btn primary">Reply</button>
                    </form>
                </div>
            <?php endif; ?>

            <div id="replies-feed">
                <h3>Replies</h3>
                <?php if(!$replies): ?>
                    <p class="muted">No replies yet.</p>
                <?php else: ?>
                    <?php foreach($replies as $reply): ?>
                        <div class="reply-item">
                            <div class="row">
                                <div class="span1">
                                    <a href="/u/<?=intval($reply['user_id'])?>">
                                        <img src="/images/avatars/<?=e($reply['avatar'] ?: 'default.png')?>" class="pfp-mini thumbnail">
                                    </a>
                                </div>
                                <div class="span8">
                                    <div style="line-height: 1.2; margin-bottom: 5px;">
                                        <strong><a href="/u/<?=intval($reply['user_id'])?>"><?= !empty($reply['display_name']) ? htmlspecialchars($reply['display_name']) : htmlspecialchars($reply['username']) ?><?php if(!empty($reply['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?><?php if(!empty($reply['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?></a></strong>
                                        <span class="muted" style="font-size:11px; margin-left:5px; font-weight:normal;">@<?= htmlspecialchars($reply['username']) ?></span>
                                        <span class="muted" style="margin-left: 10px;">&middot;</span>
                                        <small class="muted" style="margin-left:5px;"><?=date('M d, g:i a', strtotime($reply['created_at']))?></small>
                                    </div>
                                    <div class="reply-content"><?= parsePostContent($reply['content']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="span6">
            <div class="sidebar-section">
                <h3>Trending</h3>
                <ul class="unstyled" >
                    <?php if($trending_tags): ?>
                        <?php foreach($trending_tags as $tag => $count): ?>
                            <li style="margin-bottom:8px;">
                                <a href="/search.php?q=<?=urlencode($tag)?>" class="label notice"><?=e($tag)?></a> 
                                <span class="muted" style="font-size:11px;"><?=number_format($count)?> posts</span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="sidebar-section">
                <h3>Who to follow</h3>
                <ul class="unstyled">
                    <?php foreach($recommended_users as $r_user): ?>
                        <li style="margin-bottom:12px; overflow:hidden;">
                                <img src="/images/avatars/<?= htmlspecialchars($r_user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="pfp-mini" style="float:left; margin-right:10px;">
                            <div style="float:left; line-height: 1.2;">
                                <strong><a href="/u/<?=$r_user['id']?>"><?=e(!empty($r_user['display_name']) ? $r_user['display_name'] : $r_user['username'])?></a></strong><br>
                                <span class="muted" style="font-size:11px;">@<?=e($r_user['username'])?></span><br>
                                <?php if (isFollowing($uid, $r_user['id'])): ?>
                                    <a href="/backend/users/unfollow.php?id=<?=$r_user['id']?>&csrf=<?= $csrf ?>" class="btn small" style="margin-top:4px;">Unfollow</a>
                                <?php else: ?>
                                    <a href="/backend/users/follow.php?id=<?=$r_user['id']?>&csrf=<?= $csrf ?>" class="btn small success" style="margin-top:4px;">Follow</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php"; ?>