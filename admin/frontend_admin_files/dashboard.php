<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/security.php';
require_once __DIR__ . '/../../functions/messages.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { echo '<p class="text-danger">Access denied.</p>'; return; }

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPosts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalReplies = $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
$totalBans  = $pdo->query("SELECT COUNT(*) FROM bans")->fetchColumn();
$newToday = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$postsToday = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$verifiedUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
$staffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE admin = 1")->fetchColumn();
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$stmt = $pdo->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = ?");
$stmt->execute([$dbName]);
$dbSizeMB = round($stmt->fetchColumn() ?? 0, 2);

$recentSignups = $pdo->query("SELECT id, username, created_at, is_verified, admin FROM users ORDER BY id DESC LIMIT 8")->fetchAll();
$recentPosts = $pdo->query("SELECT p.id, p.content, p.created_at, u.username, u.display_name FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 8")->fetchAll();
$recentLogs = [];
try { $recentLogs = $pdo->query("SELECT l.*, u.username FROM admin_log l LEFT JOIN users u ON l.admin_id = u.id ORDER BY l.created_at DESC LIMIT 8")->fetchAll(); } catch (PDOException $e) {}
?>

<div style="margin-bottom:12px;"></div>

<div class="row">
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$totalUsers?></div><div class="lbl">Users</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$totalPosts?></div><div class="lbl">Posts</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$totalReplies?></div><div class="lbl">Replies</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$totalBans?></div><div class="lbl">Bans</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$verifiedUsers?></div><div class="lbl">Verified</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$dbSizeMB?>M</div><div class="lbl">DB Size</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num">+<?=$newToday?></div><div class="lbl">Signups Today</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num">+<?=$postsToday?></div><div class="lbl">Posts Today</div></div></div></div>
<div class="col-sm-4 col-xs-6"><div class="panel panel-default stat-box"><div class="panel-body"><div class="num"><?=$staffCount?></div><div class="lbl">Staff</div></div></div></div>
</div>

<div class="row">
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">Recent Signups</div>
<div class="panel-body">
<?php if($recentSignups): ?>
<table class="table">
<?php foreach($recentSignups as $u): ?>
<tr>
  <td><a href="edit_user.php?user_id=<?=$u['id']?>" onclick="window.open('edit_user.php?user_id=<?=$u['id']?>','manage','width=850,height=650,scrollbars=1');return false;"><b><?=e($u['username'])?></b></a></td>
  <td class="text-muted"><?=date('M j',strtotime($u['created_at']))?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?><p class="text-muted">No signups yet.</p><?php endif; ?>
</div></div></div>

<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">Recent Posts</div>
<div class="panel-body">
<?php if($recentPosts): ?>
<table class="table">
<?php foreach($recentPosts as $p): ?>
<tr>
  <td><b><?=e($p['display_name']?:$p['username'])?></b></td>
  <td class="text-muted"><?=e(substr($p['content'],0,50))?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?><p class="text-muted">No posts yet.</p><?php endif; ?>
</div></div></div></div>

<div class="row">
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">Recent Activity</div>
<div class="panel-body">
<?php if($recentLogs): ?>
<table class="table">
<?php foreach($recentLogs as $log): ?>
<tr>
  <td><b><?=e($log['username']??'?')?></b></td>
  <td class="text-muted"><?=e($log['action'])?></td>
  <td class="text-muted"><?=date('M j',strtotime($log['created_at']))?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?><p class="text-muted">No activity yet.</p><?php endif; ?>
</div></div></div>

<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">System</div>
<div class="panel-body">
<table class="table">
<tr><td><b>PHP</b></td><td><?=phpversion()?></td></tr>
<tr><td><b>Memory</b></td><td><?=round(memory_get_usage(true)/1024/1024,2)?>M</td></tr>
<tr><td><b>Database</b></td><td><?=e($dbName)?> (<?=$dbSizeMB?> MB)</td></tr>
</table>
</div></div></div></div>
