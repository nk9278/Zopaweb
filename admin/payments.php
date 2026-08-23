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

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (p.transaction_id LIKE ? OR u.name LIKE ? OR w.website_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN websites w ON p.website_id = w.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT p.*, u.name as user_name, w.website_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN websites w ON p.website_id = w.id
    $where_sql
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Payments Ledger</h2>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Search by Transaction ID, User, or Website" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
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
                        <th>Transaction ID</th>
                        <th>Customer / Website</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Gateway</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><code><?= escape($p['transaction_id']) ?></code></td>
                                <td>
                                    <div class="fw-medium"><?= escape($p['user_name']) ?></div>
                                    <div class="small text-muted"><?= escape($p['website_name']) ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($p['payment_type'])) ?></span></td>
                                <td class="fw-medium"><?= escape($p['currency']) ?> <?= escape($p['amount']) ?></td>
                                <td><?= escape($p['gateway']) ?></td>
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
                                <td><div class="small text-muted"><?= date('M d, Y', strtotime($p['payment_date'])) ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-receipt empty-state-icon"></i>
                                    <p class="text-muted mb-0">No payment records found.</p>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
                    </li>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
