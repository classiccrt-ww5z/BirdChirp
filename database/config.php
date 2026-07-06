<?php
if (!file_exists(__DIR__ . '/../.env') && basename($_SERVER['PHP_SELF']) !== 'setup.php') {
    header("Location: /setup.php");
    exit;
}

$MAINTENANCE_MODE = false; 
$SITE_NAME = "BirdChirp";
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

function get_user_ip() {
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

if ($MAINTENANCE_MODE) {
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Retry-After: 3600');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <head>
        <meta charset="UTF-8">
        <title>Maintenance - <?php echo $SITE_NAME; ?></title>
        <style>
            body { display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Inter', sans-serif; background: #f5f5f5; }
            .container { text-align: center; }
            img { max-width: 150px; margin-bottom: 20px; }
            h1 { color: #333; font-weight: 700; }
            p { color: #666; font-size: 16px; }
        </style>
    </head>
    <body>
        <div class="container">
            <img src="/images/logos/birdchirpold.png" alt="<?php echo $SITE_NAME; ?> Logo">
            <h1>Undergoing Maintenance!</h1>
            <p>we are be back soon!</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
require_once __DIR__ . '/../private/db.php';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS !== '' ? $DB_PASS : null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("<h1>Service Unavailable</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>");
}
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $SITE_NAME = $row[0];
    }
} catch (PDOException $e) {
    // Keep default
}
$userIp = get_user_ip();
if (strpos($userIp, ',') !== false) {
    $userIp = explode(',', $userIp)[0];
}
try {
    $banStmt = $pdo->prepare("SELECT id FROM ip_bans WHERE ip_address = ? LIMIT 1");
    $banStmt->execute([$userIp]);
    
    if ($banStmt->fetch()) {
        die("<h1>IP BANNED</h1><p>You have been IP banned from $SITE_NAME.</p>");
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("<h1>Connection Error</h1><p>Unable to verify user status.</p>");
}

$lifetime = 86400 * 30; // 30 days in seconds
ini_set('session.gc_maxlifetime', $lifetime);
ini_set('session.cookie_lifetime', $lifetime);
session_start();


?>