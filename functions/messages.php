<?php
function setMessage($type, $text)
{
    $_SESSION['site_message'] = [
        "type" => $type,
        "text" => $text
    ];
}
function showMessage()
{
    if ($_SESSION['site_message']??null) {

        $msg = $_SESSION['site_message'];
        $type = $msg['type'] ?? 'info'; 
        switch ($type) {
            case 'success':
                $class = 'success';
                break;
            case 'error':
                $class = 'error';
                break;
            case 'warning':
                $class = 'warning';
                break;
            default:
                $class = 'info';
                break;
        }
        echo "<div class='alert-message $class'><p>"
            . htmlspecialchars($msg['text'])
            . "</p></div>";

        unset($_SESSION['site_message']);
    }
}
?>