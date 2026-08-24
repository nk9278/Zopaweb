<?php
// user/pages.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/pages.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived']) ? $_POST['status'] : 'draft';
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $show_in_navigation = isset($_POST['show_in_navigation']) ? 1 : 0;
        $is_homepage = isset($_POST['is_homepage']) ? 1 : 0;

        // Clean slug
        $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug));
        if (empty($slug)) $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $title));

        if (empty($title) || empty($slug)) {
            set_flash_message('error', 'Page title and slug are required.');
        } else {
            // Check slug uniqueness per website
            $check_slug = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND website_id = ? AND id != ?");
            $check_slug->execute([$slug, $website_id, $action === 'edit' ? (int)$_POST['page_id'] : 0]);
            if ($check_slug->fetch()) {
                set_flash_message('error', 'A page with that slug already exists.');
                redirect('/user/pages.php');
            }

            if ($is_homepage) {
                // Remove homepage flag from others
                $pdo->prepare("UPDATE pages SET is_homepage = 0 WHERE website_id = ?")->execute([$website_id]);
            }

            if ($action === 'add') {
                $insert = $pdo->prepare("INSERT INTO pages (website_id, title, slug, status, sort_order, show_in_navigation, is_homepage) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$website_id, $title, $slug, $status, $sort_order, $show_in_navigation, $is_homepage]);
                set_flash_message('success', 'Page created.');
            } else {
                $page_id = (int)$_POST['page_id'];
                $update = $pdo->prepare("UPDATE pages SET title=?, slug=?, status=?, sort_order=?, show_in_navigation=?, is_homepage=? WHERE id=? AND website_id=?");
                $update->execute([$title, $slug, $status, $sort_order, $show_in_navigation, $is_homepage, $page_id, $website_id]);
                set_flash_message('success', 'Page updated.');
            }
        }
    } elseif ($action === 'delete') {
        $page_id = (int)$_POST['page_id'];
        // Soft delete could be mapped if schema supported, here we delete cleanly per spec for pages
        // Or if it's homepage, prevent deletion
        $check = $pdo->prepare("SELECT is_homepage FROM pages WHERE id=? AND website_id=?");
        $check->execute([$page_id, $website_id]);
        $p = $check->fetch();
        if ($p && $p['is_homepage']) {
             set_flash_message('error', 'Cannot delete the homepage. Set another page as homepage first.');
        } else {
            $delete = $pdo->prepare("DELETE FROM pages WHERE id=? AND website_id=?");
            $delete->execute([$page_id, $website_id]);
            set_flash_message('success', 'Page deleted.');
        }
    } elseif ($action === 'duplicate') {
        $page_id = (int)$_POST['page_id'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE id=? AND website_id=?");
            $stmt->execute([$page_id, $website_id]);
            $source = $stmt->fetch();

            if ($source) {
                $new_slug = $source['slug'] . '-copy-' . time();
                $insert = $pdo->prepare("INSERT INTO pages (website_id, title, slug, status, sort_order, show_in_navigation, is_homepage) VALUES (?, ?, ?, 'draft', ?, 0, 0)");
                $insert->execute([$website_id, $source['title'] . ' (Copy)', $new_slug, $source['sort_order'] + 1]);
                $new_page_id = $pdo->lastInsertId();

                // Duplicate sections
                $sec_stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id=? AND website_id=?");
                $sec_stmt->execute([$page_id, $website_id]);
                $sections = $sec_stmt->fetchAll();

                $sec_insert = $pdo->prepare("INSERT INTO page_sections (website_id, page_id, section_type, sort_order, content, status) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($sections as $s) {
                    $sec_insert->execute([$website_id, $new_page_id, $s['section_type'], $s['sort_order'], $s['content'], $s['status']]);
                }

                $pdo->commit();
                set_flash_message('success', 'Page duplicated successfully.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('error', 'Duplication failed.');
        }
    }

    redirect('/user/pages.php');
}

$stmt = $pdo->prepare("SELECT * FROM pages WHERE website_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$website_id]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Pages";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Pages</h1>
    <div>
        <a href="/user/dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPageModal">
            <i class="bi bi-plus-lg me-1"></i>Add Page
        </button>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <?php if (empty($pages)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center">
                <i class="bi bi-layout-text-window display-4 text-muted mb-3"></i>
                <h5>No pages yet.</h5>
                <p class="text-muted">Create your first page to start building your site.</p>
                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addPageModal">Create Page</button>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Title</th>
                                    <th>URL Path</th>
                                    <th>Status</th>
                                    <th>Navigation</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $p): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <?= escape($p['title']) ?>
                                        <?php if ($p['is_homepage']): ?>
                                            <span class="badge bg-primary ms-2">Homepage</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">/<?= escape($p['slug']) ?></td>
                                    <td>
                                        <span class="badge <?= $p['status'] === 'published' ? 'bg-success' : ($p['status'] === 'draft' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                            <?= escape(ucfirst($p['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['show_in_navigation']): ?>
                                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Visible</span> (Order: <?= (int)$p['sort_order'] ?>)
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-eye-slash-fill me-1"></i>Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="/user/page_editor.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary border me-1"><i class="bi bi-magic me-1"></i>Builder</a>
                                        <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#editPageModal<?= $p['id'] ?>"><i class="bi bi-gear"></i></button>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('Duplicate this page?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="duplicate">
                                            <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-light border me-1" title="Duplicate"><i class="bi bi-copy"></i></button>
                                        </form>

                                        <?php if (!$p['is_homepage']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this page permanently?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger disabled" title="Cannot delete homepage"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editPageModal<?= $p['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Page Settings</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-medium">Page Title *</label>
                                                        <input type="text" name="title" class="form-control" required value="<?= escape($p['title']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-medium">URL Slug *</label>
                                                        <input type="text" name="slug" class="form-control" required value="<?= escape($p['slug']) ?>">
                                                        <div class="form-text">e.g. 'about' becomes /about</div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-6 mb-3">
                                                            <label class="form-label fw-medium">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="draft" <?= $p['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                                <option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                                                <option value="archived" <?= $p['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-6 mb-3">
                                                            <label class="form-label fw-medium">Navigation Order</label>
                                                            <input type="number" name="sort_order" class="form-control" value="<?= (int)$p['sort_order'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" name="show_in_navigation" id="navCheck<?= $p['id'] ?>" <?= $p['show_in_navigation'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="navCheck<?= $p['id'] ?>">Show in public navigation menu</label>
                                                    </div>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" name="is_homepage" id="homeCheck<?= $p['id'] ?>" <?= $p['is_homepage'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="homeCheck<?= $p['id'] ?>">Set as Website Homepage</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Settings</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addPageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Create Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Page Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Bridal Services">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">URL Slug</label>
                        <input type="text" name="slug" class="form-control" placeholder="Leave empty to auto-generate">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft">Draft</option>
                                <option value="published" selected>Published</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-medium">Navigation Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="show_in_navigation" id="navCheckNew" checked>
                        <label class="form-check-label" for="navCheckNew">Show in public navigation menu</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_homepage" id="homeCheckNew">
                        <label class="form-check-label" for="homeCheckNew">Set as Website Homepage</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Page</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
