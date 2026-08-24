<?php
// includes/media_processor.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Handles securely uploading, validating, optimizing and storing a media file.
 *
 * @param PDO $pdo
 * @param int $website_id
 * @param array $file_array (e.g. $_FILES['upload'])
 * @return array { success: bool, error: string|null, media_id: int|null }
 */
function process_media_upload($pdo, $website_id, $file_array) {
    if (!isset($file_array['error']) || is_array($file_array['error'])) {
        return ['success' => false, 'error' => 'Invalid upload parameters.'];
    }

    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed. Error Code: ' . $file_array['error']];
    }

    $original_name = basename($file_array['name']);
    $tmp_name = $file_array['tmp_name'];
    $file_size = (int)$file_array['size'];

    // Basic size limits early check
    $mb_size = $file_size / 1048576;
    if ($mb_size > MAX_VIDEO_SIZE_MB) {
        return ['success' => false, 'error' => 'File exceeds maximum allowed size.'];
    }

    // MIME Validation via finfo (NEVER trust $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($tmp_name);

    $is_image = array_key_exists($mime_type, ALLOWED_IMAGE_MIMES);
    $is_video = array_key_exists($mime_type, ALLOWED_VIDEO_MIMES);

    if (!$is_image && !$is_video) {
        return ['success' => false, 'error' => 'Invalid file format. Only JPG, PNG, WEBP, GIF, MP4, and WEBM are allowed.'];
    }

    if ($is_image && $mb_size > MAX_IMAGE_SIZE_MB) {
        return ['success' => false, 'error' => 'Image exceeds maximum allowed size (' . MAX_IMAGE_SIZE_MB . 'MB).'];
    }

    $extension = $is_image ? ALLOWED_IMAGE_MIMES[$mime_type] : ALLOWED_VIDEO_MIMES[$mime_type];

    // Check Storage Quota
    // R1 config has generic upload_limit in settings, but we will mock generic quota for now
    // In Phase 12 this links to subscription.plan_storage_limit
    $stmt = $pdo->prepare("SELECT SUM(file_size) as total_used FROM media WHERE website_id = ?");
    $stmt->execute([$website_id]);
    $quota = $stmt->fetch();
    $total_used = (int)($quota['total_used'] ?? 0);

    // Hardcoded 500MB generic limit for R4 until Phase 12 maps it perfectly
    $limit_bytes = 500 * 1048576;
    if (($total_used + $file_size) > $limit_bytes) {
        return ['success' => false, 'error' => 'Storage quota exceeded. Please upgrade your plan.'];
    }

    // Generate safe UUID-based filename
    $unique_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $stored_filename = $unique_id . '.' . $extension;
    $website_dir = STORAGE_ROOT . '/' . $website_id;

    if (!is_dir($website_dir)) {
        if (!mkdir($website_dir, 0750, true)) {
            return ['success' => false, 'error' => 'Server error: Cannot create storage directory.'];
        }
    }

    $final_path = $website_dir . '/' . $stored_filename;

    $width = null;
    $height = null;
    $has_webp = 0;
    $has_thumbnail = 0;
    $media_type = $is_image ? 'image' : 'video';
    $total_saved_size = $file_size; // Accounts for derivatives too

    if ($is_image) {
        // Image Processing pipeline
        $img_info = @getimagesize($tmp_name);
        if ($img_info === false) {
            return ['success' => false, 'error' => 'Invalid image content.'];
        }
        $width = $img_info[0];
        $height = $img_info[1];

        // Safe Resizing constraints
        if ($width > MAX_IMAGE_DIMENSION || $height > MAX_IMAGE_DIMENSION) {
             // For simplicity in R4, we assume we just save it as is but warn, or we could resize.
             // We will implement GD resize logic below.
        }

        if (!move_uploaded_file($tmp_name, $final_path)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file.'];
        }

        // WebP Generation & Thumbnails using GD if available
        if (function_exists('imagecreatefromjpeg')) {
            $source_gd = null;
            switch ($mime_type) {
                case 'image/jpeg': $source_gd = @imagecreatefromjpeg($final_path); break;
                case 'image/png':  $source_gd = @imagecreatefrompng($final_path); break;
                case 'image/gif':  $source_gd = @imagecreatefromgif($final_path); break;
                case 'image/webp': $source_gd = @imagecreatefromwebp($final_path); break;
            }

            if ($source_gd) {
                // Resize if needed
                if ($width > MAX_IMAGE_DIMENSION || $height > MAX_IMAGE_DIMENSION) {
                    $ratio = min(MAX_IMAGE_DIMENSION / $width, MAX_IMAGE_DIMENSION / $height);
                    $new_w = round($width * $ratio);
                    $new_h = round($height * $ratio);
                    $resized_gd = imagecreatetruecolor($new_w, $new_h);

                    if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                        imagealphablending($resized_gd, false);
                        imagesavealpha($resized_gd, true);
                    }

                    imagecopyresampled($resized_gd, $source_gd, 0, 0, 0, 0, $new_w, $new_h, $width, $height);

                    // Overwrite original with optimized bounds
                    switch ($mime_type) {
                        case 'image/jpeg': imagejpeg($resized_gd, $final_path, WEBP_QUALITY); break;
                        case 'image/png':  imagepng($resized_gd, $final_path, 8); break; // 0-9 scale
                        case 'image/webp': imagewebp($resized_gd, $final_path, WEBP_QUALITY); break;
                    }
                    $width = $new_w;
                    $height = $new_h;
                    $total_saved_size = filesize($final_path);
                    imagedestroy($source_gd);
                    $source_gd = $resized_gd;
                }

                // Generate WebP derivative (if not already WebP)
                if ($mime_type !== 'image/webp' && function_exists('imagewebp')) {
                    $webp_path = $website_dir . '/' . $unique_id . '.webp';
                    if (imagewebp($source_gd, $webp_path, WEBP_QUALITY)) {
                        $has_webp = 1;
                        $total_saved_size += filesize($webp_path);
                    }
                }

                // Generate Thumbnail
                $thumb_ratio = THUMBNAIL_WIDTH / $width;
                if ($thumb_ratio < 1) {
                    $thumb_h = round($height * $thumb_ratio);
                    $thumb_gd = imagecreatetruecolor(THUMBNAIL_WIDTH, $thumb_h);

                    if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
                        imagealphablending($thumb_gd, false);
                        imagesavealpha($thumb_gd, true);
                    }

                    imagecopyresampled($thumb_gd, $source_gd, 0, 0, 0, 0, THUMBNAIL_WIDTH, $thumb_h, $width, $height);
                    $thumb_path = $website_dir . '/' . $unique_id . '_thumb.jpg';
                    if (imagejpeg($thumb_gd, $thumb_path, 70)) {
                        $has_thumbnail = 1;
                        $total_saved_size += filesize($thumb_path);
                    }
                    imagedestroy($thumb_gd);
                }

                imagedestroy($source_gd);
            }
        }
    } else {
        // Video Pipeline
        if (!move_uploaded_file($tmp_name, $final_path)) {
            return ['success' => false, 'error' => 'Failed to save uploaded video file.'];
        }
        // FFMPEG generation is deferred as it is rarely available natively on basic shared hosts.
    }

    // Save Database Record
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO media
            (website_id, original_filename, stored_filename, storage_path, mime_type, file_extension, file_size, width, height, media_type, has_webp, has_thumbnail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $website_id,
            $original_name,
            $stored_filename,
            $unique_id, // We use the unique ID as the base path so we can resolve derivates later
            $mime_type,
            $extension,
            $total_saved_size,
            $width,
            $height,
            $media_type,
            $has_webp,
            $has_thumbnail
        ]);

        $media_id = $pdo->lastInsertId();
        $pdo->commit();
        return ['success' => true, 'media_id' => $media_id];

    } catch (Exception $e) {
        $pdo->rollBack();
        // Clean up files safely on DB insert failure
        @unlink($final_path);
        if ($has_webp) @unlink($website_dir . '/' . $unique_id . '.webp');
        if ($has_thumbnail) @unlink($website_dir . '/' . $unique_id . '_thumb.jpg');
        return ['success' => false, 'error' => 'Database error. Cleaned up files.'];
    }
}

/**
 * Generates the public delivery URL for a media file safely via proxy or direct delivery wrapper.
 * @param array $media_record
 * @param string $size 'original', 'webp', or 'thumb'
 * @return string
 */
function get_media_url($media_record, $size = 'original') {
    if (!$media_record || empty($media_record['storage_path'])) {
        return '';
    }

    // Map URL to a secure public proxy script ensuring the actual filesystem isn't blindly exposed
    // This allows access checks if required later, or handles cache headers cleanly
    return "/public/api/media.php?id=" . $media_record['id'] . "&size=" . urlencode($size);
}

/**
 * Safely deletes a media item if there are no active references.
 * @param PDO $pdo
 * @param int $media_id
 * @param int $website_id
 * @return array { success: bool, error: string|null }
 */
function delete_media_safely($pdo, $media_id, $website_id) {
    // 1. Verify Ownership
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ? AND website_id = ? LIMIT 1");
    $stmt->execute([$media_id, $website_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        return ['success' => false, 'error' => 'Media not found or permission denied.'];
    }

    // 2. Check Active References across R2 schema
    $references = 0;

    // Check Gallery
    $ref_stmt = $pdo->prepare("SELECT COUNT(*) FROM gallery_items WHERE media_id = ?");
    $ref_stmt->execute([$media_id]);
    $references += (int)$ref_stmt->fetchColumn();

    // Check Services
    $ref_stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE media_id = ?");
    $ref_stmt->execute([$media_id]);
    $references += (int)$ref_stmt->fetchColumn();

    // Check Business Profiles
    $ref_stmt = $pdo->prepare("SELECT COUNT(*) FROM business_profiles WHERE logo_media_id = ? OR hero_media_id = ?");
    $ref_stmt->execute([$media_id, $media_id]);
    $references += (int)$ref_stmt->fetchColumn();

    // Check Reviews
    $ref_stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE media_id = ?");
    $ref_stmt->execute([$media_id]);
    $references += (int)$ref_stmt->fetchColumn();

    // Check Page Sections (Searching inside JSON payloads)
    $ref_stmt = $pdo->prepare("SELECT COUNT(*) FROM page_sections WHERE content LIKE ?");
    // This is safe because media:{ID} is formatted cleanly in our payloads without injection risks
    $ref_stmt->execute(['%"media:' . $media_id . '"%']);
    $references += (int)$ref_stmt->fetchColumn();

    if ($references > 0) {
        return ['success' => false, 'error' => "Cannot delete media. It is currently referenced in $references places."];
    }

    // 3. Unlink Filesystem Files
    $website_dir = STORAGE_ROOT . '/' . $website_id;
    $base_id = $media['storage_path'];
    $ext = $media['file_extension'];

    @unlink($website_dir . '/' . $base_id . '.' . $ext);
    if ($media['has_webp']) @unlink($website_dir . '/' . $base_id . '.webp');
    if ($media['has_thumbnail']) @unlink($website_dir . '/' . $base_id . '_thumb.jpg');

    // 4. Delete Database Record
    $del_stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
    $del_stmt->execute([$media_id]);

    return ['success' => true];
}
