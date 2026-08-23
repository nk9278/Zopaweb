<?php
// includes/template_engine.php

/**
 * ZopaWeb Template Engine Foundation
 * Separates data logic from presentation.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

/**
 * Fetch completely isolated data for a single website.
 * Prevents cross-client data leakage.
 * @param PDO $pdo
 * @param int $website_id
 * @return array
 */
function get_website_data($pdo, $website_id) {
    // 1. Fetch Core Website & User Profile Data
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

    // Placeholder data structures for Phase 4 rendering.
    // In future phases (5+), these will query actual DB tables (services, packages, gallery, reviews, faqs).
    $business = [
        'name' => $website['website_name'],
        'email' => $website['owner_email'],
        'phone' => $website['owner_phone'],
        'address' => 'Sample Address (Phase 5)',
        'tagline' => 'Professional Makeup Artistry',
        'about' => 'Welcome to my professional makeup portfolio. I specialize in bridal, editorial, and special event makeup, ensuring you look your absolute best for any occasion.',
        'hero_image' => 'https://images.unsplash.com/photo-1512496015851-a1dc8a473105?auto=format&fit=crop&q=80&w=1600'
    ];

    $services = [
        ['name' => 'Bridal Makeup', 'price' => '₹15,000', 'description' => 'Complete bridal package including trial and day-of styling.'],
        ['name' => 'Party Makeup', 'price' => '₹5,000', 'description' => 'Flawless makeup for parties and events.'],
        ['name' => 'Editorial Shoot', 'price' => '₹10,000', 'description' => 'Creative makeup for fashion and photography.']
    ];

    $gallery = [
        'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&q=80&w=800',
        'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&q=80&w=800',
        'https://images.unsplash.com/photo-1596704017254-9b121068fb31?auto=format&fit=crop&q=80&w=800'
    ];

    $reviews = [
        ['client' => 'Priya S.', 'rating' => 5, 'text' => 'Absolutely loved my bridal look! Highly recommended.'],
        ['client' => 'Anita K.', 'rating' => 5, 'text' => 'Very professional and understood exactly what I wanted.']
    ];

    $social = [
        'instagram' => '#',
        'facebook' => '#'
    ];

    return [
        'site' => $website,
        'template' => $template,
        'business' => $business,
        'services' => $services,
        'gallery' => $gallery,
        'reviews' => $reviews,
        'social' => $social
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
