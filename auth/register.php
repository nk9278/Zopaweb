<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/user/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name)) $errors[] = "Name is required.";
        if (empty($email) || !is_valid_email($email)) $errors[] = "Valid email is required.";
        if (empty($password)) $errors[] = "Password is required.";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

        // Check for duplicate email
        $pdo = getDB();
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email is already registered.";
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $phone, $hash])) {
                set_flash_message('success', 'Registration successful. Please log in.');
                redirect('/auth/login.php');
            } else {
                $errors[] = "An error occurred during registration. Please try again.";
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/auth_header.php'; ?>

<div class="card shadow-sm border-0 mt-5 mx-auto" style="max-width: 500px;">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold">ZopaWeb</h2>
            <p class="text-muted">Professional Websites Made for Makeup Artists.</p>
        </div>

        <h4 class="mb-4 text-center">Create an Account</h4>

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
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= old('name') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number (Optional)</label>
                <input type="text" name="phone" class="form-control" value="<?= old('phone') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Register</button>
        </form>
        <div class="mt-4 text-center">
            <p>Already have an account? <a href="/auth/login.php" class="text-decoration-none">Log In</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
