<?php
require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/notifications.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['count' => 0]); exit; }
$count = getUnreadNotificationCount($_SESSION['user_id']);
echo json_encode(['count' => (int)$count]);
