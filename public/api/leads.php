<?php
// public/api/leads.php
// Secure Lead Capture API Endpoint
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/host_resolver.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    die();
}

$pdo = getDB();
$host = $_SERVER['HTTP_HOST'] ?? '';
$resolution = resolveWebsiteFromHost($pdo, $host);

// Verify we are legitimately on a customer's website context
if ($resolution['error'] || $resolution['context'] === 'platform') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid website context.']);
    die();
}

$website_id = $resolution['website_id'];

// CSRF check (We pass a simple origin/token approach since traditional session cookies might be tricky on custom domains if not shared)
// To keep it robust but generic, we rely on standard session CSRF if available, otherwise origin checks.
// Since templates render on the same domain, standard session CSRF token is expected to be passed via POST.
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    // If strict session token fails, ensure we check Origin to prevent broad CSRF execution across domains
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (strpos($origin, $host) === false) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security token invalid.']);
        die();
    }
}

// 1. Honeypot check
if (!empty($_POST['website_url_hp'])) {
    // Bot detected. Return silent success to trick bot
    echo json_encode(['success' => true]);
    die();
}

// 2. Rate Limiting (Simple IP based Flood Protection)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// Check if more than 5 leads in the last hour from this IP
// Check if more than 5 leads in the last hour from this IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE website_id = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
$rate_stmt->execute([$website_id, $ip]);
if ($rate_stmt->fetchColumn() >= 5) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests from your IP. Please try again later.']);
    die();
}

// 3. Collect and Sanitize Inputs
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
$preferred_date = trim($_POST['preferred_date'] ?? '');
$preferred_time = trim($_POST['preferred_time'] ?? '');
$message = trim($_POST['message'] ?? '');
$source = trim($_POST['source'] ?? 'direct');
$form_type = trim($_POST['form_type'] ?? 'inquiry');

// Strip any malicious HTML tags
$name = strip_tags($name);
$message = strip_tags($message);
$phone = strip_tags($phone);

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name and Phone are required.']);
    die();
}

// Basic Phone validation (keep it loose to allow international/formatting, but block huge payloads)
if (strlen($phone) < 7 || strlen($phone) > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid phone number format.']);
    die();
}

// Ensure service actually belongs to the website if provided
if ($service_id) {
    $srv_stmt = $pdo->prepare("SELECT id FROM services WHERE id = ? AND website_id = ?");
    $srv_stmt->execute([$service_id, $website_id]);
    if (!$srv_stmt->fetch()) {
        $service_id = null; // silently drop invalid service association
    }
}

// Date validation basic
if ($preferred_date) {
    $d = DateTime::createFromFormat('Y-m-d', $preferred_date);
    if (!$d || $d->format('Y-m-d') !== $preferred_date) {
        $preferred_date = null;
    }
}

// 4. Insert Lead
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare("
        INSERT INTO leads
        (website_id, name, phone, service_id, preferred_date, preferred_time, message, source, form_type, status, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?)
    ");
    $insert->execute([
        $website_id, $name, $phone, $service_id, $preferred_date, $preferred_time, $message, $source, $form_type, $ip
    ]);

    // 5. Build WhatsApp Action URL
    $bp_stmt = $pdo->prepare("SELECT whatsapp, business_name FROM business_profiles WHERE website_id = ? LIMIT 1");
    $bp_stmt->execute([$website_id]);
    $bp = $bp_stmt->fetch();

    $whatsapp_url = null;
    if ($bp && !empty($bp['whatsapp'])) {
        // Clean whatsapp number (remove non-numeric, allow +)
        $clean_wa = preg_replace('/[^0-9+]/', '', $bp['whatsapp']);
        if (!empty($clean_wa)) {
            $wa_text = "Hi, I just submitted an inquiry on your website.\nName: $name";
            if ($preferred_date) $wa_text .= "\nDate: $preferred_date";
            $whatsapp_url = "https://wa.me/" . ltrim($clean_wa, '+') . "?text=" . rawurlencode($wa_text);
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Thanks! Your enquiry has been received.',
        'whatsapp_url' => $whatsapp_url
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}
