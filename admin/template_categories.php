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

// Add or Edit category logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add_category', 'edit_category'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/template_categories.php');
    }

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if (empty($name) || empty($slug)) {
        set_flash_message('error', 'Name and Slug are required.');
    } else {
        try {
            if ($_POST['action'] === 'add_category') {
                $stmt = $pdo->prepare("INSERT INTO template_categories (name, slug, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $slug, $description]);
                log_activity($pdo, null, $_SESSION['user_id'], 'created_category', 'template_category', $pdo->lastInsertId());
                set_flash_message('success', 'Category added successfully.');
            } else {
                $stmt = $pdo->prepare("UPDATE template_categories SET name = ?, slug = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $description, $edit_id]);
                log_activity($pdo, null, $_SESSION['user_id'], 'updated_category', 'template_category', $edit_id);
                set_flash_message('success', 'Category updated successfully.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                set_flash_message('error', 'Category slug must be unique.');
            } else {
                set_flash_message('error', 'Database error saving category.');
            }
        }
    }
    redirect('/admin/template_categories.php');
}

$stmt = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM templates WHERE category_id = c.id AND status != 'archived') as template_count
    FROM template_categories c
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Template Categories</h2>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="bi bi-plus-lg me-1"></i> Add Category</button>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-0">
        <h5 class="modal-title" id="addCategoryModalLabel">Add Template Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="">
          <div class="modal-body">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="add_category">
              <div class="mb-3">
                  <label class="form-label fw-medium">Category Name</label>
                  <input type="text" name="name" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-medium">Slug</label>
                  <input type="text" name="slug" class="form-control" required placeholder="e.g. bridal-luxury">
              </div>
              <div class="mb-3">
                  <label class="form-label fw-medium">Description (Optional)</label>
                  <textarea name="description" class="form-control" rows="3"></textarea>
              </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Category</button>
          </div>
      </form>
    </div>
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
                                <td class="fw-medium">
                                    <?= escape($cat['name']) ?><br>
                                    <small class="text-muted fw-normal"><?= escape($cat['slug']) ?></small>

                                    <!-- Hidden form data for edit modal population (via future JS if desired, or manual) -->
                                    <div class="d-none category-data"
                                         data-id="<?= $cat['id'] ?>"
                                         data-name="<?= escape($cat['name']) ?>"
                                         data-slug="<?= escape($cat['slug']) ?>"
                                         data-description="<?= escape($cat['description']) ?>"></div>
                                </td>
                                <td><span class="text-muted small"><?= escape($cat['description'] ?? 'No description') ?></span></td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill"><?= (int)$cat['template_count'] ?></span>
                                </td>
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
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-light py-1 px-2 mb-1" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <form method="POST" action="">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">

                                                <li><button type="button" class="dropdown-item edit-category-btn"><i class="bi bi-pencil me-2"></i>Edit Metadata</button></li>
                                                <?php if ($cat['status'] !== 'active'): ?>
                                                    <li><button type="submit" name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Activate</button></li>
                                                <?php endif; ?>
                                                <?php if ($cat['status'] !== 'inactive' && $cat['status'] !== 'archived'): ?>
                                                    <li><button type="submit" name="action" value="deactivate" class="dropdown-item text-warning"><i class="bi bi-pause-circle me-2"></i>Deactivate</button></li>
                                                <?php endif; ?>
                                                <?php if ($cat['status'] !== 'archived'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><button type="submit" name="action" value="archive" class="dropdown-item text-secondary"><i class="bi bi-archive me-2"></i>Archive</button></li>
                                                <?php endif; ?>
                                            </form>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-state border-0">
                                    <i class="bi bi-tags empty-state-icon"></i>
                                    <p class="text-muted mb-0">No template categories have been created yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-0">
        <h5 class="modal-title" id="editCategoryModalLabel">Edit Template Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="">
          <div class="modal-body">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="edit_category">
              <input type="hidden" name="edit_id" id="edit_category_id" value="">
              <div class="mb-3">
                  <label class="form-label fw-medium">Category Name</label>
                  <input type="text" name="name" id="edit_category_name" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-medium">Slug</label>
                  <input type="text" name="slug" id="edit_category_slug" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label fw-medium">Description (Optional)</label>
                  <textarea name="description" id="edit_category_description" class="form-control" rows="3"></textarea>
              </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Category</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-category-btn');
    const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));

    editBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tr = this.closest('tr');
            const dataDiv = tr.querySelector('.category-data');

            document.getElementById('edit_category_id').value = dataDiv.getAttribute('data-id');
            document.getElementById('edit_category_name').value = dataDiv.getAttribute('data-name');
            document.getElementById('edit_category_slug').value = dataDiv.getAttribute('data-slug');
            document.getElementById('edit_category_description').value = dataDiv.getAttribute('data-description');

            editModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
