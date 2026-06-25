<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$notifications = [];

// 1. Fetch recent profile views (last 7 days, limit 5)
$stmt = $pdo->prepare("SELECT v.view_date, u.id as user_id, u.full_name, u.profile_pic 
                       FROM profile_views v JOIN users u ON v.viewer_id = u.id 
                       WHERE v.viewed_id = ? AND v.view_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                       ORDER BY v.view_date DESC, v.id DESC LIMIT 5");
$stmt->execute([$user_id]);
$views = $stmt->fetchAll();
foreach($views as $v) {
    array_push($notifications, [
        'type' => 'view',
        'title' => 'Profile Viewer',
        'message' => '<b>' . explode(' ', $v['full_name'])[0] . '</b> visited your profile on ' . $v['view_date'] . '.',
        'color' => 'primary',
        'icon' => 'eye',
        'time' => $v['view_date'] . ' 00:00:00'
    ]);
}

// 2. Fetch new pending requests
$stmt = $pdo->prepare("SELECT i.created_at, u.full_name 
                       FROM interests i JOIN users u ON i.sender_id = u.id 
                       WHERE i.receiver_id = ? AND i.status = 'Pending' LIMIT 5");
$stmt->execute([$user_id]);
$reqs = $stmt->fetchAll();
foreach($reqs as $r) {
    array_push($notifications, [
        'type' => 'interest',
        'title' => 'New Interest Request',
        'message' => '<b>' . htmlspecialchars($r['full_name']) . '</b> has sent you an interest request. Review it now.',
        'color' => 'warning',
        'icon' => 'star',
        'time' => $r['created_at']
    ]);
}

// 3. Fetch accepted requests (You sent them, they accepted)
$stmt = $pdo->prepare("SELECT i.created_at, u.full_name, u.id 
                       FROM interests i JOIN users u ON i.receiver_id = u.id 
                       WHERE i.sender_id = ? AND i.status = 'Accepted' LIMIT 5");
$stmt->execute([$user_id]);
$accs = $stmt->fetchAll();
foreach($accs as $a) {
    array_push($notifications, [
        'type' => 'accepted',
        'title' => 'Interest Accepted!',
        'message' => '<b>' . explode(' ', $a['full_name'])[0] . '</b> accepted your interest. You can now <a href="chat.php?user='.$a['id'].'">chat with them</a>.',
        'color' => 'success',
        'icon' => 'check-circle',
        'time' => $a['created_at']
    ]);
}

// Sort notifications by time DESC
usort($notifications, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light py-3">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Notifications & Alerts</h2>
                <p class="text-muted small">Stay updated with your latest profile activity and requests.</p>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-bell-slash display-4"></i>
                            <h5 class="mt-3">You're all caught up!</h5>
                            <p>No new notifications right now.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush shadow-sm rounded-4 overflow-hidden border">
                            <?php foreach($notifications as $n): ?>
                                <div class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3 px-3">
                                    <div class="rounded-circle bg-<?= $n['color'] ?> bg-opacity-10 text-<?= $n['color'] ?> d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                        <i class="bi bi-<?= $n['icon'] ?> fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 fw-bold text-dark"><?= $n['title'] ?></h6>
                                            <small class="text-muted opacity-75 ms-2 text-nowrap" style="font-size: 0.7rem;"><?= date('M j', strtotime($n['time'])) ?></small>
                                        </div>
                                        <p class="mb-0 text-muted small lh-base"><?= $n['message'] ?></p>
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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
