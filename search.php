<?php
require_once "database/config.php";
require_once "functions/posts.php";
require_once "functions/users.php";

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$uid = $_SESSION['user_id'] ?? 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'default'; 
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date'], ENT_QUOTES, 'UTF-8') : '';
require_once 'functions/security.php';
$csrf = generateCSRF();
require_once 'functions/icons.php';

$svgReply = svg_icon('chat', '', 16, 'vertical-align:middle;margin-right:4px');
$svgRetweet = svg_icon('loop-circular', '', 16, 'vertical-align:middle;margin-right:4px');
$svgQuote = svg_icon('double-quote-sans-left', '', 14, 'vertical-align:middle;margin-right:4px');
$svgHeart = svg_icon('heart', 'heart-svg', 16, 'vertical-align:middle;margin-right:4px');

if (isset($_GET['ajax_search_more'])) {
    if (ob_get_length()) ob_clean();
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    try {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
                (SELECT COUNT(*) FROM follows f WHERE f.follower_id = ? AND f.following_id = p.user_id) as is_following,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                LEFT JOIN bans b ON p.user_id = b.user_id
                WHERE p.content LIKE ? AND p.id < ? AND b.user_id IS NULL";
        
        $likeQuery = "%" . addcslashes($query, '%_') . "%";
        $params = [$uid, $uid, $uid, $likeQuery, $last_id];
        if (!empty($date)) { 
            $sql .= " AND DATE(p.created_at) = ?"; 
            $params[] = $date; 
        }
        
        $sql .= " ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ajax_posts = $stmt->fetchAll();

        if ($ajax_posts) {
            foreach ($ajax_posts as $post) { 
                $displayName = htmlspecialchars(!empty($post['display_name']) ? $post['display_name'] : $post['username']);
                $userName = htmlspecialchars($post['username']);
                $avatar = htmlspecialchars($post['avatar'] ?? 'default.png');
                $dateStr = date('M d, g:i a', strtotime($post['created_at']));
                $content = parsePostContent($post['content']); 
                
                $followBtn = '';
                if ($post['user_id'] != $uid) {
                    if (isFollowing($uid, $post['user_id'])) {
                        $followBtn = '<a href="/backend/users/unfollow.php?id='.$post['user_id'].'&csrf='.$csrf.'" class="btn small">Unfollow</a>';
                    } else {
                        $followBtn = '<a href="/backend/users/follow.php?id='.$post['user_id'].'&csrf='.$csrf.'" class="btn small success">Follow</a>';
                    }
                }

                $imageHtml = !empty($post['image']) ? '<img src="/images/posts/'.htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8').'" class="thumbnail" style="margin-top:10px; max-width:100%; max-height:400px; display:block;">' : '';
                
                $hasLiked = $post['has_liked'] ?? false;
                $likesCount = $post['likes_count'] ?? 0;
                $hasRetweeted = $post['has_retweeted'] ?? false;
                $retweetCount = $post['retweet_count'] ?? 0;
                $retweetBtnClass = $hasRetweeted ? 'btn-retweet retweeted' : 'btn-retweet';
                $likeBtnClass = $hasLiked ? 'btn-like liked' : 'btn-like';

                $quotedHtml = '';
                if (!empty($post['retweet_of_id'])) {
                    $qStmt2 = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar FROM posts p JOIN users u ON p.user_id = u.id LEFT JOIN bans b ON p.user_id = b.user_id WHERE p.id = ? AND b.user_id IS NULL");
                    $qStmt2->execute([$post['retweet_of_id']]);
                    $quotedPost2 = $qStmt2->fetch(PDO::FETCH_ASSOC);
                    if ($quotedPost2) {
                        $quotedHtml = '<div style="border:1px solid #ddd; border-radius:8px; padding:10px; margin-top:10px; background:#f9f9f9;">';
                        $quotedHtml .= '<div style="margin-bottom:4px;"><strong><a href="/u/'.$quotedPost2['user_id'].'" style="text-decoration:none;color:inherit;">'.htmlspecialchars(!empty($quotedPost2['display_name'])?$quotedPost2['display_name']:$quotedPost2['username']).'</a></strong> <span class="muted" style="font-size:12px;">@'.htmlspecialchars($quotedPost2['username']).'</span></div>';
                        $quotedHtml .= '<div style="font-size:13px;word-wrap:break-word;">'.parsePostContent($quotedPost2['content']).'</div>';
                        if (!empty($quotedPost2['image'])) {
                            $quotedHtml .= '<img src="/images/posts/'.htmlspecialchars($quotedPost2['image'], ENT_QUOTES, 'UTF-8').'" style="max-width:100%;max-height:200px;margin-top:6px;border-radius:4px;">';
                        }
                        if (!empty($quotedPost2['video'])) {
                            $quotedHtml .= '<video controls style="max-width:100%;max-height:200px;margin-top:6px;border-radius:4px;" preload="metadata"><source src="/videos/posts/'.htmlspecialchars($quotedPost2['video'], ENT_QUOTES, 'UTF-8').'" type="video/mp4"></video>';
                        }
                        $quotedHtml .= '</div>';
                    }
                }

                echo <<<HTML
                <div class="row post" style="margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;" data-id="{$post['id']}">
                    <div class="span1">
                        <a href="/u/{$post['user_id']}"><img src="/images/avatars/{$avatar}" class="thumbnail" style="width:43px; height:43px;"></a>
                    </div>
                    <div class="span9">
                        <div style="float:right;">{$followBtn}</div>
                        <div style="margin-bottom: 5px;">
                            <strong><a href="/u/{$post['user_id']}">{$displayName}</a></strong>
                            <span class="muted" style="font-size: 12px; margin-left: 5px;">@{$userName}</span>
                            <span class="muted" style="margin-left: 10px;">&middot;</span>
                            <small class="muted" style="margin-left: 5px;">{$dateStr}</small>
                        </div>
                        <a href="/post/{$post['id']}" style="text-decoration:none; color:inherit;">
                            <div class="post-content" style="margin-top:5px; word-wrap: break-word;">{$content}</div>
                            {$imageHtml}
                        </a>
                        {$quotedHtml}
                        <div class="post-actions" style="margin-top: 10px; display: flex; gap: 50px; padding-bottom: 5px;">
                            <a href="/post/{$post['id']}#reply-area" style="color:#657786; font-size:12px; display:flex; align-items:center;">
                                {$svgReply}
                                <span>Reply</span>
                            </a>
                            <a href="/backend/posts/retweet.php?id={$post['id']}&csrf={$csrf}" class="{$retweetBtnClass}" data-post-id="{$post['id']}">
                                {$svgRetweet}
                                <span class="count">{$retweetCount}</span>
                            </a>
                            <a href="/?quote={$post['id']}" style="color:#657786;">
                                {$svgQuote}
                                <span>Quote</span>
                            </a>
                            <a href="/backend/posts/like_post.php?id={$post['id']}&csrf={$csrf}" class="{$likeBtnClass}" data-post-id="{$post['id']}">
                                {$svgHeart}
                                <span class="count">{$likesCount}</span>
                            </a>
                        </div>
                    </div>
                </div>
HTML;
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Search error: " . $e->getMessage());
        echo "Error: An error occurred";
    }
    exit;
}

require_once "header.php"; 
$posts = []; $users = [];
    if ($query !== '') {
    $likeQuery = "%" . addcslashes($query, '%_') . "%";
    if ($type === 'default' || $type === 'people') {
        $userLimit = ($type === 'default') ? 1 : 20;
        $uStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin FROM users u
                               LEFT JOIN bans b ON u.id = b.user_id
                               WHERE (u.username LIKE ? OR u.display_name LIKE ?) 
                               AND b.user_id IS NULL LIMIT $userLimit");
        $uStmt->execute([$likeQuery, $likeQuery]);
        $users = $uStmt->fetchAll();
    }

    if ($type === 'default' || $type === 'posts') {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
                (SELECT COUNT(*) FROM follows f WHERE f.follower_id = ? AND f.following_id = p.user_id) as is_following,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                LEFT JOIN bans b ON p.user_id = b.user_id
                WHERE p.content LIKE ? AND b.user_id IS NULL";
        
        $params = [$uid, $uid, $uid, $likeQuery];
        if (!empty($date)) { $sql .= " AND DATE(p.created_at) = ?"; $params[] = $date; }
        
        $sql .= " ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();
    }
}
$tagStmt = $pdo->query("SELECT content FROM posts WHERE content LIKE '%#%' ORDER BY created_at DESC LIMIT 50");
$all_content = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
$tags_found = [];
foreach($all_content as $text) {
    preg_match_all('/#(\w+)/', $text, $matches);
    foreach($matches[0] as $tag) { $tags_found[$tag] = ($tags_found[$tag] ?? 0) + 1; }
}
arsort($tags_found);
$trending_tags = array_slice($tags_found, 0, 8);
?>

<style>
    .sidebar-section { margin-bottom: 30px; }
    .filter-box { background: #f9f9f9; padding: 15px; border: 1px solid #eee; margin-bottom: 20px; }
    .user-card { display: flex; align-items: center; padding: 5px; border: 1px solid #eee; margin-bottom: 10px; }
    .user-card:hover { background: #f5f5f5; }
    .post { cursor: pointer; transition: background 0.1s; }
    .post:hover { background-color: #f9f9f9; }
    .post-actions { margin-top: 10px; display: flex; gap: 50px; padding-bottom: 5px; }
    .post-actions a { text-decoration: none; font-size: 12px; display: flex; align-items: center; }
    .post-actions a.btn-like { color: #657786; }
    .post-actions a.btn-like.liked { color: rgb(224, 36, 94); }
    .post-actions a.btn-retweet { color: #657786; }
    .post-actions a.btn-retweet.retweeted { color: rgb(23, 191, 99); }
    .post-actions img { width: 16px; height: 16px; margin-right: 8px; }
    #loading-indicator { text-align: center; padding: 20px; display: none; }
</style>

<div class="container">
    <div class="page-header">
        <h1>Search <small>Results for "<?= htmlspecialchars($query) ?>"</small></h1>
    </div>

    <div class="row">
        <div class="span10">
            <ul class="tabs">
                <li class="<?= $type == 'default' ? 'active' : '' ?>"><a href="?q=<?=urlencode($query)?>&type=default&date=<?=$date?>">Default</a></li>
                <li class="<?= $type == 'people' ? 'active' : '' ?>"><a href="?q=<?=urlencode($query)?>&type=people&date=<?=$date?>">People</a></li>
                <li class="<?= $type == 'posts' ? 'active' : '' ?>"><a href="?q=<?=urlencode($query)?>&type=posts&date=<?=$date?>">Posts</a></li>
            </ul>

            <?php if ($users): ?>
                <div class="user-results" style="margin-bottom: 30px;">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <img src="/images/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:45px; height:45px; margin-right: 15px;">
                            <div>
                                <strong><a href="/u/<?= $user['id'] ?>"><?= !empty($user['display_name']) ? htmlspecialchars($user['display_name']) : htmlspecialchars($user['username']) ?><?php if(!empty($user['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?><?php if(!empty($user['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?></a></strong><br>
                                <span class="muted" style="font-weight:normal;">@<?= htmlspecialchars($user['username']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($type === 'default' || $type === 'posts'): ?>
                <div id="post-feed">
                    <?php if(!$posts): ?>
                        <div class="alert-message info">No posts found.</div>
                    <?php else: ?>
                        <?php foreach($posts as $post) { include "components/post_item.php"; } ?>
                    <?php endif; ?>
                </div>
                <div id="loading-indicator">
                    <img src="/images/loading.gif" alt="" style="width:20px; vertical-align: middle;"> 
                    <span style="margin-left:10px; color:#999;">Loading more...</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="span6">
            <div class="filter-box">
                <form method="GET" action="search.php">
                    <input type="hidden" name="q" value="<?=htmlspecialchars($query)?>">
                    <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
                    <input type="date" name="date" class="span5" value="<?=htmlspecialchars($date)?>" style="margin-bottom: 10px;">
                    <button type="submit" class="btn small">Apply Filters</button>
                </form>
            </div>
            <div class="sidebar-section">
                <h4>Trending</h4>
                <ul class="unstyled" style="margin-top: 10px;">
                    <?php foreach($trending_tags as $tag => $count): ?>
                        <li style="margin-bottom: 10px;">
                            <a href="search.php?q=<?=urlencode($tag)?>" class="label notice"><?=htmlspecialchars($tag)?></a>
                            <span class="muted" style="font-size: 11px;"><?=number_format($count)?> posts</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var isLoading = false;
    var endOfPosts = false;
    var queryData = {
        ajax_search_more: 1,
        q: <?= json_encode($query) ?>,
        date: <?= json_encode($date) ?>,
        type: <?= json_encode($type) ?>
    };

    $(window).scroll(function() {
        if (isLoading || endOfPosts) return;
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 600) {
            loadNewPosts();
        }
    });

    function loadNewPosts() {
        var lastId = $('.post').last().attr('data-id');
        if (!lastId) return;

        isLoading = true;
        $('#loading-indicator').fadeIn(100);

        queryData.last_id = lastId;

        $.ajax({
            url: 'search.php',
            type: 'GET',
            data: queryData,
            success: function(response) {
                isLoading = false;
                $('#loading-indicator').fadeOut(100);

                if ($.trim(response) == "") {
                    endOfPosts = true;
                } else {
                    $('#post-feed').append(response);
                }
            },
            error: function() {
                isLoading = false;
                $('#loading-indicator').hide();
            }
        });
    }

    $(document).on('click', '.post', function(e) {
        if ($(e.target).closest('a, button, .btn').length) return;
        var id = $(this).data('id');
        if(id) window.location.href = '/post/' + id;
    });
});
</script>

<?php require_once "footer.php"; ?>