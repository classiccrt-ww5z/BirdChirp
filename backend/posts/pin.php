<?php
require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/security.php';
requireLogin();
$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) { header("Location: /"); exit; }
$postId = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($postId > 0) {
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if ($post && $post['user_id'] == $userId) {
        $stmt = $pdo->prepare("SELECT pinned FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $currentlyPinned = (bool)$stmt->fetchColumn();
        if ($currentlyPinned) {
            $pdo->prepare("UPDATE posts SET pinned = 0 WHERE id = ?")->execute([$postId]);
        } else {
            $pdo->prepare("UPDATE posts SET pinned = 0 WHERE user_id = ? AND pinned = 1")->execute([$userId]);
            $pdo->prepare("UPDATE posts SET pinned = 1 WHERE id = ?")->execute([$postId]);
        }
    }
}
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/u/' . $userId));
exit;
