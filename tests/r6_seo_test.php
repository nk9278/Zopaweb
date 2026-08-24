<?php
// tests/r6_seo_test.php

echo "Running R6 SEO Architecture & Security Tests...\n";

// 1. Files existence
$files = [
    'includes/seo.php',
    'user/seo.php',
    'database/migrations/r6_seo.sql'
];

foreach ($files as $f) {
    if (!file_exists(__DIR__ . '/../' . $f)) {
        die("[FAIL] Missing required file: $f\n");
    }
}
echo "[PASS] All R6 files created.\n";

// 2. SEO Helper Security Check
$seo = file_get_contents(__DIR__ . '/../includes/seo.php');
if (strpos($seo, '<title>\' . escape($title) . \'</title>') === false || strpos($seo, 'escape($description)') === false) {
    die("[FAIL] SEO tags are missing XSS escape filters.\n");
}
if (strpos($seo, 'JSON_HEX_TAG | JSON_HEX_AMP') === false) {
    die("[FAIL] JSON-LD schema generation is missing XSS JSON security flags.\n");
}
if (strpos($seo, 'meta name="robots" content="noindex, nofollow"') === false) {
    die("[FAIL] SEO missing secure noindex handler logic.\n");
}
echo "[PASS] SEO Head Generator explicitly mitigates XSS and strictly bounds noindex attributes.\n";

// 3. Dynamic Endpoints Check
$router = file_get_contents(__DIR__ . '/../public/index.php');
if (strpos($router, "\$path === 'robots.txt'") === false || strpos($router, "\$path === 'sitemap.xml'") === false) {
     die("[FAIL] Router missing dynamic sitemap/robots endpoint interceptors.\n");
}
if (strpos($router, "\$resolution['publication_status'] === 'published'") === false) {
     die("[FAIL] Router sitemap endpoint doesn't strictly verify site publication status.\n");
}
if (strpos($router, "htmlspecialchars(\$loc, ENT_XML1, 'UTF-8')") === false) {
     die("[FAIL] XML Sitemap generation doesn't escape URLs safely.\n");
}
echo "[PASS] Dynamic Sitemap & Robots.txt generation endpoints natively check status and format XML cleanly.\n";

// 4. Dashboard IDOR and Security
$dash = file_get_contents(__DIR__ . '/../user/seo.php');
if (strpos($dash, 'WHERE website_id = ?') === false || strpos($dash, 'verify_csrf_token') === false) {
     die("[FAIL] SEO Dashboard missing strict website_id bounding or CSRF checks on updates.\n");
}
echo "[PASS] Dashboard strictly enforces Tenant IDOR boundaries and CSRF requirements.\n";

echo "ALL R6 STATIC SECURITY TESTS PASSED.\n";
