<?php
require_once "header.php";

$internalCsrf = generateCSRF();
$table = $_GET['table'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$editId = $_GET['edit'] ?? null;

$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ? AND TABLE_NAME != 'adminpassword' ORDER BY TABLE_NAME");
$stmt->execute([$dbName]);
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$hiddenTables = ['adminpassword'];
$hiddenCols = ['password'];

$primaryKey = null;
$allColumns = [];
$pkValue = null;

if ($table && in_array($table, $tables)) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $allColumns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $displayColumns = array_filter($allColumns, fn($c) => !in_array(strtolower($c['Field']), $hiddenCols));
    $displayColNames = array_map(fn($c) => $c['Field'], $displayColumns);

    $pkRows = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
    if (count($pkRows) === 1) {
        $primaryKey = $pkRows[0]['Column_name'];
    }

    if (isset($_GET['delete']) && $primaryKey) {
        if (!verifyCSRF($_GET['csrf'] ?? '')) { setMessage("error","CSRF failed."); }
        else {
            $delVal = $_GET['delete'];
            $pdo->prepare("DELETE FROM `$table` WHERE `$primaryKey` = ?")->execute([$delVal]);
            setMessage("success","Row deleted.");
        }
        header("Location: db_browser.php?table=" . urlencode($table) . "&page=$page"); exit;
    }

    if ($editId && $primaryKey) {
        $pkValue = $editId;
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$primaryKey` = ?");
        $stmt->execute([$pkValue]);
        $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_row']) && $primaryKey) {
        $pkVal = $_POST['pk_value'] ?? '';
        $updates = [];
        $params = [];
        foreach ($allColumns as $c) {
            $f = $c['Field'];
            if ($f === $primaryKey || !isset($_POST[$f])) continue;
            $updates[] = "`$f` = ?";
            $params[] = $_POST[$f];
        }
        $params[] = $pkVal;
        $pdo->prepare("UPDATE `$table` SET " . implode(', ', $updates) . " WHERE `$primaryKey` = ?")->execute($params);
        header("Location: db_browser.php?table=" . urlencode($table) . "&page=$page"); exit;
    }

    $totalRows = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $perPage));
    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY 1 DESC LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<form method="get" style="margin-bottom:12px;">
  <label style="display:inline-block;margin-right:6px;">Table:</label>
  <select name="table" onchange="this.form.submit()" style="width:auto;">
    <option value="">Select a table...</option>
    <?php foreach($tables as $t): ?>
    <option value="<?=e($t)?>" <?=$table===$t?'selected':''?>><?=e($t)?></option>
    <?php endforeach; ?>
  </select>
  <?php if($table): ?> | <a href="db_browser.php">Clear</a><?php endif; ?>
</form>

<?php if($table && isset($editRow) && $primaryKey): ?>
<div class="box">
<h5>Edit <?=e($table)?> #<?=e($pkValue)?></h5>
<div class="box-inner" style="padding:14px;">
<form method="post">
  <input type="hidden" name="pk_value" value="<?=e($pkValue)?>">
  <table style="border:none;margin-bottom:10px;">
  <?php foreach($allColumns as $c): $f = $c['Field']; $val = $editRow[$f] ?? ''; if(in_array(strtolower($f),$hiddenCols))continue; ?>
  <tr>
    <td style="width:150px;font-weight:600;vertical-align:top;border:none;"><?=e($f)?></td>
    <td style="border:none;">
      <?php if ($f === $primaryKey): ?>
      <strong><?=e($val)?></strong> <span style="color:#888;">(primary key)</span>
      <?php elseif (strlen($val) > 100): ?>
      <textarea name="<?=e($f)?>" rows="4" style="font-family:monospace;width:300px;"><?=e($val)?></textarea>
      <?php else: ?>
      <input type="text" name="<?=e($f)?>" value="<?=e($val)?>" style="font-family:monospace;width:300px;">
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </table>
  <p>
    <button type="submit" name="save_row" class="btn primary">Save</button>
    <a href="db_browser.php?table=<?=urlencode($table)?>&page=<?=$page?>" class="btn">Cancel</a>
  </p>
</form>
</div></div>
<?php endif; ?>

<?php if($table): ?>
<p><b><?=e($table)?></b> - <?=number_format($totalRows)?> rows</p>

<div class="box" style="overflow-x:auto;">
<div class="box-inner">
<table style="margin-bottom:0;">
<thead><tr>
  <?php foreach($displayColNames as $col): ?>
  <th><?=e($col)?></th>
  <?php endforeach; ?>
  <?php if($primaryKey): ?><th>Action</th><?php endif; ?>
</tr></thead>
<tbody>
<?php if($rows): foreach($rows as $row): ?>
<tr>
  <?php foreach($displayColNames as $col): ?>
  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
    <?=in_array(strtolower($col), $hiddenCols) ? '***' : e(substr((string)($row[$col]??''),0,100))?>
  </td>
  <?php endforeach; ?>
  <?php if($primaryKey): ?>
  <td>
    <a href="?table=<?=urlencode($table)?>&edit=<?=urlencode($row[$primaryKey])?>&page=<?=$page?>">Edit</a>
    <a href="?table=<?=urlencode($table)?>&delete=<?=urlencode($row[$primaryKey])?>&csrf=<?=$internalCsrf?>&page=<?=$page?>" onclick="return confirm('Delete this row?')" style="color:#9d261d;margin-left:6px;">Delete</a>
  </td>
  <?php endif; ?>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="<?=count($displayColNames) + ($primaryKey?1:0)?>" style="text-align:center;padding:16px;color:#888;">Table is empty.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div></div>

<?php if($totalPages>1): ?>
<div class="pagination">
<ul>
  <?php if($page>1): ?><li><a href="?table=<?=urlencode($table)?>&page=<?=$page-1?>" class="prev">Prev</a></li><?php endif; ?>
  <li class="disabled"><a>Page <?=$page?> of <?=$totalPages?></a></li>
  <?php if($page<$totalPages): ?><li><a href="?table=<?=urlencode($table)?>&page=<?=$page+1?>" class="next">Next</a></li><?php endif; ?>
</ul>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once "footer.php"; ?>