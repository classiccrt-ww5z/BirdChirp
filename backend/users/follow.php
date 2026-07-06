<?php
require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/users.php';
require_once '../../functions/notifications.php';
require_once '../../functions/security.php';
requireLogin();
$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) { header("Location: /"); exit; }
$targetId = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
if ($targetId > 0 && $targetId != $userId) {
    followUser($userId, $targetId);
}
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;
