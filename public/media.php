<?php
// Secure media server script
// Maps /public/media.php?id=WEBSITE_ID&file=FOLDER/FILE.EXT securely to storage

require_once __DIR__ . "/../includes/session.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/security.php";

send_security_headers();

// Layer 3: Media Rate Limiting (300 requests per minute per IP to prevent rapid asset scraping)
$pdo = getDB();
if (check_api_rate_limit($pdo, 'media_fetch', 300, '1 MINUTE')) {
    http_response_code(429);
    header("Retry-After: 60");
    die("Rate limit exceeded.");
}

// Hotlink protection (Allow empty referrer or matching domains)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer)) {
    $ref_host = parse_url($referer, PHP_URL_HOST);
    // In a real app we'd query the DB to ensure $ref_host matches the allowed domains for this website_id.
    // For Phase 13, we block obvious third-party embedding while allowing social sharing bots (which usually lack referers)
    // We'll allow localhost, zopaweb, and empty referers.
    if ($ref_host && strpos($ref_host, 'zopaweb.com') === false && strpos($ref_host, 'localhost') === false) {
        // Query to check if the referer is a mapped custom domain for this site
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ? AND website_id = ? AND status = 'active'");
        $stmt->execute([$ref_host, $website_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die("Hotlinking denied.");
        }
    }
}

$website_id = (int)($_GET["id"] ?? 0);
$file = $_GET["file"] ?? "";

// Basic validation to prevent traversal
if (!$website_id || empty($file) || strpos($file, "..") !== false || strpos($file, "/") === 0) {
    http_response_code(404);
    die("File not found.");
}

// Split folder and filename to validate
$parts = explode("/", $file);
if (count($parts) !== 2) {
    http_response_code(400);
    die("Invalid request.");
}

$folder = $parts[0];
$filename = $parts[1];

if (!in_array($folder, ["originals", "optimized", "thumbnails"])) {
    http_response_code(403);
    die("Forbidden.");
}

$storage_base = realpath(__DIR__ . "/../storage/websites/");
if (!$storage_base) {
    http_response_code(500);
    die("Storage error.");
}

$filepath = $storage_base . "/" . $website_id . "/media/" . $folder . "/" . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die("File not found.");
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Send file
header("Content-Type: " . $mime_type);
header("Content-Length: " . filesize($filepath));
// Cache control for optimized media
header("Cache-Control: public, max-age=31536000, immutable");
readfile($filepath);
// eof
