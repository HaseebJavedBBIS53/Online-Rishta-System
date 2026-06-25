<?php
// c:/xampp/htdocs/online-rishta-system/admin/migrate_advanced_features.php
require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo->beginTransaction();

    // 1. Add gender-specific columns to user_profiles
    $columns = [
        "beard_status ENUM('Yes', 'No', 'Trimmed') DEFAULT 'No'",
        "living_arrangement ENUM('Own House', 'Rented', 'Family House') DEFAULT 'Family House'",
        "responsibility_role VARCHAR(100) DEFAULT NULL",
        "relocation_willingness ENUM('Yes', 'No', 'Maybe') DEFAULT 'Maybe'",
        "career_stability VARCHAR(100) DEFAULT NULL",
        "hijab_preference ENUM('Hijab', 'Niqab', 'None') DEFAULT 'None'",
        "cooking_skill ENUM('Basic', 'Moderate', 'Expert') DEFAULT 'Basic'",
        "working_status ENUM('Working', 'Not Working', 'Planning to Work') DEFAULT 'Not Working'",
        "working_after_marriage ENUM('Yes', 'No', 'Depends') DEFAULT 'Depends'",
        "guardian_name VARCHAR(150) DEFAULT NULL",
        "guardian_contact VARCHAR(20) DEFAULT NULL",
        "household_skill ENUM('Basic', 'Moderate', 'Expert') DEFAULT 'Basic'"
    ];

    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE user_profiles ADD COLUMN $col");
        } catch (Exception $e) {
            // Probably already exists
        }
    }

    // 2. Create meetings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        meeting_date DATE NOT NULL,
        meeting_time TIME NOT NULL,
        meeting_type ENUM('Online', 'Physical') DEFAULT 'Physical',
        location TEXT DEFAULT NULL,
        meeting_link TEXT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        status ENUM('Pending User Response', 'Accepted by Receiver', 'Rejected by Receiver', 'Waiting for Admin Approval', 'Approved by Admin', 'Rejected by Admin', 'Completed', 'Cancelled') DEFAULT 'Pending User Response',
        rejection_reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. RBAC Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        perm_key VARCHAR(50) NOT NULL UNIQUE,
        perm_name VARCHAR(100) NOT NULL,
        module VARCHAR(50) DEFAULT 'General'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT NOT NULL,
        perm_id INT NOT NULL,
        PRIMARY KEY (role_id, perm_id),
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (perm_id) REFERENCES permissions(id) ON DELETE CASCADE
    )");

    // 4. Update users table for RBAC
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL AFTER role");
        $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL");
    } catch (Exception $e) {}

    // 5. Seed Initial Data
    $pdo->exec("INSERT IGNORE INTO roles (role_name, description) VALUES 
        ('Super Admin', 'Full system access'),
        ('Agent', 'Operational staff for profile management'),
        ('Moderator', 'Social feed and community safety'),
        ('Support Staff', 'Customer service and tickets'),
        ('Verification Officer', 'ID and profile validation'),
        ('Subscription Manager', 'Finance and plans')");

    $permissions = [
        ['manage_users', 'Full User Management', 'Users'],
        ['edit_profiles', 'Edit User Profiles', 'Users'],
        ['verify_profiles', 'Approve/Reject Verifications', 'Users'],
        ['manage_meetings', 'Admin Approval for Meetings', 'Meetings'],
        ['moderate_feed', 'Moderate Social Feed', 'Community'],
        ['manage_subscriptions', 'Manage Plans and Pricing', 'Finance'],
        ['view_analytics', 'View System Reports', 'Analytics'],
        ['manage_rbac', 'Manage Roles and Permissions', 'Admin'],
        ['manage_support', 'Handle Support Tickets', 'Support']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (perm_key, perm_name, module) VALUES (?, ?, ?)");
    foreach ($permissions as $p) {
        $stmt->execute($p);
    }

    // Give Super Admin all permissions
    $superAdminId = $pdo->query("SELECT id FROM roles WHERE role_name = 'Super Admin'")->fetchColumn();
    if ($superAdminId) {
        $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, perm_id) SELECT $superAdminId, id FROM permissions");
        
        // Assign existing Admin users to Super Admin role
        $pdo->exec("UPDATE users SET role_id = $superAdminId WHERE role = 'Admin'");
    }

    $pdo->commit();
    echo "Migration completed successfully!";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error during migration: " . $e->getMessage();
}
