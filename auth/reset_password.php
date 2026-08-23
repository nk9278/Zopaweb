<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('/user/dashboard.php');
}

// Placeholder for Phase 1
$errors = ["Password reset functionality will be implemented in a later phase."];

?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="card shadow-sm border-0 mt-5 mx-auto" style="max-width: 450px;">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold">ZopaWeb</h2>
            <p class="text-muted">Set New Password</p>
        </div>

        <div class="alert alert-info">
            <?php foreach ($errors as $err): ?>
                <p class="mb-0"><?= escape($err) ?></p>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 text-center">
            <a href="/auth/login.php" class="text-decoration-none">Back to Log In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
