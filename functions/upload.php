<?php

function uploadImage($file, $folder)
{
    if ($file['error'] !== 0) {
        return null;
    }
    $allowed = ['jpg','jpeg','png','gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,$allowed)) {
        return null;
    }
    $name = bin2hex(random_bytes(8)) . "." . $ext;
    $path = __DIR__ . "/../images/$folder/" . $name;
    move_uploaded_file($file['tmp_name'],$path);
    return $name;
}