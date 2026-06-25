<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('verify_profiles');

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['status'])) {
    $uid = intval($_POST['user_id']);
    $status = $_POST['status'];

    if ($status === 'Approve') {
        $pdo->prepare("UPDATE user_profiles SET is_verified = 1 WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("INSERT INTO verification_logs (admin_id, target_user_id, action) VALUES (?, ?, 'Approved')")
            ->execute([$_SESSION['user_id'], $uid]);
        set_flash("Profile #$uid has been verified successfully.", "success");
    } elseif ($status === 'Reject') {
        $pdo->prepare("UPDATE user_profiles SET is_verified = 0, verification_doc = NULL, cnic_front = NULL, cnic_back = NULL WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("INSERT INTO verification_logs (admin_id, target_user_id, action) VALUES (?, ?, 'Rejected')")
            ->execute([$_SESSION['user_id'], $uid]);
        set_flash("Profile #$uid verification has been rejected.", "warning");
    }
    header("Location: verifications.php");
    exit();
}

$verifs = $pdo->query("SELECT u.full_name, u.email, u.profile_pic, u.gender, u.created_at as joined_at, p.* 
                       FROM user_profiles p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE p.is_verified = 0 
                         AND p.verification_doc IS NOT NULL AND p.verification_doc != ''
                         AND u.status = 'Active' 
                       ORDER BY p.id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.doc-preview { border-radius: 12px; overflow: hidden; border: 2px solid #f0f0f5; transition: all 0.2s; cursor: zoom-in; }
.doc-preview:hover { border-color: #6366f1; transform: scale(1.02); }
.doc-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; display: block; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom flex-wrap gap-2">
    <div>
        <h1 class="h2 fw-bold mb-0">Verification Review</h1>
        <p class="text-muted small mb-0">Review submitted identity documents and make a decision.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="verification_logs.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">
            <i class="bi bi-clock-history me-1"></i> View History
        </a>
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fs-6">
            <?= count($verifs) ?> Pending
        </span>
    </div>
</div>

<?php if (empty($verifs)): ?>
    <div class="text-center py-5 mt-5">
        <i class="bi bi-shield-check display-1 text-success opacity-50"></i>
        <h4 class="mt-3 fw-bold text-dark">All Clear!</h4>
        <p class="text-muted">No pending verification requests. All submitted profiles have been reviewed.</p>
        <a href="verification_logs.php" class="btn btn-primary rounded-pill px-4 fw-bold">View History</a>
    </div>

<?php else: ?>

<?php foreach ($verifs as $v): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    <!-- User Header Bar -->
    <div class="card-header bg-white px-4 py-3 border-bottom d-flex align-items-center gap-3">
        <img src="/online-rishta-system/assets/images/uploads/<?= $v['profile_pic'] ?: 'default.jpg' ?>"
             class="rounded-circle border" width="52" height="52" style="object-fit:cover;">
        <div class="flex-grow-1">
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($v['full_name']) ?></h5>
            <div class="small text-muted">
                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($v['email']) ?>
                <span class="mx-2">·</span>
                <i class="bi bi-gender-ambiguous me-1"></i><?= $v['gender'] ?>
                <span class="mx-2">·</span>
                <i class="bi bi-calendar me-1"></i>Joined <?= date('M d, Y', strtotime($v['joined_at'])) ?>
            </div>
        </div>
        <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
            <i class="bi bi-hourglass-split me-1"></i> Pending Review
        </span>
    </div>

    <div class="card-body p-4">
        <div class="row g-4">
            <!-- Documents Column -->
            <div class="col-lg-8">
                <div class="small fw-bold text-uppercase text-muted mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-card-image text-primary"></i> Submitted Documents
                </div>
                <div class="row g-3">
                    <!-- Selfie -->
                    <div class="col-md-4">
                        <span class="doc-label"><i class="bi bi-person-bounding-box me-1"></i>Self Photo</span>
                        <?php if($v['verification_doc']): ?>
                            <a href="/online-rishta-system/assets/images/uploads/<?= $v['verification_doc'] ?>" target="_blank">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $v['verification_doc'] ?>"
                                     class="img-fluid w-100 doc-preview" style="height:180px; object-fit:cover;" title="Click to enlarge">
                            </a>
                        <?php else: ?>
                            <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted" style="height:180px;">
                                <i class="bi bi-image fs-1 opacity-25"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- CNIC Front -->
                    <div class="col-md-4">
                        <span class="doc-label"><i class="bi bi-front me-1"></i>CNIC Front</span>
                        <?php if($v['cnic_front']): ?>
                            <a href="/online-rishta-system/assets/images/uploads/<?= $v['cnic_front'] ?>" target="_blank">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $v['cnic_front'] ?>"
                                     class="img-fluid w-100 doc-preview" style="height:180px; object-fit:cover;" title="Click to enlarge">
                            </a>
                        <?php else: ?>
                            <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted" style="height:180px;">
                                <i class="bi bi-image fs-1 opacity-25"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- CNIC Back -->
                    <div class="col-md-4">
                        <span class="doc-label"><i class="bi bi-back me-1"></i>CNIC Back</span>
                        <?php if($v['cnic_back']): ?>
                            <a href="/online-rishta-system/assets/images/uploads/<?= $v['cnic_back'] ?>" target="_blank">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $v['cnic_back'] ?>"
                                     class="img-fluid w-100 doc-preview" style="height:180px; object-fit:cover;" title="Click to enlarge">
                            </a>
                        <?php else: ?>
                            <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted" style="height:180px;">
                                <i class="bi bi-image fs-1 opacity-25"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-zoom-in me-1"></i> Click any image to open full size in a new tab.
                </p>
            </div>

            <!-- Action Column -->
            <div class="col-lg-4">
                <div class="small fw-bold text-uppercase text-muted mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-check2-circle text-primary"></i> Admin Decision
                </div>
                <div class="bg-light rounded-4 p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="mb-3 small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Review all three documents carefully before approving. Rejecting will remove submitted documents and the user will need to re-apply.
                        </div>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <form action="verifications.php" method="POST">
                            <input type="hidden" name="user_id" value="<?= $v['user_id'] ?>">
                            <input type="hidden" name="status" value="Approve">
                            <button type="submit" class="btn btn-success w-100 fw-bold py-2 rounded-3 shadow-sm"
                                    onclick="return confirm('Approve verification for <?= htmlspecialchars($v['full_name'], ENT_QUOTES) ?>?')">
                                <i class="bi bi-check-circle-fill me-2"></i> Approve Profile
                            </button>
                        </form>
                        <form action="verifications.php" method="POST">
                            <input type="hidden" name="user_id" value="<?= $v['user_id'] ?>">
                            <input type="hidden" name="status" value="Reject">
                            <button type="submit" class="btn btn-outline-danger w-100 fw-bold py-2 rounded-3"
                                    onclick="return confirm('Reject and remove documents for <?= htmlspecialchars($v['full_name'], ENT_QUOTES) ?>?')">
                                <i class="bi bi-x-circle-fill me-2"></i> Reject & Remove
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>