<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/security.php';
require_once '../../functions/messages.php';
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

if (empty($_SESSION['avatar_temp'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No upload found']);
    exit;
}

$tempFile = $_SESSION['avatar_temp'];
$tempDir = realpath(__DIR__ . "/../../") . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "avatars" . DIRECTORY_SEPARATOR . "temp";
$tempPath = $tempDir . DIRECTORY_SEPARATOR . $tempFile['filename'];

if (!file_exists($tempPath) || $tempFile['expires'] < time()) {
    unset($_SESSION['avatar_temp']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload expired']);
    exit;
}

$avatarName = bin2hex(random_bytes(16)) . "." . pathinfo($tempFile['filename'], PATHINFO_EXTENSION);
$uploadDir = realpath(__DIR__ . "/../../") . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "avatars";
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $avatarName;

if (!rename($tempPath, $targetPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to save avatar']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET avatar=? WHERE id=?");
$stmt->execute([$avatarName, $_SESSION['user_id']]);

$_SESSION['avatar'] = $avatarName;

unset($_SESSION['avatar_temp']);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Avatar saved!', 'avatar' => $avatarName]);
    exit;
}

setMessage('success', 'Profile picture updated!');
header("Location: /settings.php?tab=pfp");
exit;