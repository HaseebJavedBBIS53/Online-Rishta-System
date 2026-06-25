<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_users');

// Handle Bulk & Single User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uids = isset($_POST['user_ids']) ? (array)$_POST['user_ids'] : (isset($_POST['user_id']) ? [$_POST['user_id']] : []);
    
    if (!empty($uids) && !empty($action)) {
        $placeholders = str_repeat('?,', count($uids) - 1) . '?';
        
        if ($action === 'suspend') {
            $pdo->prepare("UPDATE users SET status = 'Suspended' WHERE id IN ($placeholders)")->execute($uids);
            set_flash(count($uids) . " user(s) suspended successfully.");
        } elseif ($action === 'activate') {
            $pdo->prepare("UPDATE users SET status = 'Active' WHERE id IN ($placeholders)")->execute($uids);
            set_flash(count($uids) . " user(s) activated successfully.");
        } elseif ($action === 'delete') {
            $pdo->prepare("UPDATE users SET status = 'Deleted' WHERE id IN ($placeholders)")->execute($uids);
            set_flash(count($uids) . " user(s) marked as deleted.", "danger");
        }
    }
    header("Location: users.php");
    exit();
}

// Search and Filter
$search_query = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';
$plan_filter = $_GET['plan'] ?? '';

$sql = "SELECT u.*, s.plan_name, p.is_verified, p.city,
        (SELECT COUNT(*) FROM users u2 WHERE u2.last_ip = u.last_ip AND u2.id != u.id) as ip_matches
        FROM users u 
        LEFT JOIN subscriptions s ON u.plan_id = s.plan_id 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        WHERE u.role = 'User'";
$params = [];

if ($search_query) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.last_ip LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
if ($status_filter) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}
if ($plan_filter) {
    if ($plan_filter == 'premium') {
        $sql .= " AND u.plan_id > 1";
    } else {
        $sql .= " AND u.plan_id = 1";
    }
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
    <h1 class="h2 fw-bold">User Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Report</button>
            <button type="button" class="btn btn-sm btn-outline-success fw-bold"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</button>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-body p-4">
        <form action="users.php" method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold small text-muted">Search Members</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-0 bg-light" placeholder="Name, Email, Phone or IP" value="<?= htmlspecialchars($search_query) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">Account Status</label>
                <select name="status" class="form-select border-0 bg-light">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Pending Approval" <?= $status_filter == 'Pending Approval' ? 'selected' : '' ?>>Pending Approval</option>
                    <option value="Suspended" <?= $status_filter == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="Deleted" <?= $status_filter == 'Deleted' ? 'selected' : '' ?>>Deleted</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">Plan Type</label>
                <select name="plan" class="form-select border-0 bg-light">
                    <option value="">All Plans</option>
                    <option value="free" <?= $plan_filter == 'free' ? 'selected' : '' ?>>Free Users</option>
                    <option value="premium" <?= $plan_filter == 'premium' ? 'selected' : '' ?>>Premium Users</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
            </div>
        </form>
    </div>
</div>

<form id="bulkActionForm" action="users.php" method="POST">
    <div class="card shadow-sm border-0 bg-white">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label fw-bold small" for="selectAll">Select All</label>
                </div>
                <div class="vr"></div>
                <select name="action" class="form-select form-select-sm w-auto border-0 bg-light fw-bold" id="bulkActionSelect">
                    <option value="">Bulk Actions</option>
                    <option value="suspend">Suspend Selected</option>
                    <option value="activate">Activate Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="submit" class="btn btn-sm btn-dark fw-bold px-3" onclick="return confirm('Apply bulk action to selected users?')">Apply</button>
            </div>
            <span class="text-muted small fw-bold"><?= count($users) ?> total members found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 border-0" width="40"></th>
                            <th class="border-0">User Info</th>
                            <th class="border-0">Account</th>
                            <th class="border-0">Credentials</th>
                            <th class="border-0">Plan</th>
                            <th class="border-0">Activity / IP</th>
                            <th class="pe-3 text-end border-0">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No users found matching your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach($users as $u): ?>
                                <tr class="<?= $u['ip_matches'] > 0 ? 'table-warning' : '' ?>">
                                    <td class="ps-3">
                                        <div class="form-check">
                                            <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="position-relative">
                                                <img src="/online-rishta-system/assets/images/uploads/<?= $u['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle me-3" width="45" height="45" style="object-fit:cover;">
                                                <?php if($u['status'] === 'Active'): ?>
                                                    <span class="position-absolute bottom-0 start-0 translate-middle-y translate-middle-x p-1 bg-success border border-light rounded-circle" style="left: 45px;"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($u['full_name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mb-1">
                                            <?php if($u['is_verified']): ?>
                                                <span class="badge bg-soft-success text-success border border-success" style="background-color: #f0fff4; font-size: 10px;"><i class="bi bi-shield-check"></i> VERIFIED</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary" style="font-size: 10px;">UNVERIFIED</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php 
                                            $color = 'success';
                                            if($u['status'] == 'Pending Approval') $color = 'info';
                                            if($u['status'] == 'Suspended') $color = 'warning text-dark';
                                            if($u['status'] == 'Deleted') $color = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $color ?>" style="font-size: 10px;"><?= strtoupper($u['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($u['password']) ?>">
                                            <code class="small text-muted"><?= substr($u['password'], 0, 15) ?>...</code>
                                            <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="Passwords are encrypted/hashed for security."></i>
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 9px;">Phone: <?= $u['phone'] ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= strpos($u['plan_name'], 'Premium') !== false ? 'warning text-dark' : 'light text-dark border' ?>" style="font-size: 11px;">
                                            <?= $u['plan_name'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="d-block text-muted">Joined: <?= date('M d, Y', strtotime($u['created_at'])) ?></small>
                                        <small class="fw-bold <?= $u['ip_matches'] > 0 ? 'text-danger' : 'text-secondary' ?>">
                                            IP: <?= $u['last_ip'] ?: '0.0.0.0' ?>
                                            <?php if($u['ip_matches'] > 0): ?>
                                                <i class="bi bi-exclamation-triangle-fill" title="Fake Profile Alert: Same IP used by <?= $u['ip_matches'] ?> other account(s)"></i>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="#"><i class="bi bi-eye me-2 text-primary"></i> View Details</a></li>
                                                <?php if ($u['status'] === 'Active'): ?>
                                                    <li>
                                                        <form action="users.php" method="POST">
                                                            <input type="hidden" name="action" value="suspend">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-warning fw-bold"><i class="bi bi-pause-circle me-2"></i> Suspend</button>
                                                        </form>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <form action="users.php" method="POST">
                                                            <input type="hidden" name="action" value="activate">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-success fw-bold"><i class="bi bi-play-circle me-2"></i> Activate</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="users.php" method="POST" onsubmit="return confirm('Strictly delete this user?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger fw-bold"><i class="bi bi-trash me-2"></i> Delete Account</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
