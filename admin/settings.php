<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1) { header("Location: /"); exit; }

$settings = [];
try { $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings"); while($r=$stmt->fetch(PDO::FETCH_NUM)) $settings[$r[0]]=$r[1]; } catch(PDOException $e){}
function gs($k,$d='0'){global $settings;return $settings[$k]??$d;}
function ss($k,$v){global $pdo,$settings; try{$c=$pdo->prepare("SELECT id FROM site_settings WHERE setting_key=?");$c->execute([$k]);if($c->fetch()){$pdo->prepare("UPDATE site_settings SET setting_value=? WHERE setting_key=?")->execute([$v,$k]);}else{$pdo->prepare("INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?)")->execute([$v,$k]);}$settings[$k]=$v;}catch(PDOException $e){}}

$settingsCsrf = generateCSRF();
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
    if(!verifyCSRF($_POST['csrf_token']??'')){setMessage("error","CSRF failed.");header("Location: settings.php");exit;}
    ss('allow_signup',isset($_POST['allow_signup'])?'1':'0');
    ss('require_birthdate',isset($_POST['require_birthdate'])?'1':'0');
    ss('min_age',(string)max(1,min(100,(int)($_POST['min_age']??14))));
    ss('site_name',trim($_POST['site_name']??'BirdChirp'));
    ss('site_description',trim($_POST['site_description']??''));
    ss('maintenance_mode',isset($_POST['maintenance_mode'])?'1':'0');
    ss('require_verification',isset($_POST['require_verification'])?'1':'0');
    setMessage("success","Settings saved.");
    header("Location: settings.php"); exit;
}

require_once "header.php";
?>


<div class="row">
<div class="col-md-7">
<div class="panel panel-default">
<div class="panel-heading">Configuration</div>
<div class="panel-body">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?=$settingsCsrf?>">
  <div class="checkbox"><label><input type="checkbox" name="allow_signup" value="1" <?=gs('allow_signup','1')==='1'?'checked':''?>> Allow signups</label></div>
  <div class="checkbox"><label><input type="checkbox" name="require_birthdate" value="1" <?=gs('require_birthdate','1')==='1'?'checked':''?>> Require birthdate</label></div>
  <div class="checkbox"><label><input type="checkbox" name="require_verification" value="1" <?=gs('require_verification','0')==='1'?'checked':''?>> Require email verification</label></div>
  <div class="checkbox"><label><input type="checkbox" name="maintenance_mode" value="1" <?=gs('maintenance_mode','0')==='1'?'checked':''?>> Maintenance mode</label></div>
  <div class="form-group">
    <label>Min Age</label>
    <input type="number" name="min_age" class="form-control input-sm" style="width:100px;" value="<?=gs('min_age','14')?>" min="1" max="100">
  </div>
  <div class="form-group">
    <label>Site Name</label>
    <input type="text" name="site_name" class="form-control input-sm" value="<?=e(gs('site_name','BirdChirp'))?>">
  </div>
  <div class="form-group">
    <label>Description</label>
    <textarea name="site_description" class="form-control input-sm" rows="3"><?=e(gs('site_description',''))?></textarea>
  </div>
  <button type="submit" name="save_settings" class="btn btn-primary btn-sm">Save Settings</button>
</form>
</div></div></div>

<div class="col-md-5">
<div class="panel panel-default">
<div class="panel-heading">Current Status</div>
<div class="panel-body" style="padding:0;">
<table class="table">
<tr><td style="font-weight:700;">Signups</td><td><?=gs('allow_signup','1')==='1'?'<span class="label label-success">Enabled</span>':'<span class="label label-danger">Disabled</span>'?></td></tr>
<tr><td style="font-weight:700;">Birthdate</td><td><?=gs('require_birthdate','1')==='1'?'<span class="label label-primary">Required</span>':'<span class="label label-default">Optional</span>'?></td></tr>
<tr><td style="font-weight:700;">Verification</td><td><?=gs('require_verification','0')==='1'?'<span class="label label-info">Required</span>':'<span class="label label-default">Optional</span>'?></td></tr>
<tr><td style="font-weight:700;">Maintenance</td><td><?=gs('maintenance_mode','0')==='1'?'<span class="label label-danger">Active</span>':'<span class="label label-success">Off</span>'?></td></tr>
<tr><td style="font-weight:700;">Min Age</td><td><?=gs('min_age','14')?> years</td></tr>
<tr><td style="font-weight:700;">Site Name</td><td><?=e(gs('site_name','BirdChirp'))?></td></tr>
</table>
</div>
<div class="panel-footer text-muted">Maintenance: only admins can access the site.</div>
</div></div></div>

<?php require_once "footer.php"; ?>
