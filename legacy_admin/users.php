<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';
require_once __DIR__ . '/../backend/misc/webhook.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) {
    header("Location: /");
    exit;
}

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$adminUsername = $stmt->fetchColumn() ?: 'Unknown Admin';

$adminCsrf = generateCSRF();
$searchQuery = trim($_GET['search'] ?? '');
$tab = $_GET['tab'] ?? 'profile';
$filter = $_GET['filter'] ?? 'newest';
$selectedUserId = (int)($_GET['user_id'] ?? 0);
$incomingCsrf = $_GET['csrf'] ?? '';
if (!empty($_GET['verify_user']) || !empty($_GET['unverify_user']) || !empty($_GET['reset_username']) || !empty($_GET['toggle_admin']) || !empty($_GET['login_as_user']) || !empty($_GET['restore_admin']) || !empty($_GET['delete_post']) || !empty($_GET['delete_reply']) || !empty($_GET['unban_user'])) {
    if (!verifyCSRF($incomingCsrf)) {
        setMessage("error", "CSRF validation failed.");
        header("Location: users.php");
        exit;
    }
}

if (isset($_GET['verify_user'])) {
    $targetId = (int)$_GET['verify_user'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUsername = $stmt->fetchColumn();
    
    $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?")->execute([$targetId]);
    setMessage("success", "User has been manually verified!");
    
    logAdminAction('Verify User', $targetUsername ?? "ID: $targetId", $adminUsername, "User email verified manually");
    
    header("Location: users.php?user_id=$targetId");
    exit;
}

if (isset($_GET['unverify_user'])) {
    $targetId = (int)$_GET['unverify_user'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUsername = $stmt->fetchColumn();
    
    $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([$targetId]);
    setMessage("success", "User has been unverified!");
    
    logAdminAction('Unverify User', $targetUsername ?? "ID: $targetId", $adminUsername, "User email unverified");
    
    header("Location: users.php?user_id=$targetId");
    exit;
}

if(isset($_GET['reset_username'])){
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $oldUsername = $stmt->fetchColumn();
    
    $newName = "UsernameReset$targetId";
    $pdo->prepare("UPDATE users SET username = ?, display_name = ? WHERE id = ?")->execute([$newName, $newName, $targetId]);
    setMessage("success", "Username has been reset to $newName.");
    
    logAdminAction('Reset Username', $oldUsername ?? "ID: $targetId", $adminUsername, "Reset from '$oldUsername' to '$newName'");
    
    header("Location: users.php?user_id=$targetId&search=".urlencode($searchQuery));
    exit;
}

if(isset($_GET['toggle_admin'])){
    $targetId = (int)$_GET['toggle_admin'];
    if($targetId != $_SESSION['user_id']){
        $stmt = $pdo->prepare("SELECT username, admin FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->prepare("UPDATE users SET admin=1-admin WHERE id=?")->execute([$targetId]);
        setMessage("success","User role updated.");
        
        $action = $targetUser['admin'] ? 'Removed Admin' : 'Added Admin';
        logAdminAction('Admin Toggle', $targetUser['username'] ?? "ID: $targetId", $adminUsername, $action);
    }
    header("Location: users.php?user_id=$targetId&search=".urlencode($searchQuery));
    exit;
}

if(isset($_GET['login_as_user'])){
    $targetId = (int)$_GET['login_as_user'];
    if($targetId != $_SESSION['user_id']){
        $stmt = $pdo->prepare("SELECT username, admin FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$targetUser){
            setMessage("error", "User not found.");
            header("Location: users.php");
            exit;
        }
        
        if($targetUser['admin']){
            setMessage("error", "Cannot login as a staff member.");
            header("Location: users.php?user_id=$targetId");
            exit;
        }
        
        logAdminAction('Login As User', $targetUser['username'], $adminUsername, "Admin logged in as this user");
        
        $_SESSION['original_admin_id'] = $_SESSION['user_id'];
        $_SESSION['original_admin_user'] = $_SESSION['username'];
        $_SESSION['original_admin_avatar'] = $_SESSION['avatar'];
        $_SESSION['user_id'] = $targetId;
        $_SESSION['admin'] = 0;
        
        header("Location: /");
        exit;
    }
}

if(isset($_GET['restore_admin'])){
    if(isset($_SESSION['original_admin_id'])){
        logAdminAction('Restore Admin', $_SESSION['username'] ?? "Unknown", $adminUsername, "Returned to admin account");
        
        $_SESSION['user_id'] = $_SESSION['original_admin_id'];
        $_SESSION['username'] = $_SESSION['original_admin_user'];
        $_SESSION['avatar'] = $_SESSION['original_admin_avatar'];
        $_SESSION['admin'] = 1;
        unset($_SESSION['original_admin_id']);
        unset($_SESSION['original_admin_user']);
        unset($_SESSION['original_admin_avatar']);
    }
    header("Location: /legacy_admin/users.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])){
    $targetId = (int)$_POST['edit_user'];
    #$newEmail = trim($_POST['email'] ?? '');
    $newUsername = trim($_POST['username'] ?? '');
    $newDisplayName = trim($_POST['display_name'] ?? '');
    $newBio = trim($_POST['bio'] ?? '');
    
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $changes = [];
    #if($newEmail !== $oldUser['email']) $changes[] = "email: {$oldUser['email']} -> $newEmail";
    if($newUsername !== $oldUser['username']) $changes[] = "username: {$oldUser['username']} -> $newUsername";
    
    #$stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id != ?");
    #$stmt->execute([$newEmail, $targetId]);
    #if($stmt->fetch() && $newEmail !== $oldUser['email']){
    #    setMessage("error", "Email already in use by another user.");
    #} else {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $targetId]);
        if($stmt->fetch() && $newUsername !== $oldUser['username']){
            setMessage("error", "Username already in use by another user.");
        } else {
            $partner = isset($_POST['partner']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE users SET username = ?, display_name = ?, bio = ?, partner = ? WHERE id = ?");
            $stmt->execute([$newUsername, $newDisplayName, $newBio, $partner, $targetId]);
            setMessage("success", "User updated successfully.");
            
            logAdminAction('Edit User', $oldUser['username'], $adminUsername, implode(", ", $changes) ?: "No field changes");
        }
    #}
    
    header("Location: users.php?user_id=$targetId&search=".urlencode($searchQuery));
    exit;
}

if(isset($_GET['delete_post'])){
    $post_id = (int)$_GET['delete_post'];
    $stmt = $pdo->prepare("SELECT user_id, content, image, video FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $postData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($postData) {
        if (!empty($postData['image'])) {
            $imgPath = __DIR__ . '/../images/posts/' . $postData['image'];
            if (file_exists($imgPath)) unlink($imgPath);
        }
        if (!empty($postData['video'])) {
            $vidPath = __DIR__ . '/../videos/posts/' . $postData['video'];
            if (file_exists($vidPath)) unlink($vidPath);
        }
    }
    
    $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$post_id]);
    setMessage("success","Post deleted.");
    
    $contentPreview = $postData ? substr($postData['content'], 0, 100) : "ID: $post_id";
    logAdminAction('Delete Post', "Post ID: $post_id", $adminUsername, "Content: $contentPreview");
    
    header("Location: users.php?user_id=$selectedUserId&tab=posts&search=".urlencode($searchQuery));
    exit;
}

if(isset($_GET['delete_reply'])){
    $reply_id = (int)$_GET['delete_reply'];
    $stmt = $pdo->prepare("SELECT user_id, content FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $replyData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->prepare("DELETE FROM replies WHERE id=?")->execute([$reply_id]);
    setMessage("success","Reply deleted.");
    
    $contentPreview = $replyData ? substr($replyData['content'], 0, 100) : "ID: $reply_id";
    logAdminAction('Delete Reply', "Reply ID: $reply_id", $adminUsername, "Content: $contentPreview");
    
    header("Location: users.php?user_id=$selectedUserId&tab=replies&search=".urlencode($searchQuery));
    exit;
}

if(isset($_GET['unban_user'])){
    $targetId = (int)$_GET['unban_user'];
    
    $stmt = $pdo->prepare("SELECT username, admin FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();
    $targetUsername = $targetUser['username'] ?? null;
    
    $isTargetStaff = (isset($targetUser['admin']) && (int)$targetUser['admin'] === 1);
    
    if ($isTargetStaff) {
        setMessage("error", "Cannot unban a staff member.");
    } else {
        $pdo->prepare("DELETE FROM bans WHERE user_id = ?")->execute([$targetId]);
        setMessage("success", "User unbanned.");
        logAdminAction('Unban', $targetUsername ?? "ID: $targetId", $adminUsername, "Ban lifted");
    }
    
    header("Location: users.php?user_id=$targetId");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user_id'])){
    $targetId = (int)$_POST['ban_user_id'];
    $reason = trim($_POST['reason'] ?? 'No reason provided');
    
    $stmt = $pdo->prepare("SELECT username, admin FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();
    $targetUsername = $targetUser['username'] ?? null;
    
    $isSelf = ($targetId === (int)$_SESSION['user_id']);
    $isTargetStaff = (isset($targetUser['admin']) && (int)$targetUser['admin'] === 1);
    
    if ($isSelf) {
        setMessage("error", "Cannot ban yourself.");
    } elseif ($isTargetStaff) {
        setMessage("error", "Cannot ban another staff member.");
    } else {
        $pdo->prepare("INSERT INTO bans (user_id, reason, banned_at) VALUES (?, ?, NOW())")->execute([$targetId, $reason]);
        setMessage("success", "User banned.");
        logAdminAction('Ban', $targetUsername ?? "ID: $targetId", $adminUsername, "Reason: $reason");
    }
    
    header("Location: users.php?user_id=$targetId");
    exit;
}

function getIPDetails($ip) {
    if (!$ip || $ip == '127.0.0.1' || $ip == '::1') return "Localhost";
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,proxy", false, $ctx);
    $details = json_decode($json, true);
    return ($details && $details['status'] == 'success') 
        ? "{$details['city']}, {$details['country']} ({$details['isp']})" . ($details['proxy'] ? " [VPN]" : "")
        : "Unknown Location";
}
$selectedUser = null;
if($selectedUserId){
    $stmt = $pdo->prepare("SELECT u.*, 
        (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as follower_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count 
        FROM users u WHERE u.id=?");
    $stmt->execute([$selectedUserId]);
    $selectedUser = $stmt->fetch();
}
$sql = "SELECT u.*, (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as follower_count FROM users u";
$where = [];
$params = [];
if($searchQuery){
    $searchQueryEscaped = addcslashes($searchQuery, '%_');
    $where[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchQueryEscaped%"; $params[] = "%$searchQueryEscaped%";
}
if($filter == 'verified') $where[] = "u.is_verified = 1";
if($filter == 'unverified') $where[] = "u.is_verified = 0";
if($filter == 'staff') $where[] = "u.admin = 1";
if($filter == 'partner') $where[] = "u.partner = 1";
if($filter == 'no_birthdate') $where[] = "(u.birthdate IS NULL OR u.birthdate = '')";

if(!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
if($filter == 'popular') $sql .= " ORDER BY follower_count DESC";
elseif($filter == 'oldest') $sql .= " ORDER BY u.id ASC";
else $sql .= " ORDER BY u.id DESC";

$sql .= " LIMIT 100";
try {
    $users = $pdo->prepare($sql);
    $users->execute($params);
    $userList = $users->fetchAll();
} catch (PDOException $e) {
    $userList = [];
}
$userList = $userList ?: [];

$bannedMap = [];
try {
    $bannedMap = $pdo->query("SELECT user_id, reason FROM bans")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // table might not exist
}

require_once "header.php";
?>

<style>
    .post-item { padding: 15px; border-bottom: 1px solid #eee; background: #fff; }
    .post-layout { display: flex; width: 100%; }
    .post-avatar { width: 58px; flex-shrink: 0; }
    .post-body { flex-grow: 1; padding-left: 10px; }
    .post-meta { font-size: 14px; margin-bottom: 5px; color: #404040; }
    .post-content { word-wrap: break-word; font-size: 14px; line-height: 1.5; color: #333; margin-bottom: 10px; }
    .post-image { max-width: 100%; max-height: 400px; display: block; margin-top: 10px; border: 1px solid #ddd; }
    .muted { color: #999; font-weight: normal; }
    .border-container { border: 1px solid #ddd; overflow: hidden; background: #fff; }
    .ban-status-active { color: #e0245e; font-weight: bold; }
    .ban-status-banned { color: #17bf63; font-weight: bold; }
</style>

<div class="container">

    <form class="form-search" method="get" style="margin-bottom:20px;">
        <input type="text" class="input-xxlarge search-query" name="search" placeholder="Search users..." value="<?=htmlspecialchars($searchQuery)?>">
        <button type="submit" class="btn primary">Search</button>
        <?php if($searchQuery || $selectedUserId): ?>
            <a href="users.php" class="btn">Clear</a>
        <?php endif; ?>
    </form>

    <?php if($selectedUser): ?>
        <?php $isBanned = isset($bannedMap[$selectedUser['id']]); ?>
        <div class="alert-message block-message info">
            <p><strong>Managing: <?=htmlspecialchars($selectedUser['username'])?></strong> (#<?=$selectedUser['id']?>)</p>
            <div class="alert-actions">
                <?php if($isBanned): ?>
                    <a href="?unban_user=<?=$selectedUser['id']?>&user_id=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn small success" onclick="return confirm('Unban this user?')">Unban</a>
                <?php else: ?>
                    <a href="?user_id=<?=$selectedUser['id']?>&tab=ban" class="btn small danger">Ban User</a>
                <?php endif; ?>
                <a href="?user_id=<?=$selectedUser['id']?>&tab=alts" class="btn small primary">Track Alts</a>
                <a href="?toggle_admin=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn small">Toggle Admin</a>
                <a href="?reset_username=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn small warning" onclick="return confirm('Reset this username?')">Reset Name</a>
                <?php if($selectedUser['is_verified']): ?>
                    <a href="?unverify_user=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn small info">Unverify Email</a>
                <?php else: ?>
                    <a href="?verify_user=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn small info">Verify Email</a>
                <?php endif; ?>
                <?php if($selectedUser['id'] != $_SESSION['user_id']): ?>
                    <a href="?login_as_user=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn small" onclick="return confirm('Login as this user? You will be logged in as <?=htmlspecialchars($selectedUser['username'], ENT_QUOTES, 'UTF-8')?>')">Login As User</a>
                <?php endif; ?>
            </div>
        </div>

        <ul class="tabs">
            <li class="<?= $tab=='profile'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=profile&search=<?=urlencode($searchQuery)?>">Profile</a></li>
            <li class="<?= $tab=='edit'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=edit&search=<?=urlencode($searchQuery)?>">Edit</a></li>
            <li class="<?= $tab=='posts'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=posts&search=<?=urlencode($searchQuery)?>">Posts</a></li>
            <li class="<?= $tab=='replies'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=replies&search=<?=urlencode($searchQuery)?>">Replies</a></li>
            <li class="<?= $tab=='followers'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=followers&search=<?=urlencode($searchQuery)?>">Followers (<?=$selectedUser['follower_count']?>)</a></li>
            <li class="<?= $tab=='following'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=following&search=<?=urlencode($searchQuery)?>">Following (<?=$selectedUser['following_count']?>)</a></li>
            <li class="<?= $tab=='ipinfo'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=ipinfo&search=<?=urlencode($searchQuery)?>">IP Info</a></li>
            <li class="<?= $tab=='alts'?'active':'' ?>"><a href="?user_id=<?=$selectedUser['id']?>&tab=alts&search=<?=urlencode($searchQuery)?>">Alt Accounts</a></li>
        </ul>

        <div style="margin-top:20px;">
            <?php if($tab=='profile'): ?>
                <table class="bordered-table zebra-striped">
                    <tr><th width="150">Username</th><td><strong><?=htmlspecialchars($selectedUser['username'])?></strong></td></tr>
                    <tr><th>Account</th><td><?=htmlspecialchars($selectedUser['email'])?></td></tr>
                    <tr><th>Display Name</th><td><?=htmlspecialchars($selectedUser['display_name'] ?? '')?></td></tr>
                    <tr><th>Bio</th><td><?=nl2br(htmlspecialchars($selectedUser['bio'] ?? ''))?></td></tr>
                    <tr><th>Joined</th><td><?=htmlspecialchars($selectedUser['created_at'])?></td></tr>
                    <tr><th>Birthdate</th><td>
                        <?php if(!empty($selectedUser['birthdate']) && $selectedUser['birthdate'] !== '0000-00-00'): ?>
                            <?=htmlspecialchars($selectedUser['birthdate'])?>
                            <?php 
                            $bdate = strtotime($selectedUser['birthdate']);
                            $age = ($bdate !== false) ? (int)((time() - $bdate) / (365.25 * 24 * 60 * 60)) : 0;
                            if ($age > 0 && $age < 200):
                            ?>
                            <span class="muted">(<?=$age?> years old)</span>
                            <?php endif; ?>
                            <?php if($age > 0 && $age < 14): ?>
                                <span class="label important">UNDERAGE</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="label important">NOT SET</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th>Status</th><td>
                        <?=($selectedUser['is_verified']) ? '<span class="label success">Verified</span>' : '<span class="label important">Unverified</span>'?>
                        <?=($selectedUser['admin']) ? '<span class="label notice">Staff</span>' : ''?>
                        <?php if(isset($bannedMap[$selectedUser['id']])): ?>
                            <span class="label important">Banned</span>
                        <?php else: ?>
                        <?php endif; ?>
                    </td></tr>
                </table>

            <?php elseif($tab=='edit'): ?>
                <div class="border-container">
                    <div style="padding: 20px;">
                        <h3>Edit User: <?= htmlspecialchars($selectedUser['username']) ?></h3>
                        
                        <form method="POST" style="margin-top: 20px;">
                            <input type="hidden" name="edit_user" value="<?= (int)$selectedUser['id'] ?>">
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;"><strong>Username:</strong></label>
                                <input type="text" name="username" value="<?= htmlspecialchars($selectedUser['username'] ?? '') ?>" style="width: 300px; padding: 6px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;"><strong>Email:</strong></label>
                                <input type="email" name="email" value="<?= htmlspecialchars($selectedUser['email'] ?? '') ?>" style="width: 300px; padding: 6px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;"><strong>Display Name:</strong></label>
                                <input type="text" name="display_name" value="<?= htmlspecialchars($selectedUser['display_name'] ?? '') ?>" style="width: 300px; padding: 6px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;"><strong>Bio:</strong></label>
                                <textarea name="bio" rows="4" style="width: 400px; padding: 6px;"><?= htmlspecialchars($selectedUser['bio'] ?? '') ?></textarea>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="partner" value="1" <?= !empty($selectedUser['partner']) ? 'checked' : '' ?>>
                                    <strong>Grant Partner Status</strong>
                                </label>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn primary large" onclick="return confirm('Save changes to this user?')">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif($tab=='ban'): ?>
                <?php 
                $isBanned = isset($bannedMap[$selectedUser['id']]);
                $banReason = $bannedMap[$selectedUser['id']] ?? '';
                ?>
                <div class="border-container">
                    <div style="padding: 20px;">
                        <h3><?= $isBanned ? 'Unban' : 'Ban' ?> User: <?= htmlspecialchars($selectedUser['username']) ?></h3>
                        
                        <div style="margin-top: 20px;">
                            <p><strong>Status:</strong> 
                                <?php if($isBanned): ?>
                                    <span class="ban-status-active">BANNED</span>
                                <?php else: ?>
                                    <span class="ban-status-banned">ACTIVE</span>
                                <?php endif; ?>
                            </p>
                            
                            <?php if($isBanned): ?>
                                <p><strong>Ban Reason:</strong></p>
                                <blockquote style="background: #f9f9f9; padding: 10px; border-left: 3px solid #e0245e;">
                                    <?= htmlspecialchars($banReason) ?>
                                </blockquote>
                                                            <?php else: ?>
                                <form method="POST" style="margin-top: 20px;">
                                    <input type="hidden" name="ban_user_id" value="<?= $selectedUser['id'] ?>">
                                    <label><strong>Reason:</strong></label>
                                    <input type="text" name="reason" placeholder="Enter violation details..." required style="width: 100%; max-width: 400px; height: 30px;">
                                    <p class="muted" style="font-size: 12px; margin-top: 5px;">This is public and WILL be shown to everyone.</p>
                                    <button type="submit" class="btn danger large" onclick="return confirm('Ban this user?')">Ban User</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif($tab=='posts'): ?>
                <?php
                $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin FROM posts p JOIN users u ON p.user_id = u.id WHERE p.user_id=? ORDER BY p.created_at DESC");
                $stmt->execute([$selectedUser['id']]);
                $posts = $stmt->fetchAll();
                ?>
                <div class="border-container">
                    <?php foreach($posts as $p): ?>
                        <div class="post-item">
                            <div class="post-layout">
                                <div class="post-avatar">
                                    <img src="/images/avatars/<?= htmlspecialchars($p['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:48px;height:48px;">
                                </div>
                                <div class="post-body">
                                    <div class="post-meta">
                                        <strong><?= htmlspecialchars($p['display_name'] ?: $p['username']) ?></strong> 
                                        <span class="muted">@<?= htmlspecialchars($p['username']) ?> - <?= $p['created_at'] ?></span>
                                    </div>
                                    <div class="post-content"><?= nl2br(htmlspecialchars($p['content'])) ?></div>
                                    <?php if(!empty($p['image'])): ?>
                                        <a href="/images/posts/<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                                            <img src="/images/posts/<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>" class="post-image">
                                        </a>
                                    <?php endif; ?>
                                    <?php if(!empty($p['video'])): ?>
                                        <video controls style="max-width:100%; max-height:300px; display:block; margin-top:10px;">
                                            <source src="/videos/posts/<?= htmlspecialchars($p['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                                        </video>
                                    <?php endif; ?>
                                    <div style="margin-top:10px;">
                                        <a href="?delete_post=<?=$p['id']?>&user_id=<?=$selectedUser['id']?>&csrf=<?= $adminCsrf ?>" class="btn danger small" onclick="return confirm('Delete post?')">Delete Forever</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(!$posts): ?><div style="padding:20px;">No posts.</div><?php endif; ?>
                </div>

            <?php elseif($tab=='replies'): ?>
                <?php
                $stmt = $pdo->prepare("SELECT r.*, u.username, u.display_name, u.avatar FROM replies r JOIN users u ON r.user_id = u.id WHERE r.user_id=? ORDER BY r.created_at DESC");
                $stmt->execute([$selectedUser['id']]);
                $replies = $stmt->fetchAll();
                ?>
                <div class="border-container">
                    <?php foreach($replies as $r): ?>
                        <div class="post-item">
                            <div class="post-layout">
                                <div class="post-avatar">
                                    <img src="/images/avatars/<?= htmlspecialchars($r['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="thumbnail" style="width:48px;height:48px;">
                                </div>
                                <div class="post-body">
                                    <div class="post-meta">
                                        <strong><?= htmlspecialchars($r['display_name'] ?: $r['username']) ?></strong> 
                                        <span class="muted">replied on <?= $r['created_at'] ?></span>
                                    </div>
                                    <div class="post-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
                                    <?php if(!empty($r['image'])): ?>
                                        <a href="/images/replies/<?= htmlspecialchars($r['image'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                                            <img src="/images/replies/<?= htmlspecialchars($r['image'], ENT_QUOTES, 'UTF-8') ?>" class="post-image">
                                        </a>
                                    <?php endif; ?>
                                    <?php if(!empty($r['video'])): ?>
                                        <video controls style="max-width:100%; max-height:300px; display:block; margin-top:10px;">
                                            <source src="/videos/replies/<?= htmlspecialchars($r['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                                        </video>
                                    <?php endif; ?>
                                    <div style="margin-top:10px;">
                                        <a href="?delete_reply=<?=$r['id']?>&user_id=<?=$selectedUser['id']?>&tab=replies&csrf=<?= $adminCsrf ?>" class="btn danger small" onclick="return confirm('Delete reply?')">Delete Forever</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(!$replies): ?><div style="padding:20px;">No replies.</div><?php endif; ?>
                </div>

            <?php elseif($tab=='followers' || $tab=='following'): ?>
                <?php
                $stmt = ($tab == 'followers') 
                    ? $pdo->prepare("SELECT u.* FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ? ORDER BY f.id DESC")
                    : $pdo->prepare("SELECT u.* FROM follows f JOIN users u ON f.following_id = u.id WHERE f.follower_id = ? ORDER BY f.id DESC");
                $stmt->execute([$selectedUser['id']]);
                $followList = $stmt->fetchAll();
                ?>
                <table class="bordered-table zebra-striped">
                    <thead><tr><th>User</th><th>Email</th><th width="80">Action</th></tr></thead>
                    <tbody>
                        <?php foreach($followList as $f): ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($f['username'])?></strong></td>
                            <td><?=htmlspecialchars($f['email'])?></td>
                            <td><a href="?user_id=<?=$f['id']?>" class="btn small">Manage</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($followList)): ?><tr><td colspan="3" style="text-align:center;">No users found.</td></tr><?php endif; ?>
                    </tbody>
                </table>

            <?php elseif($tab=='ipinfo'): ?>
                <table class="bordered-table">
                    <tr><th width="150">Registration IP</th><td><code><?=htmlspecialchars($selectedUser['registration_ip'] ?? 'N/A')?></code><br><small><?=getIPDetails($selectedUser['registration_ip'] ?? '')?></small></td></tr>
                    <tr><th>Last Login IP</th><td><code><?=htmlspecialchars($selectedUser['last_login_ip'] ?? 'N/A')?></code><br><small><?=getIPDetails($selectedUser['last_login_ip'] ?? '')?></small></td></tr>
                    <tr><th>Security Flag</th><td>
                        <?php if(($selectedUser['registration_ip'] ?? '1') !== ($selectedUser['last_login_ip'] ?? '2')): ?>
                            <span class="label important">IP Mismatch</span>
                        <?php else: ?>
                            <span class="label success">Consistent</span>
                        <?php endif; ?>
                    </td></tr>
                </table>

            <?php elseif($tab=='alts'): ?>
                <?php
                $regIp = $selectedUser['registration_ip'] ?? '0.0.0.0';
                $lastIp = $selectedUser['last_login_ip'] ?? '0.0.0.0';
                $stmt = $pdo->prepare("SELECT id, username, email, registration_ip, last_login_ip, created_at FROM users WHERE (registration_ip = ? OR last_login_ip = ? OR registration_ip = ? OR last_login_ip = ?) AND id != ? ORDER BY id DESC");
                $stmt->execute([$regIp, $regIp, $lastIp, $lastIp, $selectedUser['id']]);
                $alts = $stmt->fetchAll();
                ?>
                <h3>Accounts with Matching IPs</h3>
                <table class="bordered-table zebra-striped">
                    <thead><tr><th>User</th><th>Reg IP</th><th>Last IP</th><th width="80">Action</th></tr></thead>
                    <tbody>
                        <?php foreach($alts as $alt): ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($alt['username'] ?? '')?></strong></td>
                            <td><code><?=htmlspecialchars($alt['registration_ip'] ?? '', ENT_QUOTES, 'UTF-8')?></code></td>
                            <td><code><?=htmlspecialchars($alt['last_login_ip'] ?? '', ENT_QUOTES, 'UTF-8')?></code></td>
                            <td><a href="?user_id=<?=(int)($alt['id'])?>" class="btn small">Manage</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($alts)): ?><tr><td colspan="4" style="text-align:center;">No linked accounts found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <ul class="tabs">
            <li class="<?=$filter=='newest'?'active':''?>"><a href="?filter=newest&search=<?=urlencode($searchQuery)?>">Newest</a></li>
            <li class="<?=$filter=='popular'?'active':''?>"><a href="?filter=popular&search=<?=urlencode($searchQuery)?>">Most Followed</a></li>
            <li class="<?=$filter=='oldest'?'active':''?>"><a href="?filter=oldest&search=<?=urlencode($searchQuery)?>">Oldest</a></li>
            <li class="<?=$filter=='verified'?'active':''?>"><a href="?filter=verified&search=<?=urlencode($searchQuery)?>">Verified</a></li>
            <li class="<?=$filter=='unverified'?'active':''?>"><a href="?filter=unverified&search=<?=urlencode($searchQuery)?>">Unverified</a></li>
            <li class="<?=$filter=='staff'?'active':''?>"><a href="?filter=staff&search=<?=urlencode($searchQuery)?>">Staff</a></li>
            <li class="<?=$filter=='partner'?'active':''?>"><a href="?filter=partner&search=<?=urlencode($searchQuery)?>">Partners</a></li>
            <li class="<?=$filter=='no_birthdate'?'active':''?>"><a href="?filter=no_birthdate&search=<?=urlencode($searchQuery)?>">No Birthdate</a></li>
        </ul>

        <table class="bordered-table zebra-striped">
            <thead>
                <tr>
                    <th width="40">ID</th>
                    <th>User / Email</th>
                    <th>Followers</th>
                    <th>Status</th>
                    <th width="80">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($userList as $u): ?>
                <tr>
                    <td>#<?=$u['id']?></td>
                    <td>
                        <strong><?=htmlspecialchars($u['username'])?></strong><br>
                        <small class="muted"><?=htmlspecialchars($u['email'])?></small>
                    </td>
                    <td><span class="label notice"><?=$u['follower_count']?></span></td>
                    <td>
                        <?php if(isset($bannedMap[$u['id']])): ?>
                            <span class="label important">Banned</span>
                        <?php elseif($u['is_verified']): ?>
                            <span class="label success">Verified</span>
                        <?php elseif(empty($u['birthdate'])): ?>
                            <span class="label important">No BD</span>
                        <?php else: 
                            $bdate = strtotime($u['birthdate']);
                            $age = ($bdate !== false) ? (int)((time() - $bdate) / (365.25 * 24 * 60 * 60)) : 0;
                            if ($age > 0 && $age < 200):
                                echo "<span class='label'>" . $age . "y</span>";
                                if($age < 14) echo " <span class='label important'>!</span>";
                            endif;
                        endif; ?>
                        <?php if($u['admin']): ?><span class="label notice">Staff</span><?php endif; ?>
                        <?php if(!empty($u['partner'])): ?><span class="label success">Partner</span><?php endif; ?>
                    </td>
                    <td><a href="?user_id=<?=$u['id']?>&search=<?=urlencode($searchQuery)?>" class="btn small">Manage</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($userList)): ?>
                    <tr><td colspan="5" style="text-align:center;">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
require_once "footer.php";
ob_end_flush();
?>
