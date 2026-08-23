<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');
$pdo = getDB();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('error', 'User not found.');
    redirect('/admin/users.php');
}

// Fetch websites owned by user
$w_stmt = $pdo->prepare("
    SELECT w.*, t.name as template_name
    FROM websites w
    LEFT JOIN templates t ON w.template_id = t.id
    WHERE w.user_id = ? AND w.deleted_at IS NULL
");
$w_stmt->execute([$user_id]);
$websites = $w_stmt->fetchAll();

// Fetch subscriptions
$s_stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY id DESC");
$s_stmt->execute([$user_id]);
$subscriptions = $s_stmt->fetchAll();

// Fetch recent activity
$a_stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$a_stmt->execute([$user_id]);
$activity_logs = $a_stmt->fetchAll();

?>
<?php require_once __DIR__ . '/../includes/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <a href="/admin/users.php" class="btn btn-sm btn-light me-3 border shadow-sm"><i class="bi bi-arrow-left"></i></a>
        <h2 class="h3 mb-0 text-gray-800">User Profile</h2>
    </div>

    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
    <div>
        <form method="POST" action="/admin/users.php" class="d-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <?php if ($user['deleted_at']): ?>
                <button type="submit" name="action" value="restore" class="btn btn-success shadow-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore</button>
            <?php else: ?>
                <?php if ($user['status'] === 'active'): ?>
                    <button type="submit" name="action" value="suspend" class="btn btn-warning shadow-sm" onclick="return confirm('Suspend user?');"><i class="bi bi-pause-circle me-1"></i> Suspend</button>
                <?php else: ?>
                    <button type="submit" name="action" value="activate" class="btn btn-success shadow-sm"><i class="bi bi-play-circle me-1"></i> Activate</button>
                <?php endif; ?>
                <button type="submit" name="action" value="delete" class="btn btn-danger shadow-sm ms-1" onclick="return confirm('Soft delete user?');"><i class="bi bi-trash me-1"></i> Delete</button>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Left Col: Profile Info -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center p-4">
                <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold display-6" style="width: 80px; height: 80px;">
                    <?= substr(escape($user['name']), 0, 1) ?>
                </div>
                <h4 class="mb-1"><?= escape($user['name']) ?></h4>
                <p class="text-muted mb-2"><?= escape($user['email']) ?></p>
                <div class="mb-3">
                    <span class="badge bg-light text-dark border"><?= escape(ucfirst($user['role'])) ?></span>
                    <?php if ($user['deleted_at']): ?>
                        <span class="badge bg-danger">Deleted</span>
                    <?php elseif ($user['status'] === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Suspended</span>
                    <?php endif; ?>
                </div>
            </div>
            <ul class="list-group list-group-flush border-top-0">
                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <span class="text-muted small">Phone</span>
                    <span class="fw-medium"><?= escape($user['phone'] ?? 'N/A') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <span class="text-muted small">Joined</span>
                    <span class="fw-medium"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <span class="text-muted small">Last Login</span>
                    <span class="fw-medium"><?= $user['last_login_at'] ? date('M d, Y', strtotime($user['last_login_at'])) : 'Never' ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Right Col: Assets -->
    <div class="col-xl-8">
        <!-- Websites -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Websites</h6>
            </div>
            <div class="card-body">
                <?php if (count($websites) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th>Website</th>
                                    <th>Template</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($websites as $w): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= escape($w['website_name']) ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 150px;">web.<?= escape($w['website_slug']) ?>.zopaweb.com</div>
                                        </td>
                                        <td><?= escape($w['template_name'] ?? 'None') ?></td>
                                        <td><span class="badge <?= $w['subscription_status'] === 'paid' ? 'bg-success' : 'bg-secondary' ?>"><?= escape(ucfirst($w['subscription_status'])) ?></span></td>
                                        <td>
                                            <?php if ($w['status'] === 'active'): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Active"></i>
                                            <?php else: ?>
                                                <i class="bi bi-exclamation-circle-fill text-warning" title="<?= escape(ucfirst($w['status'])) ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/admin/website_view.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted border rounded bg-light">User has no active websites.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="font-weight-bold m-0">Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php if (count($activity_logs) > 0): ?>
                    <div class="timeline ps-3 border-start ms-2">
                        <?php foreach ($activity_logs as $log): ?>
                            <div class="position-relative mb-3 ps-3">
                                <span class="position-absolute top-0 start-0 translate-middle p-1 bg-primary border border-white rounded-circle" style="margin-left: -0.5px; margin-top: 6px;"></span>
                                <div class="small text-muted mb-1"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></div>
                                <div class="fw-medium">Action: <?= escape($log['action']) ?></div>
                                <?php if ($log['entity_type']): ?>
                                    <div class="small text-muted">Entity: <?= escape($log['entity_type']) ?> (#<?= escape($log['entity_id']) ?>)</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">No recent activity logged.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
