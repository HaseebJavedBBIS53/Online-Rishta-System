<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_login();
if ($_SESSION['role'] === 'Admin') {
    header("Location: /online-rishta-system/admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check verification for free users
if (is_free_plan() && !is_profile_verified()) {
    set_flash("Free users must verify their profile to use the Chat system.", "warning");
    header("Location: dashboard.php");
    exit();
}

// Initial Checks: Is User Verified?
$stmt = $pdo->prepare("SELECT p.is_verified, (SELECT plan_id FROM users WHERE id = :uid) as plan_id 
                       FROM user_profiles p WHERE p.user_id = :uid");
$stmt->execute([':uid' => $user_id]);
$curData = $stmt->fetch();
$is_verified = $curData['is_verified'] ?? 0;

// Get mutually accepted users
$query = "SELECT u.id, u.full_name, u.profile_pic, u.role,
          (SELECT message_text FROM messages WHERE (sender_id = :uid AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = :uid) ORDER BY created_at DESC LIMIT 1) as last_msg,
          (SELECT created_at FROM messages WHERE (sender_id = :uid AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = :uid) ORDER BY created_at DESC LIMIT 1) as last_interaction
          FROM users u
          LEFT JOIN interests i ON ((i.sender_id = :uid AND i.receiver_id = u.id) OR (i.sender_id = u.id AND i.receiver_id = :uid)) AND i.status = 'Accepted'
          WHERE (i.status = 'Accepted' OR u.id IN (SELECT sender_id FROM messages WHERE receiver_id = :uid AND sender_id IN (SELECT id FROM users WHERE role = 'Admin')))
          AND u.status = 'Active'
          GROUP BY u.id
          ORDER BY last_interaction DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([':uid' => $user_id]);
$contacts = $stmt->fetchAll();

// Check chat feature capability
$stmt = $pdo->prepare("SELECT s.can_chat FROM users u LEFT JOIN subscriptions s ON u.plan_id = s.plan_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$can_chat = $stmt->fetchColumn();

if (!$can_chat && $_SESSION['role'] !== 'Admin') {
    set_flash("Your membership plan does not support direct messaging. Please upgrade.", "warning");
    header("Location: subscription.php");
    exit();
}

$active_chat = isset($_GET['user']) ? intval($_GET['user']) : null;
$messages = [];
$active_user_details = null;

$is_chatting_with_admin = false;
if ($active_chat) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$active_chat]);
    $target_role = $stmt->fetchColumn();
    if ($target_role === 'Admin')
        $is_chatting_with_admin = true;
}

$has_access = $is_verified || count($contacts) > 0 || $is_chatting_with_admin;

if (!$has_access) {
    set_flash("Chat Locked: Verification or one mutual interest required to access secure messaging.", "warning");
    header("Location: dashboard.php");
    exit();
}

if ($active_chat) {
    // Validate contact access
    $stmt = $pdo->prepare("SELECT id, full_name, profile_pic FROM users WHERE id = ?");
    $stmt->execute([$active_chat]);
    $active_user_details = $stmt->fetch();

    // We use AJAX for new messages, but just in case fallback is triggered:
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message_text'])) {
        $msg_text = sanitize_input($_POST['message_text']);
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)")
            ->execute([$user_id, $active_chat, $msg_text]);
        header("Location: chat.php?user=$active_chat");
        exit();
    }

    // Mark as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_chat, $user_id]);

    // Fetch Messages
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $stmt->execute([$user_id, $active_chat, $active_chat, $user_id]);
    $messages = $stmt->fetchAll();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    :root {
        --chat-bg: #e5ddd5;
        --bubble-me: #dcf8c6;
        --bubble-you: #ffffff;
    }

    .messenger-container {
        height: calc(100vh - 100px);
        border-radius: 12px;
        overflow: hidden;
        background: white;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        display: flex;
        width: 100%;
        position: relative;
    }

    @media (max-width: 991px) {
        .messenger-container {
            height: calc(100vh - 110px);
            border-radius: 0;
            border: none;
        }

        .main-content {
            padding: 0 !important;
        }
    }

    .contacts-panel {
        background: #ffffff;
        border-right: 1px solid #e0e0e0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        width: 350px;
        flex-shrink: 0;
        transition: all 0.3s;
    }

    .chat-main {
        background: var(--chat-bg);
        display: flex;
        flex-direction: column;
        position: relative;
        width: 100%;
    }

    @media (max-width: 991px) {
        .contacts-panel {
            width: 100%;
            position: absolute;
            height: 100%;
            z-index: 20;
            left: 0;
        }

        .contacts-panel.hide-mobile {
            transform: translateX(-100%);
        }

        .chat-main {
            height: 100%;
        }
    }

    /* WhatsApp Background Pattern (Optional subtle image) */
    .chat-main::before {
        content: "";
        position: absolute;
        inset: 0;
        opacity: 0.05;
        pointer-events: none;
        background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
    }

    .contacts-header {
        background: #f0f2f5;
        padding: 15px;
        display: flex;
        align-items: center;
        border-bottom: 1px solid #e0e0e0;
    }

    .contact-search {
        background: white;
        border-radius: 8px;
        padding: 5px 15px;
        display: flex;
        align-items: center;
        margin: 10px;
        border: 1px solid #e0e0e0;
    }

    .contact-search input {
        border: none;
        outline: none;
        background: transparent;
        flex-grow: 1;
        margin-left: 10px;
        font-size: 0.9rem;
    }

    .contact-list {
        overflow-y: auto;
        flex-grow: 1;
    }

    .contact-item {
        padding: 12px 15px;
        transition: background 0.2s;
        border-bottom: 1px solid #f2f2f2;
        cursor: pointer;
        text-decoration: none !important;
        display: flex;
        align-items: center;
    }

    .contact-item:hover {
        background: #f5f6f6;
    }

    .contact-item.active {
        background: #ebebeb;
    }

    .contact-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
    }

    .chat-header {
        background: #f0f2f5;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #e0e0e0;
        z-index: 10;
    }

    #chatBox {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 5;
        scroll-behavior: smooth;
    }

    .msg-bubble {
        padding: 8px 12px;
        border-radius: 8px;
        max-width: 85%;
        font-size: 0.9rem;
        position: relative;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
        line-height: 1.4;
        word-wrap: break-word;
    }

    @media (min-width: 768px) {
        .msg-bubble {
            max-width: 65%;
            font-size: 0.95rem;
        }
    }

    .msg-me {
        background: var(--bubble-me);
        color: #000;
        align-self: flex-end;
        border-top-right-radius: 0;
    }

    .msg-you {
        background: var(--bubble-you);
        color: #000;
        align-self: flex-start;
        border-top-left-radius: 0;
    }

    .msg-time {
        font-size: 0.6rem;
        color: #667781;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 2px;
    }

    .msg-me .msg-time i {
        color: #53bdeb;
    }

    /* Blue tick simulate */

    .chat-input-area {
        background: #f0f2f5;
        padding: 10px 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 10;
    }

    .msg-input-wrapper {
        background: white;
        border-radius: 20px;
        display: flex;
        align-items: center;
        flex-grow: 1;
        padding: 6px 15px;
        border: 1px solid #e0e0e0;
    }

    .msg-input-wrapper input {
        border: none;
        outline: none;
        width: 100%;
        font-size: 0.9rem;
    }

    .btn-icon {
        background: transparent;
        border: none;
        color: #54656f;
        font-size: 1.2rem;
        padding: 0 5px;
        transition: color 0.2s;
    }

    .btn-icon:hover {
        color: #00a884;
    }

    #chatBox::-webkit-scrollbar {
        width: 6px;
    }

    #chatBox::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 10px;
    }

    .contact-list::-webkit-scrollbar {
        width: 4px;
    }

    .contact-list::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row g-0">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
            <div class="messenger-container shadow d-flex w-100 bg-white mx-auto" style="max-width: 1200px;">
                <!-- Sidebar -->
                <div class="contacts-panel <?= $active_chat ? 'hide-mobile' : '' ?>">
                    <div class="contacts-header">
                        <img src="/online-rishta-system/assets/images/uploads/<?= $curData['profile_pic'] ?? 'default.jpg' ?>"
                            class="contact-avatar ms-1" style="width: 40px; height: 40px;"
                            onclick="window.location.href='profile.php'" style="cursor:pointer">
                        <div class="ms-auto d-flex gap-3 text-muted fs-5">
                            <a href="search.php" class="text-muted"><i class="bi bi-chat-left-text"
                                    title="New Chat"></i></a>
                            <div class="dropdown">
                                <i class="bi bi-three-dots-vertical cursor-pointer" data-bs-toggle="dropdown"
                                    title="Menu"></i>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                    <li><a class="dropdown-item" href="profile.php">Settings</a></li>
                                    <li><a class="dropdown-item" href="/online-rishta-system/logout.php">Log out</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="contact-search">
                        <i class="bi bi-search text-muted"></i>
                        <input type="text" id="contactSearchInput" placeholder="Search or start new chat"
                            oninput="filterContacts()">
                    </div>
                    <div class="contact-list" id="contactList">
                        <?php if (empty($contacts)): ?>
                            <div class="text-center p-5 opacity-50">
                                <p class="small text-muted">No conversations found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($contacts as $c):
                                $unread = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                                $unread->execute([$c['id'], $user_id]);
                                $unreadCount = $unread->fetchColumn();
                                $isActive = ($active_chat == $c['id']);
                                ?>
                                <a href="chat.php?user=<?= $c['id'] ?>" class="contact-item <?= $isActive ? 'active' : '' ?>">
                                    <img src="/online-rishta-system/assets/images/uploads/<?= $c['profile_pic'] ?: 'default.jpg' ?>"
                                        class="contact-avatar">
                                    <div class="ms-3 min-w-0 flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0 text-dark fw-bold text-truncate contact-name"
                                                style="font-size: 0.95rem;">
                                                <?= htmlspecialchars(explode(' ', $c['full_name'])[0]) ?>
                                            </h6>
                                            <small class="text-muted"
                                                style="font-size: 0.7rem;"><?= $c['last_interaction'] ? date('h:i A', strtotime($c['last_interaction'])) : '' ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="mb-0 text-muted small text-truncate" style="max-width: 180px;">
                                                <?php if ($unreadCount > 0): ?>
                                                    <span class="text-success fw-bold"><?= $unreadCount ?> new messages</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($c['last_msg'] ?: 'Tap to chat') ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Main -->
                <div class="chat-main flex-grow-1">
                    <?php if (!$active_chat): ?>
                        <div
                            class="h-100 d-flex flex-column align-items-center justify-content-center text-center p-5 bg-light border-start border-1">
                            <i class="bi bi-whatsapp display-1 text-muted opacity-25 mb-4"></i>
                            <h4 class="fw-normal text-muted">WhatsApp for Web</h4>
                            <p class="text-muted mx-auto" style="max-width: 400px; font-size: 0.9rem;">Send and receive
                                messages without keeping your phone online. Matches are end-to-end encrypted.</p>
                            <span class="badge bg-secondary rounded-pill mt-3"><i class="bi bi-shield-lock-fill me-1"></i>
                                End-to-end encrypted</span>
                        </div>
                    <?php else: ?>
                        <!-- Header -->
                        <div class="chat-header">
                            <div class="d-flex align-items-center">
                                <a href="chat.php" class="text-muted me-3 d-lg-none"><i
                                        class="bi bi-arrow-left fs-4"></i></a>
                                <img src="/online-rishta-system/assets/images/uploads/<?= $active_user_details['profile_pic'] ?: 'default.jpg' ?>"
                                    class="contact-avatar" style="width: 40px; height: 40px;">
                                <div class="ms-3 cursor-pointer"
                                    onclick="window.location.href='view_profile.php?id=<?= $active_chat ?>'">
                                    <h6 class="mb-0 text-dark fw-bold text-truncate" style="max-width: 150px;">
                                        <?= htmlspecialchars($active_user_details['full_name']) ?>
                                    </h6>
                                    <small class="text-muted d-block fw-bold text-success" id="partnerStatusText"
                                        style="font-size: 0.7rem;">Loading...</small>
                                </div>
                            </div>
                            <div class="d-flex gap-3 text-muted fs-5 align-items-center">
                                <i class="bi bi-search cursor-pointer"
                                    onclick="document.getElementById('contactSearchInput').focus()"></i>
                                <div class="dropdown">
                                    <i class="bi bi-three-dots-vertical cursor-pointer" data-bs-toggle="dropdown"></i>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                        <li><a class="dropdown-item" href="view_profile.php?id=<?= $active_chat ?>">Contact
                                                info</a></li>
                                        <li><a class="dropdown-item" href="#"
                                                onclick="chatBox.innerHTML='<div class=\'text-center p-3\'>Chat Cleared internally...</div>'">Clear
                                                messages</a></li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item text-danger" href="support.php">Report / Block user</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Messages Box -->
                        <div id="chatBox">
                            <div class="text-center my-2">
                                <span class="badge bg-warning text-dark opacity-75 shadow-sm px-3 py-2 fw-normal"><i
                                        class="bi bi-shield-lock-fill text-warning-emphasis"></i> Messages are end-to-end
                                    encrypted. No one outside of this chat, not even ERishta Agent, can read or listen to
                                    them.</span>
                            </div>
                            <!-- Messages will be populated by AJAX -->
                        </div>

                        <div id="emojiPicker" class="d-none bg-white border rounded shadow p-2"
                            style="position: absolute; bottom: 70px; left: 20px; z-index: 100; max-width: 300px; max-height: 200px; overflow-y:auto; display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px; font-size: 1.5rem; cursor: pointer;">
                            <span onclick="insertEmoji('😊')">😊</span><span onclick="insertEmoji('😂')">😂</span><span
                                onclick="insertEmoji('❤️')">❤️</span><span onclick="insertEmoji('😍')">😍</span>
                            <span onclick="insertEmoji('🙏')">🙏</span><span onclick="insertEmoji('👍')">👍</span><span
                                onclick="insertEmoji('😢')">😢</span><span onclick="insertEmoji('🔥')">🔥</span>
                            <span onclick="insertEmoji('🥰')">🥰</span><span onclick="insertEmoji('😭')">😭</span><span
                                onclick="insertEmoji('😎')">😎</span><span onclick="insertEmoji('🥺')">🥺</span>
                            <span onclick="insertEmoji('👏')">👏</span><span onclick="insertEmoji('💯')">💯</span><span
                                onclick="insertEmoji('✨')">✨</span><span onclick="insertEmoji('🤔')">🤔</span>
                        </div>

                        <!-- Typing Area -->
                        <div class="chat-input-area position-relative">
                            <button class="btn-icon" title="Emoji"
                                onclick="document.getElementById('emojiPicker').classList.toggle('d-none')"><i
                                    class="bi bi-emoji-smile"></i></button>
                            <input type="file" id="fileInput" class="d-none" accept="image/*, .pdf, .doc, .docx"
                                onchange="sendFile(this)">
                            <button class="btn-icon" title="Attach file"
                                onclick="document.getElementById('fileInput').click()"><i
                                    class="bi bi-paperclip"></i></button>
                            <div class="msg-input-wrapper">
                                <input type="text" id="msgInput" placeholder="Type a message" autocomplete="off"
                                    onkeypress="handleKey(event)" oninput="handleTyping()">
                            </div>
                            <button class="btn-icon d-none" id="sendBtn" onclick="sendMessage()"><i
                                    class="bi bi-send-fill text-primary"></i></button>
                            <button class="btn-icon" id="micBtn"><i class="bi bi-mic"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .cursor-pointer {
        cursor: pointer;
    }
</style>

<script>
    const activeChat = <?= $active_chat ? $active_chat : 'null' ?>;
    const userId = <?= $user_id ?>;
    const chatBox = document.getElementById('chatBox');
    const msgInput = document.getElementById('msgInput');
    const sendBtn = document.getElementById('sendBtn');
    const micBtn = document.getElementById('micBtn');

    // Handle Mic vs Send icon
    if (msgInput) {
        msgInput.addEventListener('input', function () {
            if (this.value.trim().length > 0) {
                sendBtn.classList.remove('d-none');
                micBtn.classList.add('d-none');
            } else {
                sendBtn.classList.add('d-none');
                micBtn.classList.remove('d-none');
            }
        });
    }

    function renderMessage(msg) {
        const isMe = (msg.sender_id == userId);
        const bubbleClass = isMe ? 'msg-me' : 'msg-you';
        // Mocking read receipt for UI
        const readIcon = isMe ? (msg.is_read == 1 ? '<i class="bi bi-check2-all text-primary ms-1"></i>' : '<i class="bi bi-check2 ms-1"></i>') : '';

        let attachmentHtml = '';
        if (msg.attachment_url) {
            if (msg.attachment_url.match(/\.(jpeg|jpg|gif|png)$/i)) {
                attachmentHtml = `<img src="/online-rishta-system/assets/chat_uploads/${msg.attachment_url}" class="img-fluid rounded mb-2" style="max-height: 200px; max-width: 100%;">`;
            } else {
                attachmentHtml = `<a href="/online-rishta-system/assets/chat_uploads/${msg.attachment_url}" target="_blank" class="d-inline-block p-2 bg-light rounded text-decoration-none mb-2"><i class="bi bi-file-earmark-text"></i> ${msg.attachment_url.split('_').pop()}</a>`;
            }
        }

        let html = `
            <div class="d-flex flex-column ${isMe ? 'align-items-end' : 'align-items-start'}">
                <div class="msg-bubble ${bubbleClass}">
                    ${attachmentHtml}
                    ${msg.message_text ? msg.message_text.replace(/\n/g, '<br>') : ''}
                    <div class="msg-time">
                        ${msg.formatted_time} ${readIcon}
                    </div>
                </div>
            </div>
        `;
        return html;
    }

    function fetchMessages() {
        if (!activeChat) return;
        fetch(`ajax_chat.php?action=fetch_messages&active_chat=${activeChat}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                // Update Partner Status
                let ps = document.getElementById('partnerStatusText');
                if (ps && data.partner_last_seen) {
                    if (data.partner_last_seen === 'Online' || data.partner_last_seen === 'typing...') {
                        ps.className = 'text-success d-block fw-bold';
                    } else {
                        ps.className = 'text-muted d-block';
                    }
                    ps.innerText = data.partner_last_seen;
                }

                let isBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 10;

                // Clear Box except warning message
                const msgsOnly = data.messages.map(m => renderMessage(m)).join('');
                const warningHTML = `<div class="text-center my-2"><span class="badge bg-warning text-dark opacity-100 shadow-sm px-3 py-2 fw-normal" style="background-color: #ffeb3b !important;"><i class="bi bi-shield-lock-fill"></i> Messages are end-to-end encrypted.</span></div>`;

                chatBox.innerHTML = warningHTML + msgsOnly;

                if (isBottom) setTimeout(() => scrollToBottom(), 100);
            }).catch(console.error);
    }

    function sendMessage() {
        if (!activeChat || !msgInput.value.trim()) return;

        const txt = msgInput.value.trim();
        msgInput.value = '';
        msgInput.dispatchEvent(new Event('input')); // Reset icons

        // Optimistic UI update
        const tempMsg = {
            sender_id: userId,
            message_text: txt,
            formatted_time: 'Sending...',
            is_read: 0
        };
        chatBox.insertAdjacentHTML('beforeend', renderMessage(tempMsg));
        scrollToBottom();

        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('active_chat', activeChat);
        fd.append('message_text', txt);

        fetch('ajax_chat.php', { method: 'POST', body: fd })
             .then(res => res.json())
             .then(data => {
                 if (data.success) fetchMessages();
                 else if (data.error) alert(data.error);
             }).catch(console.error);
    }

    function handleKey(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
        }
    }

    function scrollToBottom() {
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }

    let typingTimer;
    function handleTyping() {
        clearTimeout(typingTimer);
        const fd = new FormData();
        fd.append('action', 'typing');
        fd.append('active_chat', activeChat);
        fetch('ajax_chat.php', { method: 'POST', body: fd }).catch(() => null);
        typingTimer = setTimeout(() => { }, 2000);
    }

    function sendFile(input) {
        if (!input.files || input.files.length === 0 || !activeChat) return;
        const file = input.files[0];

        // Optimistic UI update
        const tempMsg = {
            sender_id: userId,
            message_text: '',
            attachment_url: file.name,
            formatted_time: 'Sending...',
            is_read: 0
        };
        chatBox.insertAdjacentHTML('beforeend', renderMessage(tempMsg));
        scrollToBottom();

        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('active_chat', activeChat);
        fd.append('attachment', file);

        fetch('ajax_chat.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) fetchMessages();
                input.value = '';
            }).catch(console.error);
    }

    function filterContacts() {
        const query = document.getElementById('contactSearchInput').value.toLowerCase();
        const items = document.querySelectorAll('#contactList .contact-item');
        items.forEach(item => {
            const name = item.querySelector('.contact-name').innerText.toLowerCase();
            if (name.includes(query)) item.style.display = 'flex';
            else item.style.display = 'none';
        });
    }

    function insertEmoji(emoji) {
        msgInput.value += emoji;
        msgInput.focus();
        document.getElementById('emojiPicker').classList.add('d-none');
        msgInput.dispatchEvent(new Event('input'));
    }

    // Initial load & Polling
    if (activeChat) {
        fetchMessages();
        setInterval(fetchMessages, 3000);
    }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>