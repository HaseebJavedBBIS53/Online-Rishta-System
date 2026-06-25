<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: search.php");
    exit();
}
$target_id = intval($_GET['id']);

// Handle Meeting Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_meeting') {
    $m_date = sanitize_input($_POST['meeting_date']);
    $m_time = sanitize_input($_POST['meeting_time']);
    $m_type = sanitize_input($_POST['meeting_type']);
    $m_loc = sanitize_input($_POST['location'] ?? '');
    $m_link = sanitize_input($_POST['meeting_link'] ?? '');
    $m_notes = sanitize_input($_POST['notes'] ?? '');

    // Enforce Meeting Request Limits & Verification
    check_feature_access('meeting_request');

    // Validation
    if ($m_date < date('Y-m-d')) {
        set_flash("Meeting date cannot be in the past.", "danger");
    } else {
        // Prevent duplicates
        $check = $pdo->prepare("SELECT id FROM meetings WHERE sender_id = ? AND receiver_id = ? AND status IN ('Pending User Response', 'Accepted by Receiver', 'Waiting for Admin Approval', 'Approved by Admin')");
        $check->execute([$user_id, $target_id]);
        if ($check->fetch()) {
            set_flash("You already have an active meeting request with this user.", "warning");
        } else {
            $stmt = $pdo->prepare("INSERT INTO meetings (sender_id, receiver_id, meeting_date, meeting_time, meeting_type, location, meeting_link, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $target_id, $m_date, $m_time, $m_type, $m_loc, $m_link, $m_notes]);

            // Increment meeting counter
            $pdo->prepare("UPDATE users SET meetings_requested_count = meetings_requested_count + 1 WHERE id = ?")
                ->execute([$user_id]);

            set_flash("Meeting request sent! Waiting for receiver response.", "success");
            header("Location: view_profile.php?id=$target_id");
            exit();
        }
    }
}
$target_id = intval($_GET['id']);

// Cannot view your own profile this way
if ($user_id === $target_id) {
    header("Location: profile.php");
    exit();
}

$is_premium = !is_free_plan();

// 1. Enforce Profile View Limits & Verification
check_feature_access('profile_view');

// 2. Log view (Premium or within limit)
// We only log if not already viewed to keep count accurate
$stmt = $pdo->prepare("SELECT id FROM profile_views WHERE viewer_id = ? AND viewed_id = ?");
$stmt->execute([$user_id, $target_id]);
$already_viewed = $stmt->fetch();

if (!$already_viewed) {
    $pdo->prepare("INSERT INTO profile_views (viewer_id, viewed_id, view_date) VALUES (?, ?, CURDATE())")
        ->execute([$user_id, $target_id]);

    // Increment the counter in users table for quick checking
    $pdo->prepare("UPDATE users SET profiles_viewed_count = profiles_viewed_count + 1 WHERE id = ?")
        ->execute([$user_id]);
}

// Fetch Target Profile Details
$query = "SELECT u.id, u.full_name, u.email, u.phone, u.gender, u.dob, u.profile_pic, u.photo_visibility, u.role,
          p.education_level as education, p.degree_title, p.company_name, p.employment_type,
          p.religion, p.sect, p.profession, p.monthly_income as income, p.city, p.country, p.bio, p.is_verified, 
          p.marital_status, p.height, p.body_type, p.complexion, p.weight, p.smoking, p.drinking, p.mother_tongue,
          s.profile_visibility as account_visibility, s.can_contact,
          (SELECT status FROM interests WHERE sender_id = :uid AND receiver_id = u.id) as sent_status,
          (SELECT status FROM interests WHERE sender_id = u.id AND receiver_id = :uid) as received_status,
          (SELECT id FROM shortlists WHERE user_id = :uid AND profile_id = u.id) as is_shortlisted
          FROM users u 
          LEFT JOIN user_profiles p ON u.id = p.user_id 
          LEFT JOIN user_settings s ON u.id = s.user_id 
          WHERE u.id = :tid AND u.status != 'Deleted'";

$stmt = $pdo->prepare($query);
$stmt->execute([':uid' => $user_id, ':tid' => $target_id]);
$target = $stmt->fetch();

if (!$target) {
    set_flash("Profile not found or inactive.", "danger");
    header("Location: dashboard.php");
    exit();
}

// 1. Account Visibility Privacy Logic
if ($target['account_visibility'] === 'Private') {
    set_flash("This user has set their profile to Private.", "warning");
    header("Location: dashboard.php");
    exit();
} elseif ($target['account_visibility'] === 'Premium' && !$is_premium) {
    set_flash("This profile can only be viewed by Premium Members. <a href='subscription.php'>Upgrade Now</a>", "warning");
    header("Location: dashboard.php");
    exit();
}

// 2. Can Contact Privacy Logic
$can_send_interest = true;
$contact_reason = "";
if ($target['can_contact'] === 'Premium' && !$is_premium) {
    $can_send_interest = false;
    $contact_reason = "Requires Premium membership to send interest.";
} elseif ($target['can_contact'] === 'Verified') {
    $stmt = $pdo->prepare("SELECT is_verified FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetchColumn()) {
        $can_send_interest = false;
        $contact_reason = "Requires a verified profile to send interest.";
    }
}

$age = (new DateTime($target['dob']))->diff(new DateTime('today'))->y;

// Photo Visibility Logic
$show_photo = false;
if ($is_premium) {
    $show_photo = true;
} else if ($target['photo_visibility'] === 'All') {
    $show_photo = true;
} else if ($target['photo_visibility'] === 'Matched' && ($target['sent_status'] === 'Accepted' || $target['received_status'] === 'Accepted')) {
    $show_photo = true;
}

$show_sensitive = ($target['sent_status'] === 'Accepted' || $target['received_status'] === 'Accepted');

// Handle Interest POST Action right here for simplicity, though normally in process folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'send_interest') {
        $pdo->prepare("INSERT INTO interests (sender_id, receiver_id, status) VALUES (?, ?, 'Pending')")
            ->execute([$user_id, $target_id]);
        set_flash("Interest request sent!");
    } else if ($action === 'shortlist') {
        $pdo->prepare("INSERT INTO shortlists (user_id, profile_id) VALUES (?, ?)")
            ->execute([$user_id, $target_id]);
        set_flash("Profile added to your shortlist!");
    } else if ($action === 'remove_shortlist') {
        $pdo->prepare("DELETE FROM shortlists WHERE user_id = ? AND profile_id = ?")
            ->execute([$user_id, $target_id]);
        set_flash("Profile removed from your shortlist!");
    }
    header("Location: view_profile.php?id=$target_id");
    exit();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light py-3">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="mb-3 mt-2">
                <a href="javascript:history.back()" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back
                    to Search</a>
            </div>

            <div class="row">
                <!-- Profile Sidebar Card -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow border-0 text-center p-4">
                        <div class="position-relative mx-auto mb-3" style="width: 150px; height: 150px;">
                            <div class="<?= (!$show_photo && !$is_premium) ? 'watermark' : '' ?> h-100 w-100">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $target['profile_pic'] ?: 'default.jpg' ?>"
                                    class="rounded-circle w-100 h-100"
                                    style="object-fit: cover; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1);"
                                    alt="Profile">
                            </div>
                            <?php if (!$show_photo): ?>
                                <div class="position-absolute top-50 start-50 translate-middle text-dark w-100 bg-white bg-opacity-75 py-1"
                                    style="font-size: 0.7rem;">
                                    <small class="fw-bold"><i class="bi bi-lock-fill text-danger"></i> Photo Hidden</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($target['full_name']) ?> <span
                                class="text-muted">(<?= $age ?>)</span></h5>

                        <?php if ($target['is_verified']): ?>
                            <div class="mb-3">
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"
                                    style="font-size: 0.7rem;"><i class="bi bi-shield-check"></i> Verified Profile</span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-column gap-2 mt-2">
                            <div class="d-flex gap-2">
                                <!-- Interest Logic -->
                                <?php if ($target['sent_status'] === 'Pending'): ?>
                                    <button class="btn btn-secondary disabled w-100 py-2 sm-btn" title="Interest Sent"><i
                                            class="bi bi-clock-history"></i> Pending</button>
                                <?php elseif ($target['sent_status'] === 'Accepted' || $target['received_status'] === 'Accepted' || $target['role'] === 'Admin'): ?>
                                    <a href="chat.php?user=<?= $target_id ?>"
                                        class="btn btn-<?= $target['role'] === 'Admin' ? 'dark' : 'success' ?> fw-bold shadow-sm w-100 py-2">
                                        <i class="bi bi-chat-dots-fill"></i>
                                        <?= $target['role'] === 'Admin' ? 'Support' : 'Chat' ?>
                                    </a>
                                <?php elseif ($target['received_status'] === 'Pending'): ?>
                                    <a href="interests.php" class="btn btn-warning fw-bold w-100 py-2"><i
                                            class="bi bi-exclamation-circle-fill"></i> Review</a>
                                <?php else: ?>
                                    <?php if (!$can_send_interest): ?>
                                        <button class="btn btn-primary disabled fw-bold w-100" title="<?= $contact_reason ?>"><i
                                                class="bi bi-shield-lock"></i> Restricted</button>
                                    <?php else: ?>
                                        <form action="view_profile.php?id=<?= $target_id ?>" method="POST" class="w-100">
                                            <input type="hidden" name="action" value="send_interest">
                                            <button type="submit" class="btn btn-primary fw-bold w-100 py-2"><i
                                                    class="bi bi-heart-fill"></i> Send Interest</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Shortlist Logic -->
                                <form action="view_profile.php?id=<?= $target_id ?>" method="POST" class="d-inline">
                                    <?php if ($target['is_shortlisted']): ?>
                                        <input type="hidden" name="action" value="remove_shortlist">
                                        <button type="submit" class="btn btn-danger py-2" title="Remove"><i
                                                class="bi bi-bookmark-x-fill"></i></button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="shortlist">
                                        <button type="submit" class="btn btn-outline-danger py-2" title="Save"><i
                                                class="bi bi-bookmark-heart"></i></button>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <!-- Meeting Request Button -->
                            <?php if ($target['role'] !== 'Admin'): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold py-2 rounded-3"
                                    data-bs-toggle="modal" data-bs-target="#meetingModal">
                                    <i class="bi bi-calendar-check-fill me-1"></i> Request Meeting
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Details -->
                <div class="col-md-8 mb-4">
                    <div class="card shadow border-0 h-100">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="m-0 fw-bold"><i class="bi bi-person-lines-fill me-2"></i> Profile Overview</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">About
                                <?= htmlspecialchars(explode(' ', $target['full_name'])[0]) ?>
                            </h6>
                            <p class="mb-4"><?= nl2br(htmlspecialchars($target['bio'] ?? 'No bio provided.')) ?></p>

                            <!-- Photo Gallery -->
                            <?php
                            $gallery_stmt = $pdo->prepare("SELECT image_path FROM user_gallery WHERE user_id = ? ORDER BY created_at DESC");
                            $gallery_stmt->execute([$target_id]);
                            $gallery = $gallery_stmt->fetchAll();
                            ?>
                            <?php if (!empty($gallery)): ?>
                                <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">Photo Gallery</h6>
                                <div class="row g-2 mb-4">
                                    <?php foreach ($gallery as $img): ?>
                                        <div class="col-4 col-md-3">
                                            <a href="/online-rishta-system/assets/images/uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>"
                                                target="_blank">
                                                <img src="/online-rishta-system/assets/images/uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>"
                                                    class="img-fluid rounded shadow-sm"
                                                    style="height: 100px; width: 100%; object-fit: cover;">
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">Information &
                                Details</h6>
                            <div class="row mb-4">
                                <div class="col-6 mb-3"><strong><i class="bi bi-star"></i> Religion:</strong> <br><span
                                        class="text-muted small"><?= htmlspecialchars($target['religion'] ?? 'N/A') ?>
                                        <?= htmlspecialchars($target['sect'] ? ' - ' . $target['sect'] : '') ?></span>
                                </div>
                                <div class="col-6 mb-3"><strong><i class="bi bi-person-heart"></i> Marital
                                        Status:</strong> <br><span
                                        class="text-muted small"><?= htmlspecialchars($target['marital_status'] ?? 'N/A') ?></span>
                                </div>
                                <?php if ($show_sensitive): ?>
                                    <div class="col-6 mb-3"><strong><i class="bi bi-geo-alt"></i> Location:</strong>
                                        <br><span
                                            class="text-muted small"><?= htmlspecialchars($target['city'] ?? 'N/A') ?>,
                                            <?= htmlspecialchars($target['country'] ?? 'N/A') ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="col-6 mb-3"><strong><i class="bi bi-geo-alt"></i> Location:</strong>
                                        <br><span class="text-muted small fst-italic">Hidden</span>
                                    </div>
                                <?php endif; ?>
                                <div class="col-6 mb-3"><strong><i class="bi bi-translate"></i> Native:</strong>
                                    <br><span
                                        class="text-muted small"><?= htmlspecialchars($target['mother_tongue'] ?? 'N/A') ?></span>
                                </div>
                            </div>

                            <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">Education & Career
                            </h6>
                            <div class="row mb-4">
                                <div class="col-6 mb-3"><strong><i class="bi bi-mortarboard"></i> Education:</strong>
                                    <br><span
                                        class="text-muted small"><?= htmlspecialchars($target['education'] ?? 'N/A') ?></span>
                                </div>
                                <div class="col-6 mb-3"><strong><i class="bi bi-briefcase"></i> Profession:</strong>
                                    <br><span
                                        class="text-muted small"><?= htmlspecialchars($target['profession'] ?? 'N/A') ?></span>
                                </div>
                                <div class="col-6 mb-3"><strong><i class="bi bi-building"></i> Company:</strong>
                                    <br><span
                                        class="text-muted small text-truncate d-block"><?= htmlspecialchars($target['company_name'] ?? 'N/A') ?></span>
                                </div>
                                <div class="col-6 mb-3"><strong><i class="bi bi-cash-stack"></i> Income:</strong>
                                    <br><span
                                        class="text-muted small"><?= htmlspecialchars($target['income'] ?? 'N/A') ?></span>
                                </div>
                            </div>

                            <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">
                                <?= $target['gender'] ?> Specific Details
                            </h6>
                            <div class="row mb-4">
                                <?php if ($target['gender'] === 'Male'): ?>
                                    <div class="col-sm-6 mb-3"><strong>Beard Status:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['beard_status'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="col-sm-6 mb-3"><strong>Living Arrangement:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['living_arrangement'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="col-sm-6 mb-3"><strong>Responsibility Role:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['responsibility_role'] ?? 'N/A') ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="col-sm-6 mb-3"><strong>Hijab Preference:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['hijab_preference'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="col-sm-6 mb-3"><strong>Cooking Skill:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['cooking_skill'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="col-sm-6 mb-3"><strong>Working Status:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['working_status'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="col-sm-6 mb-3"><strong>Guardian Name:</strong> <br><span
                                            class="text-muted"><?= htmlspecialchars($target['guardian_name'] ?? 'N/A') ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="col-sm-6 mb-3"><strong>Will Relocate:</strong> <br><span
                                        class="text-muted"><?= htmlspecialchars($target['relocation_willingness'] ?? 'Maybe') ?></span>
                                </div>
                            </div>

                            <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">Physical
                                Attributes</h6>
                            <div class="row mb-4">
                                <div class="col-6 mb-2"><strong>Height:</strong> <span
                                        class="text-muted small"><?= htmlspecialchars($target['height'] ?? 'N/A') ?></span>
                                </div>
                                <div class="col-6 mb-2"><strong>Weight:</strong> <span
                                        class="text-muted small"><?= htmlspecialchars($target['weight'] ?? 'N/A') ?>
                                        kg</span></div>
                                <div class="col-6 mb-2"><strong>Body:</strong> <span
                                        class="text-muted small"><?= htmlspecialchars($target['body_type'] ?? 'N/A') ?></span>
                                </div>
                                <div class="col-6 mb-2"><strong>Complexion:</strong> <span
                                        class="text-muted small"><?= htmlspecialchars($target['complexion'] ?? 'N/A') ?></span>
                                </div>
                            </div>

                            <h6 class="fw-bold text-secondary text-uppercase mb-3 border-bottom pb-2">Contact
                                Information</h6>
                            <?php if ($show_sensitive): ?>
                                <div class="row">
                                    <div class="col-6 mb-2"><strong>Email:</strong> <a
                                            href="mailto:<?= htmlspecialchars($target['email']) ?>"><?= htmlspecialchars($target['email']) ?></a>
                                    </div>
                                    <div class="col-6 mb-2"><strong>Phone:</strong>
                                        <?= htmlspecialchars($target['phone'] ?? 'Not provided') ?></div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-6 mb-2"><strong>Email:</strong> <span
                                            class="text-muted fst-italic">Hidden <i class="bi bi-lock-fill"></i></span>
                                    </div>
                                    <div class="col-6 mb-2"><strong>Phone:</strong> <span
                                            class="text-muted fst-italic">Hidden <i class="bi bi-lock-fill"></i></span>
                                    </div>
                                </div>
                                <div class="alert alert-secondary d-flex align-items-center mt-2 mb-0">
                                    <i class="bi bi-shield-lock-fill display-6 me-3 text-secondary"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1">Strict Privacy Enforced</h6>
                                        <p class="mb-0 small text-muted">Direct contact details and precise location are
                                            hidden for all members to ensure security. Info is only visible if an interest
                                            request is mutually accepted.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Premium Meeting Request Modal -->
<div class="modal fade" id="meetingModal" tabindex="-1" aria-labelledby="meetingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Header -->
            <div class="modal-header bg-primary bg-opacity-10 border-0 p-4 pb-3 position-relative">
                <div class="position-absolute top-0 start-0 w-100 h-100 opacity-25" style="background-image: radial-gradient(#0d6efd 1px, transparent 1px); background-size: 20px 20px;"></div>
                <div class="d-flex align-items-center gap-3 position-relative z-1">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                        <i class="bi bi-calendar2-heart fs-4"></i>
                    </div>
                    <div>
                        <h4 class="modal-title fw-bold text-dark mb-0" id="meetingModalLabel">Plan a Meeting</h4>
                        <p class="text-muted small mb-0 mt-1">Propose a safe time and location for your meeting.</p>
                    </div>
                </div>
                <button type="button" class="btn-close position-relative z-1" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="view_profile.php?id=<?= $target_id ?>" method="POST">
                <input type="hidden" name="action" value="request_meeting">
                
                <div class="modal-body p-4 pt-4">
                    <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info-darken rounded-3 small mb-4 d-flex align-items-center">
                        <i class="bi bi-shield-check fs-4 me-3"></i> 
                        <div>All meeting requests require mutual acceptance and an admin's safety approval before proceeding.</div>
                    </div>

                    <div class="row g-4">
                        <!-- Meeting Type Selector -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary text-uppercase mb-3" style="letter-spacing: 0.5px;">Meeting Format</label>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check" name="meeting_type" id="typePhysical" value="Physical" checked onchange="toggleMeetingFields()">
                                    <label class="btn btn-outline-primary w-100 h-100 py-3 px-4 rounded-4 fw-bold shadow-sm border-2 text-start d-flex align-items-center meeting-type-card transition-all" for="typePhysical">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="bi bi-cup-hot fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fs-6">Physical Meet</div>
                                            <div class="small fw-normal opacity-75" style="font-size: 0.75rem;">In a safe public place</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check" name="meeting_type" id="typeOnline" value="Online" onchange="toggleMeetingFields()">
                                    <label class="btn btn-outline-primary w-100 h-100 py-3 px-4 rounded-4 fw-bold shadow-sm border-2 text-start d-flex align-items-center meeting-type-card transition-all" for="typeOnline">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="bi bi-laptop fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fs-6">Online Call</div>
                                            <div class="small fw-normal opacity-75" style="font-size: 0.75rem;">Via video or audio link</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time -->
                        <div class="col-sm-6">
                            <div class="form-floating shadow-sm rounded-4 overflow-hidden">
                                <input type="date" name="meeting_date" class="form-control border-0 bg-light" id="floatingDate" required min="<?= date('Y-m-d') ?>">
                                <label for="floatingDate" class="text-muted"><i class="bi bi-calendar-event me-2"></i>Date</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating shadow-sm rounded-4 overflow-hidden">
                                <input type="time" name="meeting_time" class="form-control border-0 bg-light" id="floatingTime" required>
                                <label for="floatingTime" class="text-muted"><i class="bi bi-clock me-2"></i>Time</label>
                            </div>
                        </div>

                        <!-- Location / Link -->
                        <div class="col-12" id="locationField">
                            <div class="form-floating shadow-sm rounded-4 overflow-hidden">
                                <input type="text" name="location" class="form-control border-0 bg-light" id="floatingLocation" placeholder="Location">
                                <label for="floatingLocation" class="text-muted"><i class="bi bi-geo-alt me-2"></i>Safe Public Location (e.g. Cafe)</label>
                            </div>
                        </div>
                        
                        <div class="col-12 d-none" id="linkField">
                            <div class="form-floating shadow-sm rounded-4 overflow-hidden">
                                <input type="url" name="meeting_link" class="form-control border-0 bg-light" id="floatingLink" placeholder="Link">
                                <label for="floatingLink" class="text-muted"><i class="bi bi-link-45deg me-2"></i>Meeting Link (e.g. Zoom, Meet)</label>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12">
                            <div class="form-floating shadow-sm rounded-4 overflow-hidden">
                                <textarea name="notes" class="form-control border-0 bg-light pt-4" id="floatingNotes" style="height: 100px" placeholder="Notes"></textarea>
                                <label for="floatingNotes" class="text-muted"><i class="bi bi-chat-left-text me-2"></i>Any special message?</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold transition-all" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm transition-all d-flex align-items-center gap-2">
                        Send Request <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Custom styling for the meeting type cards */
.meeting-type-card {
    background-color: #fff;
}
.btn-check:checked + .meeting-type-card {
    background-color: var(--bs-primary);
    color: #fff !important;
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(13, 110, 253, .15) !important;
}
.btn-check:checked + .meeting-type-card .bg-primary {
    background-color: rgba(255,255,255,0.2) !important;
}
.btn-check:checked + .meeting-type-card i {
    color: #fff !important;
}
.btn-check:not(:checked) + .meeting-type-card {
    color: #495057;
    border-color: #e9ecef !important;
}
.btn-check:not(:checked) + .meeting-type-card i {
    color: var(--bs-primary);
}
.transition-all {
    transition: all 0.2s ease-in-out;
}
.form-floating > .form-control:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
    background-color: #fff !important;
}
</style>

<script>
    function toggleMeetingFields() {
        const isOnline = document.getElementById('typeOnline').checked;
        document.getElementById('locationField').classList.toggle('d-none', isOnline);
        document.getElementById('linkField').classList.toggle('d-none', !isOnline);

        if (isOnline) {
            document.querySelector('[name="location"]').required = false;
            document.querySelector('[name="meeting_link"]').required = true;
        } else {
            document.querySelector('[name="location"]').required = true;
            document.querySelector('[name="meeting_link"]').required = false;
        }
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>