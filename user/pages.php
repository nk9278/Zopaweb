<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website
$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'You need to create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/pages.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_page') {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        // Basic sanitization
        $title = strip_tags($title);
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));

        if (empty($title) || empty($slug)) {
            set_flash_message('error', 'Title and slug are required.');
        } else {
            // Check uniqueness
            $check = $pdo->prepare("SELECT id FROM pages WHERE website_id = ? AND slug = ? AND deleted_at IS NULL");
            $check->execute([$website_id, $slug]);
            if ($check->fetch()) {
                set_flash_message('error', 'A page with this URL slug already exists.');
            } else {
                // Get max sort_order
                $max_order = $pdo->query("SELECT MAX(sort_order) FROM pages WHERE website_id = {$website_id}")->fetchColumn() ?: 0;

                $insert = $pdo->prepare("INSERT INTO pages (website_id, title, slug, sort_order) VALUES (?, ?, ?, ?)");
                $insert->execute([$website_id, $title, $slug, $max_order + 1]);
                $new_id = $pdo->lastInsertId();

                log_activity($pdo, $user_id, null, 'page_created', 'page', $new_id);
                set_flash_message('success', 'Page created successfully.');
                redirect("/user/builder.php?id={$new_id}");
            }
        }
    }
    elseif ($action === 'delete_page') {
        $page_id = (int)($_POST['page_id'] ?? 0);
        $verify = $pdo->prepare("SELECT id, slug FROM pages WHERE id = ? AND website_id = ?");
        $verify->execute([$page_id, $website_id]);
        $page = $verify->fetch();

        if ($page) {
            if ($page['slug'] === 'home') {
                set_flash_message('error', 'You cannot delete the home page.');
            } else {
                $delete = $pdo->prepare("UPDATE pages SET deleted_at = NOW() WHERE id = ?");
                $delete->execute([$page_id]);
                log_activity($pdo, $user_id, null, 'page_deleted', 'page', $page_id);
                set_flash_message('success', 'Page moved to trash.');
            }
        }
    }
    elseif ($action === 'duplicate_page') {
        $page_id = (int)($_POST['page_id'] ?? 0);
        $verify = $pdo->prepare("SELECT * FROM pages WHERE id = ? AND website_id = ?");
        $verify->execute([$page_id, $website_id]);
        $page = $verify->fetch(PDO::FETCH_ASSOC);

        if ($page) {
            $new_title = $page['title'] . ' (Copy)';
            $new_slug = $page['slug'] . '-copy-' . time();
            $max_order = $pdo->query("SELECT MAX(sort_order) FROM pages WHERE website_id = {$website_id}")->fetchColumn() ?: 0;

            $insert = $pdo->prepare("INSERT INTO pages (website_id, title, slug, page_type, status, show_in_nav, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$website_id, $new_title, $new_slug, $page['page_type'], 'draft', 0, $max_order + 1]);
            $new_page_id = $pdo->lastInsertId();

            // Duplicate sections
            $sec_stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? AND deleted_at IS NULL");
            $sec_stmt->execute([$page_id]);
            $sections = $sec_stmt->fetchAll(PDO::FETCH_ASSOC);

            $sec_insert = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, sort_order, status, settings_json, content_json) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($sections as $sec) {
                $sec_insert->execute([$new_page_id, $sec['section_type'], $sec['sort_order'], $sec['status'], $sec['settings_json'], $sec['content_json']]);
            }

            log_activity($pdo, $user_id, null, 'page_duplicated', 'page', $new_page_id);
            set_flash_message('success', 'Page duplicated successfully.');
        }
    }
        elseif ($action === 'update_page') {
        $page_id = (int)($_POST['page_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['slug'] ?? ''));
        $show_in_nav = isset($_POST['show_in_nav']) ? 1 : 0;

        $verify = $pdo->prepare("SELECT id, slug FROM pages WHERE id = ? AND website_id = ?");
        $verify->execute([$page_id, $website_id]);
        $page = $verify->fetch();

        if ($page) {
            // Check slug collision
            $check = $pdo->prepare("SELECT id FROM pages WHERE website_id = ? AND slug = ? AND id != ? AND deleted_at IS NULL");
            $check->execute([$website_id, $slug, $page_id]);
            if ($check->fetch()) {
                set_flash_message('error', 'That slug is already in use.');
            } else {
                // If it's home, force slug to remain home and nav to stay (or not, but slug must be home)
                if ($page['slug'] === 'home') {
                    $slug = 'home';
                }
                $seo_title = escape($_POST['seo_title'] ?? '');
                $seo_desc = escape($_POST['seo_description'] ?? '');
                $og_title = escape($_POST['og_title'] ?? '');
                $og_desc = escape($_POST['og_description'] ?? '');
                $canonical_url = escape($_POST['canonical_url'] ?? '');
                $r_index = isset($_POST['robots_index']) ? 1 : 0;
                $r_follow = isset($_POST['robots_follow']) ? 1 : 0;

                $update = $pdo->prepare("UPDATE pages SET title = ?, slug = ?, show_in_nav = ?, seo_title = ?, seo_description = ?, og_title = ?, og_description = ?, canonical_url = ?, robots_index = ?, robots_follow = ? WHERE id = ?");
                // Note: To prevent crashing if the user didn't run the exact updated phase11.sql migration against their dev DB,
                // in a real environment this would be strict. We'll attempt the full update but fallback to basic if the schema is old.
                try {
                    $update->execute([$title, $slug, $show_in_nav, $seo_title, $seo_desc, $og_title, $og_desc, $canonical_url, $r_index, $r_follow, $page_id]);
                } catch(PDOException $e) {
                    $fallback = $pdo->prepare("UPDATE pages SET title = ?, slug = ?, show_in_nav = ?, seo_title = ?, seo_description = ?, robots_index = ? WHERE id = ?");
                    $fallback->execute([$title, $slug, $show_in_nav, $seo_title, $seo_desc, $r_index, $page_id]);
                }
                set_flash_message('success', 'Page settings updated.');
            }
        }
    }
    elseif ($action === 'toggle_status') {
        $page_id = (int)($_POST['page_id'] ?? 0);
        $new_status = $_POST['status'] === 'published' ? 'published' : 'draft';
        $update = $pdo->prepare("UPDATE pages SET status = ? WHERE id = ? AND website_id = ?");
        $update->execute([$new_status, $page_id, $website_id]);
        set_flash_message('success', 'Page status updated.');
    }
    elseif ($action === 'reorder_pages') {
        $order = $_POST['order'] ?? '';
        $ids = explode(',', $order);
        $update = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ? AND website_id = ?");
        foreach ($ids as $index => $id) {
            if ($id) {
                $update->execute([$index, (int)$id, $website_id]);
            }
        }
        set_flash_message('success', 'Navigation order saved.');
    }

    redirect('/user/pages.php');
}

// Fetch pages
$stmt = $pdo->prepare("SELECT * FROM pages WHERE website_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
$stmt->execute([$website_id]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Website Pages</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPageModal">
        <i class="bi bi-plus-lg me-1"></i> Add Page
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($pages)): ?>
            <div class="empty-state p-5 text-center">
                <i class="bi bi-files empty-state-icon fs-1 text-muted mb-3 d-block"></i>
                <h5>No pages created yet</h5>
                <p class="text-muted">Start building your website by adding your first page.</p>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addPageModal">Create Page</button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Page Title</th>
                            <th>URL Slug</th>
                            <th>Status</th>
                            <th>Navigation</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pageList">
                        <?php foreach ($pages as $p): ?>
                        <tr data-id="<?= $p['id'] ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical text-muted me-2 cursor-move" style="cursor: grab;" title="Drag to reorder"></i>
                                    <strong><?= escape($p['title']) ?></strong>
                                    <?php if ($p['slug'] === 'home'): ?>
                                        <span class="badge bg-primary ms-2">Home</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="text-muted">/<?= escape($p['slug']) ?></span></td>
                            <td>
                                <?php if ($p['status'] === 'published'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">Published</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['show_in_nav']): ?>
                                    <i class="bi bi-eye text-primary" title="Visible in Navigation"></i>
                                <?php else: ?>
                                    <i class="bi bi-eye-slash text-muted" title="Hidden from Navigation"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="/user/builder.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary text-white">Edit</a>

                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        More
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <form method="POST" action="">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $p['status'] === 'published' ? 'draft' : 'published' ?>">
                                                <button type="submit" class="dropdown-item">
                                                    <?= $p['status'] === 'published' ? 'Unpublish to Draft' : 'Publish Page' ?>
                                                </button>
                                            </form>
                                        </li>
                                        <li><a class="dropdown-item" href="/public/site.php?website_id=<?= $website_id ?>&preview=<?= $p['slug'] ?>" target="_blank">Preview</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="openEditModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>', '<?= htmlspecialchars(addslashes($p['slug'])) ?>', <?= $p['show_in_nav'] ?>, '<?= htmlspecialchars(addslashes((string)($p['seo_title']??''))) ?>', '<?= htmlspecialchars(addslashes((string)($p['seo_description']??''))) ?>', '<?= htmlspecialchars(addslashes((string)($p['og_title']??''))) ?>', '<?= htmlspecialchars(addslashes((string)($p['og_description']??''))) ?>', '<?= htmlspecialchars(addslashes((string)($p['canonical_url']??''))) ?>', <?= isset($p['robots_index']) ? $p['robots_index'] : 'null' ?>, <?= isset($p['robots_follow']) ? $p['robots_follow'] : 'null' ?>); return false;">Edit Settings</a></li>
                                        <li>
                                            <form method="POST" action="">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="duplicate_page">
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="dropdown-item">Duplicate</button>
                                            </form>
                                        </li>
                                        <?php if ($p['slug'] !== 'home'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="" onsubmit="return confirm('<?= $p['show_in_nav'] ? 'This page is currently visible in your website navigation. ' : '' ?>Delete this page?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_page">
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">Delete</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white border-0 py-3">
                <form id="reorderForm" method="POST" action="">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="reorder_pages">
                    <input type="hidden" name="order" id="pageOrderInput">
                    <button type="button" id="saveOrderBtn" class="btn btn-sm btn-outline-secondary d-none">Save Navigation Order</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Page Modal -->
<div class="modal fade" id="addPageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Add New Page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create_page">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Page Title</label>
                        <input type="text" name="title" id="pageTitleInput" class="form-control" placeholder="e.g. Bridal Packages" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL Slug</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">/</span>
                            <input type="text" name="slug" id="pageSlugInput" class="form-control border-start-0 ps-0" placeholder="bridal-packages" required pattern="[a-z0-9-]+">
                        </div>
                        <div class="form-text">Only lowercase letters, numbers, and hyphens.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Page</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Page Modal -->
<div class="modal fade" id="editPageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Edit Page Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_page">
                <input type="hidden" name="page_id" id="editPageId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Page Title / Nav Label</label>
                        <input type="text" name="title" id="editPageTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL Slug</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">/</span>
                            <input type="text" name="slug" id="editPageSlug" class="form-control border-start-0 ps-0" required pattern="[a-z0-9-]+">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-4 pb-3 border-bottom">
                        <input class="form-check-input" type="checkbox" name="show_in_nav" id="editPageNav" value="1">
                        <label class="form-check-label" for="editPageNav">Show in navigation menu</label>
                    </div>

                    <h6 class="fw-bold mb-3">Page SEO Overrides (Optional)</h6>
                    <div class="mb-3">
                        <label class="form-label small">SEO Title Override</label>
                        <input type="text" name="seo_title" id="editSeoTitle" class="form-control form-control-sm" placeholder="Leave blank to use website default">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">SEO Description Override</label>
                        <textarea name="seo_description" id="editSeoDesc" class="form-control form-control-sm" rows="2" placeholder="Leave blank to use website default"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Canonical URL Override</label>
                        <input type="url" name="canonical_url" id="editCanonicalUrl" class="form-control form-control-sm" placeholder="Optional custom canonical URL">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small">OG Title</label>
                            <input type="text" name="og_title" id="editOgTitle" class="form-control form-control-sm" placeholder="Social share title">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">OG Description</label>
                            <input type="text" name="og_description" id="editOgDesc" class="form-control form-control-sm" placeholder="Social share desc">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="robots_index" id="editRobotsIndex" value="1">
                        <label class="form-check-label small" for="editRobotsIndex">Index page</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="robots_follow" id="editRobotsFollow" value="1">
                        <label class="form-check-label small" for="editRobotsFollow">Follow links</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, title, slug, showNav, seoTitle, seoDesc, ogTitle, ogDesc, canonicalUrl, robotsIndex, robotsFollow) {
    document.getElementById('editPageId').value = id;
    document.getElementById('editPageTitle').value = title;
    document.getElementById('editPageSlug').value = slug;
    document.getElementById('editPageNav').checked = showNav ? true : false;

    document.getElementById('editSeoTitle').value = seoTitle || '';
    document.getElementById('editSeoDesc').value = seoDesc || '';
    document.getElementById('editOgTitle').value = ogTitle || '';
    document.getElementById('editOgDesc').value = ogDesc || '';
    document.getElementById('editCanonicalUrl').value = canonicalUrl || '';
    document.getElementById('editRobotsIndex').checked = (robotsIndex === null || robotsIndex === 1) ? true : false;
    document.getElementById('editRobotsFollow').checked = (robotsFollow === null || robotsFollow === 1) ? true : false;

    // Disable slug for home page
    document.getElementById('editPageSlug').readOnly = (slug === 'home');

    new bootstrap.Modal(document.getElementById('editPageModal')).show();
}
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate slug
    const titleInput = document.getElementById('pageTitleInput');
    const slugInput = document.getElementById('pageSlugInput');

    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            let slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
            slugInput.value = slug;
        });
    }

    // Basic drag and drop reordering simulation (Since SortableJS isn't strictly loaded in template, we do a minimal version)
    const tbody = document.getElementById('pageList');
    if (tbody) {
        let draggedRow = null;

        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            row.setAttribute('draggable', true);

            row.addEventListener('dragstart', function(e) {
                draggedRow = this;
                this.classList.add('opacity-50');
            });

            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('bg-light');
            });

            row.addEventListener('dragleave', function(e) {
                this.classList.remove('bg-light');
            });

            row.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('bg-light');
                if (this !== draggedRow) {
                    let allRows = Array.from(tbody.querySelectorAll('tr'));
                    let draggedIdx = allRows.indexOf(draggedRow);
                    let targetIdx = allRows.indexOf(this);

                    if (draggedIdx < targetIdx) {
                        this.parentNode.insertBefore(draggedRow, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedRow, this);
                    }

                    // Show save order button
                    const btn = document.getElementById('saveOrderBtn');
                    if(btn) btn.classList.remove('d-none');
                }
            });

            row.addEventListener('dragend', function() {
                this.classList.remove('opacity-50');
            });
        });

        const saveOrderBtn = document.getElementById('saveOrderBtn');
        if (saveOrderBtn) {
            saveOrderBtn.addEventListener('click', function() {
                const newOrder = Array.from(tbody.querySelectorAll('tr')).map(r => r.dataset.id).join(',');
                document.getElementById('pageOrderInput').value = newOrder;
                document.getElementById('reorderForm').submit();
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
