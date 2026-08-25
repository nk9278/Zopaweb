-- /database/migrations/r6_seo.sql
-- ZopaWeb R6 SEO & Search Visibility Migration

START TRANSACTION;

-- Table structure for table `website_seo`
CREATE TABLE `website_seo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `default_seo_title` varchar(255) DEFAULT NULL,
  `default_meta_description` text DEFAULT NULL,
  `default_og_image_id` int(11) DEFAULT NULL,
  `search_engine_visibility` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `website_id` (`website_id`),
  KEY `default_og_image_id` (`default_og_image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add constraints
ALTER TABLE `website_seo`
  ADD CONSTRAINT `website_seo_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `website_seo_og_media_fk` FOREIGN KEY (`default_og_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

COMMIT;
