<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';

if (isset($_SESSION['admin_verified']) && $_SESSION['admin_verified']) {
    header("Location: index.php"); exit;
}
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1) { header("Location: /login.php"); exit; }

$user_id = $_SESSION['user_id'];
$error = "";

$stmt = $pdo->prepare("SELECT password FROM adminpassword WHERE user_id = ?");
$stmt->execute([$user_id]);
$adminpass = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminpass) {
    $plain_password = bin2hex(random_bytes(16));
    $hash = password_hash($plain_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO adminpassword (user_id, password) VALUES (?, ?)");
    $stmt->execute([$user_id, $hash]);
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
        } else { $error = "Invalid password."; }
    }
}
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Internal</title>
<style>
body{background:#fff;font-family:Verdana,sans-serif;font-size:13px;margin:0;padding:0}
form{padding:3px 4px}
input{border:2px inset #999;padding:2px 4px;font-size:13px;font-family:Verdana,sans-serif;width:140px}
button{border:2px outset #ccc;background:#eee;padding:2px 10px;font-size:13px;font-family:Verdana,sans-serif;cursor:pointer}
.er{color:#c00;font-size:12px;padding:3px 4px}
</style>
</head><body>
<form method="post">
<input type="hidden" name="csrf_token" value="<?=$loginCsrf?>">
<input type="password" name="password" placeholder="password" required autofocus>
<button type="submit">Login</button>
<?php if($error):?><div class="er"><?=e($error)?></div><?php endif;?>
</form>
</body></html>
<?php ob_end_flush(); ?>
