<?php
// user/gallery.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website strictly verifying ownership
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
        redirect('/user/gallery.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $media_id_post = !empty($_POST['media_id']) ? (int)$_POST['media_id'] : null;
        $caption = trim($_POST['caption'] ?? '');
        $alt_text = trim($_POST['alt_text'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($media_id_post)) {
            set_flash_message('error', 'Image URL is required.');
        } else {
            if ($action === 'add') {
                $insert = $pdo->prepare("INSERT INTO gallery_items (website_id, media_id, caption, alt_text, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->execute([$website_id, $media_id_post, $caption, $alt_text, $sort_order, $status]);
                set_flash_message('success', 'Image added to gallery.');
            } else {
                $item_id = (int)$_POST['item_id'];
                $update = $pdo->prepare("UPDATE gallery_items SET media_id=?, caption=?, alt_text=?, sort_order=?, status=? WHERE id=? AND website_id=?");
                $update->execute([$media_id_post, $caption, $alt_text, $sort_order, $status, $item_id, $website_id]);
                set_flash_message('success', 'Gallery item updated.');
            }
        }
    } elseif ($action === 'delete') {
        $item_id = (int)$_POST['item_id'];
        $delete = $pdo->prepare("DELETE FROM gallery_items WHERE id=? AND website_id=?");
        $delete->execute([$item_id, $website_id]);
        set_flash_message('success', 'Image removed from gallery.');
    }

    redirect('/user/gallery.php');
}

// Fetch current gallery
$stmt = $pdo->prepare("SELECT g.*, m.storage_path, m.file_extension, m.has_thumbnail, m.original_filename FROM gallery_items g LEFT JOIN media m ON g.media_id = m.id WHERE g.website_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$website_id]);
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Gallery";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Gallery</h1>
    <div>
        <a href="/user/dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGalleryModal">
            <i class="bi bi-plus-lg me-1"></i>Add Image
        </button>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <?php if (empty($gallery)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center bg-light">
                <i class="bi bi-images display-1 text-primary mb-3"></i>
                <h4 class="fw-bold">No images in your gallery</h4>
                <p class="text-muted mb-4">Upload your first portfolio image to showcase your work.</p>
                <div>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addGalleryModal">
                        <i class="bi bi-plus-lg me-2"></i>Add First Image
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($gallery as $item): ?>
            <div class="col-md-4 col-xl-3 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="<?= $item['media_id'] ? '/public/api/media.php?id='.$item['media_id'].'&size=thumb' : escape($item['image_url']) ?>" class="card-img-top" alt="<?= escape($item['alt_text']) ?>" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge <?= $item['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= escape(ucfirst($item['status'])) ?></span>
                            <span class="text-muted small">Order: <?= (int)$item['sort_order'] ?></span>
                        </div>
                        <?php if ($item['caption']): ?>
                            <p class="card-text small mt-2"><?= escape($item['caption']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 text-end">
                        <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#editGalleryModal<?= $item['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this image from gallery?');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editGalleryModal<?= $item['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Gallery Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                                <div class="mb-3">
            <label class="form-label fw-medium">Select Media Image *</label>
            <select name="media_id" class="form-select" required>
                <option value="">-- Select Media --</option>
                <?php
                $media_stmt = $pdo->prepare("SELECT id, original_filename FROM media WHERE website_id = ? AND media_type = 'image' AND status = 'active' ORDER BY created_at DESC");
                $media_stmt->execute([$website_id]);
                while($m = $media_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $selected = ($item['media_id'] == $m['id']) ? 'selected' : '';
                    echo '<option value="'.$m['id'].'" '.$selected.'>'.escape($m['original_filename']).'</option>';
                }
                ?>
            </select>
            <div class="form-text">Select an image uploaded to your <a href="/user/media.php" target="_blank">Media Library</a>.</div>
        </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Caption</label>
                        <input type="text" name="caption" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Alt Text</label>
                        <input type="text" name="alt_text" class="form-control" placeholder="For SEO and accessibility">
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
                    <button type="submit" class="btn btn-primary">Add Image</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
