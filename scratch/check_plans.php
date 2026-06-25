<?php
require 'config/database.php';
$s = $pdo->query('SELECT plan_id, plan_name FROM subscriptions')->fetchAll(PDO::FETCH_ASSOC);
print_r($s);
?>
