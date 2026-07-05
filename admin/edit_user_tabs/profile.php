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