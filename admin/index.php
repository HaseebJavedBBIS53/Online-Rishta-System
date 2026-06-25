<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

// If already logged in, redirect based on role
if (is_logged_in()) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: /online-rishta-system/user/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and Password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, role, role_id, status, password, failed_login_attempts, lock_until FROM users WHERE email = ? AND role = 'Admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            if ($admin['lock_until'] && new DateTime() < new DateTime($admin['lock_until'])) {
                $diff = (new DateTime())->diff(new DateTime($admin['lock_until']));
                $error = "Account locked globally. Try again in " . $diff->i . " minutes.";
            } else {
                if (password_verify($password, $admin['password'])) {
                    // Reset lock
                    if ($admin['failed_login_attempts'] > 0 || $admin['lock_until']) {
                        $pdo->prepare("UPDATE users SET failed_login_attempts = 0, lock_until = NULL WHERE id = ?")->execute([$admin['id']]);
                    }
                    
                    $_SESSION['user_id'] = $admin['id'];
                    $_SESSION['full_name'] = $admin['full_name'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['role_id'] = $admin['role_id'];

                    // Cache permissions for performance
                    if ($admin['role_id']) {
                        $p_stmt = $pdo->prepare("SELECT p.perm_key FROM role_permissions rp JOIN permissions p ON rp.perm_id = p.id WHERE rp.role_id = ?");
                        $p_stmt->execute([$admin['role_id']]);
                        $_SESSION['perms'] = $p_stmt->fetchAll(PDO::FETCH_COLUMN);
                    } else {
                        $_SESSION['perms'] = [];
                    }
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Invalid password logic for admin
                    $attempts = $admin['failed_login_attempts'] + 1;
                    $lock_until = null;
                    
                    if ($attempts >= 5) {
                        $lock_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $error = "Account locked for 15 minutes due to 5 failed login attempts.";
                    } else {
                        $error = "Invalid credentials. You have " . (5 - $attempts) . " attempts remaining.";
                    }
                    
                    $pdo->prepare("UPDATE users SET failed_login_attempts = ?, lock_until = ? WHERE id = ?")
                        ->execute([$attempts, $lock_until, $admin['id']]);
                }
            }
        } else {
            $error = "Invalid administrator credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - ERishta.PK</title>
    <!-- Poppins Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            margin: 0;
        }
        body::before {
            content: '';
            position: absolute;
            top: -20%; left: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(232,62,140,0.15) 0%, transparent 60%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: absolute;
            bottom: -20%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 60%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            padding: 3.5rem 3rem;
            position: relative;
            z-index: 1;
        }
        .admin-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 24px;
            background: linear-gradient(135deg, #e83e8c 0%, #6366f1 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 25px rgba(232,62,140,0.4);
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
        .btn-admin-login {
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
        .btn-admin-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(232,62,140,0.4);
            color: white;
        }
        
        @media (max-width: 500px) {
            .admin-card { padding: 2.5rem 1.5rem; border-radius: 20px; }
        }
    </style>
</head>
<body>
    <div class="admin-card">
        <div class="text-center mb-4">
            <div class="admin-icon-wrapper">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2 class="fw-bold mb-1" style="color: #1a1a2e; letter-spacing: -0.5px;">Admin Gateway</h2>
            <p class="text-muted" style="font-size: 0.9rem;">ERishta.PK Management System</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 py-2 px-3" style="font-size: 0.9rem;">
                <i class="bi bi-exclamation-octagon-fill me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" autocomplete="off">
            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control" id="floatingInput" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="new-email" required>
                <label for="floatingInput">Admin Email Address</label>
            </div>
            
            <div class="form-floating mb-4">
                <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" autocomplete="new-password" required>
                <label for="floatingPassword">Secure Password</label>
            </div>
            
            <button class="w-100 btn btn-admin-login" type="submit">
                <i class="bi bi-shield-check me-2"></i> Authorized Login
            </button>
        </form>
        
        <div class="text-center mt-4">
            <a href="/online-rishta-system/index.php" class="text-decoration-none" style="color: #6b7280; font-size: 0.9rem; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='#6366f1'" onmouseout="this.style.color='#6b7280'">
                <i class="bi bi-arrow-left me-1"></i> Return to Public Portal
            </a>
        </div>
    </div>
</body>
</html>
