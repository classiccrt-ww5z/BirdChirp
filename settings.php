<?php 
require_once "header.php"; 
requireLogin(); 
require_once "functions/users.php";
require_once "functions/security.php";

$user = getUserById($_SESSION['user_id']);
$tab = $_GET['tab'] ?? 'profile';

$bio = htmlspecialchars($user['bio'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$username = htmlspecialchars($user['username'] ?? '');
$display_name = htmlspecialchars($user['display_name'] ?? '');
$avatar = $user['avatar'] ?? 'default.png';

$csrfToken = generateCSRF();
?>

<div class="container">
<div class="page-header">
<h1>Settings</h1>
</div>

<div class="tabbable">
<ul class="tabs">
<li class="<?= $tab == 'profile' ? 'active' : '' ?>"><a href="settings.php?tab=profile">Edit Profile</a></li>
<li class="<?= $tab == 'pfp' ? 'active' : '' ?>"><a href="settings.php?tab=pfp">Profile Picture</a></li>
<li class="<?= $tab == 'banner' ? 'active' : '' ?>"><a href="settings.php?tab=banner">Profile Banner</a></li>
<li class="<?= $tab == 'css' ? 'active' : '' ?>"><a href="settings.php?tab=css">Custom CSS</a></li>
<li class="<?= $tab == 'security' ? 'active' : '' ?>"><a href="settings.php?tab=security">Account Security</a></li>
</ul>
</div>

<?php if($tab == 'profile'): ?>
<form action="/backend/users/update_profile.php" method="POST" class="form-stacked">
<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
<fieldset>
<legend>Profile Editing</legend>
<div class="clearfix">
<label>Display Name</label>
<div class="input">
<input type="text" class="xlarge" name="display_name" value="<?= $display_name ?>">
</div>
</div>
<div class="clearfix">
<label>Bio</label>
<div class="input">
<textarea class="xxlarge" name="bio" rows="4"><?= $bio ?></textarea>
</div>
</div>
</fieldset>
<button type="submit" class="btn primary">Save Profile</button>
</div>
</form>
<?php endif; ?>

<?php if($tab == 'pfp'): ?>
<form class="form-stacked">
<fieldset>
<legend>Profile Picture</legend>
<div class="clearfix">
<label>Current</label>
<div class="input">
<img src="/images/avatars/<?= htmlspecialchars($avatar) ?>" class="thumbnail" id="current-avatar" style="width:160px;height:160px;">
</div>
</div>
</fieldset>
</form>

<div id="preview-section" style="display:none;">
<img id="preview-pfp" class="thumbnail" style="width:160px;height:160px;">
<br>
<button type="button" class="btn success" onclick="savePfp()">Save Avatar</button>
<button type="button" class="btn" onclick="cancelPfp()">Cancel</button>
</div>

<form class="form-stacked" style="margin-top:20px;">
<fieldset>
<legend>Upload New</legend>
<div class="clearfix">
<label>Select Image</label>
<div class="input">
<input type="file" id="avatar-input" name="avatar" accept="image/*">
</div>
</div>
</fieldset>
</form>
<script>
var csrfToken = '<?= $csrfToken ?>';

document.getElementById('avatar-input').addEventListener('change',function(){
    var file=this.files[0];
    if(!file)return;
    if(!file.type.match(/image\/(jpeg|png|gif|webp)/)){
        alert('Please select JPG, PNG, GIF, or WebP');
        return;
    }
    var fd=new FormData();
    fd.append('avatar',file);
    fd.append('csrf_token',csrfToken);
    fd.append('ajax','1');
    document.getElementById('avatar-input').disabled=true;
    fetch('/backend/users/upload_avatar.php',{method:'POST',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){
        document.getElementById('avatar-input').disabled=false;
        try{
            var d=JSON.parse(t);
            if(d.success){
                document.getElementById('preview-pfp').src=d.preview_url;
                document.getElementById('preview-section').style.display='block';
            }else{alert(d.message||'Upload failed');}
        }catch(e){
            alert('Error: '+t);
        }
    })
    .catch(function(e){
        document.getElementById('avatar-input').disabled=false;
        alert('Network error');
    });
});
function savePfp(){
    var fd=new FormData();
    fd.append('csrf_token',csrfToken);
    fd.append('ajax','1');
    fetch('/backend/users/save_avatar.php',{method:'POST',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){
        try{
            var d=JSON.parse(t);
            if(d.success){
                document.getElementById('current-avatar').src='/images/avatars/'+d.avatar;
                document.getElementById('preview-section').style.display='none';
            }else{alert(d.message||'Save failed');}
        }catch(e){alert('Error: '+t);}
    });
}
function cancelPfp(){
    fetch('/backend/users/cancel_avatar.php?ajax=1').then(function(){location.reload();});
}
</script>
<?php endif; ?>

<?php if($tab == 'banner'): ?>
<form class="form-stacked">
<fieldset>
<legend>Profile Banner</legend>
<div class="clearfix">
<label>Current</label>
<div class="input">
<img src="/images/banners/<?= htmlspecialchars($user['banner'] ?? 'default.png') ?>" class="thumbnail" id="current-banner" style="width:500px;height:150px;object-fit:cover;">
</div>
</div>
</fieldset>
</form>

<div id="banner-preview-section" style="display:none;">
<img id="preview-banner" class="thumbnail" style="width:500px;height:150px;object-fit:cover;">
<br><br>
<button type="button" class="btn success" onclick="saveBanner()">Save Banner</button>
<button type="button" class="btn" onclick="cancelBanner()">Cancel</button>
</div>

<form class="form-stacked" style="margin-top:20px;">
<fieldset>
<legend>Upload New</legend>
<div class="clearfix">
<label>Select Image</label>
<div class="input">
<input type="file" id="banner-input" name="banner" accept="image/*">
</div>
</div>
</fieldset>
</form>
<script>
var csrfToken = '<?= $csrfToken ?>';

document.getElementById('banner-input').addEventListener('change',function(){
    var file=this.files[0];
    if(!file)return;
    if(!file.type.match(/image\/(jpeg|png|gif|webp)/)){
        alert('Please select JPG, PNG, GIF, or WebP');
        return;
    }
    var fd=new FormData();
    fd.append('banner',file);
    fd.append('csrf_token',csrfToken);
    fd.append('ajax','1');
    document.getElementById('banner-input').disabled=true;
    fetch('/backend/users/upload_banner.php',{method:'POST',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){
        document.getElementById('banner-input').disabled=false;
        try{
            var d=JSON.parse(t);
            if(d.success){
                document.getElementById('preview-banner').src=d.preview_url;
                document.getElementById('banner-preview-section').style.display='block';
            }else{alert(d.message||'Upload failed');}
        }catch(e){
            alert('Error: '+t);
        }
    })
    .catch(function(e){
        document.getElementById('banner-input').disabled=false;
        alert('Network error');
    });
});
function saveBanner(){
    var fd=new FormData();
    fd.append('csrf_token',csrfToken);
    fd.append('ajax','1');
    fetch('/backend/users/save_banner.php',{method:'POST',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){
        try{
            var d=JSON.parse(t);
            if(d.success){
                document.getElementById('current-banner').src='/images/banners/'+d.banner;
                document.getElementById('banner-preview-section').style.display='none';
            }else{alert(d.message||'Save failed');}
        }catch(e){alert('Error: '+t);}
    });
}
function cancelBanner(){
    fetch('/backend/users/cancel_banner.php?ajax=1').then(function(){location.reload();});
}
</script>
<?php endif; ?>

<?php if($tab == 'css'): ?>
<form action="/backend/users/update_css.php" method="POST" class="form-stacked">
<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
<fieldset>
<legend>Custom Profile CSS</legend>
<div class="clearfix">
<label>Your CSS</label>
<div class="input">
<textarea class="xxlarge" name="custom_css" rows="12" style="font-family:monospace;font-size:12px;width:500px;"><?= htmlspecialchars($user['custom_css'] ?? '') ?></textarea>
<span class="help-block">This CSS will be applied to your profile page. <strong>Only use on profiles you trust!</strong> Basic styling only (colors, fonts, backgrounds).</span>
</div>
</div>
</fieldset>
<button type="submit" class="btn primary">Save CSS</button>
</form>
<?php endif; ?>

<?php if($tab == 'security'): ?>
<form action="/backend/users/update_security.php" method="POST" class="form-stacked">
<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
<fieldset>
<legend>Security</legend>
<div class="clearfix">
<label>Email Address</label>
<div class="input">
<input type="text" class="xlarge" value="<?= $email ?>" disabled>
</div>
</div>
<div class="clearfix">
<label>Username</label>
<div class="input">
<input type="text" class="xlarge" name="username" value="<?= $username ?>">
</div>
</div>
</fieldset>

<fieldset style="margin-top:20px;">
<legend>Change Password</legend>
<div class="clearfix">
<label>Current Password</label>
<div class="input">
<input type="password" class="xlarge" name="current_password">
</div>
</div>
<div class="clearfix">
<label>New Password</label>
<div class="input">
<input type="password" class="xlarge" name="new_password">
</div>
</div>
<div class="clearfix">
<label>Confirm Password</label>
<div class="input">
<input type="password" class="xlarge" name="confirm_password">
</div>
</div>
</fieldset>
<button type="submit" class="btn primary">Update Security</button>
</div>
</form>
<?php endif; ?>

<?php showMessage(); ?>

</div>

<?php require_once "footer.php"; ?>