<?php
require_once 'config/database.php';

try {
    // 1. Rename can_post_community to can_community_feed in subscriptions
    $pdo->exec("ALTER TABLE subscriptions CHANGE COLUMN can_post_community can_community_feed TINYINT(1) DEFAULT 0");
    echo "Column 'can_post_community' renamed to 'can_community_feed' successfully.\n";

    // 2. Add columns to subscriptions if missing (for better plan management)
    // Actually they seem to be there based on DESCRIBE output.
    
    echo "Database updates completed.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
