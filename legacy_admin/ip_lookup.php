<?php
ob_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) {
    header("Location: /");
    exit;
}
$adminId = $_SESSION['user_id'];
$ipToLookup = trim($_GET['ip'] ?? '');
function isValidIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP);
}
$ipCsrf = generateCSRF();
if (isset($_POST['action']) && $_POST['action'] === 'ban') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setMessage("error", "CSRF validation failed.");
        header("Location: ip_lookup.php");
        exit;
    }
    $ip = trim($_POST['ip_address'] ?? '');
    
    if (isValidIP($ip)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO ip_bans (ip_address, banned_by) VALUES (?, ?)");
        $stmt->execute([$ip, $adminId]);
        setMessage("success", "IP $ip nuked.");
    } else {
        setMessage("error", "That ain't an IP, stop fucking around.");
    }
    header("Location: ip_lookup.php?ip=" . urlencode($ip));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'unban' && !empty($_POST['ban_id'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setMessage("error", "CSRF validation failed.");
        header("Location: ip_lookup.php");
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM ip_bans WHERE id = ?");
    $stmt->execute([(int)$_POST['ban_id']]);
    setMessage("info", "IP unbanned. They better behave.");
    header("Location: ip_lookup.php");
    exit;
}
function isPrivateIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}
$details = null;
$associatedUsers = [];
if ($ipToLookup && isValidIP($ipToLookup)) {
    if (isPrivateIP($ipToLookup)) {
        $details = ['status' => 'fail', 'message' => 'Private IPs not allowed'];
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE last_login_ip = ? OR registration_ip = ?");
        $stmt->execute([$ipToLookup, $ipToLookup]);
        $associatedUsers = $stmt->fetchAll();
        if (!in_array($ipToLookup, ['127.0.0.1', '::1'])) {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $json = @file_get_contents("http://ip-api.com/json/" . urlencode($ipToLookup) . "?fields=status,country,city,isp,proxy,hosting", false, $ctx);
            $details = json_decode($json, true);
        }
    }
}
$stmt = $pdo->query("SELECT b.*, u.username as admin_name FROM ip_bans b LEFT JOIN users u ON b.banned_by = u.id ORDER BY b.created_at DESC");
$bannedList = $stmt->fetchAll();

require_once "header.php";
?>

<div class="container">

    <div class="well">
        <form method="get" class="form-search" style="margin-bottom:0;">
            <input type="text" name="ip" class="input-xxlarge search-query" placeholder="Paste an IP here..." value="<?= htmlspecialchars($ipToLookup) ?>">
            <button type="submit" class="btn primary">Scan IP</button>
            <?php if($ipToLookup): ?>
                <a href="ip_lookup.php" class="btn">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($ipToLookup): ?>
        <?php if (!isValidIP($ipToLookup)): ?>
            <div class="alert-message error"><p><?=htmlspecialchars($ipToLookup)?> is NOT an IP. </p></div>
        <?php else: ?>
            <div class="row">
                <div class="span8">
                    <table class="bordered-table">
                        <tr><th width="120">Type</th><td><?= strpos($ipToLookup, ':') !== false ? 'IPv6' : 'IPv4' ?></td></tr>
                        <?php if ($details && $details['status'] === 'success'): ?>
                            <tr><th>ISP</th><td><?= htmlspecialchars($details['isp']) ?></td></tr>
                            <tr><th>Location</th><td><?= htmlspecialchars($details['city'] . ', ' . $details['country']) ?></td></tr>
                            <tr><th>Status</th><td>
                                <?php if($details['proxy']): ?><span class="label important">VPN/PROXY DETECTED</span><?php endif; ?>
                                <?php if($details['hosting']): ?><span class="label warning">DATACENTER</span><?php endif; ?>
                                <?php if(!$details['proxy'] && !$details['hosting']): ?><span class="label success">CLEAN IP</span><?php endif; ?>
                            </td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="span8">
                    <h3>Linked Accounts</h3>
                    <?php if ($associatedUsers): ?>
                        <ul class="unstyled">
                        <?php foreach($associatedUsers as $au): ?>
                            <li><a href="users.php?user_id=<?=$au['id']?>" class="btn small">View</a> <strong><?=htmlspecialchars($au['username'])?></strong></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">No accounts found on this IP.</p>
                    <?php endif; ?>
                    
                    <form method="post" style="margin-top:15px;">
                        <input type="hidden" name="csrf_token" value="<?= $ipCsrf ?>">
                        <input type="hidden" name="action" value="ban">
                        <input type="hidden" name="ip_address" value="<?=htmlspecialchars($ipToLookup)?>">
                        <button type="submit" class="btn danger large" onclick="return confirm('Ban this IP?')">ban ip</button>
                    </form>
                </div>
            </div>
            <hr>
        <?php endif; ?>
    <?php endif; ?>

    <h3>Active Bans</h3>
    <table class="bordered-table zebra-striped">
        <thead>
            <tr>
                <th>IP Address</th>
                <th>Banned By</th>
                <th>Date</th>
                <th width="100">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bannedList)): ?>
                <tr><td colspan="4" style="text-align:center;">List is empty.</td></tr>
            <?php else: ?>
                <?php foreach ($bannedList as $ban): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($ban['ip_address']) ?></code></td>
                        <td><?= htmlspecialchars($ban['admin_name'] ?? 'System') ?></td>
                        <td><?= date('M j, Y', strtotime($ban['created_at'])) ?></td>
                        <td>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= $ipCsrf ?>">
                                <input type="hidden" name="action" value="unban">
                                <input type="hidden" name="ban_id" value="<?= $ban['id'] ?>">
                                <button type="submit" class="btn small">Unban</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
require_once "footer.php"; 
ob_end_flush();
?>