<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$is_new = !isset($_GET['id']);
$template_id = $is_new ? 0 : (int)$_GET['id'];
$template = null;

if (!$is_new) {
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();

    if (!$template) {
        set_flash_message('error', 'Template not found.');
        redirect('/admin/templates.php');
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/template_view.php' . ($is_new ? '' : '?id=' . $template_id));
    }

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $folder_key = trim($_POST['folder_key'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $status = $_POST['status'] ?? 'draft';

    $errors = [];
    if (empty($name)) $errors[] = "Template name is required.";
    if (empty($slug)) $errors[] = "Template slug is required.";
    if (empty($folder_key)) $errors[] = "Folder key is required.";
    if ($category_id <= 0) $errors[] = "Category is required.";

    if (empty($errors)) {
        try {
            if ($is_new) {
                $stmt = $pdo->prepare("INSERT INTO templates (category_id, name, slug, description, folder_key, version, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $name, $slug, $description, $folder_key, $version, $status]);
                $new_id = $pdo->lastInsertId();
                log_activity($pdo, null, $_SESSION['user_id'], 'registered_template', 'template', $new_id);
                set_flash_message('success', 'Template registered successfully.');
                redirect('/admin/templates.php');
            } else {
                $stmt = $pdo->prepare("UPDATE templates SET category_id = ?, name = ?, slug = ?, description = ?, folder_key = ?, version = ?, status = ? WHERE id = ?");
                $stmt->execute([$category_id, $name, $slug, $description, $folder_key, $version, $status, $template_id]);
                log_activity($pdo, null, $_SESSION['user_id'], 'updated_template', 'template', $template_id);
                set_flash_message('success', 'Template metadata updated successfully.');
                redirect('/admin/template_view.php?id=' . $template_id);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = "Template slug must be unique.";
            } else {
                $errors[] = "A database error occurred.";
            }
        }
    }

    if (!empty($errors)) {
        foreach ($errors as $e) {
            set_flash_message('error', $e);
        }
    }
}

// Fetch categories for dropdown
$cat_stmt = $pdo->query("SELECT id, name FROM template_categories WHERE status != 'archived' ORDER BY name ASC");
$categories = $cat_stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/templates.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h3 mb-0 text-gray-800"><?= $is_new ? 'Register New Template' : 'Edit Template Metadata' ?></h2>
</div>

<?php if ($is_new): ?>
    <div class="alert alert-primary border-0 shadow-sm mb-4">
        <i class="bi bi-info-circle-fill me-2"></i> <strong>Note:</strong> This interface registers the template's metadata. The actual PHP/HTML template folder (matching the Folder Key) must be deployed separately by a developer.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?php csrf_field(); ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= escape($template['name'] ?? old('name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Slug <span class="text-danger">*</span></label>
                            <input type="text" name="slug" class="form-control" value="<?= escape($template['slug'] ?? old('slug')) ?>" required placeholder="e.g. elegant-beauty">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($template['category_id'] ?? old('category_id')) == $cat['id'] ? 'selected' : '' ?>>
                                        <?= escape($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Folder Key <span class="text-danger">*</span></label>
                            <input type="text" name="folder_key" class="form-control" value="<?= escape($template['folder_key'] ?? old('folder_key')) ?>" required placeholder="e.g. theme_elegant">
                            <div class="form-text">Must match the exact directory name in Phase 4.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Version</label>
                            <input type="text" name="version" class="form-control" value="<?= escape($template['version'] ?? old('version', '1.0')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= ($template['status'] ?? old('status')) === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="active" <?= ($template['status'] ?? old('status')) === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($template['status'] ?? old('status')) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <?php if (!$is_new): ?>
                                    <option value="archived" <?= ($template['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= escape($template['description'] ?? old('description')) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end pt-3 border-top">
                        <a href="/admin/templates.php" class="btn btn-light me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <?= $is_new ? 'Register Template' : 'Save Changes' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
