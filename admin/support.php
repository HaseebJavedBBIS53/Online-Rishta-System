<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_support');

$admin_id = $_SESSION['user_id'];

// Handle Block/Unblock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'block_user') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_support_blocked = 1 WHERE id = ?")->execute([$uid]);
        set_flash("User blocked from support.", "success");
    } elseif ($_POST['action'] === 'unblock_user') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_support_blocked = 0 WHERE id = ?")->execute([$uid]);
        set_flash("User unblocked.", "success");
    }
    header("Location: support.php" . (isset($_POST['ticket_id']) ? "?view=" . $_POST['ticket_id'] : ""));
    exit();
}

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $new_status = sanitize_input($_POST['status']);
    
    $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$new_status, $ticket_id]);
    set_flash("Ticket status updated to $new_status.", "success");
    header("Location: support.php?view=" . $ticket_id);
    exit();
}

// Handle Reply POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $reply = sanitize_input($_POST['reply_message']);
    
    $chk = $pdo->prepare("SELECT id, status FROM support_tickets WHERE id = ?");
    $chk->execute([$ticket_id]);
    $ticket = $chk->fetch();
    
    if ($ticket && $ticket['status'] !== 'Closed' && $reply) {
        $pdo->prepare("INSERT INTO ticket_replies (ticket_id, sender_id, message) VALUES (?, ?, ?)")
            ->execute([$ticket_id, $admin_id, $reply]);
        
        // Auto-change status to 'In Progress' if Open
        if ($ticket['status'] === 'Open') {
            $pdo->prepare("UPDATE support_tickets SET status = 'In Progress' WHERE id = ?")->execute([$ticket_id]);
        }
        set_flash("Reply sent to user.", "success");
    } else {
        set_flash("Cannot reply to a closed ticket.", "danger");
    }
    header("Location: support.php?view=" . $ticket_id);
    exit();
}

// View specific ticket
$view_ticket_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$active_ticket = null;
$replies = [];

if ($view_ticket_id) {
    $stmt = $pdo->prepare("SELECT t.*, u.full_name as user_name, u.email as user_email, u.profile_pic, u.is_support_blocked 
                           FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$view_ticket_id]);
    $active_ticket = $stmt->fetch();
    
    if ($active_ticket) {
        $rstmt = $pdo->prepare("SELECT r.*, u.full_name, u.role FROM ticket_replies r JOIN users u ON r.sender_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
        $rstmt->execute([$view_ticket_id]);
        $replies = $rstmt->fetchAll();
    }
}

// Filter Logic
$filter_status = $_GET['filter'] ?? 'All';
$query = "SELECT t.*, u.full_name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id";
$params = [];
if (in_array($filter_status, ['Open', 'In Progress', 'Resolved', 'Closed'])) {
    $query .= " WHERE t.status = ?";
    $params[] = $filter_status;
}
$query .= " ORDER BY CASE WHEN t.status = 'Open' THEN 1 WHEN t.status = 'In Progress' THEN 2 ELSE 3 END, t.created_at DESC";

$tickets_stmt = $pdo->prepare($query);
$tickets_stmt->execute($params);
$all_tickets = $tickets_stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4 min-vh-100 bg-light">
    <div class="row mb-4">
        <div class="col-8">
            <h2 class="fw-bold mb-0"><i class="bi bi-headset text-primary me-2"></i> Support Hub</h2>
            <p class="text-muted mb-0">Manage user inquiries and reports.</p>
        </div>
        <div class="col-4 text-end">
            <div class="dropdown">
                <button class="btn btn-white border shadow-sm dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-funnel-fill me-1 text-primary"></i> Filter: <?= $filter_status ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="support.php?filter=All">All Tickets</a></li>
                    <li><a class="dropdown-item" href="support.php?filter=Open">Open</a></li>
                    <li><a class="dropdown-item" href="support.php?filter=In Progress">In Progress</a></li>
                    <li><a class="dropdown-item" href="support.php?filter=Resolved">Resolved</a></li>
                    <li><a class="dropdown-item" href="support.php?filter=Closed">Closed</a></li>
                </ul>
            </div>
        </div>
    </div>

    <?php display_flash(); ?>

    <div class="row g-4">
        <!-- Ticket List Sidebar -->
        <div class="col-lg-<?= $active_ticket ? '4' : '12' ?>">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Tickets Queue</h5>
                    <span class="badge bg-primary rounded-pill"><?= count($all_tickets) ?></span>
                </div>
                <div class="list-group list-group-flush rounded-bottom-4 overflow-auto" style="height: calc(100vh - 200px);">
                    <?php if (empty($all_tickets)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-emoji-smile display-4 opacity-25"></i>
                            <p class="mt-3 fw-bold">All caught up!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_tickets as $t): ?>
                            <a href="support.php?view=<?= $t['id'] ?>&filter=<?= urlencode($filter_status) ?>" class="list-group-item list-group-item-action p-3 <?= ($view_ticket_id === $t['id']) ? 'bg-light border-primary' : '' ?>" style="border-left: 3px solid <?= ($view_ticket_id === $t['id']) ? '#0d6efd' : 'transparent' ?>;">
                                <div class="d-flex justify-content-between w-100 mb-1">
                                    <h6 class="mb-0 fw-bold text-truncate" style="max-width: 70%;"><?= htmlspecialchars($t['subject']) ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($t['created_at'])) ?></small>
                                </div>
                                <div class="mb-2 text-truncate small text-muted">From: <?= htmlspecialchars($t['user_name']) ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <?php 
                                    $bclass = 'bg-secondary';
                                    if ($t['status'] === 'Open') $bclass = 'bg-danger shadow-sm';
                                    if ($t['status'] === 'In Progress') $bclass = 'bg-warning text-dark';
                                    if ($t['status'] === 'Resolved' || $t['status'] === 'Closed') $bclass = 'bg-success';
                                    ?>
                                    <span class="badge <?= $bclass ?> rounded-pill" style="font-size: 0.7rem;"><?= $t['status'] ?></span>
                                    <small class="text-muted fw-bold">#<?= $t['id'] ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ticket Chat Workspace -->
        <?php if ($active_ticket): ?>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column">
                <!-- Top Header -->
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <img src="/online-rishta-system/assets/images/uploads/<?= $active_ticket['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle border" width="45" height="45" style="object-fit:cover;">
                        <div>
                            <h5 class="fw-bold mb-0 text-truncate" style="max-width: 300px;"><?= htmlspecialchars($active_ticket['subject']) ?></h5>
                            <small class="text-muted">By <a href="user_management.php?view=<?= $active_ticket['user_id'] ?>" class="text-primary fw-bold text-decoration-none"><?= htmlspecialchars($active_ticket['user_name']) ?></a> • <?= htmlspecialchars($active_ticket['user_email']) ?></small>
                        </div>
                    </div>
                    <div>
                        <!-- Block User Toggle -->
                        <form action="support.php" method="POST" class="d-inline me-2">
                            <input type="hidden" name="ticket_id" value="<?= $active_ticket['id'] ?>">
                            <input type="hidden" name="user_id" value="<?= $active_ticket['user_id'] ?>">
                            <?php if ($active_ticket['is_support_blocked']): ?>
                                <input type="hidden" name="action" value="unblock_user">
                                <button type="submit" class="btn btn-sm btn-outline-success fw-bold" onclick="return confirm('Allow user to use support again?');">Unblock Support</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="block_user">
                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold" onclick="return confirm('Block user from creating tickets and replying?');"><i class="bi bi-ban"></i> Block User</button>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Status Update Dropdown -->
                        <form action="support.php" method="POST" class="d-inline">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="ticket_id" value="<?= $active_ticket['id'] ?>">
                            <div class="input-group input-group-sm d-inline-flex" style="width:auto;">
                                <select name="status" class="form-select fw-bold bg-light" onchange="this.form.submit()">
                                    <option value="Open" <?= $active_ticket['status']=='Open'?'selected':'' ?>>Open</option>
                                    <option value="In Progress" <?= $active_ticket['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                                    <option value="Resolved" <?= $active_ticket['status']=='Resolved'?'selected':'' ?>>Resolved</option>
                                    <option value="Closed" <?= $active_ticket['status']=='Closed'?'selected':'' ?>>Closed</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Messages Thread -->
                <div class="card-body bg-light overflow-auto p-4 d-flex flex-column gap-3" style="height: calc(100vh - 350px);">
                    <!-- Original Message -->
                    <div class="d-flex justify-content-start">
                        <div class="bg-white rounded-4 rounded-top-left-0 p-3 shadow-sm mx-w-75 border-start border-primary border-4">
                            <div class="fw-bold small mb-1 text-primary">
                                <?= htmlspecialchars($active_ticket['user_name']) ?> &bull; <span class="fw-normal text-muted"><?= date('M d, h:i A', strtotime($active_ticket['created_at'])) ?></span>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($active_ticket['message'])) ?></p>
                        </div>
                    </div>
                    
                    <!-- Replies -->
                    <?php foreach ($replies as $r): 
                        $is_admin = ($r['role'] === 'Admin');
                    ?>
                        <div class="d-flex <?= $is_admin ? 'justify-content-end' : 'justify-content-start' ?>">
                            <div class="<?= $is_admin ? 'bg-primary text-white rounded-top-right-0' : 'bg-white rounded-top-left-0 border-start border-primary border-4' ?> rounded-4 p-3 shadow-sm mx-w-75">
                                <div class="fw-bold small mb-1 <?= $is_admin ? 'opacity-75' : 'text-primary' ?>">
                                    <?= $is_admin ? '<i class="bi bi-shield-lock-fill"></i> Support Team (You)' : htmlspecialchars($r['full_name']) ?> &bull; <span class="fw-normal <?= $is_admin ? 'opacity-75 text-white' : 'text-muted' ?>"><?= date('M d, h:i A', strtotime($r['created_at'])) ?></span>
                                </div>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Reply Box -->
                <div class="card-footer bg-white p-3 border-top">
                    <?php if ($active_ticket['status'] !== 'Closed'): ?>
                    <form action="support.php" method="POST">
                        <input type="hidden" name="reply_ticket" value="1">
                        <input type="hidden" name="ticket_id" value="<?= $active_ticket['id'] ?>">
                        <div class="input-group">
                            <textarea name="reply_message" class="form-control bg-light border-0" rows="2" placeholder="Write a response to the user..." required></textarea>
                            <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-send-fill mb-1 d-block"></i> Send</button>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="text-center text-muted small p-2 bg-light rounded-3">This ticket is marked as Closed. Reopen it to send more replies.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.mx-w-75 { max-width: 75%; }
.rounded-top-right-0 { border-top-right-radius: 0 !important; }
.rounded-top-left-0 { border-top-left-radius: 0 !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>