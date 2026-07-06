<?php
require_once "header.php";
require_once "functions/users.php";
require_once "functions/posts.php";

$uid = $_SESSION['user_id'] ?? 0;

if (isset($_GET['ajax_load_more'])) {
    try {
        while (ob_get_level()) { ob_end_clean(); }
        $last_id = intval($_GET['last_id']);
        
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked,
            (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
            (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN bans b ON p.user_id = b.user_id
            WHERE b.user_id IS NULL AND p.id < ? 
            ORDER BY p.id DESC LIMIT 10");
        $stmt->execute([$uid, $uid, $last_id]);
        
        $ajax_posts = $stmt->fetchAll();
        if ($ajax_posts) {
            foreach ($ajax_posts as $post) { 
                include "components/post_item.php"; 
            }
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo "AJAX Error: " . $e->getMessage();
    }
    exit;
}

$stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
    (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
    (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked,
    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN bans b ON p.user_id = b.user_id
    WHERE b.user_id IS NULL 
    ORDER BY p.id DESC LIMIT 10");
$stmt->execute([$uid, $uid]);
$posts = $stmt->fetchAll();

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
    $userStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar FROM users u
                               LEFT JOIN bans b ON u.id = b.user_id
                               WHERE u.id != ? 
                               AND b.user_id IS NULL
                               AND u.id NOT IN (SELECT f2.following_id FROM follows f2 WHERE f2.follower_id = ?)
                               ORDER BY RAND() LIMIT 5");
    $userStmt->execute([$uid, $uid]);
} else {
    $userStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar FROM users u LEFT JOIN bans b ON u.id = b.user_id WHERE b.user_id IS NULL ORDER BY RAND() LIMIT 5");
    $userStmt->execute();
}
$recommended_users = $userStmt->fetchAll();
?>

<style>
.pfp-mini { width: 32px !important; height: 32px !important; object-fit: cover; border: 1px solid #ddd; }
.sidebar-section { margin-bottom: 25px; }
.post img { max-width: 100%; height: auto; display:block;}
.load-more-container { text-align: center; margin: 20px 0; padding: 20px; }
.post:hover { background-color: #fcfcfc; }
.post-actions { margin-top: 10px; padding-bottom: 5px; }
.post-actions a { text-decoration: none; font-size: 12px; display: flex; align-items: center; }
.post-actions img { width: 16px; height: 16px; margin-right: 8px; }
</style>

<div class="container">
    <div class="page-header">
        <h1>Explore <small>Discover what's happening</small></h1>
    </div>

    <div class="row">
        <div class="span10">
            <div id="post-feed">
                <?php if(!$posts): ?>
                    <div class="alert-message info">Nothing to see here yet.</div>
                <?php else: ?>
                    <?php foreach($posts as $post) { include "components/post_item.php"; } ?>
                <?php endif; ?>
            </div>

            <div id="feed-loader" class="load-more-container" style="display: none;">
                <p class="muted">Loading more posts...</p>
            </div>
        </div>

        <div class="span6">
            <div class="sidebar-section">
                <ul class="unstyled">
                <h3>Trending</h3>
                    <?php if($trending_tags): ?>
                        <?php foreach($trending_tags as $tag => $count): ?>
                            <li style="margin-bottom:8px;">
                                <a href="/search.php?q=<?=urlencode($tag)?>" class="label notice"><?=e($tag)?></a> 
                                <span class="muted" style="font-size:11px;"><?=number_format($count)?> posts</span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="muted">No trends yet.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3>Who to follow</h3>
                <ul class="unstyled" style="margin-top:10px;">
                    <?php foreach($recommended_users as $r_user): ?>
                        <li style="margin-bottom:12px; overflow:hidden;">
                            <img src="/images/avatars/<?= htmlspecialchars($r_user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="pfp-mini" style="float:left; margin-right:10px;">
                            <div style="float:left; line-height: 1.2;">
                                <strong><a href="/user/<?=$r_user['username']?>"><?=e(!empty($r_user['display_name']) ? $r_user['display_name'] : $r_user['username'])?></a></strong><br>
                                <span class="muted" style="font-size:11px;">@<?=e($r_user['username'])?></span><br>
                                <?php if(isLoggedIn()): ?>
                                    <?php if (isFollowing($uid, $r_user['id'])): ?>
                                        <a href="/backend/users/unfollow.php?id=<?=$r_user['id']?>&csrf=<?= $csrf ?>" class="btn small" style="margin-top:4px;">Unfollow</a>
                                    <?php else: ?>
                                        <a href="/backend/users/follow.php?id=<?=$r_user['id']?>&csrf=<?= $csrf ?>" class="btn small success" style="margin-top:4px;">Follow</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $(document).on('click', '.post', function(e) {
        if ($(e.target).is('a, button, i, img, .btn, video, source')) return;
        var postId = $(this).attr('data-id');
        if(postId) {
            window.location.href = '/post/' + postId;
        }
    });

    var isLoading = false;
    $(window).on('scroll', function() {
        if (isLoading) return;
        if ($(window).scrollTop() + $(window).height() >= $(document).height() - 300) {
            var lastId = $('.post').last().data('id'); 
            if (!lastId) return;
            
            isLoading = true;
            $('#feed-loader').css('display', 'block');
            
            $.get('explore.php', { ajax_load_more: 1, last_id: lastId }, function(data) {
                isLoading = false;
                $('#feed-loader').css('display', 'none');
                if ($.trim(data) == "") {
                    $('#post-feed').append('<div class="alert-message info" style="text-align:center;">No more posts.</div>');
                    $(window).off('scroll');
                } else {
                    $('#post-feed').append(data);
                }
            }).fail(function() {
                isLoading = false;
                $('#feed-loader').css('display', 'none');
            });
        }
    });
});
</script>

<?php require_once "footer.php"; ?>