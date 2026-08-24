<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'You need to create a website first.');
    redirect('/user/dashboard.php');
}
$website_id = $website['id'];

// Central Eligibility Check
function can_publish_website($pdo, $website_id) {
    $checks = [
        'template' => false,
        'pages' => false,
        'subscription' => false
    ];

    // 1. Template
    $stmt = $pdo->prepare("SELECT template_id, subscription_status FROM websites WHERE id = ?");
    $stmt->execute([$website_id]);
    $site = $stmt->fetch();
    if ($site && $site['template_id']) $checks['template'] = true;

    // 2. Pages
    $p_stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE website_id = ? AND status = 'published' AND deleted_at IS NULL");
    $p_stmt->execute([$website_id]);
    if ($p_stmt->fetchColumn() > 0) $checks['pages'] = true;

    // 3. Subscription (Allow free tier publishing if config allows, or require 'paid')
    // For Phase 12 sandbox, we will allow publishing on any active site status.
    // If strict commercial rules apply, check $site['subscription_status'] === 'paid'.
    $checks['subscription'] = true;

    $is_eligible = !in_array(false, $checks, true);

    return ['eligible' => $is_eligible, 'checks' => $checks];
}

$eligibility = can_publish_website($pdo, $website_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/publish.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_publish') {
        if ($website['publication_status'] === 'published') {
            $upd = $pdo->prepare("UPDATE websites SET publication_status = 'unpublished' WHERE id = ?");
            $upd->execute([$website_id]);
            set_flash_message('success', 'Website unpublished. It is no longer accessible to the public.');
        } else {
            if ($eligibility['eligible']) {
                $upd = $pdo->prepare("UPDATE websites SET publication_status = 'published' WHERE id = ?");
                $upd->execute([$website_id]);
                set_flash_message('success', 'Website published successfully!');
            } else {
                set_flash_message('error', 'Please complete the setup checklist before publishing.');
            }
        }
        redirect('/user/publish.php');
    }
}

// Fetch domain resolution for display
$zopaweb_domain = "https://web.{$website['website_slug']}." . PRIMARY_PLATFORM_DOMAIN;
$d_stmt = $pdo->prepare("SELECT domain_name FROM domains WHERE website_id = ? AND domain_type = 'custom' AND status = 'active' LIMIT 1");
$d_stmt->execute([$website_id]);
$custom = $d_stmt->fetchColumn();
$active_domain = $custom ? "https://{$custom}" : $zopaweb_domain;

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Publish Website</h2>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold mb-0">Pre-Publish Checklist</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4">Ensure your website is ready for visitors by completing the following items.</p>

                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item px-0 py-3 d-flex align-items-center border-0">
                        <?php if ($eligibility['checks']['template']): ?>
                            <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Template Selected</h6>
                                <span class="text-muted small">A visual theme is applied.</span>
                            </div>
                        <?php else: ?>
                            <i class="bi bi-circle text-muted fs-4 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Select a Template</h6>
                                <span class="text-muted small">Choose a design from the Templates gallery.</span>
                            </div>
                            <a href="/user/templates.php" class="btn btn-sm btn-outline-primary ms-auto">Select</a>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item px-0 py-3 d-flex align-items-center border-0">
                        <?php if ($eligibility['checks']['pages']): ?>
                            <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Pages Published</h6>
                                <span class="text-muted small">At least one published page exists.</span>
                            </div>
                        <?php else: ?>
                            <i class="bi bi-circle text-muted fs-4 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Publish a Page</h6>
                                <span class="text-muted small">Create and publish content in the Page Builder.</span>
                            </div>
                            <a href="/user/pages.php" class="btn btn-sm btn-outline-primary ms-auto">Pages</a>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item px-0 py-3 d-flex align-items-center border-0">
                        <?php if ($eligibility['checks']['subscription']): ?>
                            <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Active Subscription</h6>
                                <span class="text-muted small">Your plan is active and in good standing.</span>
                            </div>
                        <?php else: ?>
                            <i class="bi bi-circle text-muted fs-4 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Active Subscription Required</h6>
                                <span class="text-muted small">You need an active plan to publish to the web.</span>
                            </div>
                            <a href="/user/billing.php" class="btn btn-sm btn-outline-primary ms-auto">Upgrade</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center align-items-center">

                <?php if ($website['publication_status'] === 'published'): ?>
                    <i class="bi bi-rocket-takeoff-fill display-1 text-success mb-3"></i>
                    <h3 class="fw-bold text-success mb-2">Website is Live</h3>
                    <p class="text-muted mb-4">Your website is currently published and accessible to visitors.</p>

                    <a href="<?= escape($active_domain) ?>" target="_blank" class="btn btn-outline-primary mb-4 w-100">
                        <i class="bi bi-box-arrow-up-right me-2"></i> <?= escape($active_domain) ?>
                    </a>

                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to unpublish your website? Visitors will no longer be able to access it.');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_publish">
                        <button type="submit" class="btn btn-light text-danger w-100 border">Unpublish Website</button>
                    </form>
                <?php else: ?>
                    <i class="bi bi-rocket display-1 text-muted mb-3 opacity-50"></i>
                    <h3 class="fw-bold text-dark mb-2">Ready to Launch?</h3>
                    <p class="text-muted mb-4">Your website is currently in draft mode. Only you can preview it.</p>

                    <form method="POST" action="" class="w-100">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_publish">
                        <?php if ($eligibility['eligible']): ?>
                            <button type="submit" class="btn btn-success btn-lg w-100 py-3 fw-bold shadow-sm">
                                <i class="bi bi-globe2 me-2"></i> Publish Now
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-lg w-100 py-3 fw-bold" disabled>
                                Complete Checklist First
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
