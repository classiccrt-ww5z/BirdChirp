<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';
require_once __DIR__ . '/../functions/admin_log.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { echo '<p style="color:#c00;padding:20px;">Access denied.</p>'; exit; }

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?"); $stmt->execute([$adminId]); $adminUsername = $stmt->fetchColumn() ?: 'Unknown Admin';
$adminCsrf = generateCSRF();
$tab = $_GET['tab'] ?? 'profile';
$selectedUserId = (int)($_GET['user_id'] ?? 0);
$incomingCsrf = $_GET['csrf'] ?? '';
$ajax = isset($_GET['ajax']);

$bulkActions = ['verify_user','unverify_user','reset_username','toggle_admin','login_as_user','delete_post','delete_reply','unban_user'];
foreach($bulkActions as $a){if(!empty($_GET[$a])){if(!verifyCSRF($incomingCsrf)){setMessage("error","CSRF failed.");if(!$ajax){header("Location: edit_user.php?user_id=$selectedUserId");exit;} else exit;}break;}}

if(isset($_GET['verify_user'])){
    $id=(int)$_GET['verify_user']; $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); $u=$s->fetchColumn();
    $pdo->prepare("UPDATE users SET is_verified=1, verification_token=NULL WHERE id=?")->execute([$id]);
    setMessage("success","User verified."); logAdminAction('Verify User',$u??"ID: $id",$adminUsername,"Email verified");
    if($ajax){require __DIR__."/edit_user_tabs/$tab.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}
if(isset($_GET['unverify_user'])){
    $id=(int)$_GET['unverify_user']; $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); $u=$s->fetchColumn();
    $pdo->prepare("UPDATE users SET is_verified=0 WHERE id=?")->execute([$id]);
    setMessage("success","User unverified."); logAdminAction('Unverify User',$u??"ID: $id",$adminUsername,"Email unverified");
    if($ajax){require __DIR__."/edit_user_tabs/$tab.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}
if(isset($_GET['reset_username'])){
    $id=(int)$_GET['reset_username']; $s=$pdo->prepare("SELECT username FROM users WHERE id=?"); $s->execute([$id]); $old=$s->fetchColumn();
    $new="UsernameReset$id"; $pdo->prepare("UPDATE users SET username=?, display_name=? WHERE id=?")->execute([$new,$new,$id]);
    setMessage("success","Username reset to $new."); logAdminAction('Reset Username',$old??"ID: $id",$adminUsername,"Reset from '$old' to '$new'");
    if($ajax){require __DIR__."/edit_user_tabs/$tab.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}
if(isset($_GET['toggle_admin'])){
    $id=(int)$_GET['toggle_admin'];
    if($id!=$adminId){$s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?");$s->execute([$id]);$tu=$s->fetch();
        $pdo->prepare("UPDATE users SET admin=1-admin WHERE id=?")->execute([$id]);
        $a=$tu['admin']?'Removed Admin':'Added Admin'; setMessage("success","Role updated."); logAdminAction('Admin Toggle',$tu['username']??"ID: $id",$adminUsername,$a);}
    if($ajax){require __DIR__."/edit_user_tabs/$tab.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}
if(isset($_GET['login_as_user'])){
    $id=(int)$_GET['login_as_user'];
    if($id!=$adminId){$s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?");$s->execute([$id]);$tu=$s->fetch();
        if(!$tu){setMessage("error","User not found.");if($ajax)exit;header("Location: edit_user.php");exit;}
        if($tu['admin']){setMessage("error","Cannot login as staff.");if($ajax)exit;header("Location: edit_user.php?user_id=$id");exit;}
        logAdminAction('Login As User',$tu['username'],$adminUsername,"Admin logged in as this user");
        $_SESSION['original_admin_id']=$_SESSION['user_id']; $_SESSION['original_admin_user']=$_SESSION['username']; $_SESSION['original_admin_avatar']=$_SESSION['avatar'];
        $_SESSION['user_id']=$id; $_SESSION['admin']=0; header("Location: /"); exit;}
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_user'])){
    $id=(int)$_POST['edit_user']; $nu=trim($_POST['username']??''); $nd=trim($_POST['display_name']??''); $nb=trim($_POST['bio']??'');
    $s=$pdo->prepare("SELECT username,email FROM users WHERE id=?"); $s->execute([$id]); $old=$s->fetch();
    $changes=[]; if($nu!==$old['username']) $changes[]="username: {$old['username']} -> $nu";
    $s=$pdo->prepare("SELECT 1 FROM users WHERE username=? AND id!=?"); $s->execute([$nu,$id]);
    if($s->fetch()&&$nu!==$old['username']){setMessage("error","Username taken.");}else{
        $partner=isset($_POST['partner'])?1:0; $pdo->prepare("UPDATE users SET username=?, display_name=?, bio=?, partner=? WHERE id=?")->execute([$nu,$nd,$nb,$partner,$id]);
        setMessage("success","User updated."); logAdminAction('Edit User',$old['username'],$adminUsername,implode(", ",$changes)?:"Profile updated");}
    if($ajax){require __DIR__."/edit_user_tabs/edit.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}
if(isset($_GET['delete_post'])){
    $pid=(int)$_GET['delete_post']; $s=$pdo->prepare("SELECT user_id,content,image,video FROM posts WHERE id=?"); $s->execute([$pid]); $pd=$s->fetch();
    if($pd){if($pd['image']){$p=__DIR__.'/../images/posts/'.$pd['image'];if(file_exists($p))unlink($p);}if($pd['video']){$p=__DIR__.'/../videos/posts/'.$pd['video'];if(file_exists($p))unlink($p);}}
    $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$pid]); setMessage("success","Post deleted.");
    logAdminAction('Delete Post',"Post ID: $pid",$adminUsername,substr($pd['content']??'',0,100));
    if($ajax){require __DIR__."/edit_user_tabs/posts.php";exit;} header("Location: edit_user.php?user_id=$selectedUserId&tab=posts"); exit;
}
if(isset($_GET['delete_reply'])){
    $rid=(int)$_GET['delete_reply']; $s=$pdo->prepare("SELECT user_id,content FROM replies WHERE id=?"); $s->execute([$rid]); $rd=$s->fetch();
    $pdo->prepare("DELETE FROM replies WHERE id=?")->execute([$rid]); setMessage("success","Reply deleted.");
    logAdminAction('Delete Reply',"Reply ID: $rid",$adminUsername,substr($rd['content']??'',0,100));
    if($ajax){require __DIR__."/edit_user_tabs/replies.php";exit;} header("Location: edit_user.php?user_id=$selectedUserId&tab=replies"); exit;
}
if(isset($_GET['unban_user'])){
    $id=(int)$_GET['unban_user']; $s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?"); $s->execute([$id]); $tu=$s->fetch();
    if(isset($tu['admin'])&&(int)$tu['admin']===1){setMessage("error","Cannot unban staff.");}else{$pdo->prepare("DELETE FROM bans WHERE user_id=?")->execute([$id]); setMessage("success","User unbanned."); logAdminAction('Unban',$tu['username']??"ID: $id",$adminUsername,"Ban lifted");}
    if($ajax){require __DIR__."/edit_user_tabs/ban.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ban_user_id'])){
    $id=(int)$_POST['ban_user_id']; $reason=trim($_POST['reason']??'No reason');
    $s=$pdo->prepare("SELECT username,admin FROM users WHERE id=?"); $s->execute([$id]); $tu=$s->fetch();
    $self=$id===(int)$_SESSION['user_id']; $staff=isset($tu['admin'])&&(int)$tu['admin']===1;
    if($self){setMessage("error","Cannot ban self.");}elseif($staff){setMessage("error","Cannot ban staff.");}else{$pdo->prepare("INSERT INTO bans(user_id,reason,banned_at) VALUES(?,?,NOW())")->execute([$id,$reason]); setMessage("success","User banned."); logAdminAction('Ban',$tu['username']??"ID: $id",$adminUsername,"Reason: $reason");}
    if($ajax){require __DIR__."/edit_user_tabs/ban.php";exit;} header("Location: edit_user.php?user_id=$id"); exit;
}

function ipd($ip){if(!$ip||$ip=='127.0.0.1'||$ip=='::1')return"Localhost";$c=stream_context_create(['http'=>['timeout'=>2]]);$j=@file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,proxy",false,$c);$d=json_decode($j,true);return($d&&$d['status']=='success')?"{$d['city']}, {$d['country']} ({$d['isp']})".($d['proxy']?" [VPN]":""):"Unknown";}

$selectedUser=null;
if($selectedUserId){$s=$pdo->prepare("SELECT u.*,(SELECT COUNT(*) FROM follows WHERE following_id=u.id)fc,(SELECT COUNT(*) FROM follows WHERE follower_id=u.id)fc2 FROM users u WHERE u.id=?");$s->execute([$selectedUserId]);$selectedUser=$s->fetch();}
if(!$selectedUser){echo '<p style="color:#c00;padding:20px;">User not found.</p>';exit;}
$bannedMap=[];try{$bannedMap=$pdo->query("SELECT user_id,reason FROM bans")->fetchAll(PDO::FETCH_KEY_PAIR);}catch(PDOException $e){}
$isBanned=isset($bannedMap[$selectedUser['id']]);

$msgHtml = '';
$msg = $_SESSION['site_message'] ?? null;
if($msg){$c=['success'=>'success','error'=>'danger','info'=>'info']; $msgHtml='<div class="alert alert-'.($c[$msg['type']]??'info').' alert-dismissible" style="margin-bottom:12px;"><button type="button" class="close" data-dismiss="alert">&times;</button>'.e($msg['text']).'</div>'; unset($_SESSION['site_message']);}

if ($ajax) {
    echo $msgHtml;
    $tabFile = __DIR__ . "/edit_user_tabs/$tab.php";
    if (file_exists($tabFile)) { require $tabFile; }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit User - <?=e($selectedUser['username'])?></title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
<style>
*{box-sizing:border-box}
body{font-family:Verdana,sans-serif;font-size:12px;background:#f4f4f4;padding:12px}
h2{font-size:16px;margin:0 0 10px;padding-bottom:4px;border-bottom:2px solid #eee;color:#333}
.panel{margin-bottom:12px}
.panel .panel-heading{padding:6px 12px;font-size:12px}
.panel .panel-body{padding:10px 12px}
.panel table{margin-bottom:0}
.nav-tabs{margin-bottom:10px}
.btn-group-xs>.btn,.btn-xs{padding:2px 8px;font-size:11px}
#tab-loading{display:none;position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.85);z-index:999}
#tab-loading>div{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center}
#tab-wrapper{position:relative;min-height:60px}
</style>
</head>
<body>

<?=$msgHtml?>

<div class="panel panel-default">
<div class="panel-body" style="padding:8px 12px;">
  <b><?=e($selectedUser['username'])?></b> #<?=$selectedUser['id']?>
  <?php if($isBanned):?><span class="label label-danger">Banned</span><?php endif;?>
  <?php if($selectedUser['sysadmin']):?><span class="label label-warning">Sysadmin</span><?php endif;?>
  <?php if($selectedUser['admin']):?><span class="label label-primary">Staff</span><?php endif;?>
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

<div id="tab-wrapper" style="position:relative;">
<div id="tab-loading">
<div>
<span class="glyphicon glyphicon-refresh spin" style="font-size:28px;"></span>
<p>Loading...</p>
</div>
</div>
<div id="tab-content">
<?php
$tabFile = __DIR__ . "/edit_user_tabs/$tab.php";
if (file_exists($tabFile)) { require $tabFile; }
?>
</div></div>

<style>
@-webkit-keyframes spin{0%{-webkit-transform:rotate(0deg)}100%{-webkit-transform:rotate(359deg)}}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(359deg)}}
.glyphicon-refresh.spin{-webkit-animation:spin 1s infinite linear;animation:spin 1s infinite linear}
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
$(function(){
$('.nav-tabs a').on('click',function(e){
e.preventDefault();
var href=$(this).attr('href');
var $content=$('#tab-content');
var $loading=$('#tab-loading');
$('.nav-tabs li').removeClass('active');
$(this).parent().addClass('active');
$loading.show();
history.pushState(null,'',href);
$.get(href+'&ajax=1',function(html){
$content.html(html);
$loading.hide();
});
});
$(window).on('popstate',function(){location.reload();});
});
</script>
</body>
</html>