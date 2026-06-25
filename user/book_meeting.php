<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: search.php");
    exit();
}
$target_id = intval($_GET['id']);

if ($user_id === $target_id) {
    header("Location: profile.php");
    exit();
}

// Enforce Meeting Request Limits & Verification
check_feature_access('meeting_request');

// Fetch Target Profile Details for context
$stmt = $pdo->prepare("SELECT u.full_name, u.profile_pic, p.city, p.country, u.gender
                       FROM users u 
                       LEFT JOIN user_profiles p ON u.id = p.user_id 
                       WHERE u.id = ? AND u.status != 'Deleted'");
$stmt->execute([$target_id]);
$target = $stmt->fetch();

if (!$target) {
    set_flash("Profile not found or inactive.", "danger");
    header("Location: dashboard.php");
    exit();
}

// Handle Meeting Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_meeting') {
    $m_date = sanitize_input($_POST['meeting_date']);
    $m_time = sanitize_input($_POST['meeting_time']);
    $m_type = sanitize_input($_POST['meeting_type']);
    $m_loc = sanitize_input($_POST['location'] ?? '');
    $m_link = sanitize_input($_POST['meeting_link'] ?? '');
    $m_notes = sanitize_input($_POST['notes'] ?? '');

    // Validation
    if ($m_date < date('Y-m-d')) {
        set_flash("Meeting date cannot be in the past.", "danger");
    } else {
        // Prevent duplicates
        $check = $pdo->prepare("SELECT id FROM meetings WHERE sender_id = ? AND receiver_id = ? AND status IN ('Pending User Response', 'Accepted by Receiver', 'Waiting for Admin Approval', 'Approved by Admin')");
        $check->execute([$user_id, $target_id]);
        if ($check->fetch()) {
            set_flash("You already have an active meeting request with this user.", "warning");
        } else {
            $stmt = $pdo->prepare("INSERT INTO meetings (sender_id, receiver_id, meeting_date, meeting_time, meeting_type, location, meeting_link, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $target_id, $m_date, $m_time, $m_type, $m_loc, $m_link, $m_notes]);

            // Increment meeting counter
            $pdo->prepare("UPDATE users SET meetings_requested_count = meetings_requested_count + 1 WHERE id = ?")
                ->execute([$user_id]);

            set_flash("Meeting request sent! Waiting for receiver response.", "success");
            header("Location: view_profile.php?id=$target_id");
            exit();
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light py-4 min-vh-100">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4">
                <a href="view_profile.php?id=<?= $target_id ?>" class="text-decoration-none text-muted fw-bold"><i
                        class="bi bi-arrow-left me-1"></i> Back to Profile</a>
            </div>

            <div class="row g-4">
                <!-- User Profile Preview -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100 bg-white">
                        <div class="position-relative mx-auto mb-3" style="width: 120px; height: 120px;">
                            <img src="/online-rishta-system/assets/images/uploads/<?= $target['profile_pic'] ?: 'default.jpg' ?>"
                                class="rounded-circle w-100 h-100" style="object-fit: cover; border: 4px solid #eef2f5;"
                                alt="Profile">
                        </div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($target['full_name']) ?></h5>
                        <p class="text-muted small mb-3"><i class="bi bi-geo-alt"></i>
                            <?= htmlspecialchars($target['city'] ?? 'Unknown Location') ?>,
                            <?= htmlspecialchars($target['country'] ?? '') ?></p>
                        <hr class="w-50 mx-auto text-muted">
                        <p class="small text-muted mb-0">Meeting requests are subject to mutual acceptance and
                            administrative review for your safety.</p>
                    </div>
                </div>

                <!-- Booking Form -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="card-header bg-primary bg-opacity-10 border-0 p-4 pb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                    style="width: 50px; height: 50px;">
                                    <i class="bi bi-calendar2-plus fs-4"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold text-dark mb-0">Plan a Meeting</h4>
                                    <p class="text-muted mb-0 mt-1" style="line-height: 1.2;">Suggest a date, time, and
                                        safe location.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 pt-3">
                            <?php display_flash(); ?>

                            <div
                                class="alert alert-info border-0 bg-info bg-opacity-10 text-info-darken rounded-4 small mb-4 shadow-sm">
                                <i class="bi bi-shield-check me-2"></i> Safety First! Ensure physical meetings are
                                planned in public locations. Do not share financial details during online calls.
                            </div>

                            <form action="book_meeting.php?id=<?= $target_id ?>" method="POST">
                                <input type="hidden" name="action" value="request_meeting">

                                <div class="row g-4">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted text-uppercase"
                                            style="letter-spacing: 0.5px;">Meeting Type</label>
                                        <div class="row g-3">
                                            <div class="col-sm-6">
                                                <input type="radio" class="btn-check" name="meeting_type"
                                                    id="typePhysical" value="Physical" checked
                                                    onchange="toggleMeetingFields()">
                                                <label
                                                    class="btn btn-outline-primary w-100 py-3 rounded-4 fw-bold shadow-sm border-2 text-center"
                                                    for="typePhysical">
                                                    <i class="bi bi-cup-hot d-block mb-2 fs-3"></i> Physical Meet
                                                </label>
                                            </div>
                                            <div class="col-sm-6">
                                                <input type="radio" class="btn-check" name="meeting_type"
                                                    id="typeOnline" value="Online" onchange="toggleMeetingFields()">
                                                <label
                                                    class="btn btn-outline-primary w-100 py-3 rounded-4 fw-bold shadow-sm border-2 text-center"
                                                    for="typeOnline">
                                                    <i class="bi bi-laptop d-block mb-2 fs-3"></i> Online Call
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted text-uppercase"
                                            style="letter-spacing: 0.5px;">Date</label>
                                        <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden">
                                            <span class="input-group-text bg-light border-0"><i
                                                    class="bi bi-calendar-event text-primary"></i></span>
                                            <input type="date" name="meeting_date"
                                                class="form-control bg-light border-0 fs-6" required
                                                min="<?= date('Y-m-d') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted text-uppercase"
                                            style="letter-spacing: 0.5px;">Time</label>
                                        <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden">
                                            <span class="input-group-text bg-light border-0"><i
                                                    class="bi bi-clock text-primary"></i></span>
                                            <input type="time" name="meeting_time"
                                                class="form-control bg-light border-0 fs-6" required>
                                        </div>
                                    </div>

                                    <div class="col-12" id="locationField">
                                        <label class="form-label small fw-bold text-muted text-uppercase"
                                            style="letter-spacing: 0.5px;">Location Details</label>
                                        <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden">
                                            <span class="input-group-text bg-light border-0"><i
                                                    class="bi bi-geo-alt text-primary"></i></span>
                                            <input type="text" name="location"
                                                class="form-control bg-light border-0 fs-6"
                                                placeholder="e.g. A safe public cafe or restaurant">
                                        </div>
                                    </div>

                                    <div class="col-12 d-none" id="linkField">
                                        <label class="form-label small fw-bold text-muted text-uppercase"
                                            style="letter-spacing: 0.5px;">Meeting Link (Zoom/Meet)</label>
                                        <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden">
                                            <span class="input-group-text bg-light border-0"><i
                                                    class="bi bi-link-45deg text-primary"></i></span>
                                            <input type="url" name="meeting_link"
                                                class="form-control bg-light border-0 fs-6"
                                                placeholder="https://zoom.us/j/...">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted text-uppercase"
                                            style="letter-spacing: 0.5px;">Notes / Message</label>
                                        <textarea name="notes"
                                            class="form-control bg-light border-0 rounded-4 shadow-sm p-3 fs-6" rows="3"
                                            placeholder="Any special message for the meeting?"></textarea>
                                    </div>

                                    <div class="col-12 mt-4 text-end">
                                        <button type="submit"
                                            class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm w-100 fs-5">Send
                                            Request <i class="bi bi-send-fill ms-2"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleMeetingFields() {
        const isOnline = document.getElementById('typeOnline').checked;
        document.getElementById('locationField').classList.toggle('d-none', isOnline);
        document.getElementById('linkField').classList.toggle('d-none', !isOnline);

        if (isOnline) {
            document.querySelector('[name="location"]').required = false;
            document.querySelector('[name="meeting_link"]').required = true;
        } else {
            document.querySelector('[name="location"]').required = true;
            document.querySelector('[name="meeting_link"]').required = false;
        }
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>