<?php
function checkRateLimit($action, $limit_seconds = 60) {
    if (session_status() === PHP_SESSION_NONE) {
        
    }
    
    $key = "last_submit_" . $action;
    $now = time();

    if (isset($_SESSION[$key])) {
        $elapsed = $now - $_SESSION[$key];
        if ($elapsed < $limit_seconds) {
            return $limit_seconds - $elapsed; 
        }
    }
    return 0; 
}
?>