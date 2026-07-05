<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1) { header("Location: /login.php"); exit; }
if (!isset($_SESSION['admin_verified'])) { header("Location: login.php"); exit; }

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, avatar, sysadmin FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$adminUser = $stmt->fetch();
$adminUsername = $adminUser ? $adminUser['username'] : 'Admin';
$adminAvatar = $adminUser ? $adminUser['avatar'] : 'default.png';
$isSysadmin = $adminUser ? (int)($adminUser['sysadmin'] ?? 0) : 0;

$current_file = basename($_SERVER['PHP_SELF']);

$pages = [
    'index.php' => ['Dashboard', 'glyphicon-home'],
    'users.php' => ['Users', 'glyphicon-user'],
    'posts.php' => ['Posts', 'glyphicon-list-alt'],
    'news.php' => ['News', 'glyphicon-bullhorn'],
    'settings.php' => ['Settings', 'glyphicon-cog'],
    'ip_lookup.php' => ['IP Lookup', 'glyphicon-globe'],
    'logs.php' => ['Activity Log', 'glyphicon-time'],
];
$headTitle = $pages[$current_file][0] ?? 'Admin';

$isPopup = isset($_GET['popup']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=e($headTitle)?> - Admin</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php if(!$isPopup): ?>
<nav class="navbar navbar-inverse">
  <div class="container-fluid">
    <div class="navbar-header">
      <a class="navbar-brand" href="index.php">BirdChirp Admin</a>
    </div>
    <ul class="nav navbar-nav">
    <?php foreach($pages as $file => $info): list($label, $icon) = $info; ?>
      <li class="<?=$current_file===$file?'active':''?>"><a href="<?=$file?>"><span class="glyphicon <?=$icon?>"></span> <?=$label?></a></li>
    <?php endforeach; ?>
    <?php if($isSysadmin): ?>
      <li><a href="/internal/" class="sys-link"><span class="glyphicon glyphicon-wrench"></span> Internal</a></li>
    <?php endif; ?>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="/backend/auth/logout_handler.php"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="glyphicon glyphicon-user"></span> <?=e($adminUsername)?> <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a href="/"><span class="glyphicon glyphicon-home"></span> View Site</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>
<?php endif; ?>
<div class="container-fluid">
<?php showMessage(true); ?>
