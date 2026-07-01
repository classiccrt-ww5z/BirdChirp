<?php

require_once "../../database/config.php";
require_once "../../functions/auth.php";
require_once "../../functions/posts.php"; 

if (!isLoggedIn()) {
    exit;
}

$uid = $_SESSION['user_id'];
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$stmt = $pdo->prepare("SELECT p.*, u.username, u.avatar, 
                     (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = p.user_id) as is_following
                     FROM posts p 
                     JOIN users u ON p.user_id = u.id 
                     LEFT JOIN bans b ON p.user_id = b.user_id
                     WHERE p.id < ? AND b.user_id IS NULL
                     ORDER BY p.id DESC LIMIT 5");
$stmt->execute([$uid, $last_id]);
$posts = $stmt->fetchAll();

foreach ($posts as $post) {
    include "../../components/post_template.php";
}