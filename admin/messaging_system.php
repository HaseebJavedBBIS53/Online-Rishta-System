<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_permission('manage_content');

$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$search_query = $_GET['q'] ?? '';

// Fetch recent active conversations (Admin perspective)
$chats_sql = "SELECT u.id, u.full_name, u.profile_pic, MAX(m.created_at) as last_msg 
              FROM users u 
              LEFT JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id 
              WHERE u.role = 'User'";

if ($search_query) {
    $chats_sql .= " AND (u.full_name LIKE :q OR u.email LIKE :q)";
}

$chats_sql .= " GROUP BY u.id ORDER BY last_msg DESC, u.created_at DESC LIMIT 15";
$stmt = $pdo->prepare($chats_sql);
if ($search_query) {
    $stmt->bindValue(':q', "%$search_query%");
}
$stmt->execute();
$chats = $stmt->fetchAll();

if ($target_user_id) {
    // Fetch conversation with specific user
    $stmt = $pdo->prepare("SELECT * FROM messages 
                           WHERE (sender_id = ? AND receiver_id = ?) 
                           OR (sender_id = ? AND receiver_id = ?) 
                           ORDER BY created_at ASC");
    $stmt->execute([$_SESSION['user_id'], $target_user_id, $target_user_id, $_SESSION['user_id']]);
    $conversation = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch();
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text'], $_POST['receiver_id'])) {
    $msg = sanitize_input($_POST['message_text']);
    $rec_id = intval($_POST['receiver_id']);
    
    if (!empty($msg)) {
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], $rec_id, $msg]);
        set_flash("Message sent successfully.");
    }
    header("Location: messaging_system.php?user_id=$rec_id");
    exit();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row pt-2 h-100" style="min-height: 85vh;">
    <!-- Sidebar: Recent Chats -->
    <div class="col-md-3 border-end bg-white p-0 d-flex flex-column shadow-sm">
        <div class="p-4 border-bottom bg-light bg-opacity-50">
            <h6 class="fw-bold mb-3">Direct Messaging</h6>
            <form action="messaging_system.php" method="GET">
                <div class="input-group input-group-sm bg-white border rounded-pill px-2">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-0" placeholder="Find user (Name/Email)..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
            </form>
        </div>
        <div class="list-group list-group-flush flex-grow-1 overflow-auto">
            <?php if(empty($chats)): ?>
                <div class="p-4 text-center text-muted small">No members found.</div>
            <?php else: ?>
                <?php foreach($chats as $c): ?>
                    <a href="messaging_system.php?user_id=<?= $c['id'] ?>" class="list-group-item list-group-item-action p-3 border-0 <?= $target_user_id == $c['id'] ? 'bg-primary bg-opacity-10 border-start border-4 border-primary' : '' ?>">
                        <div class="d-flex align-items-center">
                            <img src="/online-rishta-system/assets/images/uploads/<?= $c['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle me-3 shadow-sm" width="45" height="45" style="object-fit:cover;">
                            <div class="flex-grow-1 text-truncate">
                                <h6 class="mb-0 fw-bold small text-dark"><?= htmlspecialchars($c['full_name']) ?></h6>
                                <small class="text-muted" style="font-size: 10px;">
                                    <?= $c['last_msg'] ? date('M d, H:i', strtotime($c['last_msg'])) : 'No messages yet' ?>
                                </small>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="p-3 border-top mt-auto">
            <a href="announcements.php" class="btn btn-dark w-100 fw-bold rounded-pill shadow-sm"><i class="bi bi-megaphone me-2"></i> Announcement Center</a>
        </div>
    </div>

    <!-- Main: Conversation Window -->
    <div class="col-md-9 d-flex flex-column bg-light bg-opacity-25">
        <?php if($target_user_id): ?>
            <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between shadow-sm">
                <div class="d-flex align-items-center">
                    <img src="/online-rishta-system/assets/images/uploads/<?= $target_user['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle me-3" width="50" height="50" style="object-fit:cover;">
                    <div>
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($target_user['full_name']) ?></h6>
                        <small class="text-success fw-bold" style="font-size: 10px;"><i class="bi bi-circle-fill me-1" style="font-size: 7px;"></i> ACTIVE DIALOGUE</small>
                    </div>
                </div>
                <div>
                   <div class="btn-group">
                       <a href="user_details.php?id=<?= $target_user_id ?>" class="btn btn-sm btn-white border fw-bold rounded-pill px-3 me-2">View Profile</a>
                   </div>
                </div>
            </div>

            <div class="flex-grow-1 p-4 overflow-auto" id="msgContainer" style="max-height: 62vh;">
                <?php if(empty($conversation)): ?>
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center text-center opacity-50">
                        <i class="bi bi-chat-dots display-1 text-muted mb-3"></i>
                        <h6 class="fw-bold">No message history</h6>
                        <p class="small text-muted">Start the conversation by sending a message below.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($conversation as $msg): ?>
                        <div class="d-flex mb-4 <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'justify-content-end' : '' ?>">
                            <div class="card border-0 shadow-sm rounded-4 px-3 py-2 <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'bg-primary text-white' : 'bg-white text-dark' ?>" style="max-width: 70%;">
                                <p class="mb-0 small"><?= htmlspecialchars($msg['message_text']) ?></p>
                                <small class="d-block mt-1 <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'text-white-50' : 'text-muted' ?>" style="font-size: 9px;">
                                    <?= date('h:i A', strtotime($msg['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="p-4 border-top bg-white">
                <form action="messaging_system.php" method="POST">
                    <input type="hidden" name="receiver_id" value="<?= $target_user_id ?>">
                    <div class="input-group">
                        <input type="text" name="message_text" class="form-control border-0 bg-light rounded-pill px-4 py-3" placeholder="Type a message to the user..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary rounded-circle ms-2 p-0" style="width: 45px; height: 45px;">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-center p-5 opacity-50">
                <i class="bi bi-chat-quote display-1 text-muted mb-4"></i>
                <h4 class="fw-bold">Select a user to chat</h4>
                <p class="text-muted">You can communicate directly with any member of the platform.</p>
                <div class="w-50 mt-3">
                    <form action="messaging_system.php" method="GET">
                        <div class="input-group bg-white border rounded-pill p-1 shadow-sm">
                            <input type="text" name="q" class="form-control border-0 px-4" placeholder="Search by name or email...">
                            <button type="submit" class="btn btn-dark fw-bold rounded-pill px-4">Search Users</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    var objDiv = document.getElementById("msgContainer");
    if(objDiv) objDiv.scrollTop = objDiv.scrollHeight;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
