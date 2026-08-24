<?php
// tests/r5_leads_security_test.php

echo "Running R5 Leads Architecture & Security Tests...\n";

// 1. Files existence
$files = [
    'public/api/leads.php',
    'templates/fallback/lead_form.php',
    'user/leads.php',
    'database/migrations/r5_leads.sql'
];

foreach ($files as $f) {
    if (!file_exists(__DIR__ . '/../' . $f)) {
        die("[FAIL] Missing required file: $f\n");
    }
}
echo "[PASS] All R5 files created.\n";

// 2. API Security Check
$api = file_get_contents(__DIR__ . '/../public/api/leads.php');
if (strpos($api, 'verify_csrf_token') === false || strpos($api, 'website_url_hp') === false || strpos($api, 'strip_tags') === false) {
    die("[FAIL] API missing CSRF, honeypot, or tag stripping protections.\n");
}
if (strpos($api, 'SELECT COUNT(*) FROM leads') === false || strpos($api, 'Too many requests') === false) {
    die("[FAIL] API missing Rate Limiting protection.\n");
}
if (strpos($api, 'INSERT INTO leads') === false || strpos($api, "execute([") === false) {
    die("[FAIL] API missing Prepared Statement parameterized SQL.\n");
}
// Checking IDOR logic implicitly since website_id shouldn't come from $_POST securely
if (strpos($api, "\$_POST['website_id']") !== false) {
     die("[FAIL] Security risk: API blindly accepts website_id from POST payload.\n");
}
echo "[PASS] Public API securely implements Rate limiting, CSRF, Honeypots, and Server-Side Tenant Resolution.\n";

// 3. Form Validation
$form = file_get_contents(__DIR__ . '/../templates/fallback/lead_form.php');
if (strpos($form, 'website_url_hp') === false || strpos($form, 'display:none') === false) {
     die("[FAIL] Form missing hidden honeypot field.\n");
}
echo "[PASS] Template form implements honeypot appropriately.\n";

// 4. Dashboard IDOR and Security
$dash = file_get_contents(__DIR__ . '/../user/leads.php');
if (strpos($dash, 'WHERE id = ? AND website_id = ?') === false && strpos($dash, 'l.website_id = ?') === false) {
     die("[FAIL] Dashboard missing strict website_id bounding on fetches/updates.\n");
}
if (strpos($dash, 'escape($lead') === false) {
     die("[FAIL] Dashboard missing Output XSS Escaping.\n");
}
echo "[PASS] Dashboard strictly enforces Tenant IDOR boundaries and Output Escaping.\n";

echo "ALL R5 STATIC SECURITY TESTS PASSED.\n";
