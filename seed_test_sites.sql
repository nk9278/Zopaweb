START TRANSACTION;
INSERT INTO `users` (`role`, `name`, `email`, `password_hash`, `status`) VALUES
('user', 'Test User A', 'a@test.com', 'pwd', 'active'),
('user', 'Test User B', 'b@test.com', 'pwd', 'active');

INSERT INTO `websites` (`user_id`, `website_name`, `website_slug`, `template_id`, `template_version`, `status`) VALUES
((SELECT id FROM users WHERE email='a@test.com'), 'User A Beauty', 'user-a', 1, '1.0.0', 'active'),
((SELECT id FROM users WHERE email='b@test.com'), 'User B Glam', 'user-b', 2, '1.0.0', 'active');
COMMIT;
