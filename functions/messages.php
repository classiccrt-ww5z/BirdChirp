<?php
function setMessage($type, $text)
{
    $_SESSION['site_message'] = [
        "type" => $type,
        "text" => $text
    ];
}
function showMessage($admin = false)
{
    if ($_SESSION['site_message']??null) {
        $msg = $_SESSION['site_message'];
        $type = $msg['type'] ?? 'info';
        $text = htmlspecialchars($msg['text']);
        if ($admin) {
            $map = ['success'=>'success','error'=>'danger','warning'=>'warning','info'=>'info'];
            $c = $map[$type] ?? 'info';
            echo "<div class=\"alert alert-$c\">$text</div>";
        } else {
            $map = ['success'=>'success','error'=>'error','warning'=>'warning','info'=>'info'];
            $c = $map[$type] ?? 'info';
            echo "<div class=\"alert-message $c\"><p>$text</p></div>";
        }
        unset($_SESSION['site_message']);
    }
}
?>