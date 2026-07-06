<?php require_once "header.php"; ?>
<div class="container">
<div class="page-header">
<h1>CSS Tutorial for <?php echo $SITE_NAME; ?></h1>
</div>

<div style="max-width:750px;font-size:14px;line-height:1.7;">

<div style="background:#e3f2fd;padding:14px;border-radius:4px;margin-bottom:16px;">
    <strong>Your profile page supports custom CSS.</strong> Any CSS you paste in
    <a href="/settings.php?tab=css">Settings &gt; Custom CSS</a> gets applied to your profile.
    This page teaches you how to write it.
</div>

<h2 id="basics">CSS Basics</h2>
<p>CSS is a list of <strong>rules</strong>. Each rule has three parts:</p>

<div style="background:#f5f5f5;padding:14px;border-radius:4px;margin-bottom:16px;font-family:monospace;font-size:15px;">
    selector {<br>
    &nbsp;&nbsp;&nbsp;&nbsp;property: value;<br>
    }
</div>

<ul>
    <li><strong>selector</strong>  -  what to change (e.g. the banner, your name, the sidebar)</li>
    <li><strong>property</strong>  -  how to change it (color, size, background, border, font)</li>
    <li><strong>value</strong>  -  what to set it to (a color like <code>#ff0000</code>, a size like <code>20px</code>)</li>
</ul>

<p>Example  -  make your name bright blue and bigger:</p>
<div style="background:#f5f5f5;padding:14px;border-radius:4px;margin-bottom:16px;font-family:monospace;font-size:15px;">
    .profile-banner-info h1 {<br>
    &nbsp;&nbsp;&nbsp;&nbsp;color: #0069d6;<br>
    &nbsp;&nbsp;&nbsp;&nbsp;font-size: 28px;<br>
    }
</div>

<h2 id="selectors">Selectors  -  Picking the Part to Change</h2>
<p>Every part of your profile has a <strong>class</strong> or <strong>tag</strong> name you can target:</p>

<table class="zebra-striped" style="width:100%;margin-bottom:16px;">
    <tr><th style="width:40%;">Selector</th><th>What it changes</th></tr>
    <tr><td><code>body</code></td><td>The whole page background</td></tr>
    <tr><td><code>.profile-banner-wrap</code></td><td>The banner area (image + overlay)</td></tr>
    <tr><td><code>.profile-banner-info h1</code></td><td>Your display name on the banner</td></tr>
    <tr><td><code>.profile-handle</code></td><td>Your @username below the name</td></tr>
    <tr><td><code>.profile-avatar img</code></td><td>Your profile picture</td></tr>
    <tr><td><code>.profile-banner-stats .btn</code></td><td>The stat buttons (Posts, Followers, Following)</td></tr>
    <tr><td><code>.profile-module</code></td><td>Sidebar boxes (Joined date, Friends)</td></tr>
    <tr><td><code>.tabs</code></td><td>The tab bar (Posts, Replies, Media, Likes)</td></tr>
    <tr><td><code>.tabs li a</code></td><td>Individual tab links</td></tr>
    <tr><td><code>.tabs .active a</code></td><td>The currently selected tab</td></tr>
    <tr><td><code>.post-item</code></td><td>Each post in your feed</td></tr>
    <tr><td><code>.post-actions a</code></td><td>Reply and Like links on posts</td></tr>
    <tr><td><code>.btn</code></td><td>All buttons (Follow, Edit Profile, tabs, stats)</td></tr>
</table>

<div style="background:#fff3e0;padding:12px;border-radius:4px;margin-bottom:16px;">
    <strong>Tip:</strong> Press <strong>F12</strong> in your browser, click the arrow icon, then click any part of your profile. The inspector shows you the class names you can use.
</div>

<h2 id="properties">Properties  -  What to Change About It</h2>

<table class="zebra-striped" style="width:100%;margin-bottom:16px;">
    <tr><th style="width:30%;">Property</th><th style="width:30%;">Example value</th><th>What it does</th></tr>
    <tr><td><code>color</code></td><td><code>#ff0000</code>, <code>red</code>, <code>#333</code></td><td>Text color</td></tr>
    <tr><td><code>background</code></td><td><code>#f0e6d3</code>, <code>black</code></td><td>Background color</td></tr>
    <tr><td><code>font-size</code></td><td><code>16px</code>, <code>24px</code></td><td>How big the text is</td></tr>
    <tr><td><code>font-weight</code></td><td><code>bold</code>, <code>600</code>, <code>normal</code></td><td>Boldness of text</td></tr>
    <tr><td><code>font-family</code></td><td><code>Georgia, serif</code>, <code>monospace</code></td><td>Which font to use</td></tr>
    <tr><td><code>border</code></td><td><code>2px solid red</code></td><td>Outline around an element (width, style, color)</td></tr>
    <tr><td><code>border-radius</code></td><td><code>50%</code>, <code>8px</code></td><td>Roundness of corners (50% makes a circle)</td></tr>
    <tr><td><code>width</code></td><td><code>100px</code>, <code>50%</code></td><td>How wide something is</td></tr>
    <tr><td><code>height</code></td><td><code>400px</code>, <code>auto</code></td><td>How tall something is</td></tr>
    <tr><td><code>margin</code></td><td><code>10px</code>, <code>0 auto</code></td><td>Space <strong>outside</strong> an element</td></tr>
    <tr><td><code>padding</code></td><td><code>8px 12px</code></td><td>Space <strong>inside</strong> an element</td></tr>
    <tr><td><code>text-shadow</code></td><td><code>0 1px 3px rgba(0,0,0,0.5)</code></td><td>Shadow behind text</td></tr>
    <tr><td><code>box-shadow</code></td><td><code>0 2px 8px rgba(0,0,0,0.2)</code></td><td>Shadow behind the element box</td></tr>
    <tr><td><code>text-align</code></td><td><code>center</code>, <code>left</code>, <code>right</code></td><td>Text alignment</td></tr>
</table>

<h2 id="colors">Colors</h2>
<p>Colors can be written several ways:</p>
<table class="zebra-striped" style="width:100%;margin-bottom:16px;">
    <tr><th style="width:25%;">Format</th><th style="width:30%;">Example</th><th>Notes</th></tr>
    <tr><td>Hex (6-digit)</td><td><code>#ff0000</code></td><td>red, green, blue  -  <code>#ff0000</code> is bright red</td></tr>
    <tr><td>Hex (3-digit)</td><td><code>#f00</code></td><td>Shorthand  -  same as <code>#ff0000</code></td></tr>
    <tr><td>Named</td><td><code>red</code>, <code>blue</code>, <code>black</code>, <code>gold</code></td><td>Easy but limited choices</td></tr>
    <tr><td>rgb()</td><td><code>rgb(255, 0, 0)</code></td><td>Same as hex but with numbers 0-255</td></tr>
    <tr><td>rgba()</td><td><code>rgba(0, 0, 0, 0.5)</code></td><td>Like rgb() but with transparency (0 = invisible, 1 = solid)</td></tr>
</table>

<h2 id="important">When a Rule Isnt Sticking</h2>
<p>If you write CSS and it doesnt change anything, add <code>!important</code> before the semicolon:</p>
<div style="background:#f5f5f5;padding:14px;border-radius:4px;margin-bottom:16px;font-family:monospace;font-size:15px;">
    body {<br>
    &nbsp;&nbsp;&nbsp;&nbsp;background: #f0e6d3 <strong>!important</strong>;<br>
    }
</div>
<p>Some styles are built-in and <code>!important</code> overrides them.</p>

<h2 id="examples">Full Examples</h2>

<div style="background:#f5f5f5;padding:14px;border-radius:4px;margin-bottom:12px;font-family:monospace;font-size:13px;line-height:1.6;">
    /* Warm theme  -  brown sidebar, cream background */<br>
    body { background: #f0e6d3 !important; }<br>
    .profile-module { background: #faf3e0; border-color: #d4a574; }<br>
    .tabs li a { background: #faf3e0; color: #5d4037; }<br>
    .tabs .active a { background: #fff !important; border-color: #d4a574 !important; }<br>
    .post-item { border-color: #d4a574 !important; }
</div>

<div style="background:#f5f5f5;padding:14px;border-radius:4px;margin-bottom:16px;font-family:monospace;font-size:13px;line-height:1.6;">
    /* Dark theme */<br>
    body { background: #1a1a2e !important; }<br>
    .profile-module { background: #16213e; border-color: #0f3460; }<br>
    .profile-module .module-body { color: #ccc; }<br>
    .tabs li a { background: #16213e; color: #aaa; }<br>
    .post-item { background: #16213e; border-color: #0f3460 !important; color: #ccc; }
</div>

<h2 id="practice">Practice</h2>
<p>Open <a href="/settings.php?tab=css">Settings &gt; Custom CSS</a>, paste one of the examples above,
hit <strong>Save CSS</strong>, then check your profile. Tweak the colors and sizes and save again.
Every change shows up instantly.</p>

<p style="margin-top:30px;">
    <a href="/settings.php?tab=css" class="btn primary large">Go to Custom CSS Settings</a>
    <a href="/settings.php" class="btn large" style="margin-left:6px;">Back to Settings</a>
</p>

</div>
</div>
<?php require_once "footer.php"; ?>
