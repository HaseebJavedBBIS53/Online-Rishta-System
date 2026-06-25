<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('moderate_feed');

// Handle additions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['word'])) {
    $word = strtolower(trim($_POST['word']));
    if (!empty($word)) {
        $pdo->prepare("INSERT IGNORE INTO banned_words (word) VALUES (?)")->execute([$word]);
        set_flash("Word '$word' added to blacklist.");
    }
    header("Location: banned_words.php");
    exit();
}

// Handle deletions
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM banned_words WHERE id = ?")->execute([$id]);
    set_flash("Word removed from blacklist.");
    header("Location: banned_words.php");
    exit();
}

$words = $pdo->query("SELECT * FROM banned_words ORDER BY word ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center pb-2 mb-4 border-bottom">
    <h1 class="h2 fw-bold">Automated Moderation</h1>
    <span class="badge bg-primary rounded-pill"><?= count($words) ?> Banned Words</span>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-white mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Add New Word</h6>
            </div>
            <div class="card-body">
                <form action="banned_words.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Keyword / Phrase</label>
                        <input type="text" name="word" class="form-control bg-light border-0" placeholder="e.g. offensive_word" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Add to Blacklist</button>
                </form>
            </div>
        </div>
        
        <div class="alert alert-info border-0 shadow-sm">
            <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i> How it works</h6>
            <p class="small mb-0">Words in this list will be automatically masked (e.g. ****) in user profiles, messages, and bios to maintain platform decorum.</p>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 border-0">Word / Phrase</th>
                                <th class="border-0">Added On</th>
                                <th class="pe-4 text-end border-0">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($words)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">No banned words found.</td></tr>
                            <?php else: ?>
                                <?php foreach($words as $w): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-danger"><?= htmlspecialchars($w['word']) ?></td>
                                        <td class="text-muted small"><?= date('M d, Y', strtotime($w['created_at'])) ?></td>
                                        <td class="pe-4 text-end">
                                            <a href="banned_words.php?delete=<?= $w['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this word from blacklist?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
