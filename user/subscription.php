<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's current plan info
$stmt = $pdo->prepare("SELECT u.plan_id, s.plan_name, s.features 
                       FROM users u 
                       LEFT JOIN subscriptions s ON u.plan_id = s.plan_id 
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();
$current_plan_id = $currentUser['plan_id'] ?? 1;

// Get all active plans
$stmt = $pdo->prepare("SELECT * FROM subscriptions ORDER BY plan_type ASC, duration_months ASC");
$stmt->execute();
$all_plans = $stmt->fetchAll();

$grouped_plans = [
    'Free' => [],
    'Standard' => [],
    'Premium' => []
];

foreach ($all_plans as $p) {
    $type = $p['plan_type'] ?? 'Standard';
    if (!isset($grouped_plans[$type])) $grouped_plans[$type] = [];
    $grouped_plans[$type][] = $p;
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .pricing-header { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; border-radius: 20px; padding: 30px 20px; margin-bottom: 25px; position: relative; overflow: hidden; }
    @media (min-width: 768px) { .pricing-header { padding: 60px 40px; } }
    .pricing-card { border: none; border-radius: 20px; transition: all 0.3s ease; background: white; border: 1px solid rgba(0,0,0,0.05); display: flex; flex-direction: column; }
    .pricing-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1) !important; }
    .pricing-card.popular { border: 2px solid #6366f1; }
    @media (min-width: 992px) { 
        .pricing-card.popular { transform: scale(1.05); z-index: 2; }
        .pricing-card.popular:hover { transform: scale(1.05) translateY(-10px); }
    }
    .plan-badge { background: #6366f1; color: white; padding: 4px 12px; border-radius: 100px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .feature-list li { margin-bottom: 10px; font-size: 0.9rem; color: #475569; display: flex; align-items: start; }
    .feature-list i { color: #10b981; margin-right: 10px; margin-top: 3px; }
    .price-text { font-size: 2.5rem; font-weight: 800; color: #1e293b; }
    .price-sub { color: #64748b; font-size: 0.9rem; }
    .duration-selector { background: #f8fafc; border-radius: 10px; padding: 8px; margin-bottom: 20px; }
    .duration-btn { flex: 1; padding: 8px 0; border-radius: 8px; border: 1px solid transparent; background: transparent; font-size: 0.85rem; font-weight: 600; color: #64748b; transition: all 0.2s; cursor: pointer; }
    .duration-btn.active { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: #6366f1; border-color: #e2e8f0; }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            
            <div class="pricing-header shadow-lg text-center">
                <div class="position-relative z-1">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-2 rounded-pill mb-2 fw-bold d-none d-md-inline-block">Elevate Your Journey</span>
                    <h2 class="fw-bold mb-2">Membership Plans</h2>
                    <p class="opacity-75 mx-auto small" style="max-width: 500px;">Unlock premium features and connect with your future life partner.</p>
                </div>
                <div class="position-absolute top-0 end-0 p-4 opacity-10 d-none d-lg-block">
                    <i class="bi bi-gem display-1"></i>
                </div>
            </div>

            <div class="row g-4 justify-content-center align-items-stretch">
                <?php foreach(['Free', 'Standard', 'Premium'] as $type): 
                    if(empty($grouped_plans[$type])) continue;
                    $type_plans = $grouped_plans[$type];
                    $default_plan = $type_plans[0]; // the first one (often 1 month)
                    
                    $is_popular = ($type === 'Standard');
                    
                    // Check if current user is on this plan type
                    $user_is_on_this_type = false;
                    foreach($type_plans as $tp) {
                        if($tp['plan_id'] == $current_plan_id) {
                            $user_is_on_this_type = true;
                            $default_plan = $tp; // Make their current plan the default selected one
                            break;
                        }
                    }
                ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card pricing-card h-100 shadow-sm <?= $is_popular ? 'popular' : '' ?>" id="card-<?= $type ?>">
                            <div class="card-body p-4 p-md-5 d-flex flex-column">
                                <?php if ($is_popular): ?>
                                    <div class="text-center mb-3">
                                        <span class="plan-badge shadow-sm bg-warning text-dark">Most Popular</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-center mb-4">
                                    <h4 class="fw-bold text-slate-800 mb-1"><?= htmlspecialchars($type) ?></h4>
                                    
                                    <?php if(count($type_plans) > 1): ?>
                                        <div class="duration-selector d-flex gap-1 mt-3">
                                            <?php foreach($type_plans as $idx => $p): ?>
                                                <button type="button" class="duration-btn <?= ($p['plan_id'] == $default_plan['plan_id']) ? 'active' : '' ?>" 
                                                        onclick="selectPlan('<?= $type ?>', '<?= $p['plan_id'] ?>', '<?= formatPrice($p['price']) ?>', '<?= $p['duration_months'] ?>')">
                                                    <?= $p['duration_months'] ?> Mo
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-center align-items-baseline mt-3">
                                        <span class="price-text" id="price-<?= $type ?>"><?= formatPrice($default_plan['price']) ?></span>
                                        <span class="price-sub ms-2" id="duration-<?= $type ?>">/ <?= ($default_plan['price'] == 0) ? 'Lifetime' : (($default_plan['duration_months'] == 0) ? 'Instant' : $default_plan['duration_months'] . ' mo') ?></span>
                                    </div>
                                </div>

                                <hr class="my-4 opacity-5">

                                <ul class="list-unstyled feature-list mb-5 flex-grow-1">
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi bi-person-lines-fill text-primary mt-1"></i>
                                        <span><strong><?= $default_plan['profile_view_limit'] >= 999999 ? 'Unlimited' : $default_plan['profile_view_limit'] ?></strong> Profile Views</span>
                                    </li>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi bi-envelope-heart text-primary mt-1"></i>
                                        <span><strong><?= $default_plan['accepted_request_limit'] >= 999999 ? 'Unlimited' : $default_plan['accepted_request_limit'] ?></strong> Accepted Requests / Contacts</span>
                                    </li>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi bi-calendar-check text-primary mt-1"></i>
                                        <span><strong><?= $default_plan['meeting_limit'] >= 999999 ? 'Unlimited' : $default_plan['meeting_limit'] ?></strong> Meeting Requests</span>
                                    </li>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi <?= $default_plan['can_chat'] ? 'bi-chat-dots-fill text-success' : 'bi-chat-dots text-muted opacity-50' ?> mt-1"></i>
                                        <span class="<?= $default_plan['can_chat'] ? '' : 'text-muted text-decoration-line-through' ?>">Direct Chat Access</span>
                                    </li>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi <?= $default_plan['can_advanced_search'] ? 'bi-funnel-fill text-success' : 'bi-funnel text-muted opacity-50' ?> mt-1"></i>
                                        <span class="<?= $default_plan['can_advanced_search'] ? '' : 'text-muted text-decoration-line-through' ?>">Advanced Filters</span>
                                    </li>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi <?= $default_plan['can_view_who_viewed'] ? 'bi-eye-fill text-success' : 'bi-eye text-muted opacity-50' ?> mt-1"></i>
                                        <span class="<?= $default_plan['can_view_who_viewed'] ? '' : 'text-muted text-decoration-line-through' ?>">Who Viewed My Profile</span>
                                    </li>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi <?= $default_plan['can_community_feed'] ? 'bi-people-fill text-success' : 'bi-people text-muted opacity-50' ?> mt-1"></i>
                                        <span class="<?= $default_plan['can_community_feed'] ? '' : 'text-muted text-decoration-line-through' ?>">Community Feed Access</span>
                                    </li>
                                    <?php if($default_plan['can_highlight_profile']): ?>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi bi-star-fill text-warning mt-1"></i>
                                        <span>Profile Highlight Enabled</span>
                                    </li>
                                    <?php endif; ?>
                                    <?php if($default_plan['can_boost_profile']): ?>
                                    <li class="mb-2 small d-flex align-items-start gap-2">
                                        <i class="bi bi-rocket-takeoff-fill text-danger mt-1"></i>
                                        <span>Profile Boost Enabled</span>
                                    </li>
                                    <?php endif; ?>
                                    <?php 
                                    $features = explode(',', $default_plan['features']);
                                    foreach($features as $f): if(trim($f)):
                                    ?>
                                        <li class="mb-2 small d-flex align-items-start gap-2"><i class="bi bi-check2-circle text-success mt-1"></i> <span><?= htmlspecialchars(trim($f)) ?></span></li>
                                    <?php endif; endforeach; ?>
                                </ul>

                                <div class="d-grid mt-auto">
                                    <?php if ($user_is_on_this_type): ?>
                                        <button class="btn btn-light btn-lg rounded-pill fw-bold py-3 border disabled">
                                            <i class="bi bi-patch-check-fill text-success me-2"></i> Current Tier
                                        </button>
                                    <?php else: ?>
                                        <a href="payment_checkout.php?plan_id=<?= $default_plan['plan_id'] ?>" id="btn-<?= $type ?>" class="btn <?= $is_popular ? 'btn-primary' : 'btn-outline-primary' ?> btn-lg rounded-pill fw-bold py-3 shadow-sm">
                                            Select <?= $type ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 text-center">
                <div class="card border-0 shadow-sm rounded-4 p-4 d-inline-block bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="bi bi-shield-lock-fill text-success fs-4"></i>
                        </div>
                        <div class="text-start">
                            <h6 class="fw-bold mb-0">Secure Payment Guarantee</h6>
                            <p class="text-muted small mb-0">Encryption-protected transactions via processed partners.</p>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<script>
function selectPlan(type, planId, priceStr, durationStr) {
    // We cannot easily update the deep features via simple JS string replacement without re-rendering or passing JSON.
    // However, the features within the same tier (Standard vs Premium) are typically identical across 1/3/6 months except for duration/price.
    // So we only need to update the price, duration, and the select button link.

    // Update active button styling
    let card = document.getElementById('card-' + type);
    card.querySelectorAll('.duration-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    // Update Price and Duration Text
    document.getElementById('price-' + type).innerText = priceStr;
    document.getElementById('duration-' + type).innerText = '/ ' + durationStr + ' mo';

    // Update Select Button Link
    let btn = document.getElementById('btn-' + type);
    if(btn) {
        btn.href = 'payment_checkout.php?plan_id=' + planId;
    }
}
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
