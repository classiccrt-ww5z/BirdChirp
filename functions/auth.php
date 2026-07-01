<?php
function isLoggedIn()
{
    if (!isset($_SESSION)) {
        
    }

    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    return checkRememberToken() !== null;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function currentUser()
{
    if (!isset($_SESSION)) {
        
    }

    return $_SESSION['user_id'] ?? null;
}

function checkRememberToken() {
    global $pdo;
    
    if (!isset($_SESSION)) {
        
    }
    
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    
    $cookie = $_COOKIE['remember_token'] ?? null;
    if (!$cookie) {
        return null;
    }
    
    $parts = explode('|', $cookie);
    if (count($parts) !== 2) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        return null;
    }
    
    $selector = $parts[0];
    $token = $parts[1];
    
    $stmt = $pdo->prepare("SELECT user_id, token_hash, expires FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$selector]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        return null;
    }
    
    if (strtotime($record['expires']) < time()) {
        $del = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $del->execute([$selector]);
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        return null;
    }
    
    if (!password_verify($token, $record['token_hash'])) {
        return null;
    }
    
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$record['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return null;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
    $_SESSION['admin'] = (int)($user['admin'] ?? 0);
    $_SESSION['needs_birthdate'] = empty($user['birthdate']);
    
    return $user['id'];
}

function setRememberToken($user_id, $days = 30) {
    global $pdo;
    
    $selector = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $token_hash = password_hash($token, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + (86400 * $days));
    
    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$user_id]);
    
    $pdo->prepare("INSERT INTO remember_tokens (selector, token_hash, user_id, expires) VALUES (?, ?, ?, ?)")
        ->execute([$selector, $token_hash, $user_id, $expires]);
    
    $cookie_value = $selector . '|' . $token;
    setcookie('remember_token', $cookie_value, time() + (86400 * $days), '/', '', true, true);
}

function clearRememberToken() {
    global $pdo;
    
    $cookie = $_COOKIE['remember_token'] ?? null;
    if ($cookie) {
        $parts = explode('|', $cookie);
        if (count($parts) === 2) {
            $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$parts[0]]);
        }
    }
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

function checkLoginRateLimit($max_attempts = 5, $lockout_seconds = 300) {
    global $pdo;
    
    $ip = get_user_ip();
    $now = time();
    
    $stmt = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        $time_since = $now - strtotime($record['last_attempt']);
        if ($time_since > $lockout_seconds) {
            $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
            return ['allowed' => true, 'attempts' => 0];
        }
        
        if ($record['attempts'] >= $max_attempts) {
            $remaining = $lockout_seconds - $time_since;
            return ['allowed' => false, 'remaining' => $remaining, 'attempts' => $record['attempts']];
        }
    }
    
    return ['allowed' => true, 'attempts' => $record['attempts'] ?? 0];
}

function recordFailedLogin() {
    global $pdo;
    
    $ip = get_user_ip();
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        $pdo->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = ? WHERE ip = ?")
            ->execute([$now, $ip]);
    } else {
        $pdo->prepare("INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, ?)")
            ->execute([$ip, $now]);
    }
}

function clearLoginAttempts() {
    global $pdo;
    
    $ip = get_user_ip();
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
}

