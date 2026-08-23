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

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "
    SELECT w.id, w.website_name, w.website_slug, w.status, w.subscription_status, w.created_at, w.deleted_at, u.name as owner_name, u.email as owner_email
    FROM websites w
    JOIN users u ON w.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (w.website_name LIKE ? OR w.website_slug LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    if ($status_filter === 'deleted') {
        $query .= " AND w.deleted_at IS NOT NULL";
    } else {
        $query .= " AND w.status = ? AND w.deleted_at IS NULL";
        $params[] = $status_filter;
    }
} else {
    $query .= " AND w.deleted_at IS NULL";
}

$query .= " ORDER BY w.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$websites = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Websites Management</h2>
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
                                <td><?= $web['id'] ?></td>
                                <td><?= escape($web['website_name']) ?></td>
                                <td><span class="text-muted">web.</span><?= escape($web['website_slug']) ?></td>
                                <td>
                                    <?= escape($web['owner_name']) ?><br>
                                    <small class="text-muted"><?= escape($web['owner_email']) ?></small>
                                </td>
                                <td>
                                    <?php if ($web['subscription_status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Free</span>
                                    <?php endif; ?>
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
                                </td>
                                <td class="text-end">
                                    <?php if (!$web['deleted_at']): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="website_id" value="<?= $web['id'] ?>">

                                            <?php if ($web['status'] !== 'active'): ?>
                                                <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                                            <?php endif; ?>
                                            <?php if ($web['status'] !== 'suspended'): ?>
                                                <button type="submit" name="action" value="suspend" class="btn btn-sm btn-warning">Suspend</button>
                                            <?php endif; ?>
                                            <?php if ($web['status'] !== 'archived'): ?>
                                                <button type="submit" name="action" value="archive" class="btn btn-sm btn-secondary">Archive</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No websites found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
