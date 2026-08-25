<?php
// user/media.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/media_processor.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website verifying ownership
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
        redirect('/user/media.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (!empty($_FILES['media_file']['name'])) {
            $result = process_media_upload($pdo, $website_id, $_FILES['media_file']);
            if ($result['success']) {
                set_flash_message('success', 'Media uploaded successfully.');
            } else {
                set_flash_message('error', $result['error']);
            }
        } else {
            set_flash_message('error', 'No file selected.');
        }
    } elseif ($action === 'edit') {
        $media_id = (int)$_POST['media_id'];
        $alt_text = trim($_POST['alt_text'] ?? '');
        $title = trim($_POST['title'] ?? '');

        $update = $pdo->prepare("UPDATE media SET alt_text=?, title=? WHERE id=? AND website_id=?");
        $update->execute([$alt_text, $title, $media_id, $website_id]);
        set_flash_message('success', 'Media metadata updated.');

    } elseif ($action === 'delete') {
        $media_id = (int)$_POST['media_id'];
        $result = delete_media_safely($pdo, $media_id, $website_id);
        if ($result['success']) {
            set_flash_message('success', 'Media deleted successfully.');
        } else {
            set_flash_message('error', $result['error']);
        }
    }

    redirect('/user/media.php');
}

// Pagination logic
$limit = 24;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM media WHERE website_id = ? AND status = 'active'");
$count_stmt->execute([$website_id]);
$total_media = $count_stmt->fetchColumn();
$total_pages = ceil($total_media / $limit);

$stmt = $pdo->prepare("SELECT * FROM media WHERE website_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT ? OFFSET ?");
// PDO binding limits requires explicit integer casting
$stmt->bindValue(1, $website_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$media_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Storage Usage
$quota_stmt = $pdo->prepare("SELECT SUM(file_size) FROM media WHERE website_id = ?");
$quota_stmt->execute([$website_id]);
$used_bytes = (int)$quota_stmt->fetchColumn();
$limit_bytes = 500 * 1048576; // 500 MB hardcoded for now, R12 will tie to billing plan
$usage_percent = min(100, round(($used_bytes / $limit_bytes) * 100));

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$page_title = "Media Library";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Media Library</h1>
    <div>
        <a href="/user/dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-upload me-1"></i>Upload Media
        </button>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3">Storage Usage (<?= formatBytes($used_bytes) ?> / 500 MB)</h6>
        <div class="progress" style="height: 10px;">
            <div class="progress-bar <?= $usage_percent > 90 ? 'bg-danger' : ($usage_percent > 75 ? 'bg-warning' : 'bg-primary') ?>" role="progressbar" style="width: <?= $usage_percent ?>%" aria-valuenow="<?= $usage_percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>
</div>

<div class="row">
    <?php if (empty($media_items)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center">
                <i class="bi bi-images display-4 text-muted mb-3"></i>
                <h5>No media uploaded yet.</h5>
                <p class="text-muted">Upload images and videos to use on your website.</p>
                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload Media</button>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($media_items as $item):
            $is_image = $item['media_type'] === 'image';
            $preview_url = get_media_url($item, 'thumb');
            if (!$preview_url && !$is_image) {
                $preview_url = '/assets/images/video-placeholder.png'; // Need a placeholder in real life
            }
        ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                <div class="card h-100 border-0 shadow-sm overflow-hidden">
                    <div class="ratio ratio-1x1 bg-light position-relative">
                        <?php if ($is_image): ?>
                            <img src="<?= escape($preview_url) ?>" class="object-fit-cover w-100 h-100" alt="<?= escape($item['alt_text'] ?? $item['original_filename']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center w-100 h-100 text-muted">
                                <i class="bi bi-camera-video fs-1"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Badges overlay -->
                        <div class="position-absolute top-0 start-0 w-100 p-2 d-flex justify-content-between pointer-events-none">
                            <span class="badge bg-dark bg-opacity-75"><?= strtoupper(escape($item['file_extension'])) ?></span>
                        </div>
                    </div>

                    <div class="card-footer bg-white p-2">
                        <div class="text-truncate small fw-medium mb-1" title="<?= escape($item['original_filename']) ?>"><?= escape($item['original_filename']) ?></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted" style="font-size: 0.75rem;"><?= formatBytes($item['file_size']) ?></span>
                            <div>
                                <button type="button" class="btn btn-sm text-primary p-0 px-1" data-bs-toggle="modal" data-bs-target="#editMediaModal<?= $item['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this file? This will fail if it is currently being used on your website.');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="media_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm text-danger p-0 px-1"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editMediaModal<?= $item['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="media_id" value="<?= $item['id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Media Properties</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-3">
                                    <?php if ($is_image): ?>
                                        <img src="<?= escape($preview_url) ?>" class="img-thumbnail" style="max-height: 150px;">
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Title (Internal)</label>
                                    <input type="text" name="title" class="form-control" value="<?= escape($item['title'] ?? '') ?>" placeholder="e.g. Bridal Hero Image">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Alt Text</label>
                                    <input type="text" name="alt_text" class="form-control" value="<?= escape($item['alt_text'] ?? '') ?>" placeholder="For SEO and visually impaired users">
                                    <div class="form-text">Describe the image briefly.</div>
                                </div>
                                <hr>
                                <div class="small text-muted">
                                    <div><strong>Original File:</strong> <?= escape($item['original_filename']) ?></div>
                                    <div><strong>Dimensions:</strong> <?= $item['width'] ? $item['width'].'x'.$item['height'] : 'N/A' ?></div>
                                    <div><strong>Size:</strong> <?= formatBytes($item['file_size']) ?></div>
                                    <div><strong>Uploaded:</strong> <?= date('M d, Y', strtotime($item['created_at'])) ?></div>
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="col-12 mt-4">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="upload">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="bi bi-cloud-arrow-up display-1 text-primary mb-3"></i>
                    <div class="mb-4">
                        <label for="mediaFile" class="form-label fw-bold">Select File</label>
                        <input class="form-control" type="file" id="mediaFile" name="media_file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" required>
                    </div>
                    <div class="small text-muted text-start bg-light p-3 rounded">
                        <strong>Supported Formats:</strong> JPG, PNG, WEBP, GIF, MP4, WEBM<br>
                        <strong>Max Image Size:</strong> <?= MAX_IMAGE_SIZE_MB ?> MB<br>
                        <strong>Max Video Size:</strong> <?= MAX_VIDEO_SIZE_MB ?> MB<br>
                        Images are automatically optimized and converted to WebP where possible to improve your website's loading speed.
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Uploading...'; this.classList.add('disabled'); this.form.submit();">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
