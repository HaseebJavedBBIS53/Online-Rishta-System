<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('view_analytics');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Gender', 'DOB', 'Status', 'Plan ID', 'Last IP', 'Created At']);

// Fetch user data
$query = "SELECT id, full_name, email, phone, gender, dob, status, plan_id, last_ip, created_at 
          FROM users 
          WHERE role = 'User' 
          ORDER BY created_at DESC";
$stmt = $pdo->query($query);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit();
