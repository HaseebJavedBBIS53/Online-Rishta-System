<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN status ENUM('Active','Suspended','Deleted','Pending Approval') DEFAULT 'Active'");
    echo "Status enum updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
