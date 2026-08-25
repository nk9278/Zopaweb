<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$website_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT w.*, u.name as owner_name, u.email as owner_email, t.name as template_name
    FROM websites w
    JOIN users u ON w.user_id = u.id
    LEFT JOIN templates t ON w.template_id = t.id
    WHERE w.id = ?
");
$stmt->execute([$website_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Website not found.');
    redirect('/admin/websites.php');
}

// Fetch subscriptions
$s_stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE website_id = ? ORDER BY id DESC");
$s_stmt->execute([$website_id]);
$subscriptions = $s_stmt->fetchAll();

// Fetch domains
$d_stmt = $pdo->prepare("SELECT * FROM domains WHERE website_id = ? ORDER BY id DESC");
$d_stmt->execute([$website_id]);
$domains = $d_stmt->fetchAll();

// Fetch enquiries
$e_stmt = $pdo->prepare("SELECT * FROM enquiries WHERE website_id = ? ORDER BY id DESC LIMIT 10");
$e_stmt->execute([$website_id]);
$enquiries = $e_stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <a href="/admin/websites.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
        <h2 class="h3 mb-0 text-gray-800">Website Details</h2>
    </div>

    <div>
        <form method="POST" action="/admin/websites.php" class="d-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="website_id" value="<?= $website['id'] ?>">

            <?php if ($website['status'] !== 'active' && !$website['deleted_at']): ?>
                <button type="submit" name="action" value="activate" class="btn btn-success shadow-sm"><i class="bi bi-play-circle me-1"></i> Activate</button>
            <?php endif; ?>
            <?php if ($website['status'] !== 'suspended' && !$website['deleted_at']): ?>
                <button type="submit" name="action" value="suspend" class="btn btn-warning shadow-sm" onclick="return confirm('Suspending this website will take it offline. Are you sure?');"><i class="bi bi-pause-circle me-1"></i> Suspend</button>
            <?php endif; ?>
            <?php if ($website['status'] !== 'archived' && !$website['deleted_at']): ?>
                <button type="submit" name="action" value="archive" class="btn btn-secondary shadow-sm"><i class="bi bi-archive me-1"></i> Archive</button>
            <?php endif; ?>
            <?php if (!$website['deleted_at']): ?>
                <button type="submit" name="action" value="delete" class="btn btn-danger shadow-sm ms-1" onclick="return confirm('Soft delete website?');"><i class="bi bi-trash me-1"></i> Delete</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Main Info -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4 h-100">
            <div class="card-body p-4">
                <div class="mb-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light border rounded-circle mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-globe fs-1 text-primary"></i>
                    </div>
                    <h4 class="mb-1"><?= escape($website['website_name']) ?></h4>
                    <p class="text-muted mb-2">web.<?= escape($website['website_slug']) ?>.zopaweb.com</p>
                    <div class="mb-3">
                        <span class="badge <?= $website['subscription_status'] === 'paid' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                            <?= escape(ucfirst($website['subscription_status'])) ?> Plan
                        </span>
                    </div>
                    <div class="d-flex justify-content-center gap-2">
                        <?php if ($website['deleted_at']): ?>
                            <span class="badge bg-danger">Deleted</span>
                        <?php elseif ($website['status'] === 'active'): ?>
                            <span class="badge bg-success">Status: Active</span>
                        <?php elseif ($website['status'] === 'archived'): ?>
                            <span class="badge bg-secondary">Status: Archived</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Status: Suspended</span>
                        <?php endif; ?>

                        <span class="badge bg-light text-dark border">Publication: <?= escape(ucfirst($website['publication_status'])) ?></span>
                    </div>
                </div>

                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Details</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Owner</span>
                        <a href="/admin/user_view.php?id=<?= $website['user_id'] ?>" class="text-decoration-none fw-medium"><?= escape($website['owner_name']) ?></a>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Template</span>
                        <span class="fw-medium"><?= escape($website['template_name'] ?? 'None selected') ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Created On</span>
                        <span class="fw-medium"><?= date('M d, Y', strtotime($website['created_at'])) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between border-bottom-0">
                        <span class="text-muted">Last Updated</span>
                        <span class="fw-medium"><?= date('M d, Y', strtotime($website['updated_at'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Related Data -->
    <div class="col-xl-8 d-flex flex-column gap-4">
        <!-- Subscriptions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Subscription History</h6>
            </div>
            <div class="card-body">
                <?php if (count($subscriptions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th>Plan</th>
                                    <th>Price</th>
                                    <th>Cycle</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                    <tr>
                                        <td class="fw-medium"><?= escape($sub['plan_name']) ?></td>
                                        <td><?= escape($sub['plan_price']) ?></td>
                                        <td><?= escape(ucfirst($sub['billing_cycle'])) ?></td>
                                        <td><span class="badge <?= $sub['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= escape(ucfirst($sub['status'])) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($sub['expiry_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 small">No subscription records found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Domains -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Connected Domains</h6>
            </div>
            <div class="card-body">
                <?php if (count($domains) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th>Domain</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Connected At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domains as $dom): ?>
                                    <tr>
                                        <td class="fw-medium"><?= escape($dom['domain_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($dom['domain_type'])) ?></span></td>
                                        <td>
                                            <?php if ($dom['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><?= escape(ucfirst($dom['status'])) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $dom['connected_at'] ? date('M d, Y', strtotime($dom['connected_at'])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 small">No custom domains connected.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Enquiries -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Recent Enquiries</h6>
            </div>
            <div class="card-body">
                <?php if (count($enquiries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th>Name</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enquiries as $enq): ?>
                                    <tr>
                                        <td class="fw-medium"><?= escape($enq['name']) ?></td>
                                        <td><?= escape($enq['service'] ?? '-') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($enq['status'])) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($enq['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 small">No enquiries received yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
