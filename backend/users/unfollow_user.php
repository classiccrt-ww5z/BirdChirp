<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/security.php';
requireLogin();

$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) {
    safeRedirect();
}
$user = intval($_GET['id']);
$stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id=? AND following_id=?");
$stmt->execute([$_SESSION['user_id'], $user]);
safeRedirect();