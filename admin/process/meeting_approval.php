<?php
// admin/process/meeting_approval.php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/functions.php';

require_permission('manage_meetings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = intval($_POST['meeting_id']);
    $action = $_POST['action'];

    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        set_flash("Meeting request not found.", "danger");
        header("Location: ../meetings.php");
        exit();
    }

    if ($action === 'approve' && $meeting['status'] === 'Waiting for Admin Approval') {
        $pdo->prepare("UPDATE meetings SET status = 'Approved by Admin' WHERE id = ?")
            ->execute([$meeting_id]);
        set_flash("Meeting approved and both users notified.", "success");
    } elseif ($action === 'reject' && $meeting['status'] === 'Waiting for Admin Approval') {
        $reason = sanitize_input($_POST['reason'] ?? 'Safety standards not met.');
        $pdo->prepare("UPDATE meetings SET status = 'Rejected by Admin', rejection_reason = ? WHERE id = ?")
            ->execute([$reason, $meeting_id]);
        set_flash("Meeting rejected.", "warning");
    } elseif ($action === 'cancel' && $meeting['status'] === 'Approved by Admin') {
        $pdo->prepare("UPDATE meetings SET status = 'Cancelled' WHERE id = ?")
            ->execute([$meeting_id]);
        set_flash("Meeting has been cancelled.", "warning");
    } else {
        set_flash("Invalid action or meeting status.", "danger");
    }

    header("Location: ../meetings.php");
    exit();
}
