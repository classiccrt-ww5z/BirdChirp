<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
requireLogin();

$isAjax = isset($_GET['ajax']);

if (!empty($_SESSION['avatar_temp'])) {
    $tempDir = realpath(__DIR__ . "/../../") . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "avatars" . DIRECTORY_SEPARATOR . "temp";
    $tempPath = $tempDir . DIRECTORY_SEPARATOR . $_SESSION['avatar_temp']['filename'];
    
    if (file_exists($tempPath)) {
        @unlink($tempPath);
    }
    
    unset($_SESSION['avatar_temp']);
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header("Location: /settings.php?tab=pfp");
exit;