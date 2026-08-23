<?php
// config/app.php

// Application environment (development or production)
define('APP_ENV', 'development');
define('APP_URL', 'http://localhost:8000'); // Adjust as needed

// Platform Domain Configuration (Phase 5)
// Defines the core domain used to resolve subdomains. e.g. "zopaweb.com" or "localhost:8000" for dev.
define('PRIMARY_PLATFORM_DOMAIN', 'localhost:8000');

// Reserved Subdomains
// These subdomains are reserved for system use and cannot be used as client website slugs.
define('RESERVED_SUBDOMAINS', [
    'www', 'admin', 'api', 'app', 'mail', 'smtp', 'ftp', 'cdn', 'static', 'assets', 'support', 'help', 'status', 'auth', 'public'
]);

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
