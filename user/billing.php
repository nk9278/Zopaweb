<?php
// user/billing.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website strictly verifying ownership
$stmt = $pdo->prepare("SELECT id, website_slug FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];

// Fetch Active Subscription & Plan
$sub_stmt = $pdo->prepare("
    SELECT s.*, p.name as plan_name, p.storage_limit, p.custom_domain_allowed, p.price as plan_price
    FROM subscriptions s
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE s.website_id = ?
    ORDER BY s.id DESC LIMIT 1
");
$sub_stmt->execute([$website_id]);
$subscription = $sub_stmt->fetch(PDO::FETCH_ASSOC);

// If no active subscription, implicitly assign free plan rules gracefully
if (!$subscription) {
    $plan_stmt = $pdo->query("SELECT * FROM plans WHERE id = 1 LIMIT 1");
    $free_plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);

    // Auto-create free subscription natively
    $insert = $pdo->prepare("INSERT INTO subscriptions (user_id, website_id, plan_id, plan_name, plan_price, billing_cycle, status, start_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 10 YEAR))");
    $insert->execute([$user_id, $website_id, $free_plan['id'], $free_plan['name'], $free_plan['price'], $free_plan['billing_interval']]);

    // Reload
    $sub_stmt->execute([$website_id]);
    $subscription = $sub_stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/billing.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upgrade') {
        $target_plan_id = (int)($_POST['plan_id'] ?? 0);

        // Server-side validation of plan
        $p_stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND active = 1");
        $p_stmt->execute([$target_plan_id]);
        $target_plan = $p_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target_plan) {
            set_flash_message('error', 'Invalid plan selected.');
            redirect('/user/billing.php');
        }

        if ($target_plan['id'] == $subscription['plan_id']) {
            set_flash_message('info', 'You are already on this plan.');
            redirect('/user/billing.php');
        }

        // --- MOCK PAYMENT GATEWAY ABSTRACTION ---
        // In reality, this redirects to Stripe Checkout with target_plan_id securely mapped in session.
        // For R7, we execute a successful transaction mock cleanly updating states natively.

        $pdo->beginTransaction();
        try {
            // 1. Log Payment
            $tx_id = 'mock_tx_' . time();
            $pay_stmt = $pdo->prepare("INSERT INTO payments (user_id, website_id, subscription_id, payment_type, amount, currency, gateway, transaction_id, status, payment_date) VALUES (?, ?, ?, 'subscription', ?, 'INR', 'mock_gateway', ?, 'completed', NOW())");
            $pay_stmt->execute([$user_id, $website_id, $subscription['id'], $target_plan['price'], $tx_id]);

            // 2. Update Subscription State
            $upd_sub = $pdo->prepare("UPDATE subscriptions SET plan_id = ?, plan_name = ?, plan_price = ?, billing_cycle = ?, status = 'active', start_date = NOW(), expiry_date = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ? AND website_id = ?");
            $upd_sub->execute([$target_plan['id'], $target_plan['name'], $target_plan['price'], $target_plan['billing_interval'], $subscription['id'], $website_id]);

            // 3. Update Website core state tracker
            $pdo->prepare("UPDATE websites SET subscription_status = 'paid' WHERE id = ?")->execute([$website_id]);

            $pdo->commit();
            set_flash_message('success', 'Payment successful! You have been upgraded to the ' . escape($target_plan['name']) . ' plan.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('error', 'Payment transaction failed. Please try again.');
        }
    }

    redirect('/user/billing.php');
}

// Fetch all available active plans
$plans = $pdo->query("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Payment History
$hist_stmt = $pdo->prepare("SELECT * FROM payments WHERE website_id = ? ORDER BY payment_date DESC");
$hist_stmt->execute([$website_id]);
$payments = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

// Storage Usage Check
$quota_stmt = $pdo->prepare("SELECT SUM(file_size) FROM media WHERE website_id = ?");
$quota_stmt->execute([$website_id]);
$used_bytes = (int)$quota_stmt->fetchColumn();
$limit_bytes = (int)($subscription['storage_limit'] ?? 52428800);
$usage_percent = min(100, round(($used_bytes / $limit_bytes) * 100));

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$page_title = "Billing & Subscription";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Billing & Subscription</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<?php display_flash_message(); ?>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3">Current Plan</h6>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="display-6 fw-bold mb-0 text-primary"><?= escape($subscription['plan_name']) ?></h2>
                    <span class="badge <?= $subscription['status'] === 'active' ? 'bg-success' : 'bg-danger' ?> px-3 py-2 rounded-pill"><?= escape(ucfirst($subscription['status'])) ?></span>
                </div>

                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Billing Cycle:</strong> <?= escape(ucfirst($subscription['billing_cycle'])) ?></li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Renewal Date:</strong> <?= date('F j, Y', strtotime($subscription['expiry_date'])) ?></li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Custom Domain:</strong> <?= $subscription['custom_domain_allowed'] ? 'Enabled' : 'Not Included' ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3">Storage Quota</h6>
                <div class="d-flex justify-content-between mb-1">
                    <span class="fw-medium text-dark"><?= formatBytes($used_bytes) ?> Used</span>
                    <span class="text-muted"><?= formatBytes($limit_bytes) ?> Total</span>
                </div>
                <div class="progress mb-3" style="height: 12px;">
                    <div class="progress-bar <?= $usage_percent > 90 ? 'bg-danger' : ($usage_percent > 75 ? 'bg-warning' : 'bg-primary') ?>" role="progressbar" style="width: <?= $usage_percent ?>%" aria-valuenow="<?= $usage_percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <?php if ($usage_percent > 90): ?>
                    <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> You are running out of storage. Please upgrade your plan to upload more media.</p>
                <?php else: ?>
                    <p class="text-muted small mb-0">Storage includes all images, webp optimizations, and thumbnails across your website.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h4 class="fw-bold mb-3">Available Plans</h4>
<div class="row g-4 mb-5">
    <?php foreach ($plans as $p): ?>
        <div class="col-md-6">
            <div class="card border-<?= $subscription['plan_id'] == $p['id'] ? 'primary' : '0' ?> shadow-sm h-100 <?= $subscription['plan_id'] == $p['id'] ? 'bg-primary bg-opacity-10' : '' ?>">
                <div class="card-body p-4 text-center">
                    <?php if ($subscription['plan_id'] == $p['id']): ?>
                        <span class="badge bg-primary position-absolute top-0 end-0 mt-3 me-3">Current Plan</span>
                    <?php endif; ?>

                    <h3 class="fw-bold mb-1"><?= escape($p['name']) ?></h3>
                    <div class="display-5 fw-bold text-dark mb-3">₹<?= escape((int)$p['price']) ?><span class="fs-6 text-muted fw-normal">/<?= escape($p['billing_interval']) ?></span></div>

                    <ul class="list-unstyled text-start mb-4 mx-auto" style="max-width: 250px;">
                        <li class="mb-2"><i class="bi bi-cloud-check text-primary me-2"></i><?= formatBytes($p['storage_limit']) ?> Media Storage</li>
                        <li class="mb-2"><i class="bi bi-<?= $p['custom_domain_allowed'] ? 'check-circle text-primary' : 'x-circle text-muted' ?> me-2"></i>Custom Domain Support</li>
                        <li class="mb-2"><i class="bi bi-headset text-primary me-2"></i>Standard Support</li>
                    </ul>

                    <?php if ($subscription['plan_id'] != $p['id']): ?>
                        <form method="POST">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="upgrade">
                            <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-<?= $p['price'] > $subscription['plan_price'] ? 'primary' : 'outline-dark' ?> w-100 fw-bold">
                                <?= $p['price'] > $subscription['plan_price'] ? 'Upgrade Now' : 'Downgrade' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 fw-bold disabled">Active</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h4 class="fw-bold mb-3">Payment History</h4>
<div class="card border-0 shadow-sm mb-5">
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
                No payment history available.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Description</th>
                            <th>Transaction ID</th>
                            <th>Amount</th>
                            <th class="pe-4 text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $pay): ?>
                            <tr>
                                <td class="ps-4"><?= date('M j, Y', strtotime($pay['payment_date'])) ?></td>
                                <td>Plan Subscription</td>
                                <td class="text-muted small"><code><?= escape($pay['transaction_id']) ?></code></td>
                                <td>₹<?= escape((int)$pay['amount']) ?></td>
                                <td class="pe-4 text-end"><span class="badge bg-success">Completed</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
