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
$website = $stmt->fetch(PDO::FETCH_ASSOC);

$website_status = $website ? $website['status'] : 'No Website';
$website_name = $website && !empty($website['website_name']) ? $website['website_name'] : 'My Website';
$website_slug = $website ? $website['website_slug'] : '';
$current_plan = $website ? ucfirst($website['subscription_status']) : 'N/A';
$template_id = $website ? $website['template_id'] : null;

$onboarding = get_onboarding_status($pdo, $website ? $website['id'] : 0, $website ?: []);

// Fetch simple Leads summary (R5)
$leads_summary = ['new' => 0, 'contacted' => 0];
if ($website) {
    $lead_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM leads WHERE website_id = ? GROUP BY status");
    $lead_stmt->execute([$website['id']]);
    while ($row = $lead_stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['status'] === 'new') $leads_summary['new'] = $row['count'];
        if ($row['status'] === 'contacted') $leads_summary['contacted'] = $row['count'];
    }
}

// Fetch active domain if custom domain mapped
$active_domain = $website_slug . '.' . PRIMARY_PLATFORM_DOMAIN;
if ($website) {
    $d_stmt = $pdo->prepare("SELECT domain_name FROM domains WHERE website_id = ? AND status = 'active' AND domain_type = 'custom' LIMIT 1");
    $d_stmt->execute([$website['id']]);
    $cd = $d_stmt->fetch();
    if ($cd) {
        $active_domain = $cd['domain_name'];
    }
}

$page_title = "My Dashboard";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Overview</h1>
    <?php if ($website && $website['publication_status'] === 'published'): ?>
        <a href="http://<?= escape($active_domain) ?>" class="btn btn-outline-primary" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>Visit Live Site</a>
    <?php endif; ?>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <?php if ($website): ?>
            <!-- Website Status Card -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h2 class="fw-bold mb-1"><?= escape($website_name) ?></h2>
                            <p class="text-muted mb-0"><?= escape($active_domain) ?></p>
                        </div>
                        <span class="badge <?= $website['publication_status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?> px-3 py-2 rounded-pill"><?= escape(ucfirst($website['publication_status'])) ?></span>
                    </div>

                    <?php if (!$onboarding['is_complete']): ?>
                        <!-- Onboarding Progress -->
                        <div class="bg-light rounded p-4 mb-4 border border-info border-start border-4">
                            <h5 class="fw-bold text-dark mb-3">Website Setup Checklist</h5>

                            <!-- Progress Bar -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted fw-bold">Progress</span>
                                <span class="small text-primary fw-bold"><?= $onboarding['progress'] ?>%</span>
                            </div>
                            <div class="progress mb-4" style="height: 10px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $onboarding['progress'] ?>%" aria-valuenow="<?= $onboarding['progress'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <!-- Checklist -->
                            <ul class="list-group list-group-flush bg-transparent mb-4">
                                <?php foreach ($onboarding['steps'] as $key => $step): ?>
                                    <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center border-bottom-0 py-2">
                                        <div>
                                            <?php if ($step['completed']): ?>
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                <span class="text-muted text-decoration-line-through"><?= escape($step['label']) ?></span>
                                            <?php else: ?>
                                                <i class="bi bi-circle text-muted me-2"></i>
                                                <span class="text-dark fw-medium"><?= escape($step['label']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$step['completed']): ?>
                                            <a href="<?= $step['url'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">Do this</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($onboarding['next_action']): ?>
                                <div class="text-center p-3 bg-white rounded border">
                                    <span class="text-muted small fw-bold d-block mb-2 text-uppercase">Next Step</span>
                                    <a href="<?= $onboarding['next_action']['url'] ?>" class="btn btn-primary px-4 py-2 fw-bold"><?= escape($onboarding['next_action']['label']) ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-2">
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
                                <span class="d-block text-muted small fw-bold text-uppercase mb-1">New Leads</span>
                                <span class="fs-5 fw-medium text-success"><?= $leads_summary['new'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Management Tools -->
            <h4 class="fw-bold mb-3 mt-4">Manage Content</h4>
            <div class="row g-3">
                <?php
                $tools = [
                    ['icon' => 'bi-shop', 'label' => 'Business Info', 'color' => 'success', 'link' => '/user/business.php'],
                    ['icon' => 'bi-layout-text-window', 'label' => 'Pages', 'color' => 'primary', 'link' => '/user/pages.php'],
                    ['icon' => 'bi-stars', 'label' => 'Services', 'color' => 'info', 'link' => '/user/services.php'],
                    ['icon' => 'bi-images', 'label' => 'Gallery', 'color' => 'warning', 'link' => '/user/gallery.php'],
                    ['icon' => 'bi-chat-quote', 'label' => 'Reviews', 'color' => 'danger', 'link' => '/user/reviews.php'],
                    ['icon' => 'bi-envelope-paper', 'label' => 'Inquiries', 'color' => 'primary', 'link' => '/user/leads.php'],
                ];

                foreach ($tools as $tool):
                ?>
                <div class="col-lg-4 col-md-6 col-sm-6">
                    <a href="<?= $tool['link'] ?>" class="card h-100 text-decoration-none border-0 shadow-sm hover-lift text-center p-3">
                        <div class="card-body p-2">
                            <div class="text-<?= $tool['color'] ?> fs-2 mb-2">
                                <i class="bi <?= $tool['icon'] ?>"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-0"><?= $tool['label'] ?></h6>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center py-5">
                    <div class="display-1 text-primary mb-3"><i class="bi bi-laptop"></i></div>
                    <h4 class="fw-bold">Welcome to ZopaWeb</h4>
                    <p class="text-muted mb-4">Get started by creating your professional portfolio website.</p>
                    <!-- Since website creation logic is currently stubbed/handled during initial dev, providing a generic link or disabling -->
                    <a href="/user/business.php" class="btn btn-primary px-4 py-2 fw-bold">Create My Website</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold border-bottom-0">
                Setup & Settings
            </div>
            <div class="list-group list-group-flush rounded-bottom">
                <a href="<?= $website ? '/user/templates.php' : '#' ?>" class="list-group-item list-group-item-action py-3 <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-palette text-muted me-3"></i> Design & Template
                </a>
                <a href="<?= $website ? '/user/theme.php' : '#' ?>" class="list-group-item list-group-item-action py-3 <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-paint-bucket text-muted me-3"></i> Colors & Fonts
                </a>
                <a href="<?= $website ? '/user/seo.php' : '#' ?>" class="list-group-item list-group-item-action py-3 <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-google text-muted me-3"></i> Google SEO
                </a>
                <a href="<?= $website ? '/user/domains.php' : '#' ?>" class="list-group-item list-group-item-action py-3 <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-globe text-muted me-3"></i> Domain Name
                </a>
                <a href="<?= $website ? '/user/billing.php' : '#' ?>" class="list-group-item list-group-item-action py-3 <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-credit-card text-muted me-3"></i> Plan & Billing
                </a>
                <a href="<?= $website ? '/user/publish.php' : '#' ?>" class="list-group-item list-group-item-action py-3 text-success fw-bold <?= !$website ? 'disabled' : '' ?>">
                    <i class="bi bi-rocket-takeoff me-3"></i> Publish Settings
                </a>
            </div>
        </div>

        <!-- Leads summary box -->
        <?php if ($website && ($leads_summary['new'] > 0 || $leads_summary['contacted'] > 0)): ?>
        <div class="card border-0 shadow-sm bg-light text-dark p-3 mb-4">
            <div class="d-flex align-items-center">
                <div class="bg-success text-white rounded p-3 me-3">
                    <i class="bi bi-whatsapp fs-4"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold">Recent Inquiries</h6>
                    <p class="mb-0 small text-muted">You have <strong><?= $leads_summary['new'] ?></strong> new messages.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
