<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/posts.php';
require_once '../../functions/messages.php';
require_once '../../functions/security.php';

requireLogin();

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setMessage("error", "Session expired, please try again.");
    header("Location: ../../index.php");
    exit;
}

$cooldown_seconds = 15;
$current_time = time();
if (isset($_SESSION['last_post_time'])) {
    $elapsed = $current_time - $_SESSION['last_post_time'];
    if ($elapsed < $cooldown_seconds) {
        $wait = $cooldown_seconds - $elapsed;
        setMessage("error", "Slow down! Wait $wait seconds.");
        header("Location: ../../index.php");
        exit;
    }
}
$stmt = $pdo->prepare("SELECT created_at FROM posts WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$lastPost = $stmt->fetchColumn();

if ($lastPost && ($current_time - strtotime($lastPost)) < $cooldown_seconds) {
    setMessage("error", "Spam detected. Please wait.");
    header("Location: ../../index.php");
    exit;
}

$_SESSION['last_post_time'] = $current_time;
$content = trim($_POST['content'] ?? '');
$image = trim($_POST['image'] ?? null);
$video = trim($_POST['video'] ?? null);

if (empty($content) && empty($image) && empty($video)) {
    setMessage("error", "Post cannot be empty.");
    header("Location: ../../index.php");
    exit;
}

$content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

createPost($_SESSION['user_id'], $content, $image, $video);
setMessage("success", "Posted successfully!");
header("Location: ../../index.php");
exit;
