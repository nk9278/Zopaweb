<?php
// includes/template_engine.php

require_once __DIR__ . '/security.php';

/**
 * Fetches completely isolated data for a specific website, fetching only related DB records.
 *
 * @param PDO $pdo
 * @param int $website_id
 * @return array|null
 */
function get_website_data($pdo, $website_id) {
    if (!$website_id) return null;

    // 1. Fetch Core Website Data & Owner
    $stmt = $pdo->prepare("
        SELECT w.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
        FROM websites w
        JOIN users u ON w.user_id = u.id
        WHERE w.id = ? AND w.status = 'active' AND w.deleted_at IS NULL
    ");
    $stmt->execute([$website_id]);
    $website = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$website) {
        return null;
    }

    // 2. Fetch Theme/Template Data
    $template_stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $template_stmt->execute([$website['template_id']]);
    $template = $template_stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Business Profile
    $profile_stmt = $pdo->prepare("SELECT * FROM business_profiles WHERE website_id = ?");
    $profile_stmt->execute([$website_id]);
    $business_profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

    if ($business_profile) {
        $business = [
            'name' => $business_profile['business_name'],
            'tagline' => $business_profile['tagline'],
            'email' => $business_profile['email'] ?? $website['owner_email'],
            'phone' => $business_profile['phone'] ?? $website['owner_phone'],
            'whatsapp' => $business_profile['whatsapp'],
            'address' => $business_profile['address'],
            'city' => $business_profile['city'],
            'about' => $business_profile['about'],
            'logo_url' => $business_profile['logo_url'],
            'hero_image' => $business_profile['hero_image_url']
        ];
    } else {
        // Safe fallback for empty state
        $business = [
            'name' => $website['website_name'],
            'tagline' => null,
            'email' => $website['owner_email'],
            'phone' => $website['owner_phone'],
            'whatsapp' => null,
            'address' => null,
            'city' => null,
            'about' => null,
            'logo_url' => null,
            'hero_image' => null
        ];
    }

    // 4. Fetch Services
    $services_stmt = $pdo->prepare("SELECT name, description, price, image_url FROM services WHERE website_id = ? AND status = 'active' ORDER BY sort_order ASC");
    $services_stmt->execute([$website_id]);
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Gallery
    $gallery_stmt = $pdo->prepare("SELECT image_url, caption, alt_text FROM gallery_items WHERE website_id = ? AND status = 'active' ORDER BY sort_order ASC");
    $gallery_stmt->execute([$website_id]);
    $gallery_items = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
    $gallery = [];
    foreach ($gallery_items as $item) {
        $gallery[] = $item['image_url']; // To match current template array structure, can adapt later
    }

    // 6. Fetch Reviews
    $reviews_stmt = $pdo->prepare("SELECT reviewer_name as client, rating, review_text as text, image_url FROM reviews WHERE website_id = ? AND status = 'active' ORDER BY sort_order ASC");
    $reviews_stmt->execute([$website_id]);
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Fetch Social Links
    $social_stmt = $pdo->prepare("SELECT platform, url FROM social_links WHERE website_id = ? AND status = 'active' ORDER BY sort_order ASC");
    $social_stmt->execute([$website_id]);
    $social_items = $social_stmt->fetchAll(PDO::FETCH_ASSOC);
    $social = [];
    foreach ($social_items as $s) {
        $social[strtolower($s['platform'])] = $s['url'];
    }

    // 8. Fetch Theme Settings
    $theme_stmt = $pdo->prepare("SELECT * FROM theme_settings WHERE website_id = ?");
    $theme_stmt->execute([$website_id]);
    $theme = $theme_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'site' => $website,
        'template' => $template,
        'business' => $business,
        'services' => $services,
        'gallery' => $gallery,
        'reviews' => $reviews,
        'social' => $social,
        'theme' => $theme
    ];
}

/**
 * Validates a template manifest and structure.
 * @param string $folder_key
 * @return array {valid: bool, error: string|null, manifest: array|null}
 */
function validate_template_manifest($folder_key) {
    // Prevent path traversal completely
    $safe_folder = basename($folder_key);
    if ($safe_folder !== $folder_key || empty($safe_folder)) {
        return ['valid' => false, 'error' => 'Invalid folder key format.'];
    }

    $template_dir = __DIR__ . '/../templates/' . $safe_folder;

    if (!is_dir($template_dir)) {
        return ['valid' => false, 'error' => "Template directory not found: $safe_folder"];
    }

    $manifest_file = $template_dir . '/template.json';
    if (!file_exists($manifest_file)) {
        return ['valid' => false, 'error' => 'Missing template.json manifest.'];
    }

    $json_data = file_get_contents($manifest_file);
    $manifest = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)) {
        return ['valid' => false, 'error' => 'Invalid JSON in template.json.'];
    }

    // Check required manifest fields
    $required_fields = ['name', 'slug', 'version', 'category'];
    foreach ($required_fields as $field) {
        if (empty($manifest[$field])) {
            return ['valid' => false, 'error' => "Missing required field in manifest: $field"];
        }
    }

    // Check required files
    $required_files = [
        'template.php',
        'layouts/header.php',
        'layouts/footer.php',
        'pages/home.php'
    ];

    foreach ($required_files as $file) {
        if (!file_exists($template_dir . '/' . $file)) {
            return ['valid' => false, 'error' => "Missing required file: $file"];
        }
    }

    return ['valid' => true, 'error' => null, 'manifest' => $manifest];
}

/**
 * Returns a safely resolved URL for a template asset.
 * @param string $folder_key
 * @param string $asset_path e.g., 'css/style.css'
 * @return string
 */
function template_asset_url($folder_key, $asset_path) {
    $safe_folder = basename($folder_key);
    // Sanitize asset path to prevent escaping the template folder via URL
    $safe_asset = str_replace('../', '', $asset_path);
    // Assuming /templates/ is exposed publicly or proxied.
    // In a real setup, a proxy script or public symlink handles this.
    return "/templates/" . urlencode($safe_folder) . "/assets/" . ltrim($safe_asset, '/');
}

/**
 * Safely load a component file inside a template.
 * @param string $folder_key
 * @param string $component_name
 * @param array $data Data passed to the component
 * @return void
 */
function render_component($folder_key, $component_name, $data = []) {
    $safe_folder = basename($folder_key);
    $safe_component = basename($component_name);

    $path = __DIR__ . "/../templates/{$safe_folder}/components/{$safe_component}.php";

    if (file_exists($path)) {
        // Extract data so variables are available in the component scope
        extract($data);
        include $path;
    }
}

/**
 * Safely load a layout file (e.g., header, footer) inside a template.
 * @param string $folder_key
 * @param string $layout_name
 * @param array $data
 * @return void
 */
function render_layout($folder_key, $layout_name, $data = []) {
    $safe_folder = basename($folder_key);
    $safe_layout = basename($layout_name);

    $path = __DIR__ . "/../templates/{$safe_folder}/layouts/{$safe_layout}.php";

    if (file_exists($path)) {
        extract($data);
        include $path;
    } else {
        echo "<!-- Missing Layout: {$safe_layout} -->";
    }
}

/**
 * Safely render a full template page.
 * @param array $template Database record of the template
 * @param array $data The isolated website data
 * @param string $page The specific page to render (default 'home')
 * @return void
 */
function render_page($template, $data, $page = 'home') {
    if (!$template || empty($template['folder_key'])) {
        // Fallback safety
        render_fallback();
        return;
    }

    $safe_folder = basename($template['folder_key']);
    $safe_page = basename($page);

    // Check if the requested page is supported by the template manifest
    $validation = validate_template_manifest($safe_folder);
    $supported_pages = $validation['manifest']['pages'] ?? ['home', 'about', 'services', 'gallery', 'contact'];

    if (!in_array($safe_page, $supported_pages)) {
        render_404();
        return;
    }

    $template_file = __DIR__ . "/../templates/{$safe_folder}/template.php";
    $page_file = __DIR__ . "/../templates/{$safe_folder}/pages/{$safe_page}.php";

    if (!file_exists($template_file) || !file_exists($page_file)) {
        render_fallback("Template files missing.");
        return;
    }

    // Engine provides scoped variables for template.php to use.
    $engine = [
        'folder' => $safe_folder,
        'page_file' => $page_file,
        'data' => $data
    ];

    // Delegate rendering control to the template's master file.
    extract($data); // Expose $site, $business, etc.
    include $template_file;
}

/**
 * Status Renderers
 */

function render_fallback($reason = "Website configuration error.") {
    $fallback_file = __DIR__ . '/../templates/fallback/template.php';
    if (file_exists($fallback_file)) {
        include $fallback_file;
    } else {
        http_response_code(503);
        echo "<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body style='text-align:center; font-family:sans-serif; padding-top: 100px;'>";
        echo "<h1>Service Unavailable</h1><p>The website is currently undergoing maintenance.</p>";
        echo "</body></html>";
    }
}

function render_unpublished() {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><title>Not Published</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#f8f9fa;color:#333;}</style></head><body>";
    echo "<h1>Coming Soon</h1><p>This website is not published yet.</p>";
    echo "</body></html>";
}

function render_maintenance() {
    http_response_code(503);
    echo "<!DOCTYPE html><html><head><title>Maintenance</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#f8f9fa;color:#333;}</style></head><body>";
    echo "<h1>Maintenance</h1><p>This website is temporarily unavailable. Please check back later.</p>";
    echo "</body></html>";
}

function render_suspended() {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><title>Website Unavailable</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#f8f9fa;color:#333;}</style></head><body>";
    echo "<h1>Website Unavailable</h1><p>Website temporarily unavailable.</p>";
    echo "</body></html>";
}

function render_404() {
    // Attempt to load ZopaWeb's branded 404 page if it exists in public
    $branded_404 = __DIR__ . '/../public/404.php';
    if (file_exists($branded_404)) {
        include $branded_404;
    } else {
        http_response_code(404);
        echo "<!DOCTYPE html><html><head><title>Page Not Found</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#f8f9fa;color:#333;}</style></head><body>";
        echo "<h1>404 Not Found</h1><p>The page you requested does not exist.</p>";
        echo "</body></html>";
    }
}
