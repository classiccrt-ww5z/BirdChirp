<?php
require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/polls.php';
require_once '../../functions/security.php';
requireLogin();
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$pollId = intval($_POST['poll_id'] ?? 0);
$optionId = intval($_POST['option_id'] ?? 0);
$token = $_POST['csrf'] ?? '';
if (!verifyCSRF($token) || $pollId <= 0 || $optionId <= 0) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false]); exit; }
    header("Location: /"); exit;
}
$result = votePoll($pollId, $optionId, $_SESSION['user_id']);
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit;
}
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;
