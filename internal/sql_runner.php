<?php
require_once "header.php";

$query = trim($_POST['query'] ?? '');
$result = null;
$error = null;
$affectedRows = 0;

if ($query && isset($_POST['run'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setMessage("error","CSRF failed."); }
    else {
        try {
            $stmt = $pdo->query($query);
            if ($stmt->columnCount() > 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $affectedRows = $stmt->rowCount();
            }
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
}

$internalCsrf = generateCSRF();
?>

<div class="alert warning"><b>Warning:</b> SQL queries are executed directly against the database. DELETE, DROP, UPDATE, INSERT are all allowed. Be very careful. No undo.</div>

<form method="post" style="margin-bottom:16px;">
  <input type="hidden" name="csrf_token" value="<?=$internalCsrf?>">
  <div style="margin-bottom:8px;">
    <textarea name="query" rows="8" style="font-family:monospace;width:100%;"><?=e($query)?></textarea>
  </div>
  <button type="submit" name="run" class="btn" onclick="return confirm('Are you sure? This will run SQL directly.')">Execute</button>
  <span class="help">Examples: SELECT * FROM users; SHOW TABLES; DESCRIBE users; SHOW PROCESSLIST;</span>
</form>

<?php if($error): ?>
<div class="alert error" style="font-family:monospace;"><?=e($error)?></div>
<?php endif; ?>

<?php if($result !== null): ?>
<p><b>Query executed.</b> <?=count($result)?> row(s) returned.</p>
<?php if(count($result) > 0): ?>
<div class="box" style="overflow-x:auto;">
<div class="box-inner">
<table style="margin-bottom:0;">
<thead><tr>
<?php $keys=array_keys($result[0]); foreach($keys as $k): ?>
<th><?=e($k)?></th>
<?php endforeach; ?>
</tr></thead>
<tbody>
<?php foreach($result as $row): ?>
<tr>
<?php foreach($keys as $k): ?>
<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e((string)($row[$k]??''))?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
<?php endif; ?>
<?php elseif($query && $affectedRows > 0): ?>
<p><b>Query executed.</b> <?=$affectedRows?> row(s) affected.</p>
<?php elseif($query): ?>
<p><b>Query executed successfully.</b></p>
<?php endif; ?>

<?php require_once "footer.php"; ?>