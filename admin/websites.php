<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['website_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/websites.php');
    }

    $website_id = (int)$_POST['website_id'];
    $action = $_POST['action'];

    if ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE websites SET status = 'active' WHERE id = ?");
        $stmt->execute([$website_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'activated_website', 'website', $website_id);
        set_flash_message('success', 'Website activated.');
    } elseif ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE websites SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$website_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'suspended_website', 'website', $website_id);
        set_flash_message('success', 'Website suspended.');
    } elseif ($action === 'archive') {
        $stmt = $pdo->prepare("UPDATE websites SET status = 'archived' WHERE id = ?");
        $stmt->execute([$website_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'archived_website', 'website', $website_id);
        set_flash_message('success', 'Website archived.');
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE websites SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$website_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'deleted_website', 'website', $website_id);
        set_flash_message('success', 'Website soft deleted.');
    }

    redirect('/admin/websites.php');
}

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
    $where_sql .= " AND (w.website_name LIKE ? OR w.website_slug LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    if ($status_filter === 'deleted') {
        $where_sql .= " AND w.deleted_at IS NOT NULL";
    } else {
        $where_sql .= " AND w.status = ? AND w.deleted_at IS NULL";
        $params[] = $status_filter;
    }
} else {
    $where_sql .= " AND w.deleted_at IS NULL";
}

// Count total
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM websites w
    JOIN users u ON w.user_id = u.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT w.id, w.website_name, w.website_slug, w.status, w.publication_status, w.subscription_status, w.created_at, w.deleted_at,
           u.name as owner_name, u.email as owner_email,
           t.name as template_name
    FROM websites w
    JOIN users u ON w.user_id = u.id
    LEFT JOIN templates t ON w.template_id = t.id
    $where_sql
    ORDER BY w.id DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$websites = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Websites Management</h2>
</div>

<!-- Filters -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search website name, slug, or owner" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Active/Suspended/Archived</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                    <option value="deleted" <?= $status_filter === 'deleted' ? 'selected' : '' ?>>Deleted</option>
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
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Website Name</th>
                        <th>Slug</th>
                        <th>Owner</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($websites) > 0): ?>
                        <?php foreach ($websites as $web): ?>
                            <tr>
                                <td class="fw-medium">
                                    <?= escape($web['website_name']) ?><br>
                                    <small class="text-muted fw-normal text-truncate d-inline-block" style="max-width: 180px;">web.<?= escape($web['website_slug']) ?>.zopaweb.com</small>
                                </td>
                                <td>
                                    <?= escape($web['owner_name']) ?><br>
                                    <small class="text-muted"><?= escape($web['owner_email']) ?></small>
                                </td>
                                <td><span class="text-muted small"><?= escape($web['template_name'] ?? 'Not selected') ?></span></td>
                                <td>
                                    <span class="badge <?= $web['subscription_status'] === 'paid' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                        <?= escape(ucfirst($web['subscription_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($web['deleted_at']): ?>
                                        <span class="badge bg-danger">Deleted</span>
                                    <?php elseif ($web['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($web['status'] === 'archived'): ?>
                                        <span class="badge bg-secondary">Archived</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Suspended</span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?= escape(ucfirst($web['publication_status'])) ?></small>
                                </td>
                                <td><div class="small text-muted"><?= date('M d, Y', strtotime($web['created_at'])) ?></div></td>
                                <td class="text-end">
                                    <a href="/admin/website_view.php?id=<?= $web['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2 mb-1"><i class="bi bi-eye"></i> View</a>

                                    <?php if (!$web['deleted_at']): ?>
                                        <div class="dropdown d-inline-block">
                                            <button class="btn btn-sm btn-light py-1 px-2 mb-1" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <form method="POST" action="">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="website_id" value="<?= $web['id'] ?>">

                                                    <?php if ($web['status'] !== 'active'): ?>
                                                        <li><button type="submit" name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Activate</button></li>
                                                    <?php endif; ?>
                                                    <?php if ($web['status'] !== 'suspended'): ?>
                                                        <li><button type="submit" name="action" value="suspend" class="dropdown-item text-warning" onclick="return confirm('Suspending this website will take it offline. Are you sure?');"><i class="bi bi-pause-circle me-2"></i>Suspend</button></li>
                                                    <?php endif; ?>
                                                    <?php if ($web['status'] !== 'archived'): ?>
                                                        <li><button type="submit" name="action" value="archive" class="dropdown-item text-secondary"><i class="bi bi-archive me-2"></i>Archive</button></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><button type="submit" name="action" value="delete" class="dropdown-item text-danger" onclick="return confirm('This will soft delete the website. Are you sure?');"><i class="bi bi-trash me-2"></i>Delete</button></li>
                                                </form>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-globe empty-state-icon"></i>
                                    <p class="text-muted mb-0">No websites found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top p-3 d-flex align-items-center justify-content-between">
            <span class="small text-muted">Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> entries</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&limit=<?= $limit ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&limit=<?= $limit ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&limit=<?= $limit ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
