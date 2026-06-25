<?php
require_once __DIR__ . '/config/database.php';

try {
    // Drop all old/unnecessary feature columns to clean up schema
    $pdo->exec("ALTER TABLE subscriptions 
                DROP COLUMN IF EXISTS duration_days,
                DROP COLUMN IF EXISTS interest_request_limit,
                DROP COLUMN IF EXISTS contact_view_limit,
                DROP COLUMN IF EXISTS meeting_limit,
                DROP COLUMN IF EXISTS boost_count,
                DROP COLUMN IF EXISTS can_video_upload,
                DROP COLUMN IF EXISTS has_advanced_filters,
                DROP COLUMN IF EXISTS can_view_who_viewed,
                DROP COLUMN IF EXISTS can_post_community,
                DROP COLUMN IF EXISTS can_view_profile,
                DROP COLUMN IF EXISTS can_view_contact,
                DROP COLUMN IF EXISTS can_request_meeting,
                DROP COLUMN IF EXISTS can_advanced_search,
                DROP COLUMN IF EXISTS can_community_feed;");

    // Add accepted_request_limit if not exists
    try {
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN accepted_request_limit INT DEFAULT 0 AFTER profile_view_limit");
    } catch (Exception $e) {}

    // Set defaults
    $pdo->exec("UPDATE subscriptions SET plan_type = 'Free', profile_view_limit = 2, accepted_request_limit = 1, can_chat = 0 WHERE plan_name LIKE '%Free%'");
    $pdo->exec("UPDATE subscriptions SET plan_type = 'Standard', profile_view_limit = 100, accepted_request_limit = 10, can_chat = 1 WHERE plan_name LIKE '%Standard%'");
    $pdo->exec("UPDATE subscriptions SET plan_type = 'Premium', profile_view_limit = 999999, accepted_request_limit = 999999, can_chat = 1, can_highlight_profile = 1, can_boost_profile = 1 WHERE plan_name LIKE '%Premium%'");

    echo "Migration successful.";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}
