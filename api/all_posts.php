<?php
header("Content-Type: application/json");
require_once "../database/config.php";
require_once "../functions/auth.php";

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$tab = $_GET['tab'] ?? 'global';
$uid = $_SESSION['user_id'] ?? 0;

try {
    $query = "
        SELECT p.*, u.username, u.display_name, u.avatar,
        (SELECT COUNT(*) FROM follows WHERE follower_id = :uid AND following_id = p.user_id) as is_following
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN bans b ON p.user_id = b.user_id 
        WHERE b.user_id IS NULL
    ";
    
    $params = ['uid' => $uid, 'limit' => $limit, 'offset' => $offset];
    
    if ($tab === 'following' && $uid) {
        $query .= " AND p.user_id IN (SELECT following_id FROM follows WHERE follower_id = :uid)";
    }
    
    if (isset($_GET['user_id'])) {
        $query .= " AND p.user_id = :user_id";
        $params['user_id'] = intval($_GET['user_id']);
    }
    
    if (isset($_GET['replies'])) {
        $query = "SELECT r.*, u.username, u.display_name, u.avatar 
                  FROM replies r 
                  JOIN users u ON r.user_id = u.id 
                  LEFT JOIN bans b ON r.user_id = b.user_id 
                  WHERE r.post_id = :replies AND b.user_id IS NULL
                  ORDER BY r.created_at ASC 
                  LIMIT :limit OFFSET :offset";
        $params = ['replies' => intval($_GET['replies']), 'limit' => $limit, 'offset' => $offset];
    }
    
    if (isset($_GET['q'])) {
        $query .= " AND p.content LIKE :q";
        $params['q'] = '%' . addcslashes($_GET['q'], '%_') . '%';
    }
    
    $query .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($posts);
} catch (PDOException $e) {
    error_log("DB Error in all_posts.php: " . $e->getMessage());
    echo json_encode(["error" => "An error occurred"]);
}
