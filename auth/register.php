<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
verify_remember_me($pdo);

if (is_logged_in()) {
    redirect('/user/dashboard.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? ''); // Maps to WhatsApp/Mobile
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $business_name = trim($_POST['business_name'] ?? ''); // Optional for Phase 2

        if (empty($name)) $errors[] = "Full name is required.";
        if (empty($email) || !is_valid_email($email)) $errors[] = "Valid email is required.";
        if (empty($password)) $errors[] = "Password is required.";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "An account with this email already exists.";
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $hash]);
                $user_id = $pdo->lastInsertId();

                log_activity($pdo, $user_id, null, 'user_registered');

                $pdo->commit();
                $success = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "An error occurred during registration. Please try again.";
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<?php if ($success): ?>
    <div class="text-center animation-fade-in">
        <div class="mb-4">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
        </div>
        <h2 class="fw-bold mb-3">Your ZopaWeb account is ready.</h2>
        <p class="text-muted mb-4">You can now sign in and start building your professional makeup artist portfolio.</p>
        <a href="/auth/login.php" class="btn btn-primary px-4 py-2">Continue to Sign In</a>
    </div>
<?php else: ?>

    <div class="mb-4">
        <h2 class="fw-bold mb-2">Create your free website</h2>
        <p class="text-muted">Start showcasing your professional portfolio today.</p>
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

    <form method="POST" action="" class="auth-form">
        <?php csrf_field(); ?>

        <div class="mb-3">
            <label class="form-label fw-medium">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= old('name') ?>" placeholder="e.g. Jane Doe" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= old('email') ?>" placeholder="name@example.com" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium">WhatsApp / Mobile Number <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="text" name="phone" class="form-control" value="<?= old('phone') ?>" placeholder="+91 XXXXX XXXXX">
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium">Business Name <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="text" name="business_name" class="form-control" value="<?= old('business_name') ?>" placeholder="e.g. Jane Makeup Artistry">
            <div class="form-text">You can add more business details later.</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="passwordInput" class="form-control border-end-0" placeholder="Min. 8 characters" required>
                <span class="input-group-text bg-white border-start-0 toggle-password" data-target="passwordInput">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
            <div class="password-strength-bar">
                <div id="strengthMeter" class="strength-meter"></div>
            </div>
            <span id="strengthText" class="strength-text"></span>
        </div>

        <div class="mb-4">
            <label class="form-label fw-medium">Confirm Password</label>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control border-end-0" placeholder="Confirm your password" required>
                <span class="input-group-text bg-white border-start-0 toggle-password" data-target="confirmPasswordInput">
                    <i class="bi bi-eye"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-4">Create Account</button>

        <p class="text-center text-muted mb-0">
            Already have an account? <a href="/auth/login.php" class="text-decoration-none fw-bold">Sign In</a>
        </p>
    </form>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
