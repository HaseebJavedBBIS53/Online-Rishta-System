<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    header("Location: user/dashboard.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $dob = sanitize_input($_POST['dob'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;

    // Validation PHP
    if (empty($full_name) || !preg_match("/^[a-zA-Z\s]+$/", $full_name) || str_word_count($full_name) < 2) {
        $errors['full_name'] = "Please enter your full name using letters only (min 2 words).";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    }

    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    } elseif (!preg_match("/^[0-9]{11}$/", $phone)) {
        $errors['phone'] = "Phone number must contain exactly 11 digits.";
    }

    if (empty($gender)) {
        $errors['gender'] = "Please select your gender.";
    }

    if (empty($dob)) {
        $errors['dob'] = "Date of Birth is required.";
    } else {
        $bday = new DateTime($dob);
        $today = new DateTime('today');
        $age = $bday->diff($today)->y;
        if ($age < 18) {
            $errors['dob'] = "You must be at least 18 years old to register.";
        }
    }

    if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password)) {
        $errors['password'] = "Password must be at least 8 chars with upper and lowercase letters.";
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (!$terms) {
        $errors['terms'] = "You must agree to the Terms & Conditions.";
    }

    // Check unique email and phone
    $is_duplicate = false;
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = "This email address is already registered.";
        }
    }
    if (empty($errors['phone'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $is_duplicate = true;
        }
    }

    if (empty($errors)) {
        // Auto-capitalize first letter of each word
        $full_name = ucwords(strtolower($full_name));
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $status = $is_duplicate ? 'Pending Approval' : 'Active';
        
        // Find the Free Plan ID dynamically to avoid FK errors
        $stmt = $pdo->query("SELECT plan_id FROM subscriptions WHERE plan_type = 'Free' LIMIT 1");
        $default_plan_id = $stmt->fetchColumn() ?: 1; // Fallback to 1 if not found
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, gender, dob, password, role, plan_id, status) VALUES (?, ?, ?, ?, ?, ?, 'User', ?, ?)");
            
            if ($stmt->execute([$full_name, $email, $phone, $gender, $dob, $hashed_password, $default_plan_id, $status])) {
                if ($is_duplicate) {
                    set_flash("Registration submitted! Since this email or phone is already in our system, your account is under review. Please wait for admin approval to login.", "warning");
                    header("Location: login.php");
                } else {
                    set_flash("Registration successful! Your account is active. Please login.", "success");
                    header("Location: login.php");
                }
                exit();
            } else {
                $errors['general'] = "An error occurred during registration. Please try again.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                $errors['email'] = "This email address is already registered.";
            } else {
                $errors['general'] = "A database error occurred: " . $e->getMessage();
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
/* ================================================
   REGISTER PAGE — PREMIUM STYLES
================================================ */
.auth-page-wrapper {
    min-height: 90vh;
    background: linear-gradient(135deg, #f8f9fc 0%, #eef2ff 100%);
    display: flex;
    align-items: center;
    padding: 3rem 0;
    position: relative;
    overflow: hidden;
}
.auth-page-wrapper::before {
    content: '';
    position: absolute;
    top: -20%; left: -10%;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(232,62,140,0.08) 0%, transparent 70%);
    border-radius: 50%;
    z-index: 0;
}
.auth-page-wrapper::after {
    content: '';
    position: absolute;
    bottom: -20%; right: -10%;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);
    border-radius: 50%;
    z-index: 0;
}

.auth-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 30px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.05);
    overflow: hidden;
    position: relative;
    z-index: 1;
}

/* Left Panel - Branding */
.auth-brand-panel {
    background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
    padding: 3.5rem;
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
    min-height: 100%;
}
.auth-brand-panel::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url('https://images.unsplash.com/photo-1543854589-9430c0cabebe?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80') center/cover;
    opacity: 0.15;
    mix-blend-mode: overlay;
}
.auth-brand-content {
    position: relative;
    z-index: 2;
}
.auth-badge {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 100px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2rem;
}
.auth-feature-list {
    list-style: none;
    padding: 0;
    margin: 2rem 0 0 0;
}
.auth-feature-list li {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
    font-size: 0.95rem;
    color: rgba(255,255,255,0.8);
}
.auth-feature-icon {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: rgba(232,62,140,0.2);
    color: #fd7ba4;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
}

/* Right Panel - Form */
.auth-form-panel {
    padding: 3.5rem;
    background: #ffffff;
}
.form-floating > .form-control {
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    font-size: 0.95rem;
    transition: all 0.2s;
}
.form-floating > .form-control:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.form-floating > .form-select {
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    font-size: 0.95rem;
    transition: all 0.2s;
}
.form-floating > .form-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.form-floating > label {
    padding-left: 1rem;
    color: #6b7280;
}
/* Fix Browser Autofill Background */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active,
select:-webkit-autofill {
    -webkit-box-shadow: 0 0 0 30px white inset !important;
    -webkit-text-fill-color: #1a1a2e !important;
    transition: background-color 5000s ease-in-out 0s;
}
.btn-auth-submit {
    background: linear-gradient(135deg, #e83e8c 0%, #6366f1 100%);
    color: white;
    border: none;
    border-radius: 14px;
    padding: 14px;
    font-weight: 700;
    font-size: 1.05rem;
    transition: all 0.3s;
    margin-top: 1rem;
    box-shadow: 0 8px 25px rgba(232,62,140,0.3);
}
.btn-auth-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(232,62,140,0.4);
    color: white;
}

@media (max-width: 991px) {
    .auth-brand-panel { display: none !important; }
    .auth-form-panel { padding: 2.5rem 1.5rem; }
}
</style>

<div class="auth-page-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="auth-card">
                    <div class="row g-0">
                        <!-- Left Branding Panel -->
                        <div class="col-lg-5 auth-brand-panel d-none d-lg-flex">
                            <div class="auth-brand-content">
                                <div class="auth-badge">
                                    <i class="bi bi-star-fill text-warning"></i> #1 Trusted Matrimony
                                </div>
                                <h2 class="display-5 fw-bold mb-4" style="letter-spacing:-1px;">Start Your <br>Beautiful Journey.</h2>
                                <p style="font-size:1.05rem; color:rgba(255,255,255,0.7); line-height:1.6;">Join thousands of Pakistani families who found their perfect match on ERishta.PK.</p>
                                
                                <ul class="auth-feature-list">
                                    <li>
                                        <div class="auth-feature-icon"><i class="bi bi-shield-check"></i></div>
                                        100% Verified Profiles
                                    </li>
                                    <li>
                                        <div class="auth-feature-icon"><i class="bi bi-cpu"></i></div>
                                        Smart AI Compatibility Matching
                                    </li>
                                    <li>
                                        <div class="auth-feature-icon"><i class="bi bi-eye-slash"></i></div>
                                        Complete Privacy & Photo Controls
                                    </li>
                                    <li>
                                        <div class="auth-feature-icon"><i class="bi bi-chat-heart"></i></div>
                                        Secure End-to-End Messaging
                                    </li>
                                </ul>
                                
                                <div class="mt-5 pt-4 border-top border-light border-opacity-25">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-flex position-relative">
                                            <img src="https://randomuser.me/api/portraits/women/44.jpg" width="40" height="40" class="rounded-circle border border-2 border-white position-relative z-3">
                                            <img src="https://randomuser.me/api/portraits/men/32.jpg" width="40" height="40" class="rounded-circle border border-2 border-white position-relative z-2 ms-n2" style="margin-left:-15px;">
                                            <img src="https://randomuser.me/api/portraits/women/68.jpg" width="40" height="40" class="rounded-circle border border-2 border-white position-relative z-1 ms-n2" style="margin-left:-15px;">
                                        </div>
                                        <div style="font-size:0.85rem; color:rgba(255,255,255,0.8);">
                                            Over <strong>50,000+</strong> successful matches
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Form Panel -->
                        <div class="col-lg-7 auth-form-panel">
                            <div class="text-center mb-4">
                                <h3 class="fw-bold text-dark mb-2">Create an Account</h3>
                                <p class="text-muted">It takes only 2 minutes to register and it's completely free.</p>
                            </div>
                            
                            <?php if(isset($errors['general'])): ?>
                                <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $errors['general'] ?></div>
                            <?php endif; ?>

                            <form action="register.php" method="POST" id="registerForm" autocomplete="off">
                                <div class="row g-3">
                                    <!-- Full Name -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" placeholder="Full Name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" pattern="[A-Za-z\s]+" title="Letters only (A-Z, a-z). No numbers or special characters." autocomplete="off" required>
                                            <label for="full_name">Full Name <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['full_name'] ?? 'Please enter letters only.' ?></div>
                                        </div>
                                    </div>
                                    <!-- Email -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" placeholder="Email Address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="new-email" required>
                                            <label for="email">Email Address <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['email'] ?? 'Please enter a valid email address.' ?></div>
                                        </div>
                                    </div>
                                    <!-- Phone -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" placeholder="Phone Number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" pattern="[0-9]{11}" title="Must contain exactly 11 digits" autocomplete="off" required>
                                            <label for="phone">Phone Number <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['phone'] ?? 'Phone number must contain exactly 11 digits.' ?></div>
                                        </div>
                                    </div>
                                    <!-- Gender -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select name="gender" class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" id="gender" required>
                                                <option value="" disabled selected>Select Gender</option>
                                                <option value="Male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                                <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                                                <option value="Prefer not to say" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Prefer not to say') ? 'selected' : '' ?>>Prefer not to say</option>
                                            </select>
                                            <label for="gender">Gender <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['gender'] ?? '' ?></div>
                                        </div>
                                    </div>
                                    <!-- Date of Birth -->
                                    <div class="col-md-12">
                                        <div class="form-floating">
                                            <input type="date" name="dob" class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>" id="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                                            <label for="dob">Date of Birth <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['dob'] ?? '' ?></div>
                                        </div>
                                    </div>
                                    <!-- Password -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" placeholder="Password" required autocomplete="new-password" onpaste="return false;">
                                            <label for="password">Password <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['password'] ?? '' ?></div>
                                        </div>
                                    </div>
                                    <!-- Confirm Password -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" placeholder="Confirm Password" required autocomplete="new-password" onpaste="return false;">
                                            <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                                            <div class="invalid-feedback px-2"><?= $errors['confirm_password'] ?? '' ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-check mt-4 mb-3 ps-4">
                                    <input class="form-check-input <?= isset($errors['terms']) ? 'is-invalid' : '' ?>" type="checkbox" name="terms" id="terms" required style="width:1.2em; height:1.2em; border-color:#d1d5db; margin-top:0.1em; cursor:pointer;">
                                    <label class="form-check-label text-muted ms-2" for="terms" style="font-size:0.9rem; cursor:pointer;">
                                        I agree to the <a href="#" class="text-decoration-none fw-bold" style="color:#6366f1;">Terms & Conditions</a> and <a href="#" class="text-decoration-none fw-bold" style="color:#6366f1;">Privacy Policy</a>.
                                    </label>
                                    <div class="invalid-feedback"><?= $errors['terms'] ?? '' ?></div>
                                </div>

                                <button type="submit" class="btn btn-auth-submit w-100"><i class="bi bi-person-plus-fill me-2"></i> Create Free Account</button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <span class="text-muted" style="font-size:0.95rem;">Already have an account?</span> 
                                <a href="login.php" class="text-decoration-none fw-bold" style="color:#e83e8c;">Sign In</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Prevent copy paste in password fields
document.querySelectorAll('input[type=password]').forEach(input => {
    input.addEventListener('paste', e => e.preventDefault());
    input.addEventListener('copy', e => e.preventDefault());
});

document.getElementById('registerForm').addEventListener('submit', function(e) {
    let name = document.querySelector('input[name="full_name"]').value;
    if(name.split(' ').length < 2 || !/^[a-zA-Z\s]+$/.test(name)) {
        e.preventDefault();
        alert('Name must contain only letters and be at least 2 words.');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
