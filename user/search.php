<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
check_feature_access('search'); // Enforce verification for search
$results = [];

// Get current user info to set defaults
$stmt = $pdo->prepare("SELECT gender, plan_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();
$is_premium = ($currentUser['plan_id'] ?? 1) > 1;

// Default search parameters
$gender_search = $_GET['gender'] ?? ($currentUser['gender'] === 'Male' ? 'Female' : 'Male');
$min_age = isset($_GET['min_age']) ? intval($_GET['min_age']) : 18;
$max_age = isset($_GET['max_age']) ? intval($_GET['max_age']) : 50;
$city = sanitize_input($_GET['city'] ?? '');
$profession = sanitize_input($_GET['profession'] ?? '');
$sect = sanitize_input($_GET['sect'] ?? '');
$marital_status = sanitize_input($_GET['marital_status'] ?? '');
$min_income = sanitize_input($_GET['min_income'] ?? '');
$name_search = sanitize_input($_GET['name'] ?? '');

// Base query for global search
$query = "SELECT u.id, u.full_name, u.dob, u.profile_pic, u.photo_visibility, u.is_highlighted, 
          p.city, p.education_level, p.profession, p.marital_status, p.sect, p.monthly_income, p.height
          FROM users u 
          LEFT JOIN user_profiles p ON u.id = p.user_id 
          WHERE u.id != :user_id AND u.status = 'Active' AND u.role = 'User' ";

$params = [':user_id' => $user_id];

if (!empty($gender_search)) {
    $query .= " AND u.gender = :gender ";
    $params[':gender'] = $gender_search;
}

$query .= " AND TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) BETWEEN :min_age AND :max_age ";
$params[':min_age'] = $min_age;
$params[':max_age'] = $max_age;

if (!empty($city)) {
    $query .= " AND p.city LIKE :city ";
    $params[':city'] = '%' . $city . '%';
}
if (!empty($profession)) {
    $query .= " AND p.profession LIKE :profession ";
    $params[':profession'] = '%' . $profession . '%';
}
if (!empty($sect)) {
    $query .= " AND p.sect LIKE :sect ";
    $params[':sect'] = '%' . $sect . '%';
}
if (!empty($marital_status)) {
    $query .= " AND p.marital_status = :marital_status ";
    $params[':marital_status'] = $marital_status;
}
if (!empty($name_search)) {
    $query .= " AND u.full_name LIKE :name_search ";
    $params[':name_search'] = '%' . $name_search . '%';
}

// Pagination Config
$items_per_page = 8;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Query for Total Count
$count_query = "SELECT COUNT(*) FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id != :user_id AND u.status = 'Active' AND u.role = 'User' ";
if (!empty($gender_search)) $count_query .= " AND u.gender = :gender ";
$count_query .= " AND TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) BETWEEN :min_age AND :max_age ";
if (!empty($city)) $count_query .= " AND p.city LIKE :city ";
if (!empty($profession)) $count_query .= " AND p.profession LIKE :profession ";
if (!empty($sect)) $count_query .= " AND p.sect LIKE :sect ";
if (!empty($marital_status)) $count_query .= " AND p.marital_status = :marital_status ";
if (!empty($name_search)) $count_query .= " AND u.full_name LIKE :name_search ";

// Sorting Logic
$order_by = "u.is_highlighted DESC";
$sort = $_GET['sort'] ?? '';
if ($sort === 'newest') {
    $order_by .= ", u.created_at DESC";
} elseif ($sort === 'oldest') {
    $order_by .= ", u.created_at ASC";
} elseif ($sort === 'explore') {
    $order_by = "RAND()";
} else {
    $order_by .= ", u.created_at DESC";
}

$query .= " ORDER BY $order_by LIMIT :offset, :items_per_page";

try {
    // Get Total Count
    $stmt_count = $pdo->prepare($count_query);
    foreach($params as $key => $val) {
        $stmt_count->bindValue($key, $val);
    }
    $stmt_count->execute();
    $total_items = $stmt_count->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Get Paginated Results
    $stmt = $pdo->prepare($query);
    foreach($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Search system temporarily unavailable.";
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .search-hero { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); border-radius: 20px; padding: 25px; color: white; margin-bottom: 20px; }
    @media (max-width: 768px) { .search-hero { padding: 20px; text-align: center; } }
    .filter-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .profile-card-premium { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: none; border-radius: 15px; overflow: hidden; }
    .profile-card-premium:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.12) !important; }
    .img-wrapper { height: 200px; overflow: hidden; position: relative; }
    @media (min-width: 992px) { .img-wrapper { height: 280px; } }
    .img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
    .quick-actions { position: absolute; bottom: 10px; right: 10px; display: flex; gap: 5px; z-index: 10; }
    @media (min-width: 992px) { 
        .quick-actions { transform: translateY(20px); opacity: 0; }
        .profile-card-premium:hover .quick-actions { transform: translateY(0); opacity: 1; }
    }
    .action-btn { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: white; color: #6366f1; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: all 0.2s; font-size: 0.8rem; }
    .action-btn:hover { background: #6366f1; color: white; transform: scale(1.1); }
    .highlight-card { border: 2px solid #fbbf24 !important; }
    .highlight-ribbon { position: absolute; z-index: 20; top: 10px; right: -30px; background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; padding: 2px 35px; transform: rotate(45deg); font-weight: bold; font-size: 0.6rem; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    /* Mobile Search Optimization */
    @media (max-width: 576px) {
        .img-wrapper { height: 220px !important; }
        .search-hero h1 { font-size: 1.4rem !important; }
        .search-hero p { font-size: 0.8rem !important; }
        .profile-card-premium .card-body { padding: 1rem !important; }
        .pagination .page-link {
            padding: 0.75rem 1rem !important;
            font-size: 0.9rem !important;
        }
        .filter-card .card-header {
            border-radius: 15px !important;
        }
    }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            
            <div class="search-hero shadow-lg">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="fw-bold mb-2">Find Your Eternal Match</h1>
                        <p class="opacity-75 mb-0">Use our advanced psychological matching and cultural filters to find the one who truly complements your soul.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end d-none d-lg-block">
                        <i class="bi bi-stars display-1 opacity-25"></i>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filter Card -->
            <div class="card filter-card mb-4 bg-white">
                <div class="card-header bg-primary bg-opacity-10 border-0 d-lg-none p-3" onclick="document.getElementById('filterBody').classList.toggle('d-none'); this.querySelector('i').classList.toggle('bi-chevron-down'); this.querySelector('i').classList.toggle('bi-chevron-up')">
                    <div class="d-flex justify-content-between align-items-center cursor-pointer">
                        <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-funnel-fill me-2"></i>Refine Search</h6>
                        <i class="bi bi-chevron-down text-primary"></i>
                    </div>
                </div>
                <div class="card-body p-4 d-none d-lg-block" id="filterBody">
                    <form action="search.php" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Gender</label>
                            <select name="gender" class="form-select border-0 bg-light rounded-3">
                                <option value="Male" <?= $gender_search === 'Male' ? 'selected' : '' ?>>Groom</option>
                                <option value="Female" <?= $gender_search === 'Female' ? 'selected' : '' ?>>Bride</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Age Range</label>
                            <div class="input-group">
                                <input type="number" name="min_age" class="form-control border-0 bg-light rounded-start-3" value="<?= $min_age ?>">
                                <span class="input-group-text border-0 bg-light">-</span>
                                <input type="number" name="max_age" class="form-control border-0 bg-light rounded-end-3" value="<?= $max_age ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">City</label>
                            <input type="text" name="city" class="form-control border-0 bg-light rounded-3" value="<?= htmlspecialchars($city) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Sect</label>
                            <input type="text" name="sect" class="form-control border-0 bg-light rounded-3" value="<?= htmlspecialchars($sect) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Search by Name</label>
                            <input type="text" name="name" class="form-control border-0 bg-light rounded-3" placeholder="Enter name..." value="<?= htmlspecialchars($name_search) ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-sm">
                                <i class="bi bi-search me-2"></i> Find Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0 text-slate-800"><?= count($results) ?> Matching Profiles</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="search.php?sort=explore" class="btn btn-white btn-sm shadow-sm border rounded-pill px-3 <?= $sort === 'explore' ? 'active bg-primary text-white' : '' ?>">Explore</a>
                    <a href="search.php?sort=newest" class="btn btn-white btn-sm shadow-sm border rounded-pill px-3 <?= $sort === 'newest' ? 'active bg-primary text-white' : '' ?>">Newest</a>
                    <div class="dropdown">
                        <button class="btn btn-white btn-sm shadow-sm border rounded-pill px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Sort By
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            <li><a class="dropdown-item small fw-bold" href="search.php?sort=newest">Newest First</a></li>
                            <li><a class="dropdown-item small fw-bold" href="search.php?sort=oldest">Oldest First</a></li>
                            <li><a class="dropdown-item small fw-bold" href="search.php?sort=nearby">Nearby (City)</a></li>
                        </ul>
                    </div>
                    <a href="search.php" class="btn btn-light btn-sm rounded-pill px-3 d-flex align-items-center text-muted border">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </div>

            <div class="row g-3 g-md-4">
                <?php if (empty($results)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="card border-0 shadow-sm rounded-4 p-5">
                            <i class="bi bi-search display-1 text-muted opacity-25"></i>
                            <h4 class="mt-4 fw-bold">No Match Found</h4>
                            <p class="text-muted small">Try broadening your filters.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($results as $row): 
                        $age = (new DateTime($row['dob']))->diff(new DateTime('today'))->y;
                        $photo_hidden = !$is_premium || ($row['photo_visibility'] === 'Premium');
                    ?>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <div class="card profile-card-premium <?= $row['is_highlighted'] ? 'highlight-card' : '' ?> shadow-sm h-100 bg-white">
                                <div class="img-wrapper">
                                    <img src="/online-rishta-system/assets/images/uploads/<?= $row['profile_pic'] ?: 'default.jpg' ?>" alt="<?= htmlspecialchars($row['full_name']) ?>">
                                    
                                    <?php if ($photo_hidden): ?>
                                    <div class="position-absolute top-50 start-50 translate-middle text-center w-100 px-3" style="z-index: 10;">
                                        <div class="bg-dark bg-opacity-75 text-white rounded-3 p-2 small fw-bold">
                                            <i class="bi bi-lock-fill text-warning mb-1 fs-5 d-block"></i> Premium Required
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($row['is_highlighted']): ?>
                                        <div class="highlight-ribbon"><i class="bi bi-star-fill"></i> FEATURED</div>
                                    <?php endif; ?>

                                    <!-- Badges -->
                                    <div class="position-absolute top-0 start-0 m-3 d-flex flex-column gap-2" style="z-index: 10;">
                                        <span class="badge bg-white text-dark rounded-pill shadow-sm px-3 py-2 small fw-bold">
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> <span class="fst-italic text-muted">Hidden</span>
                                        </span>
                                    </div>

                                    <div class="quick-actions" style="z-index: 10;">
                                        <button class="action-btn" title="Send Interest"><i class="bi bi-heart-fill"></i></button>
                                        <button class="action-btn" title="Shortlist"><i class="bi bi-bookmark-fill"></i></button>
                                    </div>
                                </div>
                                <div class="card-body p-3 p-md-4">
                                    <div class="mb-2">
                                        <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($row['full_name']) ?></h6>
                                        <small class="text-primary fw-bold text-truncate d-block small"><?= htmlspecialchars($row['profession'] ?: 'Profession') ?></small>
                                    </div>
                                    
                                    <div class="row g-1 mb-3 mt-1 d-none d-md-flex">
                                        <div class="col-6">
                                            <div class="bg-light p-1 rounded-2 text-center">
                                                <small class="text-muted d-block uppercase-xs">Height</small>
                                                <span class="fw-bold fs-7"><?= $row['height'] ?: 'N/A' ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-light p-1 rounded-2 text-center">
                                                <small class="text-muted d-block uppercase-xs">Sect</small>
                                                <span class="fw-bold fs-7"><?= $row['sect'] ?: 'N/A' ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid mt-auto">
                                        <a href="view_profile.php?id=<?= $row['id'] ?>" class="btn btn-indigo-soft btn-sm rounded-pill fw-bold py-2">
                                            View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination UI -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-5 mb-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-md shadow-sm rounded-4 overflow-hidden">
                        <?php 
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $base_url = "search.php?" . http_build_query($query_params) . "&page=";
                        ?>
                        
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link border-0 py-3 px-4" href="<?= $base_url . ($page - 1) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link border-0 py-3 px-4 fw-bold" href="<?= $base_url . $i ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link border-0 py-3 px-4">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link border-0 py-3 px-4" href="<?= $base_url . ($page + 1) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<style>
    .btn-indigo-soft { background-color: #f5f3ff; color: #6366f1; border: 1px solid #ddd6fe; }
    .btn-indigo-soft:hover { background-color: #6366f1; color: white; border-color: #6366f1; }
    .blur-20 img { filter: blur(15px); pointer-events: none; user-select: none; }
    .uppercase-xs { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
    .watermark::before { content: ""; position: absolute; z-index: 5; top: 0; left: 0; right: 0; bottom: 0; display: block; }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
