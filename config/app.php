<?php
// config/app.php

// Application environment (development or production)
define('APP_ENV', 'development');
define('APP_URL', 'http://localhost:8000'); // Adjust as needed

// Session Configuration
define('SESSION_LIFETIME', 86400); // 1 day in seconds
define('SESSION_NAME', 'ZopaWebSession');

// Error reporting based on environment
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    // Log errors to a file
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../storage/logs/php_error.log');
}
