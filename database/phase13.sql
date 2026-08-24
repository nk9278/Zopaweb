-- /database/phase13.sql
-- ZopaWeb Phase 13 Database Schema Additions (Security & Abuse Prevention)

START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for table `rate_limits`
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_endpoint_time` (`ip_address`, `endpoint`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
