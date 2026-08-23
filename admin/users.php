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

// Fetch users
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT id, name, email, role, status, created_at, deleted_at FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}
if ($status_filter) {
    if ($status_filter === 'deleted') {
        $query .= " AND deleted_at IS NOT NULL";
    } else {
        $query .= " AND status = ? AND deleted_at IS NULL";
        $params[] = $status_filter;
    }
} else {
    // By default, don't show deleted unless filtered
    $query .= " AND deleted_at IS NULL";
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Users Management</h2>
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
                                <td><?= $user['id'] ?></td>
                                <td><?= escape($user['name']) ?></td>
                                <td><?= escape($user['email']) ?></td>
                                <td><span class="badge bg-secondary"><?= escape(ucfirst($user['role'])) ?></span></td>
                                <td>
                                    <?php if ($user['deleted_at']): ?>
                                        <span class="badge bg-danger">Deleted</span>
                                    <?php elseif ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

                                            <?php if ($user['deleted_at']): ?>
                                                <button type="submit" name="action" value="restore" class="btn btn-sm btn-success">Restore</button>
                                            <?php else: ?>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button type="submit" name="action" value="suspend" class="btn btn-sm btn-warning">Suspend</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                                                <?php endif; ?>
                                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
