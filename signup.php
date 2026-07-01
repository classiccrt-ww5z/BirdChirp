<?php 
require_once "database/config.php"; 
require_once "header.php";

$allowSignup = '1';
$requireBirthdate = '1';
$minAge = 14;

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if ($row[0] === 'allow_signup') $allowSignup = $row[1];
        if ($row[0] === 'require_birthdate') $requireBirthdate = $row[1];
        if ($row[0] === 'min_age') $minAge = (int)$row[1];
    }
} catch (PDOException $e) {}

if ($allowSignup !== '1') {
    echo "<div class='container' style='padding:50px; text-align:center;'>";
    echo "<h2>Signups are currently disabled</h2>";
    echo "<p>New registrations are not allowed at this time.</p>";
    echo "</div>";
    require_once "footer.php";
    exit;
}
?>

<div class="container">
    <div class="page-header">
        <h1>Create your account!</h1>
    </div>

    <div class="row">
        <div class="span7">
            <form action="/backend/auth/signup_handler.php" method="POST" class="form-stacked">
                <fieldset style="padding-top: 0;">
                    <div class="clearfix">
                        <label for="username" style="font-weight: bold; font-size: 14px;">Username</label>
                        <div class="input">
                            <input class="xlarge" id="username" name="username" type="text" placeholder="Username" style="height: 28px; width: 370px;" required>
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="email" style="font-weight: bold; font-size: 14px;">Email Address</label>
                        <div class="input">
                            <input class="xlarge" id="email" name="email" type="email" placeholder="Email Address" style="height: 28px; width: 370px;" required>
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="password" style="font-weight: bold; font-size: 14px;">Password</label>
                        <div class="input">
                            <input class="xlarge" id="password" name="password" type="password" placeholder="Password" style="height: 28px; width: 370px;" required>
                        </div>
                    </div>

                    <?php if ($requireBirthdate === '1'): ?>
                    <div class="clearfix">
                        <label style="font-weight: bold; font-size: 14px;">Birthday</label>
                        <div class="input" style="display: flex; gap: 10px;">
                            <select name="birth_year" id="birth_year" style="height: 28px; width: 100px;" required>
                                <option value="">Year</option>
                            </select>
                            <select name="birth_month" id="birth_month" style="height: 28px; width: 100px;" required>
                                <option value="">Month</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                            <select name="birth_day" id="birth_day" style="height: 28px; width: 80px;" required>
                                <option value="">Day</option>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="birth_year" value="2000">
                    <input type="hidden" name="birth_month" value="1">
                    <input type="hidden" name="birth_day" value="1">
                    <?php endif; ?>
                    <script>
                    (function() {
                        var yearSelect = document.getElementById('birth_year');
                        var daySelect = document.getElementById('birth_day');
                        var currentYear = new Date().getFullYear();
                        for (var y = currentYear; y >= 1900; y--) {
                            var opt = document.createElement('option');
                            opt.value = y;
                            opt.text = y;
                            yearSelect.appendChild(opt);
                        }
                        for (var d = 1; d <= 31; d++) {
                            var opt = document.createElement('option');
                            opt.value = d;
                            opt.text = d;
                            daySelect.appendChild(opt);
                        }
                    })();
                    </script>

                    <div style="display:none !important; visibility:hidden;">
                        <input type="text" name="haha" tabindex="-1" autocomplete="off">
                    </div>

                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACyK8IUiS3lozik9"></div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn primary">Create Account</button>
                    </div>
                </fieldset>
            </form>
        </div>

        <div class="span5">
            <div style="padding-top: 20px; border-left: 1px solid #eee; padding-left: 20px; min-height: 200px;">
                <p style="font-size: 13px; color: #444; margin-bottom: 10px;">
                    By signing up, you agree to our 
                    <a href="/about/rules" style="text-decoration: underline;">community guidelines</a> and 
                    <a href="/about/TOS" style="text-decoration: underline;">terms of service</a>.
                </p><p>
                <strong>READ THIS:</strong> You must sign in with EMAIL when you get to the sign in screen.
                </p>
                <p style="font-size: 12px; color: #777; line-height: 1.5;">
                     
                <strong>Notice:</strong> you must be at least <?= $minAge ?> years of age before creating an account.
                </p>

            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php"; ?>
