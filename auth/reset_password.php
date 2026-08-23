<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    redirect('/user/dashboard.php');
}

$pdo = getDB();
$token = $_GET['token'] ?? '';
$errors = [];
$success = false;
$valid_token_record = null;

if (empty($token)) {
    $errors[] = "Invalid password reset link.";
} else {
    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at >= NOW() LIMIT 1");
    $stmt->execute([$token_hash]);
    $valid_token_record = $stmt->fetch();

    if (!$valid_token_record) {
        $errors[] = "This password reset link is invalid or has expired.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token_record) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = "Invalid security token.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password)) $errors[] = "Password is required.";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Update password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update_pw = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $update_pw->execute([$hash, $valid_token_record['email']]);

                // Mark token as used
                $mark_used = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $mark_used->execute([$valid_token_record['id']]);

                // Optional: Invalidate all existing auth tokens (force logout everywhere else)
                $user_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $user_stmt->execute([$valid_token_record['email']]);
                $user_id = $user_stmt->fetchColumn();
                if ($user_id) {
                    $del_tokens = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
                    $del_tokens->execute([$user_id]);
                    log_activity($pdo, $user_id, null, 'password_reset_success');
                }

                $pdo->commit();
                $success = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "An error occurred while resetting your password.";
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="mb-4 text-center">
    <div class="mb-3">
        <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
    </div>
    <h2 class="fw-bold mb-2">Set new password</h2>
    <p class="text-muted">Your new password must be different from previous used passwords.</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 text-center p-4">
        <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
        Your password has been updated successfully.
    </div>
    <div class="text-center mt-4">
        <a href="/auth/login.php" class="btn btn-primary px-4 py-2">Return to Sign In</a>
    </div>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= escape($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if (!$valid_token_record): ?>
            <div class="text-center mt-4">
                <a href="/auth/forgot_password.php" class="btn btn-outline-primary">Request New Link</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($valid_token_record): ?>
        <form method="POST" action="" class="auth-form">
            <?php csrf_field(); ?>

            <div class="mb-3">
                <label class="form-label fw-medium">New Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="resetPasswordInput" class="form-control border-end-0" placeholder="Min. 8 characters" required autofocus>
                    <span class="input-group-text bg-white border-start-0 toggle-password" data-target="resetPasswordInput">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
                <div class="password-strength-bar">
                    <div id="strengthMeter" class="strength-meter"></div>
                </div>
                <span id="strengthText" class="strength-text"></span>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirmResetPasswordInput" class="form-control border-end-0" placeholder="Confirm your new password" required>
                    <span class="input-group-text bg-white border-start-0 toggle-password" data-target="confirmResetPasswordInput">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-4">Reset Password</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
