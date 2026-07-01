<?php


require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/posts.php';
require_once '../../functions/messages.php';
require_once '../../functions/upload.php';
require_once '../../functions/security.php';
require_once '../../functions/notifications.php';

if (!isset($csrf)) $csrf = generateCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('isLoggedIn') && isLoggedIn()) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setMessage("error", "Session expired, please try again.");
        header("Location: /index.php");
        exit;
    }
    $post_id = intval($_POST['post_id']);
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];
    $cooldown_seconds = 10;
    $current_time = time();
    if (isset($_SESSION['last_reply_time'])) {
        $elapsed = $current_time - $_SESSION['last_reply_time'];
        if ($elapsed < $cooldown_seconds) {
            $wait = $cooldown_seconds - $elapsed;
            setMessage("error", "Slow down! Wait {$wait}s before replying again.");
            header("Location: /post/" . $post_id);
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT created_at FROM replies WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $lastReply = $stmt->fetchColumn();

    if ($lastReply && ($current_time - strtotime($lastReply)) < $cooldown_seconds) {
        setMessage("error", "Please wait a moment before posting another reply.");
        header("Location: /post/" . $post_id);
        exit;
    }
    if ($post_id <= 0 || empty($content)) {
        setMessage("error", "Reply cannot be empty.");
        header("Location: /post/" . $post_id);
        exit;
    }
    if (mb_strlen($content, 'UTF-8') > 3000) {
        setMessage("error", "Reply cannot exceed 3000 characters.");
        header("Location: /post/" . $post_id);
        exit;
    }
    if (preg_match('/[\x{0B80}-\x{0BFF}]/u', $content) || preg_match('/\p{M}{4,}/u', $content)) {
        setMessage("error", "Do not use that.");
        header("Location: /post/" . $post_id);
        exit;
    }
    $blockedPatterns = [
        '/<[^>]+>/',    
        '/<iframe.*?>.*?<\/iframe>/is',
        '/<object.*?>.*?<\/object>/is',
        '/<embed.*?>.*?<\/embed>/is',
        '/<svg.*?>.*?<\/svg>/is',
        '/<script.*?>.*?<\/script>/is',
        '/<style.*?>.*?<\/style>/is',
    ];

    foreach ($blockedPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            setMessage("error", "HTML or script tags are not allowed.");
            header("Location: /post/" . $post_id);
            exit;
        }
    }

    if (stripos($content, '<?php') !== false) {
        setMessage("error", "PHP code is not allowed.");
        header("Location: /post/" . $post_id);
        exit;
    }
    $_SESSION['last_reply_time'] = $current_time;
    $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    try {
        $stmt = $pdo->prepare("INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $user_id, $content]);
        
        notifyOnReply($post_id, $user_id);
        
        setMessage("success", "Reply posted successfully!");
        header("Location: /post/" . $post_id);
        exit;
    } catch (PDOException $e) {
        setMessage("error", "Database error.");
        header("Location: /index.php");
        exit;
    }
}

header("Location: /index.php");
exit;