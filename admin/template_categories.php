<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['category_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/template_categories.php');
    }

    $category_id = (int)$_POST['category_id'];
    $action = $_POST['action'];

    if ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE template_categories SET status = 'active' WHERE id = ?");
        $stmt->execute([$category_id]);
        set_flash_message('success', 'Category activated.');
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE template_categories SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$category_id]);
        set_flash_message('success', 'Category deactivated.');
    } elseif ($action === 'archive') {
        $stmt = $pdo->prepare("UPDATE template_categories SET status = 'archived' WHERE id = ?");
        $stmt->execute([$category_id]);
        set_flash_message('success', 'Category archived.');
    }

    redirect('/admin/template_categories.php');
}

$stmt = $pdo->query("SELECT * FROM template_categories ORDER BY id DESC");
$categories = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Template Categories</h2>
    <!-- Add category would go here in full implementation -->
    <button class="btn btn-primary" disabled>Add Category (Future Phase)</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categories) > 0): ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= $cat['id'] ?></td>
                                <td><?= escape($cat['name']) ?></td>
                                <td><?= escape($cat['slug']) ?></td>
                                <td><?= escape($cat['description']) ?></td>
                                <td>
                                    <?php if ($cat['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($cat['status'] === 'archived'): ?>
                                        <span class="badge bg-secondary">Archived</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">

                                        <?php if ($cat['status'] !== 'active'): ?>
                                            <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                                        <?php endif; ?>
                                        <?php if ($cat['status'] !== 'inactive'): ?>
                                            <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-warning">Deactivate</button>
                                        <?php endif; ?>
                                        <?php if ($cat['status'] !== 'archived'): ?>
                                            <button type="submit" name="action" value="archive" class="btn btn-sm btn-secondary">Archive</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No categories found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
