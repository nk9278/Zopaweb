<?php
// user/seo.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, website_slug FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];
$website_slug = $website['website_slug'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/seo.php');
    }

    $seo_title = trim($_POST['default_seo_title'] ?? '');
    $meta_desc = trim($_POST['default_meta_description'] ?? '');
    $og_media_id = !empty($_POST['default_og_image_id']) ? (int)$_POST['default_og_image_id'] : null;
    $visibility = isset($_POST['search_engine_visibility']) ? 1 : 0;

    $stmt = $pdo->prepare("SELECT id FROM website_seo WHERE website_id = ?");
    $stmt->execute([$website_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare("UPDATE website_seo SET default_seo_title=?, default_meta_description=?, default_og_image_id=?, search_engine_visibility=? WHERE website_id=?");
        $update->execute([$seo_title, $meta_desc, $og_media_id, $visibility, $website_id]);
    } else {
        $insert = $pdo->prepare("INSERT INTO website_seo (website_id, default_seo_title, default_meta_description, default_og_image_id, search_engine_visibility) VALUES (?, ?, ?, ?, ?)");
        $insert->execute([$website_id, $seo_title, $meta_desc, $og_media_id, $visibility]);
    }

    set_flash_message('success', 'Global SEO Settings saved.');
    redirect('/user/seo.php');
}

$stmt = $pdo->prepare("SELECT * FROM website_seo WHERE website_id = ?");
$stmt->execute([$website_id]);
$seo = $stmt->fetch() ?: [];

// Fetch sample data for Preview
$bp_stmt = $pdo->prepare("SELECT business_name, tagline FROM business_profiles WHERE website_id = ?");
$bp_stmt->execute([$website_id]);
$bp = $bp_stmt->fetch();

$preview_title = $seo['default_seo_title'] ?: ($bp['business_name'] ?? 'Your Website');
$preview_desc = $seo['default_meta_description'] ?: ($bp['tagline'] ?? 'Welcome to our professional website.');
$preview_url = "https://" . $website_slug . ".zopaweb.com/";

$page_title = "Global SEO Settings";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Global SEO Settings</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="POST">
                    <?php csrf_field(); ?>

                    <div class="form-check form-switch mb-4 pb-4 border-bottom">
                        <input class="form-check-input" type="checkbox" name="search_engine_visibility" id="visibilityCheck" <?= ($seo['search_engine_visibility'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="visibilityCheck">Allow search engines to index this website</label>
                        <div class="form-text text-muted">If turned off, search engines like Google will be asked not to show your site in search results.</div>
                    </div>

                    <h5 class="fw-bold mb-3">Default Homepage SEO</h5>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Website SEO Title</label>
                        <input type="text" name="default_seo_title" class="form-control" value="<?= escape($seo['default_seo_title'] ?? '') ?>" placeholder="e.g. Jane Doe | Professional Bridal Makeup">
                        <div class="form-text">Keep it between 50-60 characters for best results.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Default Meta Description</label>
                        <textarea name="default_meta_description" class="form-control" rows="3" placeholder="Briefly describe your business..."><?= escape($seo['default_meta_description'] ?? '') ?></textarea>
                        <div class="form-text">Summarize your page. Recommended length is 150-160 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium">Default Social Share Image (Open Graph)</label>
                        <select name="default_og_image_id" class="form-select">
                            <option value="">-- Use Business Logo/Hero Default --</option>
                            <?php
                            $media_stmt = $pdo->prepare("SELECT id, original_filename FROM media WHERE website_id = ? AND media_type = 'image' AND status = 'active' ORDER BY created_at DESC");
                            $media_stmt->execute([$website_id]);
                            while($m = $media_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = (($seo['default_og_image_id'] ?? null) == $m['id']) ? 'selected' : '';
                                echo '<option value="'.$m['id'].'" '.$selected.'>'.escape($m['original_filename']).'</option>';
                            }
                            ?>
                        </select>
                        <div class="form-text">This image appears when your website is shared on Facebook, WhatsApp, or Twitter.</div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">Save SEO Settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 fw-bold">Search Result Preview</div>
            <div class="card-body bg-light">
                <div class="p-3 bg-white border rounded">
                    <div class="small text-muted mb-1" style="font-size: 13px;"><?= escape($preview_url) ?></div>
                    <div class="text-primary mb-1" style="font-size: 20px; text-decoration: underline; text-decoration-color: transparent; cursor: pointer;">
                        <?= escape($preview_title) ?>
                    </div>
                    <div class="text-muted" style="font-size: 14px; line-height: 1.4;">
                        <?= escape($preview_desc) ?>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0 text-center"><i class="bi bi-info-circle me-1"></i>This is an approximation. Actual search engine display may vary.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
