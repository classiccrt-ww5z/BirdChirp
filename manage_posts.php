<?php 
require_once "header.php"; 
requireLogin(); 
require_once "functions/users.php";
require_once "functions/posts.php"; 
$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'posts';
$sort = $_GET['sort'] ?? 'newest';
$sortSql = "ORDER BY p.created_at DESC";
if ($sort == 'oldest') $sortSql = "ORDER BY p.created_at ASC";
if ($sort == 'liked') $sortSql = "ORDER BY likes_count DESC";
if ($tab == 'posts') {
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count 
        FROM posts p 
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ? $sortSql");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT r.*, u.username, u.display_name, u.avatar, p.content as post_content 
        FROM replies r 
        JOIN users u ON r.user_id = u.id
        JOIN posts p ON r.post_id = p.id
        WHERE r.user_id = ? ORDER BY r.created_at DESC");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
}
?>

<style>
.post-item { padding: 15px 10px; border-bottom: 1px solid #eee; transition: background 0.1s ease; position: relative; }
.post-item:hover { background: #f9f9f9; }
.post-layout { display: table; width: 100%; table-layout: fixed; }
.post-avatar { display: table-cell; width: 58px; vertical-align: top; }
.post-body { display: table-cell; vertical-align: top; padding-left: 5px; }
.post-meta { font-size: 14px; margin-bottom: 3px; color: #404040; }
.post-content { word-wrap: break-word; font-size: 14px; line-height: 1.5; color: #333; }
.muted { color: #999; font-weight: normal; font-size: 13px; }
.delete-btn-container { margin-top: 10px; }
.btn-delete { color: #d9534f; background: none; border: none; padding: 0; font-size: 12px; cursor: pointer; text-decoration: underline; }
.btn-delete:hover { color: #c9302c; }
.sort-bar { padding: 8px 10px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px; font-size: 13px; }
.sort-bar a { text-decoration: none; color: #0084ff; margin: 0 5px; }
.sort-bar a.active { font-weight: bold; color: #333; pointer-events: none; }
</style>

<div class="container mt-4">
    <div class="page-header">
        <h1>Manage Posts/Replies <small><?= $tab == 'posts' ? 'My Posts' : 'My Replies' ?></small></h1>
    </div>

    <ul class="tabs">
        <li class="<?= $tab == 'posts' ? 'active' : '' ?>"><a href="manage_posts.php?tab=posts">Posts</a></li>
        <li class="<?= $tab == 'replies' ? 'active' : '' ?>"><a href="manage_posts.php?tab=replies">Replies</a></li>
    </ul>

    <?php if($tab == 'posts'): ?>
        <div class="sort-bar">
            <strong>Sort:</strong>
            <a href="?tab=posts&sort=newest" class="<?= $sort == 'newest' ? 'active' : '' ?>">Newest</a> |
            <a href="?tab=posts&sort=oldest" class="<?= $sort == 'oldest' ? 'active' : '' ?>">Oldest</a> |
            <a href="?tab=posts&sort=liked" class="<?= $sort == 'liked' ? 'active' : '' ?>">Most Liked</a>
        </div>
    <?php endif; ?>

    <div id="post-feed" class="border rounded bg-white">
        <?php if(empty($items)): ?>
            <div class="alert-message info"><p>Nothing to show here yet.</p></div>
        <?php else: ?>
            <?php foreach($items as $item): 
                $displayName = htmlspecialchars($item['display_name'] ?: $item['username']);
                $dateStr = date('M d, g:i a', strtotime($item['created_at']));
            ?>
                <div class="post-item">
                    <div class="post-layout">
                        <div class="post-avatar">
                            <img src="/images/avatars/<?= htmlspecialchars($item['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" 
                                 class="thumbnail" style="width:48px; height:48px; margin:0;">
                        </div>

                        <div class="post-body">
                            <?php if ($tab == 'replies'): ?>
                                <div class="post-meta">
                                    <span class="muted">Replied to: "<?= htmlspecialchars(substr($item['post_content'] ?? '', 0, 40)) ?>..."</span>
                                </div>
                            <?php else: ?>
                                <div class="post-meta">
                                    <strong style="color: #404040;"><?= $displayName ?></strong>
                                    <span class="muted">@<?= htmlspecialchars($item['username']) ?></span>
                                    <small class="muted" style="margin-left:8px;"><?= $dateStr ?></small>
                                </div>
                            <?php endif; ?>

                            <div class="post-content">
                                <?= nl2br(htmlspecialchars($item['content'])) ?>
                                
                                <?php if(!empty($item['image'])): ?>
                                    <img src="/images/posts/<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" 
                                         style="max-width:100%; max-height:300px; display:block; margin-top:10px; border-radius:4px;" 
                                         class="thumbnail">
                                <?php endif; ?>
                                
                                <?php if(!empty($item['video'])): ?>
                                    <video controls style="max-width:100%; max-height:300px; display:block; margin-top:10px; border-radius:4px;">
                                        <source src="/videos/posts/<?= htmlspecialchars($item['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>

                            <div class="delete-btn-container d-flex justify-content-between align-items-center">
                                <small class="muted">
                                    <?php if(isset($item['likes_count'])): ?>
                                        <strong><?= $item['likes_count'] ?></strong> Likes
                                    <?php endif; ?>
                                </small>
                                
                                <form method="POST" action="/backend/<?= ($tab == 'posts' ? 'posts' : 'replies') ?>/delete.php" 
                                      onsubmit="return confirm('Delete this forever?');" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="<?= ($tab == 'posts' ? 'post_id' : 'reply_id') ?>" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn-delete">Delete Forever</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once "footer.php"; ?>