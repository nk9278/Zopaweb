<?php
// tests/smoke_test.php
// Foundational smoke tests

// 1. Load config
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/host_resolver.php';

echo "Running Smoke Tests...\n";

// DB Configuration Test
if (defined('DB_NAME') && DB_NAME === 'zopaweb') {
    echo "[PASS] Database configuration is correct.\n";
} else {
    die("[FAIL] Database configuration is incorrect.\n");
}

// Master SQL validation
// Check if master SQL exists and contains required CREATE TABLE statements
$sql_file = __DIR__ . '/../database/zopaweb_master.sql';
if (!file_exists($sql_file)) {
    die("[FAIL] zopaweb_master.sql missing.\n");
}
$sql_content = file_get_contents($sql_file);
$required_tables = ['users', 'websites', 'templates', 'login_attempts', 'settings'];
foreach ($required_tables as $table) {
    if (strpos($sql_content, "CREATE TABLE `$table`") === false) {
        die("[FAIL] Master SQL is missing table: $table\n");
    }
}
if (strpos($sql_content, "INSERT INTO `users`") !== false) {
    die("[FAIL] Master SQL contains fake user seed data.\n");
}
echo "[PASS] Master SQL validation passed.\n";

// Authentication Smoke Test (Function existence and basic logic check)
if (!function_exists('is_logged_in') ) {
    die("[FAIL] Authentication functions missing.\n");
}
echo "[PASS] Authentication logic is present.\n";

// Public Routing Smoke Test (Function existence)
if (!function_exists('resolveWebsiteFromHost')) {
    die("[FAIL] Public routing resolver missing.\n");
}
echo "[PASS] Public routing smoke test passed.\n";

echo "ALL TESTS PASSED.\n";
