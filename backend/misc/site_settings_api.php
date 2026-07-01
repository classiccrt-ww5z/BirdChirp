<?php
require_once __DIR__ . '/../../database/config.php';

header('Content-Type: application/json');

$key = $_GET['get'] ?? '';
$allowed = ['min_age', 'require_birthdate', 'allow_signup'];

if (!in_array($key, $allowed)) {
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $value = $row ? $row[0] : '0';
} catch (PDOException $e) {
    $value = '0';
}

echo json_encode(['key' => $key, 'value' => $value]);