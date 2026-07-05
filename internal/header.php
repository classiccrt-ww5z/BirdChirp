<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { header("Location: login.php"); exit; }

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, sysadmin FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$adminUser = $stmt->fetch();
if (!$adminUser || !($adminUser['sysadmin'] ?? 0)) { header("Location: /admin/"); exit; }

$current_file = basename($_SERVER['PHP_SELF']);
$page_titles = [
    'index.php' => 'Internal', 'db_browser.php' => 'DB Browser',
    'sql_runner.php' => 'SQL Runner', 'migration.php' => 'Migration',
    'phpinfo.php' => 'PHP Info',
];
$headTitle = $page_titles[$current_file] ?? 'Internal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=e($headTitle)?> - Internal</title>
<style>
body { font-family: sans-serif; font-size: 14px; margin: 0; padding-top: 32px; background: #fff; color: #000; }
a { color: #000; }
a:visited { color: #000; }
nav { position: fixed; top: 0; left: 0; right: 0; height: 32px; display: flex; align-items: center; justify-content: center; z-index: 100; }
nav > .nav-inner { max-width: 960px; width: 100%; display: flex; align-items: center; padding: 0 10px; }
nav a { color: #000; text-decoration: none; padding: 6px 8px; font-size: 13px; display: inline-block; }
nav a:hover { text-decoration: underline; }
nav ul { list-style: none; margin: 0; padding: 0; display: inline; }
nav ul li { display: inline; }
nav ul li + li:before { content: "| "; color: #000; }
nav .right { margin-left: auto; }
.container { max-width: 960px; margin: 0 auto; padding: 12px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
table td, table th { padding: 6px 8px; text-align: left; border: 1px solid #999; font-size: 13px; }
table th { background: #eee; font-weight: 700; }
.box { border: 1px solid #999; margin-bottom: 12px; background: #fff; }
.box h5 { margin: 0; padding: 8px 10px; background: #eee; border-bottom: 1px solid #999; font-size: 14px; }
.box-inner { padding: 0; }
.alert { padding: 8px 12px; margin-bottom: 12px; border: 1px solid; font-size: 13px; }
.alert.warning { background: #ffe; border-color: #cc0; }
.alert.error { background: #fee; border-color: #c00; }
.alert.success { background: #efe; border-color: #0c0; }
.alert.info { background: #eef; border-color: #00c; }
input, select, textarea { font-family: monospace; font-size: 13px; padding: 3px 5px; border: 1px solid #999; }

.pagination { margin: 12px 0; }
.pagination ul { list-style: none; margin: 0; padding: 0; }
.pagination ul li { display: inline; }
.pagination ul li a { display: inline-block; padding: 4px 10px; border: 1px solid #999; text-decoration: none; color: #000; font-size: 13px; margin-right: 3px; }
.pagination ul li.disabled a { color: #999; border-color: #ccc; }
.flex { display: flex; }
.flex-2 { flex: 1; }
code { font-family: monospace; font-size: 12px; }
p { margin-bottom: 8px; }
.help { font-size: 12px; color: #666; }
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
  <ul>
    <li class="<?=$current_file=='index.php'?'active':''?>"><a href="index.php">Dashboard</a></li>
    <li class="<?=$current_file=='db_browser.php'?'active':''?>"><a href="db_browser.php">DB Browser</a></li>
    <li class="<?=$current_file=='sql_runner.php'?'active':''?>"><a href="sql_runner.php">SQL Runner</a></li>
    <li class="<?=$current_file=='migration.php'?'active':''?>"><a href="migration.php">Migration</a></li>
    <li class="<?=$current_file=='phpinfo.php'?'active':''?>"><a href="phpinfo.php">PHP Info</a></li>
  </ul>
  <div class="right">
    <a href="/admin/">back to admin panel</a> | you are <?=e($adminUser['username'])?>
  </div>
  </div>
</nav>
<div class="container">
<?php showMessage(); ?>