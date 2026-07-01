<?php
require_once __DIR__ . '/../database/config.php';

function createNotification($userId, $type, $fromUserId, $postId = null, $message = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, from_user_id, post_id, message) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $fromUserId, $postId, $message]);
}

function getUserNotifications($userId, $limit = 50) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT n.*, u.username, u.display_name, u.avatar
        FROM notifications n
        JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadNotificationCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function markNotificationAsRead($notificationId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsAsRead($userId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

function deleteNotification($notificationId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notificationId, $userId]);
}

function notifyOnLike($postId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $postOwner = $stmt->fetchColumn();
    if ($postOwner && $postOwner != $userId) {
        createNotification($postOwner, 'like', $userId, $postId, 'liked your post');
    }
}

function notifyOnReply($postId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $postOwner = $stmt->fetchColumn();
    if ($postOwner && $postOwner != $userId) {
        createNotification($postOwner, 'reply', $userId, $postId, 'replied to your post');
    }
}

function notifyOnFollow($followedUserId, $followerId) {
    global $pdo;
    createNotification($followedUserId, 'follow', $followerId, null, 'started following you');
}
