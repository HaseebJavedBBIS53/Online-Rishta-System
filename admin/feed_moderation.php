<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('moderate_feed');

// Handle Actions
if (isset($_GET['action'])) {
    $post_id = (int)($_GET['post_id'] ?? 0);
    $action = $_GET['action'];

    if ($action === 'delete' && $post_id) {
        $pdo->prepare("UPDATE posts SET status = 'Deleted' WHERE id = ?")->execute([$post_id]);
        set_flash("Post removed successfully.", "success");
    } elseif ($action === 'block' && $post_id) {
        $pdo->prepare("UPDATE posts SET status = 'Blocked' WHERE id = ?")->execute([$post_id]);
        set_flash("Post blocked.", "warning");
    } elseif ($action === 'restore' && $post_id) {
        $pdo->prepare("UPDATE posts SET status = 'Active' WHERE id = ?")->execute([$post_id]);
        set_flash("Post restored.", "success");
    }

    // Delete Comment
    if ($action === 'delete_comment') {
        $comment_id = (int)($_GET['comment_id'] ?? 0);
        if ($comment_id) {
            $pdo->prepare("DELETE FROM post_comments WHERE id = ?")->execute([$comment_id]);
            set_flash("Comment deleted.", "success");
        }
    }

    // Bulk
    if ($action === 'bulk_delete' && isset($_POST['post_ids'])) {
        $ids = array_map('intval', $_POST['post_ids']);
        if (!empty($ids)) {
            $placeholders = implode(',', $ids);
            $pdo->exec("UPDATE posts SET status='Deleted' WHERE id IN ($placeholders)");
            set_flash(count($ids) . " posts removed.", "success");
        }
    }

    header("Location: feed_moderation.php"); exit();
}

// Filter params
$filter_status = $_GET['status'] ?? 'Active';
$filter_category = $_GET['category'] ?? '';
$filter_user = $_GET['user_search'] ?? '';
$filter_privacy = $_GET['privacy'] ?? '';
$current_page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($current_page - 1) * $limit;

$where = "1=1";
$params = [];

if ($filter_status) {
    if ($filter_status === 'Active') {
        $where .= " AND (p.status = 'Active' OR p.status IS NULL)";
    } else {
        $where .= " AND p.status = ?"; $params[] = $filter_status;
    }
} else {
    $where .= " AND p.status != 'Deleted'";
}
if ($filter_category) { $where .= " AND p.category = ?"; $params[] = $filter_category; }
if ($filter_privacy) { $where .= " AND p.privacy = ?"; $params[] = $filter_privacy; }
if ($filter_user) {
    $where .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filter_user%"; $params[] = "%$filter_user%";
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM posts p JOIN users u ON p.user_id=u.id WHERE $where");
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_count / $limit);

$stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email, u.profile_pic,
          (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
          (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comments_count
          FROM posts p JOIN users u ON p.user_id = u.id
          WHERE $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM posts WHERE status='Active' OR status IS NULL")->fetchColumn(),
    'blocked' => $pdo->query("SELECT COUNT(*) FROM posts WHERE status='Blocked'")->fetchColumn(),
    'deleted' => $pdo->query("SELECT COUNT(*) FROM posts WHERE status='Deleted'")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at)=CURDATE() AND (status='Active' OR status IS NULL)")->fetchColumn(),
    'total_comments' => $pdo->query("SELECT COUNT(*) FROM post_comments")->fetchColumn(),
    'total_likes' => $pdo->query("SELECT COUNT(*) FROM post_likes")->fetchColumn(),
];

$categories = $pdo->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.feed-stat { background:#fff; border-radius:12px; padding:18px 20px; box-shadow:0 4px 15px rgba(0,0,0,0.05); border:none; }
.post-card { background:#fff; border-radius:14px; border:none; box-shadow:0 4px 15px rgba(0,0,0,0.05); transition:all 0.2s; }
.post-card:hover { box-shadow:0 8px 25px rgba(0,0,0,0.08); transform:translateY(-1px); }
.post-image-thumb { width:80px; height:80px; object-fit:cover; border-radius:10px; }
.status-badge-active { background:#d1fae5; color:#065f46; }
.status-badge-blocked { background:#fef3c7; color:#92400e; }
.status-badge-deleted { background:#fee2e2; color:#991b1b; }
.author-thumb { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid #e5e7eb; }
.filter-bar { background:#fff; border-radius:14px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.05); margin-bottom:24px; }
</style>

<div class="container-fluid py-4">

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i> Community Feed Moderation</h1>
        <p class="text-muted small mb-0">Monitor, review, block, and delete all user-generated social wall content.</p>
    </div>
</div>

<?php if(isset($_SESSION['flash_message'])): ?>
<div class="alert alert-<?= $_SESSION['flash_type'] ?? 'success' ?> alert-dismissible fade show shadow-sm border-0 rounded-4">
    <?= $_SESSION['flash_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="feed-stat text-center">
            <div class="h3 fw-bold text-primary mb-0"><?= $stats['total'] ?></div>
            <div class="small text-muted">Active Posts</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="feed-stat text-center">
            <div class="h3 fw-bold text-success mb-0"><?= $stats['today'] ?></div>
            <div class="small text-muted">Posted Today</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="feed-stat text-center">
            <div class="h3 fw-bold text-warning mb-0"><?= $stats['blocked'] ?></div>
            <div class="small text-muted">Blocked</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="feed-stat text-center">
            <div class="h3 fw-bold text-danger mb-0"><?= $stats['deleted'] ?></div>
            <div class="small text-muted">Deleted</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="feed-stat text-center">
            <div class="h3 fw-bold text-info mb-0"><?= $stats['total_comments'] ?></div>
            <div class="small text-muted">Comments</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="feed-stat text-center">
            <div class="h3 fw-bold text-danger mb-0"><?= $stats['total_likes'] ?></div>
            <div class="small text-muted">Total Likes</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Search by User</label>
            <input type="text" name="user_search" class="form-control bg-light border-0" placeholder="Name or email..." value="<?= htmlspecialchars($filter_user) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted">Status</label>
            <select name="status" class="form-select bg-light border-0">
                <option value="">All</option>
                <option value="Active" <?= $filter_status=='Active'?'selected':'' ?>>Active</option>
                <option value="Blocked" <?= $filter_status=='Blocked'?'selected':'' ?>>Blocked</option>
                <option value="Deleted" <?= $filter_status=='Deleted'?'selected':'' ?>>Deleted</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted">Category</label>
            <select name="category" class="form-select bg-light border-0">
                <option value="">All Categories</option>
                <?php foreach($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category==$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted">Privacy</label>
            <select name="privacy" class="form-select bg-light border-0">
                <option value="">All</option>
                <option value="Public" <?= $filter_privacy=='Public'?'selected':'' ?>>Public</option>
                <option value="Friends" <?= $filter_privacy=='Friends'?'selected':'' ?>>Friends</option>
                <option value="Only Me" <?= $filter_privacy=='Only Me'?'selected':'' ?>>Only Me</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">Apply Filters</button>
        </div>
        <div class="col-md-1">
            <a href="feed_moderation.php" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<!-- Bulk Action Form -->
<form method="POST" action="feed_moderation.php?action=bulk_delete" id="bulkForm">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <input type="checkbox" id="selectAll" class="form-check-input fs-5">
        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold rounded-pill" onclick="return confirm('Delete selected posts permanently?')">
            <i class="bi bi-trash me-1"></i> Delete Selected
        </button>
        <span class="text-muted small fw-bold"><?= $total_count ?> posts found</span>
    </div>
    <!-- Pagination -->
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if($current_page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?= $current_page-1 ?>&status=<?= $filter_status ?>&category=<?= $filter_category ?>&user_search=<?= $filter_user ?>">«</a></li><?php endif; ?>
            <?php for($i = max(1,$current_page-2); $i <= min($total_pages,$current_page+2); $i++): ?>
            <li class="page-item <?= $i==$current_page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= $filter_status ?>&category=<?= $filter_category ?>&user_search=<?= $filter_user ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <?php if($current_page < $total_pages): ?><li class="page-item"><a class="page-link" href="?page=<?= $current_page+1 ?>&status=<?= $filter_status ?>&category=<?= $filter_category ?>&user_search=<?= $filter_user ?>">»</a></li><?php endif; ?>
        </ul>
    </nav>
</div>

<!-- Posts Cards -->
<div class="row g-3">
    <?php if(empty($posts)): ?>
    <div class="col-12"><div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted"><i class="bi bi-collection display-1 opacity-25"></i><p class="mt-3 fw-bold">No posts found matching your filters.</p></div></div>
    <?php else: foreach($posts as $p): ?>
    <div class="col-12">
        <div class="post-card p-4">
            <div class="row g-3 align-items-start">
                <!-- Checkbox -->
                <div class="col-auto pt-1">
                    <input type="checkbox" name="post_ids[]" value="<?= $p['id'] ?>" class="post-checkbox form-check-input fs-5">
                </div>
                <!-- Author -->
                <div class="col-auto">
                    <img src="/online-rishta-system/assets/images/uploads/<?= $p['profile_pic'] ?: 'default.jpg' ?>" class="author-thumb">
                </div>
                <!-- Content -->
                <div class="col">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($p['full_name']) ?></span>
                            <span class="text-muted small ms-2"><?= htmlspecialchars($p['email']) ?></span>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <?php $post_status = $p['status'] ?? 'Active'; ?>
                            <span class="badge rounded-pill px-3 status-badge-<?= strtolower($post_status) ?>"><?= $post_status ?></span>
                            <span class="badge bg-secondary bg-opacity-15 text-dark rounded-pill" style="font-size:10px;"><?= htmlspecialchars($p['category'] ?? 'General') ?></span>
                            <span class="badge bg-light text-muted border rounded-pill" style="font-size:10px;"><i class="bi bi-globe me-1"></i><?= htmlspecialchars($p['privacy'] ?? 'Public') ?></span>
                            <span class="text-muted" style="font-size:11px;"><?= date('M d, Y h:i A', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>

                    <p class="mt-2 mb-2 text-dark" style="line-height:1.6;"><?= nl2br(htmlspecialchars(substr($p['content'], 0, 300))) ?><?= strlen($p['content']) > 300 ? '...' : '' ?></p>

                    <?php if($p['image']): ?>
                    <img src="/online-rishta-system/assets/feed_uploads/<?= $p['image'] ?>" class="post-image-thumb me-2 mb-2">
                    <?php endif; ?>

                    <div class="d-flex gap-3 mt-2 mb-3">
                        <span class="text-muted small"><i class="bi bi-heart-fill text-danger me-1"></i><?= $p['likes_count'] ?> Likes</span>
                        <span class="text-muted small"><i class="bi bi-chat-fill text-primary me-1"></i><?= $p['comments_count'] ?> Comments</span>
                        <button class="btn btn-link btn-sm text-muted p-0 fw-bold" onclick="loadComments(<?= $p['id'] ?>, this)">
                            <i class="bi bi-chat-left-text me-1"></i> View Comments
                        </button>
                    </div>

                    <!-- Comments Area -->
                    <div id="comments-<?= $p['id'] ?>" class="d-none bg-light rounded-3 p-3 mt-2"></div>
                </div>

                <!-- Actions Column -->
                <div class="col-auto">
                    <div class="d-flex flex-column gap-2">
                        <?php 
                        $p_stat = $p['status'] ?? 'Active';
                        if($p_stat === 'Active'): 
                        ?>
                        <a href="feed_moderation.php?action=block&post_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning fw-bold rounded-pill" onclick="return confirm('Block this post?')">
                            <i class="bi bi-slash-circle me-1"></i> Block
                        </a>
                        <a href="feed_moderation.php?action=delete&post_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger fw-bold rounded-pill" onclick="return confirm('Delete this post permanently?')">
                            <i class="bi bi-trash me-1"></i> Delete
                        </a>
                        <?php elseif($p_stat === 'Blocked'): ?>
                        <a href="feed_moderation.php?action=restore&post_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success fw-bold rounded-pill">
                            <i class="bi bi-arrow-clockwise me-1"></i> Restore
                        </a>
                        <a href="feed_moderation.php?action=delete&post_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger fw-bold rounded-pill" onclick="return confirm('Delete permanently?')">
                            <i class="bi bi-trash me-1"></i> Delete
                        </a>
                        <?php else: ?>
                        <a href="feed_moderation.php?action=restore&post_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success fw-bold rounded-pill">
                            <i class="bi bi-arrow-clockwise me-1"></i> Restore
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>
</form>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.post-checkbox').forEach(cb => cb.checked = this.checked);
});

function loadComments(postId, btn) {
    const container = document.getElementById('comments-' + postId);
    if (!container.classList.contains('d-none')) {
        container.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-chat-left-text me-1"></i> View Comments';
        return;
    }
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
    fetch('?ajax_comments=1&post_id=' + postId)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
            container.classList.remove('d-none');
            btn.innerHTML = '<i class="bi bi-chat-left-text me-1"></i> Hide Comments';
        });
}
</script>

<?php
// AJAX handler for comments
if (isset($_GET['ajax_comments']) && isset($_GET['post_id'])) {
    $post_id = (int)$_GET['post_id'];
    $cmts = $pdo->prepare("SELECT c.*, u.full_name, u.profile_pic FROM post_comments c JOIN users u ON c.user_id=u.id WHERE c.post_id=? ORDER BY c.created_at ASC");
    $cmts->execute([$post_id]);
    $comments = $cmts->fetchAll();
    if (empty($comments)) {
        echo '<p class="text-muted small text-center mb-0">No comments on this post.</p>';
    } else {
        foreach($comments as $c) {
            echo '<div class="d-flex gap-2 align-items-start mb-2">';
            echo '<img src="/online-rishta-system/assets/images/uploads/'.($c['profile_pic']?:'default.jpg').'" class="rounded-circle" style="width:30px;height:30px;object-fit:cover;">';
            echo '<div class="flex-grow-1 bg-white rounded-3 px-3 py-2 small">';
            echo '<span class="fw-bold">'.htmlspecialchars($c['full_name']).'</span>';
            echo '<span class="text-muted ms-2" style="font-size:10px;">'.date('M d, H:i', strtotime($c['created_at'])).'</span>';
            echo '<a href="feed_moderation.php?action=delete_comment&comment_id='.$c['id'].'&post_id='.$post_id.'" class="btn btn-link btn-sm p-0 ms-2 text-danger" onclick="return confirm(\'Delete comment?\')"><i class="bi bi-trash"></i></a>';
            echo '<div class="mt-1">'.htmlspecialchars($c['comment_text']).'</div>';
            echo '</div></div>';
        }
    }
    exit();
}

require_once __DIR__ . '/includes/footer.php';
?>
