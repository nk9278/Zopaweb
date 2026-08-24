<?php
// user/theme.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/theme.php');
    }

    $primary_color = trim($_POST['primary_color'] ?? '');
    $secondary_color = trim($_POST['secondary_color'] ?? '');
    $accent_color = trim($_POST['accent_color'] ?? '');
    $background_color = trim($_POST['background_color'] ?? '');
    $text_color = trim($_POST['text_color'] ?? '');
    $font_selection = trim($_POST['font_selection'] ?? '');

    // Validate hex colors safely
    $colors = [$primary_color, $secondary_color, $accent_color, $background_color, $text_color];
    foreach($colors as $color) {
        if (!empty($color) && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            set_flash_message('error', 'Invalid color format detected.');
            redirect('/user/theme.php');
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM theme_settings WHERE website_id = ?");
    $stmt->execute([$website_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare("UPDATE theme_settings SET primary_color=?, secondary_color=?, accent_color=?, background_color=?, text_color=?, font_selection=? WHERE website_id=?");
        $update->execute([$primary_color, $secondary_color, $accent_color, $background_color, $text_color, $font_selection, $website_id]);
    } else {
        $insert = $pdo->prepare("INSERT INTO theme_settings (website_id, primary_color, secondary_color, accent_color, background_color, text_color, font_selection) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([$website_id, $primary_color, $secondary_color, $accent_color, $background_color, $text_color, $font_selection]);
    }

    set_flash_message('success', 'Theme settings saved successfully.');
    redirect('/user/theme.php');
}

$stmt = $pdo->prepare("SELECT * FROM theme_settings WHERE website_id = ?");
$stmt->execute([$website_id]);
$theme = $stmt->fetch() ?: [];

$page_title = "Theme Settings";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Theme Settings</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<?php display_flash_message(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Primary Color</label>
                    <div class="d-flex align-items-center">
                        <input type="color" name="primary_color" class="form-control form-control-color me-2" value="<?= escape($theme['primary_color'] ?? '#cda894') ?>" title="Choose your primary color">
                        <span class="text-muted small">Brand identity color (Buttons, active links)</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Secondary Color</label>
                    <div class="d-flex align-items-center">
                        <input type="color" name="secondary_color" class="form-control form-control-color me-2" value="<?= escape($theme['secondary_color'] ?? '#6c757d') ?>">
                        <span class="text-muted small">Supporting color elements</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Accent Color</label>
                    <div class="d-flex align-items-center">
                        <input type="color" name="accent_color" class="form-control form-control-color me-2" value="<?= escape($theme['accent_color'] ?? '#e8c1b5') ?>">
                        <span class="text-muted small">Highlights and borders</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Background Color</label>
                    <div class="d-flex align-items-center">
                        <input type="color" name="background_color" class="form-control form-control-color me-2" value="<?= escape($theme['background_color'] ?? '#ffffff') ?>">
                        <span class="text-muted small">Global background hue</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Text Color</label>
                    <div class="d-flex align-items-center">
                        <input type="color" name="text_color" class="form-control form-control-color me-2" value="<?= escape($theme['text_color'] ?? '#333333') ?>">
                        <span class="text-muted small">Main body text</span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-medium">Typography (Font Selection)</label>
                    <select name="font_selection" class="form-select">
                        <option value="Playfair/Lato" <?= ($theme['font_selection'] ?? '') === 'Playfair/Lato' ? 'selected' : '' ?>>Playfair Display (Headings) / Lato (Body)</option>
                        <option value="Montserrat/OpenSans" <?= ($theme['font_selection'] ?? '') === 'Montserrat/OpenSans' ? 'selected' : '' ?>>Montserrat (Headings) / Open Sans (Body)</option>
                        <option value="Oswald/Roboto" <?= ($theme['font_selection'] ?? '') === 'Oswald/Roboto' ? 'selected' : '' ?>>Oswald (Headings) / Roboto (Body)</option>
                        <option value="Lora/SourceSans" <?= ($theme['font_selection'] ?? '') === 'Lora/SourceSans' ? 'selected' : '' ?>>Lora (Headings) / Source Sans (Body)</option>
                    </select>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-2">Save Theme Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
