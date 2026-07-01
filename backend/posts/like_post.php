<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/notifications.php';
require_once '../../functions/security.php';
requireLogin();

$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) {
    die("CSRF validation failed.");
}

$post_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

if ($post_id > 0) {
    $check = $pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?");
    $check->execute([$user_id, $post_id]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $post_id]);
        notifyOnLike($post_id, $user_id);
    }
}
safeRedirect();