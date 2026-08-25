-- /database/migrations/r4_media.sql
-- ZopaWeb R4 Media Architecture Migration

START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for table `media`
CREATE TABLE `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_extension` varchar(10) NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `media_type` enum('image','video','document') NOT NULL DEFAULT 'image',
  `alt_text` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `has_webp` tinyint(1) NOT NULL DEFAULT 0,
  `has_thumbnail` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','deleted') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `website_id` (`website_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Add media_id references to existing R2 tables
-- (Retaining image_url fields for fallback/legacy compatibility temporarily)

ALTER TABLE `gallery_items`
  ADD COLUMN `media_id` int(11) DEFAULT NULL AFTER `website_id`,
  ADD CONSTRAINT `gallery_items_media_fk` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `services`
  ADD COLUMN `media_id` int(11) DEFAULT NULL AFTER `price`,
  ADD CONSTRAINT `services_media_fk` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `reviews`
  ADD COLUMN `media_id` int(11) DEFAULT NULL AFTER `rating`,
  ADD CONSTRAINT `reviews_media_fk` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `business_profiles`
  ADD COLUMN `logo_media_id` int(11) DEFAULT NULL AFTER `city`,
  ADD COLUMN `hero_media_id` int(11) DEFAULT NULL AFTER `logo_media_id`,
  ADD CONSTRAINT `bp_logo_media_fk` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bp_hero_media_fk` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

COMMIT;
