-- /database/phase11.sql
-- ZopaWeb Phase 11 Database Schema Additions (SEO & Social Sharing)

START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for table `website_seo`
CREATE TABLE `website_seo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(500) DEFAULT NULL,
  `seo_keywords` varchar(500) DEFAULT NULL,
  `og_image_id` int(11) DEFAULT NULL,
  `twitter_card` enum('summary','summary_large_image') NOT NULL DEFAULT 'summary_large_image',
  `canonical_url` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `business_type` varchar(100) NOT NULL DEFAULT 'LocalBusiness',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `website_id` (`website_id`),
  KEY `og_image_id` (`og_image_id`),
  CONSTRAINT `website_seo_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `website_seo_ibfk_2` FOREIGN KEY (`og_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Add page-level SEO overrides
ALTER TABLE `pages`
  ADD COLUMN `seo_title` varchar(255) DEFAULT NULL AFTER `show_in_nav`,
  ADD COLUMN `seo_description` varchar(500) DEFAULT NULL AFTER `seo_title`,
  ADD COLUMN `og_title` varchar(255) DEFAULT NULL AFTER `seo_description`,
  ADD COLUMN `og_description` varchar(500) DEFAULT NULL AFTER `og_title`,
  ADD COLUMN `canonical_url` varchar(255) DEFAULT NULL AFTER `og_description`,
  ADD COLUMN `seo_image_id` int(11) DEFAULT NULL AFTER `seo_description`,
  ADD COLUMN `robots_index` tinyint(1) DEFAULT NULL AFTER `seo_image_id`,
  ADD COLUMN `robots_follow` tinyint(1) DEFAULT NULL AFTER `robots_index`,
  ADD CONSTRAINT `pages_seo_media_fk` FOREIGN KEY (`seo_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

COMMIT;
