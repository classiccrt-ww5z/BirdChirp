<?php

require_once "../../database/config.php";
require_once "../../functions/security.php";
if (!isset($_SESSION['user_id'])) {
    die("LOGIN FELLA");
}
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    die("CSRF validation failed.");
}
$replyId = intval($_POST['reply_id'] ?? 0);
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("DELETE FROM replies WHERE id = ? AND user_id = ?");
$stmt->execute([$replyId, $userId]);
safeRedirect();