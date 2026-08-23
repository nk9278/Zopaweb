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

// Get existing SEO Config
$seo_stmt = $pdo->prepare("SELECT s.*, m.webp_path as og_image_url FROM website_seo s LEFT JOIN media m ON s.og_image_id = m.id WHERE s.website_id = ?");
$seo_stmt->execute([$website_id]);
$seo = $seo_stmt->fetch(PDO::FETCH_ASSOC);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/seo.php');
    }

    $seo_title = escape($_POST['seo_title'] ?? '');
    $seo_desc = escape($_POST['seo_description'] ?? '');
    $business_type = escape($_POST['business_type'] ?? 'LocalBusiness');
    $robots_index = isset($_POST['robots_index']) ? 1 : 0;
    $robots_follow = isset($_POST['robots_follow']) ? 1 : 0;
    $twitter_card = ($_POST['twitter_card'] ?? '') === 'summary' ? 'summary' : 'summary_large_image';
    $og_image_id = !empty($_POST['og_image_id']) ? (int)$_POST['og_image_id'] : null;

    // Verify media belongs to website
    if ($og_image_id) {
        $check = $pdo->prepare("SELECT id FROM media WHERE id = ? AND website_id = ? AND deleted_at IS NULL");
        $check->execute([$og_image_id, $website_id]);
        if (!$check->fetch()) {
            $og_image_id = null; // invalid image
        }
    }

    if ($seo) {
        $update = $pdo->prepare("
            UPDATE website_seo SET
            seo_title = ?, seo_description = ?, og_image_id = ?, twitter_card = ?, robots_index = ?, robots_follow = ?, business_type = ?
            WHERE website_id = ?
        ");
        $update->execute([$seo_title, $seo_desc, $og_image_id, $twitter_card, $robots_index, $robots_follow, $business_type, $website_id]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO website_seo (website_id, seo_title, seo_description, og_image_id, twitter_card, robots_index, robots_follow, business_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$website_id, $seo_title, $seo_desc, $og_image_id, $twitter_card, $robots_index, $robots_follow, $business_type]);
    }

    set_flash_message('success', 'SEO settings updated successfully.');
    redirect('/user/seo.php');
}

// Media Selection for OG Image
$media_stmt = $pdo->prepare("SELECT id, original_name, thumbnail_path, webp_path FROM media WHERE website_id = ? AND media_type = 'image' AND deleted_at IS NULL ORDER BY id DESC LIMIT 50");
$media_stmt->execute([$website_id]);
$available_media = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">SEO & Search Readiness</h2>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST" action="">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save_seo">

            <!-- Basic SEO -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title fw-bold mb-0">Basic SEO</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Website SEO Title</label>
                        <input type="text" name="seo_title" id="seoTitleInput" class="form-control" value="<?= escape($seo['seo_title'] ?? '') ?>" placeholder="e.g. Jyoti Makeup Artist | Bridal Makeup in Lucknow">
                        <div class="form-text d-flex justify-content-between">
                            <span>The default title shown in Google search results.</span>
                            <span id="titleCounter" class="text-muted">0/60</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Website SEO Description</label>
                        <textarea name="seo_description" id="seoDescInput" class="form-control" rows="3" placeholder="A short summary of your business. Keep it engaging and relevant."><?= escape($seo['seo_description'] ?? '') ?></textarea>
                        <div class="form-text d-flex justify-content-between">
                            <span>The description shown below the title in search results.</span>
                            <span id="descCounter" class="text-muted">0/160</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Preview / OG Image -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title fw-bold mb-0">Social Preview (Open Graph)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Social Share Image</label>
                            <div class="input-group mb-2">
                                <input type="hidden" name="og_image_id" id="ogImageId" value="<?= $seo['og_image_id'] ?? '' ?>">
                                <input type="text" class="form-control bg-light" id="ogImageDisplay" value="<?= escape($seo['og_image_url'] ?? 'No image selected') ?>" readonly>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mediaSelectorModal">Browse</button>
                            </div>
                            <div class="form-text">Shown when your website is shared on Facebook, WhatsApp, or Instagram. Recommend 1200x630px.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Twitter Card Layout</label>
                            <select name="twitter_card" class="form-select">
                                <option value="summary_large_image" <?= ($seo['twitter_card'] ?? '') === 'summary_large_image' ? 'selected' : '' ?>>Large Image</option>
                                <option value="summary" <?= ($seo['twitter_card'] ?? '') === 'summary' ? 'selected' : '' ?>>Small Image (Summary)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Search Engine Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title fw-bold mb-0">Search Engine Settings</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Local Business Type</label>
                            <select name="business_type" class="form-select">
                                <option value="LocalBusiness" <?= ($seo['business_type'] ?? '') === 'LocalBusiness' ? 'selected' : '' ?>>General Local Business</option>
                                <option value="HealthAndBeautyBusiness" <?= ($seo['business_type'] ?? '') === 'HealthAndBeautyBusiness' ? 'selected' : '' ?>>Health & Beauty (Makeup Artist)</option>
                                <option value="BeautySalon" <?= ($seo['business_type'] ?? '') === 'BeautySalon' ? 'selected' : '' ?>>Beauty Salon</option>
                            </select>
                            <div class="form-text">Used for Schema.org JSON-LD generation.</div>
                        </div>
                        <div class="col-md-6 pt-md-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="robots_index" id="robotsIndex" value="1" <?= (!isset($seo['robots_index']) || $seo['robots_index']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="robotsIndex">Allow Indexing</label>
                            </div>
                            <div class="form-text mt-0 mb-3">Allow Google to list your published pages.</div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="robots_follow" id="robotsFollow" value="1" <?= (!isset($seo['robots_follow']) || $seo['robots_follow']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="robotsFollow">Allow Following Links</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 text-end">
                    <button type="submit" class="btn btn-primary px-4">Save SEO Settings</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Right Sidebar -->
    <div class="col-lg-4">
        <!-- Google Preview Simulator -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold mb-0">Google Search Preview</h5>
            </div>
            <div class="card-body p-4">
                <div style="font-family: arial, sans-serif; font-size: 14px;">
                    <div style="color: #202124; font-size: 12px; margin-bottom: 2px;">
                        https://web.<?= escape($website['website_slug']) ?>.zopaweb.com/
                    </div>
                    <div id="previewTitle" style="color: #1a0dab; font-size: 20px; font-weight: normal; margin-bottom: 4px; line-height: 1.3; text-decoration: none; cursor: pointer;">
                        <?= escape($seo['seo_title'] ?: 'Your Website Title') ?>
                    </div>
                    <div id="previewDesc" style="color: #4d5156; line-height: 1.58; word-wrap: break-word;">
                        <?= escape($seo['seo_description'] ?: 'Your website description will appear here. It should be compelling and explain what services you offer.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO Checklist -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold mb-0">SEO Checklist</h5>
            </div>
            <div class="card-body p-4">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2"></i> Website Created
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <?php if (!empty($seo['seo_title'])): ?>
                            <i class="bi bi-check-circle-fill text-success me-2"></i> SEO Title Added
                        <?php else: ?>
                            <i class="bi bi-circle text-muted me-2"></i> Add SEO Title
                        <?php endif; ?>
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <?php if (!empty($seo['seo_description'])): ?>
                            <i class="bi bi-check-circle-fill text-success me-2"></i> Description Added
                        <?php else: ?>
                            <i class="bi bi-circle text-muted me-2"></i> Add Description
                        <?php endif; ?>
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <?php if (!empty($seo['og_image_id'])): ?>
                            <i class="bi bi-check-circle-fill text-success me-2"></i> Social Image Set
                        <?php else: ?>
                            <i class="bi bi-circle text-muted me-2"></i> Set Social Image
                        <?php endif; ?>
                    </li>
                    <li class="d-flex align-items-center">
                        <?php if ($website['publication_status'] === 'published'): ?>
                            <i class="bi bi-check-circle-fill text-success me-2"></i> Sitemap Active
                        <?php else: ?>
                            <i class="bi bi-circle text-muted me-2"></i> Publish Website for Sitemap
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Media Selector Modal -->
<div class="modal fade" id="mediaSelectorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Select Social Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="max-height: 60vh; overflow-y: auto;">
                <?php if (empty($available_media)): ?>
                    <div class="alert alert-info">No media available. <a href="/user/media.php" target="_blank">Upload files here.</a></div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach($available_media as $m): ?>
                        <div class="col-4 col-md-3 col-lg-2">
                            <div class="card border-0 shadow-sm h-100" style="cursor: pointer;" onclick="selectMedia(<?= $m['id'] ?>, '<?= escape($m['webp_path']) ?>')">
                                <img src="<?= escape($m['thumbnail_path'] ?: $m['webp_path']) ?>" class="card-img-top" style="height: 100px; object-fit: cover;">
                                <div class="card-body p-2 text-center text-truncate small">
                                    <?= escape($m['original_name']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('seoTitleInput');
    const descInput = document.getElementById('seoDescInput');
    const titlePreview = document.getElementById('previewTitle');
    const descPreview = document.getElementById('previewDesc');
    const titleCounter = document.getElementById('titleCounter');
    const descCounter = document.getElementById('descCounter');

    function updatePreview() {
        let tVal = titleInput.value;
        let dVal = descInput.value;

        titlePreview.textContent = tVal ? tVal : 'Your Website Title';
        descPreview.textContent = dVal ? dVal : 'Your website description will appear here. It should be compelling and explain what services you offer.';

        titleCounter.textContent = `${tVal.length}/60`;
        titleCounter.className = tVal.length > 60 ? 'text-danger' : 'text-muted';

        descCounter.textContent = `${dVal.length}/160`;
        descCounter.className = dVal.length > 160 ? 'text-danger' : 'text-muted';
    }

    titleInput.addEventListener('input', updatePreview);
    descInput.addEventListener('input', updatePreview);
    updatePreview();
});

function selectMedia(id, url) {
    document.getElementById('ogImageId').value = id;
    document.getElementById('ogImageDisplay').value = url;
    bootstrap.Modal.getInstance(document.getElementById('mediaSelectorModal')).hide();
}
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
