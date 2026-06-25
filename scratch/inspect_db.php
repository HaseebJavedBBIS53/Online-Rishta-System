<?php
require_once 'config/database.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "Table: $table\n";
    $columns = $pdo->query("DESCRIBE $table")->fetchAll();
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    echo "\n";
}
?>
