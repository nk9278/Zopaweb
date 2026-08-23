-- /database/phase7.sql
-- ZopaWeb Phase 7 Database Schema Additions (Theme Customization)

START TRANSACTION;

-- --------------------------------------------------------

-- Table structure for table `website_themes`
CREATE TABLE `website_themes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `website_id` int(11) NOT NULL,
  `preset` varchar(50) DEFAULT NULL,

  -- Colors
  `primary_color` varchar(7) DEFAULT NULL,
  `secondary_color` varchar(7) DEFAULT NULL,
  `accent_color` varchar(7) DEFAULT NULL,
  `background_color` varchar(7) DEFAULT NULL,
  `surface_color` varchar(7) DEFAULT NULL,
  `text_color` varchar(7) DEFAULT NULL,
  `heading_color` varchar(7) DEFAULT NULL,
  `muted_color` varchar(7) DEFAULT NULL,
  `button_color` varchar(7) DEFAULT NULL,
  `button_text_color` varchar(7) DEFAULT NULL,
  `border_color` varchar(7) DEFAULT NULL,

  -- Typography
  `heading_font` varchar(100) DEFAULT NULL,
  `body_font` varchar(100) DEFAULT NULL,
  `accent_font` varchar(100) DEFAULT NULL,
  `heading_scale` varchar(50) DEFAULT NULL,
  `body_scale` varchar(50) DEFAULT NULL,

  -- Styles
  `button_style` varchar(50) DEFAULT NULL,
  `border_radius` varchar(50) DEFAULT NULL,
  `shadow_style` varchar(50) DEFAULT NULL,
  `image_style` varchar(50) DEFAULT NULL,
  `card_style` varchar(50) DEFAULT NULL,

  -- Layout Variants
  `hero_variant` varchar(50) DEFAULT NULL,
  `navigation_variant` varchar(50) DEFAULT NULL,

  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `website_id` (`website_id`),
  CONSTRAINT `website_themes_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
