<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = in_array($limit, [20, 50, 100]) ? $limit : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (d.domain_name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter) {
    $where_sql .= " AND d.domain_type = ?";
    $params[] = $type_filter;
}

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM domains d
    JOIN users u ON d.user_id = u.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT d.*, u.name as user_name, w.website_name
    FROM domains d
    JOIN users u ON d.user_id = u.id
    JOIN websites w ON d.website_id = w.id
    $where_sql
    ORDER BY d.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$domains = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Domains Management</h2>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by domain or owner" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="subdomain" <?= $type_filter === 'subdomain' ? 'selected' : '' ?>>Subdomain</option>
                    <option value="custom" <?= $type_filter === 'custom' ? 'selected' : '' ?>>Custom</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small">
                    <tr>
                        <th>Domain</th>
                        <th>Type & Registrar</th>
                        <th>Customer / Website</th>
                        <th>Status</th>
                        <th>Connected At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($domains) > 0): ?>
                        <?php foreach ($domains as $d): ?>
                            <tr>
                                <td class="fw-medium text-primary"><?= escape($d['domain_name']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border mb-1"><?= escape(ucfirst($d['domain_type'])) ?></span><br>
                                    <span class="small text-muted"><?= escape($d['registrar'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= escape($d['user_name']) ?></div>
                                    <div class="small text-muted"><?= escape($d['website_name']) ?></div>
                                </td>
                                <td>
                                    <?php if ($d['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($d['status'] === 'expired'): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php elseif ($d['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= escape(ucfirst($d['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="small text-muted"><?= $d['connected_at'] ? date('M d, Y', strtotime($d['connected_at'])) : '-' ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-hdd-network empty-state-icon"></i>
                                    <p class="text-muted mb-0">No domains found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top p-3 d-flex align-items-center justify-content-between">
            <span class="small text-muted">Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> entries</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>">Previous</a>
                    </li>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
