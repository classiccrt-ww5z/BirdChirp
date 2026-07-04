<?php

require_once '../../database/config.php';
require_once '../../functions/messages.php';
require_once '../../functions/users.php';
require_once "../../functions/rate_limit.php";

require_once '../misc/webhook.php';

$allowSignup = '1';
try {
    $allowSignup = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'allow_signup'")->fetchColumn() ?: '1';
} catch (PDOException $e) {}
if ($allowSignup === '0') {
    setMessage("error", "New registrations are currently disabled.");
    header("Location: ../../signup.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../signup.php");
    exit;
}
$userIp = get_user_ip();
if (strpos($userIp, ',') !== false) {
    $userIp = explode(',', $userIp)[0];
}
$is_localhost = in_array($userIp, ['127.0.0.1', '::1', 'localhost']) 
                || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
                || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;

if (!$is_localhost) {
    $json = @file_get_contents("http://ip-api.com/json/{$userIp}?fields=status,proxy,hosting");
    if ($json) {
        $details = json_decode($json, true);
        if ($details && $details['status'] === 'success') {
            if (!empty($details['proxy']) || !empty($details['hosting'])) {
                setMessage("error", "VPNs are not allowed on BirdChirp!");
                header("Location: ../../index.php");
                exit;
            }
        }
    }
}
$retry_after = checkRateLimit('signup', 60);
if ($retry_after > 0) {
    setMessage("error", "Slow down! Please wait $retry_after seconds.");
    header("Location: ../../signup.php");
    exit;
}
$secret = getenv('TURNSTILE_SECRET') ?: '';
$token = $_POST['cf-turnstile-response'] ?? '';

if (!empty($secret)) {
    if (!$token) {
        setMessage("error", "Captcha missing.");
        header("Location: ../../signup.php");
        exit;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $userIp]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($result);

    if (!$responseData || !$responseData->success) {
        setMessage("error", "Please complete the CAPTCHA correctly.");
        header("Location: ../../signup.php");
        exit;
    }
}
$username = strip_tags(trim($_POST['username'] ?? ''));
$email    = strip_tags(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$birth_year = isset($_POST['birth_year']) ? (int)$_POST['birth_year'] : 0;
$birth_month = isset($_POST['birth_month']) ? (int)$_POST['birth_month'] : 0;
$birth_day = isset($_POST['birth_day']) ? (int)$_POST['birth_day'] : 0;

if ($birth_year < 1900 || $birth_month < 1 || $birth_month > 12 || $birth_day < 1 || $birth_day > 31) {
    setMessage("error", "Please enter a valid birthdate.");
    header("Location: ../../signup.php");
    exit;
}

$birthdate = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
$birthdate_ts = strtotime($birthdate);
$age = (int)((time() - $birthdate_ts) / (365.25 * 24 * 60 * 60));

$minAge = 14;
try {
    $minAge = (int)($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'min_age'")->fetchColumn() ?: '14');
} catch (PDOException $e) {}

if ($age < $minAge) {
    if (!empty($discord_webhook_url)) {
        $msg = [
            "embeds" => [[
                "title" => "Signup Blocked - Underage",
                "color" => 15158332,
                "fields" => [
                    ["name" => "Username", "value" => $username, "inline" => true],
                    ["name" => "Age", "value" => "$age years old", "inline" => true],
                    ["name" => "Minimum Required", "value" => "$minAge years", "inline" => true],
                    ["name" => "Reason", "value" => "User has been banned for being underage", "inline" => false]
                ],
                "footer" => ["text" => "BirdChirp Auto-Ban"]
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
    
    setMessage("error", "You must be at least $minAge years old to create an account.");
    header("Location: ../../signup.php");
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    setMessage("error", "Username must be 3-20 characters and only contain letters, numbers, and underscores.");
    header("Location: ../../signup.php");
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage("error", "Invalid email address.");
    header("Location: ../../signup.php");
    exit;
}
if (strlen($password) < 8) {
    setMessage("error", "Password must be at least 8 characters.");
    header("Location: ../../signup.php");
    exit;
}
if (!preg_match('/[A-Z]/', $password)) {
    setMessage("error", "Password must contain at least one uppercase letter.");
    header("Location: ../../signup.php");
    exit;
}
if (!preg_match('/[a-z]/', $password)) {
    setMessage("error", "Password must contain at least one lowercase letter.");
    header("Location: ../../signup.php");
    exit;
}
if (!preg_match('/[0-9]/', $password)) {
    setMessage("error", "Password must contain at least one number.");
    header("Location: ../../signup.php");
    exit;
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$apiKey = getenv('MAILTRAP_API_KEY') ?: '';
$autoVerify = empty($apiKey);
$verification_token = $autoVerify ? null : bin2hex(random_bytes(32));

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, registration_ip, verification_token, is_verified, birthdate, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$username, $email, $hash, $userIp, $verification_token, $autoVerify ? 1 : 0, $birthdate]);

    if (!$autoVerify) {
        $host = $_SERVER['HTTP_HOST'] ?? 'birdchirp.org';
        $verificationLink = "http://$host/verify.php?token=" . $verification_token;
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <link href='https://birdchirp.org/css/bootstrap.css' rel='stylesheet'>
        </head>
        <body style='margin: 0; padding: 0;'>
            <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f5f5f5; font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; padding: 40px 0;'>
                <tr>
                    <td align='center'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 6px;'>
                            <tr>
                                <td style='padding: 40px; text-align: center;'>
                                    <img src='http://$host/images/logos/birdchirpold.png' alt='BirdChirp' width='150' style='margin-bottom: 20px;'>
                                    <h1 style='color: #333333; font-size: 38px; font-weight: bold; line-height: 1; margin: 0 0 20px 0; letter-spacing: -1px;'>Welcome to BirdChirp</h1>
                                    <p style='color: #777777; font-size: 18px; line-height: 24px; margin: 0 0 30px 0;'>Please verify your account using the button below:</p>
                                    <table border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                                        <tr>
                                            <td align='center' bgcolor='#0088cc' style='border-radius: 4px;'>
                                                <a href='$verificationLink' style='padding: 14px 24px; font-size: 18px; color: #ffffff; text-decoration: none; font-weight: bold; display: inline-block;'>Verify Account</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        <p style='color: #999999; font-size: 12px; margin-top: 20px;'>&copy; 2026 BirdChirp</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
        $url = 'https://send.api.mailtrap.io/api/send';
        $data = [
            'from' => ['email' => 'verify@birdchirp.org', 'name' => 'BirdChirp'],
            'to' => [['email' => $email]],
            'subject' => 'Verify Your Email',
            'html' => $htmlContent, 
            'category' => 'Registration'
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_exec($ch);
        curl_close($ch);
    }
    if (!empty($discord_webhook_url)) {
        $msg = [
            "content" => "**new account created!!**",
            "embeds" => [[
                "title" => "User Details",
                "color" => 3447003,
                "fields" => [
                    ["name" => "User", "value" => $username, "inline" => true],
                    ["name" => "Email", "value" => $email, "inline" => true],
                    ["name" => "IP", "value" => $userIp, "inline" => false]
                ],
                "footer" => ["text" => "meow :3"]
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
    if ($autoVerify) {
        setMessage("success", "Account created! You can log in right away.");
    } else {
        setMessage("success", "Check your email to verify your account! (always check yo spam folder)");
    }
    header("Location: ../../login.php");
    exit;

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        setMessage("error", "Username or Email already taken.");
    } else {
        setMessage("error", "Database Error.");
    }
    header("Location: ../../signup.php");
    exit;
}