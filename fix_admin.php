<?php
require_once __DIR__ . '/config/database.php';

$email = 'haseeb@gmail.com';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user to Admin and reset password
        $pdo->prepare("UPDATE users SET role = 'Admin', password = ?, failed_login_attempts = 0, lock_until = NULL, status = 'Active' WHERE id = ?")
            ->execute([$hashed_password, $user['id']]);
        echo "Successfully updated $email to Administrator role and reset password to: $password";
    } else {
        // Create new admin if user doesn't exist
        $pdo->prepare("INSERT INTO users (full_name, email, gender, dob, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute(['Haseeb Admin', $email, 'Male', '1990-01-01', $hashed_password, 'Admin', 'Active']);
        echo "Created new Admin account for $email with password: $password";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
