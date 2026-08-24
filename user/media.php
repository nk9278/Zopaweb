<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/media_engine.php';

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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only check CSRF if it's a standard form post (not ajax, though standard form posts still apply here)
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/media.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upload_media') {
        if (!empty($_FILES['files']['name'][0])) {
            $success_count = 0;
            $error_messages = [];

            foreach ($_FILES['files']['name'] as $key => $name) {
                $file = [
                    'name' => $_FILES['files']['name'][$key],
                    'type' => $_FILES['files']['type'][$key],
                    'tmp_name' => $_FILES['files']['tmp_name'][$key],
                    'error' => $_FILES['files']['error'][$key],
                    'size' => $_FILES['files']['size'][$key]
                ];

                $result = handle_media_upload($pdo, $file, $website_id, $user_id);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_messages[] = htmlspecialchars($name) . ': ' . $result['error'];
                }
            }

            if ($success_count > 0) {
                log_activity($pdo, $user_id, null, 'media_uploaded', 'website', $website_id);
                set_flash_message('success', "Successfully uploaded $success_count files.");
            }
            if (!empty($error_messages)) {
                set_flash_message('error', implode('<br>', $error_messages));
            }
        } else {
            set_flash_message('error', 'No files selected.');
        }
        redirect('/user/media.php');
    }
    elseif ($action === 'delete_media') {
        $media_id = (int)($_POST['media_id'] ?? 0);

        // Verify ownership
        $check = $pdo->prepare("SELECT id, webp_path FROM media WHERE id = ? AND website_id = ? AND user_id = ? AND deleted_at IS NULL");
        $check->execute([$media_id, $website_id, $user_id]);
        $media_record = $check->fetch(PDO::FETCH_ASSOC);

        if ($media_record) {
            $in_use = false;

            // Check galleries
            $gal_check = $pdo->prepare("SELECT id FROM galleries WHERE media_id = ? AND website_id = ? AND deleted_at IS NULL");
            $gal_check->execute([$media_id, $website_id]);
            if ($gal_check->fetch()) {
                $in_use = true;
            }

            // Check page sections using json search for the path string
            if (!$in_use && $media_record['webp_path']) {
                $sec_check = $pdo->prepare("SELECT id FROM page_sections WHERE page_id IN (SELECT id FROM pages WHERE website_id = ?) AND content_json LIKE ? AND deleted_at IS NULL LIMIT 1");
                $sec_check->execute([$website_id, '%' . $media_record['webp_path'] . '%']);
                if ($sec_check->fetch()) {
                    $in_use = true;
                }
            }

            if ($in_use) {
                set_flash_message('error', 'Cannot delete this media because it is currently used in a Gallery or Page Section. Remove the references first.');
            } else {
                $delete = $pdo->prepare("UPDATE media SET deleted_at = NOW() WHERE id = ?");
                $delete->execute([$media_id]);
                log_activity($pdo, $user_id, null, 'media_deleted', 'media', $media_id);
                set_flash_message('success', 'Media moved to trash.');
            }
        } else {
            set_flash_message('error', 'Media not found or permission denied.');
        }
        redirect('/user/media.php');
    }
    elseif ($action === 'edit_media') {
        $media_id = (int)($_POST['media_id'] ?? 0);
        $alt_text = escape($_POST['alt_text'] ?? '');
        $caption = escape($_POST['caption'] ?? '');

        $update = $pdo->prepare("UPDATE media SET alt_text = ?, caption = ? WHERE id = ? AND website_id = ? AND user_id = ?");
        $update->execute([$alt_text, $caption, $media_id, $website_id, $user_id]);
        set_flash_message('success', 'Media details updated.');
        redirect('/user/media.php');
    }
}

// Fetch Media
$page = (int)($_GET['page'] ?? 1);
$per_page = 24;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT * FROM media WHERE website_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $website_id, PDO::PARAM_INT);
$stmt->bindValue(2, $per_page, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$media_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_stmt = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(original_size) as storage FROM media WHERE website_id = ? AND deleted_at IS NULL");
$total_stmt->execute([$website_id]);
$stats = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total_items = $stats['cnt'] ?: 0;
$total_storage = $stats['storage'] ?: 0;
$total_pages = ceil($total_items / $per_page);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<style>
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
}
.media-item {
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    position: relative;
    aspect-ratio: 1;
}
.media-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: #f8f9fa;
}
.media-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.2s;
    color: #fff;
    padding: 10px;
}
.media-item:hover .media-overlay {
    opacity: 1;
}
.upload-zone {
    border: 2px dashed #ccc;
    border-radius: 10px;
    padding: 40px;
    text-align: center;
    background: #f8f9fa;
    transition: border 0.3s, background 0.3s;
    cursor: pointer;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #0d6efd;
    background: #f1f6ff;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 mb-0 text-gray-800">Media Library</h2>
        <small class="text-muted">Storage Used: <?= round($total_storage / (1024 * 1024), 2) ?> MB</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadCollapse">
        <i class="bi bi-cloud-arrow-up me-1"></i> Upload Media
    </button>
</div>

<div class="collapse mb-4" id="uploadCollapse">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="upload_media">
                <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <i class="bi bi-images display-4 text-muted mb-3 d-block"></i>
                    <h5>Drag & Drop images here</h5>
                    <p class="text-muted small mb-0">or click to browse files (JPEG, PNG, WebP up to 10MB)</p>
                    <input type="file" name="files[]" id="fileInput" class="d-none" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" onchange="document.getElementById('uploadForm').submit()">
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (empty($media_items)): ?>
    <div class="empty-state p-5 text-center mt-4">
        <i class="bi bi-folder2-open empty-state-icon fs-1 text-muted mb-3 d-block"></i>
        <h5>Your media library is empty.</h5>
        <p class="text-muted">Upload photos and videos to start building your website.</p>
    </div>
<?php else: ?>
    <div class="media-grid mb-4">
        <?php foreach ($media_items as $item): ?>
            <div class="media-item">
                <?php if ($item['media_type'] === 'image'): ?>
                    <img src="<?= escape($item['thumbnail_path'] ?: $item['webp_path']) ?>" class="media-thumb" alt="<?= escape($item['alt_text']) ?>" loading="lazy">
                <?php else: ?>
                    <!-- Video placeholder -->
                    <div class="media-thumb d-flex align-items-center justify-content-center bg-dark text-white">
                        <i class="bi bi-play-circle display-4"></i>
                    </div>
                <?php endif; ?>

                <div class="media-overlay text-center">
                    <span class="small fw-bold mb-2 text-truncate w-100 px-2" title="<?= escape($item['original_name']) ?>"><?= escape($item['original_name']) ?></span>
                    <span class="badge bg-light text-dark mb-3"><?= strtoupper($item['file_extension']) ?> • <?= round($item['original_size']/1024) ?> KB</span>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" onclick='openMediaModal(<?= json_encode($item) ?>)'>Details</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center border-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- Media Details Modal -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-truncate" id="modalTitle">Media Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <div class="row">
                    <div class="col-md-7 mb-3 mb-md-0">
                        <div class="bg-light rounded p-2 text-center h-100 d-flex align-items-center justify-content-center">
                            <img id="modalPreview" src="" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">
                            <div id="modalVideo" class="d-none w-100">
                                <video controls class="w-100 rounded" style="max-height: 400px;">
                                    <source id="modalVideoSrc" src="" type="">
                                </video>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <p class="text-muted small mb-4">Uploaded: <span id="modalDate"></span><br>Dimensions: <span id="modalDims"></span></p>

                        <form method="POST" action="">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="edit_media">
                            <input type="hidden" name="media_id" id="modalMediaId">

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Alt Text</label>
                                <input type="text" name="alt_text" id="modalAlt" class="form-control form-control-sm" placeholder="Describe the image for accessibility">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Caption</label>
                                <textarea name="caption" id="modalCaption" class="form-control form-control-sm" rows="3" placeholder="Optional caption for galleries"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">Save Details</button>
                        </form>

                        <hr>

                        <form method="POST" action="" onsubmit="return confirm('Delete this media? Note: If it is currently used on your website, it may cause missing images.');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_media">
                            <input type="hidden" name="media_id" id="modalMediaIdDel">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete Permanently</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openMediaModal(item) {
    document.getElementById('modalTitle').textContent = item.original_name;
    document.getElementById('modalDate').textContent = item.created_at;
    document.getElementById('modalDims').textContent = item.width && item.height ? `${item.width} x ${item.height}` : 'N/A';

    document.getElementById('modalMediaId').value = item.id;
    document.getElementById('modalMediaIdDel').value = item.id;
    document.getElementById('modalAlt').value = item.alt_text || '';
    document.getElementById('modalCaption').value = item.caption || '';

    const previewImg = document.getElementById('modalPreview');
    const previewVid = document.getElementById('modalVideo');
    const vidSrc = document.getElementById('modalVideoSrc');

    if (item.media_type === 'image') {
        previewImg.src = item.webp_path || item.thumbnail_path;
        previewImg.classList.remove('d-none');
        previewVid.classList.add('d-none');
    } else {
        vidSrc.src = item.webp_path; // Original for video
        vidSrc.type = item.mime_type;
        previewVid.querySelector('video').load();
        previewImg.classList.add('d-none');
        previewVid.classList.remove('d-none');
    }

    new bootstrap.Modal(document.getElementById('mediaModal')).show();
}

// Drag & Drop effects
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});
dropZone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
});
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length) {
        document.getElementById('fileInput').files = files;
        document.getElementById('uploadForm').submit();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
