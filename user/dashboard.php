<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website if exists
$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

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

$page_title = "My Dashboard";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    <?php if ($website && $website['publication_status'] === 'published'): ?>
        <a href="http://<?= escape($website_slug) ?>.<?= PRIMARY_PLATFORM_DOMAIN ?>" class="btn btn-outline-primary" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>Visit Live Site</a>
    <?php endif; ?>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Website Status Card -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body p-4">
                <?php if ($website): ?>
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h2 class="fw-bold mb-1"><?= escape($website_name) ?></h2>
                            <p class="text-muted mb-0">web.<?= escape($website_slug) ?>.zopaweb.com</p>
                        </div>
                        <span class="badge <?= $website_status === 'active' ? 'bg-success' : 'bg-secondary' ?> px-3 py-2 rounded-pill"><?= escape(ucfirst($website_status)) ?></span>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3 text-center h-100">
                                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Status</span>
                                <span class="fs-5 fw-medium"><?= escape(ucfirst($website['publication_status'])) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3 text-center h-100">
                                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Plan</span>
                                <span class="fs-5 fw-medium text-primary"><?= escape($current_plan) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3 text-center h-100">
                                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Template</span>
                                <span class="fs-5 fw-medium"><?= escape($template_name) ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="display-1 text-muted mb-3"><i class="bi bi-laptop"></i></div>
                        <h5>You haven't created a website yet.</h5>
                        <p class="text-muted">Get started by setting up your professional makeup artist portfolio.</p>
                        <button class="btn btn-primary" disabled>Create Website</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($website): ?>
        <!-- Management Tools -->
        <h4 class="fw-bold mb-3 mt-5">Manage Website</h4>
        <div class="row g-3">
            <?php
            $tools = [
                ['icon' => 'bi-layout-text-window', 'label' => 'Pages', 'color' => 'primary', 'link' => '/user/pages.php'],
                ['icon' => 'bi-shop', 'label' => 'Business Profile', 'color' => 'success', 'link' => '/user/business.php'],
                ['icon' => 'bi-stars', 'label' => 'Services', 'color' => 'info', 'link' => '/user/services.php'],
                ['icon' => 'bi-images', 'label' => 'Gallery', 'color' => 'warning', 'link' => '/user/gallery.php'],
                ['icon' => 'bi-chat-quote', 'label' => 'Reviews', 'color' => 'danger', 'link' => '/user/reviews.php'],
                ['icon' => 'bi-palette', 'label' => 'Theme', 'color' => 'secondary', 'link' => '/user/theme.php'],
                ['icon' => 'bi-share', 'label' => 'Social Links', 'color' => 'primary', 'link' => '/user/social.php'],
                ['icon' => 'bi-globe', 'label' => 'Domain', 'color' => 'dark', 'link' => '#', 'disabled' => true],
            ];

            foreach ($tools as $tool):
            ?>
            <div class="col-md-4 col-sm-6">
                <a href="<?= isset($tool['disabled']) && $tool['disabled'] ? '#' : $tool['link'] ?>" class="card h-100 text-decoration-none border-0 shadow-sm hover-lift <?= isset($tool['disabled']) && $tool['disabled'] ? 'opacity-50' : '' ?>">
                    <div class="card-body text-center p-4">
                        <div class="text-<?= $tool['color'] ?> fs-1 mb-2">
                            <i class="bi <?= $tool['icon'] ?>"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-0"><?= $tool['label'] ?></h6>
                        <?php if (isset($tool['disabled']) && $tool['disabled']): ?>
                            <span class="badge bg-secondary mt-2">Coming Soon</span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold">
                Quick Actions
            </div>
            <div class="list-group list-group-flush">
                <a href="<?= $website ? '/user/templates.php' : '#' ?>" class="list-group-item list-group-item-action py-3 <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-palette text-muted me-2"></i> Change Template
                </a>
                <a href="#" class="list-group-item list-group-item-action py-3 disabled">
                    <i class="bi bi-envelope-paper text-muted me-2"></i> Lead Inbox
                </a>
                <a href="#" class="list-group-item list-group-item-action py-3 disabled">
                    <i class="bi bi-graph-up text-muted me-2"></i> Analytics
                </a>
            </div>
        </div>

        <!-- Upgrade Prompt -->
        <div class="card border-0 shadow-sm bg-primary text-white text-center p-4">
            <div class="card-body">
                <i class="bi bi-rocket-takeoff display-4 mb-3"></i>
                <h4 class="fw-bold mb-3">Upgrade to Pro</h4>
                <p class="mb-4">Get a custom domain, premium templates, and unlimited storage to grow your business.</p>
                <button class="btn btn-light text-primary fw-bold" disabled>View Plans</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
