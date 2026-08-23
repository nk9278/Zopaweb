<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user'); // Also need to handle 'creator' role potentially later, but basic user access
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website if exists
$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

// Determine placeholders based on whether website is created yet
$website_status = $website ? $website['status'] : 'No Website';
$website_name = $website ? $website['website_name'] : 'Not Set';
$website_slug = $website ? $website['website_slug'] : '';
$current_plan = $website ? ucfirst($website['subscription_status']) : 'N/A';
$template_id = $website ? $website['template_id'] : null;

$template_name = 'Not Selected';
if ($template_id) {
    $t_stmt = $pdo->prepare("SELECT name FROM templates WHERE id = ?");
    $t_stmt->execute([$template_id]);
    $t = $t_stmt->fetch();
    if ($t) $template_name = $t['name'];
}

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="row g-4 mb-5">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">My Website</h4>
                    <?php if ($website): ?>
                        <span class="badge bg-<?= $website['status'] === 'active' ? 'success' : 'warning' ?>">
                            <?= escape(ucfirst($website['status'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($website): ?>
                    <div class="mb-4">
                        <h2 class="fw-bold mb-1"><?= escape($website_name) ?></h2>
                        <a href="http://web.<?= escape($website_slug) ?>.zopaweb.com" target="_blank" class="text-primary text-decoration-none">
                            <i class="bi bi-box-arrow-up-right me-1"></i> web.<?= escape($website_slug) ?>.zopaweb.com
                        </a>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block text-uppercase fw-bold mb-1">Current Template</small>
                                <span class="fs-5"><?= escape($template_name) ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block text-uppercase fw-bold mb-1">Current Plan</small>
                                <span class="fs-5"><?= escape($current_plan) ?> Plan</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-globe empty-state-icon"></i>
                        <h5>You haven't created a website yet.</h5>
                        <p class="text-muted">Get started by setting up your professional makeup artist portfolio.</p>
                        <button class="btn btn-primary" disabled>Create Website (Phase 2)</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white">
            <div class="card-body p-4 d-flex flex-column justify-content-center text-center">
                <i class="bi bi-star-fill display-4 mb-3 text-warning"></i>
                <h4 class="fw-bold mb-3">Upgrade to Pro</h4>
                <p class="mb-4">Get a custom domain, premium templates, and unlimited storage to grow your business.</p>
                <button class="btn btn-light text-primary fw-bold" disabled>View Plans (Phase 2)</button>
            </div>
        </div>
    </div>
</div>

<h4 class="mb-3">Quick Actions</h4>
<div class="row g-3 mb-4">
    <?php
    $actions = [
        ['icon' => 'bi-palette2', 'label' => 'Appearance', 'color' => 'primary', 'link' => '/user/theme.php', 'disabled' => !$website],
        ['icon' => 'bi-eye', 'label' => 'Preview', 'color' => 'info', 'link' => $website ? '/public/site.php?website_id=' . $website['id'] : '#', 'disabled' => !$website, 'target' => '_blank'],
        ['icon' => 'bi-palette', 'label' => 'Templates', 'color' => 'success', 'link' => '/user/templates.php', 'disabled' => false],
        ['icon' => 'bi-file-earmark-text', 'label' => 'Pages', 'color' => 'success', 'link' => '/user/pages.php', 'disabled' => !$website],
        ['icon' => 'bi-images', 'label' => 'Media Library', 'color' => 'warning', 'link' => '/user/media.php', 'disabled' => !$website],
        ['icon' => 'bi-columns-gap', 'label' => 'Gallery', 'color' => 'warning', 'link' => '/user/gallery.php', 'disabled' => !$website],
        ['icon' => 'bi-envelope', 'label' => 'Leads', 'color' => 'danger', 'link' => '/user/leads.php', 'disabled' => !$website],
        ['icon' => 'bi-globe2', 'label' => 'Domain', 'color' => 'secondary', 'link' => '#', 'disabled' => true],
        ['icon' => 'bi-credit-card', 'label' => 'Subscription', 'color' => 'dark', 'link' => '#', 'disabled' => true],
        ['icon' => 'bi-gear', 'label' => 'Settings', 'color' => 'secondary', 'link' => '#', 'disabled' => true],
    ];
    ?>

    <?php foreach ($actions as $action): ?>
        <div class="col-6 col-md-3">
            <?php if ($action['disabled']): ?>
                <button class="card border-0 shadow-sm w-100 h-100 text-center p-4 btn btn-light" disabled style="opacity: 0.7;">
                    <i class="bi <?= $action['icon'] ?> fs-2 text-<?= $action['color'] ?> mb-2"></i>
                    <span class="fw-bold d-block"><?= $action['label'] ?></span>
                </button>
            <?php else: ?>
                <a href="<?= $action['link'] ?>" <?= isset($action['target']) ? 'target="'.$action['target'].'"' : '' ?> class="card border-0 shadow-sm w-100 h-100 text-center p-4 btn btn-light text-decoration-none" style="transition: transform 0.2s; cursor: pointer;">
                    <i class="bi <?= $action['icon'] ?> fs-2 text-<?= $action['color'] ?> mb-2"></i>
                    <span class="fw-bold d-block text-dark"><?= $action['label'] ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-info text-center border-0 shadow-sm">
    <i class="bi bi-info-circle me-2"></i>
    Advanced website building features and actions will be available in future phases.
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
