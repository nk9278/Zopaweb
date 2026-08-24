-- /database/seed.sql
-- Development data for ZopaWeb Phase 1

START TRANSACTION;

-- Admin user (password is 'password' - this is for dev only)
INSERT INTO `users` (`role`, `name`, `email`, `phone`, `password_hash`, `status`, `email_verified`, `phone_verified`) VALUES
('admin', 'Admin User', 'admin@zopaweb.com', '9876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 1, 1),
('user', 'Test Makeup Artist', 'user@test.com', '1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 1, 1);

-- Template Categories
INSERT INTO `template_categories` (`name`, `slug`, `description`, `status`) VALUES
('Makeup Artist', 'makeup-artist', 'Professional templates for makeup artists', 'active');

-- Templates
INSERT INTO `templates` (`category_id`, `name`, `slug`, `description`, `folder_key`, `status`) VALUES
(1, 'Elegance', 'elegance', 'A clean, elegant theme for bridal makeup.', 'theme_elegance', 'active'),
(1, 'Glamour', 'glamour', 'Bold and glamorous portfolio theme.', 'theme_glamour', 'active');

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('platform_name', 'ZopaWeb'),
('tagline', 'Your Work. Your Website.'),
('support_whatsapp', '+910000000000'),
('support_phone', '+910000000000'),
('default_currency', 'INR'),
('timezone', 'Asia/Kolkata'),
('paid_plan_price', '2999'),
('upload_limit', '100');

COMMIT;
