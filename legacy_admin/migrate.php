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
                    $cols[] = [
                        'name' => $colName,
                        'def' => $line
                    ];
                }
            }
        }
        $tables[$tableName] = $cols;
    }
    return $tables;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $schemaPath = __DIR__ . '/../database/schema.sql';
        $schema = parseSchemaSQL($schemaPath);
        
        if (!$schema) {
            $errors[] = 'Could not parse schema.sql';
        } else {
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            
            foreach ($schema as $tableName => $columns) {
                $tableExists = $pdo->query("SHOW TABLES LIKE '$tableName'")->rowCount() > 0;
                
                if (!$tableExists) {
                    $createSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (\n";
                    $colDefs = [];
                    foreach ($columns as $col) {
                        $colDefs[] = $col['def'];
                    }
                    $createSQL .= implode(",\n", $colDefs);
                    $createSQL .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $pdo->exec($createSQL);
                    $output .= "Created table `$tableName`<br>\n";
                } else {
                    $existingCols = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN, 0);
                    $existingCols = array_map('strtolower', $existingCols);
                    
                    foreach ($columns as $col) {
                        $colName = strtolower($col['name']);
                        if (!in_array($colName, $existingCols)) {
                            $alterSQL = "ALTER TABLE `$tableName` ADD COLUMN " . $col['def'];
                            $pdo->exec($alterSQL);
                            $output .= "Added column `{$col['name']}` to `$tableName`<br>\n";
                        }
                    }
                }
            }
            
            $output .= "<br><strong>Migration complete!</strong>";
        }
    } catch (Exception $e) {
        $errors[] = 'Migration error: ' . $e->getMessage();
    }
}
?>

<div class="container">
    <div class="page-header">
        <h2>Database Migration</h2>
    </div>

    <?php if ($errors): ?>
        <div class="alert-message error">
            <?php foreach ($errors as $e): ?>
                <p><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($output): ?>
        <div class="alert-message success">
            <p><?= $output ?></p>
        </div>
    <?php endif; ?>

    <div class="well">
        <h4>What does this do?</h4>
        <p>Reads <code>database/schema.sql</code> and compares it against the current database. Any missing tables or columns will be created automatically.</p>
        <p>This is useful after updating the schema.sql file — just hit the button below to sync your database.</p>
    </div>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="actions">
            <button type="submit" name="run_migration" class="btn primary" onclick="return confirm('Run migration? This will add any missing tables/columns.');">
                Run Migration
            </button>
        </div>
    </form>
</div>

<?php require_once "footer.php"; ?>
