<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('creator');
?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-5 text-center">
                <i class="bi bi-tools display-1 text-primary mb-3"></i>
                <h2 class="fw-bold">Creator Dashboard</h2>
                <p class="text-muted lead mb-4">Welcome, Creator! We are building your dedicated workspace.</p>
                <div class="alert alert-info">
                    The Creator tools (commissions, payouts, analytics) will be available in a future update.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
