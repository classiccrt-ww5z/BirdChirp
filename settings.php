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
<div style="margin-bottom:8px;">
    <button type="button" class="btn small" id="btn-easy" onclick="showPanel('easy')">Easy Mode</button>
    <button type="button" class="btn small" id="btn-tips" onclick="showPanel('tips')">Examples</button>
    <a href="/tutorial.php" class="btn small">Tutorial</a>
</div>
<div class="clearfix">
<label>Your CSS</label>
<div class="input">
<textarea class="xxlarge" name="custom_css" rows="12" style="font-family:monospace;font-size:12px;width:500px;" id="css-input"><?= htmlspecialchars($user['custom_css'] ?? '') ?></textarea>

<div id="panel-tips" style="display:none;margin-top:10px;font-size:13px;line-height:1.8;background:#f9f9f9;padding:14px;border-radius:4px;border:1px solid #ddd;">
    <div style="margin-bottom:12px;font-weight:600;">Click any example to load it into the editor above:</div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:4px 12px;align-items:center;">
        <code>body { background: #f0e6d3 !important; }</code>
        <button type="button" class="btn small" onclick="fillCss('body { background: #f0e6d3 !important; }')">Load</button>
        <code>.profile-banner-wrap { height: 400px; }</code>
        <button type="button" class="btn small" onclick="fillCss('.profile-banner-wrap { height: 400px; }')">Load</button>
        <code>.profile-avatar img { border-radius: 50%; border-color: red; }</code>
        <button type="button" class="btn small" onclick="fillCss('.profile-avatar img { border-radius: 50%; border-color: red; }')">Load</button>
        <code>.profile-banner-info h1 { color: #ff0; font-size: 30px; }</code>
        <button type="button" class="btn small" onclick="fillCss('.profile-banner-info h1 { color: #ff0; font-size: 30px; }')">Load</button>
        <code>.profile-module { background: #faf3e0; border-color: #d4a574; }</code>
        <button type="button" class="btn small" onclick="fillCss('.profile-module { background: #faf3e0; border-color: #d4a574; }')">Load</button>
        <code>.tabs li a { background: #eee; color: #333; }</code>
        <button type="button" class="btn small" onclick="fillCss('.tabs li a { background: #eee; color: #333; }')">Load</button>
        <code>.post-item { border-color: #ccc !important; }</code>
        <button type="button" class="btn small" onclick="fillCss('.post-item { border-color: #ccc !important; }')">Load</button>
        <code>.post-actions a { color: #888; }</code>
        <button type="button" class="btn small" onclick="fillCss('.post-actions a { color: #888; }')">Load</button>
        <code>.btn { background: #333; color: #fff; }</code>
        <button type="button" class="btn small" onclick="fillCss('.btn { background: #333; color: #fff; }')">Load</button>
    </div>
    <div style="margin-top:12px;padding-top:10px;border-top:1px solid #ddd;font-size:12px;color:#888;">
        Tip: Use your browser's inspector (F12) to find more class names. Add <code>!important</code> if a rule isnt sticking.
    </div>
</div>

<div id="panel-easy" style="display:none;margin-top:10px;font-size:13px;background:#f9f9f9;padding:14px;border-radius:4px;border:1px solid #ddd;">
    <div style="margin-bottom:10px;font-weight:600;">Pick options and hit Generate - no CSS knowledge needed!</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Theme preset</label>
            <select id="easy-theme" style="width:100%;" onchange="applyThemePreset()">
                <option value="">Custom</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
                <option value="warm">Warm</option>
                <option value="ocean">Ocean</option>
                <option value="forest">Forest</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Page background</label>
            <input type="color" id="easy-bg" value="#ffffff" style="width:100%;height:30px;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Name color</label>
            <input type="color" id="easy-name-color" value="#333333" style="width:100%;height:30px;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Name font</label>
            <select id="easy-font" style="width:100%;">
                <option value="">Default</option>
                <option value="Georgia, serif">Serif</option>
                <option value="Courier New, monospace">Monospace</option>
                <option value="Comic Sans MS, cursive">Fun</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Sidebar background</label>
            <input type="color" id="easy-sidebar" value="#ffffff" style="width:100%;height:30px;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Tab background</label>
            <input type="color" id="easy-tab-bg" value="#f5f5f5" style="width:100%;height:30px;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Banner height</label>
            <select id="easy-banner-height" style="width:100%;">
                <option value="260">Short (260px)</option>
                <option value="320" selected>Medium (320px)</option>
                <option value="400">Tall (400px)</option>
                <option value="500">Full (500px)</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">PFP shape</label>
            <select id="easy-pfp" style="width:100%;">
                <option value="">Square</option>
                <option value="50%">Circle</option>
                <option value="12px">Rounded</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:2px;">Post border</label>
            <input type="color" id="easy-post-border" value="#eeeeee" style="width:100%;height:30px;">
        </div>
        <div style="display:flex;align-items:flex-end;">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;cursor:pointer;">
                <input type="checkbox" id="easy-round-banner" checked>
                Keep banner gradient overlay
            </label>
        </div>
    </div>
    <button type="button" class="btn primary" style="margin-top:12px;" onclick="generateEasyCss()">Generate CSS</button>
</div>
</div>
</fieldset>
<button type="submit" class="btn primary">Save CSS</button>
</form>
<script>
function showPanel(name){
    ['tips','easy'].forEach(function(n){
        var p = document.getElementById('panel-' + n);
        var b = document.getElementById('btn-' + n);
        if(p){ p.style.display = 'none'; }
        if(b){ b.className = 'btn small'; }
    });
    var p = document.getElementById('panel-' + name);
    var b = document.getElementById('btn-' + name);
    if(p && p.style.display !== 'block'){
        p.style.display = 'block';
        if(b){ b.className = 'btn small primary'; }
    }
}
function fillCss(css){
    var t = document.getElementById('css-input');
    t.value = t.value ? t.value + '\n' + css : css;
    t.focus();
}
function g(id){ return document.getElementById(id); }

var themePresets = {
    light:  { bg:'#ffffff', name:'#333333', sidebar:'#ffffff', tab:'#f5f5f5', post:'#eeeeee', font:'', banner:'320', pfp:'' },
    dark:   { bg:'#1a1a2e', name:'#ffffff', sidebar:'#16213e', tab:'#16213e', post:'#0f3460', font:'', banner:'320', pfp:'' },
    warm:   { bg:'#f0e6d3', name:'#5d4037', sidebar:'#faf3e0', tab:'#faf3e0', post:'#d4a574', font:'Georgia, serif', banner:'320', pfp:'' },
    ocean:  { bg:'#e3f2fd', name:'#01579b', sidebar:'#bbdefb', tab:'#bbdefb', post:'#90caf9', font:'', banner:'320', pfp:'' },
    forest: { bg:'#e8f5e9', name:'#1b5e20', sidebar:'#c8e6c9', tab:'#c8e6c9', post:'#a5d6a7', font:'', banner:'320', pfp:'' }
};
function applyThemePreset(){
    var v = g('easy-theme').value;
    if(!v) return;
    var p = themePresets[v];
    g('easy-bg').value = p.bg;
    g('easy-name-color').value = p.name;
    g('easy-sidebar').value = p.sidebar;
    g('easy-tab-bg').value = p.tab;
    g('easy-post-border').value = p.post;
    g('easy-font').value = p.font;
    g('easy-banner-height').value = p.banner;
    g('easy-pfp').value = p.pfp;
}
function generateEasyCss(){
    var lines = [];
    var bg = g('easy-bg').value;
    var nameColor = g('easy-name-color').value;
    var font = g('easy-font').value;
    var sidebar = g('easy-sidebar').value;
    var tabBg = g('easy-tab-bg').value;
    var bannerH = g('easy-banner-height').value;
    var pfp = g('easy-pfp').value;
    var postBorder = g('easy-post-border').value;
    var keepOverlay = g('easy-round-banner').checked;

    if(bg && bg !== '#ffffff'){
        lines.push('body { background: ' + bg + ' !important; }');
    }
    if(bannerH){
        lines.push('.profile-banner-wrap { height: ' + bannerH + 'px; }');
    }
    if(!keepOverlay){
        lines.push('.profile-banner-overlay { background: none !important; }');
    }
    if(nameColor && nameColor !== '#333333'){
        lines.push('.profile-banner-info h1 { color: ' + nameColor + '; }');
    }
    if(font){
        lines.push('.profile-banner-info h1 { font-family: ' + font + '; }');
    }
    if(pfp){
        lines.push('.profile-avatar img { border-radius: ' + pfp + '; }');
    }
    if(sidebar && sidebar !== '#ffffff'){
        lines.push('.profile-module { background: ' + sidebar + '; }');
    }
    if(tabBg && tabBg !== '#f5f5f5'){
        lines.push('.tabs li a { background: ' + tabBg + '; }');
    }
    if(postBorder && postBorder !== '#eeeeee'){
        lines.push('.post-item { border-color: ' + postBorder + ' !important; }');
    }

    var textarea = g('css-input');
    if(lines.length){
        textarea.value = lines.join('\n');
    } else {
        textarea.value = '/* Pick some options in Easy Mode and hit Generate! */';
    }
    textarea.focus();
}
</script>
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