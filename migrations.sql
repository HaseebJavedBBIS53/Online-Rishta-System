-- Phase 9: Database Expansion Migrations

USE `online_rishta`;

-- 1. Update Users Table
ALTER TABLE `users` 
ADD COLUMN `last_ip` VARCHAR(45) DEFAULT NULL,
ADD COLUMN `timezone` VARCHAR(100) DEFAULT 'UTC',
ADD COLUMN `language` VARCHAR(10) DEFAULT 'en';

-- 2. Update User Profiles Table
ALTER TABLE `user_profiles` 
ADD COLUMN `marital_status` ENUM('Single', 'Divorced', 'Widowed', 'Separated') DEFAULT 'Single';

-- 3. User Settings Table
CREATE TABLE IF NOT EXISTS `user_settings` (
    `user_id` INT(11) NOT NULL,
    `profile_visibility` ENUM('Public', 'Private', 'Premium') DEFAULT 'Public',
    `can_contact` ENUM('All', 'Verified', 'Premium') DEFAULT 'All',
    `email_notifications` TINYINT(1) DEFAULT '1',
    `sms_notifications` TINYINT(1) DEFAULT '0',
    `app_notifications` TINYINT(1) DEFAULT '1',
    PRIMARY KEY (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    ALTER TABLE table_name
    `user_id` INT(11) NOT NULL,
    `plan_id` INT(11) NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_gateway` VARCHAR(50) DEFAULT 'PayPal',
    `status` ENUM('Pending', 'Completed', 'Failed', 'Cancelled') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`) REFERENCES `subscriptions` (`plan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Banned Words Table
CREATE TABLE IF NOT EXISTS `banned_words` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `word` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default banned words
INSERT IGNORE INTO `banned_words` (`word`) VALUES ('abuse'), ('scam'), ('fake'), ('nude'), ('sex');

-- 6. Support Tickets Table
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('Open', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Ticket Replies Table
CREATE TABLE IF NOT EXISTS `ticket_replies` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `sender_id` INT(11) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `audience` ENUM('All', 'Premium', 'Free') DEFAULT 'All',
    `target_location` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize user_settings for existing users
INSERT IGNORE INTO `user_settings` (`user_id`) SELECT `id` FROM `users` WHERE `role` = 'User';
