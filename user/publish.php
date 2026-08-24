<?php
// user/publish.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website strictly verifying ownership
$stmt = $pdo->prepare("SELECT w.*, s.status as sub_status FROM websites w LEFT JOIN subscriptions s ON w.id = s.website_id AND s.status = 'active' WHERE w.user_id = ? AND w.deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];
$is_published = $website['publication_status'] === 'published';

// 1. Evaluate Publishing Checklist Conditions
$checklist = [
    'template' => ['label' => 'Template Selected', 'status' => false],
    'homepage' => ['label' => 'Homepage Created', 'status' => false],
    'business' => ['label' => 'Business Profile Complete', 'status' => false],
    'subscription' => ['label' => 'Active Subscription', 'status' => false]
];

// Template Check
if (!empty($website['template_id'])) {
    $checklist['template']['status'] = true;
}

// Homepage Check
$home_stmt = $pdo->prepare("SELECT id FROM pages WHERE website_id = ? AND is_homepage = 1 AND status = 'published' LIMIT 1");
$home_stmt->execute([$website_id]);
if ($home_stmt->fetch()) {
    $checklist['homepage']['status'] = true;
}

// Business Profile Check
$bp_stmt = $pdo->prepare("SELECT business_name FROM business_profiles WHERE website_id = ? LIMIT 1");
$bp_stmt->execute([$website_id]);
$bp = $bp_stmt->fetch();
if ($bp && !empty($bp['business_name'])) {
    $checklist['business']['status'] = true;
}

// Subscription Check
if ($website['sub_status'] === 'active') {
    $checklist['subscription']['status'] = true;
}

// Can Publish Flag
$can_publish = true;
foreach ($checklist as $item) {
    if (!$item['status']) {
        $can_publish = false;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/publish.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'publish') {
        if (!$can_publish) {
            set_flash_message('error', 'Cannot publish. Please complete all required checklist items first.');
            redirect('/user/publish.php');
        }

        $update = $pdo->prepare("UPDATE websites SET publication_status = 'published' WHERE id = ?");
        $update->execute([$website_id]);
        set_flash_message('success', 'Congratulations! Your website is now live.');

    } elseif ($action === 'unpublish') {
        $update = $pdo->prepare("UPDATE websites SET publication_status = 'draft' WHERE id = ?");
        $update->execute([$website_id]);
        set_flash_message('success', 'Your website has been unpublished and is now offline.');
    }

    redirect('/user/publish.php');
}

$page_title = "Publish Website";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Publish Website</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 text-center">

                <?php if ($is_published): ?>
                    <i class="bi bi-globe2 display-1 text-success mb-3"></i>
                    <h2 class="fw-bold mb-3">Your website is live!</h2>
                    <p class="text-muted mb-4">Visitors can now access your website on your domains.</p>

                    <?php
                        $active_domain = $website['website_slug'] . '.' . PRIMARY_PLATFORM_DOMAIN;
                        $d_stmt = $pdo->prepare("SELECT domain_name FROM domains WHERE website_id = ? AND status = 'active' AND domain_type = 'custom' LIMIT 1");
                        $d_stmt->execute([$website_id]);
                        $cd = $d_stmt->fetch();
                        if ($cd) {
                            $active_domain = $cd['domain_name'];
                        }
                    ?>
                    <a href="http://<?= escape($active_domain) ?>" target="_blank" class="btn btn-primary px-4 me-2"><i class="bi bi-box-arrow-up-right me-2"></i>View Live Site</a>

                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to unpublish? Your site will go offline immediately.');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="unpublish">
                        <button type="submit" class="btn btn-outline-danger px-4">Unpublish Site</button>
                    </form>
                <?php else: ?>
                    <i class="bi bi-rocket display-1 text-primary mb-3"></i>
                    <h2 class="fw-bold mb-3">Ready to go live?</h2>

                    <?php if ($can_publish): ?>
                        <p class="text-muted mb-4">All checks have passed. You can launch your website to the public now.</p>
                        <form method="POST">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="publish">
                            <button type="submit" class="btn btn-success btn-lg px-5 py-3 fw-bold">Publish Website</button>
                        </form>
                    <?php else: ?>
                        <p class="text-danger mb-4">You have missing checklist items. Complete them before publishing.</p>
                        <button class="btn btn-secondary btn-lg px-5 py-3 fw-bold disabled">Publishing Blocked</button>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 fw-bold">Publishing Checklist</div>
            <div class="list-group list-group-flush">
                <?php foreach ($checklist as $key => $item): ?>
                    <div class="list-group-item p-3 d-flex align-items-center">
                        <div class="me-3">
                            <?php if ($item['status']): ?>
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            <?php else: ?>
                                <i class="bi bi-circle text-muted fs-4"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold <?= $item['status'] ? 'text-dark' : 'text-muted' ?>"><?= escape($item['label']) ?></h6>
                            <?php if (!$item['status']): ?>
                                <?php if ($key === 'template'): ?>
                                    <a href="/user/templates.php" class="small text-decoration-none">Select a Template</a>
                                <?php elseif ($key === 'homepage'): ?>
                                    <a href="/user/pages.php" class="small text-decoration-none">Create a Homepage</a>
                                <?php elseif ($key === 'business'): ?>
                                    <a href="/user/business.php" class="small text-decoration-none">Update Business Profile</a>
                                <?php elseif ($key === 'subscription'): ?>
                                    <a href="/user/billing.php" class="small text-decoration-none">Activate Subscription</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
