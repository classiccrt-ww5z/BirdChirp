<?php
require_once "header.php";

$webDir  = '/error/'; 
$diskDir = __DIR__ . $webDir;
$imgExts = ['png','jpg','jpeg','gif','webp'];
$vidExts = ['mp4','webm','ogg'];

$media = [];
if (is_dir($diskDir)) {
    foreach (array_merge($imgExts, $vidExts) as $ext) {
        foreach (glob($diskDir . '*.' . $ext) as $file) {
            if (is_file($file)) {
                $type = in_array($ext, $imgExts) ? 'image' : 'video';
                $media[] = ['type' => $type, 'url' => $webDir . basename($file)];
            }
        }
    }
}

if (!empty($media)) {
    $picked = $media[array_rand($media)];
} else {
    $picked = ['type' => 'image', 'url' => '/images/default_404.png']; 
}

?>
<style>
    .error-container { text-align: center; padding: 40px 0; }
    .error-media { 
        margin: 20px auto; 
        width: 100%;
        max-width: 600px; 
        height: 400px;    
        overflow: hidden;
        background: #000; 
    }
    .error-media img, 
    .error-media video { 
        width: 100%;  
        height: 100%; 
        object-fit: fill; 
    }
</style>

<div class="container">
    <div class="error-container">
             <div class="error-media">
                        <?php if ($picked['type'] === 'video'): ?>
                            <video autoplay controls loop>
                                <source src="<?= e($picked['url']) ?>" type="video/<?= pathinfo($picked['url'], PATHINFO_EXTENSION) ?>">
                                Your browser does not support the video tag.
                            </video>
                        <?php else: ?>
                            <img src="<?= e($picked['url']) ?>" alt="404 Error">
                        <?php endif; ?>
                    </div>

                    <h2 style="margin-top:20px;">404 Not Found</h2>
                    <p class="lead">This page does not exist or has been deleted.</p>
                    <button onclick="window.history.back()" class="btn btn-large">
    Go Back
</button>        
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php"; ?>