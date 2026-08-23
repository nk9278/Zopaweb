<?php
// public/api/event.php
// Lightweight beacon endpoint for CTA click analytics

require_once __DIR__ . "/../../config/database.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die();
}

$website_id = (int)($_POST["website_id"] ?? 0);
$event_type = $_POST["event_type"] ?? "";
$page_path = $_POST["page_path"] ?? "";
$section_type = $_POST["section_type"] ?? "";

// Basic validation
if (!$website_id || empty($event_type) || !in_array($event_type, ["whatsapp_click", "call_click", "form_submit"])) {
    http_response_code(400);
    die();
}

$pdo = getDB();

// Quick insert (fire and forget)
try {
    $stmt = $pdo->prepare("
        INSERT INTO lead_events (website_id, event_type, page_path, section_type)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $website_id,
        substr($event_type, 0, 50),
        substr($page_path, 0, 255),
        substr($section_type, 0, 100)
    ]);
} catch (Exception $e) {
    // Fail silently for tracking beacons
}

echo json_encode(["success" => true]);
