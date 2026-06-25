<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
$user_id = $_SESSION['user_id'];

// Fetch recent active timeline (union of interests, profile_views, messages)
$query = "
    (SELECT 'Interest' as type, i.created_at, u.full_name, u.profile_pic, 'sent you an interest' as action, i.status 
     FROM interests i JOIN users u ON i.sender_id = u.id WHERE i.receiver_id = ? AND i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    UNION ALL
    (SELECT 'Visit' as type, CAST(v.view_date AS DATETIME) as created_at, u.full_name, u.profile_pic, 'viewed your profile' as action, NULL as status 
     FROM profile_views v JOIN users u ON v.viewer_id = u.id WHERE v.viewed_id = ? AND v.view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    UNION ALL
    (SELECT 'Message' as type, m.created_at, u.full_name, u.profile_pic, 'sent you a message' as action, NULL as status 
     FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    ORDER BY created_at DESC LIMIT 30
";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id, $user_id, $user_id]);
$timeline = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Recent Activity</h2>
                <p class="text-muted small">A timeline of your recent interactions and profile traffic.</p>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="timeline-container">
                        <?php if(empty($timeline)): ?>
                            <div class="alert alert-light border shadow-sm p-4 text-center">
                                <i class="bi bi-clock-history display-4 text-muted opacity-25"></i>
                                <p class="mt-2 text-muted">No recent activity in the last 7 days.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush shadow-sm rounded-4 border-0">
                                <?php foreach($timeline as $item): ?>
                                    <div class="list-group-item p-3 border-0 bg-white mb-2 shadow-sm rounded-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="position-relative">
                                                <img src="/online-rishta-system/assets/images/uploads/<?= $item['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                                                <div class="position-absolute bottom-0 end-0 bg-white rounded-circle p-1" style="transform: translate(25%, 25%);">
                                                    <?php if($item['type'] == 'Interest'): ?>
                                                        <i class="bi bi-heart-fill text-danger fs-6"></i>
                                                    <?php elseif($item['type'] == 'Visit'): ?>
                                                        <i class="bi bi-eye-fill text-primary fs-6"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-chat-fill text-success fs-6"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="mb-0 fw-bold text-dark text-truncate"><?= htmlspecialchars($item['full_name']) ?></h6>
                                                    <small class="text-muted text-nowrap ms-2" style="font-size: 0.7rem;"><?= date('h:i A', strtotime($item['created_at'])) ?></small>
                                                </div>
                                                <p class="text-muted small mb-0 lh-base"><?= $item['action'] ?> <span class="badge bg-light text-dark ms-1 d-inline-block" style="font-size: 0.65rem;"><?= date('M d', strtotime($item['created_at'])) ?></span></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4 mt-4 mt-md-0">
                    <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4 h-100 d-flex flex-column justify-content-center text-center">
                        <i class="bi bi-lightning-charge-fill display-5 mb-3 opacity-50"></i>
                        <h5 class="fw-bold">Level Up Your Activity</h5>
                        <p class="small opacity-75">Active profiles are noticed 5x more often.</p>
                        <div class="mt-3">
                            <a href="search.php" class="btn btn-light btn-sm fw-bold rounded-pill px-4 py-2">Find Matches</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
