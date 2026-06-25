<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_subscriptions');

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = sanitize_input($_POST['plan_name']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration_months']);
    $features = sanitize_input($_POST['features']);
    $pid = intval($_POST['plan_id'] ?? 0);
    $plan_type = sanitize_input($_POST['plan_type']);
    $profile_view_limit = intval($_POST['profile_view_limit']);
    $accepted_request_limit = intval($_POST['accepted_request_limit']);
    $meeting_limit = intval($_POST['meeting_limit']);
    $can_chat = isset($_POST['can_chat']) ? 1 : 0;
    $can_highlight_profile = isset($_POST['can_highlight_profile']) ? 1 : 0;
    $can_boost_profile = isset($_POST['can_boost_profile']) ? 1 : 0;
    $can_advanced_search = isset($_POST['can_advanced_search']) ? 1 : 0;
    $can_view_who_viewed = isset($_POST['can_view_who_viewed']) ? 1 : 0;
    $can_post_community = isset($_POST['can_post_community']) ? 1 : 0;

    if ($action === 'save') {
        if ($pid > 0) {
            $stmt = $pdo->prepare("UPDATE subscriptions SET plan_name = ?, plan_type = ?, price = ?, duration_months = ?, features = ?, profile_view_limit = ?, accepted_request_limit = ?, meeting_limit = ?, can_chat = ?, can_highlight_profile = ?, can_boost_profile = ?, can_advanced_search = ?, can_view_who_viewed = ?, can_community_feed = ? WHERE plan_id = ?");
            $stmt->execute([$name, $plan_type, $price, $duration, $features, $profile_view_limit, $accepted_request_limit, $meeting_limit, $can_chat, $can_highlight_profile, $can_boost_profile, $can_advanced_search, $can_view_who_viewed, $can_post_community, $pid]);
            set_flash("Membership plan updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO subscriptions (plan_name, plan_type, price, duration_months, features, profile_view_limit, accepted_request_limit, meeting_limit, can_chat, can_highlight_profile, can_boost_profile, can_advanced_search, can_view_who_viewed, can_community_feed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $plan_type, $price, $duration, $features, $profile_view_limit, $accepted_request_limit, $meeting_limit, $can_chat, $can_highlight_profile, $can_boost_profile, $can_advanced_search, $can_view_who_viewed, $can_post_community]);
            set_flash("New membership plan created.");
        }
    } elseif ($action === 'delete') {
        // Prevent deleting 'Free' plan or plans in use (optional constraint)
        if ($pid > 1) {
            $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE plan_id = ?");
            $stmt->execute([$pid]);
            set_flash("Plan deleted successfully.");
        } else {
            set_flash("System default plans cannot be deleted.", "warning");
        }
    }
    header("Location: subscriptions.php");
    exit();
}

$plans = $pdo->query("SELECT * FROM subscriptions ORDER BY price ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4 pt-4">
    <div class="col">
        <h1 class="h2 fw-bold text-dark mb-0">Membership Ecosystem</h1>
        <p class="text-muted small">Configure access tiers, pricing models, and platform value-adds.</p>
    </div>
    <div class="col-auto">
        <button class="btn btn-dark fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#planModal">
            <i class="bi bi-plus-lg me-1"></i> Architect New Plan
        </button>
    </div>
</div>

<div class="row g-4">
    <?php foreach($plans as $p): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
            <div class="card-header border-0 bg-white pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-<?= $p['price'] > 0 ? 'primary' : 'secondary' ?> bg-opacity-10 text-<?= $p['price'] > 0 ? 'primary' : 'secondary text-dark' ?> px-3 rounded-pill ls-1" style="font-size: 10px;">
                    <?= $p['duration_months'] ?> MONTHS
                </span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border-0 rounded-circle" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3">
                        <li><button class="dropdown-item fw-bold edit-btn" 
                                data-id="<?= $p['plan_id'] ?>" 
                                data-name="<?= htmlspecialchars($p['plan_name']) ?>" 
                                data-type="<?= htmlspecialchars($p['plan_type']) ?>" 
                                data-price="<?= $p['price'] ?>" 
                                data-duration="<?= $p['duration_months'] ?>" 
                                data-viewlimit="<?= $p['profile_view_limit'] ?>" 
                                data-reqlimit="<?= $p['accepted_request_limit'] ?>" 
                                data-meetlimit="<?= $p['meeting_limit'] ?>" 
                                data-chat="<?= $p['can_chat'] ?>" 
                                data-high="<?= $p['can_highlight_profile'] ?>" 
                                data-boost="<?= $p['can_boost_profile'] ?>" 
                                data-search="<?= $p['can_advanced_search'] ?>" 
                                data-viewed="<?= $p['can_view_who_viewed'] ?>" 
                                data-comm="<?= $p['can_community_feed'] ?>" 
                                data-features="<?= htmlspecialchars($p['features']) ?>">
                            <i class="bi bi-pencil-square me-2"></i> Edit Tier
                        </button></li>
                        <?php if($p['plan_id'] > 1): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><form action="subscriptions.php" method="POST" onsubmit="return confirm('Archive this tier?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plan_id" value="<?= $p['plan_id'] ?>">
                            <button type="submit" class="dropdown-item text-danger fw-bold"><i class="bi bi-trash3 me-2"></i> Delete Permanently</button>
                        </form></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="card-body px-4 py-4">
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($p['plan_name']) ?></h4>
                <div class="display-6 fw-bold text-dark mb-4"><?= formatPrice($p['price']) ?><small class="text-muted h6">/cycle</small></div>
                
                <h6 class="small fw-bold text-uppercase text-muted ls-1 mb-3">Core Features</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi bi-person-lines-fill text-primary mt-1"></i>
                        <span><strong><?= $p['profile_view_limit'] >= 999999 ? 'Unlimited' : $p['profile_view_limit'] ?></strong> Profile Views</span>
                    </li>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi bi-envelope-heart text-primary mt-1"></i>
                        <span><strong><?= $p['accepted_request_limit'] >= 999999 ? 'Unlimited' : $p['accepted_request_limit'] ?></strong> Accepted Requests / Contacts</span>
                    </li>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi bi-calendar-check text-primary mt-1"></i>
                        <span><strong><?= $p['meeting_limit'] >= 999999 ? 'Unlimited' : $p['meeting_limit'] ?></strong> Meeting Requests</span>
                    </li>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi <?= $p['can_chat'] ? 'bi-chat-dots-fill text-success' : 'bi-chat-dots text-muted opacity-50' ?> mt-1"></i>
                        <span class="<?= $p['can_chat'] ? '' : 'text-muted text-decoration-line-through' ?>">Direct Chat Access</span>
                    </li>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi <?= $p['can_advanced_search'] ? 'bi-funnel-fill text-success' : 'bi-funnel text-muted opacity-50' ?> mt-1"></i>
                        <span class="<?= $p['can_advanced_search'] ? '' : 'text-muted text-decoration-line-through' ?>">Advanced Filters</span>
                    </li>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi <?= $p['can_view_who_viewed'] ? 'bi-eye-fill text-success' : 'bi-eye text-muted opacity-50' ?> mt-1"></i>
                        <span class="<?= $p['can_view_who_viewed'] ? '' : 'text-muted text-decoration-line-through' ?>">Who Viewed My Profile</span>
                    </li>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi <?= $p['can_community_feed'] ? 'bi-people-fill text-success' : 'bi-people text-muted opacity-50' ?> mt-1"></i>
                        <span class="<?= $p['can_community_feed'] ? '' : 'text-muted text-decoration-line-through' ?>">Community Feed Access</span>
                    </li>
                    <?php if($p['can_highlight_profile']): ?>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi bi-star-fill text-warning mt-1"></i>
                        <span>Profile Highlight Enabled</span>
                    </li>
                    <?php endif; ?>
                    <?php if($p['can_boost_profile']): ?>
                    <li class="mb-2 small d-flex align-items-start gap-2">
                        <i class="bi bi-rocket-takeoff-fill text-danger mt-1"></i>
                        <span>Profile Boost Enabled</span>
                    </li>
                    <?php endif; ?>
                    <?php 
                    $feats = explode(',', $p['features']);
                    foreach($feats as $f): if(trim($f)):
                    ?>
                        <li class="mb-2 small d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle-fill text-success mt-1"></i>
                            <span><?= htmlspecialchars(trim($f)) ?></span>
                        </li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="subscriptions.php" method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="plan_id" id="modal_plan_id" value="0">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="modalTitle">Tier Architect</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Plan Visibility Name</label>
                            <input type="text" name="plan_name" id="modal_name" class="form-control bg-light border-0" placeholder="e.g., Diamond Prestige" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Plan Type</label>
                            <select name="plan_type" id="modal_type" class="form-select bg-light border-0">
                                <option value="Free">Free</option>
                                <option value="Standard">Standard</option>
                                <option value="Premium">Premium</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Subscription Price (USD)</label>
                            <input type="number" step="0.01" name="price" id="modal_price" class="form-control bg-light border-0" value="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Duration (Months)</label>
                            <input type="number" name="duration_months" id="modal_duration" class="form-control bg-light border-0" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Profile View Limit <small>(999999 for unlimited)</small></label>
                            <input type="number" name="profile_view_limit" id="modal_viewlimit" class="form-control bg-light border-0" value="100" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Accepted Requests (Contacts) Limit</label>
                            <input type="number" name="accepted_request_limit" id="modal_reqlimit" class="form-control bg-light border-0" value="10" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-bold">Meeting Limit</label>
                            <input type="number" name="meeting_limit" id="modal_meetlimit" class="form-control bg-light border-0" value="0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Special Access & Capabilities</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="can_chat" id="cb_chat">
                                    <label class="form-check-label small" for="cb_chat">Chat Access</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="can_highlight_profile" id="cb_high">
                                    <label class="form-check-label small" for="cb_high">Profile Highlight</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="can_boost_profile" id="cb_boost">
                                    <label class="form-check-label small" for="cb_boost">Profile Boost</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="can_advanced_search" id="cb_search">
                                    <label class="form-check-label small" for="cb_search">Advanced Search</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="can_view_who_viewed" id="cb_viewed">
                                    <label class="form-check-label small" for="cb_viewed">Who Viewed Me</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="can_post_community" id="cb_comm">
                                    <label class="form-check-label small" for="cb_comm">Community Feed</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Features (Comma separated)</label>
                        <textarea name="features" id="modal_features" class="form-control bg-light border-0" rows="4" placeholder="Unlimited Views, Revealed Contacts, Priority Chat..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark fw-bold px-4">Deploy Tier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.onclick = function() {
        document.getElementById('modal_plan_id').value = this.dataset.id;
        document.getElementById('modal_name').value = this.dataset.name;
        document.getElementById('modal_type').value = this.dataset.type;
        document.getElementById('modal_price').value = this.dataset.price;
        document.getElementById('modal_duration').value = this.dataset.duration;
        document.getElementById('modal_viewlimit').value = this.dataset.viewlimit;
        document.getElementById('modal_reqlimit').value = this.dataset.reqlimit;
        document.getElementById('modal_meetlimit').value = this.dataset.meetlimit;
        
        document.getElementById('cb_chat').checked = this.dataset.chat == 1;
        document.getElementById('cb_high').checked = this.dataset.high == 1;
        document.getElementById('cb_boost').checked = this.dataset.boost == 1;
        document.getElementById('cb_search').checked = this.dataset.search == 1;
        document.getElementById('cb_viewed').checked = this.dataset.viewed == 1;
        document.getElementById('cb_comm').checked = this.dataset.comm == 1;

        document.getElementById('modal_features').value = this.dataset.features;
        document.getElementById('modalTitle').innerText = 'Edit Tier';
        new bootstrap.Modal(document.getElementById('planModal')).show();
    };
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
