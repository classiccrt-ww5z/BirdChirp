<?php

require_once 'database/config.php';
require_once 'functions/messages.php';
require_once 'backend/misc/webhook.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, birthdate FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if ($user && !empty($user['birthdate'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$minAge = 14;
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'min_age'");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row) {
        $minAge = (int)$row[0];
    }
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_submit'])) {
    $birth_year = isset($_POST['birth_year']) ? (int)$_POST['birth_year'] : 0;
    $birth_month = isset($_POST['birth_month']) ? (int)$_POST['birth_month'] : 0;
    $birth_day = isset($_POST['birth_day']) ? (int)$_POST['birth_day'] : 0;
    
    if ($birth_year < 1900 || $birth_month < 1 || $birth_month > 12 || $birth_day < 1 || $birth_day > 31) {
        $error = "Please enter a valid birthdate.";
    } else {
        $birthdate = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
        $birthdate_ts = strtotime($birthdate);
        $age = (int)((time() - $birthdate_ts) / (365.25 * 24 * 60 * 60));
        
        if ($age < $minAge) {
            $pdo->prepare("INSERT INTO bans (user_id, reason) VALUES (?, ?)")->execute([$uid, "User has been banned for being underage (age: $age, minimum: $minAge)"]);
            
            if (!empty($discord_webhook_url)) {
                $msg = [
                    "embeds" => [[
                        "title" => "User Banned - Underage",
                        "color" => 15158332,
                        "fields" => [
                            ["name" => "Username", "value" => $user['username'], "inline" => true],
                            ["name" => "Age", "value" => "$age years old", "inline" => true],
                            ["name" => "Minimum Required", "value" => "$minAge years", "inline" => true],
                            ["name" => "Reason", "value" => "User has been banned for being underage", "inline" => false]
                        ],
                        "footer" => ["text" => "$SITE_NAME Auto-Ban"]
                    ]]
                ];
                $dw = curl_init($discord_webhook_url);
                curl_setopt($dw, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($dw, CURLOPT_POST, 1);
                curl_setopt($dw, CURLOPT_POSTFIELDS, json_encode($msg));
                curl_setopt($dw, CURLOPT_RETURNTRANSFER, true);
                curl_exec($dw);
                curl_close($dw);
            }
            
            session_destroy();
            header("Location: index.php");
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE users SET birthdate = ? WHERE id = ?");
        $stmt->execute([$birthdate, $uid]);
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Set Birthdate - <?php echo $SITE_NAME; ?></title>
</head>
<body>
    <div style="text-align: center; margin-top: 50px;">
        <img src="/images/logos/birdchirpold.png" alt="<?php echo $SITE_NAME; ?>"><br><br>
        
        <h2>Set Your Birthdate</h2>
        <p>You must be at least <?= $minAge ?> years old to use <?= $SITE_NAME ?>.</p>
        
        <?php if ($error): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <form method="POST" style="display: inline-block; text-align: left;">
            <p>
                Year: 
                <select name="birth_year">
                    <option value="">Select Year</option>
                    <?php for($y = date('Y'); $y >= 1900; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p>
                Month: 
                <select name="birth_month">
                    <option value="">Select Month</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </p>
            <p>
                Day: 
                <select name="birth_day">
                    <option value="">Select Day</option>
                    <?php for($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p style="text-align: center;">
                <input type="submit" value="Continue">
            </p>
        </form>
    </div>
</body>
</html>