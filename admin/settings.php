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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Settings</h2>
</div>

<form method="POST" action="">
    <?php csrf_field(); ?>

    <div class="row g-4">
        <!-- Navigation -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm sticky-top" style="top: 80px; z-index: 10;">
                <div class="list-group list-group-flush rounded" id="settings-tabs" role="tablist">
                    <a class="list-group-item list-group-item-action active border-0" id="list-general-list" data-bs-toggle="list" href="#list-general" role="tab">General</a>
                    <a class="list-group-item list-group-item-action border-0" id="list-contact-list" data-bs-toggle="list" href="#list-contact" role="tab">Contact</a>
                    <a class="list-group-item list-group-item-action border-0" id="list-plans-list" data-bs-toggle="list" href="#list-plans" role="tab">Plans</a>
                    <a class="list-group-item list-group-item-action border-0" id="list-system-list" data-bs-toggle="list" href="#list-system" role="tab">System</a>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="col-md-9">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 tab-content" id="nav-tabContent">

                    <!-- General Tab -->
                    <div class="tab-pane fade show active" id="list-general" role="tabpanel">
                        <h5 class="mb-4">General Settings</h5>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Platform Name</label>
                            <input type="text" name="platform_name" class="form-control" value="<?= escape($settings['platform_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Tagline</label>
                            <input type="text" name="tagline" class="form-control" value="<?= escape($settings['tagline']) ?>" required>
                        </div>
                        <div class="alert alert-info border-0 shadow-sm small mt-4 mb-0">
                            <strong>Note:</strong> Logo and Favicon uploads will be available in the next phase.
                        </div>
                    </div>

                    <!-- Contact Tab -->
                    <div class="tab-pane fade" id="list-contact" role="tabpanel">
                        <h5 class="mb-4">Contact Information</h5>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Support WhatsApp</label>
                            <input type="text" name="support_whatsapp" class="form-control" value="<?= escape($settings['support_whatsapp']) ?>">
                            <div class="form-text">Used for WhatsApp click-to-chat links across the platform.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Support Phone</label>
                            <input type="text" name="support_phone" class="form-control" value="<?= escape($settings['support_phone']) ?>">
                        </div>
                    </div>

                    <!-- Plans Tab -->
                    <div class="tab-pane fade" id="list-plans" role="tabpanel">
                        <h5 class="mb-4">Plans & Billing</h5>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Default Currency</label>
                            <input type="text" name="default_currency" class="form-control" value="<?= escape($settings['default_currency']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Paid Plan Price (Yearly)</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= escape($settings['default_currency']) ?></span>
                                <input type="number" step="0.01" name="paid_plan_price" class="form-control" value="<?= escape($settings['paid_plan_price']) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- System Tab -->
                    <div class="tab-pane fade" id="list-system" role="tabpanel">
                        <h5 class="mb-4">System Configuration</h5>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Timezone</label>
                            <input type="text" name="timezone" class="form-control" value="<?= escape($settings['timezone']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Global Upload Limit (MB)</label>
                            <input type="number" name="upload_limit" class="form-control" value="<?= escape($settings['upload_limit']) ?>">
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top-0 px-4 py-3 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
