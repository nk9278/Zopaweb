<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/settings.php');
    }

    $updates = [
        'platform_name' => trim($_POST['platform_name'] ?? ''),
        'tagline' => trim($_POST['tagline'] ?? ''),
        'support_whatsapp' => trim($_POST['support_whatsapp'] ?? ''),
        'support_phone' => trim($_POST['support_phone'] ?? ''),
        'default_currency' => trim($_POST['default_currency'] ?? 'INR'),
        'timezone' => trim($_POST['timezone'] ?? 'Asia/Kolkata'),
        'paid_plan_price' => trim($_POST['paid_plan_price'] ?? '2999'),
        'upload_limit' => trim($_POST['upload_limit'] ?? '100'),
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    $pdo->beginTransaction();
    try {
        foreach ($updates as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        $pdo->commit();
        set_flash_message('success', 'Settings updated successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash_message('error', 'Error updating settings.');
    }

    redirect('/admin/settings.php');
}

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Provide defaults if missing
$settings = [
    'platform_name' => $settings_db['platform_name'] ?? 'ZopaWeb',
    'tagline' => $settings_db['tagline'] ?? 'Your Work. Your Website.',
    'support_whatsapp' => $settings_db['support_whatsapp'] ?? '',
    'support_phone' => $settings_db['support_phone'] ?? '',
    'default_currency' => $settings_db['default_currency'] ?? 'INR',
    'timezone' => $settings_db['timezone'] ?? 'Asia/Kolkata',
    'paid_plan_price' => $settings_db['paid_plan_price'] ?? '2999',
    'upload_limit' => $settings_db['upload_limit'] ?? '100',
];

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<h2 class="mb-4">Global Settings</h2>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?php csrf_field(); ?>

            <h5 class="mb-3 border-bottom pb-2">General Info</h5>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Platform Name</label>
                    <input type="text" name="platform_name" class="form-control" value="<?= escape($settings['platform_name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tagline</label>
                    <input type="text" name="tagline" class="form-control" value="<?= escape($settings['tagline']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Support WhatsApp</label>
                    <input type="text" name="support_whatsapp" class="form-control" value="<?= escape($settings['support_whatsapp']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Support Phone</label>
                    <input type="text" name="support_phone" class="form-control" value="<?= escape($settings['support_phone']) ?>">
                </div>
            </div>

            <h5 class="mb-3 border-bottom pb-2">Configuration</h5>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Default Currency</label>
                    <input type="text" name="default_currency" class="form-control" value="<?= escape($settings['default_currency']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="<?= escape($settings['timezone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Paid Plan Price (yearly)</label>
                    <input type="number" step="0.01" name="paid_plan_price" class="form-control" value="<?= escape($settings['paid_plan_price']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Upload Limit (MB)</label>
                    <input type="number" name="upload_limit" class="form-control" value="<?= escape($settings['upload_limit']) ?>">
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary px-4">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
