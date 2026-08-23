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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid security token.";
    } else {
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email) || !is_valid_email($email)) {
            $errors[] = "Valid email is required.";
        } else {
            // In Phase 1 we won't actually send emails.
            // We just pretend it succeeded to prevent email enumeration.
            $message = "If an account with that email exists, a password reset link has been sent.";
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="card shadow-sm border-0 mt-5 mx-auto" style="max-width: 450px;">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold">ZopaWeb</h2>
            <p class="text-muted">Password Reset</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= escape($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($message): ?>
            <div class="alert alert-success"><?= escape($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Send Reset Link</button>
        </form>
        <div class="mt-4 text-center">
            <a href="/auth/login.php" class="text-decoration-none">Back to Log In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
