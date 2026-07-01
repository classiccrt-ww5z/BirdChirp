<?php 
require_once "../header.php"; 
$stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
$posts = $stmt->fetchAll();
?>

<div class="container">
    <h1>Blog</h1>
    <hr>
    <?php foreach ($posts as $post): ?>
        <div class="blog-post" style="margin-bottom: 30px;">
            <h2 style="color: #333;"><?= e($post['title']) ?></h2>
            <small style="color: #666;">Posted on: <?= $post['created_at'] ?></small>
            <p style="margin-top: 10px;"><?= nl2br(e($post['content'])) ?></p>
        </div>
        <hr>
    <?php endforeach; ?>
</div>

<?php require_once "../footer.php"; ?>