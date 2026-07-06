<?php

require_once 'database/config.php';
require_once 'functions/messages.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = 0 LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $_SESSION['message'] = "Your email has been verified! Welcome to $SITE_NAME.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "That link is invalid or has already been used.";
        $_SESSION['message_type'] = "error";
    }
    header("Location: verify.php");
    exit;
}

if (isset($_POST['ajax_resend']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');

    $cooldown = 60;
    if (isset($_SESSION['last_resend_time']) && (time() - $_SESSION['last_resend_time']) < $cooldown) {
        $remaining = $cooldown - (time() - $_SESSION['last_resend_time']);
        echo json_encode(['status' => 'error', 'message' => "Please wait $remaining seconds before requesting another link."]);
        exit;
    }

    $newEmail = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$newEmail) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
        exit;
    }

    $newToken = bin2hex(random_bytes(32));
    try {
        $update = $pdo->prepare("UPDATE users SET email = ?, verification_token = ?, is_verified = 0 WHERE id = ?");
        $update->execute([$newEmail, $newToken, $_SESSION['user_id']]);
        
        $apiKey = getenv('MAILTRAP_API_KEY');
        if (!$apiKey) {
            echo json_encode(['status' => 'error', 'message' => 'Email sending is not configured. Contact the administrator.']);
            exit;
        }
        $url = 'https://send.api.mailtrap.io/api/send';
        $host = $_SERVER['HTTP_HOST'];
        $verificationLink = "http://$host/verify.php?token=" . $newToken;

        $data = [
            'from' => ['email' => 'verify@' . $_SERVER['HTTP_HOST'], 'name' => $SITE_NAME],
            'to' => [['email' => $newEmail]],
            'subject' => 'Verify Your Email',
            'html' => "<h1>Verify $SITE_NAME</h1><p>Click here to verify: <a href='$verificationLink'>$verificationLink</a></p>"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_exec($ch);
        curl_close($ch);

        $_SESSION['last_resend_time'] = time();

        echo json_encode(['status' => 'success', 'message' => 'A new link has been sent to <strong>' . htmlspecialchars($newEmail) . '</strong>']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'This email is already linked to another account.']);
    }
    exit;
}

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT email, is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Account | <?php echo $SITE_NAME; ?></title>
    <link rel="stylesheet" href="/css/bootstrap.css">
    <style>
        body { 
            background-color: #558B2F;
            padding-top: 40px;
        }
        .container-box {
            max-width: 460px;
            margin: 0 auto;
            padding: 30px;
            background-color: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }
        .logo-box { text-align: center; margin-bottom: 25px; }
        .spinner { display: none; margin-left: 5px; vertical-align: middle; }
    </style>
</head>
<body>

<div class="container">
    <div class="container-box">
        <div class="logo-box">
            <img src="/images/logos/birdchirpold.png" height="45" alt="<?php echo $SITE_NAME; ?>">
        </div>

        <div id="js-message">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($currentUser && $currentUser['is_verified'] == 0): ?>
            <h3 style="text-align:center; line-height: 1.2;">Check your inbox</h3>
            <p class="muted" style="text-align:center; margin-bottom: 20px;">We sent a verification link to your email. Didn't get it? You can update your email or try resending below.</p>
            
            <form id="resendForm">
                <label><strong>Email Address</strong></label>
                <input type="email" id="email" name="email" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                
                <div style="margin-top: 15px;">
                    <button type="submit" id="resendBtn" class="btn primary" style="width:100%;">
                        <span id="btnText">Resend Verification</span>
                        <span id="btnSpinner" class="spinner">...</span>
                    </button>
                </div>
            </form>

        <?php elseif ($currentUser && $currentUser['is_verified'] == 1): ?>
            <div class="text-center" style="text-align:center">
                <h2 class="text-success">Verified!</h2>
                <p>You're all set. Your account is now fully active.</p>
                <br>
                <a href="/" class="btn primary" style="display:block;text-align:center;">Go to Home</a>
            </div>

        <?php else: ?>
            <div class="text-center" style="text-align:center">
                <h2>Session Expired</h2>
                <p>Please log in again to verify your account.</p>
                <br>
                <a href="/login" class="btn primary" style="display:block;text-align:center;">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-1.9.1.min.js"></script>
<script>
$(document).on('click', '.close', function() { $(this).parent().hide(); });
</script>

<script>
$(document).ready(function() {
    var $form = $('#resendForm');
    var $msgDiv = $('#js-message');
    var $btnText = $('#btnText');
    var $btnSpinner = $('#btnSpinner');
    var $resendBtn = $('#resendBtn');

    if ($form.length) {
        $form.on('submit', function(e) {
            e.preventDefault();
            
            $resendBtn.addClass('disabled').attr('disabled', 'disabled');
            $btnText.text('Sending...');
            $btnSpinner.show();

            var emailVal = $('#email').val();

            $.ajax({
                url: 'verify.php',
                type: 'POST',
                data: {
                    ajax_resend: '1',
                    email: emailVal
                },
                dataType: 'json',
                success: function(data) {
                    var alertType = (data.status === 'success') ? 'alert-success' : 'alert-error';
                    
                    $msgDiv.html(
                        '<div class="alert ' + alertType + '">' +
                        '<button type="button" class="close" data-dismiss="alert">×</button>' +
                        data.message +
                        '</div>'
                    );

                    if (data.status === 'success') {
                        var timeLeft = 60;
                        var timer = setInterval(function() {
                            timeLeft--;
                            $btnText.text('Wait ' + timeLeft + 's');
                            if (timeLeft <= 0) {
                                clearInterval(timer);
                                $resendBtn.removeClass('disabled').removeAttr('disabled');
                                $btnText.text('Resend Verification');
                                $btnSpinner.hide();
                            }
                        }, 1000);
                    } else {
                        $resendBtn.removeClass('disabled').removeAttr('disabled');
                        $btnText.text('Resend Verification');
                        $btnSpinner.hide();
                    }
                },
                error: function() {
                    $resendBtn.removeClass('disabled').removeAttr('disabled');
                    $btnText.text('Resend Verification');
                    $btnSpinner.hide();
                    $msgDiv.html('<div class="alert alert-error"><button type="button" class="close" data-dismiss="alert">×</button>An error occurred. Please try again.</div>');
                }
            });
        });
    }
});
</script>

</body>
</html>