<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/users.php';
require_once __DIR__ . '/../functions/messages.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) {
    header("Location: /");
    exit;
}
$target_id = (int)($_REQUEST['user_id'] ?? 0);
if(!$target_id) die("No user specified.");
$targetUser = getUserById($target_id); 
if (!$targetUser) die("User not found.");
$isSelf = ($target_id === (int)$_SESSION['user_id']);
$isTargetStaff = (isset($targetUser['admin']) && (int)$targetUser['admin'] === 1);
$canModify = !$isSelf && !$isTargetStaff;

require_once __DIR__ . '/../functions/security.php';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setMessage("error", "CSRF validation failed.");
        header("Location: users.php?user_id=$target_id");
        exit;
    }
    if ($isSelf) {
        setMessage("error", "Cannot ban self.");
    } elseif ($isTargetStaff) {
        setMessage("error", "Cannot ban another staff member.");
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if(isset($_POST['ban'])){
            banUser($target_id, $reason);
            setMessage("success","User banned.");
        } elseif(isset($_POST['unban'])){
            unbanUser($target_id);
            setMessage("success","User unbanned.");
        }
    }
    header("Location: users.php?user_id=$target_id");
    exit;
} 

$bannedUsers = getBannedUsers();
$isBanned = false;
$banReason = '';
foreach($bannedUsers as $b){
    if((int)$b['user_id'] === $target_id){
        $isBanned = true;
        $banReason = $b['reason'] ?? '';
        break;
    }
}

require_once "../header.php";
?>

<div class="container" style="margin-top: 20px;">
    <div class="row">
        <div class="span10 offset3">
            
            <div class="well" style="padding: 0; background-color: #fff; border: 1px solid #ccc; -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3); -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3); box-shadow: 0 3px 7px rgba(0,0,0,0.3);">
                
                <form method="post" style="margin-bottom: 0;">
                    <div class="modal-header">
                        <h3><?= $isBanned ? 'Unban' : 'Ban' ?> User: <?= htmlspecialchars($targetUser['username']) ?></h3>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="span2" style="text-align: center; border-right: 1px solid #eee;">
                                <img src="/images/avatars/<?= htmlspecialchars($targetUser['avatar'] ?? 'default.png') ?>" class="thumbnail" width="80" style="margin-bottom: 10px; display: inline-block;">
                                <p style="margin: 0;"><strong>ID: <?= $target_id ?></strong></p>
                            </div>

                            <div class="span7">
                                <p style="margin-bottom: 10px;">
                                    <strong>Status:</strong> 
                                    <?= $isBanned ? '<span class="label important">BANNED</span>' : '<span class="label success">ACTIVE</span>' ?>
                                </p>
                                
                                <input type="hidden" name="user_id" value="<?= $target_id ?>">

                                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
<?php if(!$isBanned): ?>
                                    <div style="margin-top: 5px;">
                                        <strong>Reason for ban:</strong><br>
                                        <input type="text" name="reason" 
       style="width: 98%; height: 30px; margin-top: 5px; padding: 4px;" 
       required <?= !$canModify ? 'disabled' : '' ?> 
       placeholder="<?= $isTargetStaff ? 'Staff members cannot be banned.' : 'Enter violation details...' ?>">
                                               <span class="help-block" style="margin-top: 5px; color: #777; font-size: 11px;">
                                            This is public and WILL be shown to everyone.
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 5px;">
                                        <strong>Current Ban Reason:</strong>
                                        <blockquote style="margin: 5px 0 0 0; padding: 5px 10px; border-left: 3px solid #eee;">
                                            <p style="font-style: italic; font-size: 13px;"><?= htmlspecialchars($banReason) ?></p>
                                        </blockquote>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer" style="text-align: right; background-color: #f5f5f5; border-top: 1px solid #ddd; padding: 14px 15px 15px;">
                        <?php if(!$isBanned): ?>
                            <button type="submit" name="ban" class="btn danger" <?= !$canModify ? 'disabled' : '' ?>>
    <?php 
        if ($isSelf) echo 'Cannot Ban Self';
        elseif ($isTargetStaff) echo 'Staff Protected';
        else echo 'Execute Ban';
    ?>
</button>
                        <?php else: ?>
                            <button type="submit" name="unban" class="btn primary">Lift Ban</button>
                        <?php endif; ?>
                        <a href="users.php?user_id=<?= $target_id ?>" class="btn secondary">Cancel</a>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</div>

<?php 
require_once "footer.php"; 
ob_end_flush();
?>