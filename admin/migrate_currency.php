<?php
require 'config/database.php';

$rates = json_encode([
    'USD' => 1.0, 
    'PKR' => 280.0, 
    'EUR' => 0.92, 
    'GBP' => 0.79, 
    'AED' => 3.67, 
    'SAR' => 3.75, 
    'INR' => 83.0, 
    'CAD' => 1.35, 
    'AUD' => 1.52, 
    'JPY' => 150.0
]);

$stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
$stmt->execute(['default_currency', 'USD', 'USD']);

$stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
$stmt->execute(['exchange_rates', $rates, $rates]);

echo "Multi-Currency Engine seeded.";
?>
