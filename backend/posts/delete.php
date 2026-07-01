<?php

require_once "../../database/config.php";
require_once "../../functions/posts.php";
require_once "../../functions/security.php";
if (!isset($_SESSION['user_id'])) {
    die("LOGIN FELLA");
}
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    die("CSRF validation failed.");
}
$postId = intval($_POST['post_id'] ?? 0);
$userId = $_SESSION['user_id'];
$success = deletePost($postId, $userId);
if (!$success) {
    die("Failed to delete post or not yours");
}
safeRedirect();