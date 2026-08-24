<?php
// tests/routing_test.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/host_resolver.php';

echo "Running Routing Test...\n";

echo "[SKIP] Dynamic database routing logic skipped since MySQL daemon is unavailable in this CI environment.\n";

// But we can test parsing logic on host_resolver functions
if (function_exists('normalize_host')) {
    $host1 = normalize_host('www.ZopaWeb.com:8080');
    if ($host1 === 'www.zopaweb.com') {
        echo "[PASS] normalize_host correctly formats ports and caps.\n";
    } else {
        echo "[FAIL] normalize_host failed formatting.\n";
    }
}

if (function_exists('is_valid_slug')) {
    if (is_valid_slug('admin') === false && is_valid_slug('valid-slug-123') === true) {
         echo "[PASS] is_valid_slug correctly identifies reserved and valid slugs.\n";
    } else {
         echo "[FAIL] is_valid_slug logic failed.\n";
    }
}

echo "ROUTING TESTS COMPLETED.\n";
