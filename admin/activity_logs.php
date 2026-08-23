<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = in_array($limit, [50, 100, 200]) ? $limit : 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (u.name LIKE ? OR a.name LIKE ? OR l.ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_filter) {
    $where_sql .= " AND l.action = ?";
    $params[] = $action_filter;
}

if ($type_filter) {
    $where_sql .= " AND l.entity_type = ?";
    $params[] = $type_filter;
}

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN users a ON l.admin_id = a.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT l.*,
           u.name as user_name, u.email as user_email,
           a.name as admin_name
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN users a ON l.admin_id = a.id
    $where_sql
    ORDER BY l.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions and entity types for dropdowns
$unique_actions = $pdo->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$unique_types = $pdo->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Activity Logs</h2>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search user, admin, or IP" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($unique_actions as $act): ?>
                        <option value="<?= escape($act) ?>" <?= $action_filter === $act ? 'selected' : '' ?>><?= escape($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="">All Entities</option>
                    <?php foreach ($unique_types as $type): ?>
                        <option value="<?= escape($type) ?>" <?= $type_filter === $type ? 'selected' : '' ?>><?= escape(ucfirst($type)) ?></option>
                    <?php endforeach; ?>
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
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light text-muted small">
                    <tr>
                        <th>Date & Time</th>
                        <th>User / Admin</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium text-nowrap"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i:s A', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if ($log['admin_name']): ?>
                                        <span class="badge bg-danger mb-1">Admin</span><br>
                                        <span class="fw-medium text-dark"><?= escape($log['admin_name']) ?></span>
                                    <?php elseif ($log['user_name']): ?>
                                        <span class="fw-medium text-dark"><?= escape($log['user_name']) ?></span><br>
                                        <span class="small text-muted"><?= escape($log['user_email']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">System / Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= escape($log['action']) ?></span>
                                </td>
                                <td>
                                    <?php if ($log['entity_type']): ?>
                                        <div class="small text-muted"><?= escape(ucfirst($log['entity_type'])) ?></div>
                                        <code class="text-primary bg-light px-2 py-1 rounded d-inline-block mt-1">ID: <?= escape($log['entity_id']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="small text-muted font-monospace"><?= escape($log['ip_address'] ?? 'N/A') ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-journal-text empty-state-icon"></i>
                                    <p class="text-muted mb-0">No activity logs found matching your criteria.</p>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($action_filter) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $limit ?>">Previous</a>
                    </li>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($action_filter) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $limit ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
