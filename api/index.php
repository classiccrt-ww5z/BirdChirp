<?php
header('Content-Type: application/json');
require_once __DIR__ . "/../database/config.php";
require_once __DIR__ . "/../functions/auth.php";
require_once __DIR__ . "/../functions/security.php";



$action = $_GET['action'] ?? '';
$uid = $_SESSION['user_id'] ?? 0;

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function e($t) { return htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8'); }

switch ($action) {
    case 'me':
        if (!$uid) {
            jsonResponse(['error' => 'Not logged in']);
        }
        $stmt = $pdo->prepare("SELECT id, username, display_name, avatar, bio FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if ($user) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
            $stmt->execute([$uid]);
            $user['following_count'] = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
            $stmt->execute([$uid]);
            $user['followers_count'] = $stmt->fetchColumn();
            jsonResponse($user);
        }
        jsonResponse(['error' => 'User not found']);
        break;

    case 'feed':
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN bans b ON p.user_id = b.user_id
            WHERE b.user_id IS NULL
            ORDER BY p.id DESC LIMIT 50");
        $stmt->execute([$uid]);
        $posts = $stmt->fetchAll();
        jsonResponse(['posts' => $posts]);
        break;

    case 'posts':
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN bans b ON p.user_id = b.user_id
            WHERE b.user_id IS NULL
            ORDER BY p.id DESC LIMIT 50");
        $stmt->execute([$uid]);
        $posts = $stmt->fetchAll();
        jsonResponse(['posts' => $posts]);
        break;

    case 'user':
        $user_id = intval($_GET['id'] ?? 0);
        if (!$user_id) {
            jsonResponse(['error' => 'User ID required']);
        }
        $stmt = $pdo->prepare("SELECT id, username, display_name, avatar, bio FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonResponse(['error' => 'User not found']);
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
        $stmt->execute([$user_id]);
        $user['following_count'] = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
        $stmt->execute([$user_id]);
        $user['followers_count'] = $stmt->fetchColumn();
        
        $is_following = false;
        if ($uid && $uid != $user_id) {
            $stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?");
            $stmt->execute([$uid, $user_id]);
            $is_following = (bool)$stmt->fetch();
        }
        jsonResponse(['user' => $user, 'is_following' => $is_following]);
        break;

    case 'user_posts':
        $user_id = intval($_GET['id'] ?? 0);
        if (!$user_id) {
            jsonResponse(['error' => 'User ID required']);
        }
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN bans b ON p.user_id = b.user_id
            WHERE p.user_id = ? AND b.user_id IS NULL
            ORDER BY p.id DESC LIMIT 50");
        $stmt->execute([$uid, $user_id]);
        $posts = $stmt->fetchAll();
        jsonResponse(['posts' => $posts]);
        break;

    case 'create_post':
        if (!$uid) {
            jsonResponse(['error' => 'Not logged in']);
        }
        $content = trim($_POST['content'] ?? '');
        if (!$content) {
            jsonResponse(['error' => 'Content required']);
        }
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$uid, $content]);
        jsonResponse(['success' => true, 'post_id' => $pdo->lastInsertId()]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action']);
}