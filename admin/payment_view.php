<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, u.name as user_name, u.email as user_email, w.website_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN websites w ON p.website_id = w.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    set_flash_message('error', 'Payment transaction not found.');
    redirect('/admin/payments.php');
}

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/payments.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h3 mb-0 text-gray-800">Transaction Details</h2>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4 pb-3 border-bottom">
                    <div class="text-muted small text-uppercase fw-bold mb-2">Amount</div>
                    <h1 class="display-5 fw-bold mb-3"><?= escape($payment['currency']) ?> <?= escape($payment['amount']) ?></h1>

                    <?php if ($payment['status'] === 'completed'): ?>
                        <span class="badge bg-success px-3 py-2">Completed</span>
                    <?php elseif ($payment['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark px-3 py-2">Pending</span>
                    <?php elseif ($payment['status'] === 'refunded'): ?>
                        <span class="badge bg-info text-dark px-3 py-2">Refunded</span>
                    <?php else: ?>
                        <span class="badge bg-danger px-3 py-2">Failed</span>
                    <?php endif; ?>
                </div>

                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Transaction ID</span>
                        <code class="text-dark bg-light px-2 py-1 rounded fw-bold"><?= escape($payment['transaction_id']) ?></code>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Date & Time</span>
                        <span class="fw-medium"><?= date('F j, Y, g:i A', strtotime($payment['payment_date'])) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Customer</span>
                        <a href="/admin/user_view.php?id=<?= $payment['user_id'] ?>" class="text-decoration-none fw-medium"><?= escape($payment['user_name']) ?></a>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Website</span>
                        <a href="/admin/website_view.php?id=<?= $payment['website_id'] ?>" class="text-decoration-none fw-medium"><?= escape($payment['website_name']) ?></a>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Payment Type</span>
                        <span class="fw-medium text-capitalize"><?= escape($payment['payment_type']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Payment Gateway</span>
                        <span class="fw-medium"><?= escape($payment['gateway']) ?></span>
                    </li>
                    <?php if ($payment['subscription_id']): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3 border-bottom-0">
                        <span class="text-muted">Related Subscription</span>
                        <a href="/admin/subscription_view.php?id=<?= $payment['subscription_id'] ?>" class="btn btn-sm btn-outline-primary">View Subscription</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
