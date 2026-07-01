<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/posts.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1) exit;

$offset = (int)($_GET['offset'] ?? 0);
$limit = 10;
$user_id = (int)($_GET['user_id'] ?? 0);

if(!$user_id) exit;

$stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id=? ORDER BY created_at DESC LIMIT ?, ?");
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->bindValue(3, $limit, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

foreach($posts as $p): ?>
<tr>
    <td style="text-align:center;"><input type="checkbox" name="post_ids[]" value="<?=$p['id']?>"></td>
    <td><?=htmlspecialchars($p['id'])?></td>
    <td><?=htmlspecialchars($p['content'])?></td>
    <td style="text-align:center;">
        <?php if($p['video']): ?>
            <video controls height="30" class="img-polaroid">
                <source src="/videos/posts/<?=htmlspecialchars($p['video'])?>" type="video/mp4">
            </video>
        <?php elseif($p['image']): ?>
            <a href="/images/posts/<?=htmlspecialchars($p['image'])?>" target="_blank">
                <img src="/images/posts/<?=htmlspecialchars($p['image'])?>" height="30" class="img-polaroid">
            </a>
        <?php else: ?>
            <span class="muted">None</span>
        <?php endif; ?>
    </td>
    <td><?=htmlspecialchars($p['created_at'])?></td>
    <td>
        <a href="/legacy_admin/posts.php?delete_post=<?=$p['id']?>&user_id=<?=$user_id?>" class="btn btn-danger btn-mini" onclick="return confirm('Delete this post?')">
            <i class="icon-trash icon-white"></i> Delete
        </a>
    </td>
</tr>
<?php endforeach; ?>