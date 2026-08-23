<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$domain_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT d.*, u.name as user_name, u.email as user_email, w.website_name, w.website_slug
    FROM domains d
    JOIN users u ON d.user_id = u.id
    JOIN websites w ON d.website_id = w.id
    WHERE d.id = ?
");
$stmt->execute([$domain_id]);
$domain = $stmt->fetch();

if (!$domain) {
    set_flash_message('error', 'Domain not found.');
    redirect('/admin/domains.php');
}

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/domains.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h3 mb-0 text-gray-800">Domain Details</h2>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4 pb-3 border-bottom">
                    <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-hdd-network fs-1 text-primary"></i>
                    </div>
                    <h3 class="fw-bold mb-2 text-primary"><?= escape($domain['domain_name']) ?></h3>

                    <?php if ($domain['status'] === 'active'): ?>
                        <span class="badge bg-success px-3 py-2">Active</span>
                    <?php elseif ($domain['status'] === 'expired'): ?>
                        <span class="badge bg-danger px-3 py-2">Expired</span>
                    <?php elseif ($domain['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark px-3 py-2">Pending Connection</span>
                    <?php else: ?>
                        <span class="badge bg-secondary px-3 py-2"><?= escape(ucfirst($domain['status'])) ?></span>
                    <?php endif; ?>
                </div>

                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Domain Type</span>
                        <span class="badge bg-light text-dark border"><?= escape(ucfirst($domain['domain_type'])) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Linked Website</span>
                        <div class="text-end">
                            <a href="/admin/website_view.php?id=<?= $domain['website_id'] ?>" class="text-decoration-none fw-medium d-block"><?= escape($domain['website_name']) ?></a>
                            <small class="text-muted">web.<?= escape($domain['website_slug']) ?>.zopaweb.com</small>
                        </div>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between py-3">
                        <span class="text-muted">Owner</span>
                        <div class="text-end">
                            <a href="/admin/user_view.php?id=<?= $domain['user_id'] ?>" class="text-decoration-none fw-medium d-block"><?= escape($domain['user_name']) ?></a>
                            <small class="text-muted"><?= escape($domain['user_email']) ?></small>
                        </div>
                    </li>

                    <?php if ($domain['domain_type'] === 'custom'): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between py-3 bg-light rounded px-3 my-2 border">
                            <span class="text-muted">Registrar</span>
                            <span class="fw-medium"><?= escape($domain['registrar'] ?? 'Unknown') ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between py-3">
                            <span class="text-muted">Connected At</span>
                            <span class="fw-medium"><?= $domain['connected_at'] ? date('F j, Y', strtotime($domain['connected_at'])) : 'Pending' ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between py-3 border-bottom-0">
                            <span class="text-muted">Expires At</span>
                            <span class="fw-medium"><?= $domain['expires_at'] ? date('F j, Y', strtotime($domain['expires_at'])) : 'N/A' ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
