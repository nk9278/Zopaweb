<?php
// Secure media server script
// Maps /public/media.php?id=WEBSITE_ID&file=FOLDER/FILE.EXT securely to storage

require_once __DIR__ . "/../includes/session.php";
require_once __DIR__ . "/../config/database.php";

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
