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