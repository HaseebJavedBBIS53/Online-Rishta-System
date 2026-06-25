<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_users');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user = null;
$error = '';
$success = '';

if ($user_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $gender = sanitize_input($_POST['gender']);
        $status = sanitize_input($_POST['status']);
        $plan_id = intval($_POST['plan_id']);
        $role = sanitize_input($_POST['role'] ?? 'User');
        $role_id = ($role === 'Admin') ? intval($_POST['role_id']) : NULL;
        
        $pdo->prepare("UPDATE users SET full_name = ?, email = ?, gender = ?, status = ?, plan_id = ?, role = ?, role_id = ? WHERE id = ?")
            ->execute([$full_name, $email, $gender, $status, $plan_id, $role, $role_id, $user_id]);
        $success = "User account updated successfully.";
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} else {
    // Handle Add New User
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $gender = sanitize_input($_POST['gender']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $plan_id = intval($_POST['plan_id'] ?? 1);
        $role = sanitize_input($_POST['role'] ?? 'User');
        $role_id = ($role === 'Admin') ? intval($_POST['role_id']) : NULL;
        
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            set_flash("Error: This email address is already registered.", "danger");
        } else {
            $pdo->prepare("INSERT INTO users (full_name, email, gender, dob, password, plan_id, role, role_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$full_name, $email, $gender, '1990-01-01', $password, $plan_id, $role, $role_id]);
            
            $new_user_id = $pdo->lastInsertId();
            
            if ($role === 'User') {
                $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$new_user_id]);
                $pdo->prepare("INSERT INTO partner_preferences (user_id) VALUES (?)")->execute([$new_user_id]);
            }

            set_flash("New " . ($role === 'Admin' ? 'Staff' : 'Member') . " added successfully!");
            header("Location: user_management.php");
            exit();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-7">
        <div class="d-flex align-items-center mb-4">
            <a href="user_management.php" class="btn btn-light rounded-pill px-3 me-3"><i class="bi bi-arrow-left me-1"></i> Back</a>
            <h2 class="fw-bold mb-0"><?= $user ? 'Edit Profile' : 'Add New Member' ?></h2>
        </div>

        <?php if($success): ?> <div class="alert alert-success border-0 shadow-sm"><?= $success ?></div> <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-5">
                <form action="user_form.php<?= $user ? '?id='.$user['id'] : '' ?>" method="POST">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Full Name</label>
                            <input type="text" name="full_name" class="form-control bg-light border-0 py-2" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold small">Email Address</label>
                            <input type="email" name="email" class="form-control bg-light border-0 py-2" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required <?= $user ? 'readonly' : '' ?>>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">Gender</label>
                            <select name="gender" class="form-select bg-light border-0 py-2">
                                <option value="Male" <?= ($user['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($user['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        
                        <?php if(!$user): ?>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small">Password</label>
                                <input type="password" name="password" class="form-control bg-light border-0 py-2" placeholder="Temporary Password" required>
                                <div class="form-text small">User will be able to change this after login.</div>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Subscription Plan</label>
                            <select name="plan_id" class="form-select bg-light border-0 py-2">
                                <?php
                                $plans = $pdo->query("SELECT * FROM subscriptions")->fetchAll();
                                foreach($plans as $p): ?>
                                    <option value="<?= $p['plan_id'] ?>" <?= ($user['plan_id'] ?? 1) == $p['plan_id'] ? 'selected' : '' ?>><?= $p['plan_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (has_permission('manage_rbac')): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">System Access Level</label>
                                <select name="role" id="roleSelect" class="form-select bg-light border-0 py-2">
                                    <option value="User" <?= (($user['role'] ?? $_GET['role'] ?? '') == 'User') ? 'selected' : '' ?>>Regular Member (Frontend Only)</option>
                                    <option value="Admin" <?= (($user['role'] ?? $_GET['role'] ?? '') == 'Admin') ? 'selected' : '' ?>>Staff / Admin (Full Access)</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="staffRoleContainer" style="<?= (($user['role'] ?? $_GET['role'] ?? '') == 'Admin') ? '' : 'display:none;' ?>">
                                <label class="form-label fw-bold small text-primary">Assign Staff Role</label>
                                <select name="role_id" class="form-select border-primary py-2 text-primary fw-bold">
                                    <?php
                                    $roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
                                    foreach($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= ($user['role_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= $r['role_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <script>
                            document.getElementById('roleSelect').addEventListener('change', function() {
                                const staffContainer = document.getElementById('staffRoleContainer');
                                if (this.value === 'Admin') {
                                    staffContainer.style.display = 'block';
                                } else {
                                    staffContainer.style.display = 'none';
                                }
                            });
                            </script>
                        <?php endif; ?>

                        <?php if($user): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Account Status</label>
                            <select name="status" class="form-select bg-light border-0 py-2">
                                <option value="Active" <?= $user['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Suspended" <?= $user['status'] == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="Deleted" <?= $user['status'] == 'Deleted' ? 'selected' : '' ?>>Deleted</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm">
                                <i class="bi bi-save-fill me-1"></i> Save Account Details
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/header.php'; ?>
