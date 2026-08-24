-- /database/migrations/r12_hostinger.sql
-- ZopaWeb R12 Hostinger Integration Data Architecture

START TRANSACTION;

ALTER TABLE `domains`
  ADD COLUMN `provider` varchar(50) DEFAULT 'manual' AFTER `registrar`,
  ADD COLUMN `provider_domain_id` varchar(255) DEFAULT NULL AFTER `provider`,
  ADD COLUMN `provider_status` varchar(50) DEFAULT NULL AFTER `provider_domain_id`,
  ADD COLUMN `provider_order_id` varchar(255) DEFAULT NULL AFTER `provider_status`;

COMMIT;
