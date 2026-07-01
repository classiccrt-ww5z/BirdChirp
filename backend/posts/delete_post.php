<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/posts.php';
require_once '../../functions/security.php';

requireLogin();

$token = $_GET['csrf'] ?? '';
if (!verifyCSRF($token)) {
    header("Location: ../../index.php");
    exit;
}

$id = intval($_GET['id']);

deletePost($id,$_SESSION['user_id']);

header("Location: ../../index.php");