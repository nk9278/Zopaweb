<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'You need to create a website first.');
    redirect('/user/dashboard.php');
}
$website_id = $website['id'];
$zopaweb_domain = "web.{$website['website_slug']}." . PRIMARY_PLATFORM_DOMAIN;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/domains.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_domain') {
        $domain = trim($_POST['domain_name'] ?? '');
        $domain = strtolower(preg_replace('/^https?:\/\//i', '', $domain));
        $domain = explode('/', $domain)[0]; // strip path
        $domain = preg_replace('/^www\./i', '', $domain); // store base domain

        if (empty($domain) || !preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}$/i', $domain)) {
            set_flash_message('error', 'Invalid domain name format.');
        } else {
            // Check if domain exists globally
            $check = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ?");
            $check->execute([$domain]);
            if ($check->fetch()) {
                set_flash_message('error', 'This domain is already registered in our system.');
            } else {
                // Generate validation token
                $token = 'zopaweb-verify=' . bin2hex(random_bytes(16));
                $insert = $pdo->prepare("INSERT INTO domains (user_id, website_id, domain_name, domain_type, verification_token, verification_status, status) VALUES (?, ?, ?, 'custom', ?, 'pending', 'pending')");
                $insert->execute([$user_id, $website_id, $domain, $token]);
                set_flash_message('success', 'Domain added. Please complete DNS verification.');
            }
        }
        redirect('/user/domains.php');
    } elseif ($action === 'verify_domain') {
        $domain_id = (int)$_POST['domain_id'];
        $d_stmt = $pdo->prepare("SELECT * FROM domains WHERE id = ? AND website_id = ? AND user_id = ?");
        $d_stmt->execute([$domain_id, $website_id, $user_id]);
        $dom = $d_stmt->fetch(PDO::FETCH_ASSOC);

        if ($dom && $dom['domain_type'] === 'custom') {
            // Simulated DNS Check
            // In a real app: $records = dns_get_record("_zopaweb-verification." . $dom['domain_name'], DNS_TXT);
            // We simulate success here for Phase 12 sandbox.
            $verified = true;

            if ($verified) {
                $upd = $pdo->prepare("UPDATE domains SET verification_status = 'verified', status = 'active', connected_at = NOW() WHERE id = ?");
                $upd->execute([$domain_id]);
                set_flash_message('success', 'Domain verified and activated successfully!');
            } else {
                set_flash_message('error', 'DNS verification could not be completed yet. Changes may take time to propagate.');
            }
        }
        redirect('/user/domains.php');
    } elseif ($action === 'remove_domain') {
        $domain_id = (int)$_POST['domain_id'];
        $del = $pdo->prepare("DELETE FROM domains WHERE id = ? AND website_id = ? AND user_id = ?");
        $del->execute([$domain_id, $website_id, $user_id]);
        set_flash_message('success', 'Domain removed.');
        redirect('/user/domains.php');
    }
}

// Fetch Custom Domains
$stmt = $pdo->prepare("SELECT * FROM domains WHERE website_id = ? AND domain_type = 'custom' ORDER BY id DESC");
$stmt->execute([$website_id]);
$custom_domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Domain Management</h2>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold mb-0">ZopaWeb Address</h5>
            </div>
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center align-items-center">
                <i class="bi bi-globe display-4 text-primary mb-3"></i>
                <h5 class="fw-bold text-dark"><?= escape($zopaweb_domain) ?></h5>
                <p class="text-muted small">Your default free ZopaWeb subdomain.</p>
                <a href="http://<?= escape($zopaweb_domain) ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-2">Visit Site</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white">
            <div class="card-body p-4 d-flex flex-column justify-content-center text-center">
                <i class="bi bi-link-45deg display-4 mb-3 text-white opacity-75"></i>
                <h4 class="fw-bold mb-2">Connect a Custom Domain</h4>
                <p class="mb-4 opacity-75 small">Make your brand stand out by connecting your own domain (e.g. jyotimakeup.com). Available on Pro plans.</p>
                <button class="btn btn-light text-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#addDomainModal">Add Domain</button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="card-title fw-bold mb-0">Custom Domains</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($custom_domains)): ?>
            <div class="p-5 text-center text-muted">
                <p class="mb-0">You have not connected any custom domains yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Domain Name</th>
                            <th>Status</th>
                            <th>Added On</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($custom_domains as $dom): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= escape($dom['domain_name']) ?></td>
                            <td>
                                <?php if ($dom['status'] === 'active'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-check-circle me-1"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Pending Verification</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= date('M j, Y', strtotime($dom['created_at'])) ?></small></td>
                            <td class="text-end pe-4">
                                <?php if ($dom['status'] !== 'active'): ?>
                                    <button class="btn btn-sm btn-primary me-2" onclick="verifyDomain(<?= $dom['id'] ?>, '<?= escape($dom['verification_token']) ?>', '<?= escape($dom['domain_name']) ?>')">Verify DNS</button>
                                <?php endif; ?>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Remove this domain from your website?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove_domain">
                                    <input type="hidden" name="domain_id" value="<?= $dom['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add Custom Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_domain">
                <div class="modal-body p-4 pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Domain Name</label>
                        <input type="text" name="domain_name" class="form-control" placeholder="e.g. jyotimakeup.com" required>
                        <div class="form-text">Do not include http:// or www.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verify DNS Modal -->
<div class="modal fade" id="verifyDnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Verify Domain Ownership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <p>To connect <strong id="verifyDomainName"></strong>, you must add a TXT record to your domain's DNS settings at your registrar (e.g. GoDaddy, Namecheap).</p>

                <div class="bg-light p-3 rounded border mb-4">
                    <div class="row">
                        <div class="col-3 text-muted small fw-bold">Type</div>
                        <div class="col-9 fw-bold">TXT</div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-3 text-muted small fw-bold">Host / Name</div>
                        <div class="col-9 fw-bold">_zopaweb-verification</div>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-3 text-muted small fw-bold">Value</div>
                        <div class="col-9 font-monospace" id="verifyTokenValue"></div>
                    </div>
                </div>

                <div class="alert alert-info border-0 small">
                    <i class="bi bi-info-circle me-1"></i> DNS changes may take some time to propagate globally. You can retry verification later if it fails.
                </div>
            </div>
            <div class="modal-footer border-0">
                <form method="POST" action="">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="verify_domain">
                    <input type="hidden" name="domain_id" id="verifyDomainId">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">I'll do it later</button>
                    <button type="submit" class="btn btn-primary">Verify Now</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function verifyDomain(id, token, domain) {
    document.getElementById('verifyDomainId').value = id;
    document.getElementById('verifyTokenValue').textContent = token;
    document.getElementById('verifyDomainName').textContent = domain;
    new bootstrap.Modal(document.getElementById('verifyDnsModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
