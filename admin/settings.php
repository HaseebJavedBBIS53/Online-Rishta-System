<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = sanitize_input($_POST['site_name'] ?? '');
    $contact_email = sanitize_input($_POST['contact_email'] ?? '');
    $free_views_total_limit = intval($_POST['free_views_total_limit'] ?? 2);
    $free_meetings_limit = intval($_POST['free_meetings_limit'] ?? 0);
    $free_message_limit = intval($_POST['free_message_limit'] ?? 5);
    $free_can_community_feed = isset($_POST['free_can_community_feed']) ? '1' : '0';
    
    $maintenance_mode = isset($_POST['is_maintenance_mode']) ? '1' : '0';
    $updates = [
        'site_name' => $site_name,
        'contact_email' => $contact_email,
        'free_views_total_limit' => $free_views_total_limit,
        'free_meetings_limit' => $free_meetings_limit,
        'free_message_limit' => $free_message_limit,
        'free_can_community_feed' => $free_can_community_feed,
        'is_maintenance_mode' => $maintenance_mode
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    foreach ($updates as $key => $val) {
        $stmt->execute([$key, $val, $val]);
    }

    // Sync with Subscriptions Table for Plan ID 1 (Free Plan)
    // We update all limits and the chat capability based on the settings
    $can_chat = ($free_message_limit > 0) ? 1 : 0;
    $features_text = "Profile Verification Required";
    if ($free_message_limit > 0) $features_text .= ", $free_message_limit Messages";
    if ($free_can_community_feed) $features_text .= ", Community Feed Access";
    
    $stmt = $pdo->prepare("UPDATE subscriptions SET profile_view_limit = ?, meeting_limit = ?, accepted_request_limit = ?, can_community_feed = ?, can_chat = ?, features = ? WHERE plan_id = 1");
    $stmt->execute([$free_views_total_limit, $free_meetings_limit, $free_message_limit, $free_can_community_feed, $can_chat, $features_text]);

    set_flash("Settings updated successfully.", "success");
    header("Location: settings.php");
    exit();
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .nav-pills .nav-link {
        color: #64748b;
        font-weight: 600;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 5px;
    }

    .nav-pills .nav-link.active,
    .nav-pills .show>.nav-link {
        color: #fff;
        background-color: #6366f1;
    }

    .nav-pills .nav-link:hover:not(.active) {
        background-color: #f1f5f9;
    }

    .settings-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }

    .form-switch .form-check-input {
        width: 3em;
        height: 1.5em;
        cursor: pointer;
    }
</style>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h3 fw-bold text-dark mb-0">Platform Settings</h1>
        <p class="text-muted small">Manage your core platform configuration and financials.</p>
    </div>
</div>

<form action="settings.php" method="POST">
    <div class="row">
        <!-- Sidebar Tabs -->
        <div class="col-md-3 mb-4">
            <div class="card settings-card p-3">
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active text-start" id="v-pills-general-tab" data-bs-toggle="pill"
                        data-bs-target="#v-pills-general" type="button" role="tab"><i class="bi bi-gear-fill me-2"></i>
                        General</button>
                    <button class="nav-link text-start" id="v-pills-limits-tab" data-bs-toggle="pill"
                        data-bs-target="#v-pills-limits" type="button" role="tab"><i class="bi bi-sliders me-2"></i>
                        User Limits</button>
                </div>

                <hr class="opacity-10 my-4">

                <button type="submit" class="btn btn-primary fw-bold w-100 shadow-sm py-2"><i
                        class="bi bi-save-fill me-2"></i> Save All Changes</button>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="col-md-9">
            <div class="tab-content" id="v-pills-tabContent">

                <!-- General Tab -->
                <div class="tab-pane fade show active" id="v-pills-general" role="tabpanel" tabindex="0">
                    <div class="card settings-card">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="m-0 fw-bold text-dark">General Site Configuration</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Site Name</label>
                                    <input type="text" name="site_name" class="form-control"
                                        value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
                                    <div class="form-text">Displayed on the public side header.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Support Email</label>
                                    <input type="email" name="contact_email" class="form-control"
                                        value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" required>
                                    <div class="form-text">Where user queries are redirected.</div>
                                </div>
                            </div>
                            <hr class="my-4">
                            <div
                                class="d-flex justify-content-between align-items-center bg-light bg-opacity-50 p-3 rounded-3 border border-warning border-opacity-25">
                                <div>
                                    <h6 class="fw-bold mb-1 text-warning-emphasis">Maintenance Mode</h6>
                                    <p class="mb-0 small text-muted">Temporarily disable public access to perform
                                        upgrades.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        name="is_maintenance_mode" value="1" <?= ($settings['is_maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Limits Tab -->
                <div class="tab-pane fade" id="v-pills-limits" role="tabpanel" tabindex="0">
                    <div class="card settings-card">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="m-0 fw-bold text-dark">Free Plan Account Limits</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-info small mb-4"><i class="bi bi-info-circle me-1"></i> These limits apply to users on the <b>Free Plan</b> after their profile is verified.</p>
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Total Profile View Limit</label>
                                    <input type="number" name="free_views_total_limit" class="form-control"
                                        value="<?= htmlspecialchars($settings['free_views_total_limit'] ?? '2') ?>" min="0" required>
                                    <div class="form-text">Lifetime unique profile views allowed.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Meeting Requests Limit</label>
                                    <input type="number" name="free_meetings_limit" class="form-control"
                                        value="<?= htmlspecialchars($settings['free_meetings_limit'] ?? '0') ?>" min="0" required>
                                    <div class="form-text">Total meetings a free user can request.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Chat Message Limit</label>
                                    <input type="number" name="free_message_limit" class="form-control"
                                        value="<?= htmlspecialchars($settings['free_message_limit'] ?? '5') ?>" min="0" required>
                                    <div class="form-text">Number of messages allowed for free users.</div>
                                </div>

                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3 border">
                                        <div>
                                            <h6 class="fw-bold mb-0">Community Feed Access</h6>
                                            <p class="mb-0 small text-muted">Allow free users to post in feed.</p>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                name="free_can_community_feed" value="1" <?= ($settings['free_can_community_feed'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>