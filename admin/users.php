<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

// Handle Actions (Activate, Suspend, Delete, Restore)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/users.php');
    }

    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];

    // Ensure not modifying oneself
    if ($user_id === $_SESSION['user_id']) {
        set_flash_message('error', 'You cannot modify your own account from here.');
        redirect('/admin/users.php');
    }

    if ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'activated_user', 'user', $user_id);
        set_flash_message('success', 'User activated.');
    } elseif ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$user_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'suspended_user', 'user', $user_id);
        set_flash_message('success', 'User suspended.');
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'deleted_user', 'user', $user_id);
        set_flash_message('success', 'User deleted.');
    } elseif ($action === 'restore') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active', deleted_at = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        log_activity($pdo, null, $_SESSION['user_id'], 'restored_user', 'user', $user_id);
        set_flash_message('success', 'User restored.');
    }

    redirect('/admin/users.php');
}

// Pagination setup
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = in_array($limit, [20, 50, 100]) ? $limit : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch users
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_filter) {
    $where_sql .= " AND role = ?";
    $params[] = $role_filter;
}
if ($status_filter) {
    if ($status_filter === 'deleted') {
        $where_sql .= " AND deleted_at IS NOT NULL";
    } else {
        $where_sql .= " AND status = ? AND deleted_at IS NULL";
        $params[] = $status_filter;
    }
} else {
    // By default, don't show deleted unless filtered
    $where_sql .= " AND deleted_at IS NULL";
}

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated
$query = "
    SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.created_at, u.last_login_at, u.deleted_at,
           (SELECT COUNT(*) FROM websites WHERE user_id = u.id AND deleted_at IS NULL) as website_count
    FROM users u
    $where_sql
    ORDER BY u.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Users Management</h2>
    <!-- Future: Add User CTA -->
    <button class="btn btn-primary shadow-sm" disabled><i class="bi bi-plus-lg me-1"></i> Add User</button>
</div>

<!-- Filters -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search name or email" value="<?= escape($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="creator" <?= $role_filter === 'creator' ? 'selected' : '' ?>>Creator</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Active & Suspended</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
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
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="fw-medium">
                                    <?= escape($user['name']) ?><br>
                                    <small class="text-muted fw-normal"><?= escape($user['email']) ?></small>
                                </td>
                                <td><span class="text-muted small"><?= escape($user['phone'] ?? 'N/A') ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= escape(ucfirst($user['role'])) ?></span></td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill"><?= (int)$user['website_count'] ?></span>
                                </td>
                                <td>
                                    <?php if ($user['deleted_at']): ?>
                                        <span class="badge bg-danger">Deleted</span>
                                    <?php elseif ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small"><?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="small text-muted"><?= $user['last_login_at'] ? date('M d, Y', strtotime($user['last_login_at'])) : 'Never' ?></div>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/user_view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2 mb-1"><i class="bi bi-eye"></i> View</a>

                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <div class="dropdown d-inline-block">
                                            <button class="btn btn-sm btn-light py-1 px-2 mb-1" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <form method="POST" action="">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

                                                    <?php if ($user['deleted_at']): ?>
                                                        <li><button type="submit" name="action" value="restore" class="dropdown-item text-success"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button></li>
                                                    <?php else: ?>
                                                        <?php if ($user['status'] === 'active'): ?>
                                                            <li><button type="submit" name="action" value="suspend" class="dropdown-item text-warning" onclick="return confirm('Suspending this user will prevent them from accessing their account. Are you sure?');"><i class="bi bi-pause-circle me-2"></i>Suspend</button></li>
                                                        <?php else: ?>
                                                            <li><button type="submit" name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Activate</button></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><button type="submit" name="action" value="delete" class="dropdown-item text-danger" onclick="return confirm('This will soft delete the user. Are you sure?');"><i class="bi bi-trash me-2"></i>Delete</button></li>
                                                    <?php endif; ?>
                                                </form>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-people empty-state-icon"></i>
                                    <p class="text-muted mb-0">No users found matching your criteria.</p>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>&limit=<?= $limit ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>&limit=<?= $limit ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>&limit=<?= $limit ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
