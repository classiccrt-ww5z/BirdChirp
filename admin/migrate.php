<?php
require_once "header.php";

$output = '';
$errors = [];

function parseSchemaSQL($path) {
    $sql = file_get_contents($path);
    if (!$sql) return false;
    $tables = [];
    $pattern = '/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?(\w+)\s*\((.*?)\)\s*ENGINE\s*=\s*\w+/is';
    preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $tableName = $m[1];
        $columnsDef = $m[2];
        $cols = [];
        $lines = explode(",\n", $columnsDef);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\s*(\w+)\s/', $line, $cm)) {
                $colName = $cm[1];
                if (!in_array(strtoupper($colName), ['PRIMARY', 'INDEX', 'UNIQUE', 'KEY', 'FULLTEXT', 'CONSTRAINT'])) {
                    $cols[] = ['name' => $colName, 'def' => $line];
                }
            }
        }
        $tables[$tableName] = $cols;
    }
    return $tables;
}

$extraTables = [
    'admin_log' => [
        ['name' => 'id', 'def' => 'id INT AUTO_INCREMENT PRIMARY KEY'],
        ['name' => 'admin_id', 'def' => 'admin_id INT NOT NULL'],
        ['name' => 'action', 'def' => 'action VARCHAR(100) NOT NULL'],
        ['name' => 'target_type', 'def' => 'target_type VARCHAR(50) DEFAULT NULL'],
        ['name' => 'target_id', 'def' => 'target_id VARCHAR(255) DEFAULT NULL'],
        ['name' => 'details', 'def' => 'details TEXT DEFAULT NULL'],
        ['name' => 'ip_address', 'def' => 'ip_address VARCHAR(45) DEFAULT NULL'],
        ['name' => 'created_at', 'def' => 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $schemaPath = __DIR__ . '/../database/schema.sql';
        $schema = parseSchemaSQL($schemaPath);

        if ($schema) {
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

            foreach ($schema as $tableName => $columns) {
                $tableExists = $pdo->query("SHOW TABLES LIKE '$tableName'")->rowCount() > 0;
                if (!$tableExists) {
                    $colDefs = [];
                    foreach ($columns as $col) { $colDefs[] = $col['def']; }
                    $createSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (\n" . implode(",\n", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $pdo->exec($createSQL);
                    $output .= "Created table `$tableName`<br>\n";
                } else {
                    $existingCols = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN, 0);
                    $existingCols = array_map('strtolower', $existingCols);
                    foreach ($columns as $col) {
                        $colName = strtolower($col['name']);
                        if (!in_array($colName, $existingCols)) {
                            $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN " . $col['def']);
                            $output .= "Added column `{$col['name']}` to `$tableName`<br>\n";
                        }
                    }
                }
            }

            foreach ($extraTables as $tableName => $columns) {
                $tableExists = $pdo->query("SHOW TABLES LIKE '$tableName'")->rowCount() > 0;
                if (!$tableExists) {
                    $colDefs = [];
                    foreach ($columns as $col) { $colDefs[] = $col['def']; }
                    $createSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (\n" . implode(",\n", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $pdo->exec($createSQL);
                    $output .= "Created table `$tableName` (admin_log)<br>\n";
                }
            }

            $output .= "<br><strong>Migration complete!</strong>";
        } else {
            $errors[] = 'Could not parse schema.sql';
        }
    } catch (Exception $e) {
        $errors[] = 'Migration error: ' . $e->getMessage();
    }
}
?>



<?php if ($errors): ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= e($e) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($output): ?>
    <div class="alert alert-success"><?= $output ?></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <div class="card-header"><h3>What this does</h3></div>
    <div class="card-body">
        <p>Reads <code>database/schema.sql</code> and compares it against the current database. Any missing tables or columns are created automatically.</p>
        <p>This also creates the <code>admin_log</code> table used for the Activity Log.</p>
        <p style="margin-top:12px;">
            <em class="text-muted text-sm">Run this after updating schema.sql or deploying new features that need database changes.</em>
        </p>
    </div>
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <button type="submit" name="run_migration" class="btn btn-primary btn-lg" onclick="return confirm('Run migration? This will add any missing tables/columns.');">
                Run Migration
            </button>
        </form>
    </div>
</div>

<?php require_once "footer.php"; ?>
