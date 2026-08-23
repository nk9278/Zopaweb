<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    if (has_role('admin')) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/user/dashboard.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = "Email and Password are required.";
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $errors[] = "Your account is " . escape($user['status']) . ". Please contact support.";
                } else {
                    regenerate_session();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);

                    if ($user['role'] === 'admin') {
                        redirect('/admin/dashboard.php');
                    } else {
                        redirect('/user/dashboard.php');
                    }
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="card shadow-sm border-0 mt-5 mx-auto" style="max-width: 450px;">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold">ZopaWeb</h2>
            <p class="text-muted">Your Work. Your Website.</p>
        </div>

        <h4 class="mb-4 text-center">Log In</h4>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= escape($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php display_flash_message(); ?>

        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label d-flex justify-content-between">
                    <span>Password</span>
                    <a href="/auth/forgot_password.php" class="text-decoration-none small">Forgot?</a>
                </label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Log In</button>
        </form>
        <div class="mt-4 text-center">
            <p>Don't have an account? <a href="/auth/register.php" class="text-decoration-none">Register</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
