<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/security.php';
require_once __DIR__ . '/../../functions/messages.php';
require_once __DIR__ . '/../../functions/news.php';
require_once __DIR__ . '/../../functions/admin_log.php';
if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1 || !isset($_SESSION['admin_verified'])) { return; }

$adminUsername = $_SESSION['username'] ?? 'Unknown Admin';
$newsCsrf = generateCSRF();

if (isset($_POST['add_news'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setMessage("error","CSRF failed."); }
    else {
        $title = trim($_POST['title'] ?? ''); $content = trim($_POST['content'] ?? '');
        if ($title && $content) {
            addNews($title, $content);
            setMessage("success","News post created.");
            logAdminAction('News Add','News Post',$adminUsername,"Title: $title");
        } else { setMessage("error","Title and content are required."); }
    }
    echo '<script>window.location.href="index.php?page=news"</script>'; return;
}
if (isset($_GET['delete_news'])) {
    if (!verifyCSRF($_GET['csrf'] ?? '')) { setMessage("error","CSRF failed."); }
    else {
        $newsId = (int)$_GET['delete_news'];
        $stmt = $pdo->prepare("SELECT title FROM news WHERE id = ?"); $stmt->execute([$newsId]); $t = $stmt->fetchColumn();
        deleteNews($newsId);
        setMessage("success","News post deleted.");
        logAdminAction('News Delete','News Post',$adminUsername,"Title: $t");
    }
    echo '<script>window.location.href="index.php?page=news"</script>'; return;
}
$allNews = getAllNews();
?>

<div style="margin-bottom:12px;"></div>

<div class="row">
<div class="col-md-5">
<div class="panel panel-default">
<div class="panel-heading">Create News Post</div>
<div class="panel-body">
<form method="post" action="index.php?page=news">
  <input type="hidden" name="csrf_token" value="<?=$newsCsrf?>">
  <div class="form-group">
    <label>Title</label>
    <input type="text" name="title" class="form-control input-sm" placeholder="News title..." required>
  </div>
  <div class="form-group">
    <label>Content</label>
    <textarea name="content" class="form-control input-sm" rows="6" placeholder="Full news content..." required></textarea>
  </div>
  <button type="submit" name="add_news" class="btn btn-primary btn-sm">Publish</button>
</form>
</div></div></div>

<div class="col-md-7">
<div class="panel panel-default">
<div class="panel-heading">Published (<?=count($allNews)?>)</div>
<div class="panel-body" style="padding:0;">
<?php if($allNews): ?>
<table class="table table-striped">
<?php foreach($allNews as $n): ?>
<tr>
  <td style="width:40px;">#<?=$n['id']?></td>
  <td><b><?=e($n['title'])?></b><br><span class="text-muted"><?=date('M j, Y',strtotime($n['created_at']))?></span></td>
  <td><a href="index.php?page=news&delete_news=<?=$n['id']?>&csrf=<?=$newsCsrf?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete?')">Delete</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?><p class="text-muted" style="padding:10px;margin:0;">No news posts yet.</p><?php endif; ?>
</div></div></div></div>
