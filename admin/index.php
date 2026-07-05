<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/messages.php';

if (!isLoggedIn() || ($_SESSION['admin'] ?? 0) != 1) { header("Location: /login.php"); exit; }
if (!isset($_SESSION['admin_verified'])) { header("Location: login.php"); exit; }

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, avatar, sysadmin FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$adminUser = $stmt->fetch();
$adminUsername = $adminUser ? $adminUser['username'] : 'Admin';
$adminAvatar = $adminUser ? $adminUser['avatar'] : 'default.png';
$isSysadmin = $adminUser ? (int)($adminUser['sysadmin'] ?? 0) : 0;

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard','users','posts','news','settings','ip_lookup','logs'];
if (!in_array($page, $allowedPages)) $page = 'dashboard';

$pageTitles = [
  'dashboard'=>'Dashboard','users'=>'Users','posts'=>'Posts','news'=>'News',
  'settings'=>'Settings','ip_lookup'=>'IP Lookup','logs'=>'Activity Log',
];
$headTitle = $pageTitles[$page] ?? 'Admin';
$icons = ['dashboard'=>'home','users'=>'user','posts'=>'list-alt','news'=>'bullhorn','settings'=>'cog','ip_lookup'=>'globe','logs'=>'time'];

if (isset($_GET['ajax'])) {
  $f = __DIR__ . "/frontend_admin_files/{$page}.php";
  if (file_exists($f)) { require $f; } else { echo '<p class="text-danger">Page not found.</p>'; }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=e($headTitle)?> - Admin</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<nav class="navbar navbar-inverse">
  <div class="container-fluid">
    <ul class="nav navbar-nav">
    <?php foreach($pageTitles as $k=>$v): ?>
      <li class="<?=$page===$k?'active':''?>"><a href="#" data-page="<?=$k?>"><span class="glyphicon glyphicon-<?=$icons[$k]?>"></span> <?=$v?></a></li>
    <?php endforeach; ?>
    <?php if($isSysadmin): ?>
      <li><a href="/internal/" class="sys-link"><span class="glyphicon glyphicon-wrench"></span> Internal</a></li>
    <?php endif; ?>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="/backend/auth/logout_handler.php"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="glyphicon glyphicon-user"></span> <?=e($adminUsername)?> <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a href="/"><span class="glyphicon glyphicon-home"></span> View Site</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>
<div class="container-fluid">
<div id="content">
<?php showMessage(true); ?>
<div id="loading"><div class="box"><span class="glyphicon glyphicon-refresh spin"></span><br>Loading...</div></div>

<?php
$f = __DIR__ . "/frontend_admin_files/{$page}.php";
if (file_exists($f)) { require $f; }
?>

</div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
var currentPage = '<?=$page?>';

$(document).on('click', '[data-page]', function(e){
  e.preventDefault();
  var p = $(this).data('page');
  if (p === currentPage) return;
  navigate(p);
});

function navigate(page, params){
  $('#loading').addClass('show');
  $('#content').addClass('loading');
  var url = 'index.php?ajax=1&page=' + page;
  if (params) url += '&' + params;
  var q = params ? '&' + params : '';
  $.get(url, function(html){
    $('#content').html(html).removeClass('loading');
    $('#loading').removeClass('show');
    currentPage = page;
    history.pushState({page: page, params: params || ''}, '', 'index.php?page=' + page + q);
    $('[data-page]').parent().removeClass('active');
    $('[data-page="' + page + '"]').parent().addClass('active');
    document.title = page.charAt(0).toUpperCase() + page.slice(1).replace(/_/g,' ') + ' - Admin';
  });
}

function loadPage(page, arg){
  if (typeof arg === 'string' && arg){ navigate(page, arg); return false; }
  if (arg && typeof arg === 'object'){ navigate(page, $(arg).serialize()); return false; }
  navigate(page); return false;
}

$(window).on('popstate', function(e){
  if (e.originalEvent.state && e.originalEvent.state.page){
    var p = e.originalEvent.state.page;
    var ps = e.originalEvent.state.params || '';
    $('#loading').addClass('show');
    $('#content').addClass('loading');
    var url = 'index.php?ajax=1&page=' + p + (ps ? '&' + ps : '');
    $.get(url, function(html){
      $('#content').html(html).removeClass('loading');
      $('#loading').removeClass('show');
      currentPage = p;
      $('[data-page]').parent().removeClass('active');
      $('[data-page="' + p + '"]').parent().addClass('active');
      document.title = p.charAt(0).toUpperCase() + p.slice(1).replace(/_/g,' ') + ' - Admin';
    });
  }
});
</script>
</body>
</html>
