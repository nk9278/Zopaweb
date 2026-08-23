<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

// Pagination setup
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = in_array($limit, [20, 50, 100]) ? $limit : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (u.name LIKE ? OR w.website_name LIKE ? OR s.plan_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_sql .= " AND s.status = ?";
    $params[] = $status_filter;
}

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN websites w ON s.website_id = w.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT s.*, u.name as user_name, u.email as user_email, w.website_name
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN websites w ON s.website_id = w.id
    $where_sql
    ORDER BY s.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Subscriptions Management</h2>
</div>

<!-- Filters -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search user, website, or plan name" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
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
                        <th>Customer</th>
                        <th>Website</th>
                        <th>Plan & Amount</th>
                        <th>Cycle</th>
                        <th>Status</th>
                        <th>Timeline</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($subscriptions) > 0): ?>
                        <?php foreach ($subscriptions as $sub): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= escape($sub['user_name']) ?></div>
                                    <div class="small text-muted"><?= escape($sub['user_email']) ?></div>
                                </td>
                                <td><?= escape($sub['website_name']) ?></td>
                                <td>
                                    <div class="fw-medium"><?= escape($sub['plan_name']) ?></div>
                                    <div class="small text-muted"><?= escape($sub['plan_price']) ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($sub['billing_cycle'])) ?></span></td>
                                <td>
                                    <?php if ($sub['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($sub['status'] === 'cancelled'): ?>
                                        <span class="badge bg-warning text-dark">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        <div>Starts: <?= date('M d, Y', strtotime($sub['start_date'])) ?></div>
                                        <div>Expires: <?= date('M d, Y', strtotime($sub['expiry_date'])) ?></div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/subscription_view.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2 mb-1"><i class="bi bi-eye"></i> View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-card-checklist empty-state-icon"></i>
                                    <p class="text-muted mb-0">No subscriptions found matching your criteria.</p>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                    </li>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
