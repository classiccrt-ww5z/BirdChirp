<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

$step = $_GET['step'] ?? 'license';

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$eol = $isWindows ? "\r\n" : "\n";

$envPath = __DIR__ . '/.env';

function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$directories = [
    'images/avatars/temp',
    'images/banners/temp',
    'images/posts',
    'videos/posts',
    'tmp',
];

$defaults = [
    'DB_HOST' => $isWindows ? 'localhost' : 'mysql-db',
    'DB_NAME' => 'birdchirp',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'TURNSTILE_SECRET' => '',
    'MAILTRAP_API_KEY' => '',
    'DISCORD_WEBHOOK_URL' => '',
];

$error = '';
$success = '';
$envAlreadyExists = file_exists($envPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'setup') {
    if ($envAlreadyExists && !isset($_POST['overwrite_env'])) {
        $error = '.env already exists. Check the box to overwrite.';
    } else {
        $data = [
            'DB_HOST'          => trim($_POST['DB_HOST'] ?? ''),
            'DB_NAME'          => trim($_POST['DB_NAME'] ?? ''),
            'DB_USER'          => trim($_POST['DB_USER'] ?? ''),
            'DB_PASS'          => $_POST['DB_PASS'] ?? '',
            'TURNSTILE_SECRET' => trim($_POST['TURNSTILE_SECRET'] ?? ''),
            'MAILTRAP_API_KEY' => trim($_POST['MAILTRAP_API_KEY'] ?? ''),
            'DISCORD_WEBHOOK_URL' => trim($_POST['DISCORD_WEBHOOK_URL'] ?? ''),
        ];

        if (!$data['DB_HOST'] || !$data['DB_NAME'] || !$data['DB_USER']) {
            $error = 'Host, database, and username are required.';
        } elseif (!is_writable(__DIR__)) {
            header("Location: ?step=failed&reason=perms&dir=" . urlencode(__DIR__));
            exit;
        } else {
            $envContent = implode($eol, [
                '# Database',
                'DB_HOST=' . $data['DB_HOST'],
                'DB_NAME=' . $data['DB_NAME'],
                'DB_USER=' . $data['DB_USER'],
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
            @file_put_contents($envPath, $envContent);
            if (!file_exists($envPath)) {
                header("Location: ?step=failed&reason=perms&dir=" . urlencode(__DIR__));
                exit;
            }
            foreach ($directories as $d) {
                $p = __DIR__ . '/' . $d;
                if (!is_dir($p)) {
                    @mkdir($p, 0755, true);
                }
            }
            $_SESSION['setup_env'] = $data;
            header("Location: ?step=import");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'import') {
    $env = parseEnv($envPath);
    if (!$env) $env = $_SESSION['setup_env'] ?? [];

    $dbHost = $env['DB_HOST'] ?? '';
    $dbName = $env['DB_NAME'] ?? '';
    $dbUser = $env['DB_USER'] ?? '';
    $dbPass = $env['DB_PASS'] ?? '';

    if (!$dbHost || !$dbName || !$dbUser) {
        $error = 'Database credentials missing. Go back to step 2.';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                $dbUser,
                $dbPass !== '' ? $dbPass : null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $sql = @file_get_contents(__DIR__ . '/database/schema.sql');
            if ($sql === false) {
                $error = 'Could not read database/schema.sql';
            } else {
                $pdo->exec($sql);
                $settings = [
                    ['allow_signup',        $_POST['allow_signup'] ?? '1'],
                    ['require_birthdate',   $_POST['require_birthdate'] ?? '1'],
                    ['min_age',             (string)max(1, min(100, (int)($_POST['min_age'] ?? 13)))],
                    ['site_name',           trim($_POST['site_name'] ?? 'BirdChirp')],
                    ['site_description',    trim($_POST['site_description'] ?? '')],
                    ['maintenance_mode',    $_POST['maintenance_mode'] ?? '0'],
                    ['require_verification', $_POST['require_verification'] ?? '0'],
                ];
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                foreach ($settings as $s) {
                    $stmt->execute($s);
                }
                $adminUser  = trim($_POST['admin_user'] ?? '');
                $adminPass  = $_POST['admin_pass'] ?? '';
                $adminEmail = trim($_POST['admin_email'] ?? '');
                if ($adminUser && $adminPass && $adminEmail) {
                    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT IGNORE INTO users (username, email, password, is_verified, admin, created_at) VALUES (?, ?, ?, 1, 1, NOW())")
                        ->execute([$adminUser, $adminEmail, $hash]);
                }
                $success = 'All done!';
                if ($adminUser) $success .= ' Admin account created.';
                $step = 'done';
            }
        } catch (PDOException $e) {
            header("Location: ?step=failed&reason=db&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

$deleteSelf = $_GET['delete'] ?? '';
if ($deleteSelf === '1') {
    @unlink(__FILE__);
    die('setup.php deleted');
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

$envExists = file_exists($envPath);
$envVars = $envExists ? parseEnv($envPath) : ($_SESSION['setup_env'] ?? $defaults);

$webUser = get_current_user();
$inDocker = file_exists('/.dockerenv');
$containerId = $inDocker ? gethostname() : '';
$failedReason = $_GET['reason'] ?? '';
$failedDir = $_GET['dir'] ?? '';
$failedMsg = $_GET['msg'] ?? '';

$stepNum = ['license' => 1, 'setup' => 2, 'import' => 3, 'done' => 4];
$current = $stepNum[$step] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BirdChirp Setup</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/css/bootstrap.css">
<style>
.container { max-width: 700px; }
.topbar { position: static; }
hr { margin: 18px 0; }
code { font-size: 13px; }
</style>
</head>
<body>

<div class="topbar">
<div class="fill">
<div class="container">
<h3><a href="/"><img src="/images/logos/birdchirpold.png" height="20" width="70" style="vertical-align:middle;"></a></h3>
<ul class="nav">
<li class="<?= $current == 1 ? 'active' : '' ?>"><a href="?step=license">License</a></li>
<li class="<?= $current == 2 ? 'active' : '' ?>"><a href="?step=setup">Setup</a></li>
<li class="<?= $current == 3 ? 'active' : '' ?>"><a href="?step=import">Database</a></li>
<li class="<?= $current == 4 ? 'active' : '' ?>"><a href="?step=done">Done</a></li>
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

<?php if ($step === 'failed'): ?>

<div class="page-header"><h2>Installation Failed</h2></div>

<?php if ($failedReason === 'perms'): ?>
<div class="alert-message error"><p>The web server doesn't have write permission to the project directory.</p></div>
<?php if ($inDocker): ?>
<p>You're in Docker. The volume mount permissions on your host are blocking writes. Fix the host directory permissions.</p>
<?php else: ?>
<p>The web server user needs write access to the project files.</p>
<?php endif; ?>
<p><a href="?step=setup" class="btn primary">Try again</a></p>

<?php elseif ($failedReason === 'db'): ?>
<div class="alert-message error"><p>Database error:</p></div>
<pre><?= e($failedMsg) ?></pre>
<p>Make sure MySQL is running and the credentials are correct. <a href="?step=import" class="btn primary">try again</a></p>

<?php else: ?>
<div class="alert-message error"><p>Something went wrong.</p></div>
<p><a href="?step=setup" class="btn primary">Start over</a></p>
<?php endif; ?>

<?php elseif ($step === 'license'): ?>

<div class="page-header"><h2>License</h2></div>
<p>This software uses the WTFPL license. By continuing you accept it.</p>
<pre style="max-height:300px;overflow-y:auto;font-size:11px;"><?= e(file_get_contents(__DIR__ . '/LICENSE')) ?></pre>
<div class="actions" style="margin-top:18px;">
<a href="?step=setup" class="btn primary">Continue</a>
</div>

<?php elseif ($step === 'setup'): ?>

<?php if ($envAlreadyExists): ?>
<div class="alert-message warning">
<p><strong>.env already exists.</strong> Submitting will overwrite it. <a href="?delete=1" class="btn small danger">Delete setup.php</a></p>
</div>
<?php else: ?>
<div class="alert-message warning">
<strong>Delete setup.php after install!</strong> <a href="?delete=1" style="margin-left:10px;" class="btn small danger">Delete now</a>
</div>
<?php endif; ?>

<div class="page-header"><h2>Database Connection</h2></div>
<p>.env and upload dirs will be created in <code><?= __DIR__ ?></code></p>

<form method="post" action="?step=setup" class="form-stacked">

<fieldset>
<legend>Database</legend>

<div class="clearfix">
<label for="DB_HOST">Host</label>
<div class="input">
<input type="text" id="DB_HOST" name="DB_HOST" value="<?= e($envVars['DB_HOST'] ?? '') ?>" class="span5">
<span class="help-block"><?= $isWindows ? 'Use <strong>localhost</strong> for XAMPP/WAMP' : 'Use <strong>mysql-db</strong> for Docker or <strong>localhost</strong> for native' ?></span>
</div>
</div>

<div class="clearfix">
<label for="DB_NAME">Database</label>
<div class="input">
<input type="text" id="DB_NAME" name="DB_NAME" value="<?= e($envVars['DB_NAME'] ?? '') ?>" class="span5">
</div>
</div>

<div class="clearfix">
<label for="DB_USER">Username</label>
<div class="input">
<input type="text" id="DB_USER" name="DB_USER" value="<?= e($envVars['DB_USER'] ?? '') ?>" class="span5">
</div>
</div>

<div class="clearfix">
<label for="DB_PASS">Password</label>
<div class="input">
<input type="password" id="DB_PASS" name="DB_PASS" class="span5">
<span class="help-block">Leave blank for no password</span>
</div>
</div>

</fieldset>

<fieldset>
<legend>Optional Services</legend>

<div class="clearfix">
<label for="TURNSTILE_SECRET">Turnstile Secret</label>
<div class="input">
<input type="text" id="TURNSTILE_SECRET" name="TURNSTILE_SECRET" value="<?= e($envVars['TURNSTILE_SECRET'] ?? '') ?>" class="span5">
<span class="help-block">Cloudflare Turnstile bot protection. Leave blank.</span>
</div>
</div>

<div class="clearfix">
<label for="MAILTRAP_API_KEY">Mailtrap Key</label>
<div class="input">
<input type="text" id="MAILTRAP_API_KEY" name="MAILTRAP_API_KEY" value="<?= e($envVars['MAILTRAP_API_KEY'] ?? '') ?>" class="span5">
<span class="help-block">Email verification. Leave blank.</span>
</div>
</div>

<div class="clearfix">
<label for="DISCORD_WEBHOOK_URL">Discord Webhook</label>
<div class="input">
<input type="text" id="DISCORD_WEBHOOK_URL" name="DISCORD_WEBHOOK_URL" value="<?= e($envVars['DISCORD_WEBHOOK_URL'] ?? '') ?>" class="span5">
<span class="help-block">Admin action logs. Leave blank.</span>
</div>
</div>

</fieldset>

<?php if ($envAlreadyExists): ?>
<div class="clearfix">
<div class="input">
<label class="checkbox">
<input type="checkbox" name="overwrite_env" value="1"> I want to overwrite the existing .env
</label>
</div>
</div>
<?php endif; ?>

<div class="actions">
<button type="submit" class="btn primary">Save and Continue</button>
</div>

</form>

<?php elseif ($step === 'import'): ?>

<div class="page-header"><h2>Create Database and Admin</h2></div>
<p>Database credentials are saved. Now create the database and admin account.</p>

<form method="post" action="?step=import" class="form-stacked">

<fieldset>
<legend>Admin Account</legend>

<div class="clearfix">
<label>Username</label>
<div class="input">
<input type="text" name="admin_user" class="span5">
</div>
</div>

<div class="clearfix">
<label>Email</label>
<div class="input">
<input type="email" name="admin_email" class="span5">
</div>
</div>

<div class="clearfix">
<label>Password</label>
<div class="input">
<input type="password" name="admin_pass" class="span5">
</div>
</div>

</fieldset>

<fieldset>
<legend>Site Settings</legend>

<div class="clearfix">
<div class="input">
<label class="checkbox">
<input type="checkbox" name="allow_signup" value="1" checked> Allow signups
</label>
</div>
</div>

<div class="clearfix">
<div class="input">
<label class="checkbox">
<input type="checkbox" name="require_birthdate" value="1" checked> Require birthdate
</label>
</div>
</div>

<div class="clearfix">
<div class="input">
<label class="checkbox">
<input type="checkbox" name="require_verification" value="1"> Require email verification
</label>
</div>
</div>

<div class="clearfix">
<div class="input">
<label class="checkbox">
<input type="checkbox" name="maintenance_mode" value="1"> Maintenance mode
</label>
</div>
</div>

<div class="clearfix">
<label for="min_age">Min age</label>
<div class="input">
<input type="number" id="min_age" name="min_age" value="13" min="1" max="100" class="span2">
</div>
</div>

<div class="clearfix">
<label for="site_name">Site name</label>
<div class="input">
<input type="text" id="site_name" name="site_name" value="BirdChirp" class="span5">
</div>
</div>

<div class="clearfix">
<label for="site_description">Description</label>
<div class="input">
<textarea id="site_description" name="site_description" class="span5" rows="3" placeholder="Short description"></textarea>
</div>
</div>

</fieldset>

<div class="actions">
<button type="submit" class="btn success large">Install</button>
</div>

</form>

<?php elseif ($step === 'done'): ?>

<div class="page-header"><h2>Installation Complete</h2></div>
<div class="alert-message success"><p>BirdChirp is ready.</p></div>
<ol>
<li><a href="?delete=1" class="btn small danger">Delete setup.php</a></li>
<li><a href="/" class="btn primary">Go to site</a></li>
</ol>

<?php endif; ?>

</div>

</body>
</html>
