<?php

function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
function generateCSRF()
{
    if (!isset($_SESSION)) {
        
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function verifyCSRF($token)
{
    if (!isset($_SESSION)) {
        
    }
    if (!isset($_SESSION['csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf'], $token);
}
function safeRedirect($fallback = '/') {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    if (!empty($referer)) {
        $parsed = parse_url($referer);
        $refHost = $parsed['host'] ?? '';
        if ($refHost === $host) {
            header("Location: " . $referer);
            exit;
        }
    }
    header("Location: " . $fallback);
    exit;
}

if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}
