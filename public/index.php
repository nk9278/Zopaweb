<?php
// public/index.php

// In a real-world configuration, this file acts as the primary DirectoryIndex
// for the entire application, capturing all wildcard domain requests.

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/template_engine.php';
require_once __DIR__ . '/../includes/host_resolver.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$host = $_SERVER['HTTP_HOST'] ?? '';

// 1. Resolve Host
$resolution = resolveWebsiteFromHost($pdo, $host);

// If this is the main platform domain (e.g. zopaweb.com / localhost), bypass template engine
// In this dev setup, we might just redirect to the auth system
if ($resolution['context'] === 'platform') {
    // If user requests something specifically, we can route it.
    // For now, redirect to login as default platform behavior.
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri === '/' || $uri === '/index.php' || $uri === '/public/index.php') {
        redirect('/auth/login.php');
    }
    // If they requested a specific valid script like /auth/login.php, it's typically handled by web server,
    // but in local PHP server it falls through here if rewrite rules apply.
    return false; // Let PHP dev server serve the actual file
}

// 2. Handle Resolution Errors
if ($resolution['error']) {
    // Not found or invalid subdomain
    if ($resolution['error'] === 'not_found' || $resolution['error'] === 'invalid_subdomain') {
        render_404();
        exit;
    }
    // Custom domain inactive
    if ($resolution['error'] === 'domain_inactive') {
        render_suspended();
        exit;
    }
}

$website_id = $resolution['website_id'];

// 3. Handle Website Statuses
if ($resolution['status'] === 'suspended') {
    render_suspended();
    exit;
}

if ($resolution['status'] !== 'active') {
    // archived or deleted (though deleted usually shouldn't resolve)
    render_404();
    exit;
}

// Handle Publication Statuses
if ($resolution['publication_status'] === 'maintenance') {
    render_maintenance();
    exit;
}

if ($resolution['publication_status'] === 'unpublished' || $resolution['publication_status'] === 'draft') {
    render_unpublished();
    exit;
}

// 4. Fetch Isolated Data
$data = get_website_data($pdo, $website_id);

if (!$data || empty($data['template'])) {
    render_fallback("Template configuration is missing.");
    exit;
}

// --- Phase 11 Sitemap & Robots Interception ---
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace(['/public/index.php', '/index.php'], '', $path);
$path = rtrim($path, '/');

// Determine base URL securely using the resolved slug and application platform config
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
// In a real app this platform domain would be in config/app.php
$base_url = "https://web." . $resolution['website_slug'] . ".zopaweb.com";


if ($path === '/robots.txt') {
    header('Content-Type: text/plain');
    if ($resolution['status'] !== 'active' || $resolution['publication_status'] !== 'published') {
        echo "User-agent: *\nDisallow: /\n";
    } else {
        $seo_stmt = $pdo->prepare("SELECT robots_index FROM website_seo WHERE website_id = ?");
        $seo_stmt->execute([$website_id]);
        $robots_index = $seo_stmt->fetchColumn();
        if ($robots_index === 0 || $robots_index === '0') {
            echo "User-agent: *\nDisallow: /\n";
        } else {
            echo "User-agent: *\nAllow: /\n\nSitemap: {$base_url}/sitemap.xml\n";
        }
    }
    die();
}

if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    if ($resolution['status'] === 'active' && $resolution['publication_status'] === 'published') {
        $seo_stmt = $pdo->prepare("SELECT robots_index FROM website_seo WHERE website_id = ?");
        $seo_stmt->execute([$website_id]);
        $global_index = $seo_stmt->fetchColumn();

        if ($global_index !== 0 && $global_index !== '0') {
            $pages_stmt = $pdo->prepare("SELECT slug, updated_at, robots_index FROM pages WHERE website_id = ? AND status = 'published' AND deleted_at IS NULL");
            $pages_stmt->execute([$website_id]);
            $pages = $pages_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pages as $p) {
                if ($p['robots_index'] !== 0 && $p['robots_index'] !== '0') {
                    $url = $base_url . '/' . ($p['slug'] === 'home' ? '' : ltrim($p['slug'], '/'));
                    $date = date('Y-m-d', strtotime($p['updated_at']));
                    echo "  <url>\n";
                    echo "    <loc>" . htmlspecialchars($url) . "</loc>\n";
                    echo "    <lastmod>{$date}</lastmod>\n";
                    echo "  </url>\n";
                }
            }
        }
    }

    echo '</urlset>';
    die();
}
// --- End Phase 11 Interception ---

// 5. Parse Page Request
// Determine what page the user is trying to view
// $path is already cleaned above
if (empty($path)) {
    $page = 'home';
} else {
    // Sanitize path to alphanumeric/hyphens to determine the page securely
    $page = preg_replace('/[^a-z0-9-]/', '', strtolower(ltrim($path, '/')));
}

// 6. Render the Template
// The engine verifies if the requested page is supported in the manifest
render_page($data['template'], $data, $page);
