<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Fetch payment history - Fixed column name from payment_date to created_at
$stmt = $pdo->prepare("SELECT p.*, s.plan_name 
                       FROM payments p 
                       JOIN subscriptions s ON p.plan_id = s.plan_id 
                       WHERE p.user_id = ? 
                       ORDER BY p.payment_date DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .billing-card {
        border: none;
        border-radius: 24px;
        background: white;
        overflow: hidden;
    }

    .table-premium thead {
        background: #f8fafc;
    }

    .table-premium th {
        padding: 20px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        border: none;
    }

    .table-premium td {
        padding: 20px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .status-badge {
        padding: 6px 14px;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .status-completed {
        background: #ecfdf5;
        color: #059669;
    }

    .status-pending {
        background: #fffbeb;
        color: #d97706;
    }

    .status-failed {
        background: #fef2f2;
        color: #dc2626;
    }

    .amount-text {
        font-weight: 700;
        color: #1e293b;
    }

    .transaction-id {
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 0.85rem;
        color: #6366f1;
        background: #eef2ff;
        padding: 4px 8px;
        border-radius: 6px;
    }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">Billing History</h2>
                    <p class="text-muted small">Manage your invoices and track your premium subscriptions.</p>
                </div>
                <a href="subscription.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-plus-circle me-2"></i> Upgrade Plan
                </a>
            </div>

            <div class="card billing-card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-premium mb-0">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Plan Details</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="opacity-25 mb-3">
                                            <i class="bi bi-receipt display-1"></i>
                                        </div>
                                        <h5 class="text-muted fw-bold">No Transactions Found</h5>
                                        <p class="text-muted small">Your billing history will appear here after your first
                                            purchase.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td><span class="transaction-id">#<?= htmlspecialchars($pay['transaction_id']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-slate-800"><?= htmlspecialchars($pay['plan_name']) ?></div>
                                            <small class="text-muted">Subscription Upgrade</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-wallet2 text-primary"></i>
                                                <span
                                                    class="small fw-medium"><?= htmlspecialchars($pay['payment_gateway']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-secondary small">
                                            <?= date('M d, Y', strtotime($pay['payment_date'])) ?>
                                        </td>
                                        <td><span class="amount-text"><?= formatPrice($pay['amount']) ?></span></td>
                                        <td class="text-center">
                                            <span class="status-badge status-<?= strtolower($pay['status']) ?>">
                                                <i class="bi bi-dot fs-4 align-middle"></i><?= $pay['status'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-light rounded-circle p-2" title="View Full Receipt">
                                                <i class="bi bi-eye text-muted"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 p-4 rounded-4 bg-white shadow-sm border d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-indigo-soft p-3 rounded-circle">
                        <i class="bi bi-question-circle-fill text-primary"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Need assistance with a payment?</h6>
                        <p class="text-muted small mb-0">Our billing team is available 24/7 to resolve your queries.</p>
                    </div>
                </div>
                <a href="support.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">Contact Support</a>
            </div>

        </main>
    </div>
</div>

<style>
    .bg-indigo-soft {
        background: #eef2ff;
    }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>