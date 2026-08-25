<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['enquiry_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/admin/leads.php');
    }

    $enquiry_id = (int)$_POST['enquiry_id'];
    $status = $_POST['status'] ?? '';

    $valid_statuses = ['new', 'contacted', 'converted', 'closed', 'spam'];
    if (in_array($status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
        $stmt->execute([$status, $enquiry_id]);
        set_flash_message('success', 'Enquiry status updated.');
    }

    redirect('/admin/lead_view.php?id=' . $enquiry_id);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT e.*, w.website_name, u.name as owner_name, u.id as owner_id
    FROM leads e
    JOIN websites w ON e.website_id = w.id
    JOIN users u ON w.user_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$enquiry = $stmt->fetch();

if (!$enquiry) {
    set_flash_message('error', 'Enquiry not found.');
    redirect('/admin/leads.php');
}

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/enquiries.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h3 mb-0 text-gray-800">Enquiry Details</h2>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><?= escape($enquiry['name']) ?></h4>
                        <div class="text-muted small">Received on <?= date('F j, Y \a\t g:i A', strtotime($enquiry['created_at'])) ?></div>
                    </div>
                    <div>
                        <?php if ($enquiry['status'] === 'new'): ?>
                            <span class="badge bg-primary px-3 py-2">New</span>
                        <?php elseif ($enquiry['status'] === 'contacted'): ?>
                            <span class="badge bg-info text-dark px-3 py-2">Contacted</span>
                        <?php elseif ($enquiry['status'] === 'booked'): ?>
                            <span class="badge bg-success px-3 py-2">Booked</span>
                        <?php elseif ($enquiry['status'] === 'spam'): ?>
                            <span class="badge bg-danger px-3 py-2">Spam</span>
                        <?php else: ?>
                            <span class="badge bg-secondary px-3 py-2">Closed</span>
                        <?php endif; ?>
                    </div>
                </div>

                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Contact Information</h6>
                <div class="row g-3 mb-4">
                    <?php if ($enquiry['whatsapp']): ?>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded d-flex align-items-center">
                            <i class="bi bi-whatsapp text-success fs-3 me-3"></i>
                            <div>
                                <div class="small text-muted mb-1">WhatsApp</div>
                                <div class="fw-medium"><?= escape($enquiry['whatsapp']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($enquiry['phone']): ?>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded d-flex align-items-center">
                            <i class="bi bi-telephone text-muted fs-3 me-3"></i>
                            <div>
                                <div class="small text-muted mb-1">Phone</div>
                                <div class="fw-medium"><?= escape($enquiry['phone']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Event Details</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="small text-muted mb-1">Service Requested</div>
                            <div class="fw-medium"><?= escape($enquiry['service'] ?? 'Not specified') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="small text-muted mb-1">Event Date</div>
                            <div class="fw-medium"><?= $enquiry['event_date'] ? date('F j, Y', strtotime($enquiry['event_date'])) : 'Not specified' ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="small text-muted mb-1">Location</div>
                            <div class="fw-medium"><?= escape($enquiry['location'] ?? 'Not specified') ?></div>
                        </div>
                    </div>
                </div>

                <h6 class="text-uppercase text-muted fw-bold mb-3 small">Message</h6>
                <div class="bg-light rounded p-4 border" style="min-height: 150px;">
                    <?= nl2br(escape($enquiry['message'] ?? 'No message provided.')) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 d-flex flex-column gap-4">
        <!-- Target Website -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Target Website</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-globe text-primary fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-1"><?= escape($enquiry['website_name']) ?></h6>
                        <a href="/admin/website_view.php?id=<?= $enquiry['website_id'] ?>" class="text-decoration-none small">View Website Profile</a>
                    </div>
                </div>

                <div class="d-flex align-items-center border-top pt-3 mt-3">
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 40px; height: 40px;">
                        <?= substr(escape($enquiry['owner_name']), 0, 1) ?>
                    </div>
                    <div>
                        <div class="small text-muted mb-1">Website Owner</div>
                        <a href="/admin/user_view.php?id=<?= $enquiry['owner_id'] ?>" class="text-decoration-none fw-medium text-dark"><?= escape($enquiry['owner_name']) ?></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Manage Status</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="enquiry_id" value="<?= $enquiry['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label text-muted small">Update Enquiry Status</label>
                        <select name="status" class="form-select">
                            <option value="new" <?= $enquiry['status'] === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="contacted" <?= $enquiry['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                            <option value="booked" <?= $enquiry['status'] === 'booked' ? 'selected' : '' ?>>Booked</option>
                            <option value="closed" <?= $enquiry['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            <option value="spam" <?= $enquiry['status'] === 'spam' ? 'selected' : '' ?>>Spam</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
