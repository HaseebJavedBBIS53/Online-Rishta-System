<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_highlights');

// Auto-expire check
$pdo->exec("UPDATE users SET is_highlighted=0 WHERE is_highlighted=1 AND highlight_expiry IS NOT NULL AND highlight_expiry < CURDATE()");

// Auto shift from queue
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_highlighted=1")->fetchColumn();
if ($activeCount < 20) {
    $slots = 20 - $activeCount;
    $queued = $pdo->query("SELECT * FROM highlight_queue WHERE status='Queued' ORDER BY created_at ASC LIMIT $slots")->fetchAll();
    foreach($queued as $q) {
        $pdo->prepare("UPDATE users SET is_highlighted=1, highlight_type=?, highlight_start=CURDATE(), highlight_expiry=DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id=?")
            ->execute([$q['highlight_type'], 30, $q['user_id']]);
        $pdo->prepare("UPDATE highlight_queue SET status='Active', start_date=CURDATE() WHERE id=?")->execute([$q['id']]);
    }
}

// Handle Actions
if (isset($_GET['action'])) {
    $uid = (int)($_GET['uid'] ?? 0);
    $action = $_GET['action'];
    
    if ($action === 'remove' && $uid) {
        $pdo->prepare("UPDATE users SET is_highlighted=0, highlight_type='Manual', highlight_start=NULL, highlight_expiry=NULL, highlight_priority=20 WHERE id=?")->execute([$uid]);
        $pdo->prepare("UPDATE highlight_queue SET status='Removed' WHERE user_id=? AND status='Active'")->execute([$uid]);
        set_flash("Profile removed from highlights.", "success");
    }
    
    if ($action === 'add' && $uid) {
        $active = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_highlighted=1")->fetchColumn();
        $days = (int)($_GET['days'] ?? 30);
        if ($active >= 20) {
            // Add to queue
            $exists = $pdo->prepare("SELECT id FROM highlight_queue WHERE user_id=? AND status='Queued'");
            $exists->execute([$uid]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare("INSERT INTO highlight_queue (user_id, highlight_type, status) VALUES (?, 'Manual', 'Queued')")->execute([$uid]);
            }
            set_flash("All 20 slots are full. User added to the waiting queue.", "warning");
        } else {
            $pdo->prepare("UPDATE users SET is_highlighted=1, highlight_type='Manual', highlight_start=CURDATE(), highlight_expiry=DATE_ADD(CURDATE(), INTERVAL ? DAY), highlight_priority=? WHERE id=?")
                ->execute([$days, $active + 1, $uid]);
            set_flash("Profile highlighted successfully for $days days.", "success");
        }
    }
    
    if ($action === 'priority' && $uid) {
        $newPriority = (int)($_GET['priority'] ?? 10);
        $pdo->prepare("UPDATE users SET highlight_priority=? WHERE id=?")->execute([$newPriority, $uid]);
        set_flash("Priority updated.", "success");
    }

    if ($action === 'remove_queue') {
        $qid = (int)($_GET['qid'] ?? 0);
        $pdo->prepare("UPDATE highlight_queue SET status='Removed' WHERE id=?")->execute([$qid]);
        set_flash("Removed from queue.", "success");
    }

    if ($action === 'save_package') {
        $pkg_id = (int)($_POST['pkg_id'] ?? 0);
        $pkg_name = sanitize_input($_POST['pkg_name'] ?? '');
        $pkg_days = (int)($_POST['pkg_days'] ?? 7);
        $pkg_price = (float)($_POST['pkg_price'] ?? 0);
        if ($pkg_id) {
            $pdo->prepare("UPDATE highlight_packages SET name=?, duration_days=?, price=? WHERE id=?")->execute([$pkg_name, $pkg_days, $pkg_price, $pkg_id]);
        } else {
            $pdo->prepare("INSERT INTO highlight_packages (name, duration_days, price) VALUES (?, ?, ?)")->execute([$pkg_name, $pkg_days, $pkg_price]);
        }
        set_flash("Package saved.", "success");
    }
    
    header("Location: highlighted_profiles.php");
    exit();
}

// Fetch Data
$highlighted = $pdo->query("SELECT u.id, u.full_name, u.profile_pic, u.gender, u.highlight_type, u.highlight_start, u.highlight_expiry, u.highlight_priority, u.email, p.city, TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) AS age, DATEDIFF(u.highlight_expiry, CURDATE()) AS days_left FROM users u LEFT JOIN user_profiles p ON u.id=p.user_id WHERE u.is_highlighted=1 AND u.role='User' ORDER BY u.highlight_priority ASC, u.highlight_start DESC")->fetchAll();

$queue = $pdo->query("SELECT q.*, u.full_name, u.profile_pic, p.city, TIMESTAMPDIFF(HOUR, q.created_at, NOW()) as wait_hours FROM highlight_queue q JOIN users u ON q.user_id=u.id LEFT JOIN user_profiles p ON u.id=p.user_id WHERE q.status='Queued' ORDER BY q.created_at ASC")->fetchAll();

// Search
$sq = $_GET['sq'] ?? '';
$sf_gender = $_GET['sf_gender'] ?? '';
$sf_plan = $_GET['sf_plan'] ?? '';
$sf_hl = $_GET['sf_hl'] ?? '';
$sf_age_min = (int)($_GET['sf_age_min'] ?? 0);
$sf_age_max = (int)($_GET['sf_age_max'] ?? 99);

$mq = "SELECT u.id, u.full_name, u.email, u.profile_pic, u.gender, u.is_highlighted, u.highlight_type, u.created_at, p.city, s.plan_name, TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as age FROM users u LEFT JOIN user_profiles p ON u.id=p.user_id LEFT JOIN subscriptions s ON u.plan_id=s.plan_id WHERE u.role='User' AND u.status='Active'";
if ($sq) $mq .= " AND (u.full_name LIKE '%".addslashes($sq)."%' OR u.email LIKE '%".addslashes($sq)."%')";
if ($sf_gender) $mq .= " AND u.gender='".addslashes($sf_gender)."'";
if ($sf_hl === 'yes') $mq .= " AND u.is_highlighted=1";
if ($sf_hl === 'no') $mq .= " AND u.is_highlighted=0";
if ($sf_age_min > 0) $mq .= " AND TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) >= $sf_age_min";
if ($sf_age_max < 99) $mq .= " AND TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) <= $sf_age_max";
$mq .= " ORDER BY u.is_highlighted DESC, u.created_at DESC LIMIT 100";
$members = $pdo->query($mq)->fetchAll();

$packages = $pdo->query("SELECT * FROM highlight_packages ORDER BY duration_days ASC")->fetchAll();

// Analytics
$analytics = [
    'active' => count($highlighted),
    'queue' => count($queue),
    'expired_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_highlighted=0 AND highlight_expiry=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetchColumn(),
    'added_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_highlighted=1 AND highlight_start=CURDATE()")->fetchColumn(),
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.hcard { background: #fff; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: none; }
.badge-paid { background: linear-gradient(135deg,#f59e0b,#fbbf24); color: #fff; }
.badge-manual { background: linear-gradient(135deg,#6366f1,#a855f7); color: #fff; }
.badge-expired { background: #fee2e2; color: #ef4444; }
.rank-badge { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg,#6366f1,#a855f7); color: #fff; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; }
.highlight-row { transition: all 0.2s; }
.highlight-row:hover { background: rgba(99,102,241,0.04); }
.stat-widget { border-radius: 12px; padding: 18px; text-align: center; }
.profile-thumb { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb; }
.drag-handle { cursor: grab; color: #9ca3af; padding: 4px 8px; }
.drag-handle:active { cursor: grabbing; }
</style>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-star-fill text-warning me-2"></i> Premium Highlights Management</h1>
        <p class="text-muted small mb-0">Manage the top 20 featured profiles shown in search results.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-warning text-dark px-4 py-2 rounded-pill fw-bold fs-6"><?= count($highlighted) ?>/20 Slots Used</span>
        <button class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#packagesModal"><i class="bi bi-boxes me-1"></i> Packages</button>
    </div>
</div>

<?php if(isset($_SESSION['flash_message'])): ?>
<div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show shadow-sm rounded-4">
    <?= $_SESSION['flash_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<div class="row g-4 mb-4">
    <!-- Analytics Sidebar -->
    <div class="col-lg-3">
        <div class="hcard p-4 h-100">
            <h6 class="fw-bold mb-3 text-muted text-uppercase" style="font-size:11px; letter-spacing:1px;">Analytics</h6>
            <div class="stat-widget bg-warning bg-opacity-10 mb-3">
                <div class="display-6 fw-bold text-warning"><?= $analytics['active'] ?>/20</div>
                <div class="small text-muted fw-bold">Active Highlights</div>
            </div>
            <div class="stat-widget bg-primary bg-opacity-10 mb-3">
                <div class="display-6 fw-bold text-primary"><?= $analytics['queue'] ?></div>
                <div class="small text-muted fw-bold">Users in Queue</div>
            </div>
            <div class="stat-widget bg-success bg-opacity-10 mb-3">
                <div class="display-6 fw-bold text-success"><?= $analytics['added_today'] ?></div>
                <div class="small text-muted fw-bold">Added Today</div>
            </div>
            <div class="stat-widget bg-danger bg-opacity-10">
                <div class="display-6 fw-bold text-danger"><?= $analytics['expired_today'] ?></div>
                <div class="small text-muted fw-bold">Expired Today</div>
            </div>
        </div>
    </div>

    <!-- Currently Highlighted -->
    <div class="col-lg-9">
        <div class="hcard">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Currently Highlighted Profiles</h6>
                <small class="text-muted">Drag rows to change priority</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="highlightedTable">
                    <thead class="table-light">
                        <tr>
                            <th width="40" class="px-3">#</th>
                            <th>Profile</th>
                            <th>Type</th>
                            <th>Days Left</th>
                            <th>Expiry</th>
                            <th>Priority</th>
                            <th class="text-end px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sortableBody">
                        <?php if(empty($highlighted)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No highlighted profiles yet.</td></tr>
                        <?php else: foreach($highlighted as $i => $h): ?>
                        <tr class="highlight-row" data-uid="<?= $h['id'] ?>">
                            <td class="px-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                                    <div class="rank-badge"><?= $i+1 ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="/online-rishta-system/assets/images/uploads/<?= $h['profile_pic'] ?: 'default.jpg' ?>" class="profile-thumb">
                                    <div>
                                        <div class="fw-bold small"><?= htmlspecialchars($h['full_name']) ?></div>
                                        <div class="text-muted" style="font-size:11px;"><?= $h['age'] ?> yrs · <?= htmlspecialchars($h['city'] ?? 'Unknown') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-<?= strtolower($h['highlight_type']) ?> rounded-pill px-3"><?= $h['highlight_type'] ?></span></td>
                            <td>
                                <?php if($h['days_left'] !== null): ?>
                                    <span class="fw-bold <?= $h['days_left'] <= 3 ? 'text-danger' : 'text-success' ?>"><?= max(0,$h['days_left']) ?> days</span>
                                <?php else: ?>
                                    <span class="text-muted">Lifetime</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= $h['highlight_expiry'] ? date('M d, Y', strtotime($h['highlight_expiry'])) : '—' ?></td>
                            <td>
                                <select class="form-select form-select-sm bg-light border-0 w-auto" style="min-width:70px;" onchange="changePriority(<?= $h['id'] ?>, this.value)">
                                    <?php for($p=1; $p<=20; $p++): ?>
                                    <option value="<?= $p ?>" <?= $h['highlight_priority']==$p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td class="text-end px-4">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="previewProfile(<?= htmlspecialchars(json_encode($h)) ?>)" title="Preview"><i class="bi bi-eye"></i></button>
                                    <a href="highlighted_profiles.php?action=remove&uid=<?= $h['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Remove from highlights?')" title="Remove"><i class="bi bi-x-lg"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Queue Section -->
<?php if(!empty($queue)): ?>
<div class="hcard mb-4">
    <div class="card-header bg-warning bg-opacity-10 border-bottom py-3 px-4">
        <h6 class="fw-bold mb-0 text-warning-emphasis"><i class="bi bi-hourglass-split me-2"></i> Highlight Queue (Waiting for Slot)</h6>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th class="px-4">Position</th><th>Profile</th><th>Payment Status</th><th>Queued At</th><th class="text-end px-4">Action</th></tr></thead>
            <tbody>
            <?php foreach($queue as $qi => $q): ?>
            <tr>
                <td class="px-4 fw-bold text-warning">Queue #<?= $qi+1 ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <img src="/online-rishta-system/assets/images/uploads/<?= $q['profile_pic'] ?: 'default.jpg' ?>" class="profile-thumb">
                        <div class="fw-bold small"><?= htmlspecialchars($q['full_name']) ?></div>
                    </div>
                </td>
                <td><span class="badge bg-<?= $q['highlight_type']=='Paid' ? 'success' : 'secondary' ?>"><?= $q['highlight_type'] ?></span></td>
                <td class="small text-muted"><?= date('M d, Y H:i', strtotime($q['created_at'])) ?></td>
                <td class="text-end px-4"><a href="highlighted_profiles.php?action=remove_queue&qid=<?= $q['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove from queue?')">Remove</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Active Members List -->
<div class="hcard">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="fw-bold mb-0">Active Members — Add to Highlights</h6>
    </div>
    <div class="card-body border-bottom py-3 px-4">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="1">
            <div class="col-md-3">
                <input type="text" name="sq" class="form-control bg-light border-0" placeholder="Search name or email..." value="<?= htmlspecialchars($sq) ?>">
            </div>
            <div class="col-md-2">
                <select name="sf_gender" class="form-select bg-light border-0">
                    <option value="">All Genders</option>
                    <option value="Male" <?= $sf_gender=='Male'?'selected':'' ?>>Male</option>
                    <option value="Female" <?= $sf_gender=='Female'?'selected':'' ?>>Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sf_hl" class="form-select bg-light border-0">
                    <option value="">All Statuses</option>
                    <option value="yes" <?= $sf_hl=='yes'?'selected':'' ?>>Highlighted</option>
                    <option value="no" <?= $sf_hl=='no'?'selected':'' ?>>Not Highlighted</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <input type="number" name="sf_age_min" class="form-control bg-light border-0" placeholder="Min Age" value="<?= $sf_age_min ?: '' ?>">
                <input type="number" name="sf_age_max" class="form-control bg-light border-0" placeholder="Max Age" value="<?= ($sf_age_max < 99) ? $sf_age_max : '' ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="highlighted_profiles.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="px-4">Profile</th>
                    <th>Location / Age</th>
                    <th>Membership</th>
                    <th>Highlight Status</th>
                    <th class="text-end px-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($members as $m): ?>
                <tr>
                    <td class="px-4">
                        <div class="d-flex align-items-center gap-2">
                            <img src="/online-rishta-system/assets/images/uploads/<?= $m['profile_pic'] ?: 'default.jpg' ?>" class="profile-thumb">
                            <div>
                                <div class="fw-bold small"><?= htmlspecialchars($m['full_name']) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($m['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($m['city'] ?? '—') ?> · <?= $m['age'] ?> yrs</td>
                    <td><span class="badge bg-secondary bg-opacity-15 text-dark border rounded-pill px-2" style="font-size:10px;"><?= $m['plan_name'] ?? 'Free' ?></span></td>
                    <td>
                        <?php if($m['is_highlighted']): ?>
                            <span class="badge badge-<?= strtolower($m['highlight_type'] ?? 'manual') ?> rounded-pill px-3"><?= $m['highlight_type'] ?? 'Manual' ?> ⭐</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border rounded-pill" style="font-size:10px;">Not Highlighted</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end px-4">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="previewProfile(<?= htmlspecialchars(json_encode(['id'=>$m['id'],'full_name'=>$m['full_name'],'age'=>$m['age'],'city'=>$m['city'],'profile_pic'=>$m['profile_pic'],'plan_name'=>$m['plan_name'],'highlight_type'=>$m['highlight_type']])) ?>" title="Preview"><i class="bi bi-eye"></i></button>
                            <?php if(!$m['is_highlighted']): ?>
                            <div class="btn-group btn-group-sm">
                                <a href="highlighted_profiles.php?action=add&uid=<?= $m['id'] ?>&days=7" class="btn btn-outline-warning" title="Basic 7 Days"><i class="bi bi-star"></i> 7d</a>
                                <a href="highlighted_profiles.php?action=add&uid=<?= $m['id'] ?>&days=30" class="btn btn-warning" title="Premium 30 Days"><i class="bi bi-star-fill"></i> 30d</a>
                            </div>
                            <?php else: ?>
                            <a href="highlighted_profiles.php?action=remove&uid=<?= $m['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Remove highlight?')"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; if(empty($members)): ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">No users found matching your filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Packages Modal -->
<div class="modal fade" id="packagesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-boxes text-primary me-2"></i> Highlight Packages</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                <?php foreach($packages as $pkg): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3">
                        <h6 class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></h6>
                        <div class="display-6 fw-bold text-primary mb-1">Rs <?= number_format($pkg['price']) ?></div>
                        <small class="text-muted"><?= $pkg['duration_days'] ?> Days Highlight</small>
                        <hr>
                        <button class="btn btn-sm btn-outline-primary fw-bold rounded-pill" onclick="editPackage(<?= htmlspecialchars(json_encode($pkg)) ?>)">Edit</button>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <form method="POST" action="highlighted_profiles.php?action=save_package" id="pkgForm">
                    <input type="hidden" name="pkg_id" id="pkgId" value="0">
                    <div class="row g-3">
                        <div class="col-md-4"><input type="text" name="pkg_name" id="pkgName" class="form-control" placeholder="Package Name" required></div>
                        <div class="col-md-4"><input type="number" name="pkg_days" id="pkgDays" class="form-control" placeholder="Duration Days" required></div>
                        <div class="col-md-4"><input type="number" name="pkg_price" id="pkgPrice" class="form-control" placeholder="Price (PKR)" required></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill">Save Package</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Profile Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Profile Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="previewImg" src="" class="rounded-circle mb-3" width="90" height="90" style="object-fit:cover; border: 3px solid #6366f1;">
                <h4 class="fw-bold mb-0" id="previewName"></h4>
                <div class="text-muted mb-2" id="previewMeta"></div>
                <div class="row g-2 text-center mt-3" id="previewStats"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// Drag-drop sorting
const sortableBody = document.getElementById('sortableBody');
if (sortableBody) {
    Sortable.create(sortableBody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function(evt) {
            const rows = sortableBody.querySelectorAll('tr[data-uid]');
            const order = [];
            rows.forEach((r, i) => {
                order.push({ uid: r.dataset.uid, priority: i + 1 });
                r.querySelector('.rank-badge') && (r.querySelector('.rank-badge').textContent = i + 1);
            });
            // Could send via AJAX for live update
        }
    });
}

function changePriority(uid, priority) {
    window.location.href = `highlighted_profiles.php?action=priority&uid=${uid}&priority=${priority}`;
}

function previewProfile(data) {
    document.getElementById('previewImg').src = '/online-rishta-system/assets/images/uploads/' + (data.profile_pic || 'default.jpg');
    document.getElementById('previewName').textContent = data.full_name;
    document.getElementById('previewMeta').textContent = (data.age || '?') + ' yrs · ' + (data.city || 'Unknown');
    document.getElementById('previewStats').innerHTML = `
        <div class="col-4"><div class="bg-light rounded-3 p-2"><small class="text-muted d-block">Type</small><strong>${data.highlight_type || 'N/A'}</strong></div></div>
        <div class="col-4"><div class="bg-light rounded-3 p-2"><small class="text-muted d-block">Plan</small><strong>${data.plan_name || 'Free'}</strong></div></div>
        <div class="col-4"><div class="bg-light rounded-3 p-2"><small class="text-muted d-block">Gender</small><strong>${data.gender || '?'}</strong></div></div>
    `;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

function editPackage(pkg) {
    document.getElementById('pkgId').value = pkg.id;
    document.getElementById('pkgName').value = pkg.name;
    document.getElementById('pkgDays').value = pkg.duration_days;
    document.getElementById('pkgPrice').value = pkg.price;
    document.getElementById('pkgForm').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
