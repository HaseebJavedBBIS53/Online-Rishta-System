<?php
// admin/rbac_management.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_rbac');

// Handle Role creation/updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_role') {
        $name = sanitize_input($_POST['role_name']);
        $desc = sanitize_input($_POST['description']);
        $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)")->execute([$name, $desc]);
        set_flash("Role created!");
    } elseif ($_POST['action'] === 'update_perms') {
        $role_id = intval($_POST['role_id']);
        $perms = $_POST['perms'] ?? [];
        
        // Clear existing perms
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
        
        // Add new perms
        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, perm_id) VALUES (?, ?)");
        foreach($perms as $p_id) {
            $stmt->execute([$role_id, $p_id]);
        }
        set_flash("Permissions updated for role.");
    }
}

// Fetch all roles
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
// Fetch all permissions
$all_perms = $pdo->query("SELECT * FROM permissions")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Role-Based Access Control</h2>
            <p class="text-muted">Manage system staff roles and granular permissions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="user_form.php?role=Admin" class="btn btn-outline-dark rounded-pill px-4 fw-bold">
                <i class="bi bi-person-plus-fill me-2"></i> Add New Staff
            </a>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="bi bi-plus-lg me-2"></i> Create New Role
            </button>
        </div>
    </div>

    <?php display_flash(); ?>

    <div class="row g-4">
        <?php foreach($roles as $role): ?>
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($role['role_name']) ?></h5>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($role['description']) ?></p>
                            </div>
                            <?php if($role['id'] == 1): ?>
                                <span class="badge bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 rounded-pill px-2">Core System</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body px-4">
                        <hr class="my-3 opacity-50">
                        <h6 class="small fw-bold text-muted text-uppercase mb-3">Assigned Permissions</h6>
                        
                        <?php
                        // Fetch current perms for this role
                        $rp_stmt = $pdo->prepare("SELECT perm_id FROM role_permissions WHERE role_id = ?");
                        $rp_stmt->execute([$role['id']]);
                        $current_perms = $rp_stmt->fetchAll(PDO::FETCH_COLUMN);
                        ?>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($role['id'] == 1): ?>
                                <span class="badge bg-dark rounded-pill">ALL_ACCESS</span>
                            <?php else: ?>
                                <?php 
                                $count = 0;
                                foreach($all_perms as $p): 
                                    if(in_array($p['id'], $current_perms)):
                                        echo "<span class='badge bg-soft-primary text-primary border border-primary border-opacity-10 rounded-pill'>".htmlspecialchars($p['perm_name'])."</span>";
                                        $count++;
                                    endif;
                                endforeach; 
                                if($count == 0) echo "<span class='text-muted small italic'>No permissions assigned</span>";
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 p-4 pt-0">
                        <?php if($role['id'] != 1): ?>
                            <button class="btn btn-light w-100 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#editPermsModal<?= $role['id'] ?>">
                                Manage Permissions
                            </button>

                            <!-- Edit Perms Modal -->
                            <div class="modal fade" id="editPermsModal<?= $role['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow rounded-4">
                                        <div class="modal-header border-0 pb-0 shadow-sm mb-3">
                                            <h5 class="modal-title fw-bold">Edit Permissions: <?= $role['role_name'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="rbac_management.php" method="POST">
                                            <input type="hidden" name="action" value="update_perms">
                                            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                            <div class="modal-body p-4">
                                                <div class="row g-2">
                                                    <?php foreach($all_perms as $p): ?>
                                                        <div class="col-12">
                                                            <div class="form-check form-switch p-2 border rounded-3 ps-5">
                                                                <input class="form-check-input ms-n5" type="checkbox" name="perms[]" value="<?= $p['id'] ?>" id="perm<?= $role['id'] ?>_<?= $p['id'] ?>" <?= in_array($p['id'], $current_perms) ? 'checked' : '' ?>>
                                                                <label class="form-check-label ms-3" for="perm<?= $role['id'] ?>_<?= $p['id'] ?>">
                                                                    <div class="fw-bold small"><?= htmlspecialchars($p['perm_name'] ?? $p['perm_key']) ?></div>
                                                                    <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">New Staff Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="rbac_management.php" method="POST">
                <input type="hidden" name="action" value="add_role">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Role Name</label>
                        <input type="text" name="role_name" class="form-control" required placeholder="e.g. Sales Agent">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="What does this role do?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .bg-soft-primary { background-color: #ebf5ff; }
    .text-purple { color: #8b5cf6; }
    .border-purple { border-color: #8b5cf6 !important; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
