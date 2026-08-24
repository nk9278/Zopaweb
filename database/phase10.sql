-- /database/phase10.sql
-- ZopaWeb Phase 10 Database Schema Additions (Lead Capture & Conversion)

START TRANSACTION;

-- Rename enquiries to leads
RENAME TABLE `enquiries` TO `leads`;

-- Alter leads table to add missing fields
ALTER TABLE `leads`
  ADD COLUMN `preferred_time` varchar(50) DEFAULT NULL AFTER `event_date`,
  ADD COLUMN `lead_type` enum('quote','appointment','enquiry') NOT NULL DEFAULT 'enquiry' AFTER `message`,
  ADD COLUMN `source` enum('website_form','whatsapp','phone','cta','other') NOT NULL DEFAULT 'website_form' AFTER `lead_type`,
  ADD COLUMN `notes` text DEFAULT NULL AFTER `status`,
  ADD COLUMN `deleted_at` datetime DEFAULT NULL AFTER `updated_at`;

-- Change event_date to preferred_date logically
ALTER TABLE `leads` CHANGE `event_date` `preferred_date` date DEFAULT NULL;

-- Make sure whatsapp column exists (it already does from Phase 1)

-- --------------------------------------------------------
-- Table structure for table `lead_events` (Analytics Foundation)
CREATE TABLE `lead_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `page_path` varchar(255) DEFAULT NULL,
  `section_type` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `website_id` (`website_id`),
  KEY `event_type` (`event_type`),
  CONSTRAINT `lead_events_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Alter website_themes to support WhatsApp floating button settings without needing a new table
ALTER TABLE `website_themes`
  ADD COLUMN `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`));

COMMIT;
