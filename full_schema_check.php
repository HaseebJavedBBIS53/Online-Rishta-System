<?php
require_once 'config/database.php';
function get_cols($table) {
    global $pdo;
    try {
        $stmt = $pdo->query("DESCRIBEL $table"); // Typo to see if it fails
    } catch(Exception $e) {}
    
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

$tables = ['users', 'user_profiles', 'partner_preferences', 'payments', 'subscriptions', 'shortlists'];
$schema = [];
foreach($tables as $t) {
    $schema[$t] = get_cols($t);
}
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
