<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SESSION['role'] === 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$amount_usd = 10.00; // Simulated cost limit

try {
    $pdo->beginTransaction();
    
    // Log the payment
    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, payment_method, status) VALUES (?, ?, ?, 'Completed')");
    $stmt->execute([$user_id, $amount_usd, 'Credit Card (Boost)']);
    
    // Turn on Highlight
    $pdo->prepare("UPDATE users SET is_highlighted = 1 WHERE id = ?")->execute([$user_id]);
    
    $pdo->commit();
    set_flash("Profile Boosted Successfully! You are now featured.", "success");
} catch(Exception $e) {
    $pdo->rollBack();
    set_flash("Payment failed during processing.", "danger");
}

header("Location: dashboard.php");
exit();
?>
