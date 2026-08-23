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

    // Fetch Custom Theme Data
    $theme_stmt = $pdo->prepare("SELECT * FROM website_themes WHERE website_id = ?");
    $theme_stmt->execute([$website_id]);
    $theme_settings = $theme_stmt->fetch(PDO::FETCH_ASSOC);

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

    // Fetch Gallery from DB
    $gal_stmt = $pdo->prepare("
        SELECT m.webp_path, m.alt_text, m.caption
        FROM galleries g
        JOIN media m ON g.media_id = m.id
        WHERE g.website_id = ? AND g.status = 'visible' AND g.deleted_at IS NULL AND m.deleted_at IS NULL
        ORDER BY g.sort_order ASC, g.id DESC
    ");
    $gal_stmt->execute([$website_id]);
    $gallery_records = $gal_stmt->fetchAll(PDO::FETCH_ASSOC);

    $gallery = [];
    if (!empty($gallery_records)) {
        foreach ($gallery_records as $g) {
            $gallery[] = $g['webp_path']; // The templates currently expect an array of string URLs
        }
    } else {
        // Fallback to demo images if empty for preview purposes
        $gallery = [
            'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1596704017254-9b121068fb31?auto=format&fit=crop&q=80&w=800'
        ];
    }

    $reviews = [
        ['client' => 'Priya S.', 'rating' => 5, 'text' => 'Absolutely loved my bridal look! Highly recommended.'],
        ['client' => 'Anita K.', 'rating' => 5, 'text' => 'Very professional and understood exactly what I wanted.']
    ];

    $social = [
        'instagram' => '#',
        'facebook' => '#'
    ];

    // Fetch Navigation Pages
    $nav_stmt = $pdo->prepare("SELECT title, slug FROM pages WHERE website_id = ? AND status = 'published' AND show_in_nav = 1 AND deleted_at IS NULL ORDER BY sort_order ASC");
    $nav_stmt->execute([$website_id]);
    $navigation = $nav_stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'site' => $website,
        'template' => $template,
        'theme_settings' => $theme_settings ?: [],
        'business' => $business,
        'services' => $services,
        'gallery' => $gallery,
        'reviews' => $reviews,
        'social' => $social,
        'navigation' => $navigation
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
 * Safely render a full template page dynamically.
 * @param array $template Database record of the template
 * @param array $data The isolated website data
 * @param string $page_slug The specific page to render (default 'home')
 * @return void
 */
function render_page($template, $data, $page_slug = 'home') {
    global $pdo;

    if (!$template || empty($template['folder_key'])) {
        render_fallback();
        return;
    }

    $safe_folder = basename($template['folder_key']);
    $website_id = $data['site']['id'];

    // Verify Page Exists and is Published
    $page_stmt = $pdo->prepare("SELECT * FROM pages WHERE website_id = ? AND slug = ? AND deleted_at IS NULL LIMIT 1");
    $page_stmt->execute([$website_id, $page_slug]);
    $page_record = $page_stmt->fetch(PDO::FETCH_ASSOC);

    // Draft Preview Logic: If a session exists and user owns the site, they can view drafts.
    // Otherwise, 404 for drafts/unpublished/archived.
    $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $data['site']['user_id']);

    $legacy_file = __DIR__ . "/../templates/{$safe_folder}/pages/" . basename($page_slug) . ".php";

    if (!$page_record) {
        // Always fallback to legacy pages if no DB record is found to prevent breaking existing sites
        if (!file_exists($legacy_file)) {
            render_404(); return;
        }
    } else {
        if ($page_record['status'] !== 'published' && !$is_owner) {
            render_404();
            return;
        }
    }

    $template_file = __DIR__ . "/../templates/{$safe_folder}/template.php";
    if (!file_exists($template_file)) {
        render_fallback("Template file missing.");
        return;
    }

    // Load manifest to get capabilities and defaults
    $manifest = json_decode(file_get_contents(__DIR__ . "/../templates/{$safe_folder}/template.json"), true) ?: [];
    $theme_defaults = $manifest['theme_defaults'] ?? [];
    $supported_sections = $manifest['supports']['sections'] ?? [];

    $theme_settings = $data['theme_settings'] ?? [];
    $theme_css = generate_theme_css($theme_settings, $theme_defaults);
    $google_fonts_url = generate_google_fonts_url($theme_settings, $theme_defaults);

    // Fetch and render sections
    $rendered_sections = '';

    if ($page_record) {
        $sec_stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? AND status = 'visible' AND deleted_at IS NULL ORDER BY sort_order ASC");
        $sec_stmt->execute([$page_record['id']]);
        $sections = $sec_stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        foreach ($sections as $section) {
            $type = $section['section_type'];
            // Check if template supports it
            if (!empty($supported_sections[$type])) {
                $component_path = __DIR__ . "/../templates/{$safe_folder}/components/{$type}.php";
                if (file_exists($component_path)) {
                    $section_settings = json_decode($section['settings_json'], true) ?: [];
                    $section_content = json_decode($section['content_json'], true) ?: [];

                    // Remap variables for backward compatibility with phase 6 sections
                    // e.g. some templates expect $services instead of pulling from $data['services']
                    // and $business instead of $data['business']

                    extract($data); // Re-extract so components get fresh $business, $services, etc.
                    include $component_path;
                }
            } else {
                echo "<!-- Section type '{$type}' is not supported by the current template. -->\n";
            }
        }
        $rendered_sections = ob_get_clean();
    } else {
        // Legacy fallback rendering
        ob_start();
        extract($data);
        include $legacy_file;
        $rendered_sections = ob_get_clean();
    }

    // Engine provides scoped variables for template.php to use.
    $engine = [
        'folder' => $safe_folder,
        'page_content' => $rendered_sections,
        'data' => $data,
        'theme_css' => $theme_css,
        'google_fonts_url' => $google_fonts_url,
        'theme_settings' => $theme_settings,
        'theme_defaults' => $theme_defaults,
        'page_record' => $page_record // Passes page metadata
    ];

    // Render floating WhatsApp if configured and number exists
    $opts = json_decode($theme_settings['options_json'] ?? '{}', true) ?: [];
    if (!empty($opts['floating_wa']) && !empty($business['whatsapp'])) {
        $clean_wa = preg_replace('/[^0-9]/', '', $business['whatsapp']);
        // Append it directly to the rendered page content
        $engine['page_content'] .= '
            <a href="https://wa.me/' . $clean_wa . '" target="_blank" class="zopa-floating-wa" onclick="if(window.zopaTrackEvent) window.zopaTrackEvent(\'whatsapp_click\', \'floating_btn\');">
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16">
                  <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
                </svg>
            </a>
            <style>
            .zopa-floating-wa {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background-color: #25D366;
                color: white;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                display: flex;
                justify-content: center;
                align-items: center;
                box-shadow: 0 4px 10px rgba(0,0,0,0.15);
                z-index: 9999;
                transition: transform 0.3s;
            }
            .zopa-floating-wa:hover {
                transform: scale(1.1);
                color: white;
            }
            </style>
        ';
    }

    // Inject Global Analytics Tracker
    $engine['page_content'] .= '
        <script>
        window.zopaTrackEvent = function(eventType, sectionType) {
            fetch("/api/event.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "website_id=" + encodeURIComponent("' . $website_id . '") + "&event_type=" + encodeURIComponent(eventType) + "&section_type=" + encodeURIComponent(sectionType || "") + "&page_path=" + encodeURIComponent(window.location.pathname)
            }).catch(e => console.log("Analytics error"));
        };
        // Auto-bind tel links
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll("a[href^=\'tel:\']").forEach(a => {
                a.addEventListener("click", () => window.zopaTrackEvent("call_click", "auto_tel"));
            });
        });
        </script>
    ';

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


/**
 * Generates the safe theme CSS block based on custom settings and defaults.
 * @param array $settings User's custom settings from website_themes
 * @param array $defaults Template defaults from template.json
 * @return string CSS <style> block
 */
function generate_theme_css($settings, $defaults) {
    $css_vars = [];

    // Valid color mapping
    $color_keys = [
        'primary_color', 'secondary_color', 'accent_color', 'background_color',
        'surface_color', 'text_color', 'heading_color', 'muted_color',
        'button_color', 'button_text_color', 'border_color'
    ];

    foreach ($color_keys as $key) {
        $val = !empty($settings[$key]) ? $settings[$key] : ($defaults['colors'][$key] ?? null);
        if ($val) {
            // Very strict validation: must be a hex color
            if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $val)) {
                $css_var_name = '--' . str_replace('_', '-', $key);
                $css_vars[] = "    {$css_var_name}: {$val};";
            }
        }
    }

    // Typography mapping
    $font_keys = ['heading_font', 'body_font', 'accent_font'];
    $allowed_fonts = [
        'Poppins' => "'Poppins', sans-serif",
        'Inter' => "'Inter', sans-serif",
        'Playfair Display' => "'Playfair Display', serif",
        'DM Sans' => "'DM Sans', sans-serif",
        'Montserrat' => "'Montserrat', sans-serif",
        'Cormorant Garamond' => "'Cormorant Garamond', serif",
        'Lora' => "'Lora', serif",
        'Manrope' => "'Manrope', sans-serif",
        'Outfit' => "'Outfit', sans-serif",
        'Libre Baskerville' => "'Libre Baskerville', serif",
        'Lato' => "'Lato', sans-serif"
    ];

    foreach ($font_keys as $key) {
        $val = !empty($settings[$key]) ? $settings[$key] : ($defaults['typography'][$key] ?? null);
        if ($val && isset($allowed_fonts[$val])) {
            $css_var_name = '--' . str_replace('_', '-', $key);
            $css_vars[] = "    {$css_var_name}: {$allowed_fonts[$val]};";
        }
    }

    // Scale mapping
    $scale_keys = ['heading_scale', 'body_scale'];
    $allowed_scales = [
        'Small' => '0.9',
        'Medium' => '1',
        'Large' => '1.1'
    ];

    foreach ($scale_keys as $key) {
        $val = !empty($settings[$key]) ? $settings[$key] : ($defaults['typography'][$key] ?? null);
        if ($val && isset($allowed_scales[$val])) {
            $css_var_name = '--' . str_replace('_', '-', $key);
            $css_vars[] = "    {$css_var_name}: {$allowed_scales[$val]};";
        }
    }

    // Styles (buttons, border radius, etc. mapping)
    // Map abstract names to actual CSS values where possible, or just export the abstract name and handle in CSS
    $style_keys = [
        'border_radius' => [
            'Sharp' => '0px',
            'Soft' => '4px',
            'Rounded' => '8px',
            'Pill' => '9999px'
        ],
        'shadow_style' => [
            'None' => 'none',
            'Subtle' => '0 2px 4px rgba(0,0,0,0.05)',
            'Medium' => '0 4px 6px rgba(0,0,0,0.1)',
            'Soft Luxury' => '0 10px 30px rgba(0,0,0,0.08)'
        ]
    ];

    foreach ($style_keys as $key => $mapping) {
        $val = !empty($settings[$key]) ? $settings[$key] : ($defaults['styles'][$key] ?? null);
        if ($val && isset($mapping[$val])) {
            $css_var_name = '--' . str_replace('_', '-', $key);
            $css_vars[] = "    {$css_var_name}: {$mapping[$val]};";
        }
    }

    if (empty($css_vars)) {
        return '';
    }

    $css = "<style>\n:root {\n" . implode("\n", $css_vars) . "\n}\n</style>";
    return $css;
}

/**
 * Helper to get Google Fonts URL based on selected fonts
 */
function generate_google_fonts_url($settings, $defaults) {
    $font_keys = ['heading_font', 'body_font', 'accent_font'];
    $fonts_to_load = [];

    foreach ($font_keys as $key) {
        $val = !empty($settings[$key]) ? $settings[$key] : ($defaults['typography'][$key] ?? null);
        if ($val) {
            $fonts_to_load[] = urlencode($val);
        }
    }

    $fonts_to_load = array_unique(array_filter($fonts_to_load));

    if (empty($fonts_to_load)) {
        return '';
    }

    $family_str = '';
    foreach ($fonts_to_load as $font) {
        $family_str .= "family=" . $font . ":ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&";
    }

    return "https://fonts.googleapis.com/css2?{$family_str}display=swap";
}