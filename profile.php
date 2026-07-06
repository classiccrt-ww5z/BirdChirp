<?php
require_once "database/config.php";
require_once "functions/users.php";
require_once "functions/posts.php";
require_once "functions/security.php";
require_once "functions/polls.php";

$uid = $_SESSION['user_id'] ?? 0;
$csrf = generateCSRF();

function renderPostItems($items, $tab, $isOwner = false) {
    global $uid, $csrf, $pdo;
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
        $hasRetweeted = $item['has_retweeted'] ?? false;
        $retweetCount = $item['retweet_count'] ?? 0;
        
        $output .= '<div class="post-item clickable-post" data-id="'.$realId.'" data-nav-id="'.$navId.'">';
        $output .= '<div class="post-layout">';
        $output .= '<div class="post-avatar">';
        $output .= '<img src="/images/avatars/'.htmlspecialchars($item['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8').'" class="thumbnail" style="width:48px;height:48px;margin:0;">';
        $output .= '</div>';
        $output .= '<div class="post-body">';
        
        if ($tab == 'posts' && !empty($item['retweeter_id'])) {
            $output .= '<div style="font-size:11px; color:#657786; margin-bottom:2px;">' . svg_icon('loop-circular', '', 12, 'vertical-align:middle;margin-right:2px') . '
                Reposted
            </div>';
        }
        
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

        if ($tab != 'replies') {
            $pollData = getPollForPost($realId);
            if ($pollData) {
                $userVote = $uid ? getUserPollVote($pollData['id'], $uid) : false;
                $output .= '<div style="margin-top:10px; padding:10px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">';
                $output .= '<div style="font-weight:600; font-size:14px; margin-bottom:8px;">'.htmlspecialchars($pollData['question']).'</div>';
                foreach ($pollData['options'] as $opt) {
                    $pct = $pollData['total_votes'] > 0 ? round(($opt['votes'] ?? 0) / $pollData['total_votes'] * 100) : 0;
                    $output .= '<div style="position:relative; margin-bottom:6px;"><div style="position:relative; z-index:1; display:flex; justify-content:space-between; padding:8px 12px; border-radius:4px; border:1px solid #ccc; background:#fff;"><span>'.htmlspecialchars($opt['option_text']).'</span>';
                    if ($userVote || $pollData['total_votes'] > 0) {
                        $output .= '<span style="font-weight:600;">'.$pct.'%</span>';
                    }
                    $output .= '</div>';
                    if ($userVote || $pollData['total_votes'] > 0) {
                        $bg = $userVote == $opt['id'] ? '#b3d9ff' : '#e8e8e8';
                        $output .= '<div style="position:absolute; top:0; left:0; height:100%; width:'.$pct.'%; background:'.$bg.'; border-radius:4px;"></div>';
                    }
                    $output .= '</div>';
                }
                $output .= '<div style="font-size:11px; color:#888; margin-top:4px;">'.$pollData['total_votes'].' vote'.($pollData['total_votes'] != 1 ? 's' : '').'</div>';
                $output .= '</div>';
            }
        }
        
        $output .= '<div class="post-actions" style="margin-top: 8px; display: flex; gap: 50px; padding-bottom: 5px;">';
        $output .= '<a href="/post/'.$realId.'#reply-area" style="color:#657786; font-size:12px; display:flex; align-items:center;">'.svg_icon('chat', '', 16, 'vertical-align:middle;margin-right:4px').'<span>Reply</span></a>';
        $output .= '<a href="/backend/posts/retweet.php?id='.$realId.'&csrf='.$csrf.'" class="btn-retweet'.($hasRetweeted ? ' retweeted' : '').'" data-post-id="'.$realId.'">'.svg_icon('loop-circular', '', 16, 'vertical-align:middle;margin-right:4px').'<span class="count">'.intval($retweetCount).'</span></a>';
        $output .= '<a href="/?quote='.$realId.'" style="color:#657786;">'.svg_icon('double-quote-sans-left', '', 14, 'vertical-align:middle;margin-right:4px').'<span>Quote</span></a>';
        $output .= '<a href="/backend/posts/like_post.php?id='.$realId.'&csrf='.$csrf.'" class="btn-like'.($hasLiked ? ' liked' : '').'" data-post-id="'.$realId.'">'.svg_icon('heart', 'heart-svg', 16, 'vertical-align:middle;margin-right:4px').'<span class="count">'.intval($likesCount).'</span></a>';
        if ($isOwner) {
            $pinText = !empty($item['pinned']) ? 'Unpin' : 'Pin';
            $output .= '<a href="/backend/posts/pin.php?id='.$realId.'&csrf='.$csrf.'" style="color:#657786; font-size:12px; display:flex; align-items:center; gap:4px; text-decoration:none;">'.svg_icon('pin', '', 14, '').$pinText.'</a>';
        }
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
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND (p.image IS NOT NULL AND p.image != '' OR p.video IS NOT NULL AND p.video != '') AND p.id < ? ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $uid, $id, $last_id]);
    } elseif ($tab == 'likes') {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                FROM posts p 
                JOIN users u ON p.user_id = u.id
                JOIN likes l ON l.post_id = p.id
                WHERE l.user_id = ? AND p.id < ? ORDER BY p.id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $uid, $id, $last_id]);
    } else {
        $sql = "SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted,
                NULL as retweeter_id, NULL as retweeter_username, NULL as retweeter_display_name
                FROM posts p JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND p.id < ?
                UNION ALL
                SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted,
                r.user_id as retweeter_id, ru.username as retweeter_username, ru.display_name as retweeter_display_name
                FROM retweets r
                JOIN posts p ON p.id = r.post_id
                JOIN users u ON p.user_id = u.id
                JOIN users ru ON r.user_id = ru.id
                WHERE r.user_id = ? AND p.retweet_of_id IS NULL AND p.id < ?
                ORDER BY id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $uid, $id, $last_id, $uid, $uid, $id, $last_id]);
    }
    echo renderPostItems($stmt->fetchAll(), $tab, ($uid == $id));
    exit;
}

$userinputid = $_GET['id'] ?? null;
$userinputusername = $_GET['user'] ?? null;

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
$full_width = true;
$profile_css = $profile_user['custom_css'] ?? '';

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

$pfpSrc = !empty($profile_user['pfp']) ? $profile_user['pfp'] : $profile_user['avatar'];
$bannerSrc = $profile_user['banner'] ?? 'default.png';

$follows = [];
if (!$ban) {
    $fStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar FROM follows f JOIN users u ON f.following_id = u.id WHERE f.follower_id = ? LIMIT 8");
    $fStmt->execute([$id]);
    $follows = $fStmt->fetchAll();
    if (count($follows) < 8) {
        $fStmt2 = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? LIMIT ?");
        $fStmt2->execute([$id, 8 - count($follows)]);
        $moreFriends = $fStmt2->fetchAll();
        $follows = array_merge($follows, $moreFriends);
    }
}
?>

<style>
#loading-indicator { display: none; }

.profile-wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 0 16px;
}

.profile-banner-wrap {
    position: relative;
    width: 100%;
    height: 320px;
    overflow: hidden;
    background: #1a1a2e;
    border-bottom: 1px solid #ccc;
}
.profile-banner-bg {
    position: absolute;
    inset: 0;
}
.profile-banner-bg img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.profile-banner-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(transparent 35%, rgba(0,0,0,0.7));
    pointer-events: none;
}
.profile-banner-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 18px 20px;
    display: flex;
    align-items: flex-end;
    gap: 20px;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.5);
}
.profile-banner-content .btn {
    text-shadow: none;
}
.profile-banner-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
    min-width: 0;
}
.profile-banner-info h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    text-shadow: 0 1px 4px rgba(0,0,0,0.5);
}
.profile-banner-info h1 .badge-img {
    height: 18px;
    vertical-align: middle;
    display: inline-block;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));
}
.profile-banner-info .profile-handle {
    font-size: 14px;
    color: rgba(255,255,255,0.8);
    margin: 1px 0 0;
    text-shadow: 0 1px 3px rgba(0,0,0,0.5);
}
.profile-banner-info .profile-actions-row {
    margin-top: 6px;
    display: flex;
    gap: 6px;
    align-items: center;
}
.profile-avatar {
    flex-shrink: 0;
}
.profile-avatar img {
    width: 80px;
    height: 80px;
    border: 3px solid #fff;
    object-fit: cover;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.profile-banner-stats {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.profile-layout {
    display: flex;
    gap: 20px;
    margin-top: 0;
}
.profile-sidebar {
    width: 240px;
    flex-shrink: 0;
}
.profile-main {
    flex: 1;
    min-width: 0;
}

.profile-module {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
    overflow: hidden;
}
.profile-module h4 {
    margin: 0;
    padding: 3px 8px;
    font-size: 10px;
    font-weight: 600;
    color: #666;
}
.profile-module .module-body {
    padding: 8px 10px;
    font-size: 12px;
    color: #444;
    line-height: 1.4;
}
.profile-module .module-body ul {
    list-style: none;
    margin: 0;
    padding: 0;
}
.profile-module .module-body li {
    padding: 2px 0;
    font-size: 12px;
}

.follow-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    padding: 6px;
}
.follow-item {
    text-align: center;
    font-size: 10px;
}
.follow-item a {
    text-decoration: none;
    color: #555;
}
.follow-item a:hover {
    color: #0069d6;
}
.follow-item img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    display: block;
    margin: 0 auto 3px;
    background: #f0f0f0;
    border: 1px solid #ddd;
}

.post-item {
    padding: 12px 14px;
    border-bottom: 1px solid #eee;
}
.post-item:hover {
    background: #fafbfc;
    cursor: pointer;
}
.post-layout {
    display: table;
    width: 100%;
    table-layout: fixed;
}
.post-avatar {
    display: table-cell;
    width: 54px;
    vertical-align: top;
}
.post-avatar img {
    width: 44px;
    height: 44px;
    border: 1px solid #ddd;
    object-fit: cover;
}
.post-body {
    display: table-cell;
    vertical-align: top;
    padding-left: 5px;
}
.post-meta {
    font-size: 13px;
    margin-bottom: 2px;
    color: #404040;
}
.post-meta .muted {
    font-weight: 400;
    font-size: 12px;
    color: #999;
}
.post-meta strong {
    font-weight: 600;
    color: #333;
}
.post-meta small.muted {
    font-size: 11px;
    color: #aaa;
    margin-left: 6px;
}
.post-content {
    word-wrap: break-word;
    font-size: 13px;
    line-height: 1.5;
    color: #333;
    margin-top: 2px;
}
.post-actions {
    margin-top: 6px;
    display: flex;
    gap: 30px;
}
.post-actions a {
    text-decoration: none;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
    color: #888;
}
.post-actions a.btn-like { color: #888; }
.post-actions a.btn-like.liked { color: rgb(224, 36, 94); }
.post-actions a.btn-retweet { color: #888; }
.post-actions a.btn-retweet.retweeted { color: rgb(23, 191, 99); }
.post-actions a:hover {
    color: #0069d6;
}
.post-actions img {
    width: 16px;
    height: 16px;
    margin-right: 8px;
}

.user-item {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-item:hover {
    background: #fafbfc;
}
.user-item .user-avatar img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border: 1px solid #ddd;
}
.user-item .user-info {
    flex: 1;
    min-width: 0;
}
.user-item .user-info h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
}
.user-item .user-info h3 a {
    color: #333;
    text-decoration: none;
}
.user-item .user-info h3 a:hover {
    text-decoration: underline;
}
.user-item .user-info .user-username {
    font-weight: 400;
    font-size: 12px;
    color: #999;
}
.user-item .user-info .user-bio {
    margin: 2px 0 0;
    color: #666;
    font-size: 12px;
    line-height: 1.4;
}

.tabs {
    margin: 0 0 0;
    border-bottom: 1px solid #ddd;
}
.tabs > li {
    margin-bottom: -1px;
}
.tabs > li > a {
    padding: 0 14px;
    line-height: 34px;
    font-size: 12px;
    color: #666;
    border: 1px solid transparent;
    border-radius: 4px 4px 0 0;
}
.tabs > li > a:hover {
    background: #f5f5f5;
    border-color: #eee #eee #ddd;
    text-decoration: none;
}
.tabs .active > a,
.tabs .active > a:hover {
    color: #333;
    font-weight: 600;
    background: #fff;
    border: 1px solid #ddd;
    border-bottom-color: transparent;
}

.follow-list-mode .tabs {
    display: none;
}
.follow-list-mode #loading-indicator {
    display: none !important;
}

.banned-msg {
    margin: 16px;
    padding: 12px 16px;
    background: #fff3f3;
    border: 1px solid #ffcdd2;
    color: #c62828;
}
.banned-msg strong {
    font-size: 14px;
}

.label.follows-you {
    font-size: 10px;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 1px 5px;
    margin-left: 4px;
    font-weight: 500;
}

@media (max-width: 820px) {
    .profile-layout { flex-direction: column; }
    .profile-sidebar { width: auto; }
    .profile-banner-wrap { height: 260px; }
    .profile-banner-left { flex-wrap: wrap; }
    .follow-grid { grid-template-columns: repeat(3, 1fr); }
    .profile-wrap { padding: 0 10px; }
}
</style>

<div class="profile-wrap">
<?php if($showFollowList): ?><div class="follow-list-mode"><?php endif; ?>

<div class="profile-banner-wrap">
    <div class="profile-banner-bg">
        <img src="/images/banners/<?= htmlspecialchars($bannerSrc) ?>" alt="">
    </div>
    <div class="profile-banner-overlay"></div>
    <div class="profile-banner-content">
        <div class="profile-banner-left">
            <div class="profile-avatar">
                <img src="/images/avatars/<?= htmlspecialchars($pfpSrc) ?>" alt="">
            </div>
            <div class="profile-banner-info">
                <h1>
                    <?= htmlspecialchars($displayName) ?>
                    <?php if(!empty($profile_user['partner'])): ?><img src="/images/misc/partner.png" alt="" class="badge-img" title="Partner"><?php endif; ?>
                    <?php if(!empty($profile_user['admin'])): ?><img src="/images/misc/staff.png" alt="" class="badge-img" title="Staff"><?php endif; ?>
                </h1>
                <div class="profile-handle">@<?= htmlspecialchars($profile_user['username']) ?></div>
                <div class="profile-actions-row">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $id): ?>
                        <?php if (isFollowing($_SESSION['user_id'], $id)): ?>
                            <a href="/backend/users/unfollow.php?id=<?=$id?>&csrf=<?= $csrf ?>" class="btn danger small">Unfollow</a>
                        <?php elseif (!$ban): ?>
                            <a href="/backend/users/follow.php?id=<?=$id?>&csrf=<?= $csrf ?>" class="btn success small">Follow</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id): ?>
                        <a href="/settings" class="btn small">Edit Profile</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if(!$ban): ?>
        <div class="profile-banner-stats">
            <a href="/u/<?=$id?>/posts" class="btn small<?= $activeTab == 'posts' || $activeTab == 'replies' || $activeTab == 'media' ? ' primary' : '' ?>"><?=$totalPosts?> Posts</a>
            <a href="/u/<?=$id?>/followers" class="btn small<?= $activeTab == 'followers' ? ' primary' : '' ?>"><?=$totalFollowers?> Followers</a>
            <a href="/u/<?=$id?>/following" class="btn small<?= $activeTab == 'following' ? ' primary' : '' ?>"><?=$totalFollowing?> Following</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="profile-content" style="margin-top: 20px;">
    <div class="profile-layout">
        <div class="profile-sidebar">
            <div class="profile-module">
                <div class="module-body" style="padding:8px 10px;">
                    <p style="margin:0;font-size:12px;color:#888;">Joined <?= date('M d, Y', strtotime($profile_user['created_at'])) ?></p>
                    <?php if(!empty($profile_user['bio'])): ?>
                    <p style="margin:6px 0 0;padding-top:6px;border-top:1px solid #eee;color:#555;font-size:12px;line-height:1.5;"><?= nl2br(htmlspecialchars($profile_user['bio'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-main">
            <?php if($ban): ?>
                <div class="banned-msg"><strong>Banned:</strong> <?= htmlspecialchars($ban['reason']) ?></div>
            <?php elseif ($showFollowList): ?>
                <?php if (!$userList): ?>
                    <div class="alert-message info" style="margin-top:16px;"><p>No users found in this list :(</p></div>
                <?php else: ?>
                    <?php foreach ($userList as $u):
                        $u_name = !empty($u['display_name']) ? $u['display_name'] : $u['username'];
                    ?>
                        <div class="user-item">
                            <div class="user-avatar">
                                <a href="/u/<?= $u['id'] ?>">
                                    <img src="/images/avatars/<?= e($u['avatar'] ?: 'default.png') ?>">
                                </a>
                            </div>
                            <div class="user-info">
                                <h3>
                                    <a href="/u/<?= $u['id'] ?>">
                                        <?= e($u_name) ?>
                                        <?php if(!empty($u['partner'])): ?><img src="/images/misc/partner.png" alt="" style="height:16px;vertical-align:middle;"><?php endif; ?>
                                        <?php if(!empty($u['admin'])): ?><img src="/images/misc/staff.png" alt="" style="height:16px;vertical-align:middle;"><?php endif; ?>
                                    </a>
                                    <span class="user-username">@<?= e($u['username']) ?></span>
                                    <?php if($u['follows_viewer']): ?>
                                        <span class="label follows-you">Follows you</span>
                                    <?php endif; ?>
                                </h3>
                                <p class="user-bio"><?= e(mb_strimwidth($u['bio'] ?? 'No bio available.', 0, 120, '...')) ?></p>
                            </div>
                            <div>
                                <a href="/u/<?= $u['id'] ?>" class="btn small">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <ul class="tabs">
                    <li class="<?= $activeTab=='posts' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/posts">Posts</a></li>
                    <li class="<?= $activeTab=='replies' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/replies">Replies</a></li>
                    <li class="<?= $activeTab=='media' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/media">Media</a></li>
                    <li class="<?= $activeTab=='likes' ? 'active' : '' ?>"><a href="/user/<?= htmlspecialchars($profile_user['username']) ?>/likes">Likes</a></li>
                </ul>

                <div id="post-feed">
                    <?php
                        $pinnedPost = null;
                        if ($activeTab == 'posts') {
                            $pinnedStmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin,
                                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                                (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                                FROM posts p JOIN users u ON p.user_id = u.id
                                WHERE p.user_id = ? AND p.pinned = 1 AND p.retweet_of_id IS NULL
                                LIMIT 1");
                            $pinnedStmt->execute([$uid, $uid, $id]);
                            $pinnedPost = $pinnedStmt->fetch(PDO::FETCH_ASSOC);
                        }

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
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                                    FROM posts p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? AND (p.image IS NOT NULL AND p.image != '' OR p.video IS NOT NULL AND p.video != '') ORDER BY p.id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $uid, $id]);
                        } elseif ($activeTab == 'likes') {
                            $sql = "SELECT p.*, u.username, u.display_name, u.avatar,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted
                                    FROM posts p JOIN users u ON p.user_id = u.id JOIN likes l ON l.post_id = p.id WHERE l.user_id = ? ORDER BY p.id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $uid, $id]);
                        } else {
                            if ($pinnedPost) {
                                echo '<div style="border-bottom:2px solid #1b95e0;">
                                    <div style="font-size:11px; color:#1b95e0; padding:6px 14px 0;">'.svg_icon('pin', '', 12, 'vertical-align:middle;margin-right:3px').'
                                        Pinned
                                    </div>
                                    '.renderPostItems([$pinnedPost], $activeTab, ($uid == $id)).'
                                </div>';
                            }

                            $sql = "SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted,
                                    NULL as retweeter_id, NULL as retweeter_username, NULL as retweeter_display_name
                                    FROM posts p JOIN users u ON p.user_id = u.id
                                    WHERE p.user_id = ?
                                    UNION ALL
                                    SELECT p.*, u.username, u.display_name, u.avatar, u.partner, u.admin,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                                    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as has_liked,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id) as retweet_count,
                                    (SELECT COUNT(*) FROM retweets WHERE post_id = p.id AND user_id = ?) as has_retweeted,
                                    r.user_id as retweeter_id, ru.username as retweeter_username, ru.display_name as retweeter_display_name
                                    FROM retweets r
                                    JOIN posts p ON p.id = r.post_id
                                    JOIN users u ON p.user_id = u.id
                                    JOIN users ru ON r.user_id = ru.id
                                    WHERE r.user_id = ? AND p.retweet_of_id IS NULL
                                    ORDER BY id DESC LIMIT 10";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$uid, $uid, $id, $uid, $uid, $id]);
                        }
                        echo renderPostItems($stmt->fetchAll(), $activeTab, ($uid == $id));
                    ?>
                </div>

                <div id="loading-indicator">Loading more posts...</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($showFollowList): ?></div><?php endif; ?>
</div>

<script>
function votePoll(pollId, optionId, element) {
    $.post('/backend/posts/poll_vote.php', {
        poll_id: pollId,
        option_id: optionId,
        csrf: '<?= $csrf ?>'
    }, function(response) {
        if (response.success) {
            location.reload();
        }
    }, 'json');
}

$(document).ready(function() {
    <?php if(!$showFollowList): ?>
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
        }).fail(function() {
            isLoading = false;
            $('#loading-indicator').hide();
        });
    }
    $(document).on('click', '.clickable-post', function(e) {
        if ($(e.target).closest('a, button, video, source').length) return;
        window.location.href = '/post/' + $(this).data('nav-id');
    });
    <?php endif; ?>
});
</script>

<?php require_once "footer.php"; ?>
