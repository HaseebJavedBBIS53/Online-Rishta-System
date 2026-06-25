<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle action buttons (Interest/Skip)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_interest'])) {
        $target_id = (int) $_POST['send_interest'];
        // Check if interest already exists
        $chk = $pdo->prepare("SELECT id FROM interests WHERE sender_id = ? AND receiver_id = ?");
        $chk->execute([$user_id, $target_id]);
        if (!$chk->fetchColumn()) {
            $pdo->prepare("INSERT INTO interests (sender_id, receiver_id) VALUES (?, ?)")->execute([$user_id, $target_id]);
            set_flash("Interest sent successfully!", "success");
        }
        header("Location: dashboard.php");
        exit();
    }
    if (isset($_POST['skip_profile'])) {
        $_SESSION['skipped_profiles'][] = (int) $_POST['skip_profile'];
        header("Location: dashboard.php");
        exit();
    }
}

// Fetch skipped profiles from session
$skipped = $_SESSION['skipped_profiles'] ?? [];
$skipped_sql = !empty($skipped) ? "AND u.id NOT IN (" . implode(',', $skipped) . ")" : "";


// Get user profile status and real stats
$stmt = $pdo->prepare("SELECT p.is_verified, p.city, p.bio, p.education, p.profession, s.plan_name, s.plan_id, u.full_name, u.profile_pic, u.is_highlighted
                       FROM users u 
                       LEFT JOIN user_profiles p ON u.id = p.user_id 
                       LEFT JOIN subscriptions s ON u.plan_id = s.plan_id
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();

$is_verified = $userData['is_verified'] ?? 0;
$plan_name = $userData['plan_name'] ?? 'Free';
$is_premium = ($userData['plan_id'] ?? 1) > 1;

// Calculate Profile Completion %
$completion_points = 0;
if ($userData['full_name'])
    $completion_points += 20;
if ($userData['profile_pic'] && $userData['profile_pic'] != 'default.jpg')
    $completion_points += 20;
if ($userData['bio'])
    $completion_points += 20;
if ($userData['city'])
    $completion_points += 20;
if ($userData['education'] || $userData['profession'])
    $completion_points += 20;

$profileCompletion = $completion_points;

// Real Stats & Live Activity Details
$stats = [
    'matches' => $pdo->query("SELECT COUNT(*) FROM interests WHERE (sender_id = $user_id OR receiver_id = $user_id) AND status = 'Accepted'")->fetchColumn(),
    'last_match_name' => $pdo->query("SELECT u.full_name FROM interests i JOIN users u ON (i.sender_id = u.id OR i.receiver_id = u.id) WHERE u.id != $user_id AND (i.sender_id = $user_id OR i.receiver_id = $user_id) AND i.status = 'Accepted' ORDER BY i.created_at DESC LIMIT 1")->fetchColumn(),

    'interests_received' => $pdo->query("SELECT COUNT(*) FROM interests WHERE receiver_id = $user_id AND status = 'Pending'")->fetchColumn(),
    'last_interest_from' => $pdo->query("SELECT u.full_name FROM interests i JOIN users u ON i.sender_id = u.id WHERE i.receiver_id = $user_id AND i.status = 'Pending' ORDER BY i.created_at DESC LIMIT 1")->fetchColumn(),

    'unread_messages' => $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_id = $user_id AND is_read = 0")->fetchColumn(),
    'last_msg_snippet' => $pdo->query("SELECT message_text FROM messages WHERE receiver_id = $user_id ORDER BY created_at DESC LIMIT 1")->fetchColumn(),

    'profile_views' => $pdo->query("SELECT COUNT(*) FROM profile_views WHERE viewed_id = $user_id")->fetchColumn(),
    'last_viewer_name' => $pdo->query("SELECT u.full_name FROM profile_views v JOIN users u ON v.viewer_id = u.id WHERE v.viewed_id = $user_id ORDER BY v.view_date DESC, v.id DESC LIMIT 1")->fetchColumn(),

    'shortlisted' => $pdo->query("SELECT COUNT(*) FROM shortlists WHERE user_id = $user_id")->fetchColumn(),
    'recent_activity' => $pdo->query("SELECT (
        (SELECT COUNT(*) FROM interests WHERE (sender_id = $user_id OR receiver_id = $user_id) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
        (SELECT COUNT(*) FROM profile_views WHERE viewed_id = $user_id AND view_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
        (SELECT COUNT(*) FROM messages WHERE (sender_id = $user_id OR receiver_id = $user_id) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    )")->fetchColumn()
];

// Fetch 4 suggested matches based on opposite gender (excluding skipped and already sent)
$my_gender = $pdo->query("SELECT gender FROM users WHERE id = $user_id")->fetchColumn();
$stmt = $pdo->prepare("SELECT u.id, u.full_name, u.dob, u.profile_pic, p.city, p.profession 
                       FROM users u 
                       LEFT JOIN user_profiles p ON u.id = p.user_id 
                       WHERE u.id != ? AND u.gender != ? AND u.role = 'User' AND u.status = 'Active'
                       $skipped_sql
                       AND u.id NOT IN (SELECT receiver_id FROM interests WHERE sender_id = ?)
                       ORDER BY RAND() LIMIT 4");
$stmt->execute([$user_id, $my_gender, $user_id]);
$suggestions = $stmt->fetchAll();

// Get Profile Views Limit data
$stmt = $pdo->prepare("SELECT s.profile_view_limit FROM users u LEFT JOIN subscriptions s ON u.plan_id = s.plan_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$profile_view_limit = $stmt->fetchColumn() ?: 2;

// Count total unique views (lifetime for limits)
$today_views = $pdo->prepare("SELECT COUNT(DISTINCT viewed_id) FROM profile_views WHERE viewer_id = ?");
$today_views->execute([$user_id]);
$views_done = $today_views->fetchColumn();
$views_left = max(0, $profile_view_limit - $views_done);
$views_percent = $profile_view_limit < 999999 ? min(100, ($views_done / $profile_view_limit) * 100) : 0;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    /* New Match Card styling */
    .match-card-modern {
        background: #fff;
        border-radius: 1.5rem;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .match-card-modern:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08) !important;
        border-color: rgba(99, 102, 241, 0.3);
    }

    .match-img-wrapper {
        position: relative;
        height: 180px;
        width: 100%;
        overflow: hidden;
    }

    .match-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .match-card-modern:hover .match-img-wrapper img {
        transform: scale(1.05);
    }

    .match-img-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 50%;
        background: linear-gradient(0deg, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
    }

    .action-btn-circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        transition: all 0.2s;
    }

    .btn-interest {
        background: linear-gradient(135deg, #e83e8c, #6366f1);
        color: white;
    }

    .btn-interest:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 10px rgba(232, 62, 140, 0.4);
        color: white;
    }

    .btn-skip {
        background: #f1f5f9;
        color: #64748b;
    }

    .btn-skip:hover {
        background: #e2e8f0;
        color: #334155;
        transform: scale(1.1);
    }

    .watermark::before {
        content: "";
        position: absolute;
        z-index: 5;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: block;
    }

    .blur-image img {
        filter: blur(15px);
        pointer-events: none;
        user-select: none;
    }

    /* Mobile Discover Optimization (2 columns) */
    @media (max-width: 576px) {
        .match-img-wrapper {
            height: 120px !important;
        }

        .match-card-modern h5 {
            font-size: 0.8rem !important;
        }

        .match-card-modern small {
            font-size: 0.65rem !important;
        }

        .match-card-modern .btn-sm {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.65rem !important;
        }

        .match-card-modern .action-btn-circle {
            width: 28px !important;
            height: 28px !important;
            font-size: 0.7rem !important;
        }

        .match-card-modern .card-body,
        .match-card-modern .p-3 {
            padding: 0.75rem !important;
        }
    }
</style>

<div class="container-fluid bg-light">
    <div class="row g-0">
        <!-- Sidebar -->
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <!-- Main Content (Removed excessive top padding) -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-2 pb-4">

            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div
                        class="d-flex flex-column flex-md-row align-items-md-center justify-content-between p-3 bg-white shadow-sm rounded-4 border-0">
                        <div class="mb-3 mb-md-0">
                            <h4 class="fw-bold mb-0">Welcome back, <?= explode(' ', $userData['full_name'])[0] ?>! 👋
                            </h4>
                            <p class="text-muted mb-0 small">Here's what's happening with your profile today.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!$is_verified): ?>
                                <span
                                    class="badge bg-soft-warning text-warning d-flex align-items-center px-3 py-2 border border-warning"
                                    style="background-color: #fff9e6;">
                                    <i class="bi bi-shield-slash me-2"></i> Unverified
                                </span>
                            <?php else: ?>
                                <span
                                    class="badge bg-soft-success text-success d-flex align-items-center px-3 py-2 border border-success"
                                    style="background-color: #f0fff4;">
                                    <i class="bi bi-shield-check me-2"></i> Verified Profile
                                </span>
                            <?php endif; ?>
                            <?php if (!$userData['is_highlighted']): ?>
                                <a href="boost_checkout.php"
                                    class="badge bg-warning text-dark text-decoration-none d-flex align-items-center px-3 py-2 fw-bold shadow-sm hover-lift">
                                    <i class="bi bi-rocket-takeoff-fill me-2"></i> Boost Profile
                                </a>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark d-flex align-items-center px-3 py-2 shadow-sm">
                                    <i class="bi bi-star-fill me-2"></i> Boosted
                                </span>
                            <?php endif; ?>
                            <span class="badge bg-primary d-flex align-items-center px-3 py-2">
                                <i class="bi bi-gem me-2"></i> <?= $plan_name ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 6 Interactive Stats Cards (3x2 Grid) -->
                <div class="col-6 col-md-4 col-lg-4">
                    <a href="matches.php" class="card card-vibrant grad-primary shadow-sm h-100 text-decoration-none">
                        <div class="card-vibrant-inner p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <i class="bi bi-people-fill fs-3 text-white-50"></i>
                                <span class="badge bg-white bg-opacity-25 rounded-pill px-3"><?= $stats['matches'] ?>
                                    Total</span>
                            </div>
                            <h5 class="text-white fw-bold mb-1">Matches</h5>
                            <p class="text-white-50 small mb-0">
                                <?= $stats['last_match_name'] ? "Latest: " . explode(' ', $stats['last_match_name'])[0] : "No matches yet" ?>
                            </p>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-4 col-lg-4">
                    <a href="interests.php" class="card card-vibrant grad-success shadow-sm h-100 text-decoration-none">
                        <div class="card-vibrant-inner p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <i class="bi bi-heart-fill fs-3 text-white-50"></i>
                                <span
                                    class="badge bg-white bg-opacity-25 rounded-pill px-3"><?= $stats['interests_received'] ?>
                                    New</span>
                            </div>
                            <h5 class="text-white fw-bold mb-1">Interests</h5>
                            <p class="text-white-50 small mb-0">
                                <?= $stats['last_interest_from'] ? "From: " . explode(' ', $stats['last_interest_from'])[0] : "No new interests" ?>
                            </p>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-4 col-lg-4">
                    <a href="chat.php" class="card card-vibrant grad-info shadow-sm h-100 text-decoration-none">
                        <div class="card-vibrant-inner p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <i class="bi bi-chat-heart-fill fs-3 text-white-50"></i>
                                <span
                                    class="badge bg-white bg-opacity-25 rounded-pill px-3"><?= $stats['unread_messages'] ?>
                                    Unread</span>
                            </div>
                            <h5 class="text-white fw-bold mb-1">Messages</h5>
                            <p class="text-white-50 small mb-0 d-block text-truncate">
                                <?= $stats['last_msg_snippet'] ? "\"" . substr($stats['last_msg_snippet'], 0, 20) . "...\"" : "Start a conversation" ?>
                            </p>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-4 col-lg-4">
                    <a href="visitors.php" class="card card-vibrant grad-warning shadow-sm h-100 text-decoration-none">
                        <div class="card-vibrant-inner p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <i class="bi bi-eye-fill fs-3 text-white-50"></i>
                                <span
                                    class="badge bg-white bg-opacity-25 rounded-pill px-3"><?= $stats['profile_views'] ?>
                                    Views</span>
                            </div>
                            <h5 class="text-dark fw-bold mb-1">Profile Views</h5>
                            <p class="text-dark-50 small mb-0 opacity-75">
                                <?= $stats['last_viewer_name'] ? "Last: " . explode(' ', $stats['last_viewer_name'])[0] : "No views today" ?>
                            </p>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-4 col-lg-4">
                    <a href="shortlist.php" class="card card-vibrant grad-purple shadow-sm h-100 text-decoration-none">
                        <div class="card-vibrant-inner p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <i class="bi bi-bookmark-heart-fill fs-3 text-white-50"></i>
                                <span
                                    class="badge bg-white bg-opacity-25 rounded-pill px-3"><?= $stats['shortlisted'] ?>
                                    Saved</span>
                            </div>
                            <h5 class="text-white fw-bold mb-1">Shortlist</h5>
                            <p class="text-white-50 small mb-0">Manage your saved profiles</p>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-4 col-lg-4">
                    <a href="activity.php" class="card card-vibrant grad-rose shadow-sm h-100 text-decoration-none">
                        <div class="card-vibrant-inner p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <i class="bi bi-activity fs-3 text-white-50"></i>
                                <span class="badge bg-white bg-opacity-25 rounded-pill px-3">Weekly</span>
                            </div>
                            <h5 class="text-white fw-bold mb-1">System Health</h5>
                            <p class="text-white-50 small mb-0"><?= $stats['recent_activity'] ?> interactions recently
                            </p>
                        </div>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column logic -->
                <div class="col-lg-4 d-flex flex-column gap-4">

                    <!-- Free Views Widget -->
                    <?php if (!$is_premium): ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-eye-fill text-warning me-2"></i>Daily
                                        Free Views</h6>
                                    <span class="badge bg-danger rounded-pill"><?= $views_left ?> Left</span>
                                </div>
                                <div class="progress mb-2" style="height: 10px; border-radius: 5px; background: #f1f5f9;">
                                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated"
                                        role="progressbar" style="width: <?= $views_percent ?>%"></div>
                                </div>
                                <p class="text-muted small mb-0">You have viewed <strong><?= $views_done ?></strong> out of
                                    <?= $profile_view_limit ?> allotted profiles. Get Premium for unlimited views.
                                </p>
                                <?php if ($views_left == 0): ?>
                                    <a href="subscription.php"
                                        class="btn btn-sm btn-outline-warning w-100 mt-3 fw-bold rounded-pill">Upgrade to
                                        Premium</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Completion -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                        <div class="card-body p-4 text-center">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0 text-dark">Profile Completion</h6>
                                <span
                                    class="badge bg-primary bg-opacity-10 text-primary px-3"><?= $profileCompletion ?>%</span>
                            </div>
                            <div class="position-relative mb-4" style="height: 180px;">
                                <canvas id="completionChart" class="mx-auto"></canvas>
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <div class="text-center">
                                        <h2 class="fw-bold mb-0 text-primary"><?= $profileCompletion ?>%</h2>
                                        <small class="text-muted fw-bold">Done</small>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted small mb-4 px-2">"A complete profile attracts 80% more interests."</p>
                            <a href="profile.php"
                                class="btn btn-outline-primary w-100 fw-bold py-2 rounded-pill shadow-sm transition-all">
                                <i class="bi bi-pencil-square me-2"></i> Enhance Profile
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Suggested Matches + Discovery Quick Bar -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-transparent">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-dark">Discover New Matches</h5>
                            <a href="search.php" class="text-primary text-decoration-none fw-bold small">Explore All <i
                                    class="bi bi-arrow-right"></i></a>
                        </div>

                        <div class="bg-white rounded-4 shadow-sm p-2 d-flex gap-2 mb-4 overflow-auto" id="discoverTabs">
                            <button
                                class="btn btn-white btn-sm shadow-sm rounded-pill flex-grow-1 fw-bold active text-nowrap"
                                data-tab="for_you"><i class="bi bi-stars text-warning me-1"></i> For You</button>
                            <button class="btn btn-light btn-sm rounded-pill flex-grow-1 fw-bold text-muted text-nowrap"
                                data-tab="nearby"><i class="bi bi-geo-alt me-1"></i> Nearby</button>
                            <button class="btn btn-light btn-sm rounded-pill flex-grow-1 fw-bold text-muted text-nowrap"
                                data-tab="education"><i class="bi bi-mortarboard me-1"></i> Education</button>
                            <button class="btn btn-light btn-sm rounded-pill flex-grow-1 fw-bold text-muted text-nowrap"
                                data-tab="newest"><i class="bi bi-lightning-charge me-1"></i> Newest</button>
                        </div>

                        <div class="row g-4" id="discoverMatchesContainer">
                            <?php if (empty($suggestions)): ?>
                                <div class="col-12 py-5 text-center text-muted bg-white rounded-4 shadow-sm p-5">
                                    <i class="bi bi-person-heart display-3 opacity-25"></i>
                                    <p class="mt-3 fw-bold">You've seen all suggestions!</p>
                                    <p class="small">Try adjusting your preferences or searching manually.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($suggestions as $mate):
                                    $age = (new DateTime($mate['dob']))->diff(new DateTime('today'))->y;
                                    ?>
                                    <div class="col-6 col-md-6">
                                        <div class="match-card-modern shadow-sm">
                                            <div class="match-img-wrapper">
                                                <img src="/online-rishta-system/assets/images/uploads/<?= $mate['profile_pic'] ?: 'default.jpg' ?>"
                                                    alt="Profile">
                                                <div
                                                    class="match-img-overlay d-flex flex-column justify-content-end p-3 text-white">
                                                    <h5 class="fw-bold mb-0 text-truncate text-white d-flex align-items-center">
                                                        <?= htmlspecialchars($mate['full_name']) ?>
                                                    </h5>
                                                    <small class="opacity-75"><i class="bi bi-geo-alt me-1"></i>
                                                        <?= $mate['city'] ?: 'Unknown' ?></small>
                                                </div>
                                            </div>
                                            <div class="p-3 bg-white">
                                                <div class="text-muted small mb-3 text-truncate"><i
                                                        class="bi bi-briefcase me-1"></i>
                                                    <?= $mate['profession'] ?: 'Not specified' ?></div>

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="view_profile.php?id=<?= $mate['id'] ?>"
                                                        class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">View
                                                        Profile</a>
                                                    <div class="d-flex gap-2">
                                                        <form method="POST" action="dashboard.php" class="m-0 p-0">
                                                            <button type="submit" name="skip_profile" value="<?= $mate['id'] ?>"
                                                                class="action-btn-circle btn-skip" title="Skip">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="dashboard.php" class="m-0 p-0">
                                                            <button type="submit" name="send_interest"
                                                                value="<?= $mate['id'] ?>"
                                                                class="action-btn-circle btn-interest" title="Send Interest">
                                                                <i class="bi bi-heart-fill"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Chart.js for completion wheel -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('completionChart');
    if (ctx) {
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, '#6366f1');
        gradient.addColorStop(1, '#a855f7');

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?= $profileCompletion ?>, <?= 100 - $profileCompletion ?>],
                    backgroundColor: [gradient, '#f3f4f6'],
                    borderWidth: 0,
                    borderRadius: 10,
                    hoverOffset: 0
                }]
            },
            options: {
                cutout: '80%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { duration: 2000, easing: 'easeOutQuart' }
            }
        });
    }

    // Discover Matches AJAX Tabs
    const tabs = document.querySelectorAll('#discoverTabs button');
    const container = document.getElementById('discoverMatchesContainer');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            // UI Switch
            tabs.forEach(t => {
                t.className = "btn btn-light btn-sm rounded-pill flex-grow-1 fw-bold text-muted text-nowrap";
            });
            this.className = "btn btn-white btn-sm shadow-sm rounded-pill flex-grow-1 fw-bold active text-nowrap";

            // Loading State
            container.innerHTML = '<div class="col-12 py-5 text-center"><div class="spinner-border text-primary" role="status"></div></div>';

            // AJAX Fetch
            fetch(`ajax_discover.php?tab=${this.dataset.tab}`)
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = '<div class="col-12 text-center text-danger">Failed to load content.</div>';
                });
        });
    });
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>