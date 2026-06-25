<?php
require_once 'config/database.php';
try {
    // 1. Add missing columns to partner_preferences
    $pdo->exec("ALTER TABLE partner_preferences 
                ADD COLUMN IF NOT EXISTS sect VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS weight VARCHAR(50) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS height VARCHAR(50) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS mother_tongue VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS marital_status VARCHAR(100) DEFAULT NULL");
    
    echo "Database schema updated successfully.";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?>
