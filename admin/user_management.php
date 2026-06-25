
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
            set_flash(count($uids) . " user(s) suspended.", "warning");
        } elseif ($action === 'activate') {
            $pdo->prepare("UPDATE users SET status = 'Active' WHERE id IN ($placeholders)")->execute($uids);
            set_flash(count($uids) . " user(s) activated.", "success");
        } elseif ($action === 'delete') {
            $pdo->prepare("UPDATE users SET status = 'Deleted' WHERE id IN ($placeholders)")->execute($uids);
            set_flash(count($uids) . " user(s) deleted.", "danger");
        }
    }

    // CSV Export
    if ($action === 'export_csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Name', 'Email', 'Gender', 'Status', 'Plan', 'Joined', 'City']);
        $all = $pdo->query("SELECT u.id, u.full_name, u.email, u.gender, u.status, s.plan_name, u.created_at, p.city FROM users u LEFT JOIN subscriptions s ON u.plan_id=s.plan_id LEFT JOIN user_profiles p ON u.id=p.user_id WHERE u.role='User'")->fetchAll();
        foreach($all as $r) fputcsv($out, [$r['id'], $r['full_name'], $r['email'], $r['gender'], $r['status'], $r['plan_name'], $r['created_at'], $r['city']]);
        fclose($out); exit();
    }

    header("Location: user_management.php"); exit();
}

// Handle AJAX search
$is_ajax = isset($_GET['ajax']);

// Filters
$q = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';
$plan_filter = $_GET['plan'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';   // new, old, last7, last30
$sort = $_GET['sort'] ?? 'newest';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$where = "u.role = 'User'";
$params = [];

if ($q) {
    $where .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($status_filter) { $where .= " AND u.status = ?"; $params[] = $status_filter; }
if ($gender_filter) { $where .= " AND u.gender = ?"; $params[] = $gender_filter; }
if ($plan_filter === 'free') { $where .= " AND u.plan_id = 1"; }
if ($plan_filter === 'premium') { $where .= " AND u.plan_id > 1"; }
if ($date_filter === 'new7') { $where .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; }
if ($date_filter === 'new30') { $where .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($date_filter === 'old') { $where .= " AND u.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($date_filter === 'active_week') { $where .= " AND u.last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; }
if ($date_from) { $where .= " AND DATE(u.created_at) >= ?"; $params[] = $date_from; }
if ($date_to) { $where .= " AND DATE(u.created_at) <= ?"; $params[] = $date_to; }

$order = match($sort) {
    'oldest' => 'u.created_at ASC',
    'name' => 'u.full_name ASC',
    'active' => 'u.last_seen DESC',
    default => 'u.created_at DESC'
};

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_count / $limit);

$stmt = $pdo->prepare("SELECT u.*, s.plan_name, p.is_verified, p.city, p.profession,
        (SELECT COUNT(*) FROM users u2 WHERE u2.last_ip = u.last_ip AND u2.id != u.id AND u.last_ip IS NOT NULL) as ip_matches
        FROM users u 
        LEFT JOIN subscriptions s ON u.plan_id = s.plan_id 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

if ($is_ajax) {
    // Return just the user cards HTML
    ob_start();
    include __DIR__ . '/partials/user_cards_partial.php';
    echo ob_get_clean();
    exit();
}

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='User'")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='User' AND status='Active'")->fetchColumn(),
    'premium' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='User' AND plan_id > 1")->fetchColumn(),
    'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='User' AND DATE(created_at)=CURDATE()")->fetchColumn(),
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.user-grid-card { border-radius: 16px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.06); transition: all 0.25s; }
.user-grid-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
.user-avatar-lg { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 3px solid #e5e7eb; }
.filter-card { background: #fff; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 20px; }
.stat-sm { background: #fff; border-radius: 12px; padding: 16px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.view-toggle .btn { border-radius: 8px; padding: 6px 14px; }
.view-toggle .btn.active { background: #6366f1; color: #fff; border-color: #6366f1; }
</style>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1">User Directory</h1>
            <p class="text-muted small mb-0">Manage member accounts with advanced filtering.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <!-- View Toggle -->
            <div class="view-toggle btn-group">
                <button id="listViewBtn" class="btn btn-outline-secondary active" title="List View"><i class="bi bi-list-ul"></i></button>
                <button id="gridViewBtn" class="btn btn-outline-secondary" title="Grid View"><i class="bi bi-grid-3x3-gap"></i></button>
            </div>
            <form method="POST">
                <button name="action" value="export_csv" class="btn btn-outline-success fw-bold"><i class="bi bi-download me-1"></i> Export CSV</button>
            </form>
            <a href="user_form.php" class="btn btn-primary fw-bold rounded-3"><i class="bi bi-person-plus-fill me-2"></i> Add User</a>
        </div>
    </div>

    <?php if(isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show rounded-4 border-0 shadow-sm">
        <?= $_SESSION['flash_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

    <!-- Stat Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-sm text-center"><div class="h3 fw-bold text-primary mb-0"><?= $stats['total'] ?></div><div class="small text-muted">Total Members</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-sm text-center"><div class="h3 fw-bold text-success mb-0"><?= $stats['active'] ?></div><div class="small text-muted">Active</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-sm text-center"><div class="h3 fw-bold text-warning mb-0"><?= $stats['premium'] ?></div><div class="small text-muted">Premium</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-sm text-center"><div class="h3 fw-bold text-info mb-0"><?= $stats['new_today'] ?></div><div class="small text-muted">Joined Today</div></div></div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card mb-4">
        <form id="filterForm" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" id="searchInput" class="form-control bg-light border-0" placeholder="Name, Email, Phone..." value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Status</label>
                <select name="status" class="form-select bg-light border-0">
                    <option value="">All</option>
                    <option value="Active" <?= $status_filter=='Active'?'selected':'' ?>>Active</option>
                    <option value="Suspended" <?= $status_filter=='Suspended'?'selected':'' ?>>Suspended</option>
                    <option value="Deleted" <?= $status_filter=='Deleted'?'selected':'' ?>>Deleted</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold text-muted">Gender</label>
                <select name="gender" class="form-select bg-light border-0">
                    <option value="">All</option>
                    <option value="Male" <?= $gender_filter=='Male'?'selected':'' ?>>Male</option>
                    <option value="Female" <?= $gender_filter=='Female'?'selected':'' ?>>Female</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold text-muted">Plan</label>
                <select name="plan" class="form-select bg-light border-0">
                    <option value="">Any</option>
                    <option value="free" <?= $plan_filter=='free'?'selected':'' ?>>Free</option>
                    <option value="premium" <?= $plan_filter=='premium'?'selected':'' ?>>Premium</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Profile Age</label>
                <select name="date_filter" class="form-select bg-light border-0">
                    <option value="">All Time</option>
                    <option value="new7" <?= $date_filter=='new7'?'selected':'' ?>>New (7 days)</option>
                    <option value="new30" <?= $date_filter=='new30'?'selected':'' ?>>New (30 days)</option>
                    <option value="old" <?= $date_filter=='old'?'selected':'' ?>>Old (30d+)</option>
                    <option value="active_week" <?= $date_filter=='active_week'?'selected':'' ?>>Active This Week</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold text-muted">Sort</label>
                <select name="sort" class="form-select bg-light border-0">
                    <option value="newest" <?= $sort=='newest'?'selected':'' ?>>Newest</option>
                    <option value="oldest" <?= $sort=='oldest'?'selected':'' ?>>Oldest</option>
                    <option value="name" <?= $sort=='name'?'selected':'' ?>>Name A-Z</option>
                    <option value="active" <?= $sort=='active'?'selected':'' ?>>Last Active</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
            </div>
            <div class="col-auto">
                <a href="user_management.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        <div class="row g-2 mt-2">
            <div class="col-md-3"><input type="date" id="dateFrom" name="date_from" class="form-control bg-light border-0 form-control-sm" placeholder="From Date" value="<?= $date_from ?>"></div>
            <div class="col-md-3"><input type="date" id="dateTo" name="date_to" class="form-control bg-light border-0 form-control-sm" placeholder="To Date" value="<?= $date_to ?>"></div>
        </div>
    </div>

    <!-- Bulk Actions + Result Count -->
    <form id="bulkActionForm" action="user_management.php" method="POST">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check m-0">
                    <input type="checkbox" id="selectAll" class="form-check-input fs-5">
                    <label class="form-check-label fw-bold text-muted small" for="selectAll">Select All</label>
                </div>
                <select name="action" class="form-select form-select-sm w-auto border-0 bg-light fw-bold">
                    <option value="">Bulk Action</option>
                    <option value="activate">Activate</option>
                    <option value="suspend">Suspend</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-dark fw-bold rounded-pill" onclick="return confirm('Apply bulk action?')">Apply</button>
            </div>
            <div class="text-muted small fw-bold">
                <span id="resultCount"><?= $total_count ?></span> members found
            </div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer">
        <?php include __DIR__ . '/partials/user_cards_partial.php'; ?>
        </div>
    </form>
</div>

<script>
// View Toggle
const listBtn = document.getElementById('listViewBtn');
const gridBtn = document.getElementById('gridViewBtn');

listBtn.addEventListener('click', () => {
    listBtn.classList.add('active');
    gridBtn.classList.remove('active');
    document.querySelectorAll('.user-col').forEach(c => {
        c.className = 'col-12 user-col';
        c.querySelector('.user-grid-card')?.classList.add('d-md-flex', 'align-items-center', 'px-4');
    });
    localStorage.setItem('userView', 'list');
});

gridBtn.addEventListener('click', () => {
    gridBtn.classList.add('active');
    listBtn.classList.remove('active');
    document.querySelectorAll('.user-col').forEach(c => {
        c.className = 'col-md-6 col-lg-4 col-xl-3 user-col';
        c.querySelector('.user-grid-card')?.classList.remove('d-md-flex', 'align-items-center', 'px-4');
    });
    localStorage.setItem('userView', 'grid');
});

// Restore view preference
if (localStorage.getItem('userView') === 'grid') gridBtn.click();

// Select All
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
});

// Real-time AJAX search
let searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

function applyFilters() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    
    document.getElementById('resultsContainer').innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>`;
    
    fetch('user_management.php?ajax=1&' + params)
        .then(r => r.text())
        .then(html => {
            document.getElementById('resultsContainer').innerHTML = html;
            if (localStorage.getItem('userView') === 'grid') gridBtn.click();
            // Re-bind checkboxes
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectAll);
            });
        });
}

function updateSelectAll() {
    const all = document.querySelectorAll('.user-checkbox');
    const checked = document.querySelectorAll('.user-checkbox:checked');
    document.getElementById('selectAll').checked = all.length > 0 && all.length === checked.length;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
