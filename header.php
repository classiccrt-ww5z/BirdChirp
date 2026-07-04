<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start(); 
if (session_status() !== PHP_SESSION_ACTIVE) {
    
}

require_once __DIR__ . '/database/config.php';

require_once __DIR__ . '/functions/auth.php';

$require_birthdate = '0';
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'require_birthdate'");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $require_birthdate = $row[0];
    }
} catch (PDOException $e) {
    // Table or column doesn't exist, skip birthdate check
}

if ($require_birthdate === '1' && isLoggedIn()) {
    $uid = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT birthdate FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (empty($user['birthdate'])) {
        $current_file = basename($_SERVER['PHP_SELF']);
        if ($current_file !== 'set_birthdate.php' && $current_file !== 'login.php' && $current_file !== 'signup.php') {
            header("Location: set_birthdate.php");
            exit;
        }
    }
}

$page_titles = [
    "index.php"    => "Home",
    "explore.php"  => "Explore",
    "settings.php" => "Settings",
    "search.php"   => "Search",
    "banned.php"   => "Banned",
    "news.php"     => "Blog",
    "users.php"    => "Users",
    "verify.php"   => "Verify",
    "profile.php"  => "",
    "view_post.php"=> "",
    "inbox.php"    => "Notifications",
    "login.php"    => "Login",
    "signup.php"   => "Sign Up"
];

$current_file = basename($_SERVER['PHP_SELF']);
if (!isset($page_title)) {
    $page_title = $page_titles[$current_file] ?? ucfirst(str_replace(".php", "", $current_file));
}

require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/messages.php';
require_once __DIR__ . '/functions/notifications.php';
require_once __DIR__ . '/functions/users.php';

$csrf = generateCSRF();

$avatar = "default.png";
$username = "";
$displayName = ""; 

if(isLoggedIn()){
    $uid = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT username, display_name, avatar, banner, admin, is_verified, partner, custom_css FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if($user){
        $banStmt = $pdo->prepare("SELECT reason FROM bans WHERE user_id = ? LIMIT 1");
        $banStmt->execute([$uid]);
        $banRecord = $banStmt->fetch();
        if($banRecord && $current_file !== 'banned.php'){
            header("Location: /banned.php");
            exit;
        }
        if($user['is_verified'] == 0 && $current_file !== 'verify.php' && $current_file !== 'logout_handler.php'){
            header("Location: /verify.php");
            exit;
        }

        $username = $user['username'];
        $displayName = !empty($user['display_name']) ? $user['display_name'] : $user['username'];
        $avatar = $user['avatar'] ?: "default.png";
        $_SESSION['admin'] = $user['admin'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $SITE_NAME; ?> - <?php echo $page_title; ?></title>
    
    <meta name="description" content="Join the conversation on <?php echo $SITE_NAME; ?>. Share updates, follow friends, and explore trending topics.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://birdchirp.org/<?php echo $current_file; ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo $SITE_NAME; ?>">
    <meta property="og:url" content="https://birdchirp.org/<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
    
    <?php 
    if ($current_file === 'view_post.php' && $post !== false && !empty($post)) {
        $embed_title = e(!empty($post['display_name']) ? $post['display_name'] : $post['username']) . " on " . $SITE_NAME;
        $embed_desc = substr(strip_tags($post['content'] ?? ''), 0, 160);
        if (!empty($post['image'])) {
            $embed_image = "/images/posts/".htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8');
        } elseif (!empty($post['video'])) {
            $embed_image = "/images/logos/birdchirpold.png";
        } else {
            $embed_image = "/images/avatars/".htmlspecialchars($post['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8');
        }
    } elseif ($current_file === 'profile.php' && isset($user)) {
        $embed_title = e(!empty($user['display_name']) ? $user['display_name'] : $user['username']) . " (@" . e($user['username']) . ")";
        $embed_desc = e($user['bio'] ?? 'Check out this profile on ' . $SITE_NAME);
        $embed_image = "/images/avatars/".htmlspecialchars($user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8');
    } else {
        $embed_title = $SITE_NAME . " - " . ($page_title ?: "Home");
        $embed_desc = "The place to see what's happening now.";
        $embed_image = "/images/logos/birdchirpold.png";
    }
    ?>

    <meta property="og:title" content="<?php echo $embed_title; ?>">
    <meta property="og:description" content="<?php echo $embed_desc; ?>">
    <meta property="og:image" content="https://birdchirp.org<?php echo $embed_image; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $embed_title; ?>">
    <meta name="twitter:description" content="<?php echo $embed_desc; ?>">

    <link rel="icon" type="image/x-icon" href="/favicon.png">
    <link rel="stylesheet" href="/css/bootstrap.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

    <?php if (!empty($profile_css)): ?>
    <style><?= $profile_css ?></style>
    <?php endif; ?>

    <style> 
        .topbar { 
            position: static !important; 
            margin-bottom: 20px; 
        }
        .topbar .container {
            position: relative;
        }
        .search-wrapper {
            position: absolute;
            left: 50%;
            top: 5px;
            margin-left: -110px; 
        }
        .search-wrapper input {
            border: 1px solid #111 !important;
            color: #ccc !important;
            width: 220px;
            padding: 4px 9px;
            font-family: inherit;
            border-radius: 3px;
        }
        .search-wrapper input:focus {
            background: #fff !important;
            color: #000 !important;
            outline: none;
            border-color: #0088cc !important;
        }    
        .notification-bell {
            position: relative;
            display: inline-block;
            padding: 6px 8px !important;
        }
        .notification-bell svg {
            width: 22px;
            height: 22px;
        }
        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #e0245e;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            padding: 0px 3px;
            border-radius: 6px;
            min-width: 1px;
            text-align: center;
            line-height: 12px;
            height: 12px;
        }
        .secondary-nav .dropdown {
            position: relative;
        }
        .user-dropdown {
            display: flex !important;
            align-items: center;
            padding: 4px 10px !important;
            height: 32px;
        }
        .nav-avatar {
            margin-right: 8px;
            border: 1px solid rgba(0,0,0,0.5);
            border-radius: 2px;
            background: #fff;
        }
        .name-stack {
            display: flex;
            flex-direction: column;
            line-height: 1;
            margin-right: 8px;
            text-align: left;
        }
        .display-name-nav {
            font-weight: bold;
            font-size: 12px;
            color: #fff;
            display: block;
        }
        .username-nav {
            font-size: 10px;
            color: #999;
            display: block;
        }
        .dropdown.open .user-dropdown {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        .dropdown-menu {
            z-index: 9999;
        }
        .banner-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            margin-bottom: 0;
        }
        .banner-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body>
<?php
$show_announcement = false;
if (isset($show_announcement) && $show_announcement === true):
?>
    <div class="alert-message info" style="margin-bottom: 0; border-radius: 0;">
        <div class="container">
            <p>Welcome to OpenBirdChirp</p>

        </div>
    </div>
<?php endif; ?>

<div class="topbar">
    <div class="fill">
        <div class="container">
            <h3>
                <a href="/">
                    <img src="/images/logos/birdchirpold.png" height="20" width="70" style="vertical-align:middle;">
                </a>
            </h3>

            <ul class="nav">
                <li class="<?php if($current_file=="index.php") echo 'active'; ?>"><a href="/">Home</a></li>
                <li class="<?php if($current_file=="explore.php") echo 'active'; ?>"><a href="/explore">Explore</a></li>
            </ul>

            <div class="search-wrapper">
                <form action="/search.php" method="GET" style="margin:0;">
                    <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </form>
            </div>

            <ul class="nav secondary-nav">
                <?php if(isLoggedIn()): ?>
                    <?php 
                    $unread = 0;
                    if (function_exists('getUnreadNotificationCount')) {
                        $unread = getUnreadNotificationCount($_SESSION['user_id']);
                    }
                    ?>
                    <li>
                        <a href="/inbox" class="notification-bell" title="Notifications">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <?php if($unread > 0): ?>
                                <span class="notification-badge"><?= $unread ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="dropdown" data-dropdown="dropdown">
                        <a href="#" class="dropdown-toggle user-dropdown">
                            <img class="nav-avatar" src="/images/avatars/<?= htmlspecialchars($avatar) ?>" height="24" width="24">
                            <span class="name-stack">
                                <span class="display-name-nav"><?= htmlspecialchars($displayName) ?></span>
                                <span class="username-nav">@<?= htmlspecialchars($username) ?></span>
                            </span>
                            <b class="caret"></b>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if(isset($_SESSION['original_admin_id'])): ?>
                                <li>
                                    <strong>You are logged into another user. to go back click logout!</strong> 
                                </li>
                                <li class="divider"></li>
                            <?php endif; ?>
                            <li><a href="/u/<?= (int)$_SESSION['user_id'] ?>">My Account</a></li>
                            <li><a href="/manage_posts">My Posts</a></li>
                            <li><a href="/settings">Settings</a></li>
                            <?php if(isset($_SESSION['admin']) && $_SESSION['admin']==1): ?>
                                <li><a href="/legacy_admin/index">Admin Panel</a></li>
                            <?php endif; ?>
                            <li class="divider"></li>
                            <li><a href="/backend/auth/logout_handler">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li><a href="/login">Login</a></li>
                    <li><a href="/signup">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php if (empty($full_width)): ?>
<div class="container">
<?php endif; ?>
    <?php showMessage(); ?>

<script>
$(function(){
    $('.dropdown-toggle').click(function(e) {
        var $parent = $(this).parent('.dropdown');
        var isActive = $parent.hasClass('open');
        $('.dropdown').removeClass('open');
        if (!isActive) {
            $parent.toggleClass('open');
        }
        e.preventDefault();
        e.stopPropagation();
    });
    $(document).click(function(e) {
        $('.dropdown').removeClass('open');
    });
}); 
</script>
