<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/messages.php';
require_once '../../functions/notifications.php';
require_once '../../functions/security.php';

requireLogin();

$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) {
    setMessage("error", "CSRF validation failed.");
    safeRedirect();
}

$currentUser = $_SESSION['user_id'];
$userToFollow = intval($_GET['id']);
if ($currentUser === $userToFollow) {
    setMessage("error", "You don't need to follow yourself, silly.");
    safeRedirect();
}
$checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->execute([$userToFollow]);

if (!$checkUser->fetch()) {
    setMessage("error", "That user doesn't seem to exist.");
    safeRedirect();
}
$stmt = $pdo->prepare("
    INSERT IGNORE INTO follows (follower_id, following_id)
    VALUES (?, ?)
");
$stmt->execute([$currentUser, $userToFollow]);

notifyOnFollow($userToFollow, $currentUser);

setMessage("success", "User followed successfully.");
safeRedirect();