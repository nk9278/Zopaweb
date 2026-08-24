-- /database/migrations/r7_billing.sql
-- ZopaWeb R7 Billing, Subscription, and Custom Domain Migration

START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for table `plans`
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_interval` enum('monthly','yearly','lifetime') NOT NULL DEFAULT 'yearly',
  `storage_limit` bigint(20) NOT NULL DEFAULT 52428800, -- 50MB default
  `custom_domain_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed essential plans
INSERT INTO `plans` (`id`, `name`, `price`, `billing_interval`, `storage_limit`, `custom_domain_allowed`) VALUES
(1, 'Free', 0.00, 'lifetime', 52428800, 0), -- 50MB
(2, 'Pro', 2999.00, 'yearly', 1073741824, 1); -- 1GB

-- --------------------------------------------------------
-- Update subscriptions table to link to plans
ALTER TABLE `subscriptions`
  ADD COLUMN `plan_id` int(11) DEFAULT NULL AFTER `website_id`,
  ADD CONSTRAINT `subscriptions_plan_fk` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT;

-- Initialize existing subscriptions to Free plan fallback if needed
UPDATE `subscriptions` SET `plan_id` = 1 WHERE `plan_id` IS NULL;

-- --------------------------------------------------------
-- Update domains table for verification logic
ALTER TABLE `domains`
  ADD COLUMN `verification_token` varchar(255) DEFAULT NULL AFTER `domain_type`,
  ADD COLUMN `verification_status` enum('unverified','verified','failed') NOT NULL DEFAULT 'unverified' AFTER `verification_token`;

-- Clean up dangling media_id references from domains/payments which aren't logically sound for billing architecture
-- The earlier master SQL didn't add an explicit foreign key for domains.media_id, it was just a column. Let's drop it.
ALTER TABLE `domains` DROP COLUMN `media_id`;
ALTER TABLE `payments` DROP COLUMN `media_id`;

COMMIT;
