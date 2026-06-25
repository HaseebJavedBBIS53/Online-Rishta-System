<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
check_feature_access('matches'); // Enforce verification for matches

// Get current user info and preferences
$stmt = $pdo->prepare("SELECT gender, plan_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();
$my_gender = $currentUser['gender'] ?? 'Male';
$is_premium = ($currentUser['plan_id'] ?? 1) > 1;

$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$prefs = $stmt->fetch();

$matches = [];
if ($prefs) {
    // Determine target gender (opposite of current user)
    $target_gender = ($my_gender === 'Male') ? 'Female' : 'Male';
    
    // Base SQL query for potential candidates
    $query = "SELECT u.id, u.full_name, u.dob, u.profile_pic, u.photo_visibility, 
              p.city, p.education_level, p.profession, p.religion, p.sect, p.marital_status, p.height
              FROM users u 
              LEFT JOIN user_profiles p ON u.id = p.user_id 
              WHERE u.id != ? AND u.status = 'Active' AND u.role = 'User' AND u.gender = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $target_gender]);
    $candidates = $stmt->fetchAll();

    foreach($candidates as $c) {
        $c['score'] = calculate_match_score($prefs, $c);
        // Only show matches with at least 30% compatibility to keep it relevant
        if ($c['score'] >= 30) {
            $matches[] = $c;
        }
    }
    
    // Sort by descending score
    usort($matches, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .match-header { background: radial-gradient(circle at top left, #6366f1, #4f46e5); color: white; border-radius: 20px; padding: 25px; margin-bottom: 20px; position: relative; overflow: hidden; }
    @media (max-width: 768px) { .match-header { padding: 20px; text-align: center; } }
    .match-card { border: none; border-radius: 15px; transition: all 0.3s ease; background: white; border: 1px solid rgba(0,0,0,0.05); }
    .match-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important; }
    .score-ring { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.75rem; border: 2px solid; }
    .score-high { border-color: #10b981; color: #10b981; background: #ecfdf5; }
    .score-mid { border-color: #f59e0b; color: #f59e0b; background: #fffbeb; }
    .score-low { border-color: #6366f1; color: #6366f1; background: #eef2ff; }
    .match-img { height: 180px; object-fit: cover; border-top-left-radius: 15px; border-top-right-radius: 15px; }
    @media (min-width: 992px) { .match-img { height: 260px; } }
    .compat-bar { height: 4px; border-radius: 2px; background: #f1f5f9; margin: 10px 0; overflow: hidden; }
    .compat-fill { height: 100%; transition: width 1s ease-out; }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            
            <div class="match-header shadow-lg">
                <div class="row align-items-center position-relative z-1">
                    <div class="col-lg-7">
                        <span class="badge bg-white bg-opacity-20 text-white px-3 py-2 rounded-pill mb-2 fw-bold d-none d-md-inline-block">Match Engine v2.0</span>
                        <h1 class="fw-bold mb-2">Soul Matches</h1>
                        <p class="opacity-75 mb-0 small">Personalized matches based on your lifestyle and preferences.</p>
                    </div>
                </div>
                <i class="bi bi-heart-pulse-fill position-absolute top-50 end-0 translate-middle-y opacity-10 d-none d-lg-block" style="font-size: 15rem;"></i>
            </div>

            <?php if (!$prefs): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="bg-warning bg-opacity-10 p-4 rounded-circle d-inline-block mb-4">
                        <i class="bi bi-gear-wide-connected display-1 text-warning"></i>
                    </div>
                    <h3 class="fw-bold">preferences Not Set</h3>
                    <p class="text-muted mx-auto" style="max-width: 500px;">To find your perfect match, we need to know what you are looking for. Set your partner preferences to unlock your compatibility scores.</p>
                    <div class="mt-2">
                        <a href="preferences.php" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow">Configure Preferences</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3 g-md-4">
                    <?php if (empty($matches)): ?>
                        <div class="col-12 text-center py-5">
                            <div class="card border-0 shadow-sm rounded-4 p-5 bg-white">
                                <i class="bi bi-person-heart display-1 text-muted opacity-25"></i>
                                <h4 class="mt-4 fw-bold">Broaden Your Search</h4>
                                <p class="text-muted small">Try adjusting your preferences to find more matches.</p>
                                <a href="preferences.php" class="btn btn-primary rounded-pill px-4 mt-2">Adjust Prefs</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($matches as $m): 
                            $age = (new DateTime($m['dob']))->diff(new DateTime('today'))->y;
                            $photo_hidden = ($m['photo_visibility'] === 'Premium' && !$is_premium);
                            $score_class = $m['score'] >= 80 ? 'score-high' : ($m['score'] >= 60 ? 'score-mid' : 'score-low');
                            $score_color = $m['score'] >= 80 ? '#10b981' : ($m['score'] >= 60 ? '#f59e0b' : '#6366f1');
                        ?>
                            <div class="col-6 col-md-6 col-lg-4 col-xl-3">
                                <div class="card match-card h-100 shadow-sm">
                                    <div class="position-relative">
                                        <img src="/online-rishta-system/assets/images/uploads/<?= $m['profile_pic'] ?: 'default.jpg' ?>" class="match-img w-100 <?= $photo_hidden ? 'blur-20' : '' ?>" alt="Profile">
                                        <div class="position-absolute bottom-0 start-0 m-3">
                                            <div class="score-ring <?= $score_class ?> shadow-lg">
                                                <?= $m['score'] ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-3 p-md-4">
                                        <div class="mb-1">
                                            <h6 class="fw-bold mb-0 text-slate-800 text-truncate"><?= htmlspecialchars(explode(' ', $m['full_name'])[0]) ?>, <?= $age ?></h6>
                                            <p class="text-muted small mb-0 text-truncate"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($m['city'] ?: 'Nearby') ?></p>
                                        </div>
                                        
                                        <div class="compat-bar">
                                            <div class="compat-fill" style="width: <?= $m['score'] ?>%; background: <?= $score_color ?>;"></div>
                                        </div>

                                        <ul class="list-unstyled small mb-3 text-slate-600 d-none d-md-block">
                                            <li class="mb-2 d-flex align-items-center"><i class="bi bi-briefcase me-2 text-muted"></i> <?= htmlspecialchars($m['profession'] ?: 'Profession') ?></li>
                                            <li class="mb-0 d-flex align-items-center"><i class="bi bi-mortarboard me-2 text-muted"></i> <?= htmlspecialchars($m['education_level'] ?: 'Academic') ?></li>
                                        </ul>

                                        <div class="d-grid gap-2">
                                            <a href="view_profile.php?id=<?= $m['id'] ?>" class="btn btn-primary btn-sm rounded-pill fw-bold py-2">
                                                View Match
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<style>
    .blur-20 { filter: blur(20px); }
    .text-slate-800 { color: #1e293b; }
    .text-slate-600 { color: #475569; }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
