<?php $s=$pdo->prepare("SELECT r.*,u.username,u.display_name,u.avatar FROM replies r JOIN users u ON r.user_id=u.id WHERE r.user_id=? ORDER BY r.created_at DESC");$s->execute([$selectedUser['id']]);$replies=$s->fetchAll(); ?>
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