<?php

require_once '../../database/config.php';
require_once '../../functions/auth.php';
require_once '../../functions/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$ffmpeg = 'ffmpeg';
$tmpDir = realpath(__DIR__ . "/../../tmp");
if (!$tmpDir) {
    $tmpDir = realpath(__DIR__ . "/../../") . DIRECTORY_SEPARATOR . "tmp";
}
if (!file_exists($tmpDir)) mkdir($tmpDir, 0755, true);

$uploadDir = realpath(__DIR__ . "/../../") . DIRECTORY_SEPARATOR . "videos" . DIRECTORY_SEPARATOR . "posts";
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

function cleanup() {
    global $tmpDir;
    $files = glob($tmpDir . DIRECTORY_SEPARATOR . "*");
    foreach ($files as $file) {
        if (is_file($file)) @unlink($file);
    }
}

if (!empty($_FILES['video']['name']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $videoTmpPath = $_FILES['video']['tmp_name'];
    $videoExt = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
    $allowedVideoExt = ['mp4', 'webm', 'mov', 'avi', 'mkv', '3gp', 'flv', 'wmv', 'm4v', 'mpeg', 'mpg'];
    
    if (!in_array($videoExt, $allowedVideoExt)) {
        echo json_encode(['success' => false, 'error' => 'Invalid video format: ' . $videoExt]);
        exit;
    }
    
    $videoName = bin2hex(random_bytes(16)) . ".mp4";
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $videoName;
    
    $tmpVideoName = bin2hex(random_bytes(8)) . "_input." . $videoExt;
    $tmpVideoPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpVideoName;
    
    if (!copy($videoTmpPath, $tmpVideoPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to copy file']);
        exit;
    }
    
    $cmd = sprintf(
        '%s -i %s -vf "scale=-2:480" -c:v libx264 -preset fast -crf 28 -c:a aac -b:a 64k -movflags +faststart -y %s 2>&1',
        escapeshellarg($ffmpeg),
        escapeshellarg($tmpVideoPath),
        escapeshellarg($targetPath)
    );
    shell_exec($cmd);
    
    @unlink($tmpVideoPath);
    
    if (file_exists($targetPath) && filesize($targetPath) > 0) {
        cleanup();
        echo json_encode(['success' => true, 'video' => $videoName]);
        exit;
    }
    
    if ($videoExt === 'mp4') {
        if (copy($tmpVideoPath, $targetPath)) {
            @unlink($tmpVideoPath);
            cleanup();
            echo json_encode(['success' => true, 'video' => $videoName]);
            exit;
        }
    }
    
    @unlink($tmpVideoPath);
    echo json_encode(['success' => false, 'error' => 'Video processing failed']);
    exit;
}

if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $tmpPath = $_FILES['image']['tmp_name'];
    $fileInfo = getimagesize($tmpPath);
    
    if (!$fileInfo) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        exit;
    }
    
    $imgUploadDir = realpath(__DIR__ . "/../../images/posts");
    if (!file_exists($imgUploadDir)) mkdir($imgUploadDir, 0755, true);
    
    $mime = $fileInfo['mime'];
    $isGif = ($mime === 'image/gif');
    $ext = $isGif ? 'gif' : 'jpg';
    $imageName = bin2hex(random_bytes(16)) . "." . $ext;
    $targetPath = $imgUploadDir . DIRECTORY_SEPARATOR . $imageName;
    
    if ($isGif) {
        copy($tmpPath, $targetPath);
    } else {
        if (file_exists($ffmpeg)) {
            $cmd = sprintf('%s -i %s -vf "scale=min(1200\,iw):-2" -q:v 2 -y %s 2>&1', 
                escapeshellarg($ffmpeg),
                escapeshellarg($tmpPath),
                escapeshellarg($targetPath)
            );
            shell_exec($cmd);
        }
        
        if (!file_exists($targetPath) || filesize($targetPath) == 0) {
            copy($tmpPath, $targetPath);
        }
    }
    
    if (file_exists($targetPath) && filesize($targetPath) > 0) {
        cleanup();
        echo json_encode(['success' => true, 'image' => $imageName]);
        exit;
    } else {
        cleanup();
        echo json_encode(['success' => false, 'error' => 'Image processing failed']);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'No file uploaded']);
