<?php
require_once __DIR__ . '/../database/config.php';

function getAllUsers() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, username, email, avatar, admin, is_verified, partner FROM users ORDER BY username ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function displayNameBadge($displayName, $username, $isPartner = false, $isStaff = false, $showBadges = true, $badgeSize = 16) {
    $name = htmlspecialchars($displayName);
    $badges = '';
    if ($showBadges) {
        if ($isPartner) {
            $badges .= '<img src="/images/misc/partner.png" alt="Partner" style="height:' . $badgeSize . 'px; vertical-align:middle; display:inline-block;" title="Partner">';
        }
        if ($isStaff) {
            $badges .= '<img src="/images/misc/staff.png" alt="Staff" style="height:' . $badgeSize . 'px; vertical-align:middle; display:inline-block;" title="Staff">';
        }
    }
    if ($badges) {
        return $name . ' ' . $badges;
    }
    return $name;
}

function displayUsernameBadge($username, $isPartner = false, $isStaff = false, $showBadges = false) {
    $html = '@' . htmlspecialchars($username);
    return $html;
}

function searchUsers($term) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, avatar, admin FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY username ASC");
    $like = "%" . addcslashes($term, '%_') . "%";
    $stmt->execute([$like, $like]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function toggleAdmin($id){
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET admin = 1 - admin WHERE id = ?");
    $stmt->execute([$id]);
}

function deleteUser($id){
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
}

function banUser($user_id, $reason){
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO bans (user_id, reason) VALUES (?,?)");
    $stmt->execute([$user_id, $reason]);
}

function unbanUser($user_id){
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM bans WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

function getBannedUsers(){
    global $pdo;
    $stmt = $pdo->query("SELECT b.user_id, u.username, b.reason FROM bans b JOIN users u ON b.user_id = u.id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserById($id){
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByUsername($username){
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateProfile($id, $bio){
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
    return $stmt->execute([$bio, $id]);
}
