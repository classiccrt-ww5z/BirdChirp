<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/security.php';
require_once __DIR__ . '/../../functions/messages.php';
require_once __DIR__ . '/../../functions/admin_log.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { return; }

$adminCsrf = generateCSRF();
$searchQuery = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'newest';

if (isset($_GET['restore_admin'])){
    if(isset($_SESSION['original_admin_id'])){
        logAdminAction('Restore Admin',$_SESSION['username']??"Unknown",$adminUsername??'Admin',"Returned to admin account");
        $_SESSION['user_id']=$_SESSION['original_admin_id']; $_SESSION['username']=$_SESSION['original_admin_user']; $_SESSION['avatar']=$_SESSION['original_admin_avatar'];
        $_SESSION['admin']=1; unset($_SESSION['original_admin_id'],$_SESSION['original_admin_user'],$_SESSION['original_admin_avatar']);}
    echo '<script>location.reload()</script>'; return;
}

if(isset($_SESSION['original_admin_id'])){
    echo '<div class="alert alert-warning">You are logged in as another user. <a href="index.php?page=users&restore_admin=1" class="btn btn-default btn-xs">Restore Admin</a></div>';
}

$sql="SELECT u.*,(SELECT COUNT(*) FROM follows WHERE following_id=u.id)fc FROM users u"; $where=[]; $params=[];
if($searchQuery){$e=addcslashes($searchQuery,'%_');$where[]="(u.username LIKE ? OR u.email LIKE ?)";$params[]="%$e%";$params[]="%$e%";}
if($filter=='verified')$where[]="u.is_verified=1"; if($filter=='unverified')$where[]="u.is_verified=0"; if($filter=='staff')$where[]="u.admin=1";
if($filter=='sysadmin')$where[]="u.sysadmin=1"; if($filter=='partner')$where[]="u.partner=1"; if($filter=='no_birthdate')$where[]="(u.birthdate IS NULL OR u.birthdate='')";
if(!empty($where))$sql.=" WHERE ".implode(" AND ",$where);
if($filter=='popular')$sql.=" ORDER BY fc DESC"; elseif($filter=='oldest')$sql.=" ORDER BY u.id ASC"; else $sql.=" ORDER BY u.id DESC";
$sql.=" LIMIT 100";
try{$userList=$pdo->prepare($sql);$userList->execute($params);$userList=$userList->fetchAll();}catch(PDOException $e){$userList=[];}
$userList=$userList?:[];
$bannedMap=[]; try{$bannedMap=$pdo->query("SELECT user_id,reason FROM bans")->fetchAll(PDO::FETCH_KEY_PAIR);}catch(PDOException $e){}
?>

<div style="margin-bottom:12px;"></div>

<form method="get" class="form-inline" style="margin-bottom:10px;" onsubmit="return loadPage('users',this)">
  <div class="form-group">
    <input type="text" name="search" class="form-control input-sm" placeholder="Search username or email..." value="<?=e($searchQuery)?>">
  </div>
  <button type="submit" class="btn btn-default btn-sm">Search</button>
  <?php if($searchQuery):?> <a href="index.php?page=users" class="btn btn-link btn-sm" onclick="return loadPage('users')">Clear</a><?php endif;?>
</form>

<ul class="nav nav-pills" style="margin-bottom:10px;">
  <li class="<?=$filter=='newest'?'active':''?>"><a href="index.php?page=users&filter=newest" onclick="return loadPage('users','filter=newest')">Newest</a></li>
  <li class="<?=$filter=='popular'?'active':''?>"><a href="index.php?page=users&filter=popular" onclick="return loadPage('users','filter=popular')">Popular</a></li>
  <li class="<?=$filter=='verified'?'active':''?>"><a href="index.php?page=users&filter=verified" onclick="return loadPage('users','filter=verified')">Verified</a></li>
  <li class="<?=$filter=='unverified'?'active':''?>"><a href="index.php?page=users&filter=unverified" onclick="return loadPage('users','filter=unverified')">Unverified</a></li>
  <li class="<?=$filter=='staff'?'active':''?>"><a href="index.php?page=users&filter=staff" onclick="return loadPage('users','filter=staff')">Staff</a></li>
  <li class="<?=$filter=='partner'?'active':''?>"><a href="index.php?page=users&filter=partner" onclick="return loadPage('users','filter=partner')">Partners</a></li>
  <li class="<?=$filter=='no_birthdate'?'active':''?>"><a href="index.php?page=users&filter=no_birthdate" onclick="return loadPage('users','filter=no_birthdate')">No BD</a></li>
</ul>

<div class="panel panel-default">
<table class="table table-striped">
<thead><tr><th>ID</th><th>User</th><th>Followers</th><th>Status</th><th>Action</th></tr></thead>
<tbody><?php foreach($userList as $u):?><tr>
  <td>#<?=$u['id']?></td>
  <td><a href="edit_user.php?user_id=<?=$u['id']?>" onclick="window.open('edit_user.php?user_id=<?=$u['id']?>','manage','width=850,height=650,scrollbars=1');return false;"><b><?=e($u['username'])?></b><br><span class="text-muted"><?=e($u['email'])?></span></a></td>
  <td><?=$u['fc']?></td>
  <td><?php if(isset($bannedMap[$u['id']])):?><span class="label label-danger">Banned</span><?php elseif($u['is_verified']):?><span class="label label-success">Verified</span><?php elseif(empty($u['birthdate'])):?><span class="label label-danger">No BD</span><?php else:$a=(int)((time()-strtotime($u['birthdate']))/31536000);if($a>0&&$a<200):?><?=$a?>y<?php endif;endif;?>
  <?php if($u['admin']):?><span class="label label-primary">Staff</span><?php endif;?>
  <?php if(!empty($u['sysadmin'])):?><span class="label label-warning">SA</span><?php endif;?>
  <?php if(!empty($u['partner'])):?><span class="label label-success">Partner</span><?php endif;?></td>
  <td><button class="btn btn-primary btn-xs" onclick="window.open('edit_user.php?user_id=<?=$u['id']?>','manage','width=850,height=650,scrollbars=1');return false;">Manage</button></td>
</tr><?php endforeach; if(empty($userList)):?><tr><td colspan="5" class="text-muted" style="text-align:center;padding:12px;">No users found.</td></tr><?php endif;?></tbody>
</table></div>
