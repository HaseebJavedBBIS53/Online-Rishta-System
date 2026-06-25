<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo->exec("ALTER TABLE subscriptions 
                ADD COLUMN meeting_limit INT DEFAULT 0 AFTER accepted_request_limit,
                ADD COLUMN can_video_upload TINYINT(1) DEFAULT 0,
                ADD COLUMN can_advanced_search TINYINT(1) DEFAULT 0,
                ADD COLUMN can_view_who_viewed TINYINT(1) DEFAULT 0,
                ADD COLUMN can_post_community TINYINT(1) DEFAULT 0;");
    
    // Update existing plans
    $pdo->exec("UPDATE subscriptions SET meeting_limit = 0, can_video_upload = 0, can_advanced_search = 0, can_view_who_viewed = 0, can_post_community = 0 WHERE plan_type = 'Free'");
    $pdo->exec("UPDATE subscriptions SET meeting_limit = 5, can_video_upload = 0, can_advanced_search = 0, can_view_who_viewed = 0, can_post_community = 0 WHERE plan_type = 'Standard'");
    $pdo->exec("UPDATE subscriptions SET meeting_limit = 10, can_video_upload = 1, can_advanced_search = 1, can_view_who_viewed = 1, can_post_community = 1 WHERE plan_type = 'Premium'");

    echo "Migration successful.";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}
