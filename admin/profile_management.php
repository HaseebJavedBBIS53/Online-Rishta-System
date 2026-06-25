<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('edit_profiles');

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['selected_users'])) {
    $action = $_POST['bulk_action'];
    $uids = $_POST['selected_users'];
    if (!empty($uids)) {
        $placeholders = implode(',', array_fill(0, count($uids), '?'));
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)")->execute($uids);
            set_flash("Selected profiles deleted permanently.");
        } elseif ($action === 'activate') {
            $pdo->prepare("UPDATE users SET status = 'Active' WHERE id IN ($placeholders)")->execute($uids);
            set_flash("Selected profiles activated.");
        } elseif ($action === 'deactivate') {
            $pdo->prepare("UPDATE users SET status = 'Suspended' WHERE id IN ($placeholders)")->execute($uids);
            set_flash("Selected profiles deactivated/suspended.");
        }
    }
    header("Location: profile_management.php");
    exit();
}

// Filters & Search
$q = $_GET['q'] ?? '';
$gender = $_GET['gender'] ?? '';
$city = $_GET['city'] ?? '';
$status = $_GET['status'] ?? '';
$plan = $_GET['plan'] ?? '';
$verified = $_GET['verified'] ?? '';

$sql = "SELECT u.*, p.city, p.is_verified, s.plan_name 
        FROM users u 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        LEFT JOIN subscriptions s ON u.plan_id = s.plan_id 
        WHERE u.role = 'User'";
$params = [];

if($q) { $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
if($gender) { $sql .= " AND u.gender = ?"; array_push($params, $gender); }
if($city) { $sql .= " AND p.city = ?"; array_push($params, $city); }
if($status) { $sql .= " AND u.status = ?"; array_push($params, $status); }
if($plan) { $sql .= " AND u.plan_id = ?"; array_push($params, $plan); }
if($verified !== '') { $sql .= " AND p.is_verified = ?"; array_push($params, $verified); }

$sql .= " ORDER BY u.created_at DESC";
$users = $pdo->prepare($sql);
$users->execute($params);
$user_list = $users->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h3 fw-bold text-dark mb-0">Profile Management</h1>
        <p class="text-muted small">Full administrative control over all member profiles and account states.</p>
    </div>
    <div class="col-auto">
        <a href="user_form.php" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm"><i class="bi bi-person-plus-fill me-2"></i> Add New Profile</a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Search</label>
                <input type="text" name="q" class="form-control border-0 bg-light" placeholder="Name, Email, or Phone..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Gender</label>
                <select name="gender" class="form-select border-0 bg-light">
                    <option value="">All</option>
                    <option value="Male" <?= $gender == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $gender == 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Status</label>
                <select name="status" class="form-select border-0 bg-light">
                    <option value="">All</option>
                    <option value="Active" <?= $status == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Suspended" <?= $status == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Verification</label>
                <select name="verified" class="form-select border-0 bg-light">
                    <option value="">All</option>
                    <option value="1" <?= $verified === '1' ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= $verified === '0' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-dark w-100 fw-bold rounded-pill">Apply Filters</button>
                <a href="profile_management.php" class="btn btn-link text-muted small ms-2">Clear</a>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="bulkForm">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <select name="bulk_action" class="form-select form-select-sm border-0 bg-light me-2 fw-bold" style="width: 150px;">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete Permanently</option>
                </select>
                <button type="submit" class="btn btn-sm btn-dark px-3 rounded-pill" onclick="return confirm('Apply bulk action?')">Apply</button>
            </div>
            <small class="text-muted fw-bold"><?= count($user_list) ?> Profiles Found</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th class="ps-4" style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <th>Profile</th>
                            <th>Location/Plan</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Verification</th>
                            <th class="pe-4 text-end">Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($user_list as $u): ?>
                            <tr>
                                <td class="ps-4"><input type="checkbox" name="selected_users[]" value="<?= $u['id'] ?>" class="user-check"></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="/online-rishta-system/assets/images/uploads/<?= $u['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle me-3 border" width="45" height="45" style="object-fit:cover;">
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <small class="text-muted d-block"><?= htmlspecialchars($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-bold"><?= htmlspecialchars($u['city'] ?: 'Not Set') ?></div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-2" style="font-size: 10px;"><?= $u['plan_name'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $u['status'] == 'Active' ? 'success' : 'danger' ?> rounded-pill px-3" style="font-size: 10px;"><?= strtoupper($u['status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if($u['is_verified']): ?>
                                        <i class="bi bi-patch-check-fill text-primary" title="Verified Member"></i>
                                    <?php else: ?>
                                        <i class="bi bi-clock-history text-muted" title="Pending Verification"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group">
                                        <a href="user_details.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-white border shadow-sm" title="Manage Account"><i class="bi bi-gear-fill"></i></a>
                                        <a href="user_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-white border shadow-sm" title="Edit Profile"><i class="bi bi-pencil"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.user-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once __DIR__ . '/includes/header.php'; ?>
