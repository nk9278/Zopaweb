<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'You need to create a website first.');
    redirect('/user/dashboard.php');
}
$website_id = $website['id'];

// Handle POST Payment Mock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/billing.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'subscribe') {
        $plan_id = (int)$_POST['plan_id'];
        $p_stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $p_stmt->execute([$plan_id]);
        $plan = $p_stmt->fetch(PDO::FETCH_ASSOC);

        if ($plan) {
            // Mock successful payment transaction
            $transaction_id = 'pay_' . bin2hex(random_bytes(8));

            $pay_ins = $pdo->prepare("INSERT INTO payments (user_id, website_id, payment_type, amount, currency, gateway, transaction_id, status, payment_date) VALUES (?, ?, 'subscription', ?, 'INR', 'mock_gateway', ?, 'completed', NOW())");
            $pay_ins->execute([$user_id, $website_id, $plan['price'], $transaction_id]);
            $payment_id = $pdo->lastInsertId();

            // Check existing subscription
            $sub_check = $pdo->prepare("SELECT id FROM subscriptions WHERE website_id = ?");
            $sub_check->execute([$website_id]);
            $existing_sub = $sub_check->fetch();

            $interval = $plan['billing_interval'] === 'monthly' ? '+1 month' : '+1 year';

            if ($existing_sub) {
                // Update
                $upd = $pdo->prepare("UPDATE subscriptions SET plan_id = ?, plan_name = ?, plan_price = ?, billing_cycle = ?, status = 'active', start_date = NOW(), expiry_date = DATE_ADD(NOW(), INTERVAL 1 " . strtoupper(str_replace('ly', '', $plan['billing_interval'])) . ") WHERE id = ?");
                $upd->execute([$plan['id'], $plan['name'], $plan['price'], $plan['billing_interval'], $existing_sub['id']]);
            } else {
                // Insert
                $ins = $pdo->prepare("INSERT INTO subscriptions (user_id, website_id, plan_id, plan_name, plan_price, billing_cycle, status, start_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 " . strtoupper(str_replace('ly', '', $plan['billing_interval'])) . "))");
                $ins->execute([$user_id, $website_id, $plan['id'], $plan['name'], $plan['price'], $plan['billing_interval']]);
            }

            // Update Website status
            $upd_web = $pdo->prepare("UPDATE websites SET subscription_status = 'paid' WHERE id = ?");
            $upd_web->execute([$website_id]);

            set_flash_message('success', 'Subscription activated successfully!');
        }
        redirect('/user/billing.php');
    }
}

// Fetch Plans
$plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Current Subscription
$sub_stmt = $pdo->prepare("SELECT s.*, p.storage_limit, p.custom_domain_allowed FROM subscriptions s LEFT JOIN plans p ON s.plan_id = p.id WHERE s.website_id = ?");
$sub_stmt->execute([$website_id]);
$subscription = $sub_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate Storage Usage
$store_stmt = $pdo->prepare("SELECT SUM(original_size) FROM media WHERE website_id = ? AND deleted_at IS NULL");
$store_stmt->execute([$website_id]);
$used_storage = (int)$store_stmt->fetchColumn();

// Setup plan limits if active, otherwise free tier defaults
$limit_storage = 52428800; // 50MB default
if ($subscription && $subscription['status'] === 'active') {
    $limit_storage = $subscription['storage_limit'] ?: $limit_storage;
}

$usage_pct = min(100, round(($used_storage / $limit_storage) * 100));
$usage_mb = round($used_storage / (1024 * 1024), 2);
$limit_mb = round($limit_storage / (1024 * 1024), 0);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Billing & Subscription</h2>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold mb-0">Current Plan</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($subscription && $subscription['status'] === 'active'): ?>
                    <div class="d-flex align-items-center mb-4">
                        <i class="bi bi-patch-check-fill text-success display-4 me-3"></i>
                        <div>
                            <h3 class="fw-bold mb-1"><?= escape($subscription['plan_name']) ?> Plan</h3>
                            <p class="text-muted mb-0">Active until <?= date('M j, Y', strtotime($subscription['expiry_date'])) ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center mb-4">
                        <i class="bi bi-star-fill text-warning display-4 me-3"></i>
                        <div>
                            <h3 class="fw-bold mb-1">Free Plan</h3>
                            <p class="text-muted mb-0">Basic features. Upgrade to access premium capabilities.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <h6 class="fw-bold mb-2 mt-4">Storage Usage</h6>
                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar bg-<?= $usage_pct > 85 ? 'danger' : 'primary' ?>" role="progressbar" style="width: <?= $usage_pct ?>%;"></div>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span><?= $usage_mb ?> MB used</span>
                    <span><?= $limit_mb ?> MB limit</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100 bg-light">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="card-title fw-bold mb-0">Payment History</h5>
            </div>
            <div class="card-body p-0">
                <?php
                $payments = $pdo->prepare("SELECT * FROM payments WHERE website_id = ? ORDER BY created_at DESC LIMIT 5");
                $payments->execute([$website_id]);
                $pay_records = $payments->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (empty($pay_records)): ?>
                    <div class="p-4 text-center text-muted">No payments found.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach($pay_records as $p): ?>
                        <li class="list-group-item bg-transparent px-4 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">₹<?= number_format($p['amount'], 2) ?></div>
                                    <div class="small text-muted text-uppercase"><?= escape($p['payment_type']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1"><?= ucfirst($p['status']) ?></span>
                                    <div class="small text-muted mt-1"><?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h4 class="mb-4">Available Plans</h4>
<div class="row g-4">
    <?php foreach ($plans as $plan): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 <?= ($subscription && $subscription['plan_id'] == $plan['id']) ? 'border border-primary border-2' : '' ?>">
            <div class="card-body p-4 text-center">
                <?php if ($subscription && $subscription['plan_id'] == $plan['id']): ?>
                    <span class="badge bg-primary mb-3">Current Plan</span>
                <?php endif; ?>
                <h4 class="fw-bold"><?= escape($plan['name']) ?></h4>
                <div class="display-5 fw-bold my-3 text-primary">₹<?= number_format($plan['price'], 0) ?></div>
                <div class="text-muted small mb-4 text-uppercase fw-bold">/ <?= escape($plan['billing_interval']) ?></div>

                <ul class="list-unstyled text-start mb-4">
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> <?= round($plan['storage_limit'] / (1024*1024)) ?> MB Media Storage</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Unlimited Pages</li>
                    <?php if ($plan['custom_domain_allowed']): ?>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Custom Domain Connection</li>
                    <?php else: ?>
                        <li class="mb-2 text-muted"><i class="bi bi-x-circle me-2"></i> Custom Domain Connection</li>
                    <?php endif; ?>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Standard Support</li>
                </ul>
            </div>
            <div class="card-footer bg-white border-0 p-4 pt-0">
                <?php if ($subscription && $subscription['plan_id'] == $plan['id']): ?>
                    <button class="btn btn-outline-secondary w-100" disabled>Active</button>
                <?php else: ?>
                    <form method="POST" action="">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="subscribe">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        <button type="submit" class="btn <?= $plan['price'] > 0 ? 'btn-primary' : 'btn-outline-primary' ?> w-100">
                            <?= $plan['price'] > 0 ? 'Select Plan' : 'Downgrade' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
