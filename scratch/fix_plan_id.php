<?php
require 'config/database.php';

try {
    // Disable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Check if ID 1 exists
    $stmt = $pdo->query("SELECT plan_id FROM subscriptions WHERE plan_id = 1");
    if ($stmt->fetch()) {
        // ID 1 exists, swap it with something else if it's not the free plan
        $pdo->exec("UPDATE subscriptions SET plan_id = 999 WHERE plan_id = 1");
        $pdo->exec("UPDATE users SET plan_id = 999 WHERE plan_id = 1");
    }

    // Find the Free Plan
    $stmt = $pdo->query("SELECT plan_id FROM subscriptions WHERE plan_type = 'Free' LIMIT 1");
    $old_id = $stmt->fetchColumn();

    if ($old_id) {
        echo "Moving Free Plan from $old_id to 1\n";
        $pdo->prepare("UPDATE subscriptions SET plan_id = 1 WHERE plan_id = ?")->execute([$old_id]);
        $pdo->prepare("UPDATE users SET plan_id = 1 WHERE plan_id = ?")->execute([$old_id]);
    } else {
        echo "Free Plan not found.\n";
    }

    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Success.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
