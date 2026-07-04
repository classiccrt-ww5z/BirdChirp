<?php
ob_start();

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$admin = $_SESSION['admin'] ?? 0;
if ($admin != 1) {
    header("Location: ../login.php");
    exit;
}

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
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = "CSRF validation failed.";
    } else {
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT password FROM adminpassword WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $adminpass = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminpass && password_verify($password, $adminpass['password'])) {
        $_SESSION['admin_verified'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid admin password.";
    }
    }
}

require_once __DIR__ . '/../header.php';
?>

<div class="container" style="max-width:500px; margin-top:40px;">

<h2>Admin Panel</h2>

<?php if ($new_password): ?>
<div class="alert-message block-message warning" style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin-bottom:20px;">
<strong>Admin password: <code style="font-size:1.2em;"><?= e($new_password) ?></code></strong><br>
Save this — it won't be shown again.
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-message error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= $loginCsrf ?>">
<p>Enter your admin password:</p>
<input type="password" name="password" style="width:100%;padding:8px;font-size:16px;" required>
<br><br>
<button class="btn primary">Login to Admin</button>
</form>

</div>

<script src="https://code.jquery.com/jquery-1.9.1.min.js"></script>

<?php
ob_end_flush();
?>
