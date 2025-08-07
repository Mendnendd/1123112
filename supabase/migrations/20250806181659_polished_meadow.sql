-- Fix notifications table structure
-- Add missing priority column and other enhancements

-- Add missing columns to notifications table if they don't exist
ALTER TABLE `notifications` 
ADD COLUMN IF NOT EXISTS `priority` enum('LOW','NORMAL','HIGH','URGENT') DEFAULT 'NORMAL' AFTER `data`,
ADD COLUMN IF NOT EXISTS `category` enum('SYSTEM','TRADING','AI','SECURITY','PERFORMANCE') DEFAULT 'SYSTEM' AFTER `type`,
ADD COLUMN IF NOT EXISTS `expires_at` timestamp NULL DEFAULT NULL AFTER `read_at`;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_notifications_priority` ON `notifications` (`priority`, `created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_notifications_category` ON `notifications` (`category`, `created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_notifications_read_status` ON `notifications` (`read_at`, `created_at` DESC);

-- Update existing notifications to have default priority if NULL
UPDATE `notifications` SET `priority` = 'NORMAL' WHERE `priority` IS NULL;
UPDATE `notifications` SET `category` = 'SYSTEM' WHERE `category` IS NULL;