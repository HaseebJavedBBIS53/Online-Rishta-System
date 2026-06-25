<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';

require_login();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['verification_doc']['tmp_name'];
        $original_name = basename($_FILES['verification_doc']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($ext, $allowed_exts)) {
            $new_name = 'verify_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = dirname(__DIR__, 2) . '/assets/images/uploads/' . $new_name;
            
            if (move_uploaded_file($tmp_name, $upload_path)) {
                // Update user_profiles table
                $stmt = $pdo->prepare("UPDATE user_profiles SET verification_doc = ? WHERE user_id = ?");
                $stmt->execute([$new_name, $user_id]);
                
                set_flash("Verification document submitted successfully! Admin will review it shortly.", "success");
            } else {
                set_flash("Failed to move uploaded file. Please check folder permissions.", "danger");
            }
        } else {
            set_flash("Invalid file type. Please upload JPG, PNG, or PDF.", "warning");
        }
    } else {
        set_flash("No file uploaded or error during upload.", "danger");
    }
    
    header("Location: ../profile.php#verify");
    exit();
} else {
    header("Location: ../profile.php");
    exit();
}
?>
