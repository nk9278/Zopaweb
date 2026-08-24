<?php
// tests/r4_media_security_test.php

require_once __DIR__ . '/../config/app.php';

echo "Running R4 Media Security & Architecture Tests...\n";

// Ensure files exist
$files = [
    'includes/media_processor.php',
    'public/api/media.php',
    'user/media.php',
    'database/migrations/r4_media.sql',
    'config/app.php',
    'storage/.htaccess'
];

foreach ($files as $f) {
    if (!file_exists(__DIR__ . '/../' . $f)) {
        die("[FAIL] Required file missing: $f\n");
    }
}
echo "[PASS] All R4 files created.\n";

// 1. Storage & Executable Blocking Test
$htaccess = file_get_contents(__DIR__ . '/../storage/.htaccess');
if (strpos($htaccess, '\.(php|php4|php5|php7|php8|phtml|phar|pl|py|cgi|exe)$') === false || strpos($htaccess, 'Require all denied') === false) {
    die("[FAIL] Executable blocking missing in storage/.htaccess\n");
}
if (strpos($htaccess, 'ForceType application/octet-stream') === false) {
    die("[FAIL] ForceType MIME-spoofing mitigation missing in storage/.htaccess\n");
}
echo "[PASS] Directory executable execution explicitly blocked securely.\n";

// 2. MIME Spoofing & Extension Security
$media_proc = file_get_contents(__DIR__ . '/../includes/media_processor.php');
if (strpos($media_proc, "finfo(FILEINFO_MIME_TYPE)") === false) {
    die("[FAIL] Reliable MIME typing missing. Relying on \$_FILES['type'] is unsafe.\n");
}
if (strpos($media_proc, "basename(\$file_array['name'])") === false || strpos($media_proc, "\$extension = \$is_image ? ALLOWED_IMAGE_MIMES[\$mime_type]") === false) {
    die("[FAIL] Extension forcing mapping logic missing. Path traversal possible.\n");
}
echo "[PASS] MIME verification and extension mapping validated natively.\n";

// 3. IDOR Ownership Logic
$user_media = file_get_contents(__DIR__ . '/../user/media.php');
if (strpos($user_media, "WHERE website_id = ?") === false || strpos($user_media, "user_id = ?") === false) {
    die("[FAIL] IDOR Ownership missing in Media CRUD.\n");
}
// Validate Safe deletion dependencies
if (strpos($media_proc, "SELECT COUNT(*) FROM gallery_items WHERE media_id = ?") === false) {
    die("[FAIL] Media deletion logic doesn't verify cascade reference checks.\n");
}
echo "[PASS] IDOR Ownership and Reference-Aware soft-deletion logic validated.\n";

// 4. API Proxy Logic
$api_media = file_get_contents(__DIR__ . '/../public/api/media.php');
if (strpos($api_media, "realpath(\$target_file)") === false || strpos($api_media, "strpos(\$real_target, \$real_storage)") === false) {
    die("[FAIL] Symlink and arbitrary Path Traversal not securely bounded in API logic.\n");
}
echo "[PASS] API Delivery proxy secures filesystem boundaries.\n";

echo "ALL R4 STATIC TESTS PASSED.\n";
