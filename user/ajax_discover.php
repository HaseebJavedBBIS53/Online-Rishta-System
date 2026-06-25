<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'Admin') {
    echo '<div class="col-12 text-center text-danger">Unauthorized</div>';
    exit();
}

$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'for_you';

// Fetch current user details
$stmt = $pdo->prepare("SELECT u.gender, u.plan_id, p.city, p.education_level FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();

$my_gender = $currentUser['gender'];
$my_city = $currentUser['city'];
$my_education = $currentUser['education_level'];
$is_premium = ($currentUser['plan_id'] ?? 1) > 1;

$skipped = $_SESSION['skipped_profiles'] ?? [];
$skipped_sql = !empty($skipped) ? "AND u.id NOT IN (" . implode(',', $skipped) . ")" : "";

$query = "SELECT u.id, u.full_name, u.dob, u.profile_pic, p.city, p.profession 
          FROM users u 
          LEFT JOIN user_profiles p ON u.id = p.user_id 
          WHERE u.id != ? AND u.gender != ? AND u.role = 'User' AND u.status = 'Active'
          $skipped_sql
          AND u.id NOT IN (SELECT receiver_id FROM interests WHERE sender_id = ?)";

$params = [$user_id, $my_gender, $user_id];

if ($tab === 'nearby' && !empty($my_city)) {
    $query .= " AND p.city = ?";
    $params[] = $my_city;
    $query .= " ORDER BY RAND() LIMIT 4";
} elseif ($tab === 'education' && !empty($my_education)) {
    $query .= " AND p.education_level = ?";
    $params[] = $my_education;
    $query .= " ORDER BY RAND() LIMIT 4";
} elseif ($tab === 'newest') {
    $query .= " ORDER BY u.created_at DESC LIMIT 4";
} else {
    // Default 'for_you'
    $query .= " ORDER BY RAND() LIMIT 4";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suggestions = $stmt->fetchAll();

if (empty($suggestions)) {
    echo '<div class="col-12 py-5 text-center text-muted bg-white rounded-5 shadow-sm">
            <div class="display-4 opacity-10 mb-3"><i class="bi bi-person-heart"></i></div>
            <h5 class="fw-bold">No results in this tab</h5>
            <p class="small">Try switching to "For You" or adjusting preferences.</p>
          </div>';
    exit();
}

foreach ($suggestions as $mate) {
    $age = (new DateTime($mate['dob']))->diff(new DateTime('today'))->y;
    $pic = $mate['profile_pic'] ?: 'default.jpg';
    $name = $mate['full_name'];
    $city = $mate['city'] ?: 'PK';
    $prof = $mate['profession'] ?: 'Not defined';
    $verified_icon = ($currentUser['plan_id'] > 1) ? '<i class="bi bi-patch-check-fill text-primary ms-1" title="Verified"></i>' : '';

    echo '
    <div class="col-6 col-md-6 col-lg-6">
        <div class="match-card-modern shadow-sm border-0 h-100">
            <div class="match-img-wrapper">
                <img src="/online-rishta-system/assets/images/uploads/' . $pic . '" alt="Profile">
                <div class="match-img-overlay d-flex flex-column justify-content-end p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-white bg-opacity-20 backdrop-blur text-white px-2 py-1 rounded-pill small">
                            <i class="bi bi-geo-alt me-1"></i>' . $city . '
                        </span>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <h6 class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                    ' . $name . '
                    ' . $verified_icon . '
                </h6>
                <div class="text-muted small text-truncate mb-3">
                    <i class="bi bi-briefcase me-1"></i>' . $prof . '
                </div>

                <div class="d-flex gap-2">
                    <a href="view_profile.php?id=' . $mate['id'] . '"
                        class="btn btn-primary flex-grow-1 rounded-pill btn-sm fw-bold shadow-sm">
                        View Profile
                    </a>
                    <form method="POST" action="dashboard.php" class="m-0">
                        <button type="submit" name="send_interest" value="' . $mate['id'] . '"
                            class="btn btn-outline-danger btn-sm rounded-circle action-btn-interest" title="Interested">
                            <i class="bi bi-heart-fill"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>';
}
?>