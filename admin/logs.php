<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';
if (!isLoggedIn()||($_SESSION['admin']??0)!=1||!isset($_SESSION['admin_verified'])){header("Location: /");exit;}

$page=max(1,(int)($_GET['page']??1));
$perPage=50;$offset=($page-1)*$perPage;
$actionFilter=trim($_GET['action']??'');

try{$pdo->prepare("SELECT 1 FROM admin_log LIMIT 1")->execute();}catch(PDOException $e){
    try{$pdo->exec("CREATE TABLE IF NOT EXISTS admin_log(id INT AUTO_INCREMENT PRIMARY KEY,admin_id INT NOT NULL,action VARCHAR(100) NOT NULL,target_type VARCHAR(50) DEFAULT NULL,target_id VARCHAR(255) DEFAULT NULL,details TEXT DEFAULT NULL,ip_address VARCHAR(45) DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(PDOException $e2){}
}

$where="";$params=[];
if($actionFilter){$where="WHERE l.action=?";$params[]=$actionFilter;}
$totalLogs=$pdo->prepare("SELECT COUNT(*) FROM admin_log l $where");$totalLogs->execute($params);$totalLogs=$totalLogs->fetchColumn();
$totalPages=max(1,ceil($totalLogs/$perPage));
$logs=$pdo->prepare("SELECT l.*,u.username FROM admin_log l LEFT JOIN users u ON l.admin_id=u.id $where ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset");$logs->execute($params);$logs=$logs->fetchAll();
$actions=$pdo->query("SELECT DISTINCT action FROM admin_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

require_once "header.php";
?>

<h2>Activity Log <span class="text-muted">(<?=number_format($totalLogs)?>)</span></h2>

<ul class="nav nav-pills" style="margin-bottom:10px;">
  <li class="<?=!$actionFilter?'active':''?>"><a href="logs.php">All</a></li>
  <?php foreach($actions as $a):?> <li class="<?=$actionFilter===$a?'active':''?>"><a href="logs.php?action=<?=urlencode($a)?>"><?=e($a)?></a></li><?php endforeach;?>
</ul>

<div class="panel panel-default">
<div class="panel-body" style="padding:0;">
<?php if($logs):?>
<table class="table table-striped"><thead><tr><th>ID</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>Date</th><th>IP</th></tr></thead>
<tbody><?php foreach($logs as $l):?><tr>
  <td>#<?=$l['id']?></td>
  <td><b><?=e($l['username']??'Unknown')?></b></td>
  <td><?=e($l['action'])?></td>
  <td><?=e($l['target_type']??'')?> <?=e($l['target_id']??'')?></td>
  <td><span class="trunc"><?=e(substr($l['details']??'',0,80))?></span></td>
  <td class="text-muted"><?=date('M j, Y g:i A',strtotime($l['created_at']))?></td>
  <td class="text-muted"><?=e($l['ip_address']??'-')?></td>
</tr><?php endforeach;?></tbody></table>
<?php else:?><p class="text-muted" style="text-align:center;padding:16px;margin:0;">No activity logged yet.</p><?php endif;?>
</div></div>

<?php if($totalPages>1):?>
<ul class="pager">
  <?php if($page>1):?><li><a href="?page=<?=$page-1?><?=$actionFilter?'&action='.urlencode($actionFilter):''?>">Prev</a></li><?php endif;?>
  <li class="disabled"><a>Page <?=$page?> / <?=$totalPages?></a></li>
  <?php if($page<$totalPages):?><li><a href="?page=<?=$page+1?><?=$actionFilter?'&action='.urlencode($actionFilter):''?>">Next</a></li><?php endif;?>
</ul>
<?php endif;?>

<?php require_once "footer.php"; ?>
