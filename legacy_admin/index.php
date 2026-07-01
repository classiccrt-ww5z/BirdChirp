<?php
require_once "header.php";

$users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$bans  = $pdo->query("SELECT COUNT(*) FROM bans")->fetchColumn();
$memoryLimitRaw = ini_get('memory_limit');
$memoryUsageMB = round(memory_get_usage(true)/1024/1024,2);
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$stmt = $pdo->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = ?");
$stmt->execute([$dbName]);
$dbSizeMB = round($stmt->fetchColumn() ?? 0, 2);
?>
<div class="container">
<ul class="pills" id="tabs">
<li class="active"><a href="#overview">Overview</a></li>
<li><a href="#server">Server</a></li>
<li><a href="#php">PHP</a></li>
<li><a href="#database">Database</a></li>
</ul>
<div class="tab-content" id="overview">
<div class="row">
<div class="span6">
<div class="well">
<h4>Site Overview</h4>
<ul class="unstyled">
<li>Users: <?= $users ?></li>
<li>Posts: <?= $posts ?></li>
<li>Bans: <?= $bans ?></li>
<li>Database Size: <?= $dbSizeMB ?> MB</li>
</ul>
</div>
</div>
<div class="span6">
<div class="well">
<h4>Database Usage</h4>
<canvas id="dbChart"></canvas>
</div>
</div>
</div>
</div>
<div class="tab-content" id="php" style="display:none;">
<div class="row">
<div class="span6">
<div class="well">
<h4>PHP Info</h4>
<ul class="unstyled">
<li>Memory Limit: <?= $memoryLimitRaw ?></li>
<li>Memory Used: <?= $memoryUsageMB ?> MB</li>
<li>Upload Max: <?= ini_get("upload_max_filesize") ?></li>
<li>Post Max: <?= ini_get("post_max_size") ?></li>
<li>Max Execution Time: <?= ini_get("max_execution_time") ?>s</li>
</ul>
</div>
</div>
<div class="span6">
<div class="well">
<h4>Memory Usage</h4>
<canvas id="memoryChart"></canvas>
</div>
</div>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function showTab(id){
document.querySelectorAll(".tab-content").forEach(el=>{
el.style.display="none";
});
document.querySelectorAll("#tabs li").forEach(li=>{
li.classList.remove("active");
});
let active = document.querySelector(id);
if(active) active.style.display="block";
let link = document.querySelector('#tabs a[href="'+id+'"]');
if(link) link.parentNode.classList.add("active");
}
document.querySelectorAll("#tabs a").forEach(a=>{
a.addEventListener("click", function(e){
e.preventDefault();
showTab(this.getAttribute("href"));
history.replaceState(null,null,this.getAttribute("href"));
});
});
if(window.location.hash){
showTab(window.location.hash);
} else {
showTab("#overview");
}

<?php
$memLimit = ini_get('memory_limit');
$memLimitMB = 0;
if(preg_match('/^(\d+)([KMG])$/i',$memLimit,$matches)){
$value = (int)$matches[1];
$unit = strtoupper($matches[2]);

if($unit=="K") $memLimitMB = $value/1024;
if($unit=="M") $memLimitMB = $value;
if($unit=="G") $memLimitMB = $value*1024;
}
?>
new Chart(document.getElementById('memoryChart'),{
type:'pie',
data:{
labels:['Used MB','Limit MB'],
datasets:[{
data:[<?= $memoryUsageMB ?>, <?= $memLimitMB ?>]
}]
}
});
new Chart(document.getElementById('dbChart'),{
type:'doughnut',
data:{
labels:['Used MB'],
datasets:[{
data:[<?= $dbSizeMB ?>]
}]
}
});
</script>
<?php require_once "footer.php"; ?>