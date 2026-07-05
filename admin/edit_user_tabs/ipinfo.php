<?php
?>
<div class="panel panel-default">
<table class="table">
  <tr><td style="width:140px;font-weight:700;">Registration IP</td><td><code><?=e($selectedUser['registration_ip']??'N/A')?></code><br><span class="text-muted"><?=ipd($selectedUser['registration_ip']??'')?></span></td></tr>
  <tr><td style="font-weight:700;">Last Login IP</td><td><code><?=e($selectedUser['last_login_ip']??'N/A')?></code><br><span class="text-muted"><?=ipd($selectedUser['last_login_ip']??'')?></span></td></tr>
  <tr><td style="font-weight:700;">Flag</td><td><?=(($selectedUser['registration_ip']??'1')!==($selectedUser['last_login_ip']??'2'))?'<span class="label label-danger">Mismatch</span>':'<span class="label label-success">Consistent</span>'?></td></tr>
</table></div>