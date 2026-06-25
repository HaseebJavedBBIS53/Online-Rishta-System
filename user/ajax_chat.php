<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'Admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch_messages') {
    $active_chat = isset($_GET['active_chat']) ? (int) $_GET['active_chat'] : 0;
    if (!$active_chat) {
        echo json_encode([]);
        exit;
    }

    // Get partner info (typing status / last seen)
    $pst = $pdo->prepare("SELECT last_seen, chat_typing_to FROM users WHERE id = ?");
    $pst->execute([$active_chat]);
    $partner = $pst->fetch(PDO::FETCH_ASSOC);

    $partner_status = 'offline';
    $partner_last_seen = 'Offline';
    if ($partner && $partner['last_seen']) {
        $lt = strtotime($partner['last_seen']);
        if (time() - $lt < 15) {
            $partner_status = 'online';
            $partner_last_seen = 'Online';
            if ($partner['chat_typing_to'] == $user_id) {
                $partner_last_seen = 'typing...';
            }
        } else {
            $partner_last_seen = 'last seen: ' . date('h:i A', $lt);
        }
    }

    // Mark unread as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
        ->execute([$active_chat, $user_id]);

    $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, message_text, is_read, created_at, attachment_url, is_deleted 
                           FROM messages 
                           WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                           ORDER BY created_at ASC");
    $stmt->execute([$user_id, $active_chat, $active_chat, $user_id]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format timestamp
    foreach ($msgs as &$msg) {
        $msg['formatted_time'] = date('h:i A', strtotime($msg['created_at']));
    }

    // Update my last seen
    $pdo->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user_id]);

    echo json_encode(['messages' => $msgs, 'partner_status' => $partner_status, 'partner_last_seen' => $partner_last_seen]);
    exit();
}

if ($action === 'typing') {
    $active_chat = isset($_POST['active_chat']) ? (int) $_POST['active_chat'] : 0;
    if ($active_chat) {
        $pdo->prepare("UPDATE users SET chat_typing_to = ?, last_seen = CURRENT_TIMESTAMP WHERE id = ?")->execute([$active_chat, $user_id]);
    }
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'send_message') {
    $active_chat = isset($_POST['active_chat']) ? (int) $_POST['active_chat'] : 0;
    $msg_text = sanitize_input($_POST['message_text'] ?? '');

    // Check message limits for free plan
    $access = check_feature_access('chat');
    if ($access !== true) {
        echo json_encode(['success' => false, 'error' => $access]);
        exit();
    }

    // Clear typing status
    $pdo->prepare("UPDATE users SET chat_typing_to = NULL WHERE id = ?")->execute([$user_id]);

    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . basename($_FILES['attachment']['name']);
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], dirname(__DIR__) . '/assets/chat_uploads/' . $fileName)) {
            $attachment = $fileName;
        }
    }

    if (!$active_chat || (empty($msg_text) && !$attachment)) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, attachment_url) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$user_id, $active_chat, $msg_text, $attachment]);

    echo json_encode(['success' => $success]);
    exit();
}

echo json_encode(['error' => 'Invalid action']);
exit();
?>