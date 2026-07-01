<?php

require_once '../../database/config.php';
require_once '../../functions/messages.php';
require_once '../../functions/users.php';
require_once '../../functions/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    
    $rate_limit = checkLoginRateLimit();
    if (!$rate_limit['allowed']) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Too many attempts. Try again in ' . $rate_limit['remaining'] . ' seconds.', 'redirect' => '']);
            exit;
        }
        setMessage("error", "Too many login attempts. Try again in " . $rate_limit['remaining'] . " seconds.");
        header("Location: ../../login.php");
        exit;
    }
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        
        if (!$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields', 'redirect' => '']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            recordFailedLogin();
            echo json_encode(['success' => false, 'message' => 'Invalid login credentials', 'redirect' => '']);
            exit;
        }
        
        if (empty($user['birthdate'])) {
            clearLoginAttempts();
            $login_ip = get_user_ip();
            $update_stmt = $pdo->prepare("UPDATE users SET last_login_ip = ? WHERE id = ?");
            $update_stmt->execute([$login_ip, $user['id']]);
            session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
            $_SESSION['admin'] = (int)($user['admin'] ?? 0);
            echo json_encode(['success' => true, 'message' => 'Welcome back', 'redirect' => '../set_birthdate.php']);
            exit;
        }
        
        clearLoginAttempts();
        
        $login_ip = get_user_ip();
        $update_stmt = $pdo->prepare("UPDATE users SET last_login_ip = ? WHERE id = ?");
        $update_stmt->execute([$login_ip, $user['id']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
        $_SESSION['admin'] = (int)($user['admin'] ?? 0);
        
        if ($remember) {
            setRememberToken($user['id']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Welcome back', 'redirect' => '../index.php']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        recordFailedLogin();
        setMessage("error", "Invalid login");
        header("Location: ../../login.php");
        exit;
    }
    
    if (empty($user['birthdate'])) {
        clearLoginAttempts();
        $login_ip = get_user_ip();
        $update_stmt = $pdo->prepare("UPDATE users SET last_login_ip = ? WHERE id = ?");
        $update_stmt->execute([$login_ip, $user['id']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
        $_SESSION['admin'] = (int)($user['admin'] ?? 0);
        header("Location: ../../set_birthdate.php");
        exit;
    }
    
    clearLoginAttempts();
    
    $login_ip = get_user_ip();
    $update_stmt = $pdo->prepare("UPDATE users SET last_login_ip = ? WHERE id = ?");
    $update_stmt->execute([$login_ip, $user['id']]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
    $_SESSION['admin'] = (int)($user['admin'] ?? 0);
    
    if ($remember) {
        setRememberToken($user['id']);
    }
    
    setMessage("success", "Welcome back");
    header("Location: ../../index.php");
    exit;
}
?>