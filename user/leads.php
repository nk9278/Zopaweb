<?php
// user/leads.php
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

// Handle POST actions (Update Status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/leads.php');
    }

    $action = $_POST['action'] ?? '';
    $lead_id = (int)($_POST['lead_id'] ?? 0);

    // Verify ownership natively
    $check_stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND website_id = ?");
    $check_stmt->execute([$lead_id, $website_id]);
    if (!$check_stmt->fetch()) {
        set_flash_message('error', 'Lead not found or access denied.');
        redirect('/user/leads.php');
    }

    if ($action === 'update_status') {
        $status = $_POST['status'] ?? 'new';
        if (in_array($status, ['new', 'contacted', 'converted', 'closed', 'spam'])) {
            $upd = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ? AND website_id = ?");
            $upd->execute([$status, $lead_id, $website_id]);
            set_flash_message('success', 'Lead status updated.');
        }
    } elseif ($action === 'delete') {
        $del = $pdo->prepare("DELETE FROM leads WHERE id = ? AND website_id = ?");
        $del->execute([$lead_id, $website_id]);
        set_flash_message('success', 'Lead permanently deleted.');
    }
    redirect('/user/leads.php');
}

// Search and Filter logic
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_clauses = ["l.website_id = ?"];
$params = [$website_id];

if ($search !== '') {
    $where_clauses[] = "(l.name LIKE ? OR l.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== '') {
    $where_clauses[] = "l.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Get total for pagination
$count_query = "SELECT COUNT(*) FROM leads l WHERE $where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_leads = $count_stmt->fetchColumn();
$total_pages = ceil($total_leads / $limit);

// Get leads joining services for nice name
$query = "
    SELECT l.*, s.name as service_name
    FROM leads l
    LEFT JOIN services s ON l.service_id = s.id
    WHERE $where_sql
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($query);
// Bind params dynamically mapping strings vs ints
$param_index = 1;
foreach ($params as $param) {
    $stmt->bindValue($param_index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counters for quick stats
$stats_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM leads WHERE website_id = ? GROUP BY status");
$stats_stmt->execute([$website_id]);
$stats_raw = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
$stats = ['new' => 0, 'contacted' => 0, 'converted' => 0, 'closed' => 0, 'spam' => 0, 'total' => 0];
foreach($stats_raw as $s) {
    $stats[$s['status']] = (int)$s['count'];
    $stats['total'] += (int)$s['count'];
}

$page_title = "Lead Dashboard";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Lead Inbox</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<?php display_flash_message(); ?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <a href="/user/leads.php?status=new" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-primary text-white h-100 hover-lift">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1 opacity-75">New Leads</h6>
                    <h3 class="mb-0 fw-bold"><?= $stats['new'] ?></h3>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="/user/leads.php?status=contacted" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-info text-white h-100 hover-lift">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1 opacity-75">Contacted</h6>
                    <h3 class="mb-0 fw-bold"><?= $stats['contacted'] ?></h3>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="/user/leads.php?status=converted" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-success text-white h-100 hover-lift">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1 opacity-75">Converted</h6>
                    <h3 class="mb-0 fw-bold"><?= $stats['converted'] ?></h3>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="/user/leads.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-light text-dark h-100 hover-lift">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1 text-muted">Total Enquiries</h6>
                    <h3 class="mb-0 fw-bold"><?= $stats['total'] ?></h3>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 bg-light rounded d-flex justify-content-between align-items-center">
        <form method="GET" action="" class="d-flex w-100">
            <input type="text" name="search" class="form-control me-2" placeholder="Search name or phone..." value="<?= escape($search) ?>" style="max-width: 300px;">
            <select name="status" class="form-select me-2" style="max-width: 150px;">
                <option value="">All Statuses</option>
                <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
                <option value="contacted" <?= $status_filter === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="converted" <?= $status_filter === 'converted' ? 'selected' : '' ?>>Converted</option>
                <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                <option value="spam" <?= $status_filter === 'spam' ? 'selected' : '' ?>>Spam</option>
            </select>
            <button type="submit" class="btn btn-secondary px-3">Filter</button>
            <?php if ($search || $status_filter): ?>
                <a href="/user/leads.php" class="btn btn-outline-secondary ms-2">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($leads)): ?>
            <div class="p-5 text-center bg-light rounded m-3">
                <i class="bi bi-inbox display-1 text-primary mb-3"></i>
                <h4 class="fw-bold">No leads found</h4>
                <p class="text-muted mb-0">When customers submit the contact form on your website, they will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th class="ps-4 py-3">Customer</th>
                            <th>Contact</th>
                            <th>Inquiry Details</th>
                            <th>Date Received</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead):
                            // Status Badge formatting
                            $badge_class = 'bg-secondary';
                            if ($lead['status'] === 'new') $badge_class = 'bg-primary';
                            elseif ($lead['status'] === 'contacted') $badge_class = 'bg-info text-dark';
                            elseif ($lead['status'] === 'converted') $badge_class = 'bg-success';
                            elseif ($lead['status'] === 'spam') $badge_class = 'bg-dark opacity-50';

                            $clean_phone = preg_replace('/[^0-9+]/', '', $lead['phone']);
                        ?>
                            <tr class="<?= $lead['status'] === 'new' ? 'bg-primary bg-opacity-10' : '' ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= escape($lead['name']) ?></div>
                                    <div class="small text-muted text-uppercase"><?= escape($lead['source']) ?></div>
                                </td>
                                <td>
                                    <?php if ($clean_phone): ?>
                                        <div class="mb-1"><a href="tel:<?= escape($clean_phone) ?>" class="text-decoration-none fw-medium text-dark"><i class="bi bi-telephone-fill text-muted me-1 small"></i><?= escape($lead['phone']) ?></a></div>
                                        <div><a href="https://wa.me/<?= ltrim($clean_phone, '+') ?>" target="_blank" class="badge rounded-pill text-bg-success text-decoration-none"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a></div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No phone provided</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['service_name']): ?>
                                        <div class="fw-medium text-primary mb-1"><?= escape($lead['service_name']) ?></div>
                                    <?php endif; ?>

                                    <?php if ($lead['preferred_date']): ?>
                                        <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i><?= date('M d, Y', strtotime($lead['preferred_date'])) ?> <?= escape($lead['preferred_time'] ? ' ('.$lead['preferred_time'].')' : '') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= date('M d, Y', strtotime($lead['created_at'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($lead['created_at'])) ?></div>
                                </td>
                                <td>
                                    <!-- Status Update Form Toggle inline -->
                                    <form method="POST" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                                        <select name="status" class="form-select form-select-sm badge <?= $badge_class ?> border-0 text-start" onchange="this.form.submit()" style="width:110px; cursor:pointer;">
                                            <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?> class="bg-white text-dark">New</option>
                                            <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?> class="bg-white text-dark">Contacted</option>
                                            <option value="converted" <?= $lead['status'] === 'converted' ? 'selected' : '' ?> class="bg-white text-dark">Converted</option>
                                            <option value="closed" <?= $lead['status'] === 'closed' ? 'selected' : '' ?> class="bg-white text-dark">Closed</option>
                                            <option value="spam" <?= $lead['status'] === 'spam' ? 'selected' : '' ?> class="bg-white text-dark">Spam</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="pe-4 text-end">
                                    <button type="button" class="btn btn-sm btn-light border mb-1" data-bs-toggle="modal" data-bs-target="#viewLeadModal<?= $lead['id'] ?>">View Details</button>
                                </td>
                            </tr>

                            <!-- View Details Modal -->
                            <div class="modal fade" id="viewLeadModal<?= $lead['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header border-bottom-0 pb-0">
                                            <h5 class="modal-title fw-bold">Enquiry Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="d-flex justify-content-between align-items-center mb-4">
                                                <h4 class="fw-bold mb-0"><?= escape($lead['name']) ?></h4>
                                                <span class="badge <?= $badge_class ?>"><?= escape(ucfirst($lead['status'])) ?></span>
                                            </div>

                                            <div class="bg-light p-3 rounded mb-4">
                                                <?php if ($clean_phone): ?>
                                                    <div class="mb-3 d-flex gap-2">
                                                        <a href="tel:<?= escape($clean_phone) ?>" class="btn btn-outline-dark flex-fill"><i class="bi bi-telephone-fill me-2"></i>Call</a>
                                                        <a href="https://wa.me/<?= ltrim($clean_phone, '+') ?>" target="_blank" class="btn btn-success flex-fill" style="background:#25D366;border-color:#25D366;"><i class="bi bi-whatsapp me-2"></i>WhatsApp</a>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="row text-muted small">
                                                    <div class="col-4"><strong>Phone:</strong></div>
                                                    <div class="col-8 text-dark mb-2"><?= escape($lead['phone'] ?: 'N/A') ?></div>

                                                    <div class="col-4"><strong>Service:</strong></div>
                                                    <div class="col-8 text-dark mb-2"><?= escape($lead['service_name'] ?: 'General Inquiry') ?></div>

                                                    <div class="col-4"><strong>Date:</strong></div>
                                                    <div class="col-8 text-dark mb-2"><?= $lead['preferred_date'] ? date('l, M j, Y', strtotime($lead['preferred_date'])) : 'Flexible' ?></div>

                                                    <div class="col-4"><strong>Time:</strong></div>
                                                    <div class="col-8 text-dark mb-2"><?= escape($lead['preferred_time'] ?: 'Flexible') ?></div>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <h6 class="fw-bold text-muted text-uppercase small">Message</h6>
                                                <div class="p-3 border rounded bg-white">
                                                    <?= nl2br(escape($lead['message'] ?: 'No message provided.')) ?>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-4">
                                                <span class="small text-muted">Submitted via <strong><?= escape($lead['source']) ?></strong> form on <?= date('M j, g:i A', strtotime($lead['created_at'])) ?></span>
                                                <form method="POST" onsubmit="return confirm('Permanently delete this lead? This cannot be undone.');">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="p-3 border-top d-flex justify-content-center">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
