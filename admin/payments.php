<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_payments');

// Handle Status Updates / End Plan / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['payment_id'])) {
    $pid = intval($_POST['payment_id']);
    $notes = sanitize_input($_POST['notes'] ?? '');

    if ($_POST['action'] === 'update_status') {
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE payments SET status = ?, notes = ? WHERE id = ?");
        $stmt->execute([$status, $notes, $pid]);

        if ($status === 'Completed') {
            $payment = $pdo->prepare("SELECT p.*, s.duration_months FROM payments p LEFT JOIN subscriptions s ON p.plan_id = s.plan_id WHERE p.id = ?");
            $payment->execute([$pid]);
            $payment = $payment->fetch();
            if ($payment && $payment['plan_id']) {
                $uid = $payment['user_id'];
                $plan_id = $payment['plan_id'];
                $duration = $payment['duration_months'] ?: 1;
                $start = date('Y-m-d');
                $end = date('Y-m-d', strtotime("+$duration months"));
                $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'Active') ON DUPLICATE KEY UPDATE plan_id=VALUES(plan_id), end_date=VALUES(end_date), status='Active'")
                    ->execute([$uid, $plan_id, $start, $end]);
                $pdo->prepare("UPDATE users SET plan_id = ?, expiry_date = ? WHERE id = ?")->execute([$plan_id, $end, $uid]);
            }
        }
        set_flash("Transaction #$pid updated successfully.");
    }

    if ($_POST['action'] === 'end_plan') {
        $uid = intval($_POST['uid']);
        $pdo->prepare("UPDATE users SET plan_id=1, expiry_date=NULL WHERE id=?")->execute([$uid]);
        $pdo->prepare("UPDATE user_subscriptions SET status='Expired', end_date=CURDATE() WHERE user_id=? AND status='Active'")->execute([$uid]);
        set_flash("Membership plan ended for user #$uid.", "warning");
    }

    if ($_POST['action'] === 'delete_txn') {
        $src = $_POST['src'] ?? 'online';
        if ($src === 'manual') {
            $pdo->prepare("DELETE FROM manual_payments WHERE id=?")->execute([$pid]);
        } else {
            $pdo->prepare("DELETE FROM payments WHERE id=?")->execute([$pid]);
        }
        set_flash("Transaction deleted.", "warning");
    }

    header("Location: payments.php");
    exit();
}

$search = $_GET['q'] ?? '';
$plan_filter = $_GET['plan'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$source_filter = $_GET['source'] ?? 'all'; // 'all', 'online', 'manual'

// ─── Online Payments ──────────────────────────────────────────
$sql = "SELECT p.id, p.user_id, p.amount, p.status, p.payment_date as txn_date, 
               p.transaction_id, p.payment_gateway as method, p.notes,
               u.full_name, u.email, s.plan_name, 'Online' as source
        FROM payments p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN subscriptions s ON p.plan_id = s.plan_id";
$where = []; $params = [];
if ($search) { $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($plan_filter) { $where[] = "p.plan_id = ?"; $params[] = $plan_filter; }
if ($status_filter) { $where[] = "p.status = ?"; $params[] = $status_filter; }
if ($date_from) { $where[] = "DATE(p.payment_date) >= ?"; $params[] = $date_from; }
if ($date_to) { $where[] = "DATE(p.payment_date) <= ?"; $params[] = $date_to; }
if ($where) $sql .= " WHERE " . implode(" AND ", $where);

$payments_online = [];
if ($source_filter !== 'manual') {
    $stmt = $pdo->prepare($sql . " ORDER BY p.payment_date DESC");
    $stmt->execute($params);
    $payments_online = $stmt->fetchAll();
}

// ─── Manual Payments ──────────────────────────────────────────
$sqlM = "SELECT mp.id, mp.user_id, mp.amount, mp.status, mp.created_at as txn_date,
                mp.transaction_id, mp.payment_method as method, mp.admin_notes as notes,
                u.full_name, u.email, s.plan_name, mp.screenshot, 'Manual' as source
         FROM manual_payments mp 
         JOIN users u ON mp.user_id = u.id 
         LEFT JOIN subscriptions s ON mp.plan_id = s.plan_id";
$whereM = []; $paramsM = [];
if ($search) { $whereM[] = "(u.full_name LIKE ? OR u.email LIKE ? OR mp.transaction_id LIKE ?)"; $paramsM = array_merge($paramsM, ["%$search%","%$search%","%$search%"]); }
if ($plan_filter) { $whereM[] = "mp.plan_id = ?"; $paramsM[] = $plan_filter; }
// Normalize manual status for filter (Approved = Completed)
if ($status_filter === 'Completed') { $whereM[] = "mp.status = 'Approved'"; }
elseif ($status_filter === 'Failed') { $whereM[] = "mp.status = 'Rejected'"; }
elseif ($status_filter === 'Pending') { $whereM[] = "mp.status = 'Pending'"; }
if ($date_from) { $whereM[] = "DATE(mp.created_at) >= ?"; $paramsM[] = $date_from; }
if ($date_to) { $whereM[] = "DATE(mp.created_at) <= ?"; $paramsM[] = $date_to; }
if ($whereM) $sqlM .= " WHERE " . implode(" AND ", $whereM);

$payments_manual = [];
if ($source_filter !== 'online') {
    $stmtM = $pdo->prepare($sqlM . " ORDER BY mp.created_at DESC");
    $stmtM->execute($paramsM);
    $payments_manual = $stmtM->fetchAll();
}

// Merge and sort by date
$payments = array_merge($payments_online, $payments_manual);
usort($payments, fn($a,$b) => strtotime($b['txn_date']) - strtotime($a['txn_date']));

$plans = $pdo->query("SELECT plan_id, plan_name FROM subscriptions")->fetchAll();

// Combined stats
$online_rev    = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Completed'")->fetchColumn();
$manual_rev    = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM manual_payments WHERE status='Approved'")->fetchColumn();
$total_completed = $online_rev + $manual_rev;
$pending_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='Pending'")->fetchColumn()
               + $pdo->query("SELECT COUNT(*) FROM manual_payments WHERE status='Pending'")->fetchColumn();
$failed_count  = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='Failed'")->fetchColumn()
               + $pdo->query("SELECT COUNT(*) FROM manual_payments WHERE status='Rejected'")->fetchColumn();
$completed_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='Completed'")->fetchColumn()
                 + $pdo->query("SELECT COUNT(*) FROM manual_payments WHERE status='Approved'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4 pt-2">
    <div class="col">
        <h1 class="h2 fw-bold text-dark mb-0">Payment Tracking</h1>
        <p class="text-muted small mb-0">Monitor and manage all payment transactions on the platform.</p>
    </div>
    <div class="col-auto">
        <a href="manual_payments.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-cash-coin me-1"></i> Manual Payments
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="payments.php" class="text-decoration-none">
        <div class="card border-0 rounded-4 shadow-sm p-3 h-100 bg-success bg-opacity-10 stat-hover" style="cursor:pointer; transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div class="small text-uppercase text-muted fw-bold mb-1">Total Earned</div>
            <div class="h3 fw-bold text-success mb-0">Rs. <?= number_format($total_completed, 0) ?></div>
            <div class="small text-muted mt-1">Online + Manual</div>
            <div class="text-end mt-2"><small class="text-success fw-bold" style="font-size:10px;">All Transactions →</small></div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="payments.php?status=Completed" class="text-decoration-none">
        <div class="card border-0 rounded-4 shadow-sm p-3 h-100" style="cursor:pointer; transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div class="small text-uppercase text-muted fw-bold mb-1">Completed</div>
            <div class="h3 fw-bold text-dark mb-0"><?= $completed_count ?></div>
            <div class="small text-muted mt-1">Paid & Approved</div>
            <div class="text-end mt-2"><small class="text-primary fw-bold" style="font-size:10px;">View Completed →</small></div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="payments.php?status=Pending" class="text-decoration-none">
        <div class="card border-0 rounded-4 shadow-sm p-3 h-100 bg-warning bg-opacity-10" style="cursor:pointer; transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div class="small text-uppercase text-muted fw-bold mb-1">Pending</div>
            <div class="h3 fw-bold text-warning mb-0"><?= $pending_count ?></div>
            <div class="small text-muted mt-1">Awaiting Review</div>
            <div class="text-end mt-2"><small class="text-warning fw-bold" style="font-size:10px;">View Pending →</small></div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="payments.php?status=Failed" class="text-decoration-none">
        <div class="card border-0 rounded-4 shadow-sm p-3 h-100 bg-danger bg-opacity-10" style="cursor:pointer; transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div class="small text-uppercase text-muted fw-bold mb-1">Failed / Rejected</div>
            <div class="h3 fw-bold text-danger mb-0"><?= $failed_count ?></div>
            <div class="small text-muted mt-1">Declined payments</div>
            <div class="text-end mt-2"><small class="text-danger fw-bold" style="font-size:10px;">View Failed →</small></div>
        </div>
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Search User / Transaction</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-light border-0" placeholder="Name, Email, Ref ID" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Plan</label>
                <select name="plan" class="form-select bg-light border-0">
                    <option value="">All Plans</option>
                    <?php foreach($plans as $p): ?>
                        <option value="<?= $p['plan_id'] ?>" <?= $plan_filter == $p['plan_id'] ? 'selected' : '' ?>><?= $p['plan_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Status</label>
                <select name="status" class="form-select bg-light border-0">
                    <option value="">All</option>
                    <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Failed" <?= $status_filter == 'Failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Source</label>
                <select name="source" class="form-select bg-light border-0">
                    <option value="all" <?= $source_filter == 'all' ? 'selected' : '' ?>>All Sources</option>
                    <option value="online" <?= $source_filter == 'online' ? 'selected' : '' ?>>Online Gateway</option>
                    <option value="manual" <?= $source_filter == 'manual' ? 'selected' : '' ?>>Manual Payment</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">From</label>
                <input type="date" name="from" class="form-control bg-light border-0" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">To</label>
                <input type="date" name="to" class="form-control bg-light border-0" value="<?= $date_to ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark fw-bold rounded-pill px-4">Apply Filter</button>
                <a href="payments.php" class="btn btn-light fw-bold rounded-pill px-4">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 border-0 small text-uppercase text-muted fw-bold">Transaction</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">User & Plan</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Amount</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Gateway</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Date</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold text-center">Status</th>
                    <th class="pe-4 py-3 border-0 small text-uppercase text-muted fw-bold text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($payments)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                        No transactions found.
                    </td></tr>
                <?php else: ?>
                    <?php foreach($payments as $p): ?>
                        <?php
                            $rawStatus = $p['status'];
                            $displayStatus = in_array($rawStatus, ['Approved']) ? 'Completed' : ($rawStatus === 'Rejected' ? 'Failed' : $rawStatus);
                            $sc = in_array($rawStatus, ['Completed','Approved']) ? 'success' : ($rawStatus === 'Pending' ? 'warning' : 'danger');
                            $isManual = ($p['source'] === 'Manual');
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark font-monospace small"><?= strtoupper($p['transaction_id'] ?: 'N/A') ?></div>
                                <div class="d-flex gap-1 mt-1">
                                    <span class="badge <?= $isManual ? 'bg-warning text-dark' : 'bg-primary bg-opacity-10 text-primary' ?>" style="font-size:9px;">
                                        <?= $p['source'] ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['full_name']) ?></div>
                                <span class="badge bg-primary bg-opacity-10 text-primary small"><?= $p['plan_name'] ?? '—' ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">Rs. <?= number_format($p['amount'], 0) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['method'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                                <div class="small fw-bold"><?= date('M d, Y', strtotime($p['txn_date'])) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= date('h:i A', strtotime($p['txn_date'])) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> rounded-pill px-3 py-2 fw-bold border border-<?= $sc ?> border-opacity-25">
                                    <?= $displayStatus ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <?php if(!$isManual): ?>
                                        <button class="btn btn-sm btn-dark fw-bold rounded-pill px-3 view-btn"
                                                data-id="<?= $p['id'] ?>"
                                                data-uid="<?= $p['user_id'] ?>"
                                                data-status="<?= $rawStatus ?>"
                                                data-notes="<?= htmlspecialchars($p['notes'] ?? '') ?>">
                                            Manage
                                        </button>
                                    <?php else: ?>
                                        <a href="manual_payments.php" class="btn btn-sm btn-primary fw-bold rounded-pill px-3">Review</a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-warning rounded-pill px-2 end-plan-btn"
                                            data-id="<?= $p['id'] ?>"
                                            data-uid="<?= $p['user_id'] ?>"
                                            data-src="<?= $isManual ? 'manual' : 'online' ?>"
                                            title="End Membership">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-2 delete-btn"
                                            data-id="<?= $p['id'] ?>"
                                            data-src="<?= $isManual ? 'manual' : 'online' ?>"
                                            title="Delete Transaction">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Manage Modal -->
<div class="modal fade" id="manageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="payments.php" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="payment_id" id="modal_id">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Manage Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Override Status</label>
                        <select name="status" id="modal_status" class="form-select bg-light border-0">
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed / Authorized</option>
                            <option value="Failed">Failed / Declined</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="alert alert-info small py-2 px-3 rounded-3 border-0">
                        <i class="bi bi-info-circle me-1"></i> Marking as <strong>Completed</strong> will automatically activate the user's membership plan.
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Internal Notes</label>
                        <textarea name="notes" id="modal_notes" class="form-control bg-light border-0" rows="2" placeholder="Reason for override..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-warning fw-bold rounded-pill px-3" id="endPlanBtn">End Plan</button>
                        <button type="button" class="btn btn-outline-danger fw-bold rounded-pill px-3" id="deleteTxnBtn">Delete</button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-dark fw-bold px-4">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- End Plan Hidden Form -->
<form id="endPlanForm" action="payments.php" method="POST" class="d-none">
    <input type="hidden" name="action" value="end_plan">
    <input type="hidden" name="payment_id" id="ep_pid">
    <input type="hidden" name="uid" id="ep_uid">
    <input type="hidden" name="src" id="ep_src">
</form>

<!-- Delete Hidden Form -->
<form id="deleteTxnForm" action="payments.php" method="POST" class="d-none">
    <input type="hidden" name="action" value="delete_txn">
    <input type="hidden" name="payment_id" id="dt_pid">
    <input type="hidden" name="uid" value="0">
    <input type="hidden" name="src" id="dt_src">
</form>

<script>
// Manage modal buttons
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.onclick = function() {
        document.getElementById('modal_id').value = this.dataset.id;
        document.getElementById('modal_status').value = this.dataset.status;
        document.getElementById('modal_notes').value = this.dataset.notes;
        document.getElementById('ep_pid').value = this.dataset.id;
        document.getElementById('ep_uid').value = this.dataset.uid;
        document.getElementById('ep_src').value = 'online';
        document.getElementById('dt_pid').value = this.dataset.id;
        document.getElementById('dt_src').value = 'online';
        new bootstrap.Modal(document.getElementById('manageModal')).show();
    };
});

// End Plan from table row
document.querySelectorAll('.end-plan-btn').forEach(btn => {
    btn.onclick = function() {
        if (!confirm('End membership plan for this user?')) return;
        document.getElementById('ep_pid').value = this.dataset.id;
        document.getElementById('ep_uid').value = this.dataset.uid;
        document.getElementById('ep_src').value = this.dataset.src;
        document.getElementById('endPlanForm').submit();
    };
});

// Delete from table row
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.onclick = function() {
        if (!confirm('Delete this transaction record? This cannot be undone.')) return;
        document.getElementById('dt_pid').value = this.dataset.id;
        document.getElementById('dt_src').value = this.dataset.src;
        document.getElementById('deleteTxnForm').submit();
    };
});

// Modal End Plan button
document.getElementById('endPlanBtn').onclick = function() {
    if (!confirm('End membership plan for this user?')) return;
    document.getElementById('endPlanForm').submit();
};

// Modal Delete button
document.getElementById('deleteTxnBtn').onclick = function() {
    if (!confirm('Delete this transaction? This cannot be undone.')) return;
    document.getElementById('deleteTxnForm').submit();
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
