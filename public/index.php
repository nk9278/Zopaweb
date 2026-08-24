<?php
// public/index.php

// In a real-world configuration, this file acts as the primary DirectoryIndex
// for the entire application, capturing all wildcard domain requests.

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/template_engine.php';
require_once __DIR__ . '/../includes/host_resolver.php';
require_once __DIR__ . '/../config/database.php';

// Apply basic security headers safely without breaking template styles
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

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

// 5. Parse Page Request
// Determine what page the user is trying to view
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);

// Clean up path (e.g., "/public/index.php/about" -> "/about")
$path = str_replace(['/public/index.php', '/index.php'], '', $path);
$path = trim($path, '/');

if (empty($path)) {
    $page = 'home';
} else {
    // Sanitize path to alphanumeric/hyphens to determine the page securely
    $page = preg_replace('/[^a-z0-9-]/', '', strtolower($path));
}

// 3.5 Intercept Dynamic SEO Endpoints (robots.txt & sitemap.xml)
if ($path === 'robots.txt') {
    // We only serve indexable rules if the site itself is published and not hidden
    $seo_stmt = $pdo->prepare("SELECT search_engine_visibility FROM website_seo WHERE website_id = ?");
    $seo_stmt->execute([$website_id]);
    $seo_config = $seo_stmt->fetch();
    $visible = ($resolution['publication_status'] === 'published' && (!$seo_config || $seo_config['search_engine_visibility'] == 1));

    header("Content-Type: text/plain; charset=UTF-8");
    if ($visible) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Sitemap: " . $protocol . $host . "/sitemap.xml\n";
    } else {
        echo "User-agent: *\n";
        echo "Disallow: /\n";
    }
    die();
}

if ($path === 'sitemap.xml') {
    $seo_stmt = $pdo->prepare("SELECT search_engine_visibility FROM website_seo WHERE website_id = ?");
    $seo_stmt->execute([$website_id]);
    $seo_config = $seo_stmt->fetch();
    $visible = ($resolution['publication_status'] === 'published' && (!$seo_config || $seo_config['search_engine_visibility'] == 1));

    header("Content-Type: application/xml; charset=UTF-8");
    if (!$visible) {
        // Return valid empty sitemap if not indexable
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        die();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $base_url = $protocol . $host;

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $page_stmt = $pdo->prepare("SELECT slug, is_homepage, updated_at FROM pages WHERE website_id = ? AND status = 'published' ORDER BY sort_order ASC");
    $page_stmt->execute([$website_id]);

    while ($p = $page_stmt->fetch(PDO::FETCH_ASSOC)) {
        $loc = $p['is_homepage'] ? $base_url . '/' : $base_url . '/' . $p['slug'];
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        echo "    <lastmod>" . date('Y-m-d\TH:i:sP', strtotime($p['updated_at'])) . "</lastmod>\n";
        // Simple priority heuristics
        $priority = $p['is_homepage'] ? '1.0' : '0.8';
        echo "    <priority>$priority</priority>\n";
        echo "  </url>\n";
    }

    echo '</urlset>';
    die();
}

// 4. Fetch Isolated Data
$data = get_website_data($pdo, $website_id);

if (!$data || empty($data['template'])) {
    render_fallback("Template configuration is missing.");
    exit;
}

// 5. Parse Page Request
// Determine what page the user is trying to view
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH);

// Clean up path (e.g., "/index.php/about" -> "/about")
$path = str_replace(['/public/index.php', '/index.php'], '', $path);
$path = trim($path, '/');

if (empty($path)) {
    $page = 'home';
} else {
    // Sanitize path to alphanumeric/hyphens to determine the page securely
    $page = preg_replace('/[^a-z0-9-]/', '', strtolower($path));
}

// 6. Render the Template
// The engine verifies if the requested page is supported in the manifest
render_page($data['template'], $data, $page);
