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
$template_id = $website['template_id'];

// Get Page
$page_id = (int)($_GET['id'] ?? 0);
$page_stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? AND website_id = ? AND deleted_at IS NULL");
$page_stmt->execute([$page_id, $website_id]);
$page = $page_stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    set_flash_message('error', 'Page not found.');
    redirect('/user/pages.php');
}

// Get Template capabilities
$template_stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$template_stmt->execute([$template_id]);
$template = $template_stmt->fetch();

$supported_sections = [];
if ($template) {
    $manifest_path = __DIR__ . '/../templates/' . $template['folder_key'] . '/template.json';
    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true) ?: [];
        $supported_sections = $manifest['supports']['sections'] ?? [];
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die(json_encode(['success' => false, 'error' => 'Invalid security token.']));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_section') {
        $type = $_POST['section_type'] ?? '';
        if (empty($supported_sections[$type])) {
            set_flash_message('error', 'Section not supported by active template.');
        } else {
            $max_order = $pdo->query("SELECT MAX(sort_order) FROM page_sections WHERE page_id = {$page_id}")->fetchColumn() ?: 0;
            $insert = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, sort_order) VALUES (?, ?, ?)");
            $insert->execute([$page_id, $type, $max_order + 1]);
            set_flash_message('success', 'Section added.');
        }
        redirect("/user/builder.php?id={$page_id}");
    }
    elseif ($action === 'duplicate_section') {
        $sec_id = (int)($_POST['section_id'] ?? 0);
        $verify = $pdo->prepare("SELECT * FROM page_sections WHERE id = ? AND page_id = ? AND deleted_at IS NULL");
        $verify->execute([$sec_id, $page_id]);
        $sec = $verify->fetch(PDO::FETCH_ASSOC);

        if ($sec) {
            $max_order = $pdo->query("SELECT MAX(sort_order) FROM page_sections WHERE page_id = {$page_id}")->fetchColumn() ?: 0;
            $insert = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, sort_order, status, settings_json, content_json) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->execute([$page_id, $sec['section_type'], $max_order + 1, $sec['status'], $sec['settings_json'], $sec['content_json']]);
            set_flash_message('success', 'Section duplicated.');
        }
        redirect("/user/builder.php?id={$page_id}");
    }
    elseif ($action === 'delete_section') {
        $sec_id = (int)($_POST['section_id'] ?? 0);
        $delete = $pdo->prepare("UPDATE page_sections SET deleted_at = NOW() WHERE id = ? AND page_id = ?");
        $delete->execute([$sec_id, $page_id]);
        set_flash_message('success', 'Section removed.');
        redirect("/user/builder.php?id={$page_id}");
    }
    elseif ($action === 'toggle_section') {
        $sec_id = (int)($_POST['section_id'] ?? 0);
        $status = $_POST['status'] === 'visible' ? 'visible' : 'hidden';
        $update = $pdo->prepare("UPDATE page_sections SET status = ? WHERE id = ? AND page_id = ?");
        $update->execute([$status, $sec_id, $page_id]);
        set_flash_message('success', 'Section visibility updated.');
        redirect("/user/builder.php?id={$page_id}");
    }
    elseif ($action === 'save_section') {
        $sec_id = (int)($_POST['section_id'] ?? 0);
        // Cleanse settings/content from POST
        $settings = [];
        $content = [];
        foreach ($_POST as $k => $v) {
            if (strpos($k, 's_') === 0) {
                $settings[substr($k, 2)] = escape($v); // basic string
            } elseif (strpos($k, 'c_') === 0) {
                $clean_key = substr($k, 2);
                $clean_val = strip_tags($v, '<b><i><u><strong><em><a><h1><h2><h3><h4><h5><h6><p><br><ul><ol><li>');

                // URL validation
                if (strpos($clean_key, 'url') !== false) {
                    $clean_val = trim($clean_val);
                    if (!empty($clean_val)) {
                        // Allow internal paths (/about), http/https, tel:, mailto:, wa.me
                        if (!preg_match('#^(https?://|/|tel:|mailto:|wa\.me)#i', $clean_val)) {
                            $clean_val = '#invalid-url';
                        }
                    }
                }

                $content[$clean_key] = $clean_val;
            }
        }
        $update = $pdo->prepare("UPDATE page_sections SET settings_json = ?, content_json = ? WHERE id = ? AND page_id = ?");
        $update->execute([json_encode($settings), json_encode($content), $sec_id, $page_id]);
        set_flash_message('success', 'Section saved.');
        redirect("/user/builder.php?id={$page_id}");
    }
    elseif ($action === 'reorder_sections') {
        $order = $_POST['order'] ?? '';
        $ids = explode(',', $order);
        $update = $pdo->prepare("UPDATE page_sections SET sort_order = ? WHERE id = ? AND page_id = ?");
        foreach ($ids as $index => $id) {
            if ($id) {
                $update->execute([$index, (int)$id, $page_id]);
            }
        }
        set_flash_message('success', 'Order saved.');
        redirect("/user/builder.php?id={$page_id}");
    }
}

// Fetch sections
$stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
$stmt->execute([$page_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<style>
.builder-layout {
    display: flex;
    gap: 20px;
    height: calc(100vh - 150px);
    min-height: 600px;
}
.builder-tree {
    width: 300px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
}
.builder-editor {
    flex-grow: 1;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow-y: auto;
    padding: 20px;
}
.section-item {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.section-item:hover { background: #f8f9fa; }
.section-item.active { background: #e9ecef; border-left: 3px solid #0d6efd; }
.section-item-hidden { opacity: 0.6; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="/user/pages.php" class="text-decoration-none text-muted me-2"><i class="bi bi-arrow-left"></i> Back to Pages</a>
        <h2 class="h4 mb-0 d-inline-block">Editing: <?= escape($page['title']) ?></h2>
    </div>
    <div>
        <a href="/public/site.php?website_id=<?= $website_id ?>&preview=<?= $page['slug'] ?>" target="_blank" class="btn btn-outline-info me-2">Preview Page</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="bi bi-plus-lg"></i> Add Section
        </button>
    </div>
</div>

<div class="builder-layout">
    <!-- Tree View -->
    <div class="builder-tree">
        <div class="p-3 border-bottom bg-light fw-bold">Page Sections</div>
        <div class="flex-grow-1 overflow-auto" id="sectionTree">
            <?php if(empty($sections)): ?>
                <div class="p-4 text-center text-muted small">No sections added yet.</div>
            <?php else: ?>
                <?php foreach($sections as $sec): ?>
                <div class="section-item <?= $sec['status'] === 'hidden' ? 'section-item-hidden' : '' ?>" onclick="editSection(<?= $sec['id'] ?>)">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-grip-vertical text-muted me-2 cursor-move handle"></i>
                        <span class="fw-semibold"><?= escape(ucwords(str_replace('_', ' ', $sec['section_type']))) ?></span>
                    </div>
                    <div>
                        <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_section">
                            <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                            <input type="hidden" name="status" value="<?= $sec['status'] === 'visible' ? 'hidden' : 'visible' ?>">
                            <button type="submit" class="btn btn-sm btn-link text-muted p-0 me-2" title="Toggle Visibility">
                                <i class="bi bi-eye<?= $sec['status'] === 'hidden' ? '-slash' : '' ?>"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="p-2 border-top text-center">
            <form method="POST" action="" id="reorderForm">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="reorder_sections">
                <input type="hidden" name="order" id="sectionOrderInput">
                <button type="button" class="btn btn-sm btn-light w-100 text-muted" onclick="saveOrder()">Save Order</button>
            </form>
        </div>
    </div>

    <!-- Editor View -->
    <div class="builder-editor">
        <div id="editorEmpty" class="text-center text-muted py-5">
            <i class="bi bi-cursor fs-1 mb-3 d-block"></i>
            <p>Select a section from the left to edit its content.</p>
        </div>

        <?php foreach($sections as $sec):
            $c = json_decode($sec['content_json'], true) ?: [];
            $s = json_decode($sec['settings_json'], true) ?: [];
        ?>
        <div id="editor-<?= $sec['id'] ?>" class="d-none section-editor-panel">
            <div class="d-flex justify-content-between mb-4">
                <h4 class="mb-0">Edit <?= escape(ucwords(str_replace('_', ' ', $sec['section_type']))) ?></h4>
                <div class="d-flex gap-2">
                    <form method="POST" action="">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="duplicate_section">
                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Duplicate</button>
                    </form>
                    <form method="POST" action="" onsubmit="return confirm('Remove this section?');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_section">
                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="save_section">
                <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">

                <h5 class="fw-bold mb-3 border-bottom pb-2">Content</h5>
                <?php if ($sec['section_type'] === 'hero'): ?>
                    <div class="mb-3">
                        <label class="form-label">Heading</label>
                        <input type="text" name="c_heading" class="form-control" value="<?= escape($c['heading'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subheading / Text</label>
                        <textarea name="c_text" class="form-control" rows="3"><?= escape($c['text'] ?? '') ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Button Text</label>
                            <input type="text" name="c_btn_text" class="form-control" value="<?= escape($c['btn_text'] ?? '') ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Button URL</label>
                            <input type="text" name="c_btn_url" class="form-control" value="<?= escape($c['btn_url'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="text" name="c_image" class="form-control" value="<?= escape($c['image'] ?? '') ?>">
                    </div>

                <?php elseif ($sec['section_type'] === 'cta'): ?>
                    <div class="mb-3">
                        <label class="form-label">Heading</label>
                        <input type="text" name="c_heading" class="form-control" value="<?= escape($c['heading'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="c_text" class="form-control" rows="2"><?= escape($c['text'] ?? '') ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Button Text</label>
                            <input type="text" name="c_btn_text" class="form-control" value="<?= escape($c['btn_text'] ?? '') ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Button Link</label>
                            <input type="text" name="c_btn_url" class="form-control" value="<?= escape($c['btn_url'] ?? '') ?>" placeholder="/contact">
                        </div>
                    </div>

                <?php elseif (in_array($sec['section_type'], ['services', 'gallery', 'reviews', 'faq'])): ?>
                    <div class="alert alert-info border-0">
                        <i class="bi bi-info-circle me-1"></i> This section automatically populates data from your Dashboard.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section Title (Optional)</label>
                        <input type="text" name="c_heading" class="form-control" value="<?= escape($c['heading'] ?? '') ?>">
                    </div>

                <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Heading</label>
                        <input type="text" name="c_heading" class="form-control" value="<?= escape($c['heading'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Text Content</label>
                        <textarea name="c_text" class="form-control" rows="5"><?= escape($c['text'] ?? '') ?></textarea>
                        <small class="text-muted">Basic HTML allowed (b, i, p, br, a, h3)</small>
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold mb-3 mt-4 border-bottom pb-2">Settings</h5>
                <?php if ($sec['section_type'] === 'hero'): ?>
                    <div class="mb-3">
                        <label class="form-label">Alignment</label>
                        <select name="s_alignment" class="form-select">
                            <option value="center" <?= ($s['alignment']??'')==='center'?'selected':'' ?>>Center</option>
                            <option value="left" <?= ($s['alignment']??'')==='left'?'selected':'' ?>>Left</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="mb-3 text-muted small">No specific settings for this section.</div>
                <?php endif; ?>

                <hr>
                <button type="submit" class="btn btn-primary px-4">Save Content</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Add Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <?php if(empty($supported_sections)): ?>
                    <div class="alert alert-warning">This template does not explicitly support any dynamic sections.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach($supported_sections as $sec_type => $is_supported):
                            if(!$is_supported) continue;
                        ?>
                        <div class="col-md-4">
                            <form method="POST" action="" class="h-100">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="add_section">
                                <input type="hidden" name="section_type" value="<?= $sec_type ?>">
                                <button type="submit" class="card border-0 shadow-sm w-100 h-100 text-start text-dark text-decoration-none" style="cursor:pointer; transition: transform 0.2s;">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold mb-1"><?= escape(ucwords(str_replace('_', ' ', $sec_type))) ?></h6>
                                        <small class="text-muted">Add a <?= escape(str_replace('_', ' ', $sec_type)) ?> block.</small>
                                    </div>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editSection(id) {
    // Hide empty
    document.getElementById('editorEmpty').classList.add('d-none');
    // Hide all panels
    document.querySelectorAll('.section-editor-panel').forEach(el => el.classList.add('d-none'));
    // Remove active state
    document.querySelectorAll('.section-item').forEach(el => el.classList.remove('active'));

    // Show requested
    document.getElementById('editor-' + id).classList.remove('d-none');
    // Find the item matching this ID and add active (requires event bubbling or matching, doing minimal approach)
    event.currentTarget.classList.add('active');
}

function saveOrder() {
    const items = document.querySelectorAll('.section-item');
    // Since our markup doesn't store data-id directly on the section-item easily in the quick pass,
    // we extract it from the input hidden field inside it
    let order = [];
    items.forEach(item => {
        let input = item.querySelector('input[name="section_id"]');
        if (input) order.push(input.value);
    });

    document.getElementById('sectionOrderInput').value = order.join(',');
    document.getElementById('reorderForm').submit();
}

// Minimal Drag and Drop for sections
document.addEventListener('DOMContentLoaded', function() {
    const tree = document.getElementById('sectionTree');
    if (tree) {
        let draggedItem = null;
        const items = tree.querySelectorAll('.section-item');

        items.forEach(item => {
            item.setAttribute('draggable', true);

            item.addEventListener('dragstart', function(e) {
                draggedItem = this;
                this.style.opacity = '0.5';
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            item.addEventListener('drop', function(e) {
                e.preventDefault();
                if (this !== draggedItem) {
                    let allItems = Array.from(tree.querySelectorAll('.section-item'));
                    let draggedIdx = allItems.indexOf(draggedItem);
                    let targetIdx = allItems.indexOf(this);

                    if (draggedIdx < targetIdx) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                }
            });

            item.addEventListener('dragend', function() {
                this.style.opacity = '1';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
