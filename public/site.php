<?php
// public/site.php
// A stub representing the public routing/rendering engine for Phase 4 validation
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/template_engine.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$website_id = isset($_GET['website_id']) ? (int)$_GET['website_id'] : 0;

if ($website_id <= 0) {
    die("Invalid website ID.");
}

// 1. Fetch isolated data
$data = get_website_data($pdo, $website_id);

if (!$data) {
    // Render fallback if website data couldn't be loaded (e.g. inactive or soft-deleted)
    render_fallback("Website is currently inactive or deleted.");
    exit;
}

// 2. Render Template
// The engine extracts $site, $business, $services, etc., and injects them safely.
render_page($data['template'], $data, 'home');
