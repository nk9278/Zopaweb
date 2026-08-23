<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

// Fetch counts for dashboard
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND deleted_at IS NULL")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active' AND deleted_at IS NULL")->fetchColumn(),
    'total_websites' => $pdo->query("SELECT COUNT(*) FROM websites WHERE deleted_at IS NULL")->fetchColumn(),
    'active_websites' => $pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'active' AND deleted_at IS NULL")->fetchColumn(),
    'free_websites' => $pdo->query("SELECT COUNT(*) FROM websites WHERE subscription_status = 'free' AND deleted_at IS NULL")->fetchColumn(),
    'paid_websites' => $pdo->query("SELECT COUNT(*) FROM websites WHERE subscription_status = 'paid' AND deleted_at IS NULL")->fetchColumn(),
    'total_templates' => $pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn(),
    'active_templates' => $pdo->query("SELECT COUNT(*) FROM templates WHERE status = 'active'")->fetchColumn(),
    'active_subscriptions' => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn(),
    'pending_domains' => $pdo->query("SELECT COUNT(*) FROM domains WHERE status = 'pending'")->fetchColumn(),
    'total_enquiries' => $pdo->query("SELECT COUNT(*) FROM enquiries")->fetchColumn(),
    'pending_commissions' => $pdo->query("SELECT COUNT(*) FROM commissions WHERE status = 'pending'")->fetchColumn(),
];

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<h2 class="mb-4">Dashboard Overview</h2>

<div class="row g-4">
    <!-- Users Stats -->
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100 border-0">
            <div class="card-body">
                <h6 class="card-title text-uppercase mb-2">Total Users</h6>
                <h2 class="display-6 mb-0"><?= number_format($stats['total_users']) ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-white-50">
                <?= number_format($stats['active_users']) ?> Active
            </div>
        </div>
    </div>

    <!-- Websites Stats -->
    <div class="col-md-3">
        <div class="card bg-success text-white h-100 border-0">
            <div class="card-body">
                <h6 class="card-title text-uppercase mb-2">Total Websites</h6>
                <h2 class="display-6 mb-0"><?= number_format($stats['total_websites']) ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-white-50">
                <?= number_format($stats['active_websites']) ?> Active
            </div>
        </div>
    </div>

    <!-- Subscriptions Stats -->
    <div class="col-md-3">
        <div class="card bg-info text-white h-100 border-0">
            <div class="card-body">
                <h6 class="card-title text-uppercase mb-2">Websites by Plan</h6>
                <h2 class="display-6 mb-0"><?= number_format($stats['paid_websites']) ?> <small class="fs-6">Paid</small></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-white-50">
                <?= number_format($stats['free_websites']) ?> Free
            </div>
        </div>
    </div>

    <!-- Enquiries Stats -->
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100 border-0">
            <div class="card-body">
                <h6 class="card-title text-uppercase mb-2">Total Enquiries</h6>
                <h2 class="display-6 mb-0"><?= number_format($stats['total_enquiries']) ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-dark-50">
                Platform wide
            </div>
        </div>
    </div>

    <!-- Templates -->
    <div class="col-md-3">
        <div class="card bg-secondary text-white h-100 border-0">
            <div class="card-body">
                <h6 class="card-title text-uppercase mb-2">Total Templates</h6>
                <h2 class="display-6 mb-0"><?= number_format($stats['total_templates']) ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-white-50">
                <?= number_format($stats['active_templates']) ?> Active
            </div>
        </div>
    </div>

    <!-- Other -->
    <div class="col-md-3">
        <div class="card bg-light text-dark h-100">
            <div class="card-body">
                <h6 class="card-title text-uppercase mb-2">Pending Items</h6>
                <p class="mb-1 fw-bold"><?= number_format($stats['pending_domains']) ?> Domains</p>
                <p class="mb-0 fw-bold"><?= number_format($stats['pending_commissions']) ?> Commissions</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
