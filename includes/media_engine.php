<?php
// includes/media_engine.php
// Centralized ZopaWeb Media Engine

define('MEDIA_STORAGE_BASE', __DIR__ . '/../storage/websites/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('THUMBNAIL_WIDTH', 320);
define('OPTIMIZED_MAX_WIDTH', 1920);

/**
 * Handle a secure media upload
 */
function handle_media_upload($pdo, $file, $website_id, $user_id) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'File exceeds the 10MB maximum limit.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm'
    ];

    if (!array_key_exists($mime_type, $allowed_mimes)) {
        return ['success' => false, 'error' => "Unsupported file type ($mime_type)."];
    }

    $extension = $allowed_mimes[$mime_type];
    $media_type = strpos($mime_type, 'video') === 0 ? 'video' : 'image';

    // Verify it's an actual image via GD if it claims to be one
    $width = null;
    $height = null;
    if ($media_type === 'image') {
        $img_info = @getimagesize($file['tmp_name']);
        if ($img_info === false) {
            return ['success' => false, 'error' => 'Invalid image file.'];
        }
        $width = $img_info[0];
        $height = $img_info[1];
    }

    // Prepare Storage Directories
    $site_dir = MEDIA_STORAGE_BASE . $website_id . '/media/';
    $dirs = ['originals', 'optimized', 'thumbnails'];
    foreach ($dirs as $d) {
        $path = $site_dir . $d;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    // Generate safe random stored name
    $stored_name = bin2hex(random_bytes(16)) . '.' . $extension;
    $original_path = $site_dir . 'originals/' . $stored_name;

    if (!move_uploaded_file($file['tmp_name'], $original_path)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file to secure storage.'];
    }

    $optimized_path = null;
    $thumbnail_path = null;
    $webp_path = null;

    if ($media_type === 'image') {
        // Optimization via GD
        $optimized_name = bin2hex(random_bytes(16)) . '.webp';
        $opt_path = $site_dir . 'optimized/' . $optimized_name;
        $thumb_name = bin2hex(random_bytes(16)) . '.webp';
        $thm_path = $site_dir . 'thumbnails/' . $thumb_name;

        // Process images
        if (process_image($original_path, $opt_path, OPTIMIZED_MAX_WIDTH, $mime_type)) {
            $webp_path = "/public/media.php?id={$website_id}&file=optimized/{$optimized_name}";
            // Generate thumb
            process_image($original_path, $thm_path, THUMBNAIL_WIDTH, $mime_type);
            $thumbnail_path = "/public/media.php?id={$website_id}&file=thumbnails/{$thumb_name}";
        } else {
            // Fallback to serving original directly via script if GD processing fails
            $webp_path = "/public/media.php?id={$website_id}&file=originals/{$stored_name}";
            $thumbnail_path = $webp_path;
        }
    } else {
        // Video handling (no conversion for Phase 9)
        $webp_path = "/public/media.php?id={$website_id}&file=originals/{$stored_name}";
        $thumbnail_path = null; // No thumbnail generation for Phase 9 as per spec to avoid ffmpeg dep issues
    }

    // Insert to DB
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO media (
            website_id, user_id, media_type, original_name, stored_name, mime_type, file_extension,
            original_size, width, height, storage_path, thumbnail_path, webp_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $original_name = basename($file['name']);
    // Avoid XSS in original names
    $original_name = strip_tags($original_name);

    $stmt->execute([
        $website_id, $user_id, $media_type, $original_name, $stored_name, $mime_type, $extension,
        $file['size'], $width, $height, "originals/{$stored_name}", $thumbnail_path, $webp_path
    ]);

    $media_id = $pdo->lastInsertId();

    return [
        'success' => true,
        'media_id' => $media_id,
        'webp_path' => $webp_path,
        'thumbnail_path' => $thumbnail_path
    ];
}

/**
 * Resize and convert an image to WebP using GD
 */
function process_image($source_path, $target_path, $max_width, $mime_type) {
    if (!extension_loaded('gd')) return false;

    switch ($mime_type) {
        case 'image/jpeg': $source_img = @imagecreatefromjpeg($source_path); break;
        case 'image/png':  $source_img = @imagecreatefrompng($source_path); break;
        case 'image/webp': $source_img = @imagecreatefromwebp($source_path); break;
        case 'image/gif':  $source_img = @imagecreatefromgif($source_path); break;
        default: return false;
    }

    if (!$source_img) return false;

    $width = imagesx($source_img);
    $height = imagesy($source_img);

    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = floor($height * ($max_width / $width));
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    $dest_img = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency
    imagealphablending($dest_img, false);
    imagesavealpha($dest_img, true);
    $transparent = imagecolorallocatealpha($dest_img, 255, 255, 255, 127);
    imagefilledrectangle($dest_img, 0, 0, $new_width, $new_height, $transparent);

    imagecopyresampled($dest_img, $source_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save as WebP with 80% quality
    $result = imagewebp($dest_img, $target_path, 80);

    imagedestroy($source_img);
    imagedestroy($dest_img);

    return $result;
}
