<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$admin = $_SESSION['admin'] ?? 0;
if ($admin != 1) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['admin_verified'])) {
    header("Location: login.php");
    exit;
}

$page_titles = [
    "index.php" => "Dashboard",
    "posts.php" => "Posts",
    "users.php" => "Users",
    "news.php"  => "Announcements/Blog",
    "ip_lookup.php"  => "IP Lookup",
    "site_settings.php" => "Site Settings"
];
$current_file = basename($_SERVER['PHP_SELF']);
$page_title = $page_titles[$current_file] ?? ucfirst(str_replace(".php", "", $current_file));
require_once __DIR__ . '/../header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Admin Panel</h1>
    </div>
    
    <ul class="tabs">
        <li class="<?php echo $current_file == 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">Dashboard</a>
        </li>
        <li class="<?php echo $current_file == 'users.php' ? 'active' : ''; ?>">
            <a href="users.php">Users</a>
        </li>
        <li class="<?php echo $current_file == 'news.php' ? 'active' : ''; ?>">
            <a href="news.php">Manage Blog/Announcements</a>
        </li>
        <li class="<?php echo $current_file == 'ip_lookup.php' ? 'active' : ''; ?>">
            <a href="ip_lookup.php">IP Lookup</a>
        </li>
        <li class="<?php echo $current_file == 'site_settings.php' ? 'active' : ''; ?>">
            <a href="site_settings.php">Site Settings</a>
        </li>
    </ul>

    <div style="margin-top:20px;">
        <?php showMessage(); ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-1.9.1.min.js"></script>
<script src="https://netdna.bootstrapcdn.com/twitter-bootstrap/1.4.0/bootstrap.min.js"></script>

<?php 
ob_end_flush(); 
?>
