<?php
require_once __DIR__ . '/../database/config.php';

function getAllNews() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addNews($title, $content) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO news (title, content, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$title, $content]);
}

function deleteNews($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
    $stmt->execute([$id]);
}