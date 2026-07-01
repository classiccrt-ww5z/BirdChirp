<?php

require_once '../../functions/auth.php';
require_once '../../database/config.php';
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

if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $tmpPath = $_FILES['avatar']['tmp_name'];
    $originalName = $_FILES['avatar']['name'];
    
    $info = getimagesize($tmpPath);
    if (!$info) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not a valid image']);
        exit;
    }
    
    $mime = $info['mime'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, WebP allowed']);
        exit;
    }
    
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    $tempToken = bin2hex(random_bytes(16));
    $tempDir = realpath(__DIR__ . "/../../") . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "avatars" . DIRECTORY_SEPARATOR . "temp";
    
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $tempFilePath = $tempDir . DIRECTORY_SEPARATOR . $tempToken . ".png";
    
    $cmd = sprintf('ffmpeg -i %s -vf "scale=160:160" -frames:v 1 -y %s 2>&1',
        escapeshellarg($tmpPath),
        escapeshellarg($tempFilePath)
    );
    shell_exec($cmd);
    
    if (!file_exists($tempFilePath) || filesize($tempFilePath) < 100) {
        copy($tmpPath, $tempFilePath);
    }
    
    $finalName = basename($tempFilePath);
    
    $_SESSION['avatar_temp'] = [
        'filename' => $finalName,
        'expires' => time() + 3600
    ];
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Image uploaded!',
            'preview_url' => '/images/avatars/temp/' . $finalName,
            'filename' => $finalName
        ]);
        exit;
    }
    
    setMessage('success', 'Image uploaded! Preview below.');
    header("Location: /settings.php?tab=pfp");
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'No file uploaded']);
exit;