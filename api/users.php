<?php
header("Content-Type: application/json");
require_once "../database/config.php";
require_once "../functions/auth.php";

$id = $_GET['id'] ?? null;
$uid = $_SESSION['user_id'] ?? 0;

try {
    if (isset($_GET['me']) && $uid) {
        $stmt = $pdo->prepare("SELECT id, username, display_name, avatar, bio, email FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
            $stmt->execute([$uid]);
            $data['posts_count'] = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
            $stmt->execute([$uid]);
            $data['following_count'] = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
            $stmt->execute([$uid]);
            $data['followers_count'] = $stmt->fetchColumn();
        }
        echo json_encode($data ? [$data] : []);
    } elseif ($id) {
        $stmt = $pdo->prepare("SELECT id, username, display_name, avatar, bio, created_at FROM users WHERE id = ? AND id NOT IN (SELECT user_id FROM bans)");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
            $stmt->execute([$id]);
            $data['posts_count'] = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
            $stmt->execute([$id]);
            $data['following_count'] = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
            $stmt->execute([$id]);
            $data['followers_count'] = $stmt->fetchColumn();
        }
        echo json_encode($data ? [$data] : []);
    } elseif (isset($_GET['follow'])) {
        $type = $_GET['follow'];
        if ($type === 'following') {
            $stmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.bio 
                FROM users u 
                JOIN follows f ON u.id = f.following_id 
                LEFT JOIN bans b ON u.id = b.user_id 
                WHERE f.follower_id = ? AND b.user_id IS NULL
                ORDER BY f.created_at DESC LIMIT 50");
        } else {
            $stmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.bio 
                FROM users u 
                JOIN follows f ON u.id = f.follower_id 
                LEFT JOIN bans b ON u.id = b.user_id 
                WHERE f.following_id = ? AND b.user_id IS NULL
                ORDER BY f.created_at DESC LIMIT 50");
        }
        $stmt->execute([$id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    } else {
        $stmt = $pdo->query("SELECT id, username, display_name, avatar FROM users WHERE id NOT IN (SELECT user_id FROM bans) ORDER BY id DESC LIMIT 50");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    }
} catch (PDOException $e) {
    error_log("DB Error in users.php: " . $e->getMessage());
    echo json_encode(["error" => "An error occurred"]);
}
