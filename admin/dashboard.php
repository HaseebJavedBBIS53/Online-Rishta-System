<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_admin();

// Handle Stats Export (Step 2)
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    if ($type === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="admin_stats_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Users', $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'User'")->fetchColumn()]);
        fputcsv($output, ['Total Revenue', formatPrice($pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Completed'")->fetchColumn() ?: 0)]);
        fputcsv($output, ['Matches Formed', $pdo->query("SELECT COUNT(*) FROM interests WHERE status = 'Accepted'")->fetchColumn()]);
        fclose($output);
        exit();
    }
}

// Fetch System Stats
$stats = [];
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'User'")->fetchColumn();
$stats['total_revenue'] = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Completed'")->fetchColumn() ?: 0;
$stats['accepted_matches'] = $pdo->query("SELECT COUNT(*) FROM interests WHERE status = 'Accepted'")->fetchColumn();
$stats['duplicate_ips'] = $pdo->query("SELECT COUNT(*) FROM (SELECT last_ip FROM users WHERE last_ip IS NOT NULL GROUP BY last_ip HAVING COUNT(*) > 1) as dup_ips")->fetchColumn();
$stats['pending_verifs'] = $pdo->query("SELECT COUNT(*) FROM user_profiles p JOIN users u ON p.user_id = u.id WHERE p.is_verified = 0 AND p.verification_doc IS NOT NULL AND u.status = 'Active'")->fetchColumn();
$stats['pending_reports'] = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Pending'")->fetchColumn();
$stats['pending_payments'] = $pdo->query("SELECT COUNT(*) FROM manual_payments WHERE status = 'Pending'")->fetchColumn();
$stats['active_subs'] = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE status = 'Active' AND end_date >= CURDATE()")->fetchColumn();
$stats['total_messages'] = $pdo->query("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h3 fw-bold text-dark mb-0">Overview Dashboard</h1>
        <p class="text-muted small">Welcome back! Here is what's happening on your platform today.</p>
    </div>
    <div class="col-auto">
        <div class="btn-group shadow-sm">
            <a href="dashboard.php?export=csv" class="btn btn-white border fw-bold px-3 btn-sm"><i
                    class="bi bi-download me-1"></i> Export Stats (CSV)</a>
            <button onclick="window.print()" class="btn btn-white border fw-bold px-3 btn-sm"><i
                    class="bi bi-printer me-1"></i> Print Report</button>
        </div>
    </div>
</div>

<!-- 6 Widget Grid -->
<div class="row g-4 mb-5">
    <!-- 1. Total Users -->
    <div class="col-xl-4 col-md-6">
        <a href="user_management.php"
            class="card card-vibrant grad-primary h-100 text-decoration-none border-0 shadow-sm">
            <div class="card-vibrant-inner">
                <div class="bg-white bg-opacity-25 rounded-3 p-2 d-inline-block mb-3">
                    <i class="bi bi-people-fill fs-4 text-white"></i>
                </div>
                <div class="text-white-50 small fw-bold text-uppercase ls-1">Total Members</div>
                <div class="h3 fw-bold text-white mb-0"><?= number_format($stats['total_users']) ?></div>
            </div>
        </a>
    </div>

    <!-- 2. Matches Formed -->
    <div class="col-xl-4 col-md-6">
        <a href="matches.php" class="card card-vibrant grad-info h-100 text-decoration-none border-0 shadow-sm">
            <div class="card-vibrant-inner">
                <div class="bg-white bg-opacity-25 rounded-3 p-2 d-inline-block mb-3">
                    <i class="bi bi-heart-fill fs-4 text-white"></i>
                </div>
                <div class="text-white-50 small fw-bold text-uppercase ls-1">Successful Matches</div>
                <div class="h3 fw-bold text-white mb-0"><?= $stats['accepted_matches'] ?></div>
            </div>
        </a>
    </div>

    <!-- 3. Messages Overview -->
    <div class="col-xl-4 col-md-6">
        <a href="messaging_system.php"
            class="card card-vibrant grad-purple h-100 text-decoration-none border-0 shadow-sm">
            <div class="card-vibrant-inner">
                <div class="bg-white bg-opacity-25 rounded-3 p-2 d-inline-block mb-3">
                    <i class="bi bi-chat-dots-fill fs-4 text-white"></i>
                </div>
                <div class="text-white-50 small fw-bold text-uppercase ls-1">Total Chats (30d)</div>
                <div class="h3 fw-bold text-white mb-0"><?= number_format($stats['total_messages']) ?></div>
            </div>
        </a>
    </div>

    <!-- 4. Pending Verifications -->
    <div class="col-xl-4 col-md-6">
        <a href="verifications.php"
            class="card card-vibrant grad-warning h-100 text-decoration-none border-0 shadow-sm">
            <div class="card-vibrant-inner">
                <div class="bg-white bg-opacity-25 rounded-3 p-2 d-inline-block mb-3">
                    <i class="bi bi-patch-check-fill fs-4 text-dark"></i>
                </div>
                <div class="text-dark small fw-bold text-uppercase ls-1 opacity-75">Waiting for Approval</div>
                <div class="h3 fw-bold text-dark mb-0"><?= $stats['pending_verifs'] ?></div>
            </div>
        </a>
    </div>

    <!-- 5. Reported Users -->
    <div class="col-xl-4 col-md-6">
        <a href="reports.php" class="card card-vibrant grad-rose h-100 text-decoration-none border-0 shadow-sm">
            <div class="card-vibrant-inner">
                <div class="bg-white bg-opacity-25 rounded-3 p-2 d-inline-block mb-3">
                    <i class="bi bi-flag-fill fs-4 text-white"></i>
                </div>
                <div class="text-white-50 small fw-bold text-uppercase ls-1">Safety Reports</div>
                <div class="h3 fw-bold text-white mb-0"><?= $stats['pending_reports'] ?></div>
            </div>
        </a>
    </div>

    <!-- 6. Suspicious Activity -->
    <div class="col-xl-4 col-md-6">
        <a href="activity_logs.php" class="card card-vibrant grad-danger h-100 text-decoration-none border-0 shadow-sm">
            <div class="card-vibrant-inner">
                <div class="bg-white bg-opacity-25 rounded-3 p-2 d-inline-block mb-3">
                    <i class="bi bi-shield-exclamation fs-4 text-white"></i>
                </div>
                <div class="text-white-50 small fw-bold text-uppercase ls-1">System Alerts</div>
                <div class="h3 fw-bold text-white mb-0"><?= $stats['duplicate_ips'] ?></div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Platform Growth</h6>
            </div>
            <div class="card-body bg-light bg-opacity-50 d-flex align-items-center justify-content-center p-5"
                style="min-height: 300px;">
                <div class="text-center">
                    <i class="bi bi-bar-chart-fill display-1 text-primary opacity-25"></i>
                    <p class="mt-3 text-muted small">Growth data is being tracked. Check back soon for visual charts.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Recent Alerts</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $alerts = $pdo->query("SELECT u.full_name, u.last_ip, u.created_at FROM users u WHERE status = 'Suspended' LIMIT 3")->fetchAll();
                    if (empty($alerts)): ?>
                        <div class="p-5 text-center text-muted small">No security threats found.</div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="list-group-item p-3 border-0 bg-light bg-opacity-25 mb-1 rounded-3 mx-3">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1 fw-bold small"><?= htmlspecialchars($alert['full_name']) ?></h6>
                                    <small class="text-danger">Suspended</small>
                                </div>
                                <p class="mb-0 text-muted" style="font-size: 11px;">IP: <?= $alert['last_ip'] ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>