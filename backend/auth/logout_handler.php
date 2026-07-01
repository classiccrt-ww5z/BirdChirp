<?php



if(isset($_SESSION['original_admin_id'])){
    $_SESSION['user_id'] = $_SESSION['original_admin_id'];
    $_SESSION['username'] = $_SESSION['original_admin_user'];
    $_SESSION['avatar'] = $_SESSION['original_admin_avatar'];
    $_SESSION['admin'] = 1;
    unset($_SESSION['original_admin_id']);
    unset($_SESSION['original_admin_user']);
    unset($_SESSION['original_admin_avatar']);
    header("Location: /");
} else {
    require_once __DIR__ . '/../../database/config.php';
    require_once __DIR__ . '/../../functions/auth.php';
    
    if (isset($_COOKIE['remember_token'])) {
        clearRememberToken();
    }
    
    session_destroy();
    header("Location: /");
}