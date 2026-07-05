<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';

if (!isLoggedIn()) { header("Location: /login.php"); exit; }
if (($_SESSION['admin'] ?? 0) != 1) { header("Location: /login.php"); exit; }

$user_id = $_SESSION['user_id'];
$error = "";
$new_password = null;

$stmt = $pdo->prepare("SELECT password FROM adminpassword WHERE user_id = ?");
$stmt->execute([$user_id]);
$adminpass = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminpass) {
    $plain_password = bin2hex(random_bytes(16));
    $hash = password_hash($plain_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO adminpassword (user_id, password) VALUES (?, ?)");
    $stmt->execute([$user_id, $hash]);
    $new_password = $plain_password;
    error_log("Admin password for user $user_id: $plain_password");
}

$loginCsrf = generateCSRF();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $error = "CSRF validation failed."; }
    else {
        $password = $_POST['password'];
        $stmt = $pdo->prepare("SELECT password FROM adminpassword WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $adminpass = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($adminpass && password_verify($password, $adminpass['password'])) {
            $_SESSION['admin_verified'] = true;
            header("Location: index.php"); exit;
        } else { $error = "Invalid admin password."; }
    }
}

$stmt = $pdo->prepare("SELECT username, avatar, sysadmin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$adminUser = $stmt->fetch();
$adminUsername = $adminUser ? $adminUser['username'] : '';
$isSysadmin = $adminUser ? (int)($adminUser['sysadmin'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
<link rel="stylesheet" href="admin.css">
<style>
body{background:#d4d0c8}
.lb{width:400px;margin:60px auto 0;border:3px groove #666;background:#fff;padding:0}
.lb h2{margin:0;padding:8px 14px;background:#246;color:#fff;font-size:14px;border-bottom:3px groove #666}
.lb-inner{padding:18px 20px}
.lb label{font-size:12px;font-weight:700;display:block;margin-bottom:4px;color:#333}
.lb input[type=password]{width:100%;padding:6px 8px;border:2px inset #999;font-size:14px;font-family:Verdana,sans-serif;margin-bottom:14px}
.btn{display:block;width:100%;padding:7px;border:2px outset #888;background:#e8e8e8;color:#000;font-size:13px;font-family:Verdana,sans-serif;cursor:pointer;text-align:center}
.btn:hover{background:#ddd}
.btn:active{border-style:inset}
code{background:#f8f8f8;padding:4px 8px;border:1px solid #ccc;font-size:15px;display:inline-block;margin:4px 0}
.alert{padding:7px 10px;border:2px groove #999;margin-bottom:12px;font-size:12px}
.alert-info{background:#eef;color:#246}
.alert-err{background:#fee;color:#800}
.help{font-size:11px;color:#666;margin-bottom:12px}
</style>
</head>
<body>

<nav class="navbar navbar-inverse" style="margin-bottom:0;border-radius:0;min-height:40px">
  <div class="container-fluid">
    <div class="navbar-header">
      <a class="navbar-brand" href="index.php" style="padding:10px 15px;font-size:15px;height:40px">BirdChirp Admin</a>
    </div>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="/"><span class="glyphicon glyphicon-home"></span> View Site</a></li>
    </ul>
  </div>
</nav>

<div class="lb">
  <h2>Admin Login</h2>
  <div class="lb-inner">
    <p style="margin:0 0 14px;font-size:12px;color:#555">Enter your admin password to continue.</p>

    <?php if ($new_password): ?>
    <div class="alert alert-info">
      <b>New password generated.</b><br>
      <code><?= e($new_password) ?></code><br>
      <span class="help" style="margin-bottom:0">Save this - not shown again.</span>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-err"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $loginCsrf ?>">
      <label for="pw">Password</label>
      <input type="password" name="password" id="pw" placeholder="Enter admin password..." required autofocus>
      <button type="submit" class="btn">Access Panel</button>
    </form>

    <div style="margin-top:12px;font-size:11px;color:#888;border-top:1px solid #ddd;padding-top:10px">
      Logged in as <b><?=e($adminUsername)?></b>
    </div>
  </div>
</div>

</body>
</html>
<?php ob_end_flush(); ?>
