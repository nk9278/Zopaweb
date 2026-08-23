<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    redirect('/user/dashboard.php');
}

$message = '';
$errors = [];
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid security token.";
    } else {
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email) || !is_valid_email($email)) {
            $errors[] = "Valid email is required.";
        } else {
            // Generate a secure reset token
            $reset_token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $reset_token);
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token_hash, $expires]);

            log_activity($pdo, null, null, 'password_reset_requested', null, null);

            // In Phase 2 we simulate email sending.
            // In a real app, send an email with: /auth/reset_password.php?token=$reset_token
            // Because this is local testing without email, we will log it securely or output for testing.
            // For production, never output the token on the screen.
            error_log("Password reset link (simulate): /auth/reset_password.php?token=" . $reset_token);

            // Privacy-safe response
            $message = "If an account exists for this email, password reset instructions will be sent.";
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="mb-4 text-center">
    <div class="mb-3">
        <i class="bi bi-key text-primary" style="font-size: 3rem;"></i>
    </div>
    <h2 class="fw-bold mb-2">Forgot your password?</h2>
    <p class="text-muted">No worries, we'll send you reset instructions.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm border-0">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= escape($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($message): ?>
    <div class="alert alert-success shadow-sm border-0 text-center p-4">
        <i class="bi bi-envelope-check fs-1 text-success d-block mb-2"></i>
        <?= escape($message) ?>
    </div>
    <div class="text-center mt-4">
        <a href="/auth/login.php" class="btn btn-outline-primary">Return to Sign In</a>
    </div>
<?php endif; ?>

<?php if (!$message): ?>
    <form method="POST" action="" class="auth-form">
        <?php csrf_field(); ?>
        <div class="mb-4">
            <label class="form-label fw-medium">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-4">Reset Password</button>

        <div class="text-center">
            <a href="/auth/login.php" class="text-decoration-none fw-bold text-muted">
                <i class="bi bi-arrow-left me-1"></i> Back to log in
            </a>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
