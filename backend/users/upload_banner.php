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

if (empty($_FILES['banner']['name']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['banner']['error'] ?? -1;
    $msgs = [1=>'File too large', 2=>'File too large', 3=>'Partial upload', 4=>'No file', 6=>'Temp folder missing', 7=>'Write failed'];
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $msgs[$err] ?? 'Upload error']);
    exit;
}

$tmpPath = $_FILES['banner']['tmp_name'];
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

$extMap = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp'];
$ext = $extMap[$mime] ?? 'png';

$tempToken = bin2hex(random_bytes(16));
$baseDir = realpath(__DIR__ . '/../../images');
if (!$baseDir) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Images directory not found']);
    exit;
}

$tempDir = $baseDir . '/banners/temp';
if (!is_dir($tempDir) && !@mkdir($tempDir, 0777, true)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Banner upload directory not available']);
    exit;
}

$tempFilePath = $tempDir . '/' . $tempToken . '.' . $ext;

$copied = copy($tmpPath, $tempFilePath);
if (!$copied || !file_exists($tempFilePath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

$finalName = basename($tempFilePath);

$_SESSION['banner_temp'] = [
    'filename' => $finalName,
    'expires' => time() + 3600
];

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Banner uploaded!',
        'preview_url' => '/images/banners/temp/' . $finalName,
        'filename' => $finalName
    ]);
    exit;
}

setMessage('success', 'Banner uploaded! Preview below.');
header("Location: /settings.php?tab=banner");
exit;
