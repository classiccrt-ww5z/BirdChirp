<?php
require_once "database/config.php";
require_once "functions/auth.php";
require_once "functions/posts.php";
require_once "functions/users.php";
require_once "functions/security.php";
requireLogin();

$page_title = "Export Data";
$uid = $_SESSION['user_id'];
$csrf = generateCSRF();

if (isset($_GET['download']) && isset($_GET['csrf']) && verifyCSRF($_GET['csrf'])) {
    $data = [
        'exported_at' => date('Y-m-d H:i:s'),
        'user' => [],
        'posts' => [],
        'likes' => [],
        'followers' => [],
        'following' => [],
    ];

    $stmt = $pdo->prepare("SELECT username, display_name, bio, avatar, created_at FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $data['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, content, image, video, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $data['posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT p.id, p.content, p.user_id as author_id, u.username as author_username, p.created_at FROM likes l JOIN posts p ON l.post_id = p.id JOIN users u ON p.user_id = u.id WHERE l.user_id = ?");
    $stmt->execute([$uid]);
    $data['likes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT u.id, u.username, u.display_name FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = ?");
    $stmt->execute([$uid]);
    $data['followers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT u.id, u.username, u.display_name FROM follows f JOIN users u ON f.following_id = u.id WHERE f.follower_id = ?");
    $stmt->execute([$uid]);
    $data['following'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT p.id, p.content, p.user_id as original_author_id, u.username as original_author_username, r.created_at FROM retweets r JOIN posts p ON r.post_id = p.id JOIN users u ON p.user_id = u.id WHERE r.user_id = ?");
    $stmt->execute([$uid]);
    $data['retweets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="birdchirp-data-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once "header.php";
?>
<style>
.export-container { max-width: 600px; margin: 40px auto; text-align: center; }
.export-container h2 { margin-bottom: 20px; }
.export-container p { color: #666; margin-bottom: 20px; }
</style>
<div class="container">
    <div class="export-container">
        <h2>Export Your Data</h2>
        <p>Download a JSON file containing your posts, likes, followers, following, and retweets.</p>
        <a href="/export.php?download=1&csrf=<?= $csrf ?>" class="btn primary large">Download Data</a>
        <p style="margin-top:20px; font-size:12px; color:#999;">This may include data you've deleted from your profile.</p>
    </div>
</div>
<?php require_once "footer.php"; ?>
