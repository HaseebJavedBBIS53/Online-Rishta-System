<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('view_analytics');

// ─── Live Stats ───────────────────────────────────────────────
$stats = [];
$stats['total_users']     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='User'")->fetchColumn();
$stats['new_today']       = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stats['new_week']        = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
$stats['active_premium']  = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE status='Active'")->fetchColumn();
// Revenue Stats (online + manual combined)
$online_rev         = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Completed'")->fetchColumn();
$manual_rev         = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM manual_payments WHERE status='Approved'")->fetchColumn();
$stats['total_rev'] = $online_rev + $manual_rev;
$stats['rev_month'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Completed' AND payment_date>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn()
                    + $pdo->query("SELECT COALESCE(SUM(amount),0) FROM manual_payments WHERE status='Approved' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
$stats['manual_rev']      = $manual_rev;
$stats['total_interests'] = $pdo->query("SELECT COUNT(*) FROM interests")->fetchColumn() ?: 1;
$stats['accepted']        = $pdo->query("SELECT COUNT(*) FROM interests WHERE status='Accepted'")->fetchColumn();
$stats['pending_reports'] = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='Pending'")->fetchColumn();
$stats['verified_users']  = $pdo->query("SELECT COUNT(*) FROM user_profiles WHERE is_verified=1")->fetchColumn();
$stats['profile_views']   = $pdo->query("SELECT COUNT(*) FROM profile_views WHERE view_date=CURDATE()")->fetchColumn();
$stats['messages_today']  = $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$success_rate = round(($stats['accepted'] / $stats['total_interests']) * 100, 1);

// ─── Chart: 30-day user registrations ─────────────────────────
$reg_raw = $pdo->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE role='User' AND created_at >= DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
$reg_labels = []; $reg_data = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $reg_labels[] = date('M d', strtotime($d));
    $reg_data[]   = $reg_raw[$d] ?? 0;
}

// ─── Chart: Revenue last 12 months ────────────────────────────
$rev_raw = $pdo->query("SELECT DATE_FORMAT(payment_date,'%Y-%m') AS m, SUM(amount) AS total FROM payments WHERE status='Completed' AND payment_date >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(payment_date,'%Y-%m') ORDER BY m ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
$rev_labels = []; $rev_data = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $rev_labels[] = date('M y', strtotime($m.'-01'));
    $rev_data[]   = round($rev_raw[$m] ?? 0);
}

// ─── Plan Distribution ────────────────────────────────────────
$plan_dist = $pdo->query("SELECT s.plan_name, COUNT(us.id) AS total FROM user_subscriptions us JOIN subscriptions s ON us.plan_id=s.plan_id WHERE us.status='Active' GROUP BY us.plan_id")->fetchAll();
$plan_labels = array_column($plan_dist, 'plan_name');
$plan_data   = array_column($plan_dist, 'total');

// ─── Gender Distribution ──────────────────────────────────────
$male   = $pdo->query("SELECT COUNT(*) FROM users WHERE gender='Male' AND role='User'")->fetchColumn();
$female = $pdo->query("SELECT COUNT(*) FROM users WHERE gender='Female' AND role='User'")->fetchColumn();

// ─── Top Cities ───────────────────────────────────────────────
$top_cities = $pdo->query("SELECT city, COUNT(*) AS cnt FROM user_profiles WHERE city IS NOT NULL AND city != '' GROUP BY city ORDER BY cnt DESC LIMIT 5")->fetchAll();

// ─── Recent Transactions (payments + manual_payments combined) ─
$recent_txns = $pdo->query("
    SELECT p.id, p.user_id, p.amount, p.status, p.payment_date, u.full_name, s.plan_name, 'Online' as source
    FROM payments p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN subscriptions s ON p.plan_id = s.plan_id
    UNION ALL
    SELECT mp.id, mp.user_id, mp.amount, mp.status, mp.created_at as payment_date, u.full_name, s.plan_name, mp.payment_method as source
    FROM manual_payments mp
    JOIN users u ON mp.user_id = u.id
    LEFT JOIN subscriptions s ON mp.plan_id = s.plan_id
    ORDER BY payment_date DESC
    LIMIT 8
")->fetchAll();

// ─── Handle POST Actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['txn_action'])) {
    $txn_id = intval($_POST['txn_id']);
    $uid = intval($_POST['uid']);
    $source = $_POST['source'] ?? 'online';

    if ($_POST['txn_action'] === 'delete') {
        if ($source === 'manual') {
            $pdo->prepare("DELETE FROM manual_payments WHERE id=?")->execute([$txn_id]);
        } else {
            $pdo->prepare("DELETE FROM payments WHERE id=?")->execute([$txn_id]);
        }
        set_flash("Transaction deleted.", "warning");
    }
    if ($_POST['txn_action'] === 'end_plan') {
        $pdo->prepare("UPDATE users SET plan_id=1, expiry_date=NULL WHERE id=?")->execute([$uid]);
        $pdo->prepare("UPDATE user_subscriptions SET status='Expired', end_date=CURDATE() WHERE user_id=? AND status='Active'")->execute([$uid]);
        set_flash("Membership plan ended for user #$uid.", "warning");
    }
    header("Location: analytics.php");
    exit();
}

// ─── Export CSV ───────────────────────────────────────────────
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rishta_analytics_' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Metric', 'Value']);
    foreach ([
        'Total Users' => $stats['total_users'], 'New Today' => $stats['new_today'],
        'Active Premium' => $stats['active_premium'], 'Total Revenue (Rs.)' => $stats['total_rev'],
        'Monthly Revenue (Rs.)' => $stats['rev_month'], 'Match Success Rate' => $success_rate . '%',
        'Verified Profiles' => $stats['verified_users'],
    ] as $k => $v) fputcsv($out, [$k, $v]);
    fclose($out);
    exit();
}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.stat-card { transition: transform .2s; }
.stat-card:hover { transform: translateY(-3px); }
.chart-card { min-height: 280px; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 pt-2 flex-wrap gap-2">
    <div>
        <h1 class="h2 fw-bold text-dark mb-0">Platform Analytics</h1>
        <p class="text-muted small mb-0">Live data — registrations, revenue, matchmaking, and more.</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="location.reload()" class="btn btn-light border rounded-pill px-3 fw-bold">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
        <a href="analytics.php?export=csv" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
        </a>
    </div>
</div>

<!-- KPI Cards Row 1 -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['icon'=>'bi-people-fill','color'=>'primary','label'=>'Total Users','value'=> number_format($stats['total_users']),'sub'=>'+'.number_format($stats['new_today']).' today','link'=>'users.php'],
        ['icon'=>'bi-star-fill','color'=>'warning','label'=>'Premium Members','value'=> number_format($stats['active_premium']),'sub'=>'Active subscriptions','link'=>'users.php?plan=premium'],
        ['icon'=>'bi-currency-dollar','color'=>'success','label'=>'Total Revenue','value'=>'Rs.'.number_format($stats['total_rev']),'sub'=>'Rs.'.number_format($stats['manual_rev']).' manual','link'=>'payments.php'],
        ['icon'=>'bi-graph-up-arrow','color'=>'info','label'=>'Monthly Revenue','value'=>'Rs.'.number_format($stats['rev_month']),'sub'=>'Last 30 days','link'=>'payments.php?from='.date('Y-m-d',strtotime('-30 days'))],
        ['icon'=>'bi-heart-pulse-fill','color'=>'danger','label'=>'Match Rate','value'=>$success_rate.'%','sub'=>$stats['accepted'].' accepted','link'=>'interests.php'],
        ['icon'=>'bi-patch-check-fill','color'=>'success','label'=>'Verified','value'=> number_format($stats['verified_users']),'sub'=>'Profiles confirmed','link'=>'verification_logs.php'],
        ['icon'=>'bi-chat-dots-fill','color'=>'secondary','label'=>'Msgs Today','value'=> number_format($stats['messages_today']),'sub'=>'Active conversations','link'=>'users.php'],
        ['icon'=>'bi-flag-fill','color'=>'danger','label'=>'Pending Reports','value'=> number_format($stats['pending_reports']),'sub'=>'Needs moderation','link'=>'reports.php'],
    ];
    foreach($kpis as $k): ?>
    <div class="col-md-3 col-6">
        <a href="<?= $k['link'] ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm rounded-4 p-3 stat-card h-100" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-<?= $k['color'] ?> bg-opacity-10 rounded-3 p-2 flex-shrink-0">
                    <i class="bi <?= $k['icon'] ?> text-<?= $k['color'] ?> fs-5"></i>
                </div>
                <div>
                    <div class="small text-muted fw-bold"><?= $k['label'] ?></div>
                    <div class="fw-bold text-dark fs-6 mb-0"><?= $k['value'] ?></div>
                    <div class="text-muted" style="font-size:10px;"><?= $k['sub'] ?></div>
                </div>
            </div>
            <div class="text-end mt-2"><small class="text-<?= $k['color'] ?> fw-bold" style="font-size:10px;">View Details →</small></div>
        </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row 1 -->
<div class="row g-4 mb-4">
    <!-- Registration Trend 30 days -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">User Registrations — Last 30 Days</h6>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= $stats['new_week'] ?> this week</span>
            </div>
            <canvas id="regChart" height="110"></canvas>
        </div>
    </div>
    <!-- Gender Donut -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 chart-card">
            <h6 class="fw-bold mb-3">Gender Distribution</h6>
            <canvas id="genderChart" height="150"></canvas>
            <div class="d-flex justify-content-center gap-4 mt-3 small">
                <div><span class="badge bg-primary me-1"> </span> Male: <?= $male ?></div>
                <div><span class="badge bg-danger me-1"> </span> Female: <?= $female ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-4 mb-4">
    <!-- Revenue Trend -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 chart-card">
            <h6 class="fw-bold mb-3">Revenue Trend — Last 12 Months</h6>
            <canvas id="revChart" height="120"></canvas>
        </div>
    </div>
    <!-- Plan Distribution -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 chart-card">
            <h6 class="fw-bold mb-3">Active Subscription Plans</h6>
            <?php if(empty($plan_dist)): ?>
                <div class="text-center text-muted pt-5 opacity-50"><i class="bi bi-inbox fs-1"></i><p class="mt-2 small">No active subscriptions</p></div>
            <?php else: ?>
                <canvas id="planChart" height="150"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="row g-4">
    <!-- Top Cities -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h6 class="fw-bold mb-3">Top Cities</h6>
            <?php if(empty($top_cities)): ?>
                <div class="text-muted small text-center pt-3 opacity-50">No city data yet</div>
            <?php else: ?>
                <?php $maxCity = $top_cities[0]['cnt']; ?>
                <?php foreach($top_cities as $i => $city): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-bold"><?= htmlspecialchars($city['city']) ?></span>
                            <span class="text-muted"><?= $city['cnt'] ?> users</span>
                        </div>
                        <div class="progress rounded-pill" style="height:6px;">
                            <div class="progress-bar bg-primary" style="width:<?= round(($city['cnt']/$maxCity)*100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Recent Transactions -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
            <div class="card-header bg-white px-4 py-3 border-bottom fw-bold">Recent Transactions</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light"><tr>
                        <th class="ps-4 py-2 border-0 text-muted fw-bold">User</th>
                        <th class="py-2 border-0 text-muted fw-bold">Plan</th>
                        <th class="py-2 border-0 text-muted fw-bold">Amount</th>
                        <th class="py-2 border-0 text-muted fw-bold">Via</th>
                        <th class="py-2 border-0 text-muted fw-bold">Status</th>
                        <th class="py-2 border-0 text-muted fw-bold">Date</th>
                        <th class="pe-4 py-2 border-0 text-muted fw-bold">Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php if(empty($recent_txns)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted opacity-50">No transactions yet</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_txns as $t):
                            $rawStatus = $t['status'];
                            $displayStatus = ($rawStatus === 'Approved') ? 'Completed' : $rawStatus;
                            $sc = in_array($rawStatus, ['Completed','Approved']) ? 'success' : ($rawStatus === 'Pending' ? 'warning' : 'danger');
                            $isManualSrc = !in_array($t['source'], ['Online']);
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($t['full_name']) ?></td>
                            <td><?= $t['plan_name'] ?? '—' ?></td>
                            <td class="fw-bold">Rs. <?= number_format($t['amount'], 0) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:10px;">
                                    <?= htmlspecialchars($t['source'] ?? 'Online') ?>
                                </span>
                            </td>
                            <td><span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> rounded-pill px-2"><?= $displayStatus ?></span></td>
                            <td class="text-muted"><?= date('M d, Y', strtotime($t['payment_date'])) ?></td>
                            <td class="pe-4">
                                <div class="d-flex gap-1">
                                    <!-- Delete Transaction -->
                                    <form method="POST" onsubmit="return confirm('Delete this transaction?')">
                                        <input type="hidden" name="txn_action" value="delete">
                                        <input type="hidden" name="txn_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="uid" value="<?= $t['user_id'] ?>">
                                        <input type="hidden" name="source" value="<?= $isManualSrc ? 'manual' : 'online' ?>">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" title="Delete Transaction" style="font-size:11px;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <!-- End Plan -->
                                    <form method="POST" onsubmit="return confirm('End membership plan for this user?')">
                                        <input type="hidden" name="txn_action" value="end_plan">
                                        <input type="hidden" name="txn_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="uid" value="<?= $t['user_id'] ?>">
                                        <input type="hidden" name="source" value="<?= $isManualSrc ? 'manual' : 'online' ?>">
                                        <button class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0" title="End Membership" style="font-size:11px;">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-end border-top px-4 py-2">
                <a href="payments.php" class="small fw-bold text-primary text-decoration-none">View all transactions →</a>
            </div>
        </div>
    </div>
</div>

<script>
const regCtx = document.getElementById('regChart').getContext('2d');
new Chart(regCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($reg_labels) ?>,
        datasets: [{ label: 'Registrations', data: <?= json_encode($reg_data) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.4, pointRadius: 3, borderWidth: 2.5 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { borderDash: [4,4] } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } } } }
});

const revCtx = document.getElementById('revChart').getContext('2d');
new Chart(revCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($rev_labels) ?>,
        datasets: [{ label: 'Revenue (Rs.)', data: <?= json_encode($rev_data) ?>, backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 6 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [4,4] } }, x: { grid: { display: false } } } }
});

<?php if(!empty($plan_dist)): ?>
const planCtx = document.getElementById('planChart').getContext('2d');
new Chart(planCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($plan_labels) ?>,
        datasets: [{ data: <?= json_encode($plan_data) ?>, backgroundColor: ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { padding: 12, font: { size: 11 } } } }, cutout: '65%' }
});
<?php endif; ?>

const gCtx = document.getElementById('genderChart').getContext('2d');
new Chart(gCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male','Female'],
        datasets: [{ data: [<?= $male ?>, <?= $female ?>], backgroundColor: ['#6366f1','#ec4899'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: { plugins: { legend: { display: false } }, cutout: '60%' }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>