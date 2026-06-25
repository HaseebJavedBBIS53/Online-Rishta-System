<?php
require_once 'config/database.php';
$tables = ['users', 'user_profiles', 'partner_preferences', 'payments', 'subscriptions', 'shortlists'];
foreach($tables as $t) {
    echo "<h3>Table: $t</h3><pre>";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($cols);
    } catch(Exception $e) {
        echo "Error: ". $e->getMessage();
    }
    echo "</pre>";
}
?>
