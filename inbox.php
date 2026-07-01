<?php
require_once "header.php";
require_once "functions/notifications.php";
require_once "functions/auth.php";

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];

$csrf = generateCSRF();

if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    if (!verifyCSRF($_GET['csrf'] ?? '')) { header("Location: /inbox.php"); exit; }
    $notifId = (int)$_GET['mark_read'];
    markNotificationAsRead($notifId, $userId);
    header("Location: /inbox.php");
    exit;
}

if (isset($_GET['mark_all_read'])) {
    if (!verifyCSRF($_GET['csrf'] ?? '')) { header("Location: /inbox.php"); exit; }
    markAllNotificationsAsRead($userId);
    header("Location: /inbox.php");
    exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!verifyCSRF($_GET['csrf'] ?? '')) { header("Location: /inbox.php"); exit; }
    $notifId = (int)$_GET['delete'];
    deleteNotification($notifId, $userId);
    header("Location: /inbox.php");
    exit;
}

$notifications = getUserNotifications($userId);
$unreadCount = getUnreadNotificationCount($userId);
?>

<style>
.notif-item { padding: 15px 10px; border-bottom: 1px solid #eee; transition: background 0.1s ease; position: relative; }
.notif-item:hover { background: #f9f9f9; }
.notif-item.unread { background: #f0f8ff; }
.notif-item a { text-decoration: none; color: #333; }
.notif-layout { display: table; width: 100%; table-layout: fixed; }
.notif-avatar { display: table-cell; width: 50px; vertical-align: top; }
.notif-avatar img { width: 40px; height: 40px;}
.notif-body { display: table-cell; vertical-align: top; padding-left: 10px; }
.notif-meta { font-size: 14px; margin-bottom: 3px; color: #404040; }
.notif-time { font-size: 12px; color: #999; }
.notif-type-like { color: #e0245e; font-weight: 500; }
.notif-type-reply { color: #1b95e0; font-weight: 500; }
.notif-type-follow { color: #17bf63; font-weight: 500; }
.notif-type-mention { color: #7941e6; font-weight: 500; }
.notif-actions { margin-top: 8px; }
.notif-actions a { margin-right: 10px; font-size: 12px; }
</style>

<div class="container mt-4">
    <div class="page-header">
        <h1>Notifications 
            <?php if ($unreadCount > 0): ?>
                <small><span class="label notice"><?= $unreadCount ?> unread</span></small>
            <?php endif; ?>
        </h1>
    </div>

    <?php if ($unreadCount > 0): ?>
        <div style="margin-bottom: 15px;">
            <a href="?mark_all_read=1&csrf=<?= $csrf ?>" class="btn small">Mark all as read</a>
        </div>
    <?php endif; ?>

    <div class="border rounded bg-white">
        <?php if (empty($notifications)): ?>
            <div>
                <p>No notifications yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                    <div class="notif-layout">
                        <div class="notif-avatar">
                            <a href="/u/<?= $notif['from_user_id'] ?>">
                                <img src="/images/avatars/<?= htmlspecialchars($notif['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail">
                            </a>
                        </div>
                        <div class="notif-body">
                            <div class="notif-meta">
                                <a href="/u/<?= $notif['from_user_id'] ?>">
                                    <strong style="color: #404040;"><?= htmlspecialchars($notif['display_name'] ?: $notif['username']) ?></strong>
                                </a>
                                <span class="notif-type-<?= htmlspecialchars($notif['type']) ?>">
                                    <?php switch($notif['type']) {
                                        case 'like': echo 'liked your post'; break;
                                        case 'reply': echo 'replied to your post'; break;
                                        case 'follow': echo 'started following you'; break;
                                        case 'mention': echo 'mentioned you'; break;
                                    } ?>
                                </span>
                            </div>
                            <div class="notif-time"><?= date('M d, g:i a', strtotime($notif['created_at'])) ?></div>
                            <div class="notif-actions">
                                <?php if (!$notif['is_read']): ?>
                                    <a href="?mark_read=<?= $notif['id'] ?>&csrf=<?= $csrf ?>" class="btn small">Mark read</a>
                                <?php endif; ?>
                                <a href="?delete=<?= $notif['id'] ?>&csrf=<?= $csrf ?>" class="btn small danger" onclick="return confirm('Delete this notification?')">Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once "footer.php"; ?>
