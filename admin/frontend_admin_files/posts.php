<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/security.php';
require_once __DIR__ . '/../../functions/messages.php';
require_once __DIR__ . '/../../functions/admin_log.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { return; }

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30; $offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$adminCsrf = generateCSRF();

if (isset($_GET['delete'])) {
    if (!verifyCSRF($_GET['csrf'] ?? '')) { setMessage("error","CSRF failed."); }
    else {
        $postId = (int)$_GET['delete'];
        $stmt = $pdo->prepare("SELECT user_id, content, image, video FROM posts WHERE id = ?");
        $stmt->execute([$postId]); $postData = $stmt->fetch();
        if ($postData) {
            foreach (['image'=>'images/posts/','video'=>'videos/posts/'] as $k=>$dir) {
                if (!empty($postData[$k])) { $p = __DIR__."/../../$dir".$postData[$k]; if (file_exists($p)) unlink($p); }
            }
        }
        $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$postId]);
        setMessage("success","Post #$postId deleted.");
        logAdminAction('Delete Post',"Post #$postId",$_SESSION['username']??'Admin',substr($postData['content']??'',0,100));
    }
    echo '<script>window.location.href="index.php?page=posts&filter='.e($filter).'"</script>'; return;
}

$where = "1=1"; $params = [];
if ($search) { $e = addcslashes($search,'%_'); $where .= " AND (p.content LIKE ? OR u.username LIKE ?)"; $params[]="%$e%"; $params[]="%$e%"; }
if ($filter == 'images') $where .= " AND p.image IS NOT NULL AND p.image != ''";
if ($filter == 'videos') $where .= " AND p.video IS NOT NULL AND p.video != ''";
if ($filter == 'text') $where .= " AND (p.image IS NULL OR p.image = '') AND (p.video IS NULL OR p.video = '')";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts p JOIN users u ON p.user_id = u.id WHERE $where");
$countStmt->execute($params);
$totalPosts = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalPosts / $perPage));

$stmt = $pdo->prepare("SELECT p.*, u.username, u.display_name, u.avatar FROM posts p JOIN users u ON p.user_id = u.id WHERE $where ORDER BY p.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$posts = $stmt->fetchAll();
?>

<div style="margin-bottom:12px;"></div>

<form method="get" class="form-inline" style="margin-bottom:10px;" onsubmit="return loadPage('posts',this)">
  <div class="form-group">
    <input type="text" name="search" class="form-control input-sm" placeholder="Search content or username..." value="<?=e($search)?>">
  </div>
  <button type="submit" class="btn btn-default btn-sm">Search</button>
  <?php if($search): ?> <a href="index.php?page=posts" class="btn btn-link btn-sm" onclick="return loadPage('posts')">Clear</a><?php endif; ?>
  <input type="hidden" name="filter" value="<?=e($filter)?>">
</form>

<ul class="nav nav-pills" style="margin-bottom:10px;">
  <li class="<?=$filter=='all'?'active':''?>"><a href="index.php?page=posts&filter=all" onclick="return loadPage('posts','filter=all')">All</a></li>
  <li class="<?=$filter=='images'?'active':''?>"><a href="index.php?page=posts&filter=images" onclick="return loadPage('posts','filter=images')">Images</a></li>
  <li class="<?=$filter=='videos'?'active':''?>"><a href="index.php?page=posts&filter=videos" onclick="return loadPage('posts','filter=videos')">Videos</a></li>
  <li class="<?=$filter=='text'?'active':''?>"><a href="index.php?page=posts&filter=text" onclick="return loadPage('posts','filter=text')">Text</a></li>
</ul>

<div class="panel panel-default">
<div class="panel-body" style="padding:0;">
<table class="table table-striped">
<thead><tr><th>ID</th><th>User</th><th>Content</th><th>Media</th><th>Date</th><th>Action</th></tr></thead>
<tbody>
<?php if($posts): foreach($posts as $p): ?>
<tr>
  <td>#<?=$p['id']?></td>
  <td><a href="edit_user.php?user_id=<?=$p['user_id']?>" onclick="window.open('edit_user.php?user_id=<?=$p['user_id']?>','manage','width=850,height=650,scrollbars=1');return false;"><b><?=e($p['display_name']?:$p['username'])?></b></a></td>
  <td><span class="trunc"><?=e(substr($p['content'],0,150))?></span></td>
  <td><?php if($p['image']):?><span class="label label-success">IMG</span><?php endif;?><?php if($p['video']):?><span class="label label-info">VID</span><?php endif;?></td>
  <td class="text-muted"><?=date('M j, Y',strtotime($p['created_at']))?></td>
  <td><a href="index.php?page=posts&delete=<?=$p['id']?>&csrf=<?=$adminCsrf?>&filter=<?=$filter?><?=$search?'&search='.urlencode($search):''?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete post #<?=$p['id']?>?')">Delete</a></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="text-muted" style="text-align:center;padding:16px;">No posts found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div></div>

<?php if($totalPages>1): ?>
<ul class="pager">
  <?php if($page>1): ?><li><a href="index.php?page=posts&page=<?=$page-1?>&filter=<?=$filter?><?=$search?'&search='.urlencode($search):''?>" onclick="return loadPage('posts','page=<?=$page-1?>&filter=<?=$filter?><?=$search?'&search='.urlencode($search):''?>')">Prev</a></li><?php endif; ?>
  <li class="disabled"><a>Page <?=$page?> / <?=$totalPages?></a></li>
  <?php if($page<$totalPages): ?><li><a href="index.php?page=posts&page=<?=$page+1?>&filter=<?=$filter?><?=$search?'&search='.urlencode($search):''?>" onclick="return loadPage('posts','page=<?=$page+1?>&filter=<?=$filter?><?=$search?'&search='.urlencode($search):''?>')">Next</a></li><?php endif; ?>
</ul>
<?php endif; ?>
