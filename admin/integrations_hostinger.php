<?php
// admin/integrations_hostinger.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/integrations/hostinger/client.php';

require_role('admin');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/integrations_hostinger.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_token') {
        $token = trim($_POST['hostinger_api_token'] ?? '');
        $status = $_POST['hostinger_status'] ?? 'disabled';

        // Preserve existing token if blank
        if (empty($token)) {
            $token_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'hostinger_api_token'");
            $token = $token_stmt->fetchColumn() ?: '';
        }

        if (empty($token) && $status === 'enabled') {
            set_flash_message('error', 'API Token is required to enable the integration.');
            redirect('/admin/integrations_hostinger.php');
        }

        // Test the connection before saving if enabled
        if ($status === 'enabled') {
            $client = new HostingerClient($token);
            $check = $client->test_connection();

            if (!$check['success']) {
                set_flash_message('error', 'Integration failed: ' . escape($check['error']));
                redirect('/admin/integrations_hostinger.php');
            }

            // Log successful connection
            log_activity($pdo, null, $_SESSION['user_id'], 'hostinger_connected', 'system', null);
            set_flash_message('success', 'Hostinger API successfully connected and verified!');
        } else {
            log_activity($pdo, null, $_SESSION['user_id'], 'hostinger_disconnected', 'system', null);
            set_flash_message('success', 'Hostinger integration disabled.');
        }

        // Save safely
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        // Basic mask trick for storage if we don't have an encryption layer; ideally this is encrypted via libsodium/openssl.
        $stmt->execute(['hostinger_api_token', $token]);
        $stmt->execute(['hostinger_status', $status]);

    } elseif ($action === 'test_connection') {
        $token_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'hostinger_api_token'");
        $token = $token_stmt->fetchColumn() ?: '';

        $client = new HostingerClient($token);
        $check = $client->test_connection();

        if ($check['success']) {
            set_flash_message('success', 'Connection test passed! API is responding correctly.');
        } else {
            set_flash_message('error', 'Connection test failed: ' . escape($check['error']));
        }
    }

    redirect('/admin/integrations_hostinger.php');
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('hostinger_api_token', 'hostinger_status')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$token = $settings['hostinger_api_token'] ?? '';
$masked_token = !empty($token) ? '****************' . substr($token, -4) : '';
$status = $settings['hostinger_status'] ?? 'disabled';

$page_title = "Hostinger Integration";
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Integrations: Hostinger</h1>
    <a href="/admin/settings.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Settings</a>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex align-items-center">
                <i class="bi bi-hdd-network fs-4 text-primary me-2"></i>
                <h5 class="fw-bold mb-0">API Configuration</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="save_token">

                    <div class="mb-3">
                        <label class="form-label fw-medium">Integration Status</label>
                        <select name="hostinger_status" class="form-select">
                            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                            <option value="enabled" <?= $status === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium">API Bearer Token</label>
                        <input type="text" name="hostinger_api_token" class="form-control" placeholder="<?= empty($token) ? 'Enter your Hostinger API Token' : $masked_token ?>">
                        <div class="form-text">Leave blank to keep existing token. Token is masked for security.</div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary px-4">Save Integration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Capabilities</h5>
                <span class="badge <?= $status === 'enabled' ? 'bg-success' : 'bg-secondary' ?>"><?= $status === 'enabled' ? 'Connected' : 'Not Configured' ?></span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Domain Availability
                        <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Domain Catalog & Pricing
                        <span class="badge bg-secondary rounded-pill">Limited / Mock</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Domain Registration
                        <span class="badge bg-secondary rounded-pill">Limited / Mock</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        DNS Management
                        <span class="badge bg-secondary rounded-pill">Limited / Mock</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Hosting Management
                        <span class="badge bg-danger rounded-pill">Unsupported</span>
                    </li>
                </ul>

                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="test_connection">
                    <button type="submit" class="btn btn-outline-info w-100" <?= $status === 'disabled' ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat me-2"></i>Test Live Connection</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
