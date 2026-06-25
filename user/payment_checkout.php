<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();

// Get the plan ID from URL
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

// Fetch plan details
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE plan_id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan || $plan['price'] == 0) {
    set_flash("Invalid plan selected or free plan doesn't require payment.", "warning");
    header("Location: subscription.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = trim($_POST['transaction_id']);
    $payment_method = $_POST['payment_method'];
    
    // Validation
    $errors = [];
    if (empty($transaction_id)) $errors[] = "Transaction ID is required.";
    if (empty($payment_method)) $errors[] = "Payment method is required.";
    
    // Check for duplicate transaction ID
    $checkStmt = $pdo->prepare("SELECT id FROM manual_payments WHERE transaction_id = ?");
    $checkStmt->execute([$transaction_id]);
    if ($checkStmt->fetch()) {
        $errors[] = "This Transaction ID has already been submitted.";
    }

    // File upload handle
    if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Payment proof screenshot is required.";
    } else {
        $file = $_FILES['screenshot'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and PDF files are allowed.";
        }
        if ($file['size'] > $maxSize) {
            $errors[] = "File size must be less than 5MB.";
        }
    }

    if (empty($errors)) {
        // Upload file
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "pay_" . time() . "_" . $user_id . "." . $ext;
        $uploadPath = dirname(__DIR__) . "/assets/images/payments/";
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath . $filename)) {
            // Save to database
            $insertStmt = $pdo->prepare("INSERT INTO manual_payments (user_id, plan_id, payment_method, transaction_id, screenshot, amount, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
            if ($insertStmt->execute([$user_id, $plan_id, $payment_method, $transaction_id, $filename, $plan['price']])) {
                set_flash("Payment request submitted successfully! Waiting for admin approval.", "success");
                header("Location: billing_history.php");
                exit();
            } else {
                $errors[] = "Something went wrong while saving your request.";
            }
        } else {
            $errors[] = "Failed to upload file.";
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .payment-card { border: 2px solid #eaedf2; transition: all 0.3s ease; cursor: pointer; border-radius: 20px; background: #fff; overflow: hidden; position: relative; }
    .payment-card:hover { border-color: #6366f1; transform: translateY(-5px); box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1); }
    .payment-card.active { border-color: #6366f1; background-color: #f8faff; }
    .payment-card.active::after { content: "\F272"; font-family: "bootstrap-icons"; position: absolute; top: 15px; right: 15px; color: #6366f1; font-size: 1.2rem; }
    .method-icon { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; }
    .instructions-step { position: relative; padding-left: 55px; margin-bottom: 30px; }
    .step-number { position: absolute; left: 0; top: 0; width: 40px; height: 40px; background: #6366f1; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); }
    .plan-summary { background: #fff; border-radius: 25px; padding: 30px; border: 1px solid rgba(0,0,0,0.05); }
    .upload-area { border: 2px dashed #d1d5db; border-radius: 20px; padding: 40px; text-align: center; transition: all 0.3s; cursor: pointer; background: #f9fafb; }
    .upload-area:hover { border-color: #6366f1; background: #f5f7ff; }
    .file-input-label { display: block; cursor: pointer; }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="subscription.php">Membership</a></li>
                        <li class="breadcrumb-item active">Checkout</li>
                    </ol>
                </nav>
                <h2 class="h4 fw-bold mb-0">Secure Checkout</h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm border-0 rounded-4 mb-4 p-3 ps-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <h6 class="mb-0 fw-bold">Please correct the following errors:</h6>
                    </div>
                    <ul class="mb-0 small">
                        <?php foreach($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Plan Summary -->
                <div class="col-lg-4 order-lg-2">
                    <div class="plan-summary shadow-sm sticky-top" style="top: 100px;">
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3 small fw-bold text-uppercase ls-1">Your Order</span>
                        <h4 class="fw-bold mb-4">Order Summary</h4>
                        
                        <div class="d-flex justify-content-between mb-3 p-3 bg-light rounded-4">
                            <span class="text-muted">Plan</span>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($plan['plan_name']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-4 px-3">
                            <span class="text-muted">Duration</span>
                            <span class="fw-bold"><?= ($plan['duration_months'] == 0) ? 'Instant Boost' : $plan['duration_months'] . ' Months' ?></span>
                        </div>
                        
                        <hr class="my-4 opacity-5">
                        
                        <div class="d-flex justify-content-between align-items-center px-2">
                            <span class="h6 fw-bold mb-0">Payable Amount</span>
                            <span class="h3 fw-bold text-primary mb-0">Rs. <?= number_format($plan['price'], 0) ?></span>
                        </div>

                        <div class="mt-4 p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-10 d-flex gap-3">
                            <i class="bi bi-shield-check fs-2 text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-success">Manual Verification</h6>
                                <p class="small text-muted mb-0">Your request will be prioritized by our finance team.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="col-lg-8 order-lg-1">
                    <form action="" method="POST" enctype="multipart/form-data" id="checkoutForm">
                        <!-- Step 1: Method -->
                        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-3">
                                <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">1</span>
                                Choose Payment Method
                            </h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="payment-card p-4 d-flex flex-column align-items-center text-center w-100 h-100" for="jazzcash">
                                        <input type="radio" name="payment_method" value="JazzCash" id="jazzcash" class="d-none" required>
                                        <img src="https://www.jazzcash.com.pk/jazzcash-logos-external/JC-Eng-Black.png" alt="JazzCash" class="method-icon mb-3 shadow-sm" onerror="this.src='/online-rishta-system/assets/images/jazzcash.png'">
                                        <span class="fw-bold">JazzCash</span>
                                        <span class="text-primary small fw-bold mt-1">M. Haseeb Javed</span>
                                        <span class="text-muted small font-monospace">0327-0867172</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="payment-card p-4 d-flex flex-column align-items-center text-center w-100 h-100" for="easypaisa">
                                        <input type="radio" name="payment_method" value="EasyPaisa" id="easypaisa" class="d-none">
                                        <img src="https://crystalpng.com/wp-content/uploads/2024/10/Easypaisa-logo.png" alt="EasyPaisa" class="method-icon mb-3 shadow-sm" onerror="this.src='/online-rishta-system/assets/images/easypaisa.png'">
                                        <span class="fw-bold">EasyPaisa</span>
                                        <span class="text-primary small fw-bold mt-1">M. Haseeb Javed</span>
                                        <span class="text-muted small font-monospace">0317-6914147</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="payment-card p-4 d-flex flex-column align-items-center text-center w-100 h-100" for="bank">
                                        <input type="radio" name="payment_method" value="Bank Transfer" id="bank" class="d-none">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle mb-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                                            <i class="bi bi-bank fs-3"></i>
                                        </div>
                                        <span class="fw-bold">Bank Transfer</span>
                                        <span class="text-primary small fw-bold mt-1">M. Haseeb Javed</span>
                                        <span class="text-muted small font-monospace">2696-351939813</span>
                                    </label>
                                </div>
                            </div>

                            <div id="payment-details" class="alert alert-primary bg-opacity-10 border-0 rounded-4 mb-0 d-none p-4">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="bg-primary text-white rounded-circle p-2 d-flex">
                                        <i class="bi bi-info-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Transfer Instructions</h6>
                                        <div class="small" id="details-text"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Proof -->
                        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4">
                            <h5 class="fw-bold mb-4 d-flex align-items-center gap-3">
                                <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">2</span>
                                Submission Details
                            </h5>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase ls-1">Transaction ID / Reference Number</label>
                                <input type="text" name="transaction_id" class="form-control form-control-lg rounded-4 border-0 bg-light px-4" placeholder="e.g. 123456789012" required>
                                <p class="small text-muted mt-2 mb-0 ms-1"><i class="bi bi-lightbulb me-1"></i> Check the SMS or App Receipt for a unique digit ID.</p>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase ls-1">Upload Receipt Screenshot</label>
                                <label for="screenshot" class="upload-area w-100" id="dropZone">
                                    <input type="file" name="screenshot" id="screenshot" class="d-none" accept="image/*,application/pdf" required>
                                    <div id="preview-info">
                                        <i class="bi bi-cloud-arrow-up-fill display-3 text-primary mb-3 d-inline-block"></i>
                                        <h6 class="fw-bold">Click or Drag & Drop</h6>
                                        <p class="text-muted small mb-0">Supports JPG, PNG or PDF (Max 5MB)</p>
                                    </div>
                                    <div id="file-chosen" class="d-none">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <i class="bi bi-file-earmark-check-fill text-success fs-3"></i>
                                            <span class="fw-bold" id="filename-display">file_name.jpg</span>
                                            <button type="button" class="btn btn-sm btn-link text-danger" onclick="resetFile()"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="p-4 bg-light rounded-4">
                                <h6 class="fw-bold mb-3 small text-uppercase ls-1 text-primary">Instructions</h6>
                                <div class="instructions-step">
                                    <div class="step-number">1</div>
                                    <p class="small mb-0">Send exactly <span class="fw-bold">Rs. <?= number_format($plan['price'], 0) ?></span> to the chosen account.</p>
                                </div>
                                <div class="instructions-step">
                                    <div class="step-number">2</div>
                                    <p class="small mb-0">Capture a clear screenshot showing the <span class="fw-bold">Status: Success</span> and <span class="fw-bold">Transaction ID</span>.</p>
                                </div>
                                <div class="instructions-step mb-0">
                                    <div class="step-number">3</div>
                                    <p class="small mb-0">Submit this form. Our team will verify and activate your plan within 15-30 minutes.</p>
                                </div>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-lg hover-lift">
                                    <i class="bi bi-check2-circle-fill me-2"></i> Confirm Submission
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const radioBtns = document.querySelectorAll('input[name="payment_method"]');
        const cards = document.querySelectorAll('.payment-card');
        const detailAlert = document.getElementById('payment-details');
        const detailText = document.getElementById('details-text');
        const fileInput = document.getElementById('screenshot');
        const previewInfo = document.getElementById('preview-info');
        const fileChosen = document.getElementById('file-chosen');
        const filenameDisplay = document.getElementById('filename-display');

        const details = {
            'JazzCash': '<div class="row g-2 mt-2"><div class="col-6">Account Name:</div><div class="col-6 fw-bold">Muhammad Haseeb Javed</div><div class="col-6">Number:</div><div class="col-6 fw-bold">0327-0867172</div></div>',
            'EasyPaisa': '<div class="row g-2 mt-2"><div class="col-6">Account Name:</div><div class="col-6 fw-bold">Muhammad Haseeb Javed</div><div class="col-6">Number:</div><div class="col-6 fw-bold">0317-6914147</div></div>',
            'Bank Transfer': '<div class="row g-2 mt-2"><div class="col-6">Account Name:</div><div class="col-6 fw-bold">Muhammad Haseeb Javed</div><div class="col-6">Bank:</div><div class="col-6 fw-bold">Allied Bank / Meezan</div><div class="col-6">Account No:</div><div class="col-6 fw-bold">2696-351939813</div></div>'
        };

        radioBtns.forEach(radio => {
            radio.addEventListener('change', function() {
                cards.forEach(c => c.classList.remove('active'));
                this.parentElement.classList.add('active');
                detailAlert.classList.remove('d-none');
                detailText.innerHTML = details[this.value];
            });
        });

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                previewInfo.classList.add('d-none');
                fileChosen.classList.remove('d-none');
                filenameDisplay.textContent = this.files[0].name;
            }
        });
    });

    function resetFile() {
        const fileInput = document.getElementById('screenshot');
        const previewInfo = document.getElementById('preview-info');
        const fileChosen = document.getElementById('file-chosen');
        fileInput.value = '';
        previewInfo.classList.remove('d-none');
        fileChosen.classList.add('d-none');
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>