<?php
// user/reviews.php
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
        redirect('/user/reviews.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $reviewer_name = trim($_POST['reviewer_name'] ?? '');
        $review_text = trim($_POST['review_text'] ?? '');
        $rating = (int)($_POST['rating'] ?? 5);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($rating < 1 || $rating > 5) $rating = 5;

        if (empty($reviewer_name) || empty($review_text)) {
            set_flash_message('error', 'Reviewer Name and Review Text are required.');
        } else {
            if ($action === 'add') {
                $insert = $pdo->prepare("INSERT INTO reviews (website_id, reviewer_name, review_text, rating, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->execute([$website_id, $reviewer_name, $review_text, $rating, $sort_order, $status]);
                set_flash_message('success', 'Review added.');
            } else {
                $review_id = (int)$_POST['review_id'];
                $update = $pdo->prepare("UPDATE reviews SET reviewer_name=?, review_text=?, rating=?, sort_order=?, status=? WHERE id=? AND website_id=?");
                $update->execute([$reviewer_name, $review_text, $rating, $sort_order, $status, $review_id, $website_id]);
                set_flash_message('success', 'Review updated.');
            }
        }
    } elseif ($action === 'delete') {
        $review_id = (int)$_POST['review_id'];
        $delete = $pdo->prepare("DELETE FROM reviews WHERE id=? AND website_id=?");
        $delete->execute([$review_id, $website_id]);
        set_flash_message('success', 'Review deleted.');
    }

    redirect('/user/reviews.php');
}

$stmt = $pdo->prepare("SELECT * FROM reviews WHERE website_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$website_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Reviews";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Reviews & Testimonials</h1>
    <div>
        <a href="/user/dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReviewModal">
            <i class="bi bi-plus-lg me-1"></i>Add Review
        </button>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <?php if (empty($reviews)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center bg-light">
                <i class="bi bi-chat-quote display-1 text-primary mb-3"></i>
                <h4 class="fw-bold">No reviews yet</h4>
                <p class="text-muted mb-4">Add your first client testimonial to build trust with new visitors.</p>
                <div>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                        <i class="bi bi-plus-lg me-2"></i>Add First Review
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0"><?= escape($rev['reviewer_name']) ?></h5>
                            <span class="badge <?= $rev['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= escape(ucfirst($rev['status'])) ?></span>
                        </div>
                        <div class="text-warning mb-2">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi <?= $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="card-text fst-italic">"<?= escape($rev['review_text']) ?>"</p>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Order: <?= (int)$rev['sort_order'] ?></span>
                        <div>
                            <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#editReviewModal<?= $rev['id'] ?>"><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this review?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editReviewModal<?= $rev['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Review</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Reviewer Name *</label>
                                    <input type="text" name="reviewer_name" class="form-control" required value="<?= escape($rev['reviewer_name']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Rating</label>
                                    <select name="rating" class="form-select">
                                        <?php for($i=5; $i>=1; $i--): ?>
                                            <option value="<?= $i ?>" <?= $rev['rating'] == $i ? 'selected' : '' ?>><?= $i ?> Stars</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Review Text *</label>
                                    <textarea name="review_text" class="form-control" rows="4" required><?= escape($rev['review_text']) ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label fw-medium">Sort Order</label>
                                        <input type="number" name="sort_order" class="form-control" value="<?= (int)$rev['sort_order'] ?>">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label fw-medium">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?= $rev['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $rev['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Reviewer Name *</label>
                        <input type="text" name="reviewer_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Rating</label>
                        <select name="rating" class="form-select">
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Review Text *</label>
                        <textarea name="review_text" class="form-control" rows="4" required></textarea>
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
                    <button type="submit" class="btn btn-primary">Add Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
