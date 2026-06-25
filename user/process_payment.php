<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plan_id'])) {
    header("Location: subscription.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$plan_id = intval($_POST['plan_id']);
$gateway = sanitize_input($_POST['gateway'] ?? 'PayPal');

// Validate plan exists
$stmt = $pdo->prepare("SELECT price, duration_months FROM subscriptions WHERE plan_id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    set_flash("Invalid subscription plan.", "danger");
    header("Location: subscription.php");
    exit();
}

// Prepare transaction data
$transaction_id = 'TXN_' . strtoupper(uniqid());
$amount = $plan['price'];
$status = 'Completed'; // Simulate successful payment

$pdo->beginTransaction();
try {
    // 1. Log the payment
    $stmt = $pdo->prepare("INSERT INTO payments (user_id, plan_id, transaction_id, amount, payment_gateway, status) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $plan_id, $transaction_id, $amount, $gateway, $status]);

    // 2. Update user's active plan
    $stmt = $pdo->prepare("UPDATE users SET plan_id = ? WHERE id = ?");
    $stmt->execute([$plan_id, $user_id]);

    // 3. Update/Insert user_subscriptions for active tracking
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+' . $plan['duration_months'] . ' months'));

    // Check if user already has an active entry and expire it
    $pdo->prepare("UPDATE user_subscriptions SET status = 'Expired' WHERE user_id = ? AND status = 'Active'")->execute([$user_id]);

    $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status) 
                           VALUES (?, ?, ?, ?, 'Active')");
    $stmt->execute([$user_id, $plan_id, $start_date, $end_date]);

    // Update session plan info
    $_SESSION['plan_id'] = $plan_id;

    $pdo->commit();
    set_flash("Payment Successful! Your plan has been upgraded to " . ($plan_id > 1 ? 'Premium' : 'Free') . ".", "success");
    header("Location: dashboard.php");
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash("Error processing payment: " . $e->getMessage(), "danger");
    header("Location: payment_checkout.php?plan_id=$plan_id");
}
exit();
