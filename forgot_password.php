<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$step = 1;
$error = '';
$success = '';
$user_id_to_reset = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_account'])) {
        $phone = sanitize_input($_POST['phone']);
        $dob = sanitize_input($_POST['dob']);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND dob = ?");
        $stmt->execute([$phone, $dob]);
        $user = $stmt->fetch();

        if ($user) {
            $step = 2;
            $user_id_to_reset = $user['id'];
        } else {
            $error = "Account not found with these details.";
        }
    } elseif (isset($_POST['reset_password'])) {
        $uid = intval($_POST['uid']);
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (strlen($new_pass) < 6) {
            $error = "Password must be at least 6 characters.";
            $step = 2;
            $user_id_to_reset = $uid;
        } elseif ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match.";
            $step = 2;
            $user_id_to_reset = $uid;
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $uid]);
            $success = "Password reset successfully! You can now <a href='login.php'>Login</a>.";
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Online Rishta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .btn-primary {
            background: #764ba2;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #667eea;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-dark">Reset Password</h2>
            <p class="text-muted">Recover your account access</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-phone"></i></span>
                        <input type="text" name="phone" class="form-control border-start-0" placeholder="Enter your registered number" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Date of Birth</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="dob" class="form-control border-start-0" required>
                    </div>
                    <div class="form-text mt-2 small">We verify your DOB for security purposes.</div>
                </div>
                <button type="submit" name="find_account" class="btn btn-primary w-100 shadow-sm mb-3">Find Account</button>
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none small fw-bold text-muted">Back to Login</a>
                </div>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="POST">
                <input type="hidden" name="uid" value="<?= $user_id_to_reset ?>">
                <div class="mb-3">
                    <label class="form-label small fw-bold">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary w-100 shadow-sm mb-3">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
