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

$where_sql = "WHERE role = 'creator' AND deleted_at IS NULL";
$params = [];

if ($search) {
    $where_sql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at,
           c.code, c.commission_amount
    FROM users u
    LEFT JOIN creator_codes c ON u.id = c.user_id
    $where_sql
    ORDER BY u.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$creators = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Creators Management</h2>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?= escape($search) ?>">
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
                        <th>Creator</th>
                        <th>Phone</th>
                        <th>Coupon Code</th>
                        <th>Commission Rate</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($creators) > 0): ?>
                        <?php foreach ($creators as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= escape($c['name']) ?></div>
                                    <div class="small text-muted"><?= escape($c['email']) ?></div>
                                </td>
                                <td><?= escape($c['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($c['code']): ?>
                                        <code class="text-primary bg-light px-2 py-1 rounded"><?= escape($c['code']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted small">None assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $c['commission_amount'] ? 'INR ' . escape($c['commission_amount']) : 'Default' ?></td>
                                <td>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= escape(ucfirst($c['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="small text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></div></td>
                                <td class="text-end">
                                    <a href="/admin/creator_view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2 mb-1"><i class="bi bi-eye"></i> View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-stars empty-state-icon"></i>
                                    <p class="text-muted mb-0">No creators found matching your criteria.</p>
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
