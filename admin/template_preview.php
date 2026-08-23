<?php
// admin/template_preview.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template_engine.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin'); // Only admins can access this preview tool
$pdo = getDB();

$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("Template not found.");
}

// Ensure the template files actually exist before trying to render
$validation = validate_template_manifest($template['folder_key']);
if (!$validation['valid']) {
    die("Cannot preview: Template is invalid or missing required files.<br>Error: " . escape($validation['error']));
}

// Generate Demo Data representing a realistic site
$demo_data = [
    'site' => [
        'website_name' => 'Demo Makeup Studio',
        'website_slug' => 'demo-studio'
    ],
    'business' => [
        'name' => 'Demo Makeup Studio',
        'email' => 'hello@demostudio.com',
        'phone' => '+1 555-0198',
        'tagline' => 'Enhancing Your Natural Beauty',
        'about' => 'This is a live preview of the template using demonstration data. I specialize in bridal, editorial, and special event makeup.',
        'hero_image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&q=80&w=1600'
    ],
    'services' => [
        ['name' => 'Bridal Package', 'price' => '₹18,000', 'description' => 'Full bridal makeup and styling.'],
        ['name' => 'Party Glow', 'price' => '₹6,500', 'description' => 'Perfect for special evening events.'],
        ['name' => 'Editorial Look', 'price' => '₹12,000', 'description' => 'High fashion and photography makeup.']
    ],
    'gallery' => [
        'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&q=80&w=800',
        'https://images.unsplash.com/photo-1596704017254-9b121068fb31?auto=format&fit=crop&q=80&w=800',
        'https://images.unsplash.com/photo-1512496015851-a1dc8a473105?auto=format&fit=crop&q=80&w=800'
    ],
    'reviews' => [
        ['client' => 'Sarah J.', 'rating' => 5, 'text' => 'An absolute dream to work with. The preview looks fantastic!'],
        ['client' => 'Maria V.', 'rating' => 5, 'text' => 'Highly professional and incredibly talented.']
    ],
    'social' => [
        'instagram' => '#',
        'facebook' => '#'
    ]
];

// Add an admin overlay to let the user know they are in preview mode
ob_start();
render_page($template, $demo_data, 'home');
$html = ob_get_clean();

$overlay = '
<div style="position:fixed; top:0; left:0; right:0; z-index:99999; background: #0d6efd; color:#fff; text-align:center; padding:10px; font-family:sans-serif; font-size:14px; box-shadow:0 2px 10px rgba(0,0,0,0.2);">
    <strong>Admin Preview Mode:</strong> Viewing <em>' . escape($template['name']) . '</em> (v' . escape($template['version']) . ') with demo data.
    <a href="/admin/templates.php" style="color:#fff; text-decoration:underline; margin-left:15px; font-weight:bold;">Exit Preview</a>
</div>
';

echo str_replace('<body', '<body style="padding-top:40px;" ', $html);
echo $overlay;
