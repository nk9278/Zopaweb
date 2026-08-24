<?php
// user/page_editor.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$page_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$page_id) {
    redirect('/user/pages.php');
}

$stmt = $pdo->prepare("SELECT p.*, w.template_id, t.folder_key FROM pages p JOIN websites w ON p.website_id = w.id JOIN templates t ON w.template_id = t.id WHERE p.id = ? AND w.user_id = ? AND w.deleted_at IS NULL LIMIT 1");
$stmt->execute([$page_id, $user_id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    set_flash_message('error', 'Page not found or access denied.');
    redirect('/user/pages.php');
}

$website_id = $page['website_id'];

// Get template manifest to find supported sections
require_once __DIR__ . '/../includes/template_engine.php';
$validation = validate_template_manifest($page['folder_key']);
$supported_sections = $validation['valid'] && !empty($validation['manifest']['supports']) ? array_keys(array_filter($validation['manifest']['supports'])) : ['hero', 'about', 'services', 'gallery', 'reviews', 'contact'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect("/user/page_editor.php?id=$page_id");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_section') {
        $section_type = $_POST['section_type'] ?? '';
        if (in_array($section_type, $supported_sections)) {
            $insert = $pdo->prepare("INSERT INTO page_sections (website_id, page_id, section_type, sort_order, content) VALUES (?, ?, ?, ?, '{}')");
            $insert->execute([$website_id, $page_id, $section_type, 999]); // Puts at bottom
            set_flash_message('success', 'Section added.');
        } else {
            set_flash_message('error', 'Invalid section type.');
        }
    } elseif ($action === 'delete_section') {
        $section_id = (int)$_POST['section_id'];
        $delete = $pdo->prepare("DELETE FROM page_sections WHERE id=? AND page_id=? AND website_id=?");
        $delete->execute([$section_id, $page_id, $website_id]);
        set_flash_message('success', 'Section deleted.');
        } elseif ($action === 'move_section_up' || $action === 'move_section_down') {
        $section_id = (int)$_POST['section_id'];

        // Fetch current section
        $check = $pdo->prepare("SELECT id, sort_order FROM page_sections WHERE id=? AND page_id=? AND website_id=?");
        $check->execute([$section_id, $page_id, $website_id]);
        $current = $check->fetch();

        if ($current) {
            // Find adjacent section
            $operator = $action === 'move_section_up' ? '<' : '>';
            $order_dir = $action === 'move_section_up' ? 'DESC' : 'ASC';

            $adj_stmt = $pdo->prepare("SELECT id, sort_order FROM page_sections WHERE page_id=? AND website_id=? AND sort_order $operator ? ORDER BY sort_order $order_dir, id $order_dir LIMIT 1");
            $adj_stmt->execute([$page_id, $website_id, $current['sort_order']]);
            $adjacent = $adj_stmt->fetch();

            if ($adjacent) {
                // Swap sort_order
                $pdo->prepare("UPDATE page_sections SET sort_order=? WHERE id=? AND page_id=? AND website_id=?")->execute([$adjacent['sort_order'], $current['id'], $page_id, $website_id]);
                $pdo->prepare("UPDATE page_sections SET sort_order=? WHERE id=? AND page_id=? AND website_id=?")->execute([$current['sort_order'], $adjacent['id'], $page_id, $website_id]);
                set_flash_message('success', 'Section reordered.');
            }
        }
    } elseif ($action === 'reorder_sections') {
        // Expected an array of section_ids in the new order
        $order = $_POST['order'] ?? [];
        if (is_array($order)) {
            $update = $pdo->prepare("UPDATE page_sections SET sort_order = ? WHERE id = ? AND page_id = ? AND website_id = ?");
            foreach ($order as $index => $sid) {
                $update->execute([$index, (int)$sid, $page_id, $website_id]);
            }
            set_flash_message('success', 'Sections reordered.');
        }
    } elseif ($action === 'save_section_content') {
        $section_id = (int)$_POST['section_id'];

        // Ensure section belongs to user
        $check = $pdo->prepare("SELECT section_type FROM page_sections WHERE id=? AND page_id=? AND website_id=?");
        $check->execute([$section_id, $page_id, $website_id]);
        $sec = $check->fetch();

        if ($sec) {
            // Build the JSON configuration based on the incoming fields dynamically
            // (We enforce schema server-side before converting to JSON so no executable code sneaks in)
            $content_data = [];

            // Allow-listed fields we permit saving into the JSON blob
            $allowed_fields = ['heading', 'subheading', 'content_text', 'cta_text', 'cta_url', 'image_url', 'display_style'];
            foreach ($allowed_fields as $field) {
                if (isset($_POST[$field])) {
                    // Sanitize input
                    $content_data[$field] = htmlspecialchars(trim($_POST[$field]), ENT_QUOTES, 'UTF-8');
                }
            }

            $json_payload = json_encode($content_data);
            $update = $pdo->prepare("UPDATE page_sections SET content = ? WHERE id = ? AND page_id = ? AND website_id = ?");
            $update->execute([$json_payload, $section_id, $page_id, $website_id]);
            set_flash_message('success', 'Section content saved.');
        }
    }

    redirect("/user/page_editor.php?id=$page_id");
}

$stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? AND website_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$page_id, $website_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Page Builder - " . escape($page['title']);
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
    <div>
        <a href="/user/pages.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left me-1"></i>Back to Pages</a>
        <h1 class="h4 d-inline align-middle text-gray-800 fw-bold mb-0"><?= escape($page['title']) ?> <span class="badge bg-light text-muted fw-normal ms-2 border">Page Builder</span></h1>
    </div>
    <div>
        <!-- In R4/R5 Live Preview iframe will utilize this link -->
        <a href="/public/site.php?website_id=<?= $website_id ?>&preview=true&page=<?= $page['slug'] ?>" class="btn btn-outline-info me-2" target="_blank"><i class="bi bi-eye me-1"></i>Preview Page</a>
        <span class="text-success small fw-medium"><i class="bi bi-check-circle me-1"></i>All Changes Saved</span>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <!-- Left Panel: Section Manager -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold">Page Sections</h6>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="bi bi-plus"></i> Add
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sections)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted small mb-0">No sections added yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="section-list">
                        <!-- Normally Drag-And-Drop UI is initialized here via JS -->
                        <?php foreach ($sections as $index => $sec): ?>
                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-grip-vertical text-muted me-2 cursor-grab"></i>
                                    <span class="fw-medium text-capitalize"><?= escape($sec['section_type']) ?></span>
                                </div>
                                <div>
                                    <form method="POST" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="move_section_up">
                                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                                        <button type="submit" class="btn btn-sm text-secondary p-1" title="Move Up"><i class="bi bi-arrow-up"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="move_section_down">
                                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                                        <button type="submit" class="btn btn-sm text-secondary p-1" title="Move Down"><i class="bi bi-arrow-down"></i></button>
                                    </form>
                                    <button class="btn btn-sm text-primary p-1" onclick="editSection(<?= $sec['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this section?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_section">
                                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                                        <button type="submit" class="btn btn-sm text-danger p-1"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-light border small text-muted">
            <i class="bi bi-info-circle me-1"></i> Content like <strong>Services</strong>, <strong>Gallery</strong>, and <strong>Reviews</strong> are managed globally from your Dashboard. Adding those sections here simply determines where they appear on this specific page.
        </div>
    </div>

    <!-- Center/Right Panel: Section Editor Canvas -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 bg-light">
            <div class="card-body" id="editor-canvas">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-layout-wtf display-1 mb-3 text-secondary opacity-50"></i>
                    <h4>Select a section to edit</h4>
                    <p>Click the edit icon on any section in the left panel to configure its specific content.</p>
                </div>

                <?php foreach ($sections as $sec):
                    $content = json_decode($sec['content'], true) ?: [];
                ?>
                <!-- Hidden forms toggled via JS -->
                <div id="edit-form-<?= $sec['id'] ?>" class="section-edit-form d-none">
                    <h5 class="fw-bold mb-4 text-capitalize border-bottom pb-2">Edit <?= escape($sec['section_type']) ?> Settings</h5>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="save_section_content">
                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">

                        <?php if (in_array($sec['section_type'], ['hero', 'about', 'cta'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Heading</label>
                                <input type="text" name="heading" class="form-control" value="<?= escape($content['heading'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Subheading / Content Text</label>
                                <textarea name="content_text" class="form-control" rows="4"><?= escape($content['content_text'] ?? '') ?></textarea>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($sec['section_type'], ['hero', 'cta'])): ?>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-medium">Button Text</label>
                                    <input type="text" name="cta_text" class="form-control" value="<?= escape($content['cta_text'] ?? '') ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-medium">Button Link</label>
                                    <input type="text" name="cta_url" class="form-control" value="<?= escape($content['cta_url'] ?? '') ?>">
                                </div>
                            </div>
                        <?php endif; ?>

                                                <?php if (in_array($sec['section_type'], ['hero', 'about'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Select Image</label>
                                <select name="image_url" class="form-select">
                                    <option value="">-- No Image --</option>
                                    <?php
                                    $media_stmt = $pdo->prepare("SELECT id, original_filename FROM media WHERE website_id = ? AND media_type = 'image' AND status = 'active' ORDER BY created_at DESC");
                                    $media_stmt->execute([$website_id]);
                                    while($m = $media_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        // Store the ID as the value mapped to 'image_url' JSON key for backwards compatibility
                                        $val = 'media:' . $m['id'];
                                        $selected = (($content['image_url'] ?? '') === $val) ? 'selected' : '';
                                        echo '<option value="'.$val.'" '.$selected.'>'.escape($m['original_filename']).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($sec['section_type'], ['services', 'gallery', 'reviews'])): ?>
                            <div class="alert alert-info border-0">
                                This section automatically pulls data from your globally managed <strong><?= escape(ucfirst($sec['section_type'])) ?></strong> list.
                                <br><a href="/user/<?= escape($sec['section_type']) ?>.php" class="alert-link">Manage <?= escape($sec['section_type']) ?> here.</a>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Section Display Title (Optional)</label>
                                <input type="text" name="heading" class="form-control" value="<?= escape($content['heading'] ?? '') ?>" placeholder="Leave blank to use default">
                            </div>
                        <?php endif; ?>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">Save Content</button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_section">
                <div class="modal-header">
                    <h5 class="modal-title">Add Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">The following sections are compatible with your currently active template.</p>
                    <div class="list-group">
                        <?php foreach ($supported_sections as $sec_type): ?>
                            <label class="list-group-item d-flex gap-2">
                                <input class="form-check-input flex-shrink-0" type="radio" name="section_type" value="<?= escape($sec_type) ?>" required>
                                <span>
                                    <span class="fw-medium text-capitalize"><?= escape($sec_type) ?></span>
                                    <small class="d-block text-muted">Add a <?= escape($sec_type) ?> block to your page.</small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSection(id) {
    // Hide default empty state
    document.querySelector('#editor-canvas > div.text-center').classList.add('d-none');

    // Hide all forms
    document.querySelectorAll('.section-edit-form').forEach(el => el.classList.add('d-none'));

    // Show selected form
    const form = document.getElementById('edit-form-' + id);
    if(form) {
        form.classList.remove('d-none');
        form.classList.add('fade-in');
    }
}
</script>
<style>
.cursor-grab { cursor: grab; }
.fade-in { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
