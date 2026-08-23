<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at,
           c.code, c.commission_amount
    FROM users u
    LEFT JOIN creator_codes c ON u.id = c.user_id
    WHERE u.id = ? AND u.role = 'creator' AND u.deleted_at IS NULL
");
$stmt->execute([$id]);
$creator = $stmt->fetch();

if (!$creator) {
    set_flash_message('error', 'Creator not found.');
    redirect('/admin/creators.php');
}

// Stats
$ref_stmt = $pdo->prepare("SELECT COUNT(*) FROM commissions WHERE creator_id = ?");
$ref_stmt->execute([$id]);
$total_referrals = $ref_stmt->fetchColumn();

$pending_stmt = $pdo->prepare("SELECT SUM(amount) FROM commissions WHERE creator_id = ? AND status = 'pending'");
$pending_stmt->execute([$id]);
$pending_amt = $pending_stmt->fetchColumn() ?: 0.00;

$paid_stmt = $pdo->prepare("SELECT SUM(amount) FROM commissions WHERE creator_id = ? AND status = 'paid'");
$paid_stmt->execute([$id]);
$paid_amt = $paid_stmt->fetchColumn() ?: 0.00;

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/creators.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h3 mb-0 text-gray-800">Creator Details</h2>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold display-6" style="width: 80px; height: 80px;">
                        <?= substr(escape($creator['name']), 0, 1) ?>
                    </div>
                    <h4 class="mb-1"><?= escape($creator['name']) ?></h4>
                    <p class="text-muted mb-2"><?= escape($creator['email']) ?></p>
                    <span class="badge <?= $creator['status'] === 'active' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= escape(ucfirst($creator['status'])) ?></span>
                </div>

                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Program Details</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Coupon Code</span>
                        <?php if ($creator['code']): ?>
                            <code class="text-primary bg-light px-2 py-1 rounded fw-medium"><?= escape($creator['code']) ?></code>
                        <?php else: ?>
                            <span class="text-muted small">Not set</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Commission Rate</span>
                        <span class="fw-medium">INR <?= escape($creator['commission_amount'] ?? '0.00') ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between border-bottom-0">
                        <span class="text-muted">Joined</span>
                        <span class="fw-medium"><?= date('M d, Y', strtotime($creator['created_at'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 py-2 border-primary" style="border-left: 4px solid !important;">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Referrals</div>
                        <div class="h3 mb-0 fw-bold text-gray-800"><?= $total_referrals ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 py-2 border-warning" style="border-left: 4px solid !important;">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Payouts</div>
                        <div class="h3 mb-0 fw-bold text-gray-800">INR <?= number_format((float)$pending_amt, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 py-2 border-success" style="border-left: 4px solid !important;">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Paid</div>
                        <div class="h3 mb-0 fw-bold text-gray-800">INR <?= number_format((float)$paid_amt, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Recent Commissions</h6>
            </div>
            <div class="card-body">
                <div class="empty-state py-4 border-0 text-center">
                    <i class="bi bi-wallet2 empty-state-icon"></i>
                    <p class="text-muted mb-0">Use the <a href="/admin/commissions.php" class="text-primary text-decoration-none">Commissions ledger</a> to view detailed transaction histories.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
