<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Fetch current settings
$stmt = $pdo->prepare("SELECT u.full_name, u.email, u.phone, u.timezone, u.language, s.* 
                       FROM users u 
                       LEFT JOIN user_settings s ON u.id = s.user_id 
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch();

// If settings don't exist, create default
if (!$settings['user_id']) {
    $pdo->prepare("INSERT IGNORE INTO user_settings (user_id) VALUES (?)")->execute([$user_id]);
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_account'])) {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $timezone = sanitize_input($_POST['timezone']);

        $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, timezone = ? WHERE id = ?")
            ->execute([$full_name, $email, $phone, $timezone, $user_id]);

        $_SESSION['full_name'] = $full_name;
        set_flash("Account information updated successfully.", "success");
    } elseif (isset($_POST['update_privacy'])) {
        $visibility = $_POST['profile_visibility'];
        $can_contact = $_POST['can_contact'];

        $pdo->prepare("UPDATE user_settings SET profile_visibility = ?, can_contact = ? WHERE user_id = ?")
            ->execute([$visibility, $can_contact, $user_id]);

        set_flash("Privacy settings updated.", "success");
    } elseif (isset($_POST['update_notifications'])) {
        $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notif = isset($_POST['sms_notifications']) ? 1 : 0;
        $app_notif = isset($_POST['app_notifications']) ? 1 : 0;

        $pdo->prepare("UPDATE user_settings SET email_notifications = ?, sms_notifications = ?, app_notifications = ? WHERE user_id = ?")
            ->execute([$email_notif, $sms_notif, $app_notif, $user_id]);

        set_flash("Notification preferences updated.", "success");
    } elseif (isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (password_verify($current_pass, $user['password'])) {
            if ($new_pass === $confirm_pass) {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
                set_flash("Password changed successfully.", "success");
            } else {
                set_flash("New passwords do not match.", "danger");
            }
        } else {
            set_flash("Incorrect current password.", "danger");
        }
    } elseif (isset($_POST['delete_account'])) {
        $pdo->prepare("UPDATE users SET status = 'Deleted' WHERE id = ?")->execute([$user_id]);
        header("Location: /online-rishta-system/logout.php");
        exit();
    }

    header("Location: settings.php");
    exit();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Account & Privacy Hub</h2>
                <p class="text-muted small">Manage your security, visibility, and notification preferences.</p>
            </div>

            <div class="row g-4">
                <!-- Account Section -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
                        <div class="card-header bg-white py-3 border-bottom-0">
                            <h5 class="fw-bold mb-0 text-primary">General Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="settings.php" method="POST" class="row g-3">
                                <input type="hidden" name="update_account" value="1">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold text-muted">Full Name</label>
                                    <input type="text" name="full_name" class="form-control bg-light border-0 py-2"
                                        value="<?= htmlspecialchars($settings['full_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold text-muted">Email Address</label>
                                    <input type="email" name="email" class="form-control bg-light border-0 py-2"
                                        value="<?= htmlspecialchars($settings['email']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold text-muted">Phone Number</label>
                                    <input type="text" name="phone" class="form-control bg-light border-0 py-2"
                                        value="<?= htmlspecialchars($settings['phone']) ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold text-muted">Timezone</label>
                                    <select name="timezone" class="form-select bg-light border-0 py-2">
                                        <option value="UTC" <?= $settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC /
                                            GMT</option>
                                        <option value="Asia/Karachi" <?= $settings['timezone'] === 'Asia/Karachi' ? 'selected' : '' ?>>Islamabad/Karachi (GMT+5)</option>
                                        <option value="Asia/Dubai" <?= $settings['timezone'] === 'Asia/Dubai' ? 'selected' : '' ?>>Dubai/Abu Dhabi (GMT+4)</option>
                                        <option value="Europe/London" <?= $settings['timezone'] === 'Europe/London' ? 'selected' : '' ?>>London (GMT+0)</option>
                                    </select>
                                </div>
                                <div class="col-12 text-end mt-4">
                                    <button type="submit"
                                        class="btn btn-primary px-4 py-2 fw-bold w-100 w-md-auto rounded-3 shadow-sm">Save
                                        Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                                <div class="card-header bg-white py-3 border-bottom-0">
                                    <h5 class="fw-bold mb-0 text-primary">Privacy Settings</h5>
                                </div>
                                <div class="card-body p-4">
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="update_privacy" value="1">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-muted">Profile
                                                Visibility</label>
                                            <select name="profile_visibility"
                                                class="form-select bg-light border-0 py-2">
                                                <option value="Public" <?= $settings['profile_visibility'] === 'Public' ? 'selected' : '' ?>>Public (Visible to All)</option>
                                                <option value="Premium" <?= $settings['profile_visibility'] === 'Premium' ? 'selected' : '' ?>>Premium Only</option>
                                                <option value="Private" <?= $settings['profile_visibility'] === 'Private' ? 'selected' : '' ?>>Private (Hidden from Search)</option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label small fw-bold text-muted">Who can contact
                                                me?</label>
                                            <select name="can_contact" class="form-select bg-light border-0 py-2">
                                                <option value="All" <?= $settings['can_contact'] === 'All' ? 'selected' : '' ?>>Everyone</option>
                                                <option value="Verified" <?= $settings['can_contact'] === 'Verified' ? 'selected' : '' ?>>Verified Profiles Only</option>
                                                <option value="Premium" <?= $settings['can_contact'] === 'Premium' ? 'selected' : '' ?>>Premium Users Only</option>
                                            </select>
                                        </div>
                                        <button type="submit"
                                            class="btn btn-outline-primary w-100 fw-bold py-2 rounded-3 shadow-sm">Update
                                            Privacy</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                                <div class="card-header bg-white py-3 border-bottom-0">
                                    <h5 class="fw-bold mb-0 text-primary">Notifications</h5>
                                </div>
                                <div class="card-body p-4">
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="update_notifications" value="1">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="email_notifications"
                                                id="notif-email" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold small" for="notif-email">Email
                                                Notifications</label>
                                            <div class="text-muted small" style="font-size: 0.75rem;">New matches,
                                                interests, and views.</div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="sms_notifications"
                                                id="notif-sms" <?= $settings['sms_notifications'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold small" for="notif-sms">SMS
                                                Alerts</label>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Direct alerts for
                                                urgent messages.</div>
                                        </div>
                                        <div class="form-check form-switch mb-4">
                                            <input class="form-check-input" type="checkbox" name="app_notifications"
                                                id="notif-app" <?= $settings['app_notifications'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold small" for="notif-app">App
                                                Notifications</label>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Real-time push
                                                alerts.</div>
                                        </div>
                                        <button type="submit"
                                            class="btn btn-outline-primary w-100 fw-bold py-2 rounded-3 shadow-sm">Update
                                            Notifs</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password Section -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 bg-white sticky-lg-top"
                        style="top: 100px; z-index: 10;">
                        <div class="card-header bg-white py-3 border-bottom-0">
                            <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-shield-lock-fill me-2"></i>Security
                            </h5>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <form action="settings.php" method="POST">
                                <input type="hidden" name="change_password" value="1">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">Current Password</label>
                                    <input type="password" name="current_password"
                                        class="form-control bg-light border-0 py-2" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">New Password</label>
                                    <input type="password" name="new_password"
                                        class="form-control bg-light border-0 py-2" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted">Confirm Password</label>
                                    <input type="password" name="confirm_password"
                                        class="form-control bg-light border-0 py-2" required>
                                </div>
                                <button type="submit"
                                    class="btn btn-danger w-100 fw-bold rounded-pill py-2 shadow-sm">Update
                                    Password</button>
                            </form>

                            <hr class="my-4">

                            <h6 class="fw-bold text-muted small text-uppercase">Danger Zone</h6>
                            <p class="text-muted small">Permanently delete your account and all associated data.</p>
                            <form action="settings.php" method="POST"
                                onsubmit="return confirm('WARNING: Are you sure you want to permanently delete your account? This action cannot be undone and you will be logged out immediately.');">
                                <input type="hidden" name="delete_account" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-bold">Delete My
                                    Account</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>