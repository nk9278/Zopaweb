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

    redirect('/admin/templates.php');
}

$search = $_GET['search'] ?? '';

$query = "
    SELECT t.id, t.name, t.slug, t.folder_key, t.status, c.name as category_name
    FROM templates t
    JOIN template_categories c ON t.category_id = c.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (t.name LIKE ? OR t.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY t.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$templates = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Templates Management</h2>
    <!-- Add template would go here in full implementation -->
    <button class="btn btn-primary" disabled>Add Template (Future Phase)</button>
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
                                <td><?= $tpl['id'] ?></td>
                                <td><?= escape($tpl['name']) ?></td>
                                <td><?= escape($tpl['category_name']) ?></td>
                                <td><code><?= escape($tpl['folder_key']) ?></code></td>
                                <td>
                                    <?php if ($tpl['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($tpl['status'] === 'draft'): ?>
                                        <span class="badge bg-info">Draft</span>
                                    <?php elseif ($tpl['status'] === 'archived'): ?>
                                        <span class="badge bg-secondary">Archived</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">

                                        <?php if ($tpl['status'] !== 'active'): ?>
                                            <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                                        <?php endif; ?>
                                        <?php if ($tpl['status'] !== 'inactive' && $tpl['status'] !== 'archived'): ?>
                                            <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-warning">Deactivate</button>
                                        <?php endif; ?>
                                        <?php if ($tpl['status'] !== 'archived'): ?>
                                            <button type="submit" name="action" value="archive" class="btn btn-sm btn-secondary">Archive</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No templates found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
