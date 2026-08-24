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
        redirect('/user/leads.php');
    }

    $action = $_POST['action'] ?? '';
    $lead_id = (int)($_POST['lead_id'] ?? 0);

    // Verify ownership
    $verify = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND website_id = ? AND deleted_at IS NULL");
    $verify->execute([$lead_id, $website_id]);

    if ($verify->fetch()) {
        if ($action === 'update_status') {
            $status = $_POST['status'] ?? 'new';
            $valid_statuses = ['new', 'contacted', 'qualified', 'converted', 'closed', 'spam'];
            if (in_array($status, $valid_statuses)) {
                $update = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
                $update->execute([$status, $lead_id]);
                set_flash_message('success', 'Lead status updated.');
            }
        }
        elseif ($action === 'update_notes') {
            $notes = strip_tags($_POST['notes'] ?? '');
            $update = $pdo->prepare("UPDATE leads SET notes = ? WHERE id = ?");
            $update->execute([$notes, $lead_id]);
            set_flash_message('success', 'Private notes updated.');
        }
        elseif ($action === 'delete_lead') {
            $delete = $pdo->prepare("UPDATE leads SET deleted_at = NOW() WHERE id = ?");
            $delete->execute([$lead_id]);
            log_activity($pdo, $user_id, null, 'lead_deleted', 'lead', $lead_id);
            set_flash_message('success', 'Lead removed.');
        }
    } else {
        set_flash_message('error', 'Lead not found or access denied.');
    }
    redirect('/user/leads.php');
}

// Analytics Counts
$counts_stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as count_new,
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as count_contacted,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as count_converted
    FROM leads
    WHERE website_id = ? AND deleted_at IS NULL
");
$counts_stmt->execute([$website_id]);
$counts = $counts_stmt->fetch(PDO::FETCH_ASSOC);

// Search & Filter
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$where = ["website_id = :website_id", "deleted_at IS NULL"];
$params = [':website_id' => $website_id];

if ($search) {
    $where[] = "(name LIKE :search OR phone LIKE :search OR service LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($filter_status && in_array($filter_status, ['new', 'contacted', 'qualified', 'converted', 'closed', 'spam'])) {
    $where[] = "status = :status";
    $params[':status'] = $filter_status;
}

$where_sql = implode(' AND ', $where);

// Fetch Leads
$stmt = $pdo->prepare("SELECT * FROM leads WHERE $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination total
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE $where_sql");
foreach ($params as $k => $v) {
    $total_stmt->bindValue($k, $v);
}
$total_stmt->execute();
$total_leads = $total_stmt->fetchColumn();
$total_pages = ceil($total_leads / $per_page);

function status_badge($status) {
    $badges = [
        'new' => 'bg-primary text-white',
        'contacted' => 'bg-info text-dark',
        'qualified' => 'bg-warning text-dark',
        'converted' => 'bg-success text-white',
        'closed' => 'bg-secondary text-white',
        'spam' => 'bg-danger text-white'
    ];
    $cls = $badges[$status] ?? 'bg-secondary';
    return "<span class=\"badge $cls\">" . ucfirst($status) . "</span>";
}

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Leads & Enquiries</h2>
</div>

<!-- Quick Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="text-xs fw-bold text-primary text-uppercase mb-1">New Leads</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?= (int)$counts['count_new'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="text-xs fw-bold text-info text-uppercase mb-1">Contacted</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?= (int)$counts['count_contacted'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="text-xs fw-bold text-success text-uppercase mb-1">Converted</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?= (int)$counts['count_converted'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search name, phone, service..." value="<?= escape($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="new" <?= $filter_status==='new'?'selected':'' ?>>New</option>
                    <option value="contacted" <?= $filter_status==='contacted'?'selected':'' ?>>Contacted</option>
                    <option value="qualified" <?= $filter_status==='qualified'?'selected':'' ?>>Qualified</option>
                    <option value="converted" <?= $filter_status==='converted'?'selected':'' ?>>Converted</option>
                    <option value="closed" <?= $filter_status==='closed'?'selected':'' ?>>Closed</option>
                    <option value="spam" <?= $filter_status==='spam'?'selected':'' ?>>Spam</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="/user/leads.php" class="btn btn-light w-100 border">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($leads)): ?>
            <div class="empty-state p-5 text-center">
                <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                <h5>No leads found</h5>
                <p class="text-muted mb-0">You don't have any matching enquiries yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Service</th>
                            <th>Type</th>
                            <th>Date Received</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leads as $lead): ?>
                        <tr>
                            <td class="ps-4 fw-semibold">
                                <a href="#" class="text-dark text-decoration-none" onclick='openLeadModal(<?= json_encode($lead) ?>); return false;'>
                                    <?= escape($lead['name']) ?>
                                </a>
                            </td>
                            <td><?= escape($lead['service'] ?: '-') ?></td>
                            <td><span class="text-muted small text-uppercase"><?= escape($lead['lead_type']) ?></span></td>
                            <td><small class="text-muted"><?= date('M j, Y g:i A', strtotime($lead['created_at'])) ?></small></td>
                            <td><?= status_badge($lead['status']) ?></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if($lead['whatsapp']): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $lead['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="WhatsApp">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($lead['phone']): ?>
                                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $lead['phone']) ?>" class="btn btn-sm btn-outline-primary" title="Call">
                                            <i class="bi bi-telephone"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-light border" onclick='openLeadModal(<?= json_encode($lead) ?>)'>View</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="card-footer bg-white border-top py-3">
                <ul class="pagination justify-content-center mb-0 border-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">Prev</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $total_pages ?></span></li>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">Next</a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Lead Details Modal -->
<div class="modal fade" id="leadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-0 pb-3">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="modalName">Lead Name</h5>
                    <div class="d-flex gap-2 text-muted small">
                        <span id="modalDate"></span>
                        <span>•</span>
                        <span id="modalSource" class="text-uppercase"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-7 p-4 border-end">
                        <h6 class="fw-bold mb-3 text-primary border-bottom pb-2">Enquiry Details</h6>
                        <div class="row mb-3">
                            <div class="col-4 text-muted small">Service:</div>
                            <div class="col-8 fw-semibold" id="modalService"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 text-muted small">Lead Type:</div>
                            <div class="col-8 text-uppercase" id="modalType"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 text-muted small">Pref. Date:</div>
                            <div class="col-8" id="modalPrefDate"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 text-muted small">Pref. Time:</div>
                            <div class="col-8" id="modalPrefTime"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 text-muted small">Message:</div>
                            <div class="col-8"><p id="modalMessage" class="bg-light p-3 rounded small mb-0"></p></div>
                        </div>

                        <h6 class="fw-bold mb-3 mt-4 text-primary border-bottom pb-2">Contact Info</h6>
                        <div class="row mb-2">
                            <div class="col-4 text-muted small">Phone:</div>
                            <div class="col-8 d-flex align-items-center gap-2">
                                <span id="modalPhone"></span>
                                <a href="#" id="modalCallBtn" class="btn btn-sm btn-light border py-0 px-2 d-none"><i class="bi bi-telephone text-primary"></i></a>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-muted small">WhatsApp:</div>
                            <div class="col-8 d-flex align-items-center gap-2">
                                <span id="modalWhatsApp"></span>
                                <a href="#" id="modalWABtn" target="_blank" class="btn btn-sm btn-light border py-0 px-2 d-none"><i class="bi bi-whatsapp text-success"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5 p-4 bg-light">
                        <form method="POST" action="" class="mb-4">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="lead_id" class="lead-id-input">
                            <label class="form-label fw-bold small">Status</label>
                            <div class="input-group">
                                <select name="status" id="modalStatus" class="form-select form-select-sm">
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="qualified">Qualified</option>
                                    <option value="converted">Converted</option>
                                    <option value="closed">Closed</option>
                                    <option value="spam">Spam</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Update</button>
                            </div>
                        </form>

                        <form method="POST" action="">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="update_notes">
                            <input type="hidden" name="lead_id" class="lead-id-input">
                            <label class="form-label fw-bold small">Private Notes</label>
                            <textarea name="notes" id="modalNotes" class="form-control form-control-sm mb-2" rows="4" placeholder="Add internal notes..."></textarea>
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Save Notes</button>
                        </form>

                        <hr class="my-4">

                        <form method="POST" action="" onsubmit="return confirm('Delete this lead? This action cannot be undone.');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_lead">
                            <input type="hidden" name="lead_id" class="lead-id-input">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete Lead</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openLeadModal(lead) {
    document.getElementById('modalName').textContent = lead.name;
    document.getElementById('modalDate').textContent = new Date(lead.created_at).toLocaleString();
    document.getElementById('modalSource').textContent = lead.source.replace('_', ' ');

    document.getElementById('modalService').textContent = lead.service || '-';
    document.getElementById('modalType').textContent = lead.lead_type;
    document.getElementById('modalPrefDate').textContent = lead.preferred_date || '-';
    document.getElementById('modalPrefTime').textContent = lead.preferred_time || '-';
    document.getElementById('modalMessage').textContent = lead.message || 'No message provided.';

    document.getElementById('modalPhone').textContent = lead.phone || '-';
    const callBtn = document.getElementById('modalCallBtn');
    if(lead.phone) {
        callBtn.href = 'tel:' + lead.phone.replace(/[^0-9+]/g, '');
        callBtn.classList.remove('d-none');
    } else {
        callBtn.classList.add('d-none');
    }

    document.getElementById('modalWhatsApp').textContent = lead.whatsapp || '-';
    const waBtn = document.getElementById('modalWABtn');
    if(lead.whatsapp) {
        waBtn.href = 'https://wa.me/' + lead.whatsapp.replace(/[^0-9]/g, '');
        waBtn.classList.remove('d-none');
    } else {
        waBtn.classList.add('d-none');
    }

    document.getElementById('modalStatus').value = lead.status;
    document.getElementById('modalNotes').value = lead.notes || '';

    document.querySelectorAll('.lead-id-input').forEach(el => el.value = lead.id);

    new bootstrap.Modal(document.getElementById('leadModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
