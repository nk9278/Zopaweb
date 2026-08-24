<?php
// public/api/media.php
// Secure media delivery proxy

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = getDB();
$media_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$size = isset($_GET['size']) ? $_GET['size'] : 'original';

if (!$media_id) {
    http_response_code(404);
    die();
}

// Fetch record. For public delivery, we don't strictly require session authentication
// because website images are meant to be public on the template.
$stmt = $pdo->prepare("SELECT * FROM media WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$media_id]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    http_response_code(404);
    die();
}

$website_dir = STORAGE_ROOT . '/' . $media['website_id'];
$target_file = '';
$mime_type = $media['mime_type'];

if ($size === 'thumb' && $media['has_thumbnail']) {
    $target_file = $website_dir . '/' . $media['storage_path'] . '_thumb.jpg';
    $mime_type = 'image/jpeg';
} elseif ($size === 'webp' && $media['has_webp']) {
    $target_file = $website_dir . '/' . $media['storage_path'] . '.webp';
    $mime_type = 'image/webp';
} else {
    $target_file = $website_dir . '/' . $media['stored_filename'];
}

if (!file_exists($target_file)) {
    http_response_code(404);
    die();
}

// Ensure the file is actually inside the storage directory (prevent symlink bypasses if somehow created)
$real_target = realpath($target_file);
$real_storage = realpath(STORAGE_ROOT);

if ($real_target === false || strpos($real_target, $real_storage) !== 0) {
    http_response_code(403);
    die();
}

// Set caching headers (1 year)
$expires = 60 * 60 * 24 * 365;
header('Pragma: public');
header('Cache-Control: max-age=' . $expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($real_target));
// Content-Disposition inline displays it, attachment downloads it
header('Content-Disposition: inline; filename="' . $media['original_filename'] . '"');

readfile($real_target);
die();
