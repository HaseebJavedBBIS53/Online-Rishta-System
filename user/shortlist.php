<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get shortlisted profiles
$query = "SELECT s.id as shortlist_id, u.id as profile_id, u.full_name, u.dob, u.profile_pic, u.photo_visibility, p.city, p.profession
          FROM shortlists s 
          JOIN users u ON s.profile_id = u.id 
          LEFT JOIN user_profiles p ON u.id = p.user_id 
          WHERE s.user_id = ? 
          ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$shortlisted = $stmt->fetchAll();

// Handle removal directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $remove_id = intval($_POST['remove_id']);
    $pdo->prepare("DELETE FROM shortlists WHERE id = ? AND user_id = ?")->execute([$remove_id, $user_id]);
    set_flash("Profile removed from your shortlist.");
    header("Location: shortlist.php");
    exit();
}

// Check plan for photo visibility
$stmt = $pdo->prepare("SELECT plan_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();
$is_premium = ($currentUser['plan_id'] ?? 1) > 1;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .shortlist-header { background: linear-gradient(135deg, #f43f5e 0%, #fb7185 100%); color: white; border-radius: 20px; padding: 25px; margin-bottom: 20px; position: relative; overflow: hidden; }
    @media (max-width: 768px) { .shortlist-header { padding: 20px; text-align: center; } }
    .shortlist-card { border: none; border-radius: 15px; transition: all 0.3s ease; background: white; border: 1px solid rgba(0,0,0,0.05); }
    .shortlist-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important; }
    .shortlist-img { height: 180px; object-fit: cover; border-top-left-radius: 15px; border-top-right-radius: 15px; }
    @media (min-width: 992px) { .shortlist-img { height: 260px; } }
    .remove-btn { position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,1); border: none; width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #f43f5e; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; font-size: 0.8rem; }
    .header-text { position: relative; z-index: 5; }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            
            <div class="shortlist-header shadow-lg text-center text-sm-start">
                <div class="header-text">
                    <h2 class="fw-bold mb-1">My Shortlist</h2>
                    <p class="opacity-75 mb-0 small">Saved profiles for future connection.</p>
                </div>
                <i class="bi bi-bookmark-heart-fill position-absolute top-50 end-0 translate-middle-y opacity-10 d-none d-lg-block" style="font-size: 12rem;"></i>
            </div>

            <?php if (empty($shortlisted)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="bg-rose-soft p-4 rounded-circle d-inline-block mb-4">
                        <i class="bi bi-bookmark-plus display-1 text-rose"></i>
                    </div>
                    <h3 class="fw-bold">Your vault is empty</h3>
                    <p class="text-muted mx-auto" style="max-width: 500px;">When you find profiles that interest you during your search, use the shortlist icon to save them here for later review.</p>
                    <div class="mt-2">
                        <a href="search.php" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow">Start Searching</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3 g-md-4">
                    <?php foreach($shortlisted as $row): 
                        $age = (new DateTime($row['dob']))->diff(new DateTime('today'))->y;
                        $photo_hidden = ($row['photo_visibility'] === 'Premium' && !$is_premium);
                    ?>
                        <div class="col-6 col-md-6 col-lg-4 col-xl-3">
                            <div class="card shortlist-card h-100 shadow-sm position-relative">
                                <form action="shortlist.php" method="POST" onsubmit="return confirm('Remove from shortlist?');">
                                    <input type="hidden" name="remove_id" value="<?= $row['shortlist_id'] ?>">
                                    <button type="submit" class="remove-btn shadow-sm" title="Remove Profile">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>

                                <img src="/online-rishta-system/assets/images/uploads/<?= $row['profile_pic'] ?: 'default.jpg' ?>" class="shortlist-img w-100 <?= $photo_hidden ? 'blur-20' : '' ?>" alt="Profile">
                                
                                <div class="card-body p-3 p-md-4">
                                    <div class="mb-1">
                                        <h6 class="fw-bold mb-0 text-dark text-truncate"><?= htmlspecialchars(explode(' ', $row['full_name'])[0]) ?>, <?= $age ?></h6>
                                        <p class="text-muted small mb-2 text-truncate"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($row['city'] ?: 'Nearby') ?></p>
                                    </div>
                                    
                                    <p class="small text-dark mb-3 text-truncate opacity-75 d-none d-md-block">
                                        <i class="bi bi-briefcase me-2 text-muted"></i><?= htmlspecialchars($row['profession'] ?: 'Member') ?>
                                    </p>

                                    <div class="d-grid mt-auto">
                                        <a href="view_profile.php?id=<?= $row['profile_id'] ?>" class="btn btn-primary btn-sm rounded-pill fw-bold py-2">
                                            View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<style>
    .bg-rose-soft { background: #fff1f2; }
    .text-rose { color: #f43f5e; }
    .blur-20 { filter: blur(20px); }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
