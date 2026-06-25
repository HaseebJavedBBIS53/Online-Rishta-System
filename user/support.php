<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if blocked
$stmt = $pdo->prepare("SELECT is_support_blocked FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$is_blocked = $stmt->fetchColumn() == 1;

// Handle New Ticket POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket']) && !$is_blocked) {
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if ($subject && $message) {
        $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message) VALUES (?, ?, ?)")
            ->execute([$user_id, $subject, $message]);
        set_flash("Support ticket created successfully. Our team will get back to you soon.", "success");
    } else {
        set_flash("Please provide both subject and message.", "danger");
    }
    header("Location: support.php");
    exit();
}

// Handle Reply POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket']) && !$is_blocked) {
    $ticket_id = (int)$_POST['ticket_id'];
    $reply = sanitize_input($_POST['reply_message']);
    
    // Verify ticket belongs to user
    $chk = $pdo->prepare("SELECT id, status FROM support_tickets WHERE id = ? AND user_id = ?");
    $chk->execute([$ticket_id, $user_id]);
    $ticket = $chk->fetch();
    
    if ($ticket && $ticket['status'] !== 'Closed' && $reply) {
        $pdo->prepare("INSERT INTO ticket_replies (ticket_id, sender_id, message) VALUES (?, ?, ?)")
            ->execute([$ticket_id, $user_id, $reply]);
        set_flash("Reply sent.", "success");
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
    $stmt = $pdo->prepare("SELECT t.*, u.full_name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.user_id = ?");
    $stmt->execute([$view_ticket_id, $user_id]);
    $active_ticket = $stmt->fetch();
    
    if ($active_ticket) {
        $rstmt = $pdo->prepare("SELECT r.*, u.full_name, u.role FROM ticket_replies r JOIN users u ON r.sender_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
        $rstmt->execute([$view_ticket_id]);
        $replies = $rstmt->fetchAll();
    }
}

// Fetch all user tickets
$tickets = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets->execute([$user_id]);
$all_tickets = $tickets->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Support Center</h2>
                <p class="text-muted small">Need help? Create a ticket and our agents will assist you.</p>
            </div>
            
            <div class="row g-4">
                <!-- Ticket List / Creation -->
                <div class="col-lg-<?= $active_ticket ? '4' : '12' ?> <?= ($active_ticket) ? 'd-none d-lg-block' : '' ?>">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                            <h5 class="fw-bold mb-0">My Tickets</h5>
                            <?php if ($active_ticket): ?>
                                <a href="support.php" class="btn btn-sm btn-outline-primary fw-bold" title="Back to main"><i class="bi bi-arrow-left"></i> Back</a>
                            <?php elseif (!$is_blocked): ?>
                                <button class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#newTicketModal"><i class="bi bi-plus-lg me-1"></i> New Ticket</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_blocked): ?>
                            <div class="alert alert-danger m-3 border-0 shadow-sm d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-0">Support Access Suspended</h6>
                                    <small>Your ability to contact support has been revoked.</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="list-group list-group-flush rounded-bottom-4 overflow-auto" style="max-height: 70vh;">
                            <?php if (empty($all_tickets)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-inbox-fill display-4 opacity-50 mb-2"></i>
                                    <p>No support tickets found.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($all_tickets as $t): ?>
                                    <a href="support.php?view=<?= $t['id'] ?>" class="list-group-item list-group-item-action p-3 <?= ($view_ticket_id === $t['id']) ? 'bg-light' : '' ?>">
                                        <div class="d-flex justify-content-between w-100 mb-1">
                                            <h6 class="mb-1 fw-bold text-truncate" style="max-width: 70%;"><?= htmlspecialchars($t['subject']) ?></h6>
                                            <small class="text-muted" style="white-space: nowrap;"><?= date('M d', strtotime($t['created_at'])) ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted text-truncate" style="max-width: 70%;"><?= htmlspecialchars($t['message']) ?></small>
                                            <?php 
                                            $badge_class = 'bg-secondary';
                                            if ($t['status'] === 'Open') $badge_class = 'bg-primary';
                                            if ($t['status'] === 'In Progress') $badge_class = 'bg-warning text-dark';
                                            if ($t['status'] === 'Resolved' || $t['status'] === 'Closed') $badge_class = 'bg-success';
                                            ?>
                                            <span class="badge <?= $badge_class ?> rounded-pill" style="font-size: 0.7rem;"><?= $t['status'] ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Ticket Chat View -->
                <?php if ($active_ticket): ?>
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white d-flex flex-column">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <a href="support.php" class="text-muted me-3 d-lg-none"><i class="bi bi-arrow-left fs-4"></i></a>
                                <div>
                                    <h6 class="fw-bold mb-0 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($active_ticket['subject']) ?></h6>
                                    <small class="text-muted" style="font-size: 0.7rem;">Ticket #<?= $active_ticket['id'] ?> • <strong><?= $active_ticket['status'] ?></strong></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body bg-light overflow-auto p-4 d-flex flex-column gap-3" style="max-height: 60vh;">
                            <!-- Original Message -->
                            <div class="d-flex justify-content-end">
                                <div class="bg-primary text-white rounded-4 rounded-top-right-0 p-3 shadow-sm mx-w-75">
                                    <div class="fw-bold small mb-1 opacity-75">You &bull; <?= date('M d, h:i A', strtotime($active_ticket['created_at'])) ?></div>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($active_ticket['message'])) ?></p>
                                </div>
                            </div>
                            
                            <!-- Replies -->
                            <?php foreach ($replies as $r): 
                                $is_me = ($r['sender_id'] == $user_id);
                            ?>
                                <div class="d-flex <?= $is_me ? 'justify-content-end' : 'justify-content-start' ?>">
                                     <div class="<?= $is_me ? 'bg-primary text-white rounded-top-right-0' : 'bg-white rounded-top-left-0' ?> rounded-4 p-3 shadow-sm mx-w-85 border <?= !$is_me ? 'border-light' : '' ?>">
                                         <div class="fw-bold small mb-1 <?= $is_me ? 'opacity-75' : 'text-primary' ?>" style="font-size: 0.75rem;">
                                             <?= $is_me ? 'You' : 'Support' ?> &bull; <span class="fw-normal opacity-75 text-muted"><?= date('M d, h:i A', strtotime($r['created_at'])) ?></span>
                                         </div>
                                         <p class="mb-0 small"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
                                     </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="card-footer bg-white p-3 border-top">
                            <?php if ($is_blocked): ?>
                                <div class="text-center text-muted small p-2 bg-light rounded-3 text-danger fw-bold"><i class="bi bi-ban"></i> You cannot reply to tickets due to account suspension.</div>
                            <?php elseif ($active_ticket['status'] !== 'Closed'): ?>
                            <form action="support.php" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="reply_ticket" value="1">
                                <input type="hidden" name="ticket_id" value="<?= $active_ticket['id'] ?>">
                                <input type="text" name="reply_message" class="form-control rounded-pill bg-light border-0" placeholder="Type your reply here..." required autofocus autocomplete="off">
                                <button type="submit" class="btn btn-primary rounded-circle" style="width: 44px; height: 44px;"><i class="bi bi-send-fill text-white ms-1 mt-1"></i></button>
                            </form>
                            <?php else: ?>
                                <div class="text-center text-muted small p-2">This ticket has been closed. You can no longer send replies.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </main>
    </div>
</div>

<!-- New Ticket Modal -->
<div class="modal fade" id="newTicketModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Open Support Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="support.php" method="POST">
          <input type="hidden" name="create_ticket" value="1">
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-bold small">Subject</label>
                <input type="text" name="subject" class="form-control bg-light border-0" placeholder="Brief outline of issue" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold small">Description / Message</label>
                <textarea name="message" class="form-control bg-light border-0" rows="5" placeholder="Provide as much detail as possible..." required></textarea>
            </div>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4">Submit Ticket</button>
          </div>
      </form>
    </div>
  </div>
</div>

<style>
 .mx-w-85 { max-width: 85% !important; }
 .rounded-top-right-0 { border-top-right-radius: 0 !important; }
 .rounded-top-left-0 { border-top-left-radius: 0 !important; }
 @media (max-width: 991px) { .main-content { padding-bottom: 80px !important; } }
 </style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
