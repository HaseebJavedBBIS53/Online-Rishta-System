<?php
require 'config/database.php';

try {
    $pdo->exec("ALTER TABLE posts ADD COLUMN image VARCHAR(255) NULL DEFAULT NULL AFTER content");
    $pdo->exec("ALTER TABLE posts ADD COLUMN privacy VARCHAR(20) NOT NULL DEFAULT 'Public' AFTER image");
    echo "posts table altered.\n";
} catch (Exception $e) { }

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_highlighted TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    echo "users table altered.\n";
} catch (Exception $e) { }

echo "Database migrations complete.";
?>
