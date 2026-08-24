<?php
// user/social.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/social.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $platform = trim($_POST['platform'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        // Block javascript: URLs
        if (stripos(strtolower($url), 'javascript:') === 0 || stripos(strtolower($url), 'data:') === 0) {
             set_flash_message('error', 'Invalid URL scheme detected.');
             redirect('/user/social.php');
        }

        if (empty($platform) || empty($url)) {
            set_flash_message('error', 'Platform and URL are required.');
        } else {
            if ($action === 'add') {
                // Check if platform already exists
                $check = $pdo->prepare("SELECT id FROM social_links WHERE website_id = ? AND platform = ?");
                $check->execute([$website_id, $platform]);
                if ($check->fetch()) {
                    set_flash_message('error', 'That platform already exists.');
                } else {
                    $insert = $pdo->prepare("INSERT INTO social_links (website_id, platform, url, sort_order, status) VALUES (?, ?, ?, ?, ?)");
                    $insert->execute([$website_id, $platform, $url, $sort_order, $status]);
                    set_flash_message('success', 'Social link added.');
                }
            } else {
                $link_id = (int)$_POST['link_id'];
                $update = $pdo->prepare("UPDATE social_links SET platform=?, url=?, sort_order=?, status=? WHERE id=? AND website_id=?");
                $update->execute([$platform, $url, $sort_order, $status, $link_id, $website_id]);
                set_flash_message('success', 'Social link updated.');
            }
        }
    } elseif ($action === 'delete') {
        $link_id = (int)$_POST['link_id'];
        $delete = $pdo->prepare("DELETE FROM social_links WHERE id=? AND website_id=?");
        $delete->execute([$link_id, $website_id]);
        set_flash_message('success', 'Social link deleted.');
    }

    redirect('/user/social.php');
}

$stmt = $pdo->prepare("SELECT * FROM social_links WHERE website_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$website_id]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

$supported_platforms = ['Instagram', 'Facebook', 'YouTube', 'Pinterest', 'Twitter', 'TikTok', 'LinkedIn'];

$page_title = "Manage Social Links";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Social Links</h1>
    <div>
        <a href="/user/dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLinkModal">
            <i class="bi bi-plus-lg me-1"></i>Add Link
        </button>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <?php if (empty($links)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center bg-light">
                <i class="bi bi-share display-1 text-primary mb-3"></i>
                <h4 class="fw-bold">No social links yet</h4>
                <p class="text-muted mb-4">Connect your social media profiles to grow your audience.</p>
                <div>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addLinkModal">
                        <i class="bi bi-plus-lg me-2"></i>Add Social Link
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Platform</th>
                                    <th>URL</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $lnk): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <i class="bi bi-<?= strtolower(escape($lnk['platform'])) ?> text-primary me-2"></i>
                                        <?= escape($lnk['platform']) ?>
                                    </td>
                                    <td><a href="<?= escape($lnk['url']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 300px;"><?= escape($lnk['url']) ?></a></td>
                                    <td><span class="badge <?= $lnk['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= escape(ucfirst($lnk['status'])) ?></span></td>
                                    <td><?= (int)$lnk['sort_order'] ?></td>
                                    <td class="pe-4 text-end">
                                        <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#editLinkModal<?= $lnk['id'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this social link?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="link_id" value="<?= $lnk['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editLinkModal<?= $lnk['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="link_id" value="<?= $lnk['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Social Link</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-medium">Platform *</label>
                                                        <select name="platform" class="form-select" required>
                                                            <?php foreach ($supported_platforms as $p): ?>
                                                                <option value="<?= $p ?>" <?= strtolower($lnk['platform']) === strtolower($p) ? 'selected' : '' ?>><?= $p ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-medium">Profile URL *</label>
                                                        <input type="url" name="url" class="form-control" required value="<?= escape($lnk['url']) ?>">
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-6 mb-3">
                                                            <label class="form-label fw-medium">Sort Order</label>
                                                            <input type="number" name="sort_order" class="form-control" value="<?= (int)$lnk['sort_order'] ?>">
                                                        </div>
                                                        <div class="col-6 mb-3">
                                                            <label class="form-label fw-medium">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= $lnk['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $lnk['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addLinkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add Social Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Platform *</label>
                        <select name="platform" class="form-select" required>
                            <?php foreach ($supported_platforms as $p): ?>
                                <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Profile URL *</label>
                        <input type="url" name="url" class="form-control" required placeholder="https://...">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-medium">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
