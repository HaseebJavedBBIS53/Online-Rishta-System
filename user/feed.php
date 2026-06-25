<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user details and capabilities
check_feature_access('community_feed');

$stmt = $pdo->prepare("SELECT u.full_name, u.profile_pic, p.is_verified, s.can_community_feed 
                       FROM users u 
                       LEFT JOIN user_profiles p ON u.id = p.user_id 
                       LEFT JOIN subscriptions s ON u.plan_id = s.plan_id
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();

// Create Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $content = sanitize_input($_POST['content']);
    $category = sanitize_input($_POST['category'] ?? 'General');
    $privacy = sanitize_input($_POST['privacy'] ?? 'Public');
    
    $image_path = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
        $image_path = time() . '_' . rand(1000, 9999) . '.' . $ext;
        move_uploaded_file($_FILES['post_image']['tmp_name'], dirname(__DIR__) . '/assets/feed_uploads/' . $image_path);
    }
    
    if (!empty($content) || $image_path) {
        $pdo->prepare("INSERT INTO posts (user_id, content, category, privacy, image, status) VALUES (?, ?, ?, ?, ?, 'Active')")
            ->execute([$user_id, $content, $category, $privacy, $image_path]);
        set_flash("Post created successfully!", "success");
        header("Location: feed.php");
        exit();
    }
}

// Add Comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $post_id = (int)$_POST['post_id'];
    $comment_text = sanitize_input($_POST['comment_text']);
    if (!empty($comment_text)) {
        $pdo->prepare("INSERT INTO post_comments (post_id, user_id, comment_text) VALUES (?, ?, ?)")
            ->execute([$post_id, $user_id, $comment_text]);
    }
    header("Location: feed.php#post-" . $post_id);
    exit();
}

// Like Post (AJAX or direct fallback)
if (isset($_GET['like_post'])) {
    $post_id = (int)$_GET['like_post'];
    // Check if liked
    $chk = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $chk->execute([$post_id, $user_id]);
    if ($chk->fetch()) {
        $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $user_id]);
    } else {
        $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $user_id]);
    }
    header("Location: feed.php#post-" . $post_id);
    exit();
}

// Delete Post
if (isset($_GET['delete_post'])) {
    $post_id = (int)$_GET['delete_post'];
    $pdo->prepare("UPDATE posts SET status = 'Deleted' WHERE id = ? AND user_id = ?")->execute([$post_id, $user_id]);
    set_flash("Post deleted.", "info");
    header("Location: feed.php");
    exit();
}

// Fetch categories for filtering
$cat_filter = isset($_GET['cat']) ? sanitize_input($_GET['cat']) : 'All';

// Fetch Posts
$query = "SELECT p.*, u.full_name, u.profile_pic, u.last_seen,
          (SELECT is_verified FROM user_profiles WHERE user_id = u.id) as is_verified,
          (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
          (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = :uid) as user_liked,
          (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comments_count
          FROM posts p
          JOIN users u ON p.user_id = u.id
          WHERE p.status = 'Active' AND p.visibility = 'Public' AND u.status = 'Active'";
if ($cat_filter !== 'All') {
    $query .= " AND p.category = :cat";
}
$query .= " ORDER BY p.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':uid', $user_id);
if ($cat_filter !== 'All') {
    $stmt->bindValue(':cat', $cat_filter);
}
$stmt->execute();
$posts = $stmt->fetchAll();

// Process Hashtags Helper
function parseHashtags($text) {
    return preg_replace('/#(\w+)/', '<span class="badge bg-primary bg-opacity-10 text-primary py-1 px-2 mx-1 mt-1 rounded-pill cursor-pointer hover-shadow">#$1</span>', htmlspecialchars($text));
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    body, .min-vh-100 { background-color: #f8fafc !important; }
    .feed-container { max-width: 680px; margin: 0 auto; }
    @media (max-width: 680px) { .feed-container { padding: 0; } }
    
    .community-header { background: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%); border-radius: 15px; color: white; padding: 25px; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15); }
    @media (max-width: 576px) { .community-header { border-radius: 0; padding: 20px 15px; } }
    
    .create-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 20px; }
    @media (max-width: 576px) { .create-card { border-radius: 0; margin-top: 0; border-bottom: 8px solid #f1f5f9; } }
    
    .input-pill { background: #f1f5f9; border: none; border-radius: 30px; padding: 12px 20px; width: 100%; outline: none; transition: background 0.3s; font-size: 0.95rem; }
    .input-pill:focus { background: #e2e8f0; }
    
    .category-tabs { display: flex; gap: 8px; margin: 20px 0; overflow-x: auto; padding-bottom: 5px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .category-tabs::-webkit-scrollbar { display: none; }
    .cat-tab { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 8px 16px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; white-space: nowrap; }
    .cat-tab.active { background: #eff6ff; border-color: #3b82f6; color: #2563eb; }
    
    .post-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; border: none; }
    @media (max-width: 576px) { .post-card { border-radius: 0; padding: 15px; margin-bottom: 8px; } }
    
    .post-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
    .action-row { display: flex; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; padding: 2px 0; margin-top: 15px; }
    .action-btn { flex: 1; display: flex; justify-content: center; align-items: center; gap: 8px; background: transparent; border: none; color: #64748b; font-weight: 600; padding: 10px; border-radius: 8px; transition: background 0.2s; font-size: 0.9rem; text-decoration: none; }
    @media (max-width: 576px) { .action-btn span { display: none; } .action-btn { font-size: 1.1rem; } }
    
    @media (max-width: 991px) { .main-content { padding-bottom: 80px !important; } }
</style>

<div class="container-fluid min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="feed-container">
                <!-- Header Banner -->
                <div class="community-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="bi bi-globe-americas display-6"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0">Community Wall</h4>
                                <p class="mb-0 opacity-75 small text-white">Share your thoughts & hobbies</p>
                            </div>
                        </div>
                        <div class="text-end d-none d-lg-block">
                            <div class="bg-white bg-opacity-25 rounded-3 p-2 px-3 fw-bold small text-white d-inline-block">1.2k <span class="fw-normal opacity-75 d-block" style="font-size:0.7rem">Members</span></div>
                            <div class="bg-white bg-opacity-25 rounded-3 p-2 px-3 fw-bold small text-white d-inline-block ms-2">3.5k <span class="fw-normal opacity-75 d-block" style="font-size:0.7rem">Posts</span></div>
                        </div>
                    </div>
                </div>

                <!-- Create Post Box -->
                <div class="create-card">
                    <form action="feed.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="create_post" value="1">
                        <input type="hidden" name="privacy" id="privacyInput" value="Public">
                        <input type="file" name="post_image" id="postImageInput" accept="image/*" class="d-none" onchange="document.getElementById('imgPreviewBadge').classList.remove('d-none')">
                        
                        <div class="d-flex gap-3 align-items-center mb-3">
                            <div class="position-relative">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $currentUser['profile_pic'] ?: 'default.jpg' ?>" class="post-avatar">
                                <div class="status-dot"></div>
                            </div>
                            <input type="text" name="content" class="input-pill" placeholder="What's on your mind?" autocomplete="off" id="postInput">
                        </div>
                        <span id="imgPreviewBadge" class="badge bg-success ms-5 mb-3 d-none"><i class="bi bi-image"></i> Image Attached</span>
                        
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex gap-3 text-muted align-items-center">
                                <button type="button" class="btn-icon-subtle" onclick="document.getElementById('postInput').value+=' 😊'"><i class="bi bi-emoji-smile text-warning"></i></button>
                                <button type="button" class="btn-icon-subtle" onclick="document.getElementById('postImageInput').click()"><i class="bi bi-image text-success"></i></button>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-light border-0 fw-bold rounded-pill text-muted dropdown-toggle" data-bs-toggle="dropdown" id="privacyBtn"><i class="bi bi-globe me-1"></i> Public</button>
                                    <ul class="dropdown-menu shadow-sm border-0 fs-7">
                                        <li><a class="dropdown-item" href="#" onclick="setPrivacy('Public', 'bi-globe')"><i class="bi bi-globe me-1"></i> Public</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setPrivacy('Friends', 'bi-people-fill')"><i class="bi bi-people-fill me-1"></i> Friends</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setPrivacy('Only Me', 'bi-lock-fill')"><i class="bi bi-lock-fill me-1"></i> Only Me</a></li>
                                    </ul>
                                </div>
                                <select name="category" class="form-select form-select-sm border-0 bg-light text-muted fw-bold rounded-pill shadow-none" style="width: auto;">
                                    <option value="General">General</option>
                                    <option value="Tech">Tech</option>
                                    <option value="Religion">Religion</option>
                                    <option value="Fun">Fun</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">Post</button>
                        </div>
                    </form>
                </div>

                <!-- Filters -->
                <div class="category-tabs">
                    <a href="feed.php?cat=All" class="cat-tab <?= $cat_filter === 'All' ? 'active' : '' ?>">All</a>
                    <a href="feed.php?cat=General" class="cat-tab <?= $cat_filter === 'General' ? 'active' : '' ?>">General</a>
                    <a href="feed.php?cat=Tech" class="cat-tab <?= $cat_filter === 'Tech' ? 'active' : '' ?>">Tech</a>
                    <a href="feed.php?cat=Religion" class="cat-tab <?= $cat_filter === 'Religion' ? 'active' : '' ?>">Religion</a>
                    <a href="feed.php?cat=Fun" class="cat-tab <?= $cat_filter === 'Fun' ? 'active' : '' ?>">Fun</a>
                </div>

                <!-- Feed Posts -->
                <?php if(empty($posts)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-x display-1 text-muted opacity-25 mb-3"></i>
                        <h5 class="fw-bold text-dark">No posts yet!</h5>
                    </div>
                <?php else: ?>
                    <?php foreach($posts as $post):
                        $is_online = (strtotime($post['last_seen']) > (time() - 900)); // 15 mins
                    ?>
                        <div class="post-card" id="post-<?= $post['id'] ?>">
                            <!-- Post Header -->
                            <div class="d-flex justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="position-relative">
                                        <img src="/online-rishta-system/assets/images/uploads/<?= $post['profile_pic'] ?: 'default.jpg' ?>" class="post-avatar">
                                        <?php if ($is_online): ?><div class="status-dot"></div><?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="user-name">
                                            <?= htmlspecialchars($post['full_name']) ?>
                                            <?php if ($post['is_verified']): ?><i class="bi bi-patch-check-fill verified-badge" title="Verified Member"></i><?php endif; ?>
                                        </div>
                                        <div class="post-meta">
                                            <?= date('M d, g:i A', strtotime($post['created_at'])) ?> &bull; 
                                            <i class="bi bi-globe-americas"></i> Everyone
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <i class="bi bi-three-dots text-muted cursor-pointer px-2" data-bs-toggle="dropdown"></i>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                        <?php if($post['user_id'] == $user_id): ?>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i>Edit post</a></li>
                                            <li><a class="dropdown-item text-danger" href="feed.php?delete_post=<?= $post['id'] ?>" onclick="return confirm('Delete this post?');"><i class="bi bi-trash-fill me-2"></i>Delete post</a></li>
                                        <?php else: ?>
                                            <li><a class="dropdown-item text-danger" href="support.php"><i class="bi bi-flag-fill me-2"></i>Report post</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Post Body -->
                            <div class="post-content">
                                <?= parseHashtags($post['content']) ?>
                                <?php if($post['image']): ?>
                                    <img src="/online-rishta-system/assets/feed_uploads/<?= $post['image'] ?>" class="img-fluid rounded mt-3 w-100" style="max-height: 400px; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            
                            <!-- Stats summary -->
                            <div class="d-flex justify-content-between mt-3 text-muted" style="font-size: 0.9rem;">
                                <div><i class="bi bi-heart-fill text-danger opacity-75"></i> <span id="like-count-<?= $post['id'] ?>"><?= $post['likes_count'] ?></span></div>
                                <div><?= $post['comments_count'] ?> Comments</div>
                            </div>
                            
                            <!-- Action Row -->
                            <div class="action-row">
                                <a href="feed.php?like_post=<?= $post['id'] ?>" class="action-btn <?= $post['user_liked'] ? 'liked' : '' ?>">
                                    <i class="bi bi-heart<?= $post['user_liked'] ? '-fill' : '' ?>"></i> <span>Like</span>
                                </a>
                                <button class="action-btn" onclick="document.getElementById('cmt-<?= $post['id'] ?>').focus()"><i class="bi bi-chat"></i> <span>Comment</span></button>
                                <button class="action-btn" onclick="navigator.clipboard.writeText('<?= urlencode('https://'.$_SERVER['HTTP_HOST'].'/online-rishta-system/user/feed.php#post-'.$post['id']) ?>'); alert('Link copied!');"><i class="bi bi-share"></i> <span>Share</span></button>
                            </div>
                            
                            <!-- Comments List -->
                            <?php 
                            $cmts = $pdo->prepare("SELECT c.comment_text, c.created_at, u.full_name, u.profile_pic FROM post_comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at DESC LIMIT 3");
                            $cmts->execute([$post['id']]);
                            foreach($cmts->fetchAll() as $cmt):
                            ?>
                            <div class="d-flex gap-2 mt-3">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $cmt['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle" style="width: 32px; height: 32px; border:1px solid #ddd;">
                                <div class="bg-light px-3 py-2 rounded-4">
                                    <div class="fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($cmt['full_name']) ?> <span class="fw-normal text-muted" style="font-size: 0.7rem;"><?= date('M d', strtotime($cmt['created_at'])) ?></span></div>
                                    <div class="text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($cmt['comment_text']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Comment Area -->
                            <div class="comment-area align-items-center">
                                <img src="/online-rishta-system/assets/images/uploads/<?= $currentUser['profile_pic'] ?: 'default.jpg' ?>" class="post-avatar" style="width: 35px; height: 35px;">
                                <form action="feed.php" method="POST" class="comment-input-wrap m-0 w-100">
                                    <input type="hidden" name="submit_comment" value="1">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <input type="text" name="comment_text" id="cmt-<?= $post['id'] ?>" class="comment-input mb-0 pe-5 border focus-ring" placeholder="Write a comment..." required>
                                    <button type="submit" class="btn btn-link comment-icon-right text-primary p-0 h-100"><i class="bi bi-send-fill fs-5"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
function setPrivacy(text, iconClass) {
    document.getElementById('privacyInput').value = text;
    document.getElementById('privacyBtn').innerHTML = `<i class="bi ${iconClass} me-1"></i> ` + text;
}
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
