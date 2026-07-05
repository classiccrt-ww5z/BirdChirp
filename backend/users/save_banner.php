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

if (empty($_SESSION['banner_temp'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No upload found']);
    exit;
}

$tempFile = $_SESSION['banner_temp'];
$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "images") ?: (__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "images");
$tempDir = $baseDir . DIRECTORY_SEPARATOR . "banners" . DIRECTORY_SEPARATOR . "temp";
$tempPath = $tempDir . DIRECTORY_SEPARATOR . $tempFile['filename'];

if (!file_exists($tempPath) || $tempFile['expires'] < time()) {
    unset($_SESSION['banner_temp']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload expired']);
    exit;
}

$bannerName = bin2hex(random_bytes(16)) . "." . pathinfo($tempFile['filename'], PATHINFO_EXTENSION);
$uploadDir = $baseDir . DIRECTORY_SEPARATOR . "banners";
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $bannerName;

if (!rename($tempPath, $targetPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to save banner']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET banner=? WHERE id=?");
$stmt->execute([$bannerName, $_SESSION['user_id']]);

unset($_SESSION['banner_temp']);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Banner saved!', 'banner' => $bannerName]);
    exit;
}

setMessage('success', 'Banner updated!');
header("Location: /settings.php?tab=banner");
exit;
