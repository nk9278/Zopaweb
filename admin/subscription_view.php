<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT s.*, u.name as user_name, u.email as user_email, w.website_name
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN websites w ON s.website_id = w.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$subscription = $stmt->fetch();

if (!$subscription) {
    set_flash_message('error', 'Subscription not found.');
    redirect('/admin/subscriptions.php');
}

$p_stmt = $pdo->prepare("SELECT * FROM payments WHERE subscription_id = ? ORDER BY id DESC");
$p_stmt->execute([$id]);
$payments = $p_stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/subscriptions.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h3 mb-0 text-gray-800">Subscription Details</h2>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Summary</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Customer</span>
                        <a href="/admin/user_view.php?id=<?= $subscription['user_id'] ?>" class="text-decoration-none fw-medium"><?= escape($subscription['user_name']) ?></a>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Website</span>
                        <a href="/admin/website_view.php?id=<?= $subscription['website_id'] ?>" class="text-decoration-none fw-medium"><?= escape($subscription['website_name']) ?></a>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Plan</span>
                        <span class="fw-medium"><?= escape($subscription['plan_name']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Price</span>
                        <span class="fw-medium"><?= escape($subscription['plan_price']) ?> / <?= escape($subscription['billing_cycle']) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between border-bottom-0">
                        <span class="text-muted">Status</span>
                        <span class="fw-medium">
                            <?php if ($subscription['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($subscription['status'] === 'cancelled'): ?>
                                <span class="badge bg-warning text-dark">Cancelled</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Expired</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Start Date</span>
                        <span class="fw-medium"><?= date('M d, Y', strtotime($subscription['start_date'])) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between border-bottom-0">
                        <span class="text-muted">Expiry Date</span>
                        <span class="fw-medium"><?= date('M d, Y', strtotime($subscription['expiry_date'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Payment History</h6>
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><code><?= escape($p['transaction_id']) ?></code></td>
                                        <td><?= date('M d, Y h:i A', strtotime($p['payment_date'])) ?></td>
                                        <td><?= escape($p['currency']) ?> <?= escape($p['amount']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= escape($p['gateway']) ?></span></td>
                                        <td>
                                            <?php if ($p['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($p['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php elseif ($p['status'] === 'refunded'): ?>
                                                <span class="badge bg-info text-dark">Refunded</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4 border-0 text-center">
                        <i class="bi bi-receipt empty-state-icon"></i>
                        <p class="text-muted mb-0">No payment records found for this subscription.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
