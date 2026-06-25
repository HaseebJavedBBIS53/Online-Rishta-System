-- Database Name: online_rishta
-- You can run these commands via phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS `online_rishta`;
USE `online_rishta`;

-- 1. Subscriptions Table
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `plan_id` int(11) NOT NULL AUTO_INCREMENT,
    `plan_name` varchar(100) NOT NULL,
    `price` decimal(10,2) NOT NULL DEFAULT '0.00',
    `duration_months` int(11) NOT NULL DEFAULT '1',
    `features` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default plans
INSERT IGNORE INTO `subscriptions` (`plan_id`, `plan_name`, `price`, `duration_months`, `features`) VALUES 
(1, 'Free', 0.00, 1200, '5 Profile Views/Day, Blurred Photos, Hidden Contact Info'),
(2, 'Premium (1 Month)', 19.99, 1, 'Unlimited Views, Unblurred Photos, Reveal Contact Info, Chat Access'),
(3, 'Premium (6 Months)', 89.99, 6, 'Unlimited Views, Unblurred Photos, Reveal Contact Info, Chat Access');

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `full_name` varchar(150) NOT NULL,
    `email` varchar(100) NOT NULL UNIQUE,
    `phone` varchar(20) DEFAULT NULL,
    `gender` enum('Male','Female','Prefer not to say') NOT NULL,
    `dob` date NOT NULL,
    `password` varchar(255) NOT NULL,
    `profile_pic` varchar(255) DEFAULT 'default.jpg',
    `photo_visibility` enum('All','Matched','Premium') DEFAULT 'All',
    `status` enum('Active','Suspended','Deleted') DEFAULT 'Active',
    `role` enum('User','Admin') DEFAULT 'User',
    `plan_id` int(11) DEFAULT '1',
    `failed_login_attempts` int(11) DEFAULT '0',
    `lock_until` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`plan_id`) REFERENCES `subscriptions` (`plan_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
-- Note: password uses password_hash() in PHP BCRYPT. Here is a generic hash for 'admin123'
INSERT IGNORE INTO `users` (`full_name`, `email`, `gender`, `dob`, `password`, `role`) VALUES 
('System Admin', 'admin@rishta.com', 'Prefer not to say', '1990-01-01', '$2y$10$tZ2E1kZp9jP.iIeXvYc7u.uC.v5X6a6kXv.1/Y3.7O8x.3k.E.', 'Admin');

-- 3. User Profiles Table
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `education` varchar(150) DEFAULT NULL,
    `religion` varchar(100) DEFAULT NULL,
    `profession` varchar(150) DEFAULT NULL,
    `income` varchar(100) DEFAULT NULL,
    `city` varchar(100) DEFAULT NULL,
    `bio` text,
    `is_verified` tinyint(1) DEFAULT '0',
    `verification_doc` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Partner Preferences Table
CREATE TABLE IF NOT EXISTS `partner_preferences` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `min_age` int(11) DEFAULT '18',
    `max_age` int(11) DEFAULT '50',
    `city` varchar(100) DEFAULT NULL,
    `education` varchar(150) DEFAULT NULL,
    `religion` varchar(100) DEFAULT NULL,
    `profession` varchar(150) DEFAULT NULL,
    `min_income` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Interests Table
CREATE TABLE IF NOT EXISTS `interests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sender_id` int(11) NOT NULL,
    `receiver_id` int(11) NOT NULL,
    `status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Messages Table
CREATE TABLE IF NOT EXISTS `messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sender_id` int(11) NOT NULL,
    `receiver_id` int(11) NOT NULL,
    `message_text` text NOT NULL,
    `is_read` tinyint(1) DEFAULT '0',
    `is_reported` tinyint(1) DEFAULT '0',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. User Subscriptions Table
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `plan_id` int(11) NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `status` enum('Active','Expired') DEFAULT 'Active',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`) REFERENCES `subscriptions` (`plan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Profile Views Table (For Limits)
CREATE TABLE IF NOT EXISTS `profile_views` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `viewer_id` int(11) NOT NULL,
    `viewed_id` int(11) NOT NULL,
    `view_date` date NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`viewed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Shortlists Table
CREATE TABLE IF NOT EXISTS `shortlists` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `profile_id` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`profile_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Reports Table
CREATE TABLE IF NOT EXISTS `reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `reported_by` int(11) NOT NULL,
    `reported_user` int(11) NOT NULL,
    `reason` varchar(255) NOT NULL,
    `item_type` enum('Profile','Message') NOT NULL,
    `item_id` int(11) DEFAULT NULL,
    `status` enum('Pending','Resolved') DEFAULT 'Pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reported_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Site Settings Table
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL UNIQUE,
    `setting_value` text,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES 
('site_name', 'Online Rishta System'),
('contact_email', 'admin@rishta.com'),
('free_views_limit', '5');
