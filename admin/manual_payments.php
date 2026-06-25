<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_admin();

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = (int)$_POST['payment_id'];
    $action = $_POST['action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    // Fetch payment details
    $stmt = $pdo->prepare("SELECT p.*, s.duration_months, s.plan_name FROM manual_payments p JOIN subscriptions s ON p.plan_id = s.plan_id WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if ($payment && $payment['status'] === 'Pending') {
        if ($action === 'Approve') {
            $pdo->beginTransaction();
            try {
                // 1. Update Payment Status
                $updatePay = $pdo->prepare("UPDATE manual_payments SET status = 'Approved', admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $updatePay->execute([$admin_notes, $payment_id]);

                $user_id = $payment['user_id'];
                $duration = $payment['duration_months'];
                $plan_id = $payment['plan_id'];
                
                if ($plan_id == 5) {
                    // Profile Boost Logic (7 Days Highlight)
                    $expiry = date('Y-m-d', strtotime("+7 days"));
                    $updateUser = $pdo->prepare("UPDATE users SET is_highlighted = 1, highlight_expiry = ?, highlight_priority = 100 WHERE id = ?");
                    $updateUser->execute([$expiry, $user_id]);
                    
                    $msg = "Profile Boost activated until $expiry.";
                } else {
                    // Membership Plan Logic
                    $userStmt = $pdo->prepare("SELECT expiry_date FROM users WHERE id = ?");
                    $userStmt->execute([$user_id]);
                    $currentUser = $userStmt->fetch();
                    
                    $startDate = date('Y-m-d');
                    if ($currentUser['expiry_date'] && $currentUser['expiry_date'] > $startDate) {
                        $startDate = $currentUser['expiry_date'];
                    }
                    
                    $endDate = date('Y-m-d', strtotime("+$duration months", strtotime($startDate)));
                    
                    $updateUser = $pdo->prepare("UPDATE users SET plan_id = ?, expiry_date = ? WHERE id = ?");
                    $updateUser->execute([$plan_id, $endDate, $user_id]);
                    
                    $msg = "Membership activated until $endDate.";
                }

                // 4. Record in user_subscriptions (Always record for log)
                $insertSub = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'Active')");
                $finalEnd = ($plan_id == 5) ? date('Y-m-d', strtotime("+7 days")) : $endDate;
                $insertSub->execute([$user_id, $plan_id, date('Y-m-d'), $finalEnd]);

                $pdo->commit();
                set_flash("Payment approved! $msg", "success");
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash("Error during approval: " . $e->getMessage(), "danger");
            }
        } elseif ($action === 'Reject') {
            $updatePay = $pdo->prepare("UPDATE manual_payments SET status = 'Rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?");
            if ($updatePay->execute([$admin_notes, $payment_id])) {
                set_flash("Payment request rejected.", "warning");
            } else {
                set_flash("Failed to reject payment.", "danger");
            }
        }
    } else {
        set_flash("Payment request not found or already processed.", "danger");
    }
    header("Location: manual_payments.php");
    exit();
}

// Fetch Pending Payments
$status = $_GET['status'] ?? 'Pending';
$stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email, s.plan_name 
                       FROM manual_payments p 
                       JOIN users u ON p.user_id = u.id 
                       JOIN subscriptions s ON p.plan_id = s.plan_id 
                       WHERE p.status = ? 
                       ORDER BY p.created_at DESC");
$stmt->execute([$status]);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h3 fw-bold text-dark mb-0">Manual Payment Verification</h1>
        <p class="text-muted small">Verify manual transactions and activate premium memberships.</p>
    </div>
    <div class="col-auto">
        <div class="btn-group shadow-sm">
            <a href="?status=Pending" class="btn <?= $status == 'Pending' ? 'btn-primary' : 'btn-white border' ?> btn-sm px-3 fw-bold">Pending</a>
            <a href="?status=Approved" class="btn <?= $status == 'Approved' ? 'btn-primary' : 'btn-white border' ?> btn-sm px-3 fw-bold">Approved</a>
            <a href="?status=Rejected" class="btn <?= $status == 'Rejected' ? 'btn-primary' : 'btn-white border' ?> btn-sm px-3 fw-bold">Rejected</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="px-4 py-3 border-0 small text-uppercase text-muted fw-bold">User</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Plan Details</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Transaction</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Amount</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Submission</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted opacity-25"></i>
                            <p class="text-muted mt-2">No <?= strtolower($status) ?> payments found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($payments as $p): ?>
                        <tr>
                            <td class="px-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['full_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($p['email']) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['plan_name']) ?></div>
                                <div class="small text-muted">Manual Upgrade</div>
                            </td>
                            <td>
                                <div class="badge bg-light text-dark fw-normal border"><?= $p['payment_method'] ?></div>
                                <div class="small text-muted mt-1 font-monospace"><?= htmlspecialchars($p['transaction_id']) ?></div>
                            </td>
                            <td>
                                <span class="fw-bold text-primary">Rs. <?= number_format($p['amount'], 0) ?></span>
                            </td>
                            <td>
                                <div class="small text-dark"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                                <div class="small text-muted"><?= date('h:i A', strtotime($p['created_at'])) ?></div>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-white border rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $p['id'] ?>">
                                    Review Proof
                                </button>

                                <!-- Review Modal -->
                                <div class="modal fade" id="reviewModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold">Review Payment Proof</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row g-4">
                                                    <div class="col-md-7">
                                                        <div class="bg-light p-2 rounded-3 text-center mb-3">
                                                            <a href="/online-rishta-system/assets/images/payments/<?= $p['screenshot'] ?>" target="_blank">
                                                                <img src="/online-rishta-system/assets/images/payments/<?= $p['screenshot'] ?>" class="img-fluid rounded-2 shadow-sm" style="max-height: 500px;" alt="Receipt">
                                                            </a>
                                                        </div>
                                                        <p class="text-center text-muted small mb-0"><i class="bi bi-zoom-in"></i> Click image to open in full size</p>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="card border bg-light bg-opacity-50 h-100 p-3 rounded-4">
                                                            <h6 class="fw-bold mb-3 border-bottom pb-2">Verification Details</h6>
                                                            <div class="mb-3">
                                                                <label class="small text-muted d-block mb-1">Claimed ID</label>
                                                                <div class="fw-bold fs-5 text-primary"><?= htmlspecialchars($p['transaction_id']) ?></div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="small text-muted d-block mb-1">Expected Amount</label>
                                                                <div class="fw-bold fs-5">Rs. <?= number_format($p['amount'], 0) ?></div>
                                                            </div>
                                                            <div class="mb-4">
                                                                <label class="small text-muted d-block mb-1">Payment via</label>
                                                                <div class="fw-bold"><?= $p['payment_method'] ?></div>
                                                            </div>

                                                            <?php if ($p['status'] === 'Pending'): ?>
                                                                <form action="" method="POST">
                                                                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label small fw-bold">Admin Remarks (Optional)</label>
                                                                        <textarea name="admin_notes" class="form-control form-control-sm" rows="3" placeholder="Notes for user..."></textarea>
                                                                    </div>
                                                                    <div class="d-grid gap-2">
                                                                        <button type="submit" name="action" value="Approve" class="btn btn-success fw-bold py-2 rounded-pill shadow-sm" onclick="return confirm('Are you sure you want to APPROVE this payment? This will active the user membership.')">
                                                                            <i class="bi bi-check-circle-fill me-2"></i> Approve & Activate
                                                                        </button>
                                                                        <button type="submit" name="action" value="Reject" class="btn btn-outline-danger fw-bold py-2 rounded-pill" onclick="return confirm('Are you sure you want to REJECT this payment?')">
                                                                            <i class="bi bi-x-circle-fill me-2"></i> Reject Request
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            <?php else: ?>
                                                                <div class="alert <?= $p['status'] == 'Approved' ? 'alert-success' : 'alert-danger' ?> py-2 mb-0 rounded-3">
                                                                    <i class="bi <?= $p['status'] == 'Approved' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-2"></i>
                                                                    <b><?= $p['status'] ?></b> on <?= date('M d, Y', strtotime($p['updated_at'])) ?>
                                                                </div>
                                                                <?php if ($p['admin_notes']): ?>
                                                                    <div class="mt-3 small p-2 border bg-white rounded">
                                                                        <span class="text-muted">Notes:</span> <?= htmlspecialchars($p['admin_notes']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
