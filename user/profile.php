<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user & profile data
$stmt = $pdo->prepare("SELECT u.*, p.* FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$data = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Update users table (Core Info)
    $phone = sanitize_input($_POST['phone'] ?? $data['phone']);
    $photo_visibility = sanitize_input($_POST['photo_visibility'] ?? $data['photo_visibility']);

    // 2. Handle Profile Picture
    $profile_pic = $data['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = "profile_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], dirname(__DIR__) . "/assets/images/uploads/" . $new_name)) {
                $profile_pic = $new_name;
                $_SESSION['profile_pic'] = $new_name; // Fix Profile Pic not showing immediately
            }
        }
    }

    $pdo->prepare("UPDATE users SET phone = ?, profile_pic = ?, photo_visibility = ? WHERE id = ?")
        ->execute([$phone, $profile_pic, $photo_visibility, $user_id]);

    // 3. Update user_profiles (Detailed Info)
    $fields = [
        'marital_status',
        'height',
        'sect',
        'caste',
        'mother_tongue',
        'country',
        'education_level',
        'degree_title',
        'profession',
        'company_name',
        'monthly_income',
        'employment_type',
        'weight',
        'complexion',
        'body_type',
        'smoking',
        'drinking',
        'disability',
        'city',
        'bio',
        'beard_status',
        'living_arrangement',
        'responsibility_role',
        'relocation_willingness',
        'career_stability',
        'hijab_preference',
        'cooking_skill',
        'working_status',
        'working_after_marriage',
        'guardian_name',
        'guardian_contact',
        'household_skill'
    ];

    $params = [];
    $set_clauses = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? $data[$f];
        $set_clauses[] = "`$f` = ?";
        $params[] = $val;
    }
    $params[] = $user_id;

    // Ensure profile row exists before updating
    $check = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $check->execute([$user_id]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$user_id]);
    }

    $pdo->prepare("UPDATE user_profiles SET " . implode(', ', $set_clauses) . " WHERE user_id = ?")
        ->execute($params);

    set_flash("Profile updated successfully!", "success");
    header("Location: profile.php");
    exit();
}

// Handle Gallery Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_gallery'])) {
    if (!empty($_FILES['gallery_photos']['name'][0])) {
        $files = $_FILES['gallery_photos'];
        $upload_dir = dirname(__DIR__) . '/assets/images/uploads/gallery/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Check current count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_gallery WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_count = $stmt->fetchColumn();

        $max_allowed = 10;
        $remaining = $max_allowed - $current_count;

        if ($remaining <= 0) {
            set_flash("Gallery limit reached (max 10 photos).", "danger");
        } else {
            $count = 0;
            foreach ($files['name'] as $key => $name) {
                if ($count >= $remaining) break;
                if ($files['error'][$key] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = 'gal_' . $user_id . '_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$key], $upload_dir . $filename)) {
                        $pdo->prepare("INSERT INTO user_gallery (user_id, image_path) VALUES (?, ?)")->execute([$user_id, $filename]);
                        $count++;
                    }
                }
            }
            set_flash("Uploaded $count gallery photos.", "success");
        }
    }
    header("Location: profile.php#tab-gallery");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gallery_photo'])) {
    $photo_id = intval($_POST['photo_id']);
    $stmt = $pdo->prepare("SELECT image_path FROM user_gallery WHERE id = ? AND user_id = ?");
    $stmt->execute([$photo_id, $user_id]);
    $photo = $stmt->fetch();
    if ($photo) {
        $file_path = dirname(__DIR__) . '/assets/images/uploads/gallery/' . $photo['image_path'];
        if (file_exists($file_path)) unlink($file_path);
        $pdo->prepare("DELETE FROM user_gallery WHERE id = ?")->execute([$photo_id]);
        set_flash("Photo removed from gallery.", "info");
    }
    header("Location: profile.php#tab-gallery");
    exit();
}

// Calculate Completion Percentage
$completion = 0;
// Fields to track for completion (Gender aware)
$track_fields = ['full_name', 'bio', 'city', 'education_level', 'profession', 'monthly_income', 'height', 'marital_status', 'religion', 'sect', 'phone', 'dob', 'weight', 'complexion'];

if ($data['gender'] === 'Male') {
    $track_fields = array_merge($track_fields, ['beard_status', 'living_arrangement', 'relocation_willingness']);
} else {
    $track_fields = array_merge($track_fields, ['hijab_preference', 'cooking_skill', 'working_status']);
}

foreach ($track_fields as $f) {
    if (!empty($data[$f]) && $data[$f] !== 'default.jpg')
        $completion++;
}
if ($data['profile_pic'] != 'default.jpg')
    $completion++;

$progress = round(($completion / (count($track_fields) + 1)) * 100);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light min-vh-100 pb-5">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-3">
            <!-- Header Section -->
            <div class="row mb-4 g-3">
                <div class="col-12">
                    <div
                        class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center bg-white p-3 rounded-4 shadow-sm">
                        <div class="mb-2 mb-sm-0">
                            <h4 class="fw-bold mb-0">My Profile</h4>
                            <p class="text-muted small mb-0">Update your details for better matching.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1 d-sm-none">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>
                            <span id="progressTextUI"
                                class="badge <?= $progress > 80 ? 'bg-success' : 'bg-warning' ?> rounded-pill px-3 py-2"><?= $progress ?>%
                                Done</span>
                        </div>
                    </div>
                </div>
            </div>

            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <!-- Left Sidebar Summary -->
                    <div class="col-xl-3 col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 text-center p-3 p-md-4 h-100 mb-4 mb-lg-0">
                            <div class="position-relative d-inline-block mx-auto mb-3">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $data['profile_pic'] ?: 'default.jpg' ?>"
                                    class="rounded-circle border border-4 border-primary p-1 shadow-sm"
                                    id="profilePreview" style="width: 160px; height: 160px; object-fit: cover;">
                                <label for="picUpload"
                                    class="position-absolute bottom-0 end-0 bg-primary text-white p-2 rounded-circle shadow-sm cursor-pointer"
                                    style="width: 40px; height: 40px;">
                                    <i class="bi bi-camera-fill"></i>
                                    <input type="file" name="profile_pic" id="picUpload" class="d-none"
                                        onchange="previewImage(this)">
                                </label>
                            </div>

                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($data['full_name']) ?></h4>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars($data['profession'] ?: 'Profession Not Set') ?></p>

                            <div class="d-grid gap-2 mb-4">
                                <?php if ($data['is_verified']): ?>
                                    <span class="badge bg-soft-success text-success py-2 rounded-3">
                                        <i class="bi bi-patch-check-fill me-1"></i> Verified Profile
                                    </span>
                                <?php elseif (!empty($data['verification_doc'])): ?>
                                    <span
                                        class="badge bg-soft-info text-info py-2 rounded-3 shadow-sm border border-info border-opacity-25 bg-opacity-10"
                                        style="background-color: var(--bs-info-bg-subtle);">
                                        <i class="bi bi-hourglass-split me-1"></i> Verification Pending
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-soft-warning text-warning py-2 rounded-3 mb-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Identity Unverified
                                    </span>
                                    <a href="verify.php" class="btn btn-sm btn-primary rounded-pill fw-bold">
                                        Verify Now
                                    </a>
                                <?php endif; ?>
                            </div>

                            <hr class="my-4 opacity-50">

                            <div class="text-start">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bi bi-calendar-event me-3 text-primary"></i>
                                    <div>
                                        <small class="text-muted d-block">Age</small>
                                        <span
                                            class="fw-bold small"><?= (new DateTime($data['dob']))->diff(new DateTime('today'))->y ?>
                                            Years</span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bi bi-geo-alt me-3 text-primary"></i>
                                    <div>
                                        <small class="text-muted d-block">Location</small>
                                        <span class="fw-bold small"><?= htmlspecialchars($data['city'] ?: 'Not Set') ?>,
                                            <?= $data['country'] ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-award me-3 text-primary"></i>
                                    <div>
                                        <small class="text-muted d-block">Membership</small>
                                        <span
                                            class="fw-bold small text-primary"><?= $_SESSION['plan_name'] ?? 'Gold Member' ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Tabs Section -->
                    <div class="col-xl-9 col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                            <div class="card-header bg-white p-0 border-0">
                                <div class="overflow-auto scrollbar-hidden">
                                    <ul class="nav nav-pills p-2 bg-light m-3 rounded-3 flex-nowrap text-nowrap"
                                        id="profileTabs" style="width: fit-content; min-width: 100%;">
                                        <li class="nav-item flex-fill text-center">
                                            <button class="nav-link active fw-bold px-3" data-bs-toggle="pill"
                                                data-bs-target="#tab-basic" type="button">
                                                <i class="bi bi-person-lines-fill me-1"></i>Basic
                                            </button>
                                        </li>
                                        <li class="nav-item flex-fill text-center">
                                            <button class="nav-link fw-bold px-3" data-bs-toggle="pill"
                                                data-bs-target="#tab-gender" type="button">
                                                <i class="bi bi-gender-amber me-1"></i><?= $data['gender'] ?>
                                            </button>
                                        </li>
                                        <li class="nav-item flex-fill text-center">
                                            <button class="nav-link fw-bold px-3" data-bs-toggle="pill"
                                                data-bs-target="#tab-career" type="button">
                                                <i class="bi bi-briefcase-fill me-1"></i>Career
                                            </button>
                                        </li>
                                        <li class="nav-item flex-fill text-center">
                                            <button class="nav-link fw-bold px-3" data-bs-toggle="pill"
                                                data-bs-target="#tab-lifestyle" type="button">
                                                <i class="bi bi-suit-heart-fill me-1"></i>Persona
                                            </button>
                                        </li>
                                        <li class="nav-item flex-fill text-center">
                                            <button class="nav-link fw-bold px-3" data-bs-toggle="pill"
                                                data-bs-target="#tab-gallery" type="button">
                                                <i class="bi bi-images me-1"></i>Gallery
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card-body p-4 pt-0">
                                <div class="tab-content pt-2">
                                    <!-- Basic Information -->
                                    <div class="tab-pane fade show active" id="tab-basic">
                                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                                            <span class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                                                <i class="bi bi-info-circle-fill"></i>
                                            </span>
                                            Core Details
                                        </h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Full Name</label>
                                                <input type="text" class="form-control bg-light"
                                                    value="<?= htmlspecialchars($data['full_name']) ?>" readonly>
                                                <small class="text-muted" style="font-size:10px;">Contact admin to
                                                    change name</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Gender</label>
                                                <input type="text" class="form-control bg-light"
                                                    value="<?= $data['gender'] ?>" readonly>
                                                <small class="text-muted" style="font-size:10px;">Gender cannot be
                                                    changed</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Date of Birth</label>
                                                <input type="date" name="dob" class="form-control bg-light"
                                                    value="<?= $data['dob'] ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Marital
                                                    Status</label>
                                                <select name="marital_status" class="form-select shadow-none">
                                                    <option value="Single" <?= $data['marital_status'] == 'Single' ? 'selected' : '' ?>>Single</option>
                                                    <option value="Divorced" <?= $data['marital_status'] == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                                                    <option value="Widowed" <?= $data['marital_status'] == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                                    <option value="Separated" <?= $data['marital_status'] == 'Separated' ? 'selected' : '' ?>>Separated</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Religion</label>
                                                <input type="text" name="religion" class="form-control shadow-none"
                                                    placeholder="e.g. Islam"
                                                    value="<?= htmlspecialchars($data['religion'] ?? 'Islam') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Sect</label>
                                                <input type="text" name="sect" class="form-control shadow-none"
                                                    placeholder="e.g. Sunni / Shiah"
                                                    value="<?= htmlspecialchars($data['sect']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">City &
                                                    Location</label>
                                                <input type="text" name="city" class="form-control shadow-none"
                                                    placeholder="e.g. Lahore, Gulberg"
                                                    value="<?= htmlspecialchars($data['city']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Country</label>
                                                <input type="text" name="country" class="form-control shadow-none"
                                                    value="<?= htmlspecialchars($data['country'] ?: 'Pakistan') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Gender Specific Details -->
                                    <div class="tab-pane fade" id="tab-gender">
                                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                                            <span class="bg-purple bg-opacity-10 text-purple p-2 rounded-3 me-3">
                                                <i class="bi bi-stars"></i>
                                            </span>
                                            <?= $data['gender'] ?> Profile Features
                                        </h5>
                                        <div class="row g-3">
                                            <?php if ($data['gender'] === 'Male'): ?>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Beard Status</label>
                                                    <select name="beard_status" class="form-select shadow-none">
                                                        <option value="Yes" <?= $data['beard_status'] == 'Yes' ? 'selected' : '' ?>>Yes (Sunnah)</option>
                                                        <option value="No" <?= $data['beard_status'] == 'No' ? 'selected' : '' ?>>No</option>
                                                        <option value="Trimmed" <?= $data['beard_status'] == 'Trimmed' ? 'selected' : '' ?>>Trimmed</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Living
                                                        Arrangement</label>
                                                    <select name="living_arrangement" class="form-select shadow-none">
                                                        <option value="Own House" <?= $data['living_arrangement'] == 'Own House' ? 'selected' : '' ?>>Own House</option>
                                                        <option value="Rented" <?= $data['living_arrangement'] == 'Rented' ? 'selected' : '' ?>>Rented House</option>
                                                        <option value="Family House" <?= $data['living_arrangement'] == 'Family House' ? 'selected' : '' ?>>With Family</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Responsibility
                                                        Role</label>
                                                    <input type="text" name="responsibility_role"
                                                        class="form-control shadow-none"
                                                        placeholder="e.g. Family Head / Primary Earner"
                                                        value="<?= htmlspecialchars($data['responsibility_role']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Career
                                                        Stability</label>
                                                    <input type="text" name="career_stability"
                                                        class="form-control shadow-none"
                                                        placeholder="e.g. Well Established / Mid Level"
                                                        value="<?= htmlspecialchars($data['career_stability']) ?>">
                                                </div>
                                            <?php else: ?>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Hijab
                                                        Preference</label>
                                                    <select name="hijab_preference" class="form-select shadow-none">
                                                        <option value="Hijab" <?= $data['hijab_preference'] == 'Hijab' ? 'selected' : '' ?>>Hijab</option>
                                                        <option value="Niqab" <?= $data['hijab_preference'] == 'Niqab' ? 'selected' : '' ?>>Niqab</option>
                                                        <option value="None" <?= $data['hijab_preference'] == 'None' ? 'selected' : '' ?>>None</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Cooking Skill
                                                        Level</label>
                                                    <select name="cooking_skill" class="form-select shadow-none">
                                                        <option value="Basic" <?= $data['cooking_skill'] == 'Basic' ? 'selected' : '' ?>>Basic</option>
                                                        <option value="Moderate" <?= $data['cooking_skill'] == 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                                                        <option value="Expert" <?= $data['cooking_skill'] == 'Expert' ? 'selected' : '' ?>>Expert (Chef level)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Working
                                                        Status</label>
                                                    <select name="working_status" class="form-select shadow-none">
                                                        <option value="Working" <?= $data['working_status'] == 'Working' ? 'selected' : '' ?>>Working</option>
                                                        <option value="Not Working" <?= $data['working_status'] == 'Not Working' ? 'selected' : '' ?>>Not Working</option>
                                                        <option value="Planning to Work"
                                                            <?= $data['working_status'] == 'Planning to Work' ? 'selected' : '' ?>>Planning to Work</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Work After
                                                        Marriage</label>
                                                    <select name="working_after_marriage" class="form-select shadow-none">
                                                        <option value="Yes" <?= $data['working_after_marriage'] == 'Yes' ? 'selected' : '' ?>>Yes</option>
                                                        <option value="No" <?= $data['working_after_marriage'] == 'No' ? 'selected' : '' ?>>No</option>
                                                        <option value="Depends"
                                                            <?= $data['working_after_marriage'] == 'Depends' ? 'selected' : '' ?>>Depends on Partner</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Guardian Name</label>
                                                    <input type="text" name="guardian_name" class="form-control shadow-none"
                                                        placeholder="Father or Brother Name"
                                                        value="<?= htmlspecialchars($data['guardian_name']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Guardian
                                                        Contact</label>
                                                    <input type="text" name="guardian_contact"
                                                        class="form-control shadow-none" placeholder="Phone Number"
                                                        value="<?= htmlspecialchars($data['guardian_contact']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label text-muted small fw-bold">Household Skill
                                                        Level</label>
                                                    <select name="household_skill" class="form-select shadow-none">
                                                        <option value="Basic" <?= $data['household_skill'] == 'Basic' ? 'selected' : '' ?>>Basic</option>
                                                        <option value="Moderate" <?= $data['household_skill'] == 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                                                        <option value="Expert" <?= $data['household_skill'] == 'Expert' ? 'selected' : '' ?>>Expert</option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Willingness to
                                                    Relocate</label>
                                                <select name="relocation_willingness" class="form-select shadow-none">
                                                    <option value="Yes" <?= $data['relocation_willingness'] == 'Yes' ? 'selected' : '' ?>>Yes</option>
                                                    <option value="No" <?= $data['relocation_willingness'] == 'No' ? 'selected' : '' ?>>No</option>
                                                    <option value="Maybe" <?= $data['relocation_willingness'] == 'Maybe' ? 'selected' : '' ?>>Maybe</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Career & Education -->
                                    <div class="tab-pane fade" id="tab-career">
                                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                                            <span class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-3">
                                                <i class="bi bi-mortarboard-fill"></i>
                                            </span>
                                            Education & Profession
                                        </h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Education
                                                    Level</label>
                                                <select name="education_level" class="form-select shadow-none">
                                                    <option value="Bachelors" <?= $data['education_level'] == 'Bachelors' ? 'selected' : '' ?>>Bachelors</option>
                                                    <option value="Masters" <?= $data['education_level'] == 'Masters' ? 'selected' : '' ?>>Masters</option>
                                                    <option value="PhD" <?= $data['education_level'] == 'PhD' ? 'selected' : '' ?>>PhD</option>
                                                    <option value="Intermediate"
                                                        <?= $data['education_level'] == 'Intermediate' ? 'selected' : '' ?>>Intermediate</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Degree Title</label>
                                                <input type="text" name="degree_title" class="form-control shadow-none"
                                                    placeholder="e.g. BS Computer Science"
                                                    value="<?= htmlspecialchars($data['degree_title']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Job Title</label>
                                                <input type="text" name="profession" class="form-control shadow-none"
                                                    placeholder="e.g. Software Engineer"
                                                    value="<?= htmlspecialchars($data['profession']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Company
                                                    (Optional)</label>
                                                <input type="text" name="company_name" class="form-control shadow-none"
                                                    placeholder="Current Workplace"
                                                    value="<?= htmlspecialchars($data['company_name']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Employment
                                                    Type</label>
                                                <select name="employment_type" class="form-select shadow-none">
                                                    <?php foreach (['Private', 'Govt', 'Business', 'Student', 'Unemployed'] as $t): ?>
                                                        <option value="<?= $t ?>" <?= $data['employment_type'] == $t ? 'selected' : '' ?>><?= $t ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Monthly Income ($
                                                    Range)</label>
                                                <input type="text" name="monthly_income"
                                                    class="form-control shadow-none" placeholder="e.g. 100k - 150k"
                                                    value="<?= htmlspecialchars($data['monthly_income']) ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Lifestyle & Physical -->
                                    <div class="tab-pane fade" id="tab-lifestyle">
                                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                                            <span class="bg-rose bg-opacity-10 text-rose p-2 rounded-3 me-3">
                                                <i class="bi bi-palette-fill"></i>
                                            </span>
                                            Lifestyle & Habits
                                        </h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Complexion</label>
                                                <select name="complexion" class="form-select shadow-none">
                                                    <?php foreach (['Fair', 'Very Fair', 'Wheatish', 'Dark'] as $c): ?>
                                                        <option value="<?= $c ?>" <?= $data['complexion'] == $c ? 'selected' : '' ?>><?= $c ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Body Type</label>
                                                <select name="body_type" class="form-select shadow-none">
                                                    <?php foreach (['Slim', 'Athletic', 'Average', 'Heavy'] as $bt): ?>
                                                        <option value="<?= $bt ?>" <?= $data['body_type'] == $bt ? 'selected' : '' ?>><?= $bt ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Smoking
                                                    Habits</label>
                                                <select name="smoking" class="form-select shadow-none">
                                                    <option value="No" <?= $data['smoking'] == 'No' ? 'selected' : '' ?>>
                                                        Non-Smoker</option>
                                                    <option value="Yes" <?= $data['smoking'] == 'Yes' ? 'selected' : '' ?>>
                                                        Smoker</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small fw-bold">Drinking
                                                    Habits</label>
                                                <select name="drinking" class="form-select shadow-none">
                                                    <option value="No" <?= $data['drinking'] == 'No' ? 'selected' : '' ?>>
                                                        No</option>
                                                    <option value="Yes" <?= $data['drinking'] == 'Yes' ? 'selected' : '' ?>>Socially / Often</option>
                                                </select>
                                            </div>
                                            <div class="col-12 mt-3">
                                                <label class="form-label text-muted small fw-bold">Physical Disability
                                                    (If any)</label>
                                                <input type="text" name="disability" class="form-control shadow-none"
                                                    placeholder="None"
                                                    value="<?= htmlspecialchars($data['disability']) ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Gallery Tab -->
                                    <div class="tab-pane fade" id="tab-gallery">
                                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                                            <span class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                                                <i class="bi bi-images"></i>
                                            </span>
                                            Photo Gallery (Up to 10)
                                        </h5>
                                        
                                        <?php
                                        $gal_stmt = $pdo->prepare("SELECT * FROM user_gallery WHERE user_id = ? ORDER BY created_at DESC");
                                        $gal_stmt->execute([$user_id]);
                                        $user_gallery = $gal_stmt->fetchAll();
                                        ?>
                                        
                                        <div class="row g-3 mb-4">
                                            <?php foreach ($user_gallery as $photo): ?>
                                                <div class="col-6 col-md-4 col-lg-3 position-relative">
                                                    <div class="ratio ratio-1x1">
                                                        <img src="/online-rishta-system/assets/images/uploads/gallery/<?= htmlspecialchars($photo['image_path']) ?>" 
                                                             class="img-fluid rounded shadow-sm object-fit-cover">
                                                    </div>
                                                    <form method="POST" class="position-absolute top-0 end-0 mt-2 me-3">
                                                        <input type="hidden" name="delete_gallery_photo" value="1">
                                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm rounded-circle shadow" onclick="return confirm('Delete this photo?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (count($user_gallery) < 10): ?>
                                                <div class="col-6 col-md-4 col-lg-3">
                                                    <label for="gal-upload-p" class="d-flex flex-column align-items-center justify-content-center bg-light border-dashed rounded-3 w-100 ratio ratio-1x1" style="cursor: pointer; border: 2px dashed #dee2e6;">
                                                        <div>
                                                            <i class="bi bi-plus-circle fs-2 text-muted d-block text-center"></i>
                                                            <span class="small text-muted fw-bold">Add Photo</span>
                                                        </div>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <form action="profile.php" method="POST" enctype="multipart/form-data" id="galleryForm">
                                            <input type="hidden" name="upload_gallery" value="1">
                                            <input type="file" name="gallery_photos[]" id="gal-upload-p" class="form-control d-none" multiple accept="image/*" onchange="document.getElementById('galleryForm').submit()">
                                            <div class="alert alert-info border-0 rounded-4 p-3 small">
                                                <i class="bi bi-info-circle-fill me-2"></i> 
                                                Upload up to 10 high-quality photos. Profiles with more photos receive <strong>3x more interests</strong>.
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-white p-4 border-top">
                                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow-sm rounded-pill">
                                    <i class="bi bi-save me-2"></i> Save Changes
                                </button>
                                <span class="text-muted small ms-3">Last updated: <?= date('M d, Y') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Live Profile completeness update
    document.addEventListener('DOMContentLoaded', () => {
        const trackedInputs = document.querySelectorAll('input[type="text"], input[type="number"], select, textarea');
        trackedInputs.forEach(input => {
            input.addEventListener('input', calculateProgress);
            input.addEventListener('change', calculateProgress);
        });

        function calculateProgress() {
            let filled = 0;
            const gender = '<?= $data['gender'] ?>';

            // Count specific vital fields inside the form
            const names = ['religion', 'sect', 'city', 'bio', 'phone', 'education_level', 'profession', 'monthly_income', 'height', 'weight', 'marital_status'];

            if (gender === 'Male') {
                names.push('beard_status', 'living_arrangement', 'relocation_willingness');
            } else {
                names.push('hijab_preference', 'cooking_skill', 'working_status');
            }

            names.forEach(name => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el && el.value.trim() !== '') filled++;
            });

            // Add 3 for static fields (Name, DOB, Profile Pic)
            filled += 3;
            const total = names.length + 3;

            let progress = Math.round((filled / total) * 100);
            if (progress > 100) progress = 100;

            document.getElementById('progressBarUI').style.width = progress + '%';
            document.getElementById('progressTextUI').innerText = progress + '% Complete';

            if (progress > 80) {
                document.getElementById('progressTextUI').className = 'badge bg-success rounded-pill px-3';
            } else {
                document.getElementById('progressTextUI').className = 'badge bg-warning rounded-pill px-3';
            }
        }
    });

    // Handle URL Hash for Tabs
    document.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash;
        if (hash) {
            const targetTab = document.querySelector(`[data-bs-target="${hash}"]`);
            if (targetTab) {
                const tabTrigger = new bootstrap.Tab(targetTab);
                tabTrigger.show();
            }
        }
    });
</script>

<style>
    .bg-soft-success {
        background-color: #f0fff4 !important;
    }

    .bg-soft-warning {
        background-color: #fff9e6 !important;
    }

    .nav-pills .nav-link {
        color: #64748b;
        background-color: transparent !important;
        border-radius: 8px !important;
        padding: 12px 10px;
    }

    .nav-pills .nav-link.active {
        color: #0d6efd !important;
        background-color: #fff !important;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }

    .text-rose {
        color: #f43f5e;
    }

    .bg-rose {
        background-color: #fce7f3;
    }

    .transition-all {
        transition: all 0.2s;
    }

    .scrollbar-hidden::-webkit-scrollbar {
        display: none;
    }

    .scrollbar-hidden {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .cursor-pointer {
        cursor: pointer;
    }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>