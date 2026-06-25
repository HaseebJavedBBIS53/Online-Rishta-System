<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current preferences
$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$prefs = $stmt->fetch();
$has_prefs = $prefs !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min_age = intval($_POST['min_age']);
    $max_age = intval($_POST['max_age']);
    $city = sanitize_input($_POST['city']);
    $education = sanitize_input($_POST['education']);
    $religion = sanitize_input($_POST['religion']);
    $sect = sanitize_input($_POST['sect']);
    $profession = sanitize_input($_POST['profession']);
    $min_income = sanitize_input($_POST['min_income']);
    $height = sanitize_input($_POST['height']);
    $weight = sanitize_input($_POST['weight']);
    $mother_tongue = sanitize_input($_POST['mother_tongue']);
    $marital_status = sanitize_input($_POST['marital_status']);

    if ($has_prefs) {
        $pdo->prepare("UPDATE partner_preferences SET min_age=?, max_age=?, city=?, education=?, religion=?, sect=?, profession=?, min_income=?, height=?, weight=?, mother_tongue=?, marital_status=? WHERE user_id=?")
            ->execute([$min_age, $max_age, $city, $education, $religion, $sect, $profession, $min_income, $height, $weight, $mother_tongue, $marital_status, $user_id]);
    } else {
        $pdo->prepare("INSERT INTO partner_preferences (user_id, min_age, max_age, city, education, religion, sect, profession, min_income, height, weight, mother_tongue, marital_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$user_id, $min_age, $max_age, $city, $education, $religion, $sect, $profession, $min_income, $height, $weight, $mother_tongue, $marital_status]);
    }

    set_flash("Partner preferences updated! Your match scores will now be more accurate.", "success");
    header("Location: preferences.php");
    exit();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="mb-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Partner Preferences</h2>
                        <p class="text-muted small">Target your ideal match with precise criteria.</p>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold small align-self-start">
                        <i class="bi bi-cpu-fill me-2"></i> Smart Matching Active
                    </div>
                </div>
            </div>
            
            <form action="preferences.php" method="POST">
                <div class="row g-4">
                    <!-- Basic Criteria -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4 d-flex align-items-center">
                                    <span class="bg-indigo-soft p-2 rounded-3 me-3 text-primary"><i class="bi bi-person-heart"></i></span>
                                    Physical & Personal
                                </h5>
                                
                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-bold">Age Range (Min - Max)</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="number" name="min_age" class="form-control border-0 bg-light rounded-3 py-2" placeholder="Min" min="18" max="70" value="<?= $prefs['min_age'] ?? '18' ?>">
                                        <div class="vr mx-1"></div>
                                        <input type="number" name="max_age" class="form-control border-0 bg-light rounded-3 py-2" placeholder="Max" min="18" max="70" value="<?= $prefs['max_age'] ?? '50' ?>">
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6 mb-3">
                                        <label class="form-label text-muted small fw-bold">Height</label>
                                        <input type="text" name="height" class="form-control border-0 bg-light rounded-3 py-2" placeholder="e.g. 5' 5\"" value="<?= htmlspecialchars($prefs['height'] ?? '') ?>">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label text-muted small fw-bold">Weight</label>
                                        <input type="text" name="weight" class="form-control border-0 bg-light rounded-3 py-2" placeholder="e.g. 60kg" value="<?= htmlspecialchars($prefs['weight'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label text-muted small fw-bold">Marital Status Preferred</label>
                                    <select name="marital_status" class="form-select border-0 bg-light rounded-3">
                                        <option value="Any" <?= ($prefs['marital_status'] ?? '') == 'Any' ? 'selected' : '' ?>>No Preference (Any)</option>
                                        <option value="Single" <?= ($prefs['marital_status'] ?? '') == 'Single' ? 'selected' : '' ?>>Never Married (Single)</option>
                                        <option value="Divorced" <?= ($prefs['marital_status'] ?? '') == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                                        <option value="Widowed" <?= ($prefs['marital_status'] ?? '') == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cultural & Social -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4 d-flex align-items-center">
                                    <span class="bg-rose-soft p-2 rounded-3 me-3 text-danger"><i class="bi bi-globe-americas"></i></span>
                                    Identity & Location
                                </h5>
                                
                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-bold">Preferred City</label>
                                    <input type="text" name="city" class="form-control border-0 bg-light rounded-3" placeholder="Any City" value="<?= htmlspecialchars($prefs['city'] ?? '') ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-bold">Religion Preference</label>
                                    <input type="text" name="religion" class="form-control border-0 bg-light rounded-3" placeholder="Any Religion" value="<?= htmlspecialchars($prefs['religion'] ?? '') ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-bold">Sect Preference</label>
                                    <input type="text" name="sect" class="form-control border-0 bg-light rounded-3" placeholder="e.g. Sunni, Shia..." value="<?= htmlspecialchars($prefs['sect'] ?? '') ?>">
                                </div>

                                <div class="mb-0">
                                    <label class="form-label text-muted small fw-bold">Mother Tongue</label>
                                    <input type="text" name="mother_tongue" class="form-control border-0 bg-light rounded-3" placeholder="e.g. Urdu, Punjabi..." value="<?= htmlspecialchars($prefs['mother_tongue'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 bg-white">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4 d-flex align-items-center">
                                    <span class="bg-success-soft p-2 rounded-3 me-3 text-success"><i class="bi bi-briefcase-fill"></i></span>
                                    Professional & Academic
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label text-muted small fw-bold">Education Level</label>
                                        <input type="text" name="education" class="form-control border-0 bg-light rounded-3" placeholder="Any Education" value="<?= htmlspecialchars($prefs['education'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-muted small fw-bold">Preferred Profession</label>
                                        <input type="text" name="profession" class="form-control border-0 bg-light rounded-3" placeholder="e.g. Doctor, Engineer..." value="<?= htmlspecialchars($prefs['profession'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-muted small fw-bold">Minimum Monthly Income</label>
                                        <input type="text" name="min_income" class="form-control border-0 bg-light rounded-3" placeholder="Any Income" value="<?= htmlspecialchars($prefs['min_income'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                     <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-lg w-100 w-md-auto transition-all hover-lift">
                            <i class="bi bi-save2-fill me-2"></i> Save Match Preferences
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<style>
    .bg-indigo-soft { background-color: #eef2ff; }
    .bg-rose-soft { background-color: #fff1f2; }
    .bg-success-soft { background-color: #f0fdf4; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
