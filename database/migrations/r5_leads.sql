-- /database/migrations/r5_leads.sql
-- ZopaWeb R5 Leads & Enquiries Architecture Migration

START TRANSACTION;

-- Rename 'enquiries' table to 'leads' for consistent architecture naming (optional, but requested implicitly by prompt preferring 'leads')
-- We will just rename and restructure the existing 'enquiries' table to match R5 requirements.
RENAME TABLE `enquiries` TO `leads`;

ALTER TABLE `leads`
  DROP COLUMN `media_id`,
  DROP COLUMN `whatsapp`, -- Consolidating to just 'phone' to avoid duplicate UX
  DROP COLUMN `location`,
  CHANGE COLUMN `service` `service_id` int(11) DEFAULT NULL,
  CHANGE COLUMN `event_date` `preferred_date` date DEFAULT NULL,
  ADD COLUMN `preferred_time` varchar(50) DEFAULT NULL AFTER `preferred_date`,
  ADD COLUMN `source` varchar(50) DEFAULT 'direct' AFTER `message`,
  ADD COLUMN `form_type` varchar(50) DEFAULT 'inquiry' AFTER `source`,
  ADD COLUMN `ip_address` varchar(45) DEFAULT NULL AFTER `status`;

-- Update status enum to standard R5 values
ALTER TABLE `leads`
  MODIFY COLUMN `status` enum('new','contacted','converted','closed','spam') NOT NULL DEFAULT 'new';

-- Add constraints
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_service_fk` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

COMMIT;
