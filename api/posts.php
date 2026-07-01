<?php
header('Content-Type: application/json');
require_once "../database/config.php"; 

$uid = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 0;
if (!$uid) {
    echo json_encode(['error' => 'User ID required']);
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar 
                           FROM posts p 
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.user_id = ? 
                           ORDER BY p.id DESC 
                           LIMIT 1");
    $stmt->execute([$uid]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        echo json_encode([
            'status' => 'success',
            'data' => $post
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No posts found for this user'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
}
exit;