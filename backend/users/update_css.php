<?php
require_once '../../functions/auth.php';
require_once '../../functions/security.php';
require_once '../../functions/messages.php';
require_once '../../database/config.php';
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

$custom_css = trim($_POST['custom_css'] ?? '');

$sanitized = strip_tags($custom_css);

$stmt = $pdo->prepare("UPDATE users SET custom_css = ? WHERE id = ?");
$stmt->execute([$sanitized, $_SESSION['user_id']]);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Custom CSS saved!']);
    exit;
}

setMessage('success', 'Custom CSS updated!');
header("Location: /settings.php?tab=css");
exit;
