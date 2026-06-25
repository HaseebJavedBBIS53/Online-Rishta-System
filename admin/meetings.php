<?php
// admin/meetings.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_meetings'); // RBAC enforced

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'Pending';

$status_filter = "";
if ($filter === 'Approved') {
    $status_filter = "m.status = 'Approved by Admin'";
} elseif ($filter === 'Rejected') {
    $status_filter = "m.status = 'Rejected by Admin'";
} elseif ($filter === 'Pending') {
    $status_filter = "m.status = 'Waiting for Admin Approval'";
} else {
    $status_filter = "m.status IN ('Waiting for Admin Approval', 'Approved by Admin', 'Rejected by Admin')";
}

// Fetch Meetings
$stmt = $pdo->prepare("SELECT m.*, 
                       u1.full_name as sender_name, u1.email as sender_email, u1.phone as sender_phone, u1.gender as sender_gender,
                       u2.full_name as receiver_name, u2.email as receiver_email, u2.phone as receiver_phone, u2.gender as receiver_gender
                       FROM meetings m 
                       JOIN users u1 ON m.sender_id = u1.id 
                       JOIN users u2 ON m.receiver_id = u2.id 
                       WHERE $status_filter 
                       ORDER BY m.created_at DESC");
$stmt->execute();
$meetings = $stmt->fetchAll();

// Fetch admin action stats
$stats_stmt = $pdo->query("SELECT 
                            SUM(CASE WHEN status = 'Approved by Admin' THEN 1 ELSE 0 END) as approved_count,
                            SUM(CASE WHEN status = 'Rejected by Admin' THEN 1 ELSE 0 END) as rejected_count,
                            SUM(CASE WHEN status = 'Waiting for Admin Approval' THEN 1 ELSE 0 END) as pending_count,
                            COUNT(*) as total_count
                           FROM meetings 
                           WHERE status IN ('Waiting for Admin Approval', 'Approved by Admin', 'Rejected by Admin')");
$stats = $stats_stmt->fetch();
$approved_count = $stats['approved_count'] ?? 0;
$rejected_count = $stats['rejected_count'] ?? 0;
$pending_count = $stats['pending_count'] ?? 0;
$total_count = $stats['total_count'] ?? 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-5 g-4">
        <div class="col-lg-5">
            <h2 class="fw-bold mb-1">Meeting Management</h2>
            <p class="text-muted mb-0">Review and approve physical or online meetings for safety.</p>
        </div>
        <div class="col-lg-7">
            <div class="d-flex gap-3 flex-wrap justify-content-lg-end">
                <a href="?filter=All" class="text-decoration-none">
                    <div class="bg-white px-4 py-3 rounded-4 shadow-sm border text-center transition-all hover-shadow <?= $filter === 'All' ? 'border-dark border-2' : '' ?>"
                        style="min-width: 110px;">
                        <span class="d-block small text-muted fw-bold text-uppercase mb-1"
                            style="font-size: 0.7rem; letter-spacing: 0.5px;">All</span>
                        <span class="fs-4 fw-bold text-dark"><?= $total_count ?></span>
                    </div>
                </a>
                <a href="?filter=Pending" class="text-decoration-none">
                    <div class="bg-white px-4 py-3 rounded-4 shadow-sm border text-center transition-all hover-shadow <?= $filter === 'Pending' ? 'border-primary border-2' : '' ?>"
                        style="min-width: 110px;">
                        <span class="d-block small text-muted fw-bold text-uppercase mb-1"
                            style="font-size: 0.7rem; letter-spacing: 0.5px;">Pending</span>
                        <span class="fs-4 fw-bold text-primary"><?= $pending_count ?></span>
                    </div>
                </a>
                <a href="?filter=Approved" class="text-decoration-none">
                    <div class="bg-white px-4 py-3 rounded-4 shadow-sm border text-center transition-all hover-shadow <?= $filter === 'Approved' ? 'border-success border-2' : '' ?>"
                        style="min-width: 110px;">
                        <span class="d-block small text-muted fw-bold text-uppercase mb-1"
                            style="font-size: 0.7rem; letter-spacing: 0.5px;">Approved</span>
                        <span class="fs-4 fw-bold text-success"><?= $approved_count ?></span>
                    </div>
                </a>
                <a href="?filter=Rejected" class="text-decoration-none">
                    <div class="bg-white px-4 py-3 rounded-4 shadow-sm border text-center transition-all hover-shadow <?= $filter === 'Rejected' ? 'border-danger border-2' : '' ?>"
                        style="min-width: 110px;">
                        <span class="d-block small text-muted fw-bold text-uppercase mb-1"
                            style="font-size: 0.7rem; letter-spacing: 0.5px;">Rejected</span>
                        <span class="fs-4 fw-bold text-danger"><?= $rejected_count ?></span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <?php display_flash(); ?>

    <?php if (empty($meetings)): ?>
        <div class="text-center py-5">
            <div class="display-1 text-muted mb-3"><i class="bi bi-inbox"></i></div>
            <h4 class="fw-bold text-muted">No Meetings Found</h4>
            <p class="text-muted">There are no meetings matching the current filter.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($meetings as $m): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                        <!-- Status Header Indicator -->
                        <div class="position-absolute top-0 start-0 w-100"
                            style="height: 5px; background-color: var(--bs-<?= getAdminStatusColor($m['status']) ?>);"></div>

                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span
                                    class="badge bg-<?= getAdminStatusColor($m['status']) ?>-soft text-<?= getAdminStatusColor($m['status']) ?> px-3 py-2 rounded-pill fw-bold border border-<?= getAdminStatusColor($m['status']) ?> border-opacity-25">
                                    <?= $m['status'] ?>
                                </span>
                                <span class="text-muted small fw-bold font-monospace">#MEET-<?= $m['id'] ?></span>
                            </div>

                            <!-- Meeting Meta -->
                            <div class="bg-light rounded-4 p-3 mb-4">
                                <div class="row g-3 text-center">
                                    <div class="col-6 border-end">
                                        <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem;">
                                            Date & Time</div>
                                        <div class="fw-bold text-dark"><i class="bi bi-calendar-event text-primary me-1"></i>
                                            <?= date('M d, Y', strtotime($m['meeting_date'])) ?></div>
                                        <div class="small text-muted"><i class="bi bi-clock text-primary me-1"></i>
                                            <?= date('h:i A', strtotime($m['meeting_time'])) ?></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem;">
                                            Location</div>
                                        <span
                                            class="badge bg-<?= $m['meeting_type'] == 'Online' ? 'info' : 'secondary' ?> mb-1"><?= $m['meeting_type'] ?></span>
                                        <div class="small text-truncate w-100"
                                            title="<?= htmlspecialchars($m['meeting_type'] == 'Online' ? $m['meeting_link'] : $m['location']) ?>">
                                            <?php if ($m['meeting_type'] == 'Online'): ?>
                                                <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank"
                                                    class="text-decoration-none"><i class="bi bi-link-45deg"></i> Link</a>
                                            <?php else: ?>
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($m['location']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Participants -->
                            <div class="position-relative">
                                <!-- Sender -->
                                <div
                                    class="d-flex align-items-start gap-3 p-3 border rounded-4 border-primary border-opacity-25 bg-primary bg-opacity-10 mb-3">
                                    <div class="avatar-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                                        style="width: 45px; height: 45px;">
                                        <?= substr(htmlspecialchars($m['sender_name']), 0, 1) ?>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold mb-0 text-primary text-truncate">
                                                <?= htmlspecialchars($m['sender_name']) ?></h6>
                                            <span class="badge bg-primary rounded-pill"
                                                style="font-size: 0.6rem;">Initiator</span>
                                        </div>
                                        <div class="small text-muted mb-2 d-flex gap-2">
                                            <span><?= htmlspecialchars($m['sender_gender']) ?></span>
                                            <span class="border-start ps-2"><a href="user_details.php?id=<?= $m['sender_id'] ?>"
                                                    class="text-decoration-none">View Profile</a></span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="mailto:<?= htmlspecialchars($m['sender_email']) ?>"
                                                class="btn btn-sm btn-light border p-1 rounded-circle" title="Email"><i
                                                    class="bi bi-envelope text-primary"></i></a>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $m['sender_phone']) ?>"
                                                target="_blank" class="btn btn-sm btn-light border p-1 rounded-circle"
                                                title="WhatsApp"><i class="bi bi-whatsapp text-success"></i></a>
                                        </div>
                                    </div>
                                </div>

                                <div class="position-absolute top-50 start-50 translate-middle bg-white border rounded-circle shadow-sm d-flex align-items-center justify-content-center"
                                    style="width: 32px; height: 32px; z-index: 10;">
                                    <i class="bi bi-arrow-down text-muted"></i>
                                </div>

                                <!-- Receiver -->
                                <div
                                    class="d-flex align-items-start gap-3 p-3 border rounded-4 border-info border-opacity-25 bg-info bg-opacity-10">
                                    <div class="avatar-circle bg-info text-white fw-bold d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                                        style="width: 45px; height: 45px;">
                                        <?= substr(htmlspecialchars($m['receiver_name']), 0, 1) ?>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold mb-0 text-info text-darken text-truncate">
                                                <?= htmlspecialchars($m['receiver_name']) ?></h6>
                                            <span class="badge bg-info rounded-pill" style="font-size: 0.6rem;">Recipient</span>
                                        </div>
                                        <div class="small text-muted mb-2 d-flex gap-2">
                                            <span><?= htmlspecialchars($m['receiver_gender']) ?></span>
                                            <span class="border-start ps-2"><a
                                                    href="user_details.php?id=<?= $m['receiver_id'] ?>"
                                                    class="text-decoration-none text-info text-darken">View Profile</a></span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="mailto:<?= htmlspecialchars($m['receiver_email']) ?>"
                                                class="btn btn-sm btn-light border p-1 rounded-circle" title="Email"><i
                                                    class="bi bi-envelope text-primary"></i></a>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $m['receiver_phone']) ?>"
                                                target="_blank" class="btn btn-sm btn-light border p-1 rounded-circle"
                                                title="WhatsApp"><i class="bi bi-whatsapp text-success"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Actions -->
                        <?php if ($m['status'] === 'Waiting for Admin Approval'): ?>
                            <div class="card-footer bg-white border-top-0 p-4 pt-0">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <form action="process/meeting_approval.php" method="POST" class="m-0 p-0">
                                            <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success rounded-pill w-100 fw-bold shadow-sm">
                                                <i class="bi bi-check-circle-fill me-1"></i> Approve
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-danger rounded-pill w-100 fw-bold shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?= $m['id'] ?>">
                                            <i class="bi bi-x-circle-fill me-1"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Reason Modal -->
                            <div class="modal fade" id="rejectModal<?= $m['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow rounded-4">
                                        <div class="modal-header border-0 pb-0 shadow-sm mb-2">
                                            <h5 class="modal-title fw-bold">Admin Rejection</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="process/meeting_approval.php" method="POST">
                                            <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <div class="modal-body">
                                                <label class="form-label small fw-bold text-muted">Safety Rejection Reason</label>
                                                <textarea name="reason" class="form-control" rows="3"
                                                    placeholder="Explain why this meeting is rejected..." required></textarea>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-light rounded-pill px-4"
                                                    data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Reject
                                                    Meeting</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($m['status'] === 'Approved by Admin'): ?>
                            <div class="card-footer bg-white border-top-0 p-4 pt-0">
                                <form action="process/meeting_approval.php" method="POST" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to cancel this approved meeting?');">
                                    <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-outline-danger rounded-pill w-100 fw-bold shadow-sm">
                                        <i class="bi bi-x-circle me-1"></i> Cancel Meeting
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .bg-success-soft {
        background-color: rgba(25, 135, 84, 0.1);
    }

    .bg-danger-soft {
        background-color: rgba(220, 53, 69, 0.1);
    }

    .bg-primary-soft {
        background-color: rgba(13, 110, 253, 0.1);
    }

    .bg-info-soft {
        background-color: rgba(13, 202, 240, 0.1);
    }

    .text-info-darken {
        color: #087990 !important;
    }

    .hover-shadow:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
    }

    .transition-all {
        transition: all 0.2s ease-in-out;
    }
</style>

<?php
function getAdminStatusColor($status)
{
    switch ($status) {
        case 'Waiting for Admin Approval':
            return 'primary';
        case 'Approved by Admin':
            return 'success';
        case 'Rejected by Admin':
            return 'danger';
        default:
            return 'secondary';
    }
}
require_once __DIR__ . '/includes/footer.php';
?>