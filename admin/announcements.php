<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_content');

// Handle New Broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'broadcast') {
        $title = sanitize_input($_POST['title']);
        $message = sanitize_input($_POST['message']);
        $audience = sanitize_input($_POST['audience'] ?? 'All');
        $type = sanitize_input($_POST['type'] ?? 'info');
        $scheduled = !empty($_POST['scheduled_for']) ? $_POST['scheduled_for'] : null;

        if (!empty($title) && !empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, audience, type, scheduled_for, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $message, $audience, $type, $scheduled, $_SESSION['user_id']]);
            set_flash("Broadcasting mission initiated. History updated.");
        }
    } elseif ($_POST['action'] === 'delete') {
        $aid = intval($_POST['id']);
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$aid]);
        set_flash("Transmission record deleted.");
    }
    header("Location: announcements.php");
    exit();
}

$announcements = $pdo->query("SELECT a.*, u.full_name as author 
                             FROM announcements a 
                             JOIN users u ON a.created_by = u.id 
                             ORDER BY a.created_at DESC")->fetchAll();

$plans = $pdo->query("SELECT plan_id, plan_name FROM subscriptions")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4 pt-4">
    <div class="col">
        <h1 class="h2 fw-bold text-dark mb-0">Broadcast Center</h1>
        <p class="text-muted small">Deploy system-wide alerts, maintenance notices, and targeted announcements.</p>
    </div>
    <div class="col-auto d-flex gap-2">
        <button class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#broadcastModal">
            <i class="bi bi-megaphone-fill me-1"></i> New Announcement
        </button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold mb-0">Broadcast History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 border-0">Subject & Audience</th>
                                <th class="border-0">Broadcast Status</th>
                                <th class="border-0">Author / Date</th>
                                <th class="pe-4 text-end border-0">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($announcements)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No transmission records found.</td></tr>
                            <?php else: ?>
                                <?php foreach($announcements as $a): ?>
                                    <tr>
                                        <td class="ps-4 py-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="badge bg-<?= $a['type'] ?> p-2 rounded-circle shadow-sm" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="bi bi-broadcast fs-6"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($a['title']) ?></div>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1" style="font-size: 9px;"><?= strtoupper($a['audience']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                                $now = date('Y-m-d H:i:s');
                                                $status = 'Live Broadcast';
                                                $cls = 'success';
                                                if ($a['scheduled_for'] && $a['scheduled_for'] > $now) {
                                                    $status = 'Scheduled';
                                                    $cls = 'warning text-dark';
                                                }
                                            ?>
                                            <span class="badge bg-<?= $cls ?> rounded-pill px-3 py-1 ls-1" style="font-size: 9px;"><?= strtoupper($status) ?></span>
                                            <?php if($a['scheduled_for']): ?>
                                                <div class="text-muted small mt-1" style="font-size: 10px;">Set for: <?= date('M d, h:i A', strtotime($a['scheduled_for'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($a['author']) ?></div>
                                            <small class="text-muted"><?= date('M d, Y', strtotime($a['created_at'])) ?></small>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="btn-group shadow-sm">
                                                <button class="btn btn-sm btn-white border view-msg" data-msg="<?= htmlspecialchars($a['message']) ?>"><i class="bi bi-search"></i></button>
                                                <form action="announcements.php" method="POST" class="d-inline" onsubmit="return confirm('Erase record?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-white border text-danger"><i class="bi bi-trash"></i></button>
                                                </form>
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
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4 h-100" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;">
            <i class="bi bi-info-circle-fill display-5 mb-3 text-white opacity-75"></i>
            <h4 class="fw-bold">Operator Guidance</h4>
            <ul class="list-unstyled small opacity-75 mt-3">
                <li class="mb-3 d-flex gap-2">
                    <i class="bi bi-1-circle-fill text-white"></i>
                    <span>"All" reaches every active profile instantly. Target specific plans if needed.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <i class="bi bi-2-circle-fill text-white"></i>
                    <span>Scheduled messages remain hidden until the target datetime.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <i class="bi bi-3-circle-fill text-white"></i>
                    <span>Use "Danger" for maintenance or critical security alerts.</span>
                </li>
            </ul>
            <hr class="opacity-25 mt-auto">
            <p class="small text-white-50 mb-0">Total Live Broadcasts: <?= count($announcements) ?></p>
        </div>
    </div>
</div>

<!-- Broadcast Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="announcements.php" method="POST">
                <input type="hidden" name="action" value="broadcast">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase ls-1 text-secondary">Announcement Subject</label>
                        <input type="text" name="title" class="form-control bg-light border-0 py-2" placeholder="e.g. Summer Premium Promo" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase ls-1 text-secondary">Target Audience</label>
                            <select name="audience" class="form-select bg-light border-0 py-2">
                                <option value="All">Everyone</option>
                                <?php foreach($plans as $plan): ?>
                                    <option value="<?= $plan['plan_id'] ?>"><?= htmlspecialchars($plan['plan_name']) ?> Plan Only</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase ls-1 text-secondary">Priority Level</label>
                            <select name="type" class="form-select bg-light border-0 py-2">
                                <option value="info">Informational (Blue)</option>
                                <option value="success">Promotional (Green)</option>
                                <option value="warning">Warning (Orange)</option>
                                <option value="danger">Critical (Red)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase ls-1 text-secondary">Schedule (Optional)</label>
                        <input type="datetime-local" name="scheduled_for" class="form-control bg-light border-0 py-2">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase ls-1 text-secondary">Announcement Message</label>
                        <textarea name="message" class="form-control bg-light border-0" rows="5" placeholder="Details of the announcement..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Broadcast</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Transmission Decrypted</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="msg_body" class="p-3 bg-light rounded-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-msg').forEach(btn => {
    btn.onclick = function() {
        document.getElementById('msg_body').innerText = this.dataset.msg;
        new bootstrap.Modal(document.getElementById('viewModal')).show();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
