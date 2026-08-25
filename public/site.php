<?php
// public/site.php
// Secure Preview Endpoint for R3 Page Builder

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/template_engine.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$website_id = isset($_GET['website_id']) ? (int)$_GET['website_id'] : 0;
$page_slug = isset($_GET['page']) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['page'])) : 'home';
$is_preview = isset($_GET['preview']) && $_GET['preview'] === 'true';

// 1. Verify Ownership & Authentication for Previews
if ($is_preview) {
    if (!is_logged_in()) {
        redirect('/auth/login.php');
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$website_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have permission to preview this website.</p>";
        die();
    }
} else {
    // If not preview, this shouldn't be used for public routing, public/index.php does that
    redirect('/');
}

// 2. Fetch Isolated Data
$data = get_website_data($pdo, $website_id);

if (!$data || empty($data['template'])) {
    render_fallback("Template configuration is missing.");
    die();
}

// 3. Check Page Existence in Database
$page_stmt = $pdo->prepare("SELECT * FROM pages WHERE website_id = ? AND slug = ? LIMIT 1");
$page_stmt->execute([$website_id, $page_slug]);
$page_record = $page_stmt->fetch(PDO::FETCH_ASSOC);

if (!$page_record) {
    render_404();
    die();
}

// Ensure unpublished pages are ONLY visible in authenticated preview mode
if ($page_record['status'] !== 'published' && !$is_preview) {
    render_unpublished();
    die();
}

// Expose page record data to template
$data['page'] = $page_record;

// 4. Render Template
render_page($data['template'], $data, $page_slug);
