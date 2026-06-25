<?php
// config/functions.php

/**
 * Sanitize input to prevent XSS and SQL injection.
 * Note: PDO already prevents SQL injection via prepared statements,
 * but this is useful for output and general sanitation.
 */
function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Check if the user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

/**
 * Require login for a page, redirect otherwise
 */
function require_login()
{
    if (!is_logged_in()) {
        header("Location: /online-rishta-system/login.php");
        exit();
    }
    check_membership_expiry();
}

/**
 * Check if the current user's membership has expired and downgrade if necessary.
 */
function check_membership_expiry()
{
    global $pdo;
    if (!isset($_SESSION['user_id']))
        return;

    // We only check regular users
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin')
        return;

    $user_id = $_SESSION['user_id'];

    // Check cache to avoid DB hit on every page load (once per 5 minutes)
    if (isset($_SESSION['last_expiry_check']) && (time() - $_SESSION['last_expiry_check'] < 300)) {
        return;
    }

    $stmt = $pdo->prepare("SELECT plan_id, expiry_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Sync plan_id in session
        $_SESSION['plan_id'] = $user['plan_id'];

        if ($user['plan_id'] != 1 && $user['expiry_date']) {
            if (strtotime($user['expiry_date']) < time()) {
                // Membership expired
                $pdo->prepare("UPDATE users SET plan_id = 1, expiry_date = NULL WHERE id = ?")->execute([$user_id]);
                $pdo->prepare("UPDATE user_subscriptions SET status = 'Expired' WHERE user_id = ? AND status = 'Active'")->execute([$user_id]);

                $_SESSION['plan_id'] = 1;
                set_flash("Your premium membership has expired and your account has been reverted to the Free plan.", "warning");
            }
        }
    }

    $_SESSION['last_expiry_check'] = time();
}

/**
 * Require admin access, redirect otherwise
 */
function require_admin()
{
    require_login();
    if ($_SESSION['role'] !== 'Admin') {
        header("Location: /online-rishta-system/login.php");
        exit();
    }
}

/**
 * RBAC: Log security events (Audit)
 */
function log_security_event($action, $resource, $status = 'Allowed')
{
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, resource, ip_address, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $resource, $ip, $status]);
}

/**
 * RBAC: Check if user has specific permission (Live Sync)
 */
function has_permission($perm_key)
{
    global $pdo;
    static $request_cache = []; // Per-request cache to prevent redundant DB calls

    if (!isset($_SESSION['user_id']))
        return false;

    // Super Admin Bypass (role_id 1)
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)
        return true;

    // Check per-request cache
    if (isset($request_cache[$perm_key]))
        return $request_cache[$perm_key];

    // Query DB for real-time accuracy (fixes "logout required" issue)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp 
                           JOIN permissions p ON rp.perm_id = p.id 
                           WHERE rp.role_id = ? AND p.perm_key = ?");
    $stmt->execute([$_SESSION['role_id'] ?? 0, $perm_key]);
    $has = $stmt->fetchColumn() > 0;

    $request_cache[$perm_key] = $has;
    return $has;
}

/**
 * RBAC: Require specific permission to access page (With Auditing)
 */
function require_permission($perm_key)
{
    require_admin(); // Must be staff first

    $resource = basename($_SERVER['PHP_SELF']);

    if (!has_permission($perm_key)) {
        log_security_event('Unauthorized Page Access', $resource, 'Denied');

        // Show 403 Forbidden for clean security
        http_response_code(403);
        set_flash("CRITICAL: Access Denied. This unauthorized attempt has been logged.", "danger");
        header("Location: /online-rishta-system/admin/dashboard.php");
        exit();
    }

    // Log successful access for sensitive logs if needed (optional)
    // log_security_event('Page Access', $resource, 'Allowed');
}

/**
 * Display flash message
 */
function display_flash()
{
    if (isset($_SESSION['flash_msg'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $msg = $_SESSION['flash_msg'];
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        unset($_SESSION['flash_msg']);
        unset($_SESSION['flash_type']);
    }
}

/**
 * Set a flash message
 */
function set_flash($msg, $type = 'success')
{
    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_type'] = $type;
}
/**
 * Calculate compatibility between two users based on partner preferences
 */
function calculate_match_score($user_prefs, $target_profile)
{
    if (!$user_prefs || !$target_profile)
        return 0;

    $score = 0;
    $total_weight = 0;

    // 1. Age (Weight: 20)
    $total_weight += 20;
    if (isset($target_profile['dob'])) {
        $age = (new DateTime($target_profile['dob']))->diff(new DateTime('today'))->y;
        if ($age >= ($user_prefs['min_age'] ?? 18) && $age <= ($user_prefs['max_age'] ?? 70)) {
            $score += 20;
        }
    }

    // 2. City (Weight: 15)
    $total_weight += 15;
    if (
        !empty($user_prefs['city']) && !empty($target_profile['city']) &&
        stripos($target_profile['city'], $user_prefs['city']) !== false
    ) {
        $score += 15;
    }

    // 3. Education (Weight: 15)
    $total_weight += 15;
    $target_edu = $target_profile['education_level'] ?? $target_profile['education'] ?? '';
    if (
        !empty($user_prefs['education']) && !empty($target_edu) &&
        stripos($target_edu, $user_prefs['education']) !== false
    ) {
        $score += 15;
    }

    // 4. Profession (Weight: 15)
    $total_weight += 15;
    if (
        !empty($user_prefs['profession']) && !empty($target_profile['profession']) &&
        stripos($target_profile['profession'], $user_prefs['profession']) !== false
    ) {
        $score += 15;
    }

    // 5. Marital Status (Weight: 15)
    $total_weight += 15;
    if (
        ($user_prefs['marital_status'] ?? 'Any') === 'Any' ||
        ($user_prefs['marital_status'] ?? '') === ($target_profile['marital_status'] ?? '')
    ) {
        $score += 15;
    }

    // 6. Sect (Weight: 20)
    $total_weight += 20;
    if (
        !empty($user_prefs['sect']) && !empty($target_profile['sect']) &&
        stripos($target_profile['sect'], $user_prefs['sect']) !== false
    ) {
        $score += 20;
    }

    return round(($score / $total_weight) * 100);
}
/**
 * Format price as PKR (Rs.)
 */
function formatPrice($amount)
{
    return 'Rs. ' . number_format($amount, 0);
}

// Deprecated (Alias to new function)
function format_currency($amount)
{
    return formatPrice($amount);
}

/**
 * Get a site setting by key
 */
function get_setting($key, $default = null)
{
    global $pdo;
    static $settings_cache = null;

    if ($settings_cache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $settings_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return $settings_cache[$key] ?? $default;
}

/**
 * Check if a user's profile is verified
 */
function is_profile_verified($user_id = null)
{
    global $pdo;
    $user_id = $user_id ?: ($_SESSION['user_id'] ?? null);
    if (!$user_id) return false;

    $stmt = $pdo->prepare("SELECT is_verified FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Check if current user is on free plan
 */
function is_free_plan()
{
    return ($_SESSION['plan_id'] ?? 1) == 1;
}

/**
 * Check if free user has access to a feature (Verification + Limit)
 */
function check_feature_access($feature)
{
    if ($_SESSION['role'] === 'Admin') return true;
    
    if (is_free_plan()) {
        if (!is_profile_verified()) {
            set_flash("Please verify your profile to unlock all features. Visit your profile to start verification.", "info");
            header("Location: /online-rishta-system/user/profile.php");
            exit();
        }

        global $pdo;
        $user_id = $_SESSION['user_id'];
        
        switch ($feature) {
            case 'community_feed':
                if (!get_setting('free_can_community_feed', 0)) {
                    set_flash("Community Feed is not available in the Free Plan. Please upgrade.", "warning");
                    header("Location: /online-rishta-system/user/subscription.php");
                    exit();
                }
                break;
            
            case 'profile_view':
                $limit = get_setting('free_views_total_limit', 2);
                $stmt = $pdo->prepare("SELECT profiles_viewed_count FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $count = $stmt->fetchColumn();
                if ($count >= $limit) {
                    set_flash("You have reached your free profile view limit ($limit). Please upgrade to view more.", "warning");
                    header("Location: /online-rishta-system/user/subscription.php");
                    exit();
                }
                break;

            case 'meeting_request':
                $limit = get_setting('free_meetings_limit', 0);
                $stmt = $pdo->prepare("SELECT meetings_requested_count FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $count = $stmt->fetchColumn();
                if ($count >= $limit) {
                    set_flash("Meeting requests are limited on the Free Plan. Please upgrade.", "warning");
                    header("Location: /online-rishta-system/user/subscription.php");
                    exit();
                }
                break;

            case 'chat':
                $limit = get_setting('free_message_limit', 5);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
                $stmt->execute([$user_id]);
                $count = $stmt->fetchColumn();
                if ($count >= $limit) {
                    return "You have reached your free message limit ($limit). Please upgrade to continue chatting.";
                }
                break;
        }
    }
    return true;
}
?>