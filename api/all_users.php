<?php
header("Content-Type: application/json");
header("Content-Type: application/json; charset=utf-8"); 

require_once "../database/config.php";

try {
    $sql = "SELECT 
                id, 
                username, 
                display_name, 
                avatar, 
                bio, 
                created_at 
            FROM users 
            WHERE id NOT IN (SELECT user_id FROM bans) 
            ORDER BY id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$users) {
        echo json_encode([]);
    } else {
        echo json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error", "details" => "Could not fetch directory."]);
}