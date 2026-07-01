<?php
require_once "database/config.php";
require_once "functions/users.php";
require_once "functions/posts.php";
require_once "functions/security.php";

$uid = $_SESSION['user_id'] ?? 0;
$csrf = generateCSRF();

function renderPostItems($items, $tab) {
    global $uid;
    if (!$items) {
        return '<div class="alert-message info" style="margin-top:20px;"><p>Nothing to show here yet.</p></div>';
    }
    
    $output = '';
    foreach ($items as $item) {
        $displayName = htmlspecialchars(!empty($item['display_name']) ? $item['display_name'] : $item['username']);
        $dateStr = date('M d, g:i a', strtotime($item['created_at']));
        $content = parsePostContent($item['content']);
        $realId = $item['id'];
        $navId = ($tab == 'replies') ? $item['post_id'] : $item['id'];
        
        $hasLiked = $item['has_liked'] ?? false;
        $likesCount = $item['likes_count'] ?? 0;
        $likeStyle = $hasLiked ? 'color:rgb(5, 190, 5); font-weight: bold;' : 'color: #657786;';
        
        $output .= '<div class="post-item clickable-post" data-id="'.$realId.'" data-nav-id="'.$navId.'">';
        $output .= '<div class="post-layout">';
        $output .= '<div class="post-avatar">';
        $output .= '<img src="/images/avatars/'.htmlspecialchars($item['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8').'" class="thumbnail" style="width:48px;height:48px;margin:0;">';
        $output .= '</div>';
        $output .= '<div class="post-body">';
        
        if ($tab == 'replies') {
            $output .= '<div class="post-meta"><span class="muted">Replied to: "'.htmlspecialchars(substr($item['post_content'] ?? '', 0, 40)).'..."</span> <a href="/post/'.$navId.'" class="view-link"></a></div>';
            $output .= '<div class="post-content">'.$content;
            if(!empty($item['image'])) {
                $output .= '<img src="/images/replies/'.htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8').'" style="max-width:100%; max-height:400px; display:block; margin-top:10px;" class="thumbnail">';
            }
            if(!empty($item['video'])) {
                $output .= '<video controls style="max-width:100%; height:auto; display:block; margin-top:10px;" preload="metadata">
                    <source src="/videos/replies/'.htmlspecialchars($item['video'], ENT_QUOTES, 'UTF-8').'" type="video/mp4">
                </video>';
            }
            $output .= '</div>';
        } else {
            $output .= '<div class="post-meta">';
            $output .= '<strong style="color: #404040;">'.$displayName.'</strong> ';
            $output .= '<span class="muted" style="font-weight: normal; font-size: 13px;">@'.htmlspecialchars($item['username']).'</span> ';
            $output .= '<small class="muted" style="margin-left:8px;">'.$dateStr.'</small>';
            $output .= ' <a href="/post/'.$navId.'" class="view-link"></a>';
            $output .= '</div>';
            
            $output .= '<div class="post-content">'.$content;
            if(!empty($item['image'])) {
                $output .= '<a href="/post/'.$realId.'"><img src="/images/posts/'.htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8').'" style="max-width:100%; max-height:400px; display:block; margin-top:10px;" class="thumbnail"></a>';
            }
            if(!empty($item['video'])) {
                $output .= '<video controls style="max-width:100%; height:auto; display:block; margin-top:10px;" preload="metadata">
                    <source src="/videos/posts/'.htmlspecialchars($item['video'], ENT_QUOTES, 'UTF-8').'" type="video/mp4">
                </video>';
            }
            $output .= '</div>';
        }
        
        $output .= '<div class="post-actions" style="margin-top: 8px; display: flex; gap: 50px; padding-bottom: 5px;">';
        $output .= '<a href="/post/'.$realId.'#reply-area" style="color:#657786;">
            <img src="/images/misc/reply.png" style="opacity:0.6">
            <span>Reply</span>
        </a>';
        $output .= '<a href="/backend/posts/like_post.php?id='.$realId.'&csrf='.$csrf.'" style="'.$likeStyle.'">
            <img src="/images/misc/likes.png" style="opacity: '.($hasLiked ? '1' : '0.6').'">
            <span>'.intval($likesCount).'</span>
        </a>';
        $output .= '</div>';
        
        $output .= '</div>'; 
        $output .= '</div>'; 
        $output .= '</div>'; 
    }
    return $output;
}

if (isset($_GET['ajax_load_more'])) {
    if (ob_get_length()) ob_clean();
    $id = intval($_GET['id']);
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 999999999;
    $tab = $_GET['tab'] ?? 'posts';

    if ($tab == 'replies') {
        $sql = "SELECT r.*, p.content as post_content, u.username, u.display_name, u.avatar,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = r.post_id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = r.post_id AND l.user_id = ?) as has_liked
                FROM replies r JOIN posts p ON r.post_id = p.id JOIN users u ON r.user_id = u.id
                WHERE r.user_id = ? AND r.id < ? ORDER BY r.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $id, $last_id]);
    } elseif ($tab == 'media') {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND (p.image IS NOT NULL AND p.image != '' OR p.video IS NOT NULL AND p.video != '') AND p.id < ? ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $id, $last_id]);
    } elseif ($tab == 'likes') {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked
                FROM posts p 
                JOIN users u ON p.user_id = u.id
                JOIN likes l ON l.post_id = p.id
                WHERE l.user_id = ? AND p.id < ? ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $id, $last_id]);
    } else {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND p.id < ? ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $id, $last_id]);
    }
    echo renderPostItems($stmt->fetchAll(), $tab);
    exit;
}

// user inputs
$userinputid = $_GET['id'] ?? null;
$userinputusername = $_GET['user'] ?? null;

// resolve the user input to profile data
$profile_user = match(true) {
    !empty($userinputid)       => getUserById(intval($userinputid)),
    !empty($userinputusername) => getUserByUsername($userinputusername),
    default           => null
};

if (!$profile_user) {
    require_once "404page.php"; 
    exit;
}

$id = intval($profile_user['id']);

$displayName = !empty($profile_user['display_name']) ? $profile_user['display_name'] : $profile_user['username'];
$page_title = $displayName;
$user = $profile_user;

require_once "header.php";

$displayName = !empty($profile_user['display_name']) ? $profile_user['display_name'] : $profile_user['username'];
$stmt = $pdo->prepare("SELECT reason FROM bans WHERE user_id = ?");
$stmt->execute([$id]);
$ban = $stmt->fetch();
$isFollowing = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $id) {
    $stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user_id'], $id]);
    $isFollowing = (bool)$stmt->fetch();
}

$activeTab = $_GET['tab'] ?? 'posts';
$followType = $_GET['type'] ?? 'followers';
$totalPosts = $totalFollowers = $totalFollowing = 0;

if (!$ban) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?"); 
    $stmt->execute([$id]); 
    $totalPosts = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?"); 
    $stmt->execute([$id]); 
    $totalFollowers = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?"); 
    $stmt->execute([$id]); 
    $totalFollowing = $stmt->fetchColumn();
}

$showFollowList = in_array($activeTab, ['followers', 'following']);
if ($showFollowList) {
    $viewer_id = $_SESSION['user_id'] ?? 0;
    if ($activeTab === 'following') {
        $title = "Following";
        $sql = "SELECT u.*, 
                (SELECT 1 FROM follows WHERE follower_id = u.id AND following_id = ?) as follows_viewer
                FROM follows f 
                JOIN users u ON f.following_id = u.id 
                WHERE f.follower_id = ? 
                ORDER BY u.display_name ASC";
    } else {
        $title = "Followers";
        $sql = "SELECT u.*, 
                (SELECT 1 FROM follows WHERE follower_id = u.id AND following_id = ?) as follows_viewer
                FROM follows f 
                JOIN users u ON f.follower_id = u.id 
                WHERE f.following_id = ? 
                ORDER BY u.display_name ASC";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$viewer_id, $id]);
    $userList = $stmt->fetchAll();
}
?>

<style>
.sidebar-sticky { position: -webkit-sticky; position: sticky; top: 20px; height: fit-content; }
.sidebar-sticky h2 { word-wrap: break-word; overflow-wrap: break-word; max-width: 100%; line-height: 1.2; font-size: 22px; }
.sidebar-sticky .muted { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.help-block { word-wrap: break-word; overflow-wrap: break-word; white-space: normal; max-width: 100%; display: block; line-height: 1.4; }
.post-item { padding: 15px 10px; border-bottom: 1px solid #eee; transition: background 0.1s ease; }
.post-item:hover { background: #f9f9f9; cursor: pointer; }
.post-layout { display: table; width: 100%; table-layout: fixed; }
.post-avatar { display: table-cell; width: 58px; vertical-align: top; }
.post-body { display: table-cell; vertical-align: top; padding-left: 5px; }

.post-meta { font-size: 14px; margin-bottom: 3px; color: #404040; }
.post-content { 
    word-wrap: break-word; 
    font-size: 14px; 
    line-height: 1.5; 
    color: #333; 
}

.view-link { font-size: 11px; color: #0084ff; margin-left: 8px; text-decoration: none; opacity: 0.7; }
.view-link:hover { opacity: 1; text-decoration: underline; }

.post-actions { margin-top: 8px; padding-bottom: 5px; }
.post-actions a { text-decoration: none; font-size: 12px; display: flex; align-items: center; }
.post-actions img { width: 16px; height: 16px; margin-right: 8px; }

.user-item { padding: 15px 10px; border-bottom: 1px solid #eee; display: block; text-decoration: none !important; color: inherit; }
.user-item:hover { background: #fdfdfd; }
.user-item .info { overflow: hidden; }
.user-item h3 { margin: 0; font-size: 15px; line-height: 1.2; }
.user-item .bio { margin: 4px 0 0; color: #666; font-size: 13px; line-height: 1.4; }
.label.follows-you { font-size: 10px; background: #eee; color: #777; text-shadow: none; vertical-align: middle; margin-left: 5px; }

#loading-indicator { text-align: center; padding: 25px; display: none; color: #777; font-size: 13px; }
</style>

<?php if ($showFollowList): ?>
<style>
.tabs { display: none; }
</style>
<?php endif; ?>

<div class="container">
    <div class="page-header">
        <h1><?= htmlspecialchars($displayName) ?> <?php if(!$ban): ?><small><?= ucfirst($activeTab) ?></small><?php endif; ?></h1>
    </div>


                <div class="row">
        <div class="span4 sidebar-sticky">
            <div style="text-align: center; padding-bottom: 20px;">
                <img src="/images/avatars/<?= htmlspecialchars($profile_user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="margin: 0 auto 15px; display: block; width: 160px; height: 160px;">
                <h2 style="margin-bottom: 5px; line-height: 1.2;">
                    <?= htmlspecialchars($displayName) ?><?php if(!empty($profile_user['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:20px; vertical-align:middle;display:inline-block;" title="Partner"><?php endif; ?><?php if(!empty($profile_user['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:20px; vertical-align:middle;display:inline-block;" title="Staff"><?php endif; ?>
                    <span class="muted" style="font-size:14px; margin-left:5px; font-weight:normal;">@<?= htmlspecialchars($profile_user['username']) ?></span>
                </h2>
                <p class="help-block"><?=htmlspecialchars($profile_user['bio'] ?? 'No bio yet.')?></p>

                
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $id): ?>
                    <div class="btn-follow-container">
                        <?php if ($isFollowing): ?>
                            <a href="/backend/users/unfollow_user.php?id=<?=$id?>&csrf=<?= $csrf ?>" class="btn danger">Unfollow</a>
                        <?php elseif (!$ban): ?>
                            <a href="/backend/users/follow_user.php?id=<?=$id?>&csrf=<?= $csrf ?>" class="btn success">Follow</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <?php if(!$ban): ?>
            <ul class="unstyled" style="padding: 0 10px;">
                <li style="padding: 8px 0; border-top: 1px solid #eee;">
                    <a href="/u/<?=$id?>/posts" style="display: block; text-decoration: none; color: inherit;">
                        <strong>Posts</strong> <span class="label pull-right notice"><?=$totalPosts?></span>
                    </a>
                </li>
                <li style="border-top: 1px solid #eee;">
                    <a href="/u/<?=$id?>/followers" style="display: block; padding: 8px 0; text-decoration: none; color: inherit;">
                        <strong>Followers</strong> <span class="label pull-right"><?=$totalFollowers?></span>
                    </a>
                </li>
                <li style="border-top: 1px solid #eee; border-bottom: 1px solid #eee;">
                    <a href="/u/<?=$id?>/following" style="display: block; padding: 8px 0; text-decoration: none; color: inherit;">
                        <strong>Following</strong> <span class="label pull-right"><?=$totalFollowing?></span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </div>

        <div class="span12">
            <?php if ($ban): ?>
                <div class="alert-message block-message error">
                    <p><strong>Banned:</strong> <?= htmlspecialchars($ban['reason']) ?></p>
                </div>
            <?php elseif ($showFollowList): ?>
                <div class="tab-content">
                    <?php if (!$userList): ?>
                        <div class="alert-message info" style="margin-top:20px;">
                            <p>No users found in this list :( </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userList as $u): 
                            $u_name = !empty($u['display_name']) ? $u['display_name'] : $u['username'];
                        ?>
                            <div class="user-item">
                                <div class="row">
                                    <div class="span1">
                                        <a href="/u/<?= $u['id'] ?>">
                                            <img src="/images/avatars/<?= e($u['avatar'] ?: 'default.png') ?>" class="thumbnail" style="width:48px;height:48px;">
                                        </a>
                                    </div>
                                    <div class="span8 info">
                                        <h3>
                                            <a href="/u/<?= $u['id'] ?>" style="color: #404040; text-decoration: none;">
                                                <?= e($u_name) ?>
                                            </a>
                                            <span class="muted" style="font-weight: normal; font-size: 13px;">@<?= e($u['username']) ?></span>
                                            <?php if($u['follows_viewer']): ?>
                                                <span class="label follows-you">Follows you</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="bio"><?= e($u['bio'] ?: 'No bio available.') ?></p>
                                    </div>
                                    <div class="span2" style="text-align: right;">
                                        <a href="/u/<?= $u['id'] ?>" class="btn small">View</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="tabs">
                    <li class="<?= $activeTab=='posts' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/posts">Posts</a></li>
                    <li class="<?= $activeTab=='replies' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/replies">Replies</a></li>
                    <li class="<?= $activeTab=='media' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/media">Media</a></li>
                    <li class="<?= $activeTab=='likes' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/likes">Likes</a></li>
                </ul>

                <div id="post-feed">
                    <?php 
                        if ($activeTab == 'replies') {
                            $sql = "SELECT r.*, p.content as post_content, u.username, u.display_name, u.avatar,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = r.post_id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = r.post_id AND l.user_id = ?) as has_liked
                                    FROM replies r JOIN posts p ON r.post_id = p.id JOIN users u ON r.user_id = u.id WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $id]);
                        } elseif ($activeTab == 'media') {
                            $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked
                                    FROM posts p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? AND (p.image IS NOT NULL AND p.image != '' OR p.video IS NOT NULL AND p.video != '') ORDER BY p.id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $id]);
                        } elseif ($activeTab == 'likes') {
                            $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked
                                    FROM posts p JOIN users u ON p.user_id = u.id JOIN likes l ON l.post_id = p.id WHERE l.user_id = ? ORDER BY p.id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $id]);
                        } else {
                            $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked
                                    FROM posts p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? ORDER BY p.id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $id]);
                        }
                        echo renderPostItems($stmt->fetchAll(), $activeTab);
                    ?>
                </div>

                <div id="loading-indicator">Loading more posts...</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var isLoading = false;
    var endOfPosts = false;
    var userId = <?= json_encode($id) ?>;
    var currentTab = <?= json_encode($activeTab) ?>;

    $(window).scroll(function() {
        if (isLoading || endOfPosts) return;
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 800) {
            loadItems();
        }
    });

    function loadItems() {
        var lastId = $('.post-item').last().data('id');
        if (!lastId) return;

        isLoading = true;
        $('#loading-indicator').show();

        $.get('/profile', {
            ajax_load_more: 1,
            id: userId,
            tab: currentTab,
            last_id: lastId
        }, function(data) {
            isLoading = false;
            $('#loading-indicator').hide();
            if ($.trim(data) == "" || data.includes('alert-message info')) {
                endOfPosts = true;
            } else {
                $('#post-feed').append(data);
            }
        });
    }
    $(document).on('click', '.clickable-post', function(e) {
        if ($(e.target).closest('a, button, video, source').length) return;
        window.location.href = '/post/' + $(this).data('nav-id');
    });
});
</script>

<?php require_once "footer.php"; ?>