<?php
require_once '../../database/config.php';
require_once '../../functions/auth.php';
requireLogin();

$isAjax = isset($_GET['ajax']);

if (!empty($_SESSION['banner_temp'])) {
    $baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "images") ?: (__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "images");
    $tempDir = $baseDir . DIRECTORY_SEPARATOR . "banners" . DIRECTORY_SEPARATOR . "temp";
    $tempPath = $tempDir . DIRECTORY_SEPARATOR . $_SESSION['banner_temp']['filename'];

    if (file_exists($tempPath)) {
        @unlink($tempPath);
    }

    unset($_SESSION['banner_temp']);
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header("Location: /settings.php?tab=banner");
exit;
