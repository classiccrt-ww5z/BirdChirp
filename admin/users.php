<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';
require_once __DIR__ . '/../functions/admin_log.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { header("Location: /"); exit; }

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?"); $stmt->execute([$adminId]); $adminUsername = $stmt->fetchColumn() ?: 'Unknown Admin';
$adminCsrf = generateCSRF();
$searchQuery = trim($_GET['search'] ?? '');
$tab = $_GET['tab'] ?? 'profile';
$filter = $_GET['filter'] ?? 'newest';
$selectedUserId = (int)($_GET['user_id'] ?? 0);
$incomingCsrf = $_GET['csrf'] ?? '';

$bulkActions = ['verify_user','unverify_user','reset_username','toggle_admin','login_as_user','restore_admin','delete_post','delete_reply','unban_user'];
foreach($bulkActions as $a){if(!empty($_GET[$a])){if(!verifyCSRF($incomingCsrf)){setMessage("error","CSRF failed.");header("Location: users.php");exit;}break;}}

if(isset($_GET['verify_user'])){
    $id=(int)$_GET['verify_user']; $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); $u=$s->fetchColumn();
    $pdo->prepare("UPDATE users SET is_verified=1, verification_token=NULL WHERE id=?")->execute([$id]);
    setMessage("success","User verified."); logAdminAction('Verify User',$u??"ID: $id",$adminUsername,"Email verified"); header("Location: users.php?user_id=$id"); exit;
}
if(isset($_GET['unverify_user'])){
    $id=(int)$_GET['unverify_user']; $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); $u=$s->fetchColumn();
    $pdo->prepare("UPDATE users SET is_verified=0 WHERE id=?")->execute([$id]);
    setMessage("success","User unverified."); logAdminAction('Unverify User',$u??"ID: $id",$adminUsername,"Email unverified"); header("Location: users.php?user_id=$id"); exit;
}
if(isset($_GET['reset_username'])){
    $id=(int)$_GET['reset_username']; $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); $old=$s->fetchColumn();
    $new="UsernameReset$id"; $pdo->prepare("UPDATE users SET username=?, display_name=? WHERE id=?")->execute([$new,$new,$id]);
    setMessage("success","Username reset to $new."); logAdminAction('Reset Username',$old??"ID: $id",$adminUsername,"Reset from '$old' to '$new'"); header("Location: users.php?user_id=$id"); exit;
}
if(isset($_GET['toggle_admin'])){
    $id=(int)$_GET['toggle_admin'];
    if($id!=$adminId){$s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?");$s->execute([$id]);$tu=$s->fetch();
        $pdo->prepare("UPDATE users SET admin=1-admin WHERE id=?")->execute([$id]);
        $a=$tu['admin']?'Removed Admin':'Added Admin'; setMessage("success","Role updated."); logAdminAction('Admin Toggle',$tu['username']??"ID: $id",$adminUsername,$a);}
    header("Location: users.php?user_id=$id"); exit;
}
if(isset($_GET['login_as_user'])){
    $id=(int)$_GET['login_as_user'];
    if($id!=$adminId){$s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?");$s->execute([$id]);$tu=$s->fetch();
        if(!$tu){setMessage("error","User not found.");header("Location: users.php");exit;}
        if($tu['admin']){setMessage("error","Cannot login as staff.");header("Location: users.php?user_id=$id");exit;}
        logAdminAction('Login As User',$tu['username'],$adminUsername,"Admin logged in as this user");
        $_SESSION['original_admin_id']=$_SESSION['user_id']; $_SESSION['original_admin_user']=$_SESSION['username']; $_SESSION['original_admin_avatar']=$_SESSION['avatar'];
        $_SESSION['user_id']=$id; $_SESSION['admin']=0; header("Location: /"); exit;}
}
if(isset($_GET['restore_admin'])){
    if(isset($_SESSION['original_admin_id'])){
        logAdminAction('Restore Admin',$_SESSION['username']??"Unknown",$adminUsername,"Returned to admin account");
        $_SESSION['user_id']=$_SESSION['original_admin_id']; $_SESSION['username']=$_SESSION['original_admin_user']; $_SESSION['avatar']=$_SESSION['original_admin_avatar'];
        $_SESSION['admin']=1; unset($_SESSION['original_admin_id'],$_SESSION['original_admin_user'],$_SESSION['original_admin_avatar']);}
    header("Location: users.php"); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_user'])){
    $id=(int)$_POST['edit_user']; $nu=trim($_POST['username']??''); $nd=trim($_POST['display_name']??''); $nb=trim($_POST['bio']??'');
    $s=$pdo->prepare("SELECT username,email FROM users WHERE id=?"); $s->execute([$id]); $old=$s->fetch();
    $changes=[]; if($nu!==$old['username']) $changes[]="username: {$old['username']} -> $nu";
    $s=$pdo->prepare("SELECT 1 FROM users WHERE username=? AND id!=?"); $s->execute([$nu,$id]);
    if($s->fetch()&&$nu!==$old['username']){setMessage("error","Username taken.");}else{
        $partner=isset($_POST['partner'])?1:0; $pdo->prepare("UPDATE users SET username=?, display_name=?, bio=?, partner=? WHERE id=?")->execute([$nu,$nd,$nb,$partner,$id]);
        setMessage("success","User updated."); logAdminAction('Edit User',$old['username'],$adminUsername,implode(", ",$changes)?:"Profile updated");}
    header("Location: users.php?user_id=$id"); exit;
}
if(isset($_GET['delete_post'])){
    $pid=(int)$_GET['delete_post']; $s=$pdo->prepare("SELECT user_id,content,image,video FROM posts WHERE id=?"); $s->execute([$pid]); $pd=$s->fetch();
    if($pd){if($pd['image']){$p=__DIR__.'/../images/posts/'.$pd['image'];if(file_exists($p))unlink($p);}if($pd['video']){$p=__DIR__.'/../videos/posts/'.$pd['video'];if(file_exists($p))unlink($p);}}
    $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$pid]); setMessage("success","Post deleted.");
    logAdminAction('Delete Post',"Post ID: $pid",$adminUsername,substr($pd['content']??'',0,100));
    header("Location: users.php?user_id=$selectedUserId&tab=posts"); exit;
}
if(isset($_GET['delete_reply'])){
    $rid=(int)$_GET['delete_reply']; $s=$pdo->prepare("SELECT user_id,content FROM replies WHERE id=?"); $s->execute([$rid]); $rd=$s->fetch();
    $pdo->prepare("DELETE FROM replies WHERE id=?")->execute([$rid]); setMessage("success","Reply deleted.");
    logAdminAction('Delete Reply',"Reply ID: $rid",$adminUsername,substr($rd['content']??'',0,100));
    header("Location: users.php?user_id=$selectedUserId&tab=replies"); exit;
}
if(isset($_GET['unban_user'])){
    $id=(int)$_GET['unban_user']; $s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?"); $s->execute([$id]); $tu=$s->fetch();
    if(isset($tu['admin'])&&(int)$tu['admin']===1){setMessage("error","Cannot unban staff.");}else{$pdo->prepare("DELETE FROM bans WHERE user_id=?")->execute([$id]); setMessage("success","User unbanned."); logAdminAction('Unban',$tu['username']??"ID: $id",$adminUsername,"Ban lifted");}
    header("Location: users.php?user_id=$id"); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ban_user_id'])){
    $id=(int)$_POST['ban_user_id']; $reason=trim($_POST['reason']??'No reason');
    $s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?"); $s->execute([$id]); $tu=$s->fetch();
    $self=$id===(int)$_SESSION['user_id']; $staff=isset($tu['admin'])&&(int)$tu['admin']===1;
    if($self){setMessage("error","Cannot ban self.");}elseif($staff){setMessage("error","Cannot ban staff.");}else{$pdo->prepare("INSERT INTO bans(user_id,reason,banned_at) VALUES(?,?,NOW())")->execute([$id,$reason]); setMessage("success","User banned."); logAdminAction('Ban',$tu['username']??"ID: $id",$adminUsername,"Reason: $reason");}
    header("Location: users.php?user_id=$id"); exit;
}

function ipd($ip){if(!$ip||$ip=='127.0.0.1'||$ip=='::1')return"Localhost";$c=stream_context_create(['http'=>['timeout'=>2]]);$j=@file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,proxy",false,$c);$d=json_decode($j,true);return($d&&$d['status']=='success')?"{$d['city']}, {$d['country']} ({$d['isp']})".($d['proxy']?" [VPN]":""):"Unknown";}

$selectedUser=null;
if($selectedUserId){$s=$pdo->prepare("SELECT u.*,(SELECT COUNT(*) FROM follows WHERE following_id=u.id)fc,(SELECT COUNT(*) FROM follows WHERE follower_id=u.id)fc2 FROM users u WHERE u.id=?");$s->execute([$selectedUserId]);$selectedUser=$s->fetch();}
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

$isPopup = isset($_GET['popup']);
require_once "header.php";
?>

<?php if(!($selectedUserId && $isPopup)): ?>
<form method="get" class="form-inline" style="margin-bottom:10px;">
  <div class="form-group">
    <input type="text" name="search" class="form-control input-sm" placeholder="Search username or email..." value="<?=e($searchQuery)?>">
  </div>
  <button type="submit" class="btn btn-default btn-sm">Search</button>
  <?php if($searchQuery||$selectedUserId):?> <a href="users.php" class="btn btn-link btn-sm">Clear</a><?php endif;?>
</form>

<ul class="nav nav-pills" style="margin-bottom:10px;">
  <li class="<?=$filter=='newest'?'active':''?>"><a href="?filter=newest<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">Newest</a></li>
  <li class="<?=$filter=='popular'?'active':''?>"><a href="?filter=popular<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">Popular</a></li>
  <li class="<?=$filter=='verified'?'active':''?>"><a href="?filter=verified<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">Verified</a></li>
  <li class="<?=$filter=='unverified'?'active':''?>"><a href="?filter=unverified<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">Unverified</a></li>
  <li class="<?=$filter=='staff'?'active':''?>"><a href="?filter=staff<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">Staff</a></li>
  <li class="<?=$filter=='partner'?'active':''?>"><a href="?filter=partner<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">Partners</a></li>
  <li class="<?=$filter=='no_birthdate'?'active':''?>"><a href="?filter=no_birthdate<?=$searchQuery?'&search='.urlencode($searchQuery):''?>">No BD</a></li>
</ul>
<?php endif; ?>

<?php if($selectedUser): $isBanned=isset($bannedMap[$selectedUser['id']]); ?>
<div class="panel panel-default">
<div class="panel-body" style="padding:8px 12px;">
  <b><?=e($selectedUser['username'])?></b> #<?=$selectedUser['id']?>
  <?php if($isBanned):?><span class="badge badge-red">Banned</span><?php endif;?>
  <?php if($selectedUser['sysadmin']):?><span class="badge badge-org">Sysadmin</span><?php endif;?>
  <?php if($selectedUser['admin']):?><span class="badge badge-blu">Staff</span><?php endif;?>
  <div class="pull-right btn-group btn-group-xs">
    <?php if($isBanned):?><a href="?unban_user=<?=$selectedUser['id']?>&user_id=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-default" onclick="return confirm('Unban?')">Unban</a><?php else:?><a href="?user_id=<?=$selectedUser['id']?>&tab=ban" class="btn btn-danger">Ban</a><?php endif;?>
    <a href="?toggle_admin=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-default">Toggle Staff</a>
    <a href="?reset_username=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-default" onclick="return confirm('Reset username?')">Reset Name</a>
    <?php if($selectedUser['is_verified']):?><a href="?unverify_user=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-default">Unverify</a><?php else:?><a href="?verify_user=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-default">Verify</a><?php endif;?>
    <?php if($selectedUser['id']!=$adminId):?><a href="?login_as_user=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-default" onclick="return confirm('Login as this user?')">Login As</a><?php endif;?>
  </div>
</div></div>

<ul class="nav nav-tabs">
  <li class="<?=$tab=='profile'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=profile">Profile</a></li>
  <li class="<?=$tab=='edit'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=edit">Edit</a></li>
  <li class="<?=$tab=='posts'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=posts">Posts</a></li>
  <li class="<?=$tab=='replies'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=replies">Replies</a></li>
  <li class="<?=$tab=='followers'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=followers">Followers</a></li>
  <li class="<?=$tab=='following'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=following">Following</a></li>
  <li class="<?=$tab=='ipinfo'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=ipinfo">IP Info</a></li>
  <li class="<?=$tab=='alts'?'active':''?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=alts">Alts</a></li>
</ul>

<?php if($tab=='profile'): ?>
<div class="panel panel-default">
<table class="table">
  <tr><td style="width:140px;font-weight:700;">Username</td><td><b><?=e($selectedUser['username'])?></b></td></tr>
  <tr><td style="font-weight:700;">Email</td><td><?=e($selectedUser['email'])?></td></tr>
  <tr><td style="font-weight:700;">Display Name</td><td><?=e($selectedUser['display_name']??'')?:'<span class="text-muted">-</span>'?></td></tr>
  <tr><td style="font-weight:700;">Bio</td><td><?=nl2br(e($selectedUser['bio']??''))?:'<span class="text-muted">-</span>'?></td></tr>
  <tr><td style="font-weight:700;">Joined</td><td><?=date('M j, Y g:i A',strtotime($selectedUser['created_at']))?></td></tr>
  <tr><td style="font-weight:700;">Birthdate</td><td><?php if(!empty($selectedUser['birthdate'])&&$selectedUser['birthdate']!=='0000-00-00'):?><?=e($selectedUser['birthdate'])?><?php $age=(int)((time()-strtotime($selectedUser['birthdate']))/31536000);if($age>0&&$age<200):?> (<?=$age?>y)<?php endif;?><?php else:?><span class="label label-danger">Not Set</span><?php endif;?></td></tr>
  <tr><td style="font-weight:700;">Status</td><td><?=$selectedUser['is_verified']?'<span class="label label-success">Verified</span>':'<span class="label label-default">Unverified</span>'?> <?=$selectedUser['admin']?'<span class="label label-primary">Staff</span>':''?> <?=$selectedUser['sysadmin']?'<span class="label label-warning">Sysadmin</span>':''?> <?=isset($bannedMap[$selectedUser['id']])?'<span class="label label-danger">Banned</span>':''?> <?=$selectedUser['partner']?'<span class="label label-success">Partner</span>':''?></td></tr>
</table></div>

<?php elseif($tab=='edit'): ?>
<div class="panel panel-default">
<div class="panel-heading">Edit <?=e($selectedUser['username'])?></div>
<div class="panel-body">
<form method="POST" class="form-horizontal"><input type="hidden" name="edit_user" value="<?=$selectedUser['id']?>">
  <div class="form-group"><label class="col-sm-2 control-label">Username</label><div class="col-sm-6"><input type="text" name="username" class="form-control input-sm" value="<?=e($selectedUser['username']??'')?>"></div></div>
  <div class="form-group"><label class="col-sm-2 control-label">Display Name</label><div class="col-sm-6"><input type="text" name="display_name" class="form-control input-sm" value="<?=e($selectedUser['display_name']??'')?>"></div></div>
  <div class="form-group"><label class="col-sm-2 control-label">Bio</label><div class="col-sm-10"><textarea name="bio" class="form-control input-sm" rows="4"><?=e($selectedUser['bio']??'')?></textarea></div></div>
  <div class="form-group"><div class="col-sm-offset-2 col-sm-10"><div class="checkbox"><label><input type="checkbox" name="partner" value="1" <?=!empty($selectedUser['partner'])?'checked':''?>> Partner status</label></div></div></div>
  <div class="form-group"><div class="col-sm-offset-2 col-sm-10"><button type="submit" class="btn btn-primary btn-sm">Save Changes</button></div></div>
</form>
</div></div>

<?php elseif($tab=='ban'): $banReason=$bannedMap[$selectedUser['id']]??''; ?>
<div class="panel panel-default">
<div class="panel-heading"><?=$isBanned?'Unban':'Ban'?> <?=e($selectedUser['username'])?></div>
<div class="panel-body">
<?php if($isBanned):?>
<blockquote style="border-left:3px solid #c00;padding:8px 12px;background:#f8f8f8;margin:0 0 8px;font-size:12px;"><?=e($banReason)?></blockquote>
<?php else:?><form method="POST" class="form-inline"><input type="hidden" name="ban_user_id" value="<?=$selectedUser['id']?>">
  <div class="form-group"><input type="text" name="reason" class="form-control input-sm" placeholder="Violation details..." required style="width:300px;"></div>
  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Ban this user?')">Ban User</button>
  <p class="help-block">This reason is public.</p>
</form><?php endif;?>
</div></div>

<?php elseif($tab=='posts'): $s=$pdo->prepare("SELECT p.*,u.username,u.display_name,u.avatar FROM posts p JOIN users u ON p.user_id=u.id WHERE p.user_id=? ORDER BY p.created_at DESC");$s->execute([$selectedUser['id']]);$posts=$s->fetchAll(); ?>
<div class="panel panel-default">
<div class="panel-body">
<?php if($posts): foreach($posts as $p):?>
<div class="media" style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
  <div class="media-left"><img src="/images/avatars/<?=e($p['avatar']??'default.png')?>" style="width:32px;height:32px;" class="media-object img-thumbnail"></div>
  <div class="media-body">
    <b><?=e($p['display_name']?:$p['username'])?></b> <span class="text-muted"><?=$p['created_at']?></span>
    <p style="margin:4px 0;"><?=nl2br(e($p['content']))?></p>
    <?php if($p['image']):?><img src="/images/posts/<?=e($p['image'])?>" style="max-width:200px;max-height:150px;" class="img-thumbnail"><?php endif;?>
    <?php if($p['video']):?><video controls style="max-width:200px;max-height:150px;" class="img-thumbnail"><source src="/videos/posts/<?=e($p['video'])?>" type="video/mp4"></video><?php endif;?>
    <p style="margin:6px 0 0;"><a href="?delete_post=<?=$p['id']?>&user_id=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete?')">Delete</a></p>
  </div>
</div>
<?php endforeach; else:?><p class="text-muted" style="text-align:center;padding:12px;">No posts.</p><?php endif;?>
</div></div>

<?php elseif($tab=='replies'): $s=$pdo->prepare("SELECT r.*,u.username,u.display_name,u.avatar FROM replies r JOIN users u ON r.user_id=u.id WHERE r.user_id=? ORDER BY r.created_at DESC");$s->execute([$selectedUser['id']]);$replies=$s->fetchAll(); ?>
<div class="panel panel-default">
<div class="panel-body">
<?php if($replies): foreach($replies as $r):?>
<div style="padding:8px 0;border-bottom:1px solid #eee;">
  <b><?=e($r['display_name']?:$r['username'])?></b> <span class="text-muted">replied <?=$r['created_at']?></span>
  <p style="margin:4px 0;"><?=nl2br(e($r['content']))?></p>
  <a href="?delete_reply=<?=$r['id']?>&user_id=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete?')">Delete</a>
</div>
<?php endforeach; else:?><p class="text-muted" style="text-align:center;padding:12px;">No replies.</p><?php endif;?>
</div></div>

<?php elseif($tab=='followers'||$tab=='following'):
$isF=$tab=='followers';$q=$isF?"SELECT u.* FROM follows f JOIN users u ON f.follower_id=u.id WHERE f.following_id=? ORDER BY f.id DESC":"SELECT u.* FROM follows f JOIN users u ON f.following_id=u.id WHERE f.follower_id=? ORDER BY f.id DESC";
$s=$pdo->prepare($q);$s->execute([$selectedUser['id']]);$fl=$s->fetchAll();?>
<div class="panel panel-default">
<table class="table"><thead><tr><th>User</th><th>Email</th><th>Action</th></tr></thead><tbody>
<?php foreach($fl as $f):?><tr><td><b><?=e($f['username'])?></b></td><td><?=e($f['email'])?></td><td><a href="?user_id=<?=$f['id']?>">Manage</a></td></tr><?php endforeach;?>
<?php if(empty($fl)):?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:12px;">No users.</td></tr><?php endif;?>
</tbody></table></div>

<?php elseif($tab=='ipinfo'): ?>
<div class="panel panel-default">
<table class="table">
  <tr><td style="width:140px;font-weight:700;">Registration IP</td><td><code><?=e($selectedUser['registration_ip']??'N/A')?></code><br><span class="text-muted"><?=ipd($selectedUser['registration_ip']??'')?></span></td></tr>
  <tr><td style="font-weight:700;">Last Login IP</td><td><code><?=e($selectedUser['last_login_ip']??'N/A')?></code><br><span class="text-muted"><?=ipd($selectedUser['last_login_ip']??'')?></span></td></tr>
  <tr><td style="font-weight:700;">Flag</td><td><?=(($selectedUser['registration_ip']??'1')!==($selectedUser['last_login_ip']??'2'))?'<span class="label label-danger">Mismatch</span>':'<span class="label label-success">Consistent</span>'?></td></tr>
</table></div>

<?php elseif($tab=='alts'): $rIp=$selectedUser['registration_ip']??'0.0.0.0';$lIp=$selectedUser['last_login_ip']??'0.0.0.0';
$s=$pdo->prepare("SELECT id,username,email,registration_ip,last_login_ip FROM users WHERE (registration_ip=? OR last_login_ip=? OR registration_ip=? OR last_login_ip=?) AND id!=? ORDER BY id DESC");
$s->execute([$rIp,$rIp,$lIp,$lIp,$selectedUser['id']]);$alts=$s->fetchAll();?>
<div class="panel panel-default">
<table class="table"><thead><tr><th>User</th><th>Reg IP</th><th>Last IP</th><th>Action</th></tr></thead><tbody>
<?php foreach($alts as $alt):?><tr><td><b><?=e($alt['username']??'')?></b></td><td><code><?=e($alt['registration_ip']??'')?></code></td><td><code><?=e($alt['last_login_ip']??'')?></code></td><td><a href="?user_id=<?=$alt['id']?>">Manage</a></td></tr><?php endforeach;?>
<?php if(empty($alts)):?><tr><td colspan="4" class="text-muted" style="text-align:center;padding:12px;">No linked accounts.</td></tr><?php endif;?>
</tbody></table></div>
<?php endif; ?>

<?php else: ?>
<div class="panel panel-default">
<table class="table table-striped">
<thead><tr><th>ID</th><th>User</th><th>Followers</th><th>Status</th><th>Action</th></tr></thead>
<tbody><?php foreach($userList as $u):?><tr>
  <td>#<?=$u['id']?></td>
  <td><a href="?user_id=<?=$u['id']?>"><b><?=e($u['username'])?></b><br><span class="text-muted"><?=e($u['email'])?></span></a></td>
  <td><?=$u['fc']?></td>
  <td><?php if(isset($bannedMap[$u['id']])):?><span class="label label-danger">Banned</span><?php elseif($u['is_verified']):?><span class="label label-success">Verified</span><?php elseif(empty($u['birthdate'])):?><span class="label label-danger">No BD</span><?php else:$a=(int)((time()-strtotime($u['birthdate']))/31536000);if($a>0&&$a<200):?><?=$a?>y<?php endif;endif;?>
  <?php if($u['admin']):?><span class="label label-primary">Staff</span><?php endif;?>
  <?php if(!empty($u['sysadmin'])):?><span class="label label-warning">SA</span><?php endif;?>
  <?php if(!empty($u['partner'])):?><span class="label label-success">Partner</span><?php endif;?></td>
  <td><button class="btn btn-primary btn-xs" onclick="window.open('users.php?user_id=<?=$u['id']?>&popup=1','manage','width=850,height=650,scrollbars=1');return false;">Manage</button></td>
</tr><?php endforeach; if(empty($userList)):?><tr><td colspan="5" class="text-muted" style="text-align:center;padding:12px;">No users found.</td></tr><?php endif;?></tbody>
</table></div>
<?php endif; ?>

<?php require_once "footer.php"; ?>
