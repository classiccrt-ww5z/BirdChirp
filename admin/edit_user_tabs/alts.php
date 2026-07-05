<?php $rIp=$selectedUser['registration_ip']??'0.0.0.0';$lIp=$selectedUser['last_login_ip']??'0.0.0.0';
$s=$pdo->prepare("SELECT id,username,email,registration_ip,last_login_ip FROM users WHERE (registration_ip=? OR last_login_ip=? OR registration_ip=? OR last_login_ip=?) AND id!=? ORDER BY id DESC");
$s->execute([$rIp,$rIp,$lIp,$lIp,$selectedUser['id']]);$alts=$s->fetchAll();?>
<div class="panel panel-default">
<table class="table"><thead><tr><th>User</th><th>Reg IP</th><th>Last IP</th><th>Action</th></tr></thead><tbody>
<?php foreach($alts as $alt):?><tr><td><b><?=e($alt['username']??'')?></b></td><td><code><?=e($alt['registration_ip']??'')?></code></td><td><code><?=e($alt['last_login_ip']??'')?></code></td><td><a href="edit_user.php?user_id=<?=$alt['id']?>">Manage</a></td></tr><?php endforeach;?>
<?php if(empty($alts)):?><tr><td colspan="4" class="text-muted" style="text-align:center;padding:12px;">No linked accounts.</td></tr><?php endif;?>
</tbody></table></div>