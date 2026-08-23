START TRANSACTION;
INSERT INTO `users` (`role`, `name`, `email`, `password_hash`, `status`) VALUES
('user', 'Pooja Beauty', 'pooja@example.com', 'pwd', 'active');

INSERT INTO `websites` (`user_id`, `website_name`, `website_slug`, `template_id`, `template_version`, `status`, `publication_status`) VALUES
((SELECT id FROM users WHERE email='pooja@example.com'), 'Pooja Beauty Studio', 'pooja-beauty', 1, '1.0.0', 'active', 'unpublished');
COMMIT;
