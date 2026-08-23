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
$status_filter = $_GET['status'] ?? '';

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (cr.name LIKE ? OR cu.name LIKE ? OR c.coupon_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_sql .= " AND c.status = ?";
    $params[] = $status_filter;
}

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM commissions c
    JOIN users cr ON c.creator_id = cr.id
    JOIN users cu ON c.user_id = cu.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT c.*,
           cr.name as creator_name,
           cu.name as customer_name,
           w.website_name
    FROM commissions c
    JOIN users cr ON c.creator_id = cr.id
    JOIN users cu ON c.user_id = cu.id
    JOIN websites w ON c.website_id = w.id
    $where_sql
    ORDER BY c.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$commissions = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Commissions Ledger</h2>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search creator, customer, or coupon" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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
                        <th>Creator</th>
                        <th>Customer / Website</th>
                        <th>Coupon Code</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Dates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($commissions) > 0): ?>
                        <?php foreach ($commissions as $c): ?>
                            <tr>
                                <td class="fw-medium text-primary"><a href="/admin/creator_view.php?id=<?= $c['creator_id'] ?>" class="text-decoration-none"><?= escape($c['creator_name']) ?></a></td>
                                <td>
                                    <div class="fw-medium"><?= escape($c['customer_name']) ?></div>
                                    <div class="small text-muted"><?= escape($c['website_name']) ?></div>
                                </td>
                                <td><code class="text-primary bg-light px-2 py-1 rounded"><?= escape($c['coupon_code']) ?></code></td>
                                <td class="fw-medium">INR <?= escape($c['amount']) ?></td>
                                <td>
                                    <?php if ($c['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($c['status'] === 'approved'): ?>
                                        <span class="badge bg-info text-dark">Approved</span>
                                    <?php elseif ($c['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= escape(ucfirst($c['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted">Created: <?= date('M d, Y', strtotime($c['created_at'])) ?></div>
                                    <?php if ($c['approved_at']): ?>
                                        <div class="small text-muted">Approved: <?= date('M d, Y', strtotime($c['approved_at'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($c['paid_at']): ?>
                                        <div class="small text-muted">Paid: <?= date('M d, Y', strtotime($c['paid_at'])) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-wallet2 empty-state-icon"></i>
                                    <p class="text-muted mb-0">No commissions found matching your criteria.</p>
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
