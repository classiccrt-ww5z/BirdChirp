<?php
$target = $_GET['url'] ?? '';
if (filter_var($target, FILTER_VALIDATE_URL) === FALSE) {
    die("Invalid request.");
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Leaving Our Website</title>
<link rel="stylesheet" href="/css/bootstrap.css">
<style>
body { background: #558B2F; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
.modal-box { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.3); max-width: 500px; width: 90%; }
.modal-box .hd { background: #669933; color: #fff; padding: 12px 20px; border-radius: 10px 10px 0 0; }
.modal-box .hd h3 { margin: 0; font-size: 16px; }
.modal-box .bd { padding: 20px; }
.modal-box .bd .url-box { background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px; padding: 12px; word-break: break-all; margin: 12px 0; font-weight: bold; }
.modal-box .ft { padding: 12px 20px; border-top: 1px solid #e5e5e5; text-align: right; }
.modal-box .ft .btn { margin-left: 8px; }
</style>
</head>
<body>
<div class="modal-box">
  <div class="hd"><h3>Leaving Our Website</h3></div>
  <div class="bd">
    <p>You are now leaving to visit an external site:</p>
    <div class="url-box"><?= htmlspecialchars($target) ?></div>
    <p style="font-size:13px;color:#666;">Always verify the URL before entering any personal information or passwords.</p>
  </div>
  <div class="ft">
    <button onclick="window.history.back()" class="btn">Go Back</button>
    <button data-url="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>" class="btn primary" onclick="window.location.href=this.dataset.url">Proceed to Website</button>
  </div>
</div>
</body>
</html>