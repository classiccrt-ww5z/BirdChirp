<?php
require_once "header.php";
require_once __DIR__ . '/../functions/news.php'; 
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../backend/misc/webhook.php';

$adminUsername = $_SESSION['username'] ?? 'Unknown Admin';
$newsCsrf = generateCSRF();

if(isset($_POST['add_news'])){
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setMessage("error", "CSRF validation failed.");
        header("Location: news.php");
        exit;
    }
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if($title && $content){
        addNews($title, $content); 
        setMessage("success","yeah its been added added successfully i dont fucking know dawg");
        
        logAdminAction('News Add', 'News Post', $adminUsername, "Title: $title");
        
        header("Location: news.php");
        exit;
    } else {
        setMessage("error","Title and Content are required.");
    }
}
if(isset($_GET['delete_news'])){
    if (!verifyCSRF($_GET['csrf'] ?? '')) {
        setMessage("error", "CSRF validation failed.");
        header("Location: news.php");
        exit;
    }
    $newsId = (int)$_GET['delete_news'];
    $stmt = $pdo->prepare("SELECT title FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $newsTitle = $stmt->fetchColumn();
    
    deleteNews($newsId); 
    setMessage("success","WOW DELETED HOLU SHIT");
    
    logAdminAction('News Delete', 'News Post', $adminUsername, "Title: $newsTitle");
    
    header("Location: news.php");
    exit;
}
$allNews = getAllNews(); 
?>

<div class="container">
    <form method="post" class="form-horizontal" style="margin-bottom:20px;">
        <input type="hidden" name="csrf_token" value="<?= $newsCsrf ?>">
        <div class="control-group">
            <div class="controls">
                <input type="text" id="title" name="title" class="input-xlarge" placeholder="News title">
            </div>
        </div>
        <div class="control-group">
            <div class="controls">
                <textarea id="content" name="content" class="input-xlarge" rows="5" placeholder="Full news content"></textarea>
            </div>
        </div>
        <div class="control-group">
            <div class="controls">
            <button class="btn primary" type="submit" name="add_news">Submit</button>
            </div>
        </div>
    </form>
    <h3>Existing Announcements/Updates/Blogs</h3>
    <?php if(!empty($allNews)): ?>
    <table class="table table-striped table-bordered table-condensed">
        <thead>
            <tr class="info">
                <th>ID</th>
                <th>Title</th>
                <th>Content</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($allNews as $news): ?>
            <tr>
                <td><?= $news['id'] ?></td>
                <td><?= htmlspecialchars($news['title']) ?></td>
                <td><?= nl2br(htmlspecialchars($news['content'])) ?></td>
                <td><?= $news['created_at'] ?></td>
                <td>
                    <a href="?delete_news=<?= $news['id'] ?>&csrf=<?= $newsCsrf ?>" class="btn danger btn-mini" onclick="return confirm('Delete this news?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No news items added yet.</p>
    <?php endif; ?>
</div>

<?php require_once "footer.php"; ?>