<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/security.php';
require_once __DIR__ . '/../../functions/messages.php';
if (!isLoggedIn()||($_SESSION['admin']??0)!=1||!isset($_SESSION['admin_verified'])){return;}

$adminId=$_SESSION['user_id'];
$ipToLookup=trim($_GET['ip']??'');
$ipCsrf=generateCSRF();

if(isset($_POST['action'])&&$_POST['action']==='ban'){
    if(!verifyCSRF($_POST['csrf_token']??'')){setMessage("error","CSRF failed.");}
    else{
        $ip=trim($_POST['ip_address']??'');
        if(filter_var($ip,FILTER_VALIDATE_IP)){$pdo->prepare("INSERT IGNORE INTO ip_bans(ip_address,banned_by) VALUES(?,?)")->execute([$ip,$adminId]);setMessage("success","IP banned.");}
        else{setMessage("error","Invalid IP.");}
    }
    echo '<script>window.location.href="index.php?page=ip_lookup&ip='.urlencode($ip).'"</script>'; return;
}
if(isset($_POST['action'])&&$_POST['action']==='unban'&&!empty($_POST['ban_id'])){
    if(!verifyCSRF($_POST['csrf_token']??'')){setMessage("error","CSRF failed.");}
    else{$pdo->prepare("DELETE FROM ip_bans WHERE id=?")->execute([(int)$_POST['ban_id']]);setMessage("success","IP unbanned.");}
    echo '<script>window.location.href="index.php?page=ip_lookup"</script>'; return;
}

$details=null;$associatedUsers=[];
if($ipToLookup&&filter_var($ipToLookup,FILTER_VALIDATE_IP)){
    if(filter_var($ipToLookup,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false){$details=['status'=>'fail','message'=>'Private IP'];}
    else{$s=$pdo->prepare("SELECT id,username,email FROM users WHERE last_login_ip=? OR registration_ip=?");$s->execute([$ipToLookup,$ipToLookup]);$associatedUsers=$s->fetchAll();
        if(!in_array($ipToLookup,['127.0.0.1','::1'])){$c=stream_context_create(['http'=>['timeout'=>2]]);$j=@file_get_contents("http://ip-api.com/json/".urlencode($ipToLookup)."?fields=status,country,city,isp,proxy,hosting",false,$c);$details=json_decode($j,true);}}
}
$bannedList=$pdo->query("SELECT b.*,u.username as admin_name FROM ip_bans b LEFT JOIN users u ON b.banned_by=u.id ORDER BY b.created_at DESC")->fetchAll();
?>

<div style="margin-bottom:12px;"></div>

<form method="get" class="form-inline" style="margin-bottom:10px;" onsubmit="return loadPage('ip_lookup',this)">
  <div class="form-group">
    <input type="text" name="ip" class="form-control input-sm" placeholder="Enter an IP address..." value="<?=e($ipToLookup)?>">
  </div>
  <button type="submit" class="btn btn-default btn-sm">Lookup</button>
  <?php if($ipToLookup):?> <a href="index.php?page=ip_lookup" class="btn btn-link btn-sm" onclick="return loadPage('ip_lookup')">Clear</a><?php endif;?>
</form>

<?php if($ipToLookup): if(!filter_var($ipToLookup,FILTER_VALIDATE_IP)):?>
<p class="text-danger">"<?=e($ipToLookup)?>" is not a valid IP address.</p>
<?php else:?>

<div class="row">
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">IP Details</div>
<table class="table">
  <tr><td style="font-weight:700;">Address</td><td><code><?=e($ipToLookup)?></code></td></tr>
  <tr><td style="font-weight:700;">Type</td><td><?=strpos($ipToLookup,':')!==false?'IPv6':'IPv4'?></td></tr>
  <?php if($details&&$details['status']==='success'):?>
  <tr><td style="font-weight:700;">ISP</td><td><?=e($details['isp'])?></td></tr>
  <tr><td style="font-weight:700;">Location</td><td><?=e($details['city'].', '.$details['country'])?></td></tr>
  <tr><td style="font-weight:700;">Risk</td><td><?php if($details['proxy']):?><span class="label label-danger">VPN</span><?php endif;?><?php if($details['hosting']):?><span class="label label-warning">Datacenter</span><?php endif;?><?php if(!$details['proxy']&&!$details['hosting']):?><span class="label label-success">Clean</span><?php endif;?></td></tr>
  <?php endif;?>
</table></div></div>

<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">Linked Accounts (<?=count($associatedUsers)?>)</div>
<div class="panel-body" style="padding:0;">
<?php if($associatedUsers):?>
<table class="table"><?php foreach($associatedUsers as $au):?><tr><td><a href="edit_user.php?user_id=<?=$au['id']?>" onclick="window.open('edit_user.php?user_id=<?=$au['id']?>','manage','width=850,height=650,scrollbars=1');return false;"><b><?=e($au['username'])?></b></a></td><td><?=e($au['email'])?></td><td><button class="btn btn-default btn-xs" onclick="window.open('edit_user.php?user_id=<?=$au['id']?>','manage','width=850,height=650,scrollbars=1')">Manage</button></td></tr><?php endforeach;?></table>
<?php else:?><p class="text-muted" style="padding:10px;margin:0;">No accounts on this IP.</p><?php endif;?>
</div></div></div></div>

<div class="panel panel-default">
<div class="panel-body" style="padding:6px 12px;">
  <b>IP:</b> <code><?=e($ipToLookup)?></code>
  <form method="post" action="index.php?page=ip_lookup" style="display:inline;margin-left:10px;">
    <input type="hidden" name="csrf_token" value="<?=$ipCsrf?>">
    <input type="hidden" name="action" value="ban">
    <input type="hidden" name="ip_address" value="<?=e($ipToLookup)?>">
    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Ban this IP?')">Ban IP</button>
  </form>
</div></div>
<?php endif; endif;?>

<div class="panel panel-default">
<div class="panel-heading">Active IP Bans</div>
<div class="panel-body" style="padding:0;">
<?php if($bannedList):?>
<table class="table table-striped"><thead><tr><th>IP</th><th>Banned By</th><th>Date</th><th>Action</th></tr></thead>
<tbody><?php foreach($bannedList as $b):?><tr><td><code><?=e($b['ip_address'])?></code></td><td><?=e($b['admin_name']??'System')?></td><td class="text-muted"><?=date('M j, Y',strtotime($b['created_at']))?></td>
<td><form method="post" action="index.php?page=ip_lookup" style="margin:0;"><input type="hidden" name="csrf_token" value="<?=$ipCsrf?>"><input type="hidden" name="action" value="unban"><input type="hidden" name="ban_id" value="<?=$b['id']?>"><button type="submit" class="btn btn-default btn-xs">Unban</button></form></td></tr><?php endforeach;?></tbody></table>
<?php else:?><p class="text-muted" style="text-align:center;padding:10px;margin:0;">No IP bans active.</p><?php endif;?>
</div></div>
