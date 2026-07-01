<?php

require_once '../../functions/auth.php';
require_once '../../functions/users.php';
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

$bio = trim($_POST['bio'] ?? '');
$display_name = trim($_POST['display_name'] ?? '');

global $pdo;
$stmt = $pdo->prepare("UPDATE users SET bio = ?, display_name = ? WHERE id = ?");
$stmt->execute([$bio, $display_name, $_SESSION['user_id']]);

$_SESSION['display_name'] = $display_name;

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    exit;
}

setMessage('success', 'Profile updated!');
header("Location: ../../settings.php?tab=profile");
exit;