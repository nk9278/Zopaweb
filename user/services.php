<?php
// user/services.php
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
        redirect('/user/services.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '');
        // image_url to be integrated via Media Library in R4
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            set_flash_message('error', 'Service Name is required.');
        } else {
            if ($action === 'add') {
                $insert = $pdo->prepare("INSERT INTO services (website_id, name, description, price, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->execute([$website_id, $name, $description, $price, $sort_order, $status]);
                set_flash_message('success', 'Service added successfully.');
            } else {
                $service_id = (int)$_POST['service_id'];
                // Ownership implicitly enforced by AND website_id = ?
                $update = $pdo->prepare("UPDATE services SET name=?, description=?, price=?, sort_order=?, status=? WHERE id=? AND website_id=?");
                $update->execute([$name, $description, $price, $sort_order, $status, $service_id, $website_id]);
                set_flash_message('success', 'Service updated successfully.');
            }
        }
    } elseif ($action === 'delete') {
        $service_id = (int)$_POST['service_id'];
        $delete = $pdo->prepare("DELETE FROM services WHERE id=? AND website_id=?");
        $delete->execute([$service_id, $website_id]);
        set_flash_message('success', 'Service removed.');
    }

    redirect('/user/services.php');
}

// Fetch current services
$stmt = $pdo->prepare("SELECT * FROM services WHERE website_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$website_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Services";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Services</h1>
    <div>
        <a href="/user/dashboard.php" class="btn btn-outline-secondary me-2">Dashboard</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="bi bi-plus-lg me-1"></i>Add Service
        </button>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <?php if (empty($services)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center">
                <i class="bi bi-stars display-4 text-muted mb-3"></i>
                <h5>No services yet.</h5>
                <p class="text-muted">Add your first service to show clients what you offer.</p>
                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addServiceModal">Add Service</button>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($services as $srv): ?>
            <div class="col-md-6 col-xl-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold mb-0"><?= escape($srv['name']) ?></h5>
                            <span class="badge <?= $srv['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= escape(ucfirst($srv['status'])) ?></span>
                        </div>
                        <p class="text-primary fw-medium mb-3"><?= escape($srv['price']) ?></p>
                        <p class="card-text text-muted small"><?= escape($srv['description']) ?></p>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Order: <?= (int)$srv['sort_order'] ?></span>
                        <div>
                            <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#editServiceModal<?= $srv['id'] ?>"><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this service?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="service_id" value="<?= $srv['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editServiceModal<?= $srv['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="service_id" value="<?= $srv['id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Service Name *</label>
                                    <input type="text" name="name" class="form-control" required value="<?= escape($srv['name']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Price</label>
                                    <input type="text" name="price" class="form-control" placeholder="e.g. ₹15,000" value="<?= escape($srv['price']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?= escape($srv['description']) ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label fw-medium">Sort Order</label>
                                        <input type="number" name="sort_order" class="form-control" value="<?= (int)$srv['sort_order'] ?>">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label fw-medium">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?= $srv['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $srv['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Service Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Price</label>
                        <input type="text" name="price" class="form-control" placeholder="e.g. ₹15,000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary">Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
