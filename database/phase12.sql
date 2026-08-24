-- /database/phase12.sql
-- ZopaWeb Phase 12 Database Schema Additions (Subscription, Billing & Domains)

START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for table `plans`
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_interval` enum('monthly','yearly','lifetime') NOT NULL DEFAULT 'yearly',
  `storage_limit` bigint(20) NOT NULL DEFAULT 104857600, -- Default 100MB
  `custom_domain_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default plans
INSERT INTO `plans` (`name`, `slug`, `price`, `billing_interval`, `storage_limit`, `custom_domain_allowed`) VALUES
('Free', 'free', 0.00, 'yearly', 52428800, 0), -- 50MB
('Pro Annual', 'pro-yearly', 2999.00, 'yearly', 1073741824, 1), -- 1GB
('Pro Monthly', 'pro-monthly', 299.00, 'monthly', 1073741824, 1);

-- --------------------------------------------------------
-- Update domains table for DNS verification
ALTER TABLE `domains`
  ADD COLUMN `verification_token` varchar(255) DEFAULT NULL AFTER `domain_type`,
  ADD COLUMN `verification_status` enum('pending','verified','failed') NOT NULL DEFAULT 'pending' AFTER `verification_token`;

-- Add plan_id to subscriptions
ALTER TABLE `subscriptions`
  ADD COLUMN `plan_id` int(11) DEFAULT NULL AFTER `website_id`,
  ADD COLUMN `cancelled_at` datetime DEFAULT NULL AFTER `expiry_date`,
  ADD CONSTRAINT `subscriptions_plan_fk` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL;

COMMIT;
