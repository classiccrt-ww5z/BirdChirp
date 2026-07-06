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
    if (!empty($referer)) {
        $refParsed = parse_url($referer);
        $refHost = $refParsed['host'] ?? '';
        $refPort = $refParsed['port'] ?? '';
        $curParsed = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''));
        $curHost = $curParsed['host'] ?? '';
        $curPort = $curParsed['port'] ?? '';
        if ($refHost === $curHost && (!$refPort || !$curPort || $refPort === $curPort)) {
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
