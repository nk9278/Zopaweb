<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
verify_remember_me($pdo);

if (is_logged_in()) {
    if (has_role('admin')) {
        redirect('/admin/dashboard.php');
    } elseif (has_role('creator')) {
        redirect('/creator/dashboard.php');
    } else {
        redirect('/user/dashboard.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Phase 13 Login Rate Limiting (max 10 per 5 min)
    if (check_api_rate_limit($pdo, 'login_attempt', 10, '5 MINUTE')) {
        set_flash_message('error', 'Too many login attempts. Please try again later.');
        redirect('/auth/login.php');
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $errors[] = "Email and Password are required.";
        } elseif (is_rate_limited($pdo, $email)) {
            $errors[] = "Too many failed login attempts. Please try again later.";
            log_activity($pdo, null, null, 'rate_limit_exceeded', 'login_attempt', null);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'suspended') {
                    $errors[] = "Your account is currently unavailable. Please contact support.";
                } elseif ($user['status'] === 'deleted') {
                    $errors[] = "Invalid email or password."; // Don't expose deleted status
                } else {
                    // Successful login
                    clear_login_attempts($pdo, $email);
                    regenerate_session();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);

                    log_activity($pdo, $user['id'], null, 'user_login');

                    if ($remember) {
                        $selector = bin2hex(random_bytes(16));
                        $validator = bin2hex(random_bytes(32));
                        $hashed_validator = hash('sha256', $validator);
                        $expires = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                        $insertToken = $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
                        $insertToken->execute([$user['id'], $selector, $hashed_validator, $expires]);

                        setcookie('remember_me', $selector . ':' . $validator, time() + (86400 * 30), '/', '', false, true);
                    }

                    if ($user['role'] === 'admin') {
                        redirect('/admin/dashboard.php');
                    } elseif ($user['role'] === 'creator') {
                        redirect('/creator/dashboard.php');
                    } else {
                        redirect('/user/dashboard.php');
                    }
                }
            } else {
                log_failed_login($pdo, $email);
                $errors[] = "Invalid email or password.";
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="mb-4">
    <h2 class="fw-bold mb-2">Welcome back</h2>
    <p class="text-muted">Please enter your details to sign in.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm border-0">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= escape($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php display_flash_message(); ?>

<form method="POST" action="" class="auth-form">
    <?php csrf_field(); ?>
    <div class="mb-3">
        <label class="form-label fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="<?= old('email') ?>" placeholder="Enter your email" required autofocus>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label fw-medium mb-0">Password</label>
            <a href="/auth/forgot_password.php" class="text-decoration-none small fw-medium">Forgot password?</a>
        </div>
        <div class="input-group">
            <input type="password" name="password" id="loginPassword" class="form-control border-end-0" placeholder="••••••••" required>
            <span class="input-group-text bg-white border-start-0 toggle-password" data-target="loginPassword">
                <i class="bi bi-eye"></i>
            </span>
        </div>
    </div>

    <div class="mb-4 form-check">
        <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
        <label class="form-check-label text-muted" for="rememberMe">Remember me for 30 days</label>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-4">Sign In</button>

    <p class="text-center text-muted mb-0">
        Don't have an account? <a href="/auth/register.php" class="text-decoration-none fw-bold">Create free account</a>
    </p>
</form>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
