<?php $s=$pdo->prepare("SELECT u.* FROM follows f JOIN users u ON f.following_id=u.id WHERE f.follower_id=? ORDER BY f.id DESC");$s->execute([$selectedUser['id']]);$fl=$s->fetchAll();?>
<div class="panel panel-default">
<table class="table"><thead><tr><th>User</th><th>Email</th><th>Action</th></tr></thead><tbody>
<?php foreach($fl as $f):?><tr><td><b><?=e($f['username'])?></b></td><td><?=e($f['email'])?></td><td><a href="edit_user.php?user_id=<?=$f['id']?>">Manage</a></td></tr><?php endforeach;?>
<?php if(empty($fl)):?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:12px;">No users.</td></tr><?php endif;?>
</tbody></table></div>