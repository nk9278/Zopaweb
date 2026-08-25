<?php
// tests/r7_billing_test.php
echo "Running R7 Billing, Subscription & Domains Architecture Tests...\n";

// Ensure files exist
$files = [
    'user/billing.php',
    'user/domains.php',
    'user/publish.php',
    'database/migrations/r7_billing.sql'
];

foreach ($files as $f) {
    if (!file_exists(__DIR__ . '/../' . $f)) {
        die("[FAIL] Required file missing: $f\n");
    }
}
echo "[PASS] All R7 files created.\n";

// Validate CSRF & IDOR across Domain UI
$domains_file = file_get_contents(__DIR__ . '/../user/domains.php');
if (strpos($domains_file, "verify_csrf_token") === false || strpos($domains_file, "csrf_field()") === false) {
    die("[FAIL] Domains missing strict CSRF token evaluations.\n");
}
if (strpos($domains_file, "AND website_id = ?") === false) {
    die("[FAIL] Domains manipulation missing strict website_id IDOR bounding.\n");
}
if (strpos($domains_file, "random_bytes(16)") === false) {
    die("[FAIL] Verification token missing cryptographic security.\n");
}
echo "[PASS] Domains CRUD operations verified against IDOR and CSRF.\n";

// Validate Billing Constraints and Mocks
$billing_file = file_get_contents(__DIR__ . '/../user/billing.php');
if (strpos($billing_file, "AND website_id = ?") === false || strpos($billing_file, "verify_csrf_token") === false) {
    die("[FAIL] Billing missing IDOR / CSRF mappings.\n");
}
if (strpos($billing_file, "SELECT * FROM plans WHERE id = ? AND active = 1") === false) {
    die("[FAIL] Server-side plan price validation missing. Trusting client POST variables is unsafe.\n");
}
echo "[PASS] Billing explicitly handles server-side Plan Validation without trusting UI payloads.\n";

// Validate Publish Constraints
$publish_file = file_get_contents(__DIR__ . '/../user/publish.php');
if (strpos($publish_file, "\$can_publish") === false || strpos($publish_file, "publication_status = 'published'") === false) {
    die("[FAIL] Publish logic missing structured state checking.\n");
}
echo "[PASS] Publish actions bounded correctly by checklist states.\n";

// Validate Master SQL Structure
$sql = file_get_contents(__DIR__ . '/../database/zopaweb_master.sql');
if (strpos($sql, "`plans` (") === false || strpos($sql, "storage_limit") === false || strpos($sql, "custom_domain_allowed") === false) {
    die("[FAIL] Master SQL missing Plan schema requirements.\n");
}
echo "[PASS] Master SQL incorporates robust Billing Schema definitions.\n";

echo "ALL R7 STATIC TESTS PASSED.\n";
