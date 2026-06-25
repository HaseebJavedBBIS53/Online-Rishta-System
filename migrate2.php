<?php
require 'config/database.php';

try {
    // 1. Posts (Social Wall)
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(50) DEFAULT 'General',
        visibility ENUM('Public', 'Connections') DEFAULT 'Public',
        status ENUM('Active', 'Deleted', 'Blocked') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 2. Post Likes
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Post Comments
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        comment_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES post_comments(id) ON DELETE CASCADE
    )");
    
    // 4. Update messages table for extra features (Attachment, Reaction, Deleted)
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN attachment_url VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE messages ADD COLUMN reaction VARCHAR(10) DEFAULT NULL");
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {
        // columns might already exist
    }

    echo "Migrations applied successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
