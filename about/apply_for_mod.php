<?php 
require_once "../header.php"; 
$webhook_url = "https://discord.com/api/webhooks/1489495517296722031/x7JWPrm4K664fKcYf6i_tLKeD5j8-3_swqskbUYs8vK5fHWMlUivt3Wx9pYCBaOsk7vi"; 
$message_sent = false;
$already_applied = false;
$is_logged_in = isset($_SESSION['user_id']); 
$current_site_user = $_SESSION['username'] ?? 'Unknown User'; 

// Check if they have already applied in this session
if (isset($_SESSION['has_applied']) && $_SESSION['has_applied'] === true) {
    $already_applied = true;
}

if ($is_logged_in && !$already_applied && $_SERVER["REQUEST_METHOD"] == "POST") {
    $discord_tag = htmlspecialchars($_POST['discord_tag']);
    $email       = htmlspecialchars($_POST['email']);
    $age         = htmlspecialchars($_POST['age']);
    $reason      = htmlspecialchars($_POST['reason']);
    
    $data = [
        "content" => "**New Moderator Application Received!**",
        "embeds" => [
            [
                "title" => "Application Details",
                "color" => 5814783,
                "fields" => [
                    ["name" => "BirdChirp User", "value" => $current_site_user, "inline" => true],
                    ["name" => "Discord Tag", "value" => $discord_tag, "inline" => true],
                    ["name" => "Email Address", "value" => $email, "inline" => false],
                    ["name" => "Age", "value" => $age, "inline" => true],
                    ["name" => "Reasoning", "value" => $reason, "inline" => false]
                ],
                "footer" => ["text" => "meow"]
            ]
        ]
    ];

    $json_data = json_encode($data);

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_exec($ch);
    curl_close($ch);
    
    $_SESSION['has_applied'] = true;
    $message_sent = true;
}
?>

<div class="container">
    <div class="row">
        <div class="page-header">
            <h1>Apply for Moderator <small>Join the team at <?php echo $SITE_NAME; ?></small></h1>
        </div>

        <?php if (!$is_logged_in): ?>
            <div class="alert-message error">
                <p><strong>Access Denied!</strong> You must be logged in to your account to apply.</p>
                <div style="margin-top:10px;">
                    <a href="/login" class="btn primary">Login Now</a>
                </div>
            </div>

        <?php elseif ($already_applied && !$message_sent): ?>
            <div class="alert-message warning">
                <p><strong>Hold on!</strong> You have already submitted an application recently. Please wait for us to review it!</p>
            </div>
            <p><a href="/" class="btn">Return Home</a></p>

        <?php elseif ($message_sent): ?>
            <div class="alert-message success">
                <p><strong>Application Sent!</strong> Thank you, <?php echo $current_site_user; ?>. We'll be in touch!</p>
            </div>
            <p><a href="/" class="btn">Return Home</a></p>

        <?php else: ?>
            <form method="POST" action="" id="appForm">
                <fieldset>
                    <div class="clearfix">
                        <label>BirdChirp User</label>
                        <div class="input">
                            <input class="xlarge disabled" type="text" value="<?php echo $current_site_user; ?>" disabled>
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="discord_tag">Discord Username</label>
                        <div class="input">
                            <input class="xlarge" id="discord_tag" name="discord_tag" type="text" placeholder="exampleusername" required>
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="email">Contact Email</label>
                        <div class="input">
                            <input class="xlarge" id="email" name="email" type="email" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="age">Your Age</label>
                        <div class="input">
                            <input class="small" id="age" name="age" type="number" min="13" required>
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="reason">Why pick you?</label>
                        <div class="input">
                            <textarea class="xxlarge" id="reason" name="reason" rows="5" required></textarea>
                        </div>
                    </div>

                    <div>
                        <button type="submit" id="submitBtn" class="btn primary">Submit Staff Application</button>
                        <button type="reset" class="btn">Cancel</button>
                    </div>
                </fieldset>
            </form>

            <script>
                document.getElementById('appForm').onsubmit = function() {
                    var btn = document.getElementById('submitBtn');
                    btn.innerHTML = "Loading...";
                    btn.classList.add('disabled');
                    btn.style.pointerEvents = 'none'; 
                };
            </script>
        <?php endif; ?>
    </div>
</div>

<?php require_once "../footer.php"; ?>