<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_admin();

echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; padding: 40px; line-height: 1.6; }
    .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; shadow: 0 4px 20px rgba(0,0,0,0.08); }
    h2 { color: #0d6efd; border-bottom: 2px solid #eef2f7; pb-3: 10px; }
    .log-entry { margin-bottom: 10px; padding: 10px; border-radius: 6px; border-left: 4px solid #ccc; background: #fdfdfd; }
    .success { border-left-color: #198754; background: #f0fff4; color: #198754; }
    .info { border-left-color: #0dcaf0; background: #f0faff; color: #087990; }
    .error { border-left-color: #dc3545; background: #fff5f5; color: #dc3545; }
    .btn { display: inline-block; padding: 12px 24px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; transition: 0.2s; }
    .btn:hover { background: #0b5ed7; transform: translateY(-2px); }
</style>";

echo "<div class='container'>";
echo "<h2>🛠️ Universal Database Sync (MySQL/MariaDB)</h2>";
echo "<p>Running cross-compatible diagnostics...</p>";

function columnExists($pdo, $table, $column)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function tableExists($pdo, $table)
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function logMsg($msg, $type = 'info')
{
    echo "<div class='log-entry $type'>$msg</div>";
}

// 1. Ensure ANNOUNCEMENTS exists and has correct columns
if (!tableExists($pdo, 'announcements')) {
    try {
        $pdo->exec("CREATE TABLE `announcements` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `audience` ENUM('All','Premium','Free') DEFAULT 'All',
            `type` ENUM('info','success','warning','danger') DEFAULT 'info',
            `created_by` INT(11) NOT NULL,
            `scheduled_for` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        logMsg("✅ Created table 'announcements'", "success");
    } catch (Exception $e) {
        logMsg("❌ Error creating announcements: " . $e->getMessage(), "error");
    }
} else {
    // Check missing columns for announcements
    $cols = [
        'created_by' => "INT(11) NOT NULL",
        'scheduled_for' => "DATETIME DEFAULT NULL",
        'audience' => "ENUM('All','Premium','Free') DEFAULT 'All'",
        'type' => "ENUM('info','success','warning','danger') DEFAULT 'info'"
    ];
    foreach ($cols as $col => $def) {
        if (!columnExists($pdo, 'announcements', $col)) {
            try {
                $pdo->exec("ALTER TABLE `announcements` ADD `$col` $def");
                logMsg("✅ Added '$col' to announcements", "success");
            } catch (Exception $e) {
                logMsg("❌ Failed to add '$col' to announcements: " . $e->getMessage(), "error");
            }
        }
    }
}

// 2. Ensure PAYMENTS columns exist
if (tableExists($pdo, 'payments')) {
    // If created_at exists but payment_date doesn't, we can try to rename it or add it
    if (!columnExists($pdo, 'payments', 'payment_date')) {
        if (columnExists($pdo, 'payments', 'created_at')) {
            try {
                $pdo->exec("ALTER TABLE `payments` CHANGE `created_at` `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                logMsg("✅ Renamed 'created_at' to 'payment_date' in payments", "success");
            } catch (Exception $e) {
                // If rename fails, try to add it
                try {
                    $pdo->exec("ALTER TABLE `payments` ADD `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    logMsg("✅ Added 'payment_date' to payments", "success");
                } catch (Exception $e2) {
                    logMsg("❌ Critical: Could not fix payment_date: " . $e2->getMessage(), "error");
                }
            }
        } else {
            try {
                $pdo->exec("ALTER TABLE `payments` ADD `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                logMsg("✅ Added 'payment_date' to payments", "success");
            } catch (Exception $e) {
                logMsg("❌ Error adding payment_date: " . $e->getMessage(), "error");
            }
        }
    }

    $other_cols = [
        'plan_id' => "INT(11) DEFAULT NULL",
        'transaction_id' => "VARCHAR(255) DEFAULT NULL",
        'status' => "ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending'",
        'notes' => "TEXT DEFAULT NULL"
    ];
    foreach ($other_cols as $col => $def) {
        if (!columnExists($pdo, 'payments', $col)) {
            try {
                $pdo->exec("ALTER TABLE `payments` ADD `$col` $def");
                logMsg("✅ Added '$col' to payments", "success");
            } catch (Exception $e) {
                logMsg("❌ Error adding '$col' to payments: " . $e->getMessage(), "error");
            }
        }
    }
} else {
    logMsg("⚠️ table 'payments' does not exist. Creating it now...", "info");
    try {
        $pdo->exec("CREATE TABLE `payments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `plan_id` int(11) DEFAULT NULL,
            `amount` decimal(10,2) NOT NULL,
            `transaction_id` varchar(255) DEFAULT NULL,
            `payment_method` varchar(100) DEFAULT 'Manual',
            `status` enum('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
            `payment_date` timestamp DEFAULT CURRENT_TIMESTAMP,
            `notes` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        logMsg("✅ Created table 'payments'", "success");
    } catch (Exception $e) {
        logMsg("❌ Error creating payments table: " . $e->getMessage(), "error");
    }
}

// 4. User Profiles Detailed Extensions
if (tableExists($pdo, 'user_profiles')) {
    $profile_cols = [
        'marital_status' => "ENUM('Single', 'Divorced', 'Widowed') DEFAULT 'Single'",
        'height' => "VARCHAR(50) DEFAULT NULL",
        'sect' => "VARCHAR(100) DEFAULT NULL",
        'caste' => "VARCHAR(100) DEFAULT NULL",
        'mother_tongue' => "VARCHAR(100) DEFAULT NULL",
        'country' => "VARCHAR(100) DEFAULT 'Pakistan'",
        'education_level' => "VARCHAR(100) DEFAULT NULL",
        'degree_title' => "VARCHAR(150) DEFAULT NULL",
        'company_name' => "VARCHAR(150) DEFAULT NULL",
        'monthly_income' => "VARCHAR(100) DEFAULT NULL",
        'employment_type' => "ENUM('Private', 'Govt', 'Business', 'Student', 'Unemployed') DEFAULT 'Private'",
        'weight' => "VARCHAR(50) DEFAULT NULL",
        'complexion' => "VARCHAR(100) DEFAULT NULL",
        'body_type' => "VARCHAR(100) DEFAULT NULL",
        'smoking' => "ENUM('Yes', 'No') DEFAULT 'No'",
        'drinking' => "ENUM('Yes', 'No') DEFAULT 'No'",
        'disability' => "TEXT DEFAULT NULL",
        'photo_visibility' => "ENUM('Public', 'Connections', 'Private') DEFAULT 'Public'",
        'phone_verified' => "TINYINT(1) DEFAULT 0",
        'cnic_verified' => "TINYINT(1) DEFAULT 0",
        'hide_phone' => "TINYINT(1) DEFAULT 0",
        'hide_search' => "TINYINT(1) DEFAULT 0",
        'premium_only_view' => "TINYINT(1) DEFAULT 0"
    ];
    foreach ($profile_cols as $col => $def) {
        if (!columnExists($pdo, 'user_profiles', $col)) {
            try {
                $pdo->exec("ALTER TABLE `user_profiles` ADD `$col` $def");
                logMsg("✅ Added '$col' to user_profiles", "success");
            } catch (Exception $e) {
                logMsg("❌ Error adding '$col' to user_profiles: " . $e->getMessage(), "error");
            }
        }
    }
}

echo "<hr><p><b>Diagnostic Complete.</b> All systems should be operational.</p>";
echo "<a href='dashboard.php' class='btn'>🚀 Launch Dashboard</a>";
echo "</div>";
?>