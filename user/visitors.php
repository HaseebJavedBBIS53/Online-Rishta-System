<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
$user_id = $_SESSION['user_id'];

// Fetch non-duplicate recent visitors (last 30 days)
$stmt = $pdo->prepare("SELECT v.viewer_id, v.view_date, u.full_name, u.profile_pic, p.city, p.profession 
                       FROM profile_views v 
                       JOIN users u ON v.viewer_id = u.id 
                       LEFT JOIN user_profiles p ON u.id = p.user_id 
                       WHERE v.viewed_id = ? AND v.view_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                       ORDER BY v.view_date DESC 
                       LIMIT 50");
$stmt->execute([$user_id]);
$visitors = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-4 pb-5">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 gap-2">
                <div>
                    <h2 class="fw-bold mb-0">Profile Visitors</h2>
                    <p class="text-muted small mb-0">People who viewed your profile in the last 30 days.</p>
                </div>
                <div>
                    <span class="badge bg-primary px-3 py-2 rounded-pill"><?= count($visitors) ?> Recent Visitors</span>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-body p-0">
                    <!-- Desktop Table View -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 border-0 py-3">Visitor</th>
                                    <th class="border-0 py-3">Location / Profession</th>
                                    <th class="border-0 py-3">Viewed On</th>
                                    <th class="pe-4 text-end border-0 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($visitors)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">No recent visitors found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($visitors as $v): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="/online-rishta-system/assets/images/uploads/<?= $v['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle shadow-sm border" width="45" height="45" style="object-fit: cover;">
                                                    <span class="fw-bold"><?= htmlspecialchars($v['full_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted d-block"><?= htmlspecialchars($v['city'] ?: 'Unknown City') ?></small>
                                                <small class="fw-bold text-primary"><?= htmlspecialchars($v['profession'] ?: 'Member') ?></small>
                                            </td>
                                            <td class="text-muted small">
                                                <?= date('M d, Y', strtotime($v['view_date'])) ?>
                                                <small class="d-block opacity-75"><?= date('h:i A', strtotime($v['view_date'])) ?></small>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <a href="view_profile.php?id=<?= $v['viewer_id'] ?>" class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3">View Profile</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="d-md-none">
                        <?php if(empty($visitors)): ?>
                            <div class="text-center py-5 text-muted small">No recent visitors found.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($visitors as $v): ?>
                                    <a href="view_profile.php?id=<?= $v['viewer_id'] ?>" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="/online-rishta-system/assets/images/uploads/<?= $v['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle shadow-sm border" width="50" height="50" style="object-fit: cover;">
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h6 class="fw-bold mb-0 text-dark text-truncate"><?= htmlspecialchars($v['full_name']) ?></h6>
                                                    <small class="text-muted" style="font-size: 0.7rem;"><?= date('M d', strtotime($v['view_date'])) ?></small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <small class="text-muted text-truncate"><?= htmlspecialchars($v['city'] ?: 'Nearby') ?> • <?= htmlspecialchars($v['profession'] ?: 'Member') ?></small>
                                                    <i class="bi bi-chevron-right text-muted small"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
