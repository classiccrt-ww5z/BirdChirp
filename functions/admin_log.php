<?php
require_once __DIR__ . '/../database/config.php';

function logAdminAction($action, $targetUser, $adminUser, $details = '') {
    global $pdo;

    $adminId = $_SESSION['user_id'] ?? 0;
    $adminUsername = $adminUser ?: ($_SESSION['username'] ?? 'Unknown');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    try {
        $pdo->prepare("CREATE TABLE IF NOT EXISTS admin_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            target_id VARCHAR(255) DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->prepare("INSERT INTO admin_log (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, $action, $targetUser, $adminUsername, $details, $ip]);
    } catch (PDOException $e) {}

    sendAdminDiscordLog($action, $targetUser, $adminUsername, $details);
}

function sendAdminDiscordLog($action, $targetUser, $adminUser, $details = '') {
    $discord_webhook_url = getenv('DISCORD_WEBHOOK_URL') ?: '';
    if (empty($discord_webhook_url)) return false;

    $colorMap = [
        'ban' => 15158332, 'unban' => 3066993, 'delete' => 15158332,
        'verify' => 3066993, 'admin toggle' => 16776960, 'news add' => 3066993,
        'news delete' => 15158332, 'edit user' => 3447003, 'login as user' => 16776960,
        'restore admin' => 3447003, 'reset username' => 16776960,
    ];
    $actionLower = strtolower($action);
    $color = $colorMap[$actionLower] ?? 3447003;

    $fields = [
        ['name' => 'Action', 'value' => $action, 'inline' => true],
        ['name' => 'Admin', 'value' => $adminUser, 'inline' => true],
        ['name' => 'Target', 'value' => $targetUser, 'inline' => true],
    ];
    if (!empty($details)) {
        $fields[] = ['name' => 'Details', 'value' => $details, 'inline' => false];
    }

    $payload = [
        'embeds' => [[
            'title' => "[ADMIN] {$action}",
            'description' => "Admin action performed on the platform",
            'color' => $color,
            'timestamp' => date('c'),
            'footer' => ['text' => 'Admin Action Log'],
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
