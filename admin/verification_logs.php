<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('verify_profiles');

// Summary Counts
$total_approved = $pdo->query("SELECT COUNT(*) FROM verification_logs WHERE action = 'Approved'")->fetchColumn();
$total_rejected = $pdo->query("SELECT COUNT(*) FROM verification_logs WHERE action = 'Rejected'")->fetchColumn();

$logs = $pdo->query("SELECT l.*, a.full_name as admin_name, u.full_name as user_name, u.profile_pic
                     FROM verification_logs l 
                     JOIN users a ON l.admin_id = a.id 
                     JOIN users u ON l.target_user_id = u.id 
                     ORDER BY l.created_at DESC LIMIT 100")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold mb-0">Verification History</h1>
        <p class="text-muted small mb-0">Complete audit log of all admin verification decisions.</p>
    </div>
    <a href="verifications.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
        <i class="bi bi-shield-check me-1"></i> Pending Reviews
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 rounded-4 shadow-sm p-3 text-center h-100 bg-success bg-opacity-10">
            <div class="display-6 fw-bold text-success"><?= $total_approved ?></div>
            <div class="small text-muted fw-bold mt-1">Total Approved</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 rounded-4 shadow-sm p-3 text-center h-100 bg-danger bg-opacity-10">
            <div class="display-6 fw-bold text-danger"><?= $total_rejected ?></div>
            <div class="small text-muted fw-bold mt-1">Total Rejected</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 rounded-4 shadow-sm p-3 text-center h-100 bg-primary bg-opacity-10">
            <div class="display-6 fw-bold text-primary"><?= $total_approved + $total_rejected ?></div>
            <div class="small text-muted fw-bold mt-1">Total Reviewed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 rounded-4 shadow-sm p-3 text-center h-100">
            <div class="display-6 fw-bold text-dark">
                <?= ($total_approved + $total_rejected) > 0 ? round(($total_approved / ($total_approved + $total_rejected)) * 100) : 0 ?>%
            </div>
            <div class="small text-muted fw-bold mt-1">Approval Rate</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 border-0 small text-uppercase text-muted fw-bold">User</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Decision</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Reviewed By</th>
                    <th class="py-3 border-0 small text-uppercase text-muted fw-bold">Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($logs)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 opacity-25 d-block mb-2"></i>
                            No verification history found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($logs as $l): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="/online-rishta-system/assets/images/uploads/<?= $l['profile_pic'] ?: 'default.jpg' ?>" 
                                         class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                                    <div>
                                        <div class="fw-bold small"><?= htmlspecialchars($l['user_name']) ?></div>
                                        <div class="text-muted" style="font-size:10px;">User ID #<?= $l['target_user_id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($l['action'] === 'Approved'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Approved
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-bold">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small fw-bold text-dark"><?= htmlspecialchars($l['admin_name']) ?></span>
                            </td>
                            <td>
                                <div class="small fw-bold"><?= date('M d, Y', strtotime($l['created_at'])) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= date('h:i A', strtotime($l['created_at'])) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
