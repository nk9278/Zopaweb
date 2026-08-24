<?php
// public/api/lead.php
// Secure endpoint for handling lead submissions
require_once __DIR__ . "/../../includes/session.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/security.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed."]);
    die();
}

// Check CSRF
if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Invalid security token."]);
    die();
}

$pdo = getDB();

// Layer 5: Request throttling via strict Global Rate Limits
if (check_api_rate_limit($pdo, 'lead_form', 5, '1 HOUR')) {
    http_response_code(429);
    header("Retry-After: 3600");
    echo json_encode(["success" => false, "error" => "Rate limit exceeded. Please try again later."]);
    die();
}
$ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

$website_id = (int)($_POST["website_id"] ?? 0);
if (!$website_id) {
    echo json_encode(["success" => false, "error" => "Website configuration error."]);
    die();
}

// Extract and sanitize inputs
$name = strip_tags(trim($_POST["name"] ?? ""));
$phone = strip_tags(trim($_POST["phone"] ?? ""));
$whatsapp = strip_tags(trim($_POST["whatsapp"] ?? ""));
$service = strip_tags(trim($_POST["service"] ?? ""));
$pref_date = trim($_POST["preferred_date"] ?? "");
$pref_time = strip_tags(trim($_POST["preferred_time"] ?? ""));
$message = strip_tags(trim($_POST["message"] ?? ""));
$lead_type = in_array($_POST["lead_type"] ?? "", ["quote", "appointment", "enquiry"]) ? $_POST["lead_type"] : "enquiry";
$source = "website_form"; // Force source since it is via this endpoint

// Validation
if (empty($name)) {
    echo json_encode(["success" => false, "error" => "Please enter your name."]);
    die();
}

if (empty($phone)) {
    echo json_encode(["success" => false, "error" => "Please enter a valid phone number."]);
    die();
}

// Normalize phone numbers strictly loosely (allow +, digits, space, hyphen, brackets)
if (!preg_match("/^[0-9+\-\(\)\s]{5,20}$/", $phone)) {
    echo json_encode(["success" => false, "error" => "Please enter a valid phone number format."]);
    die();
}

if (strlen($message) > 2000) {
    echo json_encode(["success" => false, "error" => "Message is too long. Maximum 2000 characters."]);
    die();
}

// Date validation
if (!empty($pref_date)) {
    $d = DateTime::createFromFormat("Y-m-d", $pref_date);
    if (!$d || $d->format("Y-m-d") !== $pref_date) {
        echo json_encode(["success" => false, "error" => "Please enter a valid date."]);
        die();
    }
} else {
    $pref_date = null;
}

$notes = "Captured via website form.\nIP: $ip";

$stmt = $pdo->prepare("
    INSERT INTO leads (website_id, name, phone, whatsapp, service, preferred_date, preferred_time, message, lead_type, source, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \"new\", ?)
");

$stmt->execute([
    $website_id, $name, $phone, $whatsapp, $service, $pref_date, $pref_time, $message, $lead_type, $source, $notes
]);

// Optionally trigger lead_event tracking here or rely on JS beacon.

echo json_encode([
    "success" => true,
    "message" => "Thank you! Your enquiry has been received."
]);
