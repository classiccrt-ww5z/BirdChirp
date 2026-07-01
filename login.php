<?php 
require_once "database/config.php"; 
require_once "header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Login</h1>
    </div>

    <div class="row">
        <div class="span7">
            <form action="/backend/auth/login_handler.php" method="POST" class="form-stacked">
                <fieldset style="padding-top: 0;">
                    <div class="clearfix">
                        <label for="email" style="font-weight: bold; font-size: 14px;">Email Address</label>
                        <div class="input">
                            <input class="xlarge" id="email" name="email" type="text" placeholder="name@example.com" style="height: 28px; width: 370px;">
                        </div>
                    </div>

                    <div class="clearfix">
                        <label for="password" style="font-weight: bold; font-size: 14px;">Password</label>
                        <div class="input">
                            <input class="xlarge" id="password" name="password" type="password" placeholder="Password" style="height: 28px; width: 370px;">
                        </div>
                    </div>
                    <div class="clearfix">
                        <label class="checkbox">
                            <input type="checkbox" name="remember" value="1"> Remember me
                        </label>
                    </div>
                    <div style="margin-top: 20px; width: 300px;">
                        <button type="submit" class="btn primary">Login</button>
                    </div>
                </fieldset>
            </form>
        </div>

        <div class="span5">
            <div style="padding-top: 20px; border-left: 1px solid #eee; padding-left: 20px; min-height: 150px;">
                <p style="font-size: 13px; color: #444; margin-bottom: 10px;">
                    Welcome back! Please enter your credentials to access your account.
                    </p>
                <p style="font-size: 12px; color: #777; line-height: 1.5;">
                    If you are having trouble logging in, please contact support or reset your password.
                </p>
                <p>
                <strong>Reminder:</strong> You must sign in with your email.
</p>
            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php"; ?>
