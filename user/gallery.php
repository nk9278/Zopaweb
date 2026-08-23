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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/gallery.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_gallery') {
        $media_id = (int)($_POST['media_id'] ?? 0);

        // Verify media ownership
        $check = $pdo->prepare("SELECT id FROM media WHERE id = ? AND website_id = ? AND deleted_at IS NULL");
        $check->execute([$media_id, $website_id]);
        if ($check->fetch()) {
            $max_order = $pdo->query("SELECT MAX(sort_order) FROM galleries WHERE website_id = {$website_id}")->fetchColumn() ?: 0;
            $insert = $pdo->prepare("INSERT INTO galleries (website_id, media_id, sort_order) VALUES (?, ?, ?)");
            $insert->execute([$website_id, $media_id, $max_order + 1]);
            set_flash_message('success', 'Image added to gallery.');
        } else {
            set_flash_message('error', 'Invalid media selection.');
        }
    }
    elseif ($action === 'remove_from_gallery') {
        $gal_id = (int)($_POST['gallery_id'] ?? 0);
        // Soft delete from gallery table (does not delete media file)
        $del = $pdo->prepare("UPDATE galleries SET deleted_at = NOW() WHERE id = ? AND website_id = ?");
        $del->execute([$gal_id, $website_id]);
        set_flash_message('success', 'Image removed from gallery.');
    }
    elseif ($action === 'reorder_gallery') {
        $order = $_POST['order'] ?? '';
        $ids = explode(',', $order);
        $update = $pdo->prepare("UPDATE galleries SET sort_order = ? WHERE id = ? AND website_id = ?");
        foreach ($ids as $index => $id) {
            if ($id) {
                $update->execute([$index, (int)$id, $website_id]);
            }
        }
        set_flash_message('success', 'Gallery order saved.');
    }
    redirect('/user/gallery.php');
}

// Fetch Gallery items
$gal_stmt = $pdo->prepare("
    SELECT g.id as gallery_id, g.sort_order, m.id as media_id, m.original_name, m.thumbnail_path, m.webp_path
    FROM galleries g
    JOIN media m ON g.media_id = m.id
    WHERE g.website_id = ? AND g.deleted_at IS NULL AND m.deleted_at IS NULL
    ORDER BY g.sort_order ASC, g.id ASC
");
$gal_stmt->execute([$website_id]);
$gallery_items = $gal_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Available Media
$media_stmt = $pdo->prepare("SELECT id, original_name, thumbnail_path, webp_path FROM media WHERE website_id = ? AND deleted_at IS NULL AND media_type = 'image' ORDER BY id DESC");
$media_stmt->execute([$website_id]);
$available_media = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Gallery Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGalleryModal">
        <i class="bi bi-plus-lg me-1"></i> Add to Gallery
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <?php if(empty($gallery_items)): ?>
            <div class="text-center text-muted p-5">
                <i class="bi bi-images display-4 mb-3 d-block"></i>
                <p>Your gallery is empty. Select images from your Media Library to feature them here.</p>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-4"><i class="bi bi-info-circle"></i> Drag and drop to reorder images.</p>
            <div class="row g-3" id="galleryGrid">
                <?php foreach($gallery_items as $item): ?>
                <div class="col-6 col-md-4 col-lg-3 gallery-item" data-id="<?= $item['gallery_id'] ?>" draggable="true">
                    <div class="card border-0 shadow-sm h-100 position-relative">
                        <img src="<?= escape($item['thumbnail_path'] ?: $item['webp_path']) ?>" class="card-img-top" style="height: 150px; object-fit: cover;">
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            <i class="bi bi-grip-vertical text-muted cursor-move" style="cursor: grab;"></i>
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Remove from gallery?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="remove_from_gallery">
                                <input type="hidden" name="gallery_id" value="<?= $item['gallery_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if(!empty($gallery_items)): ?>
    <div class="card-footer bg-white border-0 py-3">
        <form method="POST" action="" id="reorderForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="reorder_gallery">
            <input type="hidden" name="order" id="galleryOrderInput">
            <button type="button" class="btn btn-outline-secondary d-none" id="saveOrderBtn">Save Order</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Select Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="max-height: 60vh; overflow-y: auto;">
                <?php if (empty($available_media)): ?>
                    <div class="alert alert-info">No media available. <a href="/user/media.php" target="_blank">Upload files here.</a></div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach($available_media as $m): ?>
                        <div class="col-4 col-md-3 col-lg-2">
                            <form method="POST" action="">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="add_to_gallery">
                                <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="card border-0 shadow-sm h-100 w-100 p-0 text-start" style="cursor: pointer; transition: transform 0.2s;">
                                    <img src="<?= escape($m['thumbnail_path'] ?: $m['webp_path']) ?>" class="card-img-top" style="height: 100px; object-fit: cover;" title="<?= escape($m['original_name']) ?>">
                                    <div class="card-body p-2 text-center text-truncate small w-100">
                                        <?= escape($m['original_name']) ?>
                                    </div>
                                </button>
                            </form>
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
    const grid = document.getElementById('galleryGrid');
    if (grid) {
        let draggedItem = null;
        const items = grid.querySelectorAll('.gallery-item');

        items.forEach(item => {
            item.addEventListener('dragstart', function() {
                draggedItem = this;
                this.style.opacity = '0.5';
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            item.addEventListener('drop', function(e) {
                e.preventDefault();
                if (this !== draggedItem) {
                    let allItems = Array.from(grid.querySelectorAll('.gallery-item'));
                    let draggedIdx = allItems.indexOf(draggedItem);
                    let targetIdx = allItems.indexOf(this);

                    if (draggedIdx < targetIdx) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }

                    document.getElementById('saveOrderBtn').classList.remove('d-none');
                }
            });

            item.addEventListener('dragend', function() {
                this.style.opacity = '1';
            });
        });

        const saveOrderBtn = document.getElementById('saveOrderBtn');
        if (saveOrderBtn) {
            saveOrderBtn.addEventListener('click', function() {
                const newOrder = Array.from(grid.querySelectorAll('.gallery-item')).map(r => r.dataset.id).join(',');
                document.getElementById('galleryOrderInput').value = newOrder;
                document.getElementById('reorderForm').submit();
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
