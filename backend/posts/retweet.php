<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/posts.php';
require_once '../../functions/notifications.php';
require_once '../../functions/security.php';
requireLogin();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header("Location: /");
    exit;
}
header('Content-Type: application/json');

$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) {
    echo json_encode(['success' => false, 'error' => 'CSRF']);
    exit;
}

$post_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$retweeted = false;

if ($post_id > 0) {
    $check = $pdo->prepare("SELECT 1 FROM retweets WHERE user_id = ? AND post_id = ?");
    $check->execute([$user_id, $post_id]);

    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM retweets WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
        $retweeted = false;
    } else {
        $stmt = $pdo->prepare("INSERT INTO retweets (user_id, post_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $post_id]);
        notifyOnRetweet($post_id, $user_id);
        $retweeted = true;
    }
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM retweets WHERE post_id = ?");
$countStmt->execute([$post_id]);
$count = intval($countStmt->fetchColumn());

echo json_encode(['success' => true, 'retweeted' => $retweeted, 'count' => $count]);
