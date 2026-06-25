<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current verification status
$stmt = $pdo->prepare("SELECT is_verified, verification_doc, cnic_front, cnic_back FROM user_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$user_id]);
    $profile = ['is_verified' => 0, 'verification_doc' => null, 'cnic_front' => null, 'cnic_back' => null];
}

$is_verified = $profile['is_verified'];
$has_submitted = (!empty($profile['verification_doc']) && !empty($profile['cnic_front']) && !empty($profile['cnic_back']));

// Handle Multi-step Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_verification') {
    
    $allowed = ['jpg', 'jpeg', 'png'];
    $upload_dir = dirname(__DIR__) . "/assets/images/uploads/";
    $files_to_upload = ['selfie' => 'verification_doc', 'cnic_front' => 'cnic_front', 'cnic_back' => 'cnic_back'];
    $uploaded_names = [];
    $errors = [];

    foreach ($files_to_upload as $key => $db_col) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$key];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                if ($file['size'] <= 5 * 1024 * 1024) {
                    $new_name = "verify_" . $key . "_" . $user_id . "_" . time() . "." . $ext;
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                        $uploaded_names[$db_col] = $new_name;
                    } else {
                        $errors[] = "Failed to upload $key.";
                    }
                } else {
                    $errors[] = "$key size exceeds 5MB.";
                }
            } else {
                $errors[] = "Invalid file type for $key. Use JPG or PNG.";
            }
        } else {
            $errors[] = "$key is required.";
        }
    }

    if (empty($errors)) {
        // Update DB
        $stmt = $pdo->prepare("UPDATE user_profiles SET verification_doc = ?, cnic_front = ?, cnic_back = ?, is_verified = 0 WHERE user_id = ?");
        $stmt->execute([
            $uploaded_names['verification_doc'],
            $uploaded_names['cnic_front'],
            $uploaded_names['cnic_back'],
            $user_id
        ]);
        
        set_flash("Verification documents submitted! Admin will review them shortly.", "success");
        header("Location: verify.php");
        exit();
    } else {
        $error = implode("<br>", $errors);
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .verify-stepper { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
    .verify-stepper::before { content: ''; position: absolute; top: 15px; left: 0; right: 0; height: 2px; background: #e2e8f0; z-index: 1; }
    .step-item { position: relative; z-index: 2; background: #f8fafc; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #e2e8f0; font-weight: bold; font-size: 14px; color: #64748b; }
    .step-item.active { background: #6366f1; border-color: #6366f1; color: white; }
    .step-item.completed { background: #10b981; border-color: #10b981; color: white; }
    .step-label { font-size: 11px; margin-top: 8px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .upload-zone { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 25px; text-align: center; transition: all 0.2s; cursor: pointer; background: #fff; margin-bottom: 15px; }
    .upload-zone:hover { border-color: #6366f1; background: #f8fafc; }
    .upload-zone.filled { border-style: solid; border-color: #10b981; background: #f0fdf4; }
    
    .preview-img { width: 100%; max-height: 150px; object-fit: contain; border-radius: 10px; display: none; margin-top: 10px; }
</style>

<div class="container-fluid bg-light min-vh-100 pb-5">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-shield-check text-primary me-2"></i>Identity Verification</h4>
                <a href="profile.php" class="btn btn-sm btn-white border rounded-pill px-3">Back to Profile</a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    
                    <?php if ($is_verified): ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center">
                            <div class="bg-success py-5">
                                <i class="bi bi-patch-check-fill display-1 text-white"></i>
                            </div>
                            <div class="card-body p-5">
                                <h3 class="fw-bold">Verified Account</h3>
                                <p class="text-muted">Your identity has been successfully verified. You now have the verified badge on your profile.</p>
                                <a href="dashboard.php" class="btn btn-primary rounded-pill px-5 fw-bold">Explore Matches</a>
                            </div>
                        </div>
                    <?php elseif ($has_submitted): ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center">
                            <div class="bg-primary py-5">
                                <i class="bi bi-hourglass-split display-1 text-white"></i>
                            </div>
                            <div class="card-body p-5">
                                <h3 class="fw-bold">Review in Progress</h3>
                                <p class="text-muted">We have received your documents. Our team is currently reviewing your profile for authenticity. This usually takes 12-24 hours.</p>
                                <div class="row g-3 mt-4">
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded-3 bg-light">
                                            <img src="/online-rishta-system/assets/images/uploads/<?= $profile['verification_doc'] ?>" class="img-fluid rounded" style="height:100px; object-fit:cover;">
                                            <div class="small fw-bold mt-2">Self Photo</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded-3 bg-light">
                                            <img src="/online-rishta-system/assets/images/uploads/<?= $profile['cnic_front'] ?>" class="img-fluid rounded" style="height:100px; object-fit:cover;">
                                            <div class="small fw-bold mt-2">CNIC Front</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded-3 bg-light">
                                            <img src="/online-rishta-system/assets/images/uploads/<?= $profile['cnic_back'] ?>" class="img-fluid rounded" style="height:100px; object-fit:cover;">
                                            <div class="small fw-bold mt-2">CNIC Back</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-body p-4 p-md-5">
                                <div class="text-center mb-5">
                                    <h3 class="fw-bold">Get Verified</h3>
                                    <p class="text-muted">Follow these 2 steps to verify your identity and build trust.</p>
                                </div>

                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger rounded-3"><?= $error ?></div>
                                <?php endif; ?>

                                <form action="verify.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="submit_verification">
                                    
                                    <div class="row g-4">
                                        <!-- Step 1 -->
                                        <div class="col-md-6 border-end">
                                            <h5 class="fw-bold mb-4"><span class="badge bg-primary rounded-pill me-2">Step 1</span> Your Photo</h5>
                                            <p class="small text-muted mb-4">Upload a clear selfie or recent photo of yourself for visual verification.</p>
                                            
                                            <div class="upload-zone" onclick="document.getElementById('selfie').click()" id="zone-selfie">
                                                <i class="bi bi-person-bounding-box fs-1 text-muted mb-2 d-block"></i>
                                                <span class="small fw-bold text-muted" id="label-selfie">Upload Self Photo</span>
                                                <img id="prev-selfie" class="preview-img">
                                            </div>
                                            <input type="file" name="selfie" id="selfie" class="d-none" accept="image/*" required onchange="handlePreview(this, 'selfie')">
                                        </div>

                                        <!-- Step 2 -->
                                        <div class="col-md-6">
                                            <h5 class="fw-bold mb-4"><span class="badge bg-primary rounded-pill me-2">Step 2</span> ID Documents</h5>
                                            <p class="small text-muted mb-4">Upload clear photos of your CNIC / Identity Card (Front & Back).</p>
                                            
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <div class="upload-zone p-3" onclick="document.getElementById('cnic_front').click()" id="zone-front">
                                                        <i class="bi bi-card-image fs-2 text-muted mb-1 d-block"></i>
                                                        <span class="small fw-bold text-muted d-block" id="label-front" style="font-size:10px;">CNIC Front</span>
                                                        <img id="prev-front" class="preview-img">
                                                    </div>
                                                    <input type="file" name="cnic_front" id="cnic_front" class="d-none" accept="image/*" required onchange="handlePreview(this, 'front')">
                                                </div>
                                                <div class="col-6">
                                                    <div class="upload-zone p-3" onclick="document.getElementById('cnic_back').click()" id="zone-back">
                                                        <i class="bi bi-card-image fs-2 text-muted mb-1 d-block"></i>
                                                        <span class="small fw-bold text-muted d-block" id="label-back" style="font-size:10px;">CNIC Back</span>
                                                        <img id="prev-back" class="preview-img">
                                                    </div>
                                                    <input type="file" name="cnic_back" id="cnic_back" class="d-none" accept="image/*" required onchange="handlePreview(this, 'back')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5 text-center">
                                        <div class="alert alert-warning d-inline-block py-2 px-4 small rounded-pill border-0 shadow-sm">
                                            <i class="bi bi-shield-lock-fill me-2"></i> Documents are encrypted and only visible to authorized administrators.
                                        </div>
                                        <div class="mt-4">
                                            <button type="submit" class="btn btn-primary btn-lg px-5 py-3 rounded-pill fw-bold shadow">
                                                Submit for Verification
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>
</div>

<script>
function handlePreview(input, type) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const zone = document.getElementById('zone-' + type);
        const prev = document.getElementById('prev-' + type);
        const label = document.getElementById('label-' + type);

        reader.onload = function(e) {
            prev.src = e.target.result;
            prev.style.display = 'block';
            zone.classList.add('filled');
            label.innerText = 'File selected: ' + input.files[0].name.substring(0, 15) + '...';
            // Hide icon
            zone.querySelector('i').style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
