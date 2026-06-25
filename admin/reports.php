<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_reports');

$filter = $_GET['filter'] ?? 'all';

// Handle Actions (Resolve Report, Suspend User)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'], $_POST['reported_user'])) {
    $action = $_POST['action'];
    $rid = intval($_POST['report_id']);
    $uid = intval($_POST['reported_user']);
    
    if ($action === 'resolve') {
        $pdo->prepare("UPDATE reports SET status = 'Resolved' WHERE id = ?")->execute([$rid]);
        set_flash("Report marked as resolved.");
    } elseif ($action === 'suspend_resolve') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET status = 'Suspended' WHERE id = ?")->execute([$uid]);
            $pdo->prepare("UPDATE reports SET status = 'Resolved' WHERE id = ?")->execute([$rid]);
            $pdo->commit();
            set_flash("User suspended and report resolved.", "success");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash("Error processing action.", "danger");
        }
    }
    header("Location: reports.php?filter=$filter");
    exit();
}

// Build Query
$query = "SELECT r.*, r.id as report_id,
          reporter.full_name as reporter_name, reporter.id as reporter_id,
          reported.full_name as reported_name, reported.id as reported_uid, reported.status as user_status,
          m.message_text
          FROM reports r
          JOIN users reporter ON r.reported_by = reporter.id
          JOIN users reported ON r.reported_user = reported.id
          LEFT JOIN messages m ON r.item_type = 'Message' AND r.item_id = m.id";

if ($filter === 'profile') {
    $query .= " WHERE r.item_type = 'Profile'";
} elseif ($filter === 'chat') {
    $query .= " WHERE r.item_type = 'Message'";
}

$query .= " ORDER BY (r.status = 'Pending') DESC, r.created_at DESC";

$stmt = $pdo->query($query);
$reports = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h2 fw-bold text-dark mb-0">Moderation Center</h1>
        <p class="text-muted small">Manage and review all safety reports from platform members.</p>
    </div>
    <div class="col-auto">
        <div class="badge bg-danger rounded-pill px-3 py-2 shadow-sm">
            <i class="bi bi-shield-fill-exclamation me-1"></i> <?= count(array_filter($reports, fn($r) => $r['status'] === 'Pending')) ?> Actionable Reports
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill gap-2">
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'all' ? 'active bg-dark' : 'text-dark' ?> fw-bold rounded-3" href="reports.php?filter=all">
                    <i class="bi bi-grid-fill me-2"></i> All Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'profile' ? 'active bg-dark' : 'text-dark' ?> fw-bold rounded-3" href="reports.php?filter=profile">
                    <i class="bi bi-person-badge-fill me-2"></i> Profile Violations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'chat' ? 'active bg-dark' : 'text-dark' ?> fw-bold rounded-3" href="reports.php?filter=chat">
                    <i class="bi bi-chat-square-text-fill me-2"></i> Chat Misconduct
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 border-0">Reporter</th>
                        <th class="border-0">Accused User</th>
                        <th class="border-0">Issue Details</th>
                        <th class="border-0 text-center">Resolution</th>
                        <th class="pe-4 text-end border-0">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($reports)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No reports found in this section.</td></tr>
                    <?php else: ?>
                        <?php foreach($reports as $r): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($r['reporter_name']) ?></div>
                                    <small class="text-muted">UID #<?= $r['reporter_id'] ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-danger"><?= htmlspecialchars($r['reported_name']) ?></div>
                                    <div class="badge bg-<?= $r['user_status'] === 'Active' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $r['user_status'] === 'Active' ? 'success' : 'danger' ?>" style="font-size: 9px;"><?= strtoupper($r['user_status']) ?></div>
                                </td>
                                <td style="max-width:350px;">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25" style="font-size: 10px;"><?= strtoupper($r['item_type']) ?></span>
                                        <small class="text-muted" style="font-size:11px;"><?= date('M d, h:i A', strtotime($r['created_at'])) ?></small>
                                    </div>
                                    <div class="small fw-bold text-dark"><?= htmlspecialchars($r['reason']) ?></div>
                                    <?php if ($r['item_type'] === 'Message' && $r['message_text']): ?>
                                        <div class="bg-light p-2 rounded-3 mt-2 border-start border-4 border-primary border-opacity-50">
                                            <small class="text-muted d-block mb-1 fw-bold" style="font-size: 9px;">REPORTED CONTENT:</small>
                                            <span class="small italic">"<?= htmlspecialchars($r['message_text']) ?>"</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($r['status'] === 'Pending'): ?>
                                        <div class="spinner-grow spinner-grow-sm text-danger me-1" role="status"></div>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3">PENDING</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3">RESOLVED</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if($r['status'] === 'Pending'): ?>
                                        <div class="btn-group shadow-sm">
                                            <form action="reports.php?filter=<?= $filter ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="resolve">
                                                <input type="hidden" name="report_id" value="<?= $r['report_id'] ?>">
                                                <input type="hidden" name="reported_user" value="<?= $r['reported_uid'] ?>">
                                                <button type="submit" class="btn btn-sm btn-white border fw-bold text-success" title="Mark as fixed"><i class="bi bi-check-circle-fill"></i></button>
                                            </form>
                                            <a href="user_details.php?id=<?= $r['reported_uid'] ?>" class="btn btn-sm btn-white border fw-bold text-primary" title="Investigate User"><i class="bi bi-eye-fill"></i></a>
                                            <?php if ($r['user_status'] === 'Active'): ?>
                                                <form action="reports.php?filter=<?= $filter ?>" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="suspend_resolve">
                                                    <input type="hidden" name="report_id" value="<?= $r['report_id'] ?>">
                                                    <input type="hidden" name="reported_user" value="<?= $r['reported_uid'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-white border fw-bold text-danger" title="Suspend User & Resolve" onclick="return confirm('Immediately suspend this user account?');"><i class="bi bi-ban"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border disabled"><i class="bi bi-lock-fill"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
