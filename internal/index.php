<?php
require_once "header.php";

$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$stmt = $pdo->prepare("SELECT TABLE_NAME, ENGINE, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as SIZE_MB FROM information_schema.tables WHERE table_schema = ? ORDER BY TABLE_NAME");
$stmt->execute([$dbName]);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalSize = array_sum(array_column($tables, 'SIZE_MB'));
$totalRows = array_sum(array_column($tables, 'TABLE_ROWS'));
$phpVersion = phpversion();
$memoryLimit = ini_get('memory_limit');
$uploadMax = ini_get('upload_max_filesize');
$maxExec = ini_get('max_execution_time') . 's';
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
?>

<div class="alert warning"><b>Warning:</b> This panel provides direct database access. Changes made here cannot be undone. Be careful.</div>

<div class="flex">
<div class="box flex-2">
<h5>System Info</h5>
<div class="box-inner">
<table>
<tr><td style="font-weight:bold;">Database</td><td><?=e($dbName)?></td></tr>
<tr><td style="font-weight:bold;">Tables</td><td><?=count($tables)?></td></tr>
<tr><td style="font-weight:bold;">Total Rows</td><td><?=number_format($totalRows)?></td></tr>
<tr><td style="font-weight:bold;">Total Size</td><td><?=number_format($totalSize, 2)?> MB</td></tr>
<tr><td style="font-weight:bold;">PHP Version</td><td><?=e($phpVersion)?></td></tr>
<tr><td style="font-weight:bold;">Memory Limit</td><td><?=e($memoryLimit)?></td></tr>
<tr><td style="font-weight:bold;">Upload Max</td><td><?=e($uploadMax)?></td></tr>
<tr><td style="font-weight:bold;">Max Execution</td><td><?=e($maxExec)?></td></tr>
<tr><td style="font-weight:bold;">Server</td><td><?=e($serverSoftware)?></td></tr>
</table>
</div></div>

<div class="box flex-2">
<h5>Database Tables (<?=count($tables)?>)</h5>
<div class="box-inner">
<table>
<thead><tr><th>Table</th><th>Engine</th><th>Rows</th><th>Size</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($tables as $t): ?>
<tr>
  <td><b><?=e($t['TABLE_NAME'])?></b></td>
  <td><?=e($t['ENGINE'])?></td>
  <td><?=number_format($t['TABLE_ROWS'])?></td>
  <td><?=number_format($t['SIZE_MB'], 2)?> MB</td>
  <td><a href="db_browser.php?table=<?=urlencode($t['TABLE_NAME'])?>">Browse</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div></div>

<?php require_once "footer.php"; ?>