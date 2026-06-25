<?php
// user/process/meeting_actions.php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/functions.php';

require_login();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = intval($_POST['meeting_id']);
    $action = $_POST['action'];

    // Fetch meeting details to verify ownership
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        set_flash("Meeting request not found.", "danger");
        header("Location: ../meetings.php");
        exit();
    }

    switch ($action) {
        case 'accept':
            // Only receiver can accept
            if ($meeting['receiver_id'] == $user_id && $meeting['status'] === 'Pending User Response') {
                $pdo->prepare("UPDATE meetings SET status = 'Waiting for Admin Approval' WHERE id = ?")
                    ->execute([$meeting_id]);
                set_flash("Meeting accepted! Now waiting for final Admin safety approval.", "success");
            }
            break;

        case 'reject':
            // Only receiver can reject
            if ($meeting['receiver_id'] == $user_id && $meeting['status'] === 'Pending User Response') {
                $reason = sanitize_input($_POST['reason'] ?? '');
                $pdo->prepare("UPDATE meetings SET status = 'Rejected by Receiver', rejection_reason = ? WHERE id = ?")
                    ->execute([$reason, $meeting_id]);
                set_flash("Meeting request rejected.", "info");
            }
            break;

        case 'cancel':
            // Sender can cancel if pending
            if ($meeting['sender_id'] == $user_id && in_array($meeting['status'], ['Pending User Response', 'Waiting for Admin Approval'])) {
                $pdo->prepare("UPDATE meetings SET status = 'Cancelled' WHERE id = ?")
                    ->execute([$meeting_id]);
                set_flash("Meeting request cancelled.", "info");
            } 
            // Either party can cancel an approved meeting
            elseif (($meeting['sender_id'] == $user_id || $meeting['receiver_id'] == $user_id) && $meeting['status'] === 'Approved by Admin') {
                $pdo->prepare("UPDATE meetings SET status = 'Cancelled' WHERE id = ?")
                    ->execute([$meeting_id]);
                set_flash("Meeting has been successfully cancelled.", "info");
            }
            break;
    }

    header("Location: ../meetings.php");
    exit();
}
