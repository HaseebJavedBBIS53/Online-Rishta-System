<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Acceptance or Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['interest_id'])) {
    $action = $_POST['action'];
    $interest_id = intval($_POST['interest_id']);
    
    // Validate that the logged-in user is the receiver of this interest
    $stmt = $pdo->prepare("SELECT receiver_id, status FROM interests WHERE id = ?");
    $stmt->execute([$interest_id]);
    $interest = $stmt->fetch();
    
    if ($interest && $interest['receiver_id'] === $user_id && $interest['status'] === 'Pending') {
        if ($action === 'accept') {
            // Check accepted limit
            $limit_stmt = $pdo->prepare("SELECT s.accepted_request_limit FROM users u LEFT JOIN subscriptions s ON u.plan_id = s.plan_id WHERE u.id = ?");
            $limit_stmt->execute([$user_id]);
            $limit = $limit_stmt->fetchColumn() ?: 0;
            
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM interests WHERE (sender_id = ? OR receiver_id = ?) AND status = 'Accepted'");
            $count_stmt->execute([$user_id, $user_id]);
            $current_accepted = $count_stmt->fetchColumn();
            
            if ($current_accepted >= $limit && $limit < 999999) {
                set_flash("You have reached your limit of $limit accepted requests. Please upgrade your plan to accept more.", "danger");
            } else {
                $pdo->prepare("UPDATE interests SET status = 'Accepted' WHERE id = ?")->execute([$interest_id]);
                set_flash("Interest accepted! You can now chat with this person.", "success");
            }
        } else if ($action === 'reject') {
            $pdo->prepare("UPDATE interests SET status = 'Rejected' WHERE id = ?")->execute([$interest_id]);
            set_flash("Interest declined.", "info");
        }
    }
    header("Location: interests.php");
    exit();
}

// Fetch Received Interests
$query_received = "SELECT i.id as interest_id, i.sender_id, i.status, i.created_at, u.full_name, u.dob, u.profile_pic, p.city, p.profession 
                   FROM interests i 
                   JOIN users u ON i.sender_id = u.id 
                   LEFT JOIN user_profiles p ON u.id = p.user_id 
                   WHERE i.receiver_id = ? ORDER BY i.created_at DESC";
$stmt_rec = $pdo->prepare($query_received);
$stmt_rec->execute([$user_id]);
$received = $stmt_rec->fetchAll();

// Fetch Sent Interests
$query_sent = "SELECT i.id as interest_id, i.receiver_id, i.status, i.created_at, u.full_name, u.dob, u.profile_pic, p.city, p.profession 
               FROM interests i 
               JOIN users u ON i.receiver_id = u.id 
               LEFT JOIN user_profiles p ON u.id = p.user_id 
               WHERE i.sender_id = ? ORDER BY i.created_at DESC";
$stmt_sent = $pdo->prepare($query_sent);
$stmt_sent->execute([$user_id]);
$sent = $stmt_sent->fetchAll();

// Check if current user is premium for photo blur
$stmt = $pdo->prepare("SELECT plan_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$is_premium = ($stmt->fetch()['plan_id'] ?? 1) > 1;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .interests-card { border: none; border-radius: 15px; transition: all 0.3s ease; background: white; border: 1px solid rgba(0,0,0,0.05); }
    .interests-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08) !important; }
    .status-pill { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 12px; border-radius: 100px; }
    .status-pending { background: #fff9e6; color: #d97706; border: 1px solid #fde68a; }
    .status-accepted { background: #f0fff4; color: #059669; border: 1px solid #a7f3d0; }
    .status-declined { background: #fff5f5; color: #dc2626; border: 1px solid #fecaca; }
    .tabs-premium .nav-link { color: #64748b; border: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
    .tabs-premium .nav-link.active { background: #6366f1; color: white; }
    .user-avatar-lg { width: 60px; height: 60px; border-radius: 12px; object-fit: cover; }
    .scrollbar-hidden::-webkit-scrollbar { display: none; }
    .scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">Interest Dashboard</h2>
                    <p class="text-muted small">Manage your connections and response to incoming requests.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
                <div class="card-header bg-white border-0 p-3 p-md-4 pb-0 overflow-auto scrollbar-hidden">
                    <ul class="nav nav-pills tabs-premium flex-nowrap" id="pills-tab" role="tablist">
                        <li class="nav-item me-2" role="presentation">
                            <button class="nav-link active" id="pills-received-tab" data-bs-toggle="pill" data-bs-target="#pills-received" type="button" role="tab">
                                <i class="bi bi-box-arrow-in-down me-1"></i> Received
                                <?php 
                                $pending_count = count(array_filter($received, fn($i) => $i['status'] === 'Pending'));
                                if ($pending_count > 0) echo "<span class='badge bg-danger ms-1 rounded-pill'>$pending_count</span>";
                                ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-sent-tab" data-bs-toggle="pill" data-bs-target="#pills-sent" type="button" role="tab">
                                <i class="bi bi-send me-1"></i> Sent
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="tab-content" id="pills-tabContent">
                        <!-- Received Intersts -->
                        <div class="tab-pane fade show active" id="pills-received" role="tabpanel">
                            <?php if (empty($received)): ?>
                                <div class="text-center py-5">
                                    <img src="/online-rishta-system/assets/images/empty_inbox.svg" style="width: 200px; opacity: 0.5;" alt="Empty">
                                    <h5 class="mt-4 text-muted">Your inbox is empty</h5>
                                    <p class="small text-muted">When someone expresses interest in you, it will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach($received as $row): 
                                        $age = (new DateTime($row['dob']))->diff(new DateTime('today'))->y;
                                        $id_enc = base64_encode($row['interest_id']);
                                    ?>
                                        <div class="col-xl-4 col-md-6">
                                            <div class="card interests-card shadow-sm">
                                                <div class="card-body p-4">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <img src="/online-rishta-system/assets/images/uploads/<?= $row['profile_pic'] ?: 'default.jpg' ?>" class="user-avatar-lg shadow-sm border me-3">
                                                        <div class="flex-grow-1">
                                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($row['full_name']) ?></h6>
                                                            <small class="text-muted"><?= $age ?> yrs • <?= htmlspecialchars($row['profession'] ?: 'Member') ?></small>
                                                        </div>
                                                        <span class="status-pill status-<?= strtolower($row['status']) ?>"><?= $row['status'] ?></span>
                                                    </div>

                                                    <div class="bg-light p-2 rounded-3 mb-3 small text-muted">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <i class="bi bi-geo-alt me-2 text-primary"></i><?= htmlspecialchars($row['city'] ?: 'Location Hidden') ?>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-clock me-2 text-primary"></i><?= date('d M, Y', strtotime($row['created_at'])) ?>
                                                        </div>
                                                    </div>

                                                    <?php if ($row['status'] === 'Pending'): ?>
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <form action="interests.php" method="POST">
                                                                    <input type="hidden" name="action" value="accept">
                                                                    <input type="hidden" name="interest_id" value="<?= $row['interest_id'] ?>">
                                                                    <button class="btn btn-primary w-100 fw-bold rounded-pill shadow-sm"><i class="bi bi-check2-circle me-1"></i> Accept</button>
                                                                </form>
                                                            </div>
                                                            <div class="col-6">
                                                                <form action="interests.php" method="POST">
                                                                    <input type="hidden" name="action" value="reject">
                                                                    <input type="hidden" name="interest_id" value="<?= $row['interest_id'] ?>">
                                                                    <button class="btn btn-outline-danger w-100 fw-bold rounded-pill"><i class="bi bi-x-circle me-1"></i> Decline</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    <?php elseif ($row['status'] === 'Accepted'): ?>
                                                        <a href="chat.php?user=<?= $row['sender_id'] ?>" class="btn btn-indigo-soft w-100 fw-bold rounded-pill py-2">
                                                            <i class="bi bi-chat-dots-fill me-2"></i> Start Conversation
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-light w-100 rounded-pill disabled opacity-50 fw-bold">Request Declined</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sent Interests -->
                        <div class="tab-pane fade" id="pills-sent" role="tabpanel">
                            <?php if (empty($sent)): ?>
                                <div class="text-center py-5">
                                    <h5 class="text-muted">You haven't sent any requests yet.</h5>
                                    <a href="matches.php" class="btn btn-primary rounded-pill px-4 mt-3 fw-bold">Discover Matches</a>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach($sent as $row): 
                                        $age = (new DateTime($row['dob']))->diff(new DateTime('today'))->y;
                                    ?>
                                        <div class="col-xl-4 col-md-6">
                                            <div class="card interests-card shadow-sm h-100">
                                                <div class="card-body p-4">
                                                    <div class="d-flex align-items-center mb-4">
                                                        <img src="/online-rishta-system/assets/images/uploads/<?= $row['profile_pic'] ?: 'default.jpg' ?>" class="user-avatar-lg shadow-sm border me-3">
                                                        <div class="flex-grow-1">
                                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars(explode(' ', $row['full_name'])[0]) ?></h6>
                                                            <small class="text-muted"><?= $age ?> yrs • <?= htmlspecialchars($row['profession'] ?: 'Member') ?></small>
                                                        </div>
                                                        <span class="status-pill status-<?= strtolower($row['status']) ?>"><?= $row['status'] ?></span>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">Sent on <?= date('d M, Y', strtotime($row['created_at'])) ?></small>
                                                        <?php if ($row['status'] === 'Accepted'): ?>
                                                            <a href="chat.php?user=<?= $row['receiver_id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">Chat Now</a>
                                                        <?php else: ?>
                                                            <a href="view_profile.php?id=<?= $row['receiver_id'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold">View Profile</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .btn-indigo-soft { background-color: #f5f3ff; color: #6366f1; border: 1px solid #ddd6fe; transition: all 0.2s; }
    .btn-indigo-soft:hover { background-color: #6366f1; color: white; transform: scale(1.02); }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
