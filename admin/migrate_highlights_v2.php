<?php
require 'config/database.php';

// Create highlight_packages table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS highlight_packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        duration_days INT NOT NULL DEFAULT 7,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'PKR',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "created highlight_packages\n";
} catch(Exception $e) { echo "highlight_packages: " . $e->getMessage() . "\n"; }

// Create highlight_queue table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS highlight_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        package_id INT DEFAULT NULL,
        highlight_type ENUM('Paid','Manual') DEFAULT 'Manual',
        status ENUM('Queued','Active','Expired','Removed') DEFAULT 'Queued',
        priority INT DEFAULT 20,
        start_date DATE NULL,
        expiry_date DATE NULL,
        payment_ref VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "created highlight_queue\n";
} catch(Exception $e) { echo "highlight_queue: " . $e->getMessage() . "\n"; }

// Add priority and expiry columns to users
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN highlight_type ENUM('Paid','Manual') DEFAULT 'Manual' AFTER is_highlighted");
    echo "added highlight_type to users\n";
} catch(Exception $e) { echo "highlight_type: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN highlight_start DATE NULL AFTER highlight_type");
    echo "added highlight_start to users\n";
} catch(Exception $e) { echo "highlight_start: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN highlight_expiry DATE NULL AFTER highlight_start");
    echo "added highlight_expiry to users\n";
} catch(Exception $e) { echo "highlight_expiry: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN highlight_priority INT DEFAULT 20 AFTER highlight_expiry");
    echo "added highlight_priority\n";
} catch(Exception $e) { echo "highlight_priority: " . $e->getMessage() . "\n"; }

// Insert default packages
try {
    $pdo->exec("INSERT IGNORE INTO highlight_packages (id, name, duration_days, price, currency) VALUES 
        (1, 'Basic Highlight', 7, 500, 'PKR'),
        (2, 'Premium Highlight', 30, 1500, 'PKR'),
        (3, 'VIP Highlight', 60, 2500, 'PKR')");
    echo "inserted default packages\n";
} catch(Exception $e) { echo "packages insert: " . $e->getMessage() . "\n"; }

// Auto expire highlighted profiles
try {
    $pdo->exec("UPDATE users SET is_highlighted = 0, highlight_expiry = NULL WHERE is_highlighted = 1 AND highlight_expiry IS NOT NULL AND highlight_expiry < CURDATE()");
    echo "expired profiles removed\n";
} catch(Exception $e) { echo "expiry: " . $e->getMessage() . "\n"; }

echo "Migration complete!";
?>
