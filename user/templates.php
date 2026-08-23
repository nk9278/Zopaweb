<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's active website
$stmt = $pdo->prepare("SELECT id, template_id, website_name FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['template_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/templates.php');
    }

    if (!$website) {
        set_flash_message('error', 'You must create a website first.');
        redirect('/user/dashboard.php');
    }

    $template_id = (int)$_POST['template_id'];

    // Verify template is active
    $t_stmt = $pdo->prepare("SELECT id, version FROM templates WHERE id = ? AND status = 'active'");
    $t_stmt->execute([$template_id]);
    $template = $t_stmt->fetch();

    if ($template) {
        // Assign to website
        $update = $pdo->prepare("UPDATE websites SET template_id = ?, template_version = ? WHERE id = ?");
        $update->execute([$template['id'], $template['version'], $website['id']]);

        log_activity($pdo, $user_id, null, 'switched_template', 'website', $website['id']);
        set_flash_message('success', 'Your website design has been successfully updated!');
    } else {
        set_flash_message('error', 'This template is not currently available.');
    }
    redirect('/user/templates.php');
}

// Fetch all active templates for selection
$templates_stmt = $pdo->query("
    SELECT t.id, t.name, t.description, t.version, c.name as category_name
    FROM templates t
    JOIN template_categories c ON t.category_id = c.id
    WHERE t.status = 'active'
    ORDER BY t.id DESC
");
$templates = $templates_stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Template Gallery</h2>
</div>

<?php if (!$website): ?>
    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> You must create a website profile before selecting a template.
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($templates as $tpl): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm template-card <?= ($website && $website['template_id'] == $tpl['id']) ? 'border border-2 border-primary' : '' ?>">
                <!-- Simulated Preview Image (Placeholder) -->
                <div class="bg-light p-5 text-center text-muted" style="height: 200px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                    <i class="bi bi-image fs-1 opacity-25"></i>
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title fw-bold mb-0"><?= escape($tpl['name']) ?></h5>
                        <?php if ($website && $website['template_id'] == $tpl['id']): ?>
                            <span class="badge bg-primary">Active</span>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-light text-dark border mb-3"><?= escape($tpl['category_name']) ?></span>
                    <p class="card-text text-muted small"><?= escape($tpl['description']) ?></p>
                </div>

                <div class="card-footer bg-white border-top-0 pt-0 pb-4 px-4">
                    <div class="d-flex gap-2">
                        <!-- Public preview logic to be built or use generic preview if accessible -->
                        <a href="/admin/template_preview.php?id=<?= $tpl['id'] ?>" class="btn btn-light w-50" target="_blank" onclick="if(!confirm('Previewing requires Admin access in this demo. Proceed?')) return false;">Preview</a>

                        <?php if ($website && $website['template_id'] == $tpl['id']): ?>
                            <button class="btn btn-outline-secondary w-50" disabled>Selected</button>
                        <?php elseif ($website): ?>
                            <button class="btn btn-primary w-50" data-bs-toggle="modal" data-bs-target="#confirmTemplateModal_<?= $tpl['id'] ?>">Use Template</button>
                        <?php else: ?>
                            <button class="btn btn-secondary w-50" disabled>Need Website</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <?php if ($website && $website['template_id'] != $tpl['id']): ?>
        <div class="modal fade" id="confirmTemplateModal_<?= $tpl['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">Switch Template?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to switch your website to the <strong><?= escape($tpl['name']) ?></strong> template.</p>
                        <div class="alert alert-info border-0">
                            <i class="bi bi-info-circle me-2"></i> Your content will remain unchanged. Only the website design will change.
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <form method="POST" action="">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="use_template">
                            <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary px-4">Confirm Change</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endforeach; ?>

    <?php if (count($templates) === 0): ?>
        <div class="col-12">
            <div class="empty-state py-5 border-0 text-center">
                <i class="bi bi-palette empty-state-icon"></i>
                <p class="text-muted mb-0">No active templates are currently available. Check back soon!</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.template-card { transition: transform 0.2s, box-shadow 0.2s; }
.template-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
</style>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
