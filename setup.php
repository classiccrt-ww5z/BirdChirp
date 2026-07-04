<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

$step = $_GET['step'] ?? 'license';

function e($v) {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function parseEnv($path) {
    if (!file_exists($path)) return false;
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        if ((strpos($val, '"') === 0 && strrpos($val, '"') === strlen($val)-1) ||
            (strpos($val, "'") === 0 && strrpos($val, "'") === strlen($val)-1)) {
            $val = substr($val, 1, -1);
        }
        $vars[$key] = $val;
    }
    return $vars;
}

function buildEnv($data) {
    return implode("\n", [
        '# Database',
        'DB_HOST=' . ($data['DB_HOST'] ?: 'mysql-db'),
        'DB_NAME=' . ($data['DB_NAME'] ?: 'birdchirp'),
        'DB_USER=' . ($data['DB_USER'] ?: 'root'),
        'DB_PASS=' . $data['DB_PASS'],
        '',
        '# Optional: Cloudflare Turnstile (signup bot protection)',
        'TURNSTILE_SECRET=' . $data['TURNSTILE_SECRET'],
        '',
        '# Optional: Mailtrap email verification',
        'MAILTRAP_API_KEY=' . $data['MAILTRAP_API_KEY'],
        '',
        '# Optional: Discord webhook for admin action logs',
        'DISCORD_WEBHOOK_URL=' . $data['DISCORD_WEBHOOK_URL'],
        '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'write') {
    $data = [
        'DB_HOST'          => trim($_POST['DB_HOST'] ?? ''),
        'DB_NAME'          => trim($_POST['DB_NAME'] ?? ''),
        'DB_USER'          => trim($_POST['DB_USER'] ?? ''),
        'DB_PASS'          => $_POST['DB_PASS'] ?? '',
        'TURNSTILE_SECRET' => trim($_POST['TURNSTILE_SECRET'] ?? ''),
        'MAILTRAP_API_KEY' => trim($_POST['MAILTRAP_API_KEY'] ?? ''),
        'DISCORD_WEBHOOK_URL' => trim($_POST['DISCORD_WEBHOOK_URL'] ?? ''),
    ];
    if (empty($_POST['confirms'])) {
        $error = 'You must confirm';
    } else {
        $_SESSION['setup_env'] = $data;
        $_SESSION['env_contents'] = buildEnv($data);
        header("Location: ?step=show_import");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'import_schema') {
    $env = parseEnv(__DIR__ . '/.env');
    if (!$env) $env = $_SESSION['setup_env'] ?? [];
    $dbHost = $_POST['DB_HOST'] ?? $env['DB_HOST'] ?? '';
    $dbName = $_POST['DB_NAME'] ?? $env['DB_NAME'] ?? '';
    $dbUser = $_POST['DB_USER'] ?? $env['DB_USER'] ?? '';
    $dbPass = $_POST['DB_PASS'] ?? $env['DB_PASS'] ?? '';
    $secret = trim($_POST['secret_word'] ?? '');
    $expected = $_SESSION['secret_word'] ?? '';
    if (strtolower($secret) !== strtolower($expected)) {
        $error = 'Verification word does not match';
    } elseif (!$dbHost || !$dbName || !$dbUser) {
        $error = 'Database credentials are required';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                $dbUser,
                $dbPass !== '' ? $dbPass : null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $dbname = $dbName;
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");

            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            if ($sql === false) {
                $error = 'Could not read database/schema.sql';
            } else {
                $pdo->exec($sql);

                $adminUser = trim($_POST['admin_user'] ?? '');
                $adminPass = $_POST['admin_pass'] ?? '';
                $adminEmail = trim($_POST['admin_email'] ?? '');
                if ($adminUser && $adminPass && $adminEmail) {
                    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, is_verified, admin, created_at) VALUES (?, ?, ?, 1, 1, NOW())");
                    $stmt->execute([$adminUser, $adminEmail, $hash]);
                }

                $signups = $_POST['allow_signup'] ?? '1';
                $birthdate = $_POST['require_birthdate'] ?? '1';
                $minAge = max(1, min(100, (int)($_POST['min_age'] ?? 14)));
                $pdo->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('allow_signup', '$signups') ON DUPLICATE KEY UPDATE setting_value='$signups'");
                $pdo->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('require_birthdate', '$birthdate') ON DUPLICATE KEY UPDATE setting_value='$birthdate'");
                $pdo->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('min_age', '$minAge') ON DUPLICATE KEY UPDATE setting_value='$minAge'");

                $success = 'Database created!';
                if ($adminUser) $success .= ' Admin account created';
                $step = 'done';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$envExists = file_exists(__DIR__ . '/.env');
$envVars = $envExists ? parseEnv(__DIR__ . '/.env') : ($_SESSION['setup_env'] ?? [
    'DB_HOST' => 'mysql-db',
    'DB_NAME' => 'birdchirp',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'TURNSTILE_SECRET' => '',
    'MAILTRAP_API_KEY' => '',
    'DISCORD_WEBHOOK_URL' => '',
]);

$deleteSelf = $_GET['delete'] ?? '';
if ($deleteSelf === '1') {
    unlink(__FILE__);
    die('setup.php has been deleted');
}

$licWords = ['PUBLIC', 'LICENSE', 'FUCK', 'WANT', 'DISTRIBUTION', 'COPYING', 'PERMITTED', 'CHANGING'];
$_SESSION['secret_word'] = $licWords[mt_rand(0, count($licWords)-1)];

$evil = mt_rand(0, 2);

$allSteps = ['license' => 1, 'start' => 2, 'write' => 2, 'show_import' => 2, 'import_schema' => 3, 'done' => 4];
$currentNum = $allSteps[$step] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BirdChirp - Setup</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/css/bootstrap.css">
</head>
<body>

<div class="topbar">
<div class="fill">
<div class="container">
<h3><a href="/"><img src="/images/logos/birdchirpold.png" height="20" width="70" style="vertical-align:middle;"></a></h3>
<ul class="nav">
<li class="<?= $currentNum == 1 ? 'active' : '' ?>"><a href="?step=license">1. License</a></li>
<li class="<?= $currentNum == 2 ? 'active' : '' ?>"><a href="?step=start">2. Setup</a></li>
<li class="<?= $currentNum == 3 ? 'active' : '' ?>"><a href="?step=import_schema">3. Database</a></li>
<li class="<?= $currentNum == 4 ? 'active' : '' ?>"><a href="?step=done">4. Done</a></li>
</ul>
</div>
</div>
</div>

<div class="container" style="margin-top:20px;">

<?php if ($error): ?>
<div class="alert-message error"><p><?= e($error) ?></p></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-message success"><p><?= e($success) ?></p></div>
<?php endif; ?>

<?php if ($step === 'license'): ?>

<div class="page-header">
<h2>License</h2>
</div>

<p>This software uses the WTFPL license. By continuing you accept it.</p>

<pre style="max-height:300px;overflow-y:auto;font-size:11px;"><?= e(file_get_contents(__DIR__ . '/LICENSE')) ?></pre>

<div class="actions" style="margin-top:18px;">
<a href="?step=start" class="btn primary">Continue</a>
</div>

<?php elseif ($step === 'start' || $step === 'show_import'): ?>

<div class="alert-message warning">
<strong>Delete this file after setup!</strong>
</div>

<div class="page-header">
<h2>Setup</h2>
</div>

<?php if (!empty($_SESSION['env_contents'])): ?>
<div class="alert-message info">
<p><strong>.env file</strong> — copy into <code>.env</code> at the project root:</p>
</div>
<pre><?= e($_SESSION['env_contents']) ?></pre>
<?php endif; ?>

<?php
$fields = [
    ['key' => 'DB_HOST', 'label' => 'Host'],
    ['key' => 'DB_NAME', 'label' => 'Database'],
    ['key' => 'DB_USER', 'label' => 'Username'],
    ['key' => 'DB_PASS', 'label' => 'Password'],
];
if ($evil === 0) shuffle($fields);
if ($evil === 1) $fields = array_reverse($fields);
?>

<form method="post" action="<?= $step === 'show_import' ? '?step=import_schema' : '?step=write' ?>" class="form-stacked">

<fieldset>
<legend>Database</legend>

<?php foreach ($fields as $f): $key = $f['key']; ?>
<div class="clearfix">
<label for="<?= $key ?>"><?= $f['label'] ?></label>
<div class="input">
<input type="<?= $key === 'DB_PASS' ? 'password' : 'text' ?>" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($envVars[$key] ?? '') ?>" class="span5">
</div>
</div>
<?php endforeach; ?>

</fieldset>

<?php
$optServices = [
    ['key' => 'TURNSTILE_SECRET', 'label' => 'Turnstile Secret', 'help' => 'Bot protection. Leave blank to skip'],
    ['key' => 'MAILTRAP_API_KEY', 'label' => 'Mailtrap Key', 'help' => 'Email verification. Leave blank to skip'],
    ['key' => 'DISCORD_WEBHOOK_URL', 'label' => 'Discord Webhook', 'help' => 'Admin logs. Leave blank to skip'],
];
if ($evil === 0) shuffle($optServices);
if ($evil === 2) $optServices = array_reverse($optServices);
?>

<?php if ($step !== 'show_import'): ?>
<fieldset>
<legend>Optional</legend>

<?php foreach ($optServices as $s): ?>
<div class="clearfix">
<label for="<?= $s['key'] ?>"><?= $s['label'] ?></label>
<div class="input">
<input type="text" id="<?= $s['key'] ?>" name="<?= $s['key'] ?>" value="<?= e($envVars[$s['key']] ?? '') ?>" class="span5">
<span class="help-block"><?= $s['help'] ?></span>
</div>
</div>
<?php endforeach; ?>
</fieldset>

<div class="clearfix">
<label>Confirm</label>
<div class="input">
<select name="confirms" class="span3">
<option value="">Choose</option>
<option value="1">I confirm</option>
</select>
</div>
</div>

<div class="actions">
<button type="submit" class="btn primary">Continue</button>
</div>
<?php endif; ?>

<?php if ($step === 'show_import'): ?>

<fieldset>
<legend>Admin</legend>

<div class="clearfix">
<label>Username</label>
<div class="input">
<input type="text" name="admin_user" class="span5" required>
</div>
</div>

<div class="clearfix">
<label>Email</label>
<div class="input">
<input type="email" name="admin_email" class="span5" required>
</div>
</div>

<div class="clearfix">
<label>Password</label>
<div class="input">
<input type="password" name="admin_pass" class="span5" required>
</div>
</div>
</fieldset>

<fieldset>
<legend>Settings</legend>

<div class="clearfix">
<label>Allow signups</label>
<div class="input">
<select name="allow_signup" class="span3">
<option value="1">Yes</option>
<option value="0">No</option>
</select>
</div>
</div>

<div class="clearfix">
<label>Require birthdate</label>
<div class="input">
<select name="require_birthdate" class="span3">
<option value="1">Yes</option>
<option value="0">No</option>
</select>
</div>
</div>

<div class="clearfix">
<label>Min age</label>
<div class="input">
<input type="number" name="min_age" value="14" min="1" max="100" class="span2">
</div>
</div>
</fieldset>

<div class="clearfix">
<label>Type this word: <strong><?= e($_SESSION['secret_word']) ?></strong></label>
<div class="input">
<input type="text" name="secret_word" class="span4" required>
</div>
</div>

<div class="actions">
<button type="submit" class="btn success">Import</button>
</div>
<?php endif; ?>

</form>

<?php elseif ($step === 'done'): ?>

<div class="page-header">
<h2>Done</h2>
</div>
<p>Database is ready.</p>
<ol>
<li><a href="?delete=1">Delete setup.php</a></li>
<li><a href="/">Go to site</a></li>
</ol>

<?php endif; ?>

</div>

</body>
</html>
