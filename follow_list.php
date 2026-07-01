<?php
require_once "header.php";
require_once "functions/users.php";

$id = intval($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'followers';
$viewer_id = $_SESSION['user_id'] ?? 0;
$user = getUserById($id);
if (!$user) {
    echo "<div class='container'><div class='alert-message error' style='margin-top:20px;'><p><strong>Error:</strong> User not found.</p></div></div>";
    require_once "footer.php"; exit;
}
$displayName = !empty($user['display_name']) ? $user['display_name'] : $user['username'];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$stmt->execute([$id]);
$totalPosts = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt->execute([$id]);
$totalFollowers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt->execute([$id]);
$totalFollowing = $stmt->fetchColumn();
if ($type === 'following') {
    $title = "Following";
    $sql = "SELECT u.*, 
            (SELECT 1 FROM follows WHERE follower_id = u.id AND following_id = ?) as follows_viewer
            FROM follows f 
            JOIN users u ON f.following_id = u.id 
            WHERE f.follower_id = ? 
            ORDER BY u.display_name ASC";
} else {
    $title = "Followers";
    $sql = "SELECT u.*, 
            (SELECT 1 FROM follows WHERE follower_id = u.id AND following_id = ?) as follows_viewer
            FROM follows f 
            JOIN users u ON f.follower_id = u.id 
            WHERE f.following_id = ? 
            ORDER BY u.display_name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$viewer_id, $id]);
$userList = $stmt->fetchAll();
?>

<style>
.sidebar-sticky { position: -webkit-sticky; position: sticky; top: 20px; height: fit-content; }
.sidebar-sticky h2 { word-wrap: break-word; overflow-wrap: break-word; max-width: 100%; line-height: 1.2; font-size: 22px; }
.sidebar-sticky .muted { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.user-item { padding: 15px 10px; border-bottom: 1px solid #eee; display: block; text-decoration: none !important; color: inherit; }
.user-item:hover { background: #fdfdfd; }
.user-item .info { overflow: hidden; }
.user-item h3 { margin: 0; font-size: 15px; line-height: 1.2; }
.user-item .bio { margin: 4px 0 0; color: #666; font-size: 13px; line-height: 1.4; }
.label.follows-you { font-size: 10px; background: #eee; color: #777; text-shadow: none; vertical-align: middle; margin-left: 5px; }

</style>

<div class="container">
    <div class="page-header">
        <h1><?= e($displayName) ?> <small> <?= $title ?> </small></h1>
    </div>

    <div class="row">
    <div class="span4 sidebar-sticky">
        <div style="text-align: center; padding-bottom: 20px;">
            <img src="/images/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="margin: 0 auto 15px; display: block; width: 160px; height: 160px;">
            <h2 style="margin-bottom: 5px; line-height: 1.2;">
                <?=htmlspecialchars($displayName)?><?php if(!empty($user['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:20px; vertical-align:middle;display:inline-block;" title="Partner"><?php endif; ?><?php if(!empty($user['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:20px; vertical-align:middle;display:inline-block;" title="Staff"><?php endif; ?>
                <span class="muted" style="font-size:14px; margin-left:5px; font-weight:normal;">@<?=htmlspecialchars($user['username'])?></span>
            </h2>
            <p class="help-block"><?=htmlspecialchars($user['bio'] ?? 'No bio yet.')?></p>
            
        </div>
            <ul class="unstyled" style="padding: 0 10px;">
                <li style="padding: 8px 0; border-top: 1px solid #eee;">
                    <a href="/u/<?=$id?>" style="display: block; text-decoration: none; color: inherit;">
                        <strong>Posts</strong> <span class="label  pull-right"><?=$totalPosts?></span>
                    </a>
                </li>
                <li style="border-top: 1px solid #eee;" class="<?= $type == 'followers' ? 'active-list' : '' ?>">
                    <a href="/u/<?=$id?>/followers" style="display: block; padding: 8px 0; text-decoration: none; color: inherit;">
                        <strong>Followers</strong> <span class="label <?= $type == 'followers' ? 'notice' : '' ?> pull-right"><?=$totalFollowers?></span>
                    </a>
                </li>
                <li style="border-top: 1px solid #eee; border-bottom: 1px solid #eee;" class="<?= $type == 'following' ? 'active-list' : '' ?>">
                    <a href="/u/<?=$id?>/following" style="display: block; padding: 8px 0; text-decoration: none; color: inherit;">
                        <strong>Following</strong> <span class="label <?= $type == 'following' ? 'notice' : '' ?> pull-right"><?=$totalFollowing?></span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="span12">
   


            <div class="tab-content">
                <?php if (!$userList): ?>
                    <div class="alert-message info" style="margin-top:20px;">
                        <p>No users found in this list :( </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($userList as $u): 
                        $u_name = !empty($u['display_name']) ? $u['display_name'] : $u['username'];
                    ?>
                        <div class="user-item">
                            <div class="row">
                                <div class="span1">
                                    <a href="/u/<?= $u['id'] ?>">
                                        <img src="/images/avatars/<?= htmlspecialchars($u['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:48px;height:48px;">
                                    </a>
                                </div>
                                <div class="span8 info">
                                    <h3>
                                        <a href="/u/<?= $u['id'] ?>" style="color: #404040; text-decoration: none;">
                                            <?= !empty($u['display_name']) ? e($u['display_name']) : e($u['username']) ?><?php if(!empty($u['partner'])): ?> <img src="/images/misc/partner.png" alt="Partner" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?><?php if(!empty($u['admin'])): ?> <img src="/images/misc/staff.png" alt="Staff" style="height:16px; vertical-align:middle;display:inline-block;"><?php endif; ?>
                                        </a>
                                        <span class="muted" style="font-weight: normal; font-size: 13px;">@<?= e($u['username']) ?></span>
                                        <?php if($u['follows_viewer']): ?>
                                            <span class="label follows-you">Follows you</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="bio"><?= e($u['bio'] ?: 'No bio available.') ?></p>
                                </div>
                                <div class="span2" style="text-align: right;">
                                    <a href="/u/<?= $u['id'] ?>" class="btn small">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php"; ?>