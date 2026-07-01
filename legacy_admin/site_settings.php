<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1) {
    header("Location: /");
    exit;
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $settings[$row[0]] = $row[1];
    }
} catch (PDOException $e) {}

function getSetting($key, $default = '0') {
    global $settings;
    return $settings[$key] ?? $default;
}

function saveSetting($key, $value) {
    global $pdo, $settings;
    try {
        $check = $pdo->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
        $check->execute([$key]);
        if ($check->fetch()) {
            $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $key]);
        } else {
            $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $value]);
        }
        $settings[$key] = $value;
    } catch (PDOException $e) {}
}

$settingsCsrf = generateCSRF();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setMessage("error", "CSRF validation failed.");
        header("Location: site_settings.php");
        exit;
    }
    $allow_signup = isset($_POST['allow_signup']) ? '1' : '0';
    $require_birthdate = isset($_POST['require_birthdate']) ? '1' : '0';
    $min_age = max(1, min(100, (int)($_POST['min_age'] ?? 14)));
    
    saveSetting('allow_signup', $allow_signup);
    saveSetting('require_birthdate', $require_birthdate);
    saveSetting('min_age', (string)$min_age);
    
    setMessage("success", "Settings saved!");
    header("Location: site_settings.php");
    exit;
}

require_once "header.php";
?>

<br><br>
<b>SITE SETTINGS</b>
<br><br>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= $settingsCsrf ?>">
<table border="1" cellpadding="5" cellspacing="0">
<tr>
<td>Allow Signups:</td>
<td><input type="checkbox" name="allow_signup" value="1" <?= getSetting('allow_signup', '1') === '1' ? 'checked' : '' ?>></td>
</tr>
<tr>
<td>Require Birthdate:</td>
<td><input type="checkbox" name="require_birthdate" value="1" <?= getSetting('require_birthdate', '1') === '1' ? 'checked' : '' ?>></td>
</tr>
<tr>
<td>Min Age:</td>
<td><input type="number" name="min_age" value="<?= getSetting('min_age', '14') ?>" min="1" max="100" size="5"></td>
</tr>
</table>
<br>
<button type="submit" name="save_settings" class="btn primary">Save Settings</button>
</form>

<br><br>
<b>Current Status:</b>
<br>
Signups: <?= getSetting('allow_signup', '1') === '1' ? 'ON' : 'OFF' ?>
<br>
Min Age: <?= getSetting('min_age', '14') ?>

<?php
require_once "footer.php";
ob_end_flush();
?>
