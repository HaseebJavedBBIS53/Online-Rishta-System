<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_users');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    header("Location: user_management.php");
    exit();
}

// Fetch user data with profiles and activity stats
$stmt = $pdo->prepare("SELECT u.*, p.*, s.plan_name,
                      (SELECT COUNT(*) FROM interests WHERE sender_id = u.id) as interests_sent,
                      (SELECT COUNT(*) FROM interests WHERE receiver_id = u.id) as interests_received,
                      (SELECT COUNT(*) FROM messages WHERE sender_id = u.id OR receiver_id = u.id) as total_messages
                      FROM users u 
                      LEFT JOIN user_profiles p ON u.id = p.user_id 
                      LEFT JOIN subscriptions s ON u.plan_id = s.plan_id 
                      WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: user_management.php");
    exit();
}

// Handle Role Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $new_role_id = intval($_POST['role_id']);
    $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?")->execute([$new_role_id, $user_id]);
    set_flash("User role updated successfully.");
    header("Location: user_details.php?id=$user_id");
    exit();
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row pt-4 pb-5">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4 mb-4">
            <div class="position-relative d-inline-block mx-auto mb-3">
                <img src="/online-rishta-system/assets/images/uploads/<?= $user['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle border border-5 border-white shadow-sm" width="150" height="150" style="object-fit:cover;">
                <span class="position-absolute bottom-0 end-0 bg-<?= $user['status'] == 'Active' ? 'success' : 'danger' ?> border-4 border-white rounded-circle p-2 translate-middle-x" style="width: 25px; height: 25px;"></span>
            </div>
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
            <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>
            <div class="d-grid gap-2">
                <a href="user_form.php?id=<?= $user['id'] ?>" class="btn btn-primary rounded-pill fw-bold"><i class="bi bi-pencil-square me-1"></i> Quick Edit</a>
                <a href="messaging_system.php?user_id=<?= $user['id'] ?>" class="btn btn-outline-dark rounded-pill fw-bold"><i class="bi bi-chat-dots me-1"></i> Message User</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h6 class="fw-bold text-uppercase small text-muted mb-3 ls-1">Platform Activity</h6>
            <div class="row text-center g-2">
                <div class="col-6">
                    <div class="bg-light p-3 rounded-4">
                        <div class="fw-bold h5 mb-0"><?= $user['interests_sent'] ?></div>
                        <div class="small text-muted">Sent</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light p-3 rounded-4">
                        <div class="fw-bold h5 mb-0"><?= $user['interests_received'] ?></div>
                        <div class="small text-muted">Received</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="bg-light p-3 rounded-4">
                        <div class="fw-bold h5 mb-0"><?= $user['total_messages'] ?></div>
                        <div class="small text-muted">Direct Messages</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Profile Verification Info</h5>
                <?php if($user['is_verified']): ?>
                    <span class="badge bg-success-soft text-success px-3 py-2 rounded-pill"><i class="bi bi-shield-check"></i> Account Verified</span>
                <?php else: ?>
                    <span class="badge bg-warning-soft text-warning px-3 py-2 rounded-pill"><i class="bi bi-shield-exclamation"></i> Not Verified</span>
                <?php endif; ?>
            </div>
            
            <div class="row g-4">
                <?php if($user['verification_doc']): ?>
                <div class="col-12">
                    <div class="p-3 bg-light rounded-4 text-center">
                        <label class="d-block small text-muted fw-bold mb-2">Submitted Document</label>
                        <img src="/online-rishta-system/assets/images/uploads/<?= $user['verification_doc'] ?>" class="img-fluid rounded-4 shadow-sm" style="max-height: 400px;">
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <label class="small text-muted fw-bold mb-1">Education</label>
                    <p class="fw-bold"><?= htmlspecialchars($user['education'] ?: 'Not specified') ?></p>
                </div>
                <div class="col-md-6">
                    <label class="small text-muted fw-bold mb-1">Profession</label>
                    <p class="fw-bold"><?= htmlspecialchars($user['profession'] ?: 'Not specified') ?></p>
                </div>
                <div class="col-md-6">
                    <label class="small text-muted fw-bold mb-1">Location</label>
                    <p class="fw-bold"><?= htmlspecialchars($user['city'] ?: 'Not specified') ?></p>
                </div>
                <div class="col-md-6">
                    <label class="small text-muted fw-bold mb-1">Religion</label>
                    <p class="fw-bold"><?= htmlspecialchars($user['religion'] ?: 'Not specified') ?></p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 mt-4">
            <h5 class="fw-bold mb-4">Role & Access Control</h5>
            <form action="user_details.php?id=<?= $user_id ?>" method="POST" class="row align-items-end g-3">
                <input type="hidden" name="action" value="update_role">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-muted">Assign Staff Role</label>
                    <select name="role_id" class="form-select rounded-pill">
                        <option value="">Standard User (No Admin Access)</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $user['role_id'] == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-dark w-100 rounded-pill fw-bold">Update Role</button>
                </div>
                <div class="col-12">
                    <div class="alert alert-info py-2 mb-0 small rounded-3">
                        <i class="bi bi-info-circle-fill me-2"></i> Only users with assigned roles can access the Admin Panel modules based on permissions.
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
