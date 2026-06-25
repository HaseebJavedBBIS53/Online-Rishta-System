<?php
// user/meetings.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
$user_id = $_SESSION['user_id'];

// Fetch Sent Meetings
$sent_stmt = $pdo->prepare("SELECT m.*, u.full_name, u.profile_pic FROM meetings m JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = ? ORDER BY m.created_at DESC");
$sent_stmt->execute([$user_id]);
$sent_meetings = $sent_stmt->fetchAll();

// Fetch Received Meetings
$recv_stmt = $pdo->prepare("SELECT m.*, u.full_name, u.profile_pic FROM meetings m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? ORDER BY m.created_at DESC");
$recv_stmt->execute([$user_id]);
$received_meetings = $recv_stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h3 class="fw-bold mb-4">My Meetings</h3>

            <?php display_flash(); ?>

            <div class="overflow-auto scrollbar-hidden mb-4">
                <ul class="nav nav-pills bg-white p-2 rounded-4 shadow-sm flex-nowrap text-nowrap" id="meetingTabs" role="tablist" style="width: fit-content; min-width: 100%;">
                    <li class="nav-item flex-fill">
                        <button class="nav-link active fw-bold px-3 py-2 w-100" id="received-tab" data-bs-toggle="pill" data-bs-target="#received" type="button">Received Requests</button>
                    </li>
                    <li class="nav-item flex-fill">
                        <button class="nav-link fw-bold px-3 py-2 w-100" id="sent-tab" data-bs-toggle="pill" data-bs-target="#sent" type="button">Sent Requests</button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="meetingTabsContent">
                <!-- Received Requests -->
                <div class="tab-pane fade show active" id="received">
                    <?php if (empty($received_meetings)): ?>
                        <div class="text-center p-5 bg-white rounded-4 shadow-sm">
                            <i class="bi bi-calendar-x display-1 opacity-25"></i>
                            <p class="mt-3 text-muted">No meeting requests received yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($received_meetings as $m): ?>
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                        <div class="card-body p-4">
                                            <div class="d-flex flex-column flex-sm-row align-items-sm-center">
                                                <div class="d-flex align-items-center mb-3 mb-sm-0 flex-grow-1">
                                                    <img src="/online-rishta-system/assets/images/uploads/<?= $m['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle me-3 shadow-sm border" width="55" height="55" style="object-fit:cover;">
                                                    <div>
                                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($m['full_name']) ?></h6>
                                                        <p class="text-muted mb-0" style="font-size: 0.75rem;">
                                                            <i class="bi bi-calendar3 me-1"></i> <?= date('d M, Y', strtotime($m['meeting_date'])) ?> 
                                                            <i class="bi bi-clock ms-2 me-1"></i> <?= date('h:i A', strtotime($m['meeting_time'])) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="ms-sm-auto">
                                                    <span class="badge bg-<?= getStatusColor($m['status']) ?> rounded-pill px-3 py-2" style="font-size: 0.7rem;"><?= $m['status'] ?></span>
                                                </div>
                                            </div>
                                            <div class="mt-3 bg-light p-3 rounded-3 small">
                                                <strong>Type:</strong> <?= $m['meeting_type'] ?><br>
                                                <?php if($m['meeting_type'] == 'Physical'): ?>
                                                    <strong>Location:</strong> <?= htmlspecialchars($m['location']) ?><br>
                                                <?php else: ?>
                                                    <strong>Link:</strong> <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank">Join Meeting</a><br>
                                                <?php endif; ?>
                                                <?php if($m['notes']): ?>
                                                    <div class="mt-2 text-muted fst-italic">"<?= htmlspecialchars($m['notes']) ?>"</div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($m['status'] === 'Pending User Response'): ?>
                                                <div class="mt-3 d-flex gap-2">
                                                    <form action="process/meeting_actions.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                                        <input type="hidden" name="action" value="accept">
                                                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm">Accept</button>
                                                    </form>
                                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $m['id'] ?>">Reject</button>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade" id="rejectModal<?= $m['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow rounded-4">
                                                            <div class="modal-header border-0 pb-0">
                                                                <h5 class="modal-title fw-bold">Reject Meeting</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form action="process/meeting_actions.php" method="POST">
                                                                <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <div class="modal-body pt-3">
                                                                    <label class="form-label text-muted small fw-bold">Reason for Rejection (Optional)</label>
                                                                    <textarea name="reason" class="form-control" rows="3" placeholder="I'm not available on this date..."></textarea>
                                                                </div>
                                                                <div class="modal-footer border-0">
                                                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                                                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirm Reject</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sent Requests -->
                <div class="tab-pane fade" id="sent">
                    <?php if (empty($sent_meetings)): ?>
                        <div class="text-center p-5 bg-white rounded-4 shadow-sm">
                            <i class="bi bi-send-x display-1 opacity-25"></i>
                            <p class="mt-3 text-muted">You haven't requested any meetings yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($sent_meetings as $m): ?>
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                        <div class="card-body p-4">
                                            <div class="d-flex flex-column flex-sm-row align-items-sm-center">
                                                <div class="d-flex align-items-center mb-3 mb-sm-0 flex-grow-1">
                                                    <img src="/online-rishta-system/assets/images/uploads/<?= $m['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle me-3 shadow-sm border" width="55" height="55" style="object-fit:cover;">
                                                    <div>
                                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($m['full_name']) ?></h6>
                                                        <p class="text-muted mb-0" style="font-size: 0.75rem;">Requested: <?= date('d M, Y', strtotime($m['meeting_date'])) ?> at <?= date('h:i A', strtotime($m['meeting_time'])) ?></p>
                                                    </div>
                                                </div>
                                                <div class="ms-sm-auto">
                                                    <span class="badge bg-<?= getStatusColor($m['status']) ?> rounded-pill px-3 py-2" style="font-size: 0.7rem;"><?= $m['status'] ?></span>
                                                </div>
                                            </div>
                                            <?php if ($m['status'] === 'Pending User Response' || $m['status'] === 'Waiting for Admin Approval'): ?>
                                                <div class="mt-3">
                                                    <form action="process/meeting_actions.php" method="POST" onsubmit="return confirm('Cancel this meeting request?')">
                                                        <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <button type="submit" class="btn btn-link text-danger p-0 small text-decoration-none fw-bold">Cancel Request</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($m['rejection_reason']): ?>
                                                <div class="mt-3 alert alert-danger py-2 mb-0 small">
                                                    <strong>Rejection Reason:</strong> <?= htmlspecialchars($m['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .scrollbar-hidden::-webkit-scrollbar { display: none; }
    .scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }
</style>
<?php 
function getStatusColor($status) {
    switch ($status) {
        case 'Pending User Response': return 'warning text-dark';
        case 'Accepted by Receiver': return 'info';
        case 'Waiting for Admin Approval': return 'primary';
        case 'Approved by Admin': return 'success';
        case 'Rejected by Receiver':
        case 'Rejected by Admin': return 'danger';
        case 'Completed': return 'dark';
        case 'Cancelled': return 'secondary';
        default: return 'light text-dark';
    }
}
require_once dirname(__DIR__) . '/includes/footer.php'; 
?>
