<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
logout_user($pdo);
set_flash_message('success', 'You have been logged out successfully.');
redirect('/auth/login.php');
