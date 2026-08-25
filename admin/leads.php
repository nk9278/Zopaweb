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
    $where_sql .= " AND (e.name LIKE ? OR w.website_name LIKE ? OR e.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_sql .= " AND e.status = ?";
    $params[] = $status_filter;
}

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM leads e
    JOIN websites w ON e.website_id = w.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT e.*, w.website_name
    FROM leads e
    JOIN websites w ON e.website_id = w.id
    $where_sql
    ORDER BY e.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Enquiries Management</h2>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by name, website, or service" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="contacted" <?= $status_filter === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                    <option value="booked" <?= $status_filter === 'booked' ? 'selected' : '' ?>>Booked</option>
                    <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                    <option value="spam" <?= $status_filter === 'spam' ? 'selected' : '' ?>>Spam</option>
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
                        <th>Enquirer</th>
                        <th>Website</th>
                        <th>Service / Event</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($enquiries) > 0): ?>
                        <?php foreach ($enquiries as $e): ?>
                            <tr>
                                <td class="fw-medium"><?= escape($e['name']) ?></td>
                                <td><?= escape($e['website_name']) ?></td>
                                <td>
                                    <div class="fw-medium"><?= escape($e['service'] ?? 'Not specified') ?></div>
                                    <div class="small text-muted"><?= $e['event_date'] ? date('M d, Y', strtotime($e['event_date'])) : 'No date' ?></div>
                                </td>
                                <td>
                                    <?php if ($e['whatsapp']): ?>
                                        <div class="small"><i class="bi bi-whatsapp text-success me-1"></i><?= escape($e['whatsapp']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($e['phone'] && $e['phone'] !== $e['whatsapp']): ?>
                                        <div class="small"><i class="bi bi-telephone text-muted me-1"></i><?= escape($e['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($e['status'] === 'new'): ?>
                                        <span class="badge bg-primary">New</span>
                                    <?php elseif ($e['status'] === 'contacted'): ?>
                                        <span class="badge bg-info text-dark">Contacted</span>
                                    <?php elseif ($e['status'] === 'booked'): ?>
                                        <span class="badge bg-success">Booked</span>
                                    <?php elseif ($e['status'] === 'spam'): ?>
                                        <span class="badge bg-danger">Spam</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="small text-muted"><?= date('M d, Y', strtotime($e['created_at'])) ?></div></td>
                                <td class="text-end">
                                    <a href="/admin/enquiry_view.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2 mb-1"><i class="bi bi-eye"></i> View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-envelope empty-state-icon"></i>
                                    <p class="text-muted mb-0">No enquiries found matching your criteria.</p>
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
