<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['template_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/templates.php');
    }

    $template_id = (int)$_POST['template_id'];
    $action = $_POST['action'];

    // Handle dangerous delete attempts
    if ($action === 'delete') {
        // Check active usages
        $chk = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE template_id = ? AND status = 'active' AND deleted_at IS NULL");
        $chk->execute([$template_id]);
        $active_usage = $chk->fetchColumn();

        if ($active_usage > 0) {
            set_flash_message('error', "This template is currently used by $active_usage active websites and cannot be permanently deleted. Consider archiving it instead.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
            $stmt->execute([$template_id]);
            log_activity($pdo, null, $_SESSION['user_id'], 'deleted_template', 'template', $template_id);
            set_flash_message('success', 'Template permanently deleted.');
        }
    } else {
        if ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE templates SET status = 'active' WHERE id = ?");
            $stmt->execute([$template_id]);
            set_flash_message('success', 'Template activated.');
        } elseif ($action === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE templates SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$template_id]);
            set_flash_message('success', 'Template deactivated.');
        } elseif ($action === 'archive') {
            $stmt = $pdo->prepare("UPDATE templates SET status = 'archived' WHERE id = ?");
            $stmt->execute([$template_id]);
            set_flash_message('success', 'Template archived.');
        }
    }
    redirect('/admin/templates.php');
}

// Pagination setup
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = in_array($limit, [20, 50, 100]) ? $limit : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';

$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (t.name LIKE ? OR t.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM templates t
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "
    SELECT t.id, t.name, t.slug, t.version, t.folder_key, t.status, t.created_at,
           c.name as category_name,
           (SELECT COUNT(*) FROM websites WHERE template_id = t.id AND deleted_at IS NULL) as usage_count
    FROM templates t
    JOIN template_categories c ON t.category_id = c.id
    $where_sql
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$templates = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Templates Management</h2>
    <a href="/admin/template_view.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i> Register Template</a>
</div>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Search template name or slug" value="<?= escape($search) ?>">
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
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Folder Key</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($templates) > 0): ?>
                        <?php foreach ($templates as $tpl): ?>
                            <tr>
                                <td class="fw-medium">
                                    <?= escape($tpl['name']) ?><br>
                                    <small class="text-muted fw-normal">v<?= escape($tpl['version']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= escape($tpl['category_name']) ?></span></td>
                                <td><code class="text-primary bg-light px-2 py-1 rounded"><?= escape($tpl['folder_key']) ?></code></td>
                                <td>
                                    <?php if ($tpl['usage_count'] > 0): ?>
                                        <span class="badge bg-info text-dark rounded-pill"><?= (int)$tpl['usage_count'] ?> websites</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border rounded-pill">Unused</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tpl['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($tpl['status'] === 'draft'): ?>
                                        <span class="badge bg-secondary">Draft</span>
                                    <?php elseif ($tpl['status'] === 'archived'): ?>
                                        <span class="badge bg-dark">Archived</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="small text-muted"><?= date('M d, Y', strtotime($tpl['created_at'])) ?></div></td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-light py-1 px-2 mb-1" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <form method="POST" action="">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">

                                                <?php if ($tpl['status'] !== 'active'): ?>
                                                    <li><button type="submit" name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Activate</button></li>
                                                <?php endif; ?>
                                                <?php if ($tpl['status'] !== 'inactive' && $tpl['status'] !== 'archived'): ?>
                                                    <li><button type="submit" name="action" value="deactivate" class="dropdown-item text-warning"><i class="bi bi-pause-circle me-2"></i>Deactivate</button></li>
                                                <?php endif; ?>
                                                <?php if ($tpl['status'] !== 'archived'): ?>
                                                    <li><button type="submit" name="action" value="archive" class="dropdown-item text-secondary"><i class="bi bi-archive me-2"></i>Archive</button></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button type="submit" name="action" value="delete" class="dropdown-item text-danger" onclick="return confirm('This will permanently delete the template if it is unused. Are you sure?');"><i class="bi bi-trash me-2"></i>Delete</button></li>
                                            </form>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-palette empty-state-icon"></i>
                                    <p class="text-muted mb-0">No templates have been registered yet.</p>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
