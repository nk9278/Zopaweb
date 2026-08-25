<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

// Fetch counts for dashboard
$stats = [
    'total_users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND deleted_at IS NULL")->fetchColumn(),
    'active_users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active' AND deleted_at IS NULL")->fetchColumn(),
    'total_websites' => (int)$pdo->query("SELECT COUNT(*) FROM websites WHERE deleted_at IS NULL")->fetchColumn(),
    'active_websites' => (int)$pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'active' AND deleted_at IS NULL")->fetchColumn(),
    'free_websites' => (int)$pdo->query("SELECT COUNT(*) FROM websites WHERE subscription_status = 'free' AND deleted_at IS NULL")->fetchColumn(),
    'paid_websites' => (int)$pdo->query("SELECT COUNT(*) FROM websites WHERE subscription_status = 'paid' AND deleted_at IS NULL")->fetchColumn(),
    'active_subscriptions' => (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn(),
    'total_templates' => (int)$pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn(),
    'active_templates' => (int)$pdo->query("SELECT COUNT(*) FROM templates WHERE status = 'active'")->fetchColumn(),
    'pending_domains' => (int)$pdo->query("SELECT COUNT(*) FROM domains WHERE status = 'pending'")->fetchColumn(),
    'total_enquiries' => (int)$pdo->query("SELECT COUNT(*) FROM enquiries")->fetchColumn(),
    'pending_commissions' => (int)$pdo->query("SELECT COUNT(*) FROM commissions WHERE status = 'pending'")->fetchColumn(),
];

// Fetch recent users
$recent_users = $pdo->query("
    SELECT id, name, email, role, status, created_at, last_login_at
    FROM users
    WHERE deleted_at IS NULL
    ORDER BY id DESC LIMIT 5
")->fetchAll();

// Fetch recent websites
$recent_websites = $pdo->query("
    SELECT w.id, w.website_name, w.status, w.subscription_status, w.created_at, u.name as owner_name, t.name as template_name
    FROM websites w
    JOIN users u ON w.user_id = u.id
    LEFT JOIN templates t ON w.template_id = t.id
    WHERE w.deleted_at IS NULL
    ORDER BY w.id DESC LIMIT 5
")->fetchAll();

// Fetch recent enquiries
$recent_enquiries = $pdo->query("
    SELECT e.id, e.name, e.service, e.status, e.created_at, w.website_name
    FROM enquiries e
    JOIN websites w ON e.website_id = w.id
    ORDER BY e.id DESC LIMIT 5
")->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Dashboard Overview</h2>
</div>

<!-- Primary KPIs -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_users']) ?></div>
                        <div class="small text-muted mt-1"><?= number_format($stats['active_users']) ?> Active</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fs-2 text-gray-300 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #198754 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Websites</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_websites']) ?></div>
                        <div class="small text-muted mt-1"><?= number_format($stats['active_websites']) ?> Active</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-globe fs-2 text-gray-300 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #0dcaf0 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Subscriptions</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['active_subscriptions']) ?></div>
                        <div class="small text-muted mt-1"><?= number_format($stats['paid_websites']) ?> Paid / <?= number_format($stats['free_websites']) ?> Free</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-card-checklist fs-2 text-gray-300 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #ffc107 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Enquiries</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_enquiries']) ?></div>
                        <div class="small text-muted mt-1">Platform wide</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-envelope fs-2 text-gray-300 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Stats Row -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase mb-1">Templates</h6>
                    <h4 class="mb-0"><?= number_format($stats['total_templates']) ?> <small class="fs-6 text-muted fw-normal">(<?= number_format($stats['active_templates']) ?> Active)</small></h4>
                </div>
                <i class="bi bi-palette fs-1 text-primary opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase mb-1">Pending Actions</h6>
                    <h4 class="mb-0 text-danger"><?= number_format($stats['pending_domains']) ?> <small class="fs-6 text-muted fw-normal">Domains</small> &bull; <?= number_format($stats['pending_commissions']) ?> <small class="fs-6 text-muted fw-normal">Commissions</small></h4>
                </div>
                <i class="bi bi-exclamation-circle fs-1 text-danger opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Tables -->
<div class="row g-4 mb-4">
    <!-- Recent Users -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent Users</h6>
                <a href="/admin/users.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recent_users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= escape($u['name']) ?></div>
                                            <div class="small text-muted"><?= escape($u['email']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($u['role'])) ?></span></td>
                                        <td><span class="small"><?= date('M d, Y', strtotime($u['created_at'])) ?></span></td>
                                        <td class="text-end">
                                            <a href="/admin/user_view.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">No users found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Websites -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent Websites</h6>
                <a href="/admin/websites.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recent_websites) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>Website</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_websites as $w): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= escape($w['website_name']) ?></div>
                                            <div class="small text-muted">by <?= escape($w['owner_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $w['subscription_status'] === 'paid' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= escape(ucfirst($w['subscription_status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($w['status'] === 'active'): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Active"></i>
                                            <?php else: ?>
                                                <i class="bi bi-exclamation-circle-fill text-warning" title="<?= escape(ucfirst($w['status'])) ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/admin/website_view.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">No websites found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enquiries Row -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent Enquiries</h6>
                <a href="/admin/leads.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recent_enquiries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>Name</th>
                                    <th>Website</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_enquiries as $e): ?>
                                    <tr>
                                        <td class="fw-medium"><?= escape($e['name']) ?></td>
                                        <td><?= escape($e['website_name']) ?></td>
                                        <td><?= escape($e['service']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($e['status'])) ?></span></td>
                                        <td><span class="small"><?= date('M d, Y', strtotime($e['created_at'])) ?></span></td>
                                        <td class="text-end">
                                            <a href="/admin/lead_view.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-5">
                        <i class="bi bi-envelope empty-state-icon"></i>
                        <p class="text-muted mb-0">No recent enquiries across the platform.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
