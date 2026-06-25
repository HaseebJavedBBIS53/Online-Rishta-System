<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_reports');

// Fetch RBAC Audit Logs
$audit_logs = $pdo->query("SELECT a.*, u.full_name, u.role 
                          FROM audit_logs a 
                          LEFT JOIN users u ON a.user_id = u.id 
                          ORDER BY a.created_at DESC 
                          LIMIT 50")->fetchAll();

// Fetch duplicate IPs (System Alerts)
$duplicates = $pdo->query("SELECT last_ip, COUNT(*) as user_count 
                           FROM users 
                           WHERE last_ip IS NOT NULL 
                           GROUP BY last_ip 
                           HAVING COUNT(*) > 1 
                           ORDER BY user_count DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h3 fw-bold text-dark mb-0">Security Command Center</h1>
        <p class="text-muted small">Monitoring real-time access requests and suspicious network activity.</p>
    </div>
    <div class="col-auto">
        <button onclick="window.location.reload()" class="btn btn-white border fw-bold shadow-sm"><i class="bi bi-arrow-clockwise me-1"></i> Refresh Logs</button>
    </div>
</div>

<div class="row g-4">
    <!-- 1. Real-Time RBAC Audit Trail -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-shield-shaded me-2"></i> RBAC Access Audit Trail</h6>
                <span class="badge bg-light text-dark border rounded-pill small">Last 50 Events</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Timestamp</th>
                                <th>Staff Member</th>
                                <th>Action / Resource</th>
                                <th>Status</th>
                                <th class="pe-4">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($audit_logs)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No audit events recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($audit_logs as $log): ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?= date('H:i:s d/m', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($log['full_name'] ?? 'Guest/System') ?></div>
                                            <div class="text-muted" style="font-size: 10px;"><?= $log['role'] ?? 'N/A' ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($log['action']) ?></div>
                                            <div class="text-primary" style="font-size: 10px;"><?= htmlspecialchars($log['resource']) ?></div>
                                        </td>
                                        <td>
                                            <?php if($log['status'] === 'Allowed'): ?>
                                                <span class="badge bg-success-subtle text-success px-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Authorized</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger px-2 rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i> DENIED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 font-monospace text-muted"><?= $log['ip_address'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Flagged IP Activity (Existing) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-broadcast me-2"></i> Flagged Network IPs</h6>
            </div>
            <div class="card-body px-4">
                <?php if(empty($duplicates)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle display-4 text-success opacity-25"></i>
                        <p class="text-muted small mt-2">No duplicate IPs detected.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($duplicates as $d): ?>
                        <div class="bg-light bg-opacity-50 rounded-4 p-3 mb-3 border border-danger border-opacity-10">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-danger rounded-pill px-2 small"><?= $d['user_count'] ?> Flagged Accounts</span>
                                <code class="text-dark"><?= $d['last_ip'] ?></code>
                            </div>
                            <div class="list-group list-group-flush small bg-transparent">
                                <?php 
                                $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE last_ip = ?");
                                $stmt->execute([$d['last_ip']]);
                                $users = $stmt->fetchALL();
                                foreach($users as $u): ?>
                                    <div class="list-group-item px-0 border-0 bg-transparent py-1 d-flex justify-content-between">
                                        <span><?= htmlspecialchars($u['full_name']) ?></span>
                                        <a href="user_details.php?id=<?= $u['id'] ?>" class="text-primary text-decoration-none fw-bold" style="font-size: 10px;">Audit</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.bg-success-subtle { background-color: #d1e7dd !important; }
.bg-danger-subtle { background-color: #f8d7da !important; }
</style>

<?php require_once __DIR__ . '/includes/header.php'; ?>
