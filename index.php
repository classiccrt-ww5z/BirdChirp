<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

$base_path = __DIR__ . DIRECTORY_SEPARATOR;

try {
    
    
    $required = [
        $base_path . "database" . DIRECTORY_SEPARATOR . "config.php", 
        $base_path . "functions" . DIRECTORY_SEPARATOR . "posts.php", 
        $base_path . "functions" . DIRECTORY_SEPARATOR . "auth.php", 
        $base_path . "functions" . DIRECTORY_SEPARATOR . "security.php",
        $base_path . "functions" . DIRECTORY_SEPARATOR . "users.php"
    ];

    foreach ($required as $file) {
        if (!file_exists($file)) {
            die("Fatal Error: File not found at " . htmlspecialchars($file));
        }
        require_once $file;
    }

    if (!isset($pdo)) {
        die("Fatal Error: \$pdo not defined.");
    }

    if (!function_exists('e')) {
        function e($t) { return htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8'); }
    }

} catch (Throwable $t) {
    die("Setup Crash: " . $t->getMessage());
}

$uid = $_SESSION['user_id'] ?? 0;

if(isset($_GET['set_tab'])){
    $tab = $_GET['set_tab'];
    if(in_array($tab, ["following","recommended","newest"])){
        setcookie("feed_tab", $tab, time() + 60*60*24*30, "/");
    }
    header("Location: /");
    exit;
}

$activeTab = $_COOKIE['feed_tab'] ?? 'following';

if (isset($_GET['ajax_load_more'])) {
    try {
        while (ob_get_level()) { ob_end_clean(); }
        $last_id = intval($_GET['last_id']);
        
        $query = "SELECT p.*, u.username, u.display_name, u.avatar,
                  (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = p.user_id) as is_following,
                  (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                  (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked
                  FROM posts p
                  JOIN users u ON p.user_id = u.id ";
        
        if ($activeTab == "following" && isLoggedIn()) {
            $stmt = $pdo->prepare($query . " JOIN follows f ON p.user_id = f.following_id LEFT JOIN bans b ON p.user_id = b.user_id WHERE f.follower_id = ? AND p.id < ? AND b.user_id IS NULL ORDER BY p.id DESC LIMIT 10");
            $stmt->execute([$uid, $uid, $uid, $last_id]);
        } else {
            $stmt = $pdo->prepare($query . " LEFT JOIN bans b ON p.user_id = b.user_id WHERE p.id < ? AND b.user_id IS NULL ORDER BY p.id DESC LIMIT 10");
            $stmt->execute([$uid, $uid, $last_id]);
        }
        
        $ajax_posts = $stmt->fetchAll();
        if ($ajax_posts) {
            foreach ($ajax_posts as $post) { 
                include $base_path . "components" . DIRECTORY_SEPARATOR . "post_item.php"; 
            }
        }
    } catch (Throwable $e) {
        http_response_code(500);
        error_log("AJAX Error: " . $e->getMessage());
        echo "AJAX Error: An error occurred";
    }
    exit;
}

$base_query = "SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
               (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = p.user_id) as is_following,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked
               FROM posts p 
               JOIN users u ON p.user_id = u.id ";

if ($activeTab == "following" && isLoggedIn()) {
    $stmt = $pdo->prepare($base_query . " JOIN follows f ON p.user_id = f.following_id LEFT JOIN bans b ON p.user_id = b.user_id WHERE f.follower_id = ? AND b.user_id IS NULL ORDER BY p.id DESC LIMIT 10");
    $stmt->execute([$uid, $uid, $uid]);
} elseif ($activeTab == "recommended") {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin,
        (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = p.user_id) as is_following,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as has_liked,
        ( (SELECT COUNT(*) FROM likes WHERE post_id = p.id) / 
          (POW(TIMESTAMPDIFF(HOUR, p.created_at, NOW()) + 2, 1.5)) 
        ) as rank_score

        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN bans b ON p.user_id = b.user_id 
        WHERE b.user_id IS NULL 
        ORDER BY rank_score DESC, p.id DESC 
        LIMIT 10
    ");
    $stmt->execute([$uid, $uid]);
} else {
    $stmt = $pdo->prepare($base_query . " LEFT JOIN bans b ON p.user_id = b.user_id WHERE b.user_id IS NULL ORDER BY p.id DESC LIMIT 10");
    $stmt->execute([$uid, $uid]);
}
$posts = $stmt->fetchAll();

$tagStmt = $pdo->query("SELECT p.content FROM posts p LEFT JOIN bans b ON p.user_id = b.user_id WHERE p.content LIKE '%#%' AND b.user_id IS NULL ORDER BY p.created_at DESC LIMIT 50");
$all_content = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
$tags_found = [];
foreach($all_content as $text){
    preg_match_all('/#(\w+)/',$text,$matches);
    foreach($matches[0] as $tag){ $tags_found[$tag] = ($tags_found[$tag] ?? 0) + 1; }
}
arsort($tags_found);
$trending_tags = array_slice($tags_found,0,5);

if (isLoggedIn()) {
    $userStmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.is_verified, u.partner, u.admin FROM users u LEFT JOIN bans b ON u.id = b.user_id WHERE u.id != ? AND b.user_id IS NULL AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id=?) ORDER BY RAND() LIMIT 5");
    $userStmt->execute([$uid,$uid]);
} else {
    $userStmt = $pdo->query("SELECT u.id, u.username, u.display_name, u.avatar FROM users u LEFT JOIN bans b ON u.id = b.user_id WHERE b.user_id IS NULL ORDER BY RAND() LIMIT 5");
}
$recommended_users = $userStmt->fetchAll();

$remaining_cooldown = 0;
if (isLoggedIn()) {
    $cStmt = $pdo->prepare("SELECT created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $cStmt->execute([$uid]);
    $lastTime = $cStmt->fetchColumn();
    if ($lastTime) {
        $passed = time() - strtotime($lastTime);
        if ($passed < 15) { $remaining_cooldown = 15 - $passed; }
    }
}

require_once $base_path . "header.php";
?>

<style>
.pfp-mini { width: 32px !important; height: 32px !important; object-fit: cover; border: 1px solid #ddd; }
.sidebar-section { margin-bottom: 25px; }
.post img { max-width: 100%; height: auto; display:block;}
.load-more-container { text-align: center; margin: 20px 0; padding: 20px; }
.post:hover { background-color: #fcfcfc; }
.post-actions { 
    margin-top: 10px; 
    padding-bottom: 5px; 
}
.post-actions a { text-decoration: none; font-size: 12px; display: flex; align-items: center; }
.post-actions img { width: 16px; height: 16px; margin-right: 8px; }
#post-form.well { padding: 14px; margin-bottom: 20px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; transition: all 0.2s; }
#post-form.dragover { background-color: #e7f2ff !important; border-color: #b3d4ff !important; }
#post-content { border: 1px solid #ccc; font-family: inherit; font-size: 13px; line-height: 18px; padding: 4px; resize: none; }
.upload-preview { display: none; position: relative; margin: 10px 0; width: 200px; border: 1px solid #ccc; padding: 2px; background: #fff; }
.upload-preview img, .upload-preview video { width: 100%; display: block; }
.remove-upload { position: absolute; top: -8px; right: -8px; background: #333; color: #fff; border-radius: 50%; width: 16px; height: 16px; line-height: 14px; text-align: center; font-size: 10px; cursor: pointer; border: 1px solid #fff; }
.image-upload-trigger { cursor: pointer; opacity: 0.7; vertical-align: middle; margin-right: 5px; width: 20px; }
.image-upload-trigger:hover { opacity: 1; }
.upload-progress { margin: 10px 0; display: none; }
.upload-progress-outer { border: 1px solid #7f9db9; height: 20px; background: #fff; width: 200px; position: relative; }
.upload-progress-inner { height: 100%; background: #3c78d8; width: 0%; }
.upload-progress-text { 
    position: absolute; 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%); 
    font-size: 11px; 
    font-family: Tahoma, Arial, sans-serif; 
    color: #000; 
    font-weight: bold;
    text-shadow: 0 0 2px #fff;
    white-space: nowrap;
}
.upload-progress-status { font-size: 11px; color: #333; margin-top: 4px; font-family: Tahoma, Arial, sans-serif; }
</style>

<div class="container">
<?php if(!isLoggedIn()): ?>
    <div class="hero-unit" style="text-align:center;">
        <h1>Welcome to  <?php echo $SITE_NAME; ?>.</h1>
        <p>"<?php include "slogan.php"; ?>"</p>
        <p>
            <a href="/signup" class="btn primary large">Sign Up</a>
            <a href="/login" class="btn large">Login</a>
        </p>
    </div>
<?php else: ?>
    <div class="page-header">
        <h1>My Feed <small>What's happening</small></h1>
    </div>

    <div class="row">
        <div class="span10">
            <form id="post-form" class="well" action="/backend/posts/create_post.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="image" id="post-image" value="">
                <input type="hidden" name="video" id="post-video" value="">
                <textarea id="post-content" name="content" class="span9" placeholder="What's happening?" style="height: 60px;"></textarea>
                <div id="media-preview" class="upload-preview" style="display:none;">
                    <img id="preview-img" src="">
                    <span class="remove-upload" onclick="clearMedia('image')">x</span>
                </div>
                <div id="video-preview" class="upload-preview" style="display:none;">
                    <video id="preview-video" controls></video>
                    <span class="remove-upload" onclick="clearMedia('video')">x</span>
                </div>
                <div id="upload-progress" class="upload-progress">
                    <div class="upload-progress-outer">
                        <div class="upload-progress-inner" id="progress-bar">
                            <div class="upload-progress-text" id="progress-text">0%</div>
                        </div>
                    </div>
                    <div class="upload-progress-status" id="progress-status">Preparing...</div>
                </div>
                <div style="margin-top:8px;">
                    <input type="file" id="media-input" style="display:none;" accept="image/*,video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska">
                    <img src="/images/misc/image.png" class="image-upload-trigger" title="Add Image or Video" onclick="document.getElementById('media-input').click()">
                    <div class="pull-right">
                        <span id="char-count" style="font-size:12px; color:#666; margin-right:10px;">0 / 2000</span>
                        <button type="submit" id="submit-post" class="btn primary <?= ($remaining_cooldown > 0) ? 'disabled' : '' ?>" <?= ($remaining_cooldown > 0) ? 'disabled' : '' ?>>
                            <?= ($remaining_cooldown > 0) ? "Wait (" . $remaining_cooldown . "s)" : "Post" ?>
                        </button>
                    </div>
                </div>
                <div style="clear:both;"></div>
            </form>

            <ul class="tabs">
                <li class="<?= $activeTab == 'following' ? 'active' : '' ?>"><a href="?set_tab=following">Following</a></li>
                <li class="<?= $activeTab == 'recommended' ? 'active' : '' ?>"><a href="?set_tab=recommended">Recommended</a></li>
                <li class="<?= $activeTab == 'newest' ? 'active' : '' ?>"><a href="?set_tab=newest">Newest</a></li>
            </ul>

            <div id="post-feed">
                <?php if(!$posts): ?>
                    <div class="alert-message info">Nothing here yet.</div>
                <?php else: ?>
                    <?php foreach($posts as $post){ include $base_path . "components" . DIRECTORY_SEPARATOR . "post_item.php"; } ?>
                <?php endif; ?>
            </div>

            <div id="feed-loader" class="load-more-container" style="display:none;">
                <p class="muted">Loading more posts...</p>
            </div>
            <input type="hidden" id="no-more-posts" value="0">
        </div>

        <div class="span6">
            <div class="sidebar-section">
                <h3>Trending</h3>
                <ul class="unstyled">
                    <?php foreach($trending_tags as $tag=>$count): ?>
                        <li><a href="/search?q=<?=urlencode($tag)?>" class="label notice"><?=htmlspecialchars($tag)?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="sidebar-section">
                <h3>Who to follow</h3>
                <ul class="unstyled" style="margin-top:10px;">
                    <?php foreach($recommended_users as $r_user): ?>
                        <li style="margin-bottom:12px; overflow:hidden;">
                            <img src="/images/avatars/<?= htmlspecialchars($r_user['avatar'] ?? 'default.png', ENT_QUOTES, 'UTF-8') ?>" class="pfp-mini" style="float:left; margin-right:10px;">
                            <div style="float:left; line-height: 1.2;">
                                <strong><a href="/u/<?=$r_user['id']?>"><?=e(!empty($r_user['display_name']) ? htmlspecialchars($r_user['display_name']) : $r_user['username'])?></a></strong><br>
                                <span class="muted" style="font-size:11px;">@<?=e($r_user['username'])?></span><br>
                                <?php if(isLoggedIn()): ?>
                                    <a href="/backend/users/follow_user.php?id=<?=$r_user['id']?>&csrf=<?= $csrf ?>" class="btn small success" style="margin-top:4px;">Follow</a>
                                <?php else: ?>
                                    <a href="/login" class="btn small" style="margin-top:4px;">Follow</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
const mediaInput = document.getElementById('media-input');
const mediaPreview = document.getElementById('media-preview');
const videoPreview = document.getElementById('video-preview');
const previewImg = document.getElementById('preview-img');
const previewVideo = document.getElementById('preview-video');
const postForm = document.getElementById('post-form');
const postContent = document.getElementById('post-content');
const postImage = document.getElementById('post-image');
const postVideo = document.getElementById('post-video');
const uploadProgress = document.getElementById('upload-progress');
const progressBar = document.getElementById('progress-bar');
const progressText = document.getElementById('progress-text');
const progressStatus = document.getElementById('progress-status');
const submitBtn = document.getElementById('submit-post');

let pendingImage = null;
let pendingVideo = null;
let isUploading = false;
let currentMediaType = null;
let progressInterval = null;

function setProgress(percent, status) {
    if (percent >= 100) {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    }
    progressBar.style.width = percent + '%';
    progressText.textContent = Math.round(percent) + '%';
    progressStatus.textContent = status;
}

function clearMedia(type) {
    if (type === 'image') {
        mediaInput.value = "";
        mediaPreview.style.display = 'none';
        previewImg.src = "";
        postImage.value = "";
        pendingImage = null;
    } else if (type === 'video') {
        mediaInput.value = "";
        videoPreview.style.display = 'none';
        previewVideo.src = "";
        postVideo.value = "";
        pendingVideo = null;
    }
    currentMediaType = null;
    updateSubmitButton();
}

function updateSubmitButton() {
    if (isUploading) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Processing...";
    } else {
        submitBtn.disabled = false;
        submitBtn.textContent = "Post";
    }
}

async function uploadMedia(file, type) {
    isUploading = true;
    currentMediaType = type;
    updateSubmitButton();
    
    uploadProgress.style.display = 'block';
    setProgress(5, 'Loading...');
    
    const formData = new FormData();
    if (type === 'video') {
        formData.append('video', file);
    } else {
        formData.append('image', file);
    }
    
    let progress = 5;
    const intervalTime = type === 'video' ? 400 : 250;
    
    progressInterval = setInterval(function() {
        progress += 8;
        if (progress >= 95) progress = 92;
        
        if (progress <= 20) {
            setProgress(progress, 'Loading...');
        } else if (progress <= 50) {
            setProgress(progress, 'Processing...');
        } else if (progress <= 80) {
            setProgress(progress, type === 'video' ? 'Converting...' : 'Optimizing...');
        } else {
            setProgress(progress, 'Almost done...');
        }
    }, intervalTime);
    
    try {
        const response = await fetch('/backend/posts/process_media.php', {
            method: 'POST',
            body: formData
        });
        
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        
        const result = await response.json();
        
        setProgress(100, 'Done!');
        
        if (result.success) {
            if (type === 'video') {
                pendingVideo = result.video;
                postVideo.value = result.video;
                previewVideo.src = URL.createObjectURL(file);
                videoPreview.style.display = 'block';
            } else {
                pendingImage = result.image;
                postImage.value = result.image;
                previewImg.src = URL.createObjectURL(file);
                mediaPreview.style.display = 'block';
            }
        } else {
            setProgress(0, 'Failed: ' + result.error);
            setTimeout(function() { uploadProgress.style.display = 'none'; }, 2000);
            isUploading = false;
            updateSubmitButton();
            return;
        }
    } catch (err) {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        setProgress(0, 'Error: ' + err.message);
        setTimeout(function() { uploadProgress.style.display = 'none'; }, 2000);
        isUploading = false;
        updateSubmitButton();
        return;
    }
    
    isUploading = false;
    updateSubmitButton();
    setTimeout(function() { uploadProgress.style.display = 'none'; }, 1000);
}

function handleMediaChange(files) {
    if (files.length > 0) {
        const file = files[0];
        if (file.type.startsWith('video/')) {
            if (!postVideo.value) {
                uploadMedia(file, 'video');
            }
        } else if (file.type.startsWith('image/')) {
            if (!postImage.value) {
                uploadMedia(file, 'image');
            }
        }
    }
}

mediaInput.onchange = evt => handleMediaChange(mediaInput.files);

postContent.onpaste = evt => {
    const items = (evt.clipboardData || evt.originalEvent.clipboardData).items;
    for (const item of items) {
        if (item.type.indexOf('image') !== -1) {
            const blob = item.getAsFile();
            const dt = new DataTransfer();
            dt.items.add(blob);
            handleMediaChange(dt.files);
        }
    }
};

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(name => {
    postForm.addEventListener(name, e => { e.preventDefault(); e.stopPropagation(); }, false);
});

postForm.addEventListener('dragover', () => postForm.classList.add('dragover'));
postForm.addEventListener('dragleave', () => postForm.classList.remove('dragover'));
postForm.addEventListener('drop', e => {
    postForm.classList.remove('dragover');
    handleMediaChange(e.dataTransfer.files);
});

let isLoading = false;
$(window).on('scroll', function() {
    if ($('#no-more-posts').val() == "1" || isLoading) return;
    if ($(window).scrollTop() + $(window).height() > $(document).height() - 400) {
        loadMorePosts();
    }
});

function loadMorePosts() {
    var lastId = $('.post').last().attr('data-id'); 
    if (!lastId) return;
    isLoading = true;
    $('#feed-loader').show();
    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: { ajax_load_more: 1, last_id: lastId },
        success: function(data) {
            isLoading = false;
            $('#feed-loader').hide();
            if ($.trim(data) == "") {
                $('#no-more-posts').val("1");
                $('#post-feed').append('<div class="alert-message info" style="text-align:center;">No more posts.</div>');
            } else {
                $('#post-feed').append(data);
            }
        },
        error: function(xhr) {
            isLoading = false;
            $('#feed-loader').hide();
        }
    });
}

$(document).ready(function() {
    $('#post-content').on('input', function() {
        let len = $(this).val().length;
        $('#char-count').text(len + " / 2000").css('color', len > 1900 ? '#b94a48' : '#666');
    });

    let cooldown = <?= (int)$remaining_cooldown ?>;
    if (cooldown > 0) {
        let timer = setInterval(function() {
            cooldown--;
            if (cooldown <= 0) {
                $('#submit-post').removeClass('disabled').prop('disabled', false).text('Post');
                clearInterval(timer);
            } else {
                $('#submit-post').text('Wait (' + cooldown + 's)');
            }
        }, 1000);
    }
});

$(document).on('click', '.post', function(e) {
    if ($(e.target).is('a, button, i, img, .btn, .remove-upload, video, source')) return;
    var postId = $(this).attr('data-id');
    if(postId) window.location.href = '/post/' + postId;
});
</script>

<?php require_once $base_path . "footer.php"; ?>