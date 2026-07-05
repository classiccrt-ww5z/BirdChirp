<?php $banReason=$bannedMap[$selectedUser['id']]??''; ?>
<div class="panel panel-default">
<div class="panel-heading"><?=$isBanned?'Unban':'Ban'?> <?=e($selectedUser['username'])?></div>
<div class="panel-body">
<?php if($isBanned):?>
<blockquote style="border-left:3px solid #c00;padding:8px 12px;background:#f8f8f8;margin:0 0 8px;font-size:12px;"><?=e($banReason)?></blockquote>
<?php else:?><form method="POST" class="form-inline"><input type="hidden" name="ban_user_id" value="<?=$selectedUser['id']?>">
  <div class="form-group" style="width:300px;"><input type="text" name="reason" class="form-control input-sm" placeholder="Violation details..." required></div>
  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Ban this user?')">Ban User</button>
  <p class="help-block">This reason is public.</p>
</form><?php endif;?>
</div></div>