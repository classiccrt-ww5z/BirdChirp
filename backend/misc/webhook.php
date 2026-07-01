<?php
require_once __DIR__ . '/../../database/config.php';

$discord_webhook_url = getenv('DISCORD_WEBHOOK_URL') ?: '';

function sendDiscordLog($title, $description, $color = 3447003, $fields = []) {
    global $discord_webhook_url;
    
    if (empty($discord_webhook_url)) {
        return false;
    }
    
    $payload = [
        'embeds' => [[
            'title' => $title,
            'description' => $description,
            'color' => $color,
            'timestamp' => date('c'),
            'footer' => [
                'text' => 'Admin Action Log'
            ],
            'fields' => $fields
        ]]
    ];
    
    $ch = curl_init($discord_webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 204 || $httpCode === 200;
}

function logAdminAction($action, $targetUser, $adminUser, $details = '') {
    $color = 3447003;
    $actionIcon = '[ACTION]';
    
    switch(strtolower($action)) {
        case 'ban':
            $color = 15158332;
            $actionIcon = '[BAN]';
            break;
        case 'unban':
            $color = 3066993;
            $actionIcon = '[UNBAN]';
            break;
        case 'delete':
            $color = 15158332;
            $actionIcon = '[DELETE]';
            break;
        case 'verify':
            $color = 3066993;
            $actionIcon = '[VERIFY]';
            break;
        case 'admin toggle':
            $color = 16776960;
            $actionIcon = '[ADMIN]';
            break;
        case 'news add':
            $color = 3066993;
            $actionIcon = '[NEWS]';
            break;
        case 'news delete':
            $color = 15158332;
            $actionIcon = '[NEWS]';
            break;
    }
    
    $fields = [
        ['name' => 'Action', 'value' => $action, 'inline' => true],
        ['name' => 'Admin', 'value' => $adminUser, 'inline' => true],
        ['name' => 'Target', 'value' => $targetUser, 'inline' => true]
    ];
    
    if (!empty($details)) {
        $fields[] = ['name' => 'Details', 'value' => $details, 'inline' => false];
    }
    
    return sendDiscordLog("{$actionIcon} Admin Action: {$action}", "An admin action was performed on the platform", $color, $fields);
}
