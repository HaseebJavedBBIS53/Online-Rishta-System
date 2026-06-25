<?php
require 'config/database.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_support_blocked TINYINT(1) DEFAULT 0");
    echo "Column is_support_blocked added successfully.\n";
} catch (PDOException $e) {
    echo "Error adding is_support_blocked: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "Column last_seen added successfully.\n";
} catch (PDOException $e) {
    echo "Error adding last_seen: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN chat_typing_to INT DEFAULT NULL"); // Who are they typing to
    echo "Column chat_typing_to added successfully.\n";
} catch (PDOException $e) {
    echo "Error adding chat_typing_to: " . $e->getMessage() . "\n";
}
?>
