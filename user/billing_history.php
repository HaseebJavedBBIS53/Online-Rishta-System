<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
$user_id = $_SESSION['user_id'];

// Get billing history
$stmt = $pdo->prepare("SELECT p.*, s.plan_name 
                       FROM manual_payments p 
                       JOIN subscriptions s ON p.plan_id = s.plan_id 
                       WHERE p.user_id = ? 
                       ORDER BY p.created_at DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 fw-bold mb-0">Billing History</h2>
                <a href="subscription.php" class="btn btn-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i> New Upgrade
                </a>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 border-0 small text-uppercase text-muted fw-bold">Date</th>
                                <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Plan Details</th>
                                <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Method & ID</th>
                                <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Amount</th>
                                <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Status</th>
                                <th class="py-3 border-0 small text-uppercase text-muted fw-bold text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="bi bi-receipt fs-1 text-muted opacity-25"></i>
                                            <p class="text-muted mt-2">No billing history found.</p>
                                            <a href="subscription.php" class="btn btn-outline-primary btn-sm rounded-pill mt-2">Browse Plans</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($payments as $p): ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="fw-bold text-slate-700"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                                            <div class="small text-muted"><?= date('h:i A', strtotime($p['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($p['plan_name']) ?></div>
                                            <div class="small text-muted">Manual Activation</div>
                                        </td>
                                        <td>
                                            <div class="badge bg-light text-dark fw-normal border"><?= $p['payment_method'] ?></div>
                                            <div class="small text-muted mt-1 font-monospace"><?= htmlspecialchars($p['transaction_id']) ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-bold">Rs. <?= number_format($p['amount'], 0) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = 'bg-warning';
                                            if ($p['status'] === 'Approved') $statusClass = 'bg-success';
                                            if ($p['status'] === 'Rejected') $statusClass = 'bg-danger';
                                            ?>
                                            <span class="badge rounded-pill <?= $statusClass ?>"><?= $p['status'] ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill" data-bs-toggle="modal" data-bs-target="#viewModal<?= $p['id'] ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>

                                            <!-- Modal -->
                                            <div class="modal fade" id="viewModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h5 class="modal-title fw-bold">Payment Proof</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <img src="/online-rishta-system/assets/images/payments/<?= $p['screenshot'] ?>" class="img-fluid rounded-3 mb-3 border" style="max-height: 400px;" alt="Receipt">
                                                            <div class="text-start bg-light p-3 rounded-3">
                                                                <div class="row g-2 mb-0">
                                                                    <div class="col-6 small text-muted">Transaction ID:</div>
                                                                    <div class="col-6 small fw-bold text-end"><?= htmlspecialchars($p['transaction_id']) ?></div>
                                                                    <div class="col-6 small text-muted">Amount Submitted:</div>
                                                                    <div class="col-6 small fw-bold text-end text-primary">Rs. <?= number_format($p['amount'], 0) ?></div>
                                                                    <?php if ($p['admin_notes']): ?>
                                                                        <div class="col-12 mt-2 pt-2 border-top">
                                                                            <div class="small text-muted mb-1">Admin Remark:</div>
                                                                            <div class="p-2 bg-white rounded border small text-danger"><?= htmlspecialchars($p['admin_notes']) ?></div>
                                                                        </div>
                                                                    <?php endif; ?>
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

            <div class="mt-4 bg-white p-4 rounded-4 shadow-sm border-0">
                <h6 class="fw-bold mb-3">Verification Process</h6>
                <div class="row g-4 small text-muted">
                    <div class="col-md-4">
                        <div class="d-flex gap-3">
                            <i class="bi bi-clock-history fs-4 text-warning"></i>
                            <div>
                                <span class="d-block fw-bold text-dark">Pending Status</span>
                                Your request is in queue. Admin will verify the transaction ID with the statement.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-3">
                            <i class="bi bi-patch-check-fill fs-4 text-success"></i>
                            <div>
                                <span class="d-block fw-bold text-dark">Approved Status</span>
                                Your plan is activated immediately. The expiry date starts from the approval moment.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-3">
                            <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
                            <div>
                                <span class="d-block fw-bold text-dark">Rejected Status</span>
                                If details are incorrect or fake, request is rejected. You can resubmit with correct info.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
