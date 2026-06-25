<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    header("Location: user/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and Password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, role, role_id, status, password, failed_login_attempts, lock_until FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['status'] !== 'Active') {
                if ($user['status'] === 'Pending Approval') {
                    $error = "Your account is currently under review by admin due to duplicate email/phone. Please check back later.";
                } else {
                    $error = "This account is " . strtolower($user['status']) . ". Please contact support.";
                }
            } else {
                // Check if locked out
                if ($user['lock_until'] && new DateTime() < new DateTime($user['lock_until'])) {
                    $diff = (new DateTime())->diff(new DateTime($user['lock_until']));
                    $error = "Account locked due to too many failed attempts. Try again in " . $diff->i . " minutes.";
                } else {
                    // Check password
                    if (password_verify($password, $user['password'])) {
                        // Reset failed attempts
                        if ($user['failed_login_attempts'] > 0 || $user['lock_until']) {
                            $pdo->prepare("UPDATE users SET failed_login_attempts = 0, lock_until = NULL WHERE id = ?")->execute([$user['id']]);
                        }
                        
                        // Set Session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['role_id'] = $user['role_id'];
                        
                        // Cache permissions for performance
                        if ($user['role_id']) {
                            $p_stmt = $pdo->prepare("SELECT p.perm_key FROM role_permissions rp JOIN permissions p ON rp.perm_id = p.id WHERE rp.role_id = ?");
                            $p_stmt->execute([$user['role_id']]);
                            $_SESSION['perms'] = $p_stmt->fetchAll(PDO::FETCH_COLUMN);
                        } else {
                            $_SESSION['perms'] = [];
                        }
                        
                        set_flash("Welcome back, " . explode(' ', $user['full_name'])[0] . "!");
                        
                        if ($user['role'] === 'Admin') {
                            header("Location: /online-rishta-system/admin/dashboard.php");
                        } else {
                            header("Location: /online-rishta-system/user/dashboard.php");
                        }
                        exit();
                    } else {
                        // Invalid password
                        $attempts = $user['failed_login_attempts'] + 1;
                        $lock_until = null;
                        
                        if ($attempts >= 5) {
                            $lock_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                            $error = "Account locked for 15 minutes due to 5 failed login attempts.";
                        } else {
                            $error = "Invalid email or password. You have " . (5 - $attempts) . " attempts remaining.";
                        }
                        
                        $pdo->prepare("UPDATE users SET failed_login_attempts = ?, lock_until = ? WHERE id = ?")
                            ->execute([$attempts, $lock_until, $user['id']]);
                    }
                }
            }
        } else {
            // Setup generic error to prevent email enumeration, but for simplicity here we just say invalid
            $error = "Invalid email or password.";
        }
    }
}
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
/* ================================================
   LOGIN PAGE — PREMIUM STYLES
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
    transform: scaleX(-1); /* mirror image for variety */
}
.auth-brand-content {
    position: relative;
    z-index: 2;
}

/* Right Panel - Form */
.auth-form-panel {
    padding: 4rem 3.5rem;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: center;
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
.form-floating > label {
    padding-left: 1rem;
    color: #6b7280;
}
/* Fix Browser Autofill Background */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
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
    .auth-form-panel { padding: 3rem 1.5rem; }
}
</style>

<div class="auth-page-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">
                <div class="auth-card">
                    <div class="row g-0">
                        <!-- Left Branding Panel -->
                        <div class="col-lg-5 auth-brand-panel d-none d-lg-flex">
                            <div class="auth-brand-content text-center">
                                <div style="width:70px;height:70px;background:linear-gradient(135deg,#e83e8c,#6366f1);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2rem;color:white;margin:0 auto 24px;box-shadow:0 10px 25px rgba(232,62,140,0.4);">
                                    <i class="bi bi-heart-fill"></i>
                                </div>
                                <h2 class="display-6 fw-bold mb-3" style="letter-spacing:-1px;">Welcome Back!</h2>
                                <p style="font-size:1.05rem; color:rgba(255,255,255,0.7); line-height:1.6; padding:0 10px;">Log in to continue your search for the perfect match.</p>
                                
                                <div class="mt-5 pt-4 border-top border-light border-opacity-25 text-start">
                                    <div class="d-flex align-items-center gap-3 justify-content-center">
                                        <i class="bi bi-shield-check" style="font-size:2rem; color:#10b981;"></i>
                                        <div style="font-size:0.85rem; color:rgba(255,255,255,0.8);">
                                            <strong>Safe & Secure</strong><br>Your data is protected with enterprise-grade encryption.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Form Panel -->
                        <div class="col-lg-7 auth-form-panel">
                            <div class="text-center mb-4">
                                <h3 class="fw-bold text-dark mb-2">Login to Your Account</h3>
                                <p class="text-muted">Enter your credentials below to access your dashboard.</p>
                            </div>
                            
                            <?php if(!empty($error)): ?>
                                <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div>
                            <?php endif; ?>

                            <form action="login.php" method="POST" autocomplete="off">
                                <div class="form-floating mb-3">
                                    <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="new-email" required>
                                    <label for="email">Email Address</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" autocomplete="new-password" required>
                                    <label for="password">Password</label>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-4 px-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" style="cursor:pointer; border-color:#d1d5db;">
                                        <label class="form-check-label text-muted" for="remember" style="font-size:0.9rem; cursor:pointer;">
                                            Remember me
                                        </label>
                                    </div>
                                    <a href="forgot_password.php" class="text-decoration-none fw-bold small" style="color:#6366f1;">Forgot Password?</a>
                                </div>

                                <button type="submit" class="btn btn-auth-submit w-100"><i class="bi bi-box-arrow-in-right me-2"></i> Secure Login</button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <span class="text-muted" style="font-size:0.95rem;">Don't have an account?</span> 
                                <a href="register.php" class="text-decoration-none fw-bold" style="color:#e83e8c;">Sign Up Here</a>
                            </div>
                            
                            <hr class="my-4" style="border-color:#e5e7eb;">
                            
                            <div class="text-center">
                                <p class="small text-muted mb-0">Staff Member? <a href="admin/index.php" class="text-decoration-none fw-bold" style="color:#1a1a2e;"><i class="bi bi-shield-lock me-1"></i> Admin Portal</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
