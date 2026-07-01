<?php
require_once __DIR__ . '/../database/config.php';

function isUserBanned($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM bans WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() !== false;
}

function createPost($userId, $content, $image = null, $video = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image, video) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $content, $image, $video]);
}

function getFeedPosts($limit = 50) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT posts.*, users.username, users.avatar
        FROM posts
        JOIN users ON users.id = posts.user_id
        WHERE NOT EXISTS (
            SELECT 1 FROM bans WHERE bans.user_id = posts.user_id
        )
        ORDER BY posts.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPost($id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT posts.*, users.username, users.avatar
        FROM posts
        JOIN users ON users.id = posts.user_id
        LEFT JOIN bans ON bans.user_id = posts.user_id
        WHERE posts.id = ? AND bans.user_id IS NULL
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function deletePost($postId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT image, video FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) return false;
    if (!empty($post['image'])) {
        $imagePath = __DIR__ . '/../images/posts/' . $post['image'];
        if (file_exists($imagePath)) @unlink($imagePath);
    }
    if (!empty($post['video'])) {
        $videoPath = __DIR__ . '/../videos/posts/' . $post['video'];
        if (file_exists($videoPath)) @unlink($videoPath);
    }
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
    return $stmt->execute([$postId, $userId]);
}

function countUserPosts($userId){
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res['total'] ?? 0;
}

function getUserPosts($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT posts.*, users.username, users.avatar
        FROM posts
        JOIN users ON users.id = posts.user_id
        LEFT JOIN bans ON bans.user_id = posts.user_id
        WHERE posts.user_id = ? AND bans.user_id IS NULL
        ORDER BY posts.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function parsePostContent($content) {
    $wrapEmbed = function($iframeSrc, $originalUrl) {
        return '
        <div class="post-embed" style="margin-top:10px; max-width:560px;">
            <div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; ">
                <iframe src="'.$iframeSrc.'" 
                        style="position:absolute; top:0; left:0; width:100%; height:100%;" 
                        frameborder="0" allowfullscreen></iframe>
            </div>
            <div style="font-size:11px; margin-top:4px;"><a href="'.$originalUrl.'" target="_blank">'.$originalUrl.'</a></div>
        </div>';
    };

    $embeds = [
        'youtube'      => '/https?:\/\/(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
        'youtubeShorts'=> '/https?:\/\/(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        'vidlii'       => '/https?:\/\/(?:www\.)?vidlii\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        'kamtape'      => '/https?:\/\/(?:www\.)?kamtape\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        'betacast'     => '/https?:\/\/(?:www\.)?betacast\.org\/watch\?v=([a-zA-Z0-9]+)/',
    ];

    $embedUsed = false;
    foreach ($embeds as $site => $pattern) {
        $content = preg_replace_callback($pattern, function($matches) use ($site, &$embedUsed, $wrapEmbed) {
            $url = $matches[0];
            if ($embedUsed) return $url;
            $embedUsed = true;

            switch($site) {
                case 'youtube':
                    return $wrapEmbed("https://www.youtube.com/embed/".htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8'), $url);
                case 'youtubeShorts':
                    return $wrapEmbed("https://www.youtube.com/embed/".htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8'), $url);
                case 'vidlii':
                    return $wrapEmbed("https://www.vidlii.com/embed?v=".htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8'), $url);
                case 'kamtape':
                    return $wrapEmbed("https://www.kamtape.com/watch_video?v=".htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8')."&webm=1", $url);
                case 'betacast':
                    return $wrapEmbed("https://www.betacast.org/embed/".htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8'), $url);
            }
            return $url;
        }, $content);
    }
    $content = preg_replace_callback('/#(\w+)/', function($matches) {
        return '<a href="/search.php?q=%23' . rawurlencode($matches[1]) . '" class="hashtag">#<span>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</span></a>';
    }, $content);
    
    if (!$embedUsed) {
        $url_pattern = '/(?<!["\'=\/])(https?:\/\/[^\s<]+)/i';
        $content = preg_replace_callback($url_pattern, function($matches) {
            $url = $matches[1];
            $trusted_domains = ['birdchirp.org','kamtape.com','betacast.org','youtube.com','youtu.be','vidlii.com','x.com','twitter.com','imgur.com','tenor.com','google.com','catbox.moe','files.catbox.moe' ];
            $host = strtolower(parse_url($url, PHP_URL_HOST));
            $is_trusted = false;

            foreach ($trusted_domains as $domain) {
                if ($host && strpos($host, $domain) !== false) {
                    $is_trusted = true;
                    break;
                }
            }

            if ($is_trusted) {
                return '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank">'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'</a>';
            } else {
                $redirectUrl = "/ExternalUrl.php?url=" . urlencode($url);
                return '<a href="'.htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'</a>';
            }
        }, $content);
    }

    return $content;
}

function getUserReplies($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT replies.*, users.username, users.avatar
        FROM replies
        JOIN users ON users.id = replies.user_id
        LEFT JOIN bans ON bans.user_id = replies.user_id
        WHERE replies.user_id = ? AND bans.user_id IS NULL
        ORDER BY replies.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteReply($replyId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM replies WHERE id = ? AND user_id = ?");
    return $stmt->execute([$replyId, $userId]);
}