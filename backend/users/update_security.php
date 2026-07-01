<?php

require_once __DIR__ . "/../../database/config.php";
require_once __DIR__ . "/../../functions/auth.php";
require_once __DIR__ . "/../../functions/security.php";
require_once __DIR__ . "/../../functions/messages.php";
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
    exit;
}

$isAjax = isset($_POST['ajax']);
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($username !== $user['username']) {
    if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Username must be 3-30 characters']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
    $stmt->execute([$username, $user_id]);
    $_SESSION['username'] = $username;
}

if (!empty($new_password)) {
    if (!password_verify($current_password, $user['password'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
        exit;
    }
    if ($new_password !== $confirm_password) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit;
    }
    if (strlen($new_password) < 8) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hash, $user_id]);
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Security settings updated!']);
    exit;
}

setMessage('success', 'Security settings updated!');
header("Location: /settings.php?tab=security");
exit;