<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/profile.php');
    }

    $name = trim($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $errors = [];
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email) || !is_valid_email($email)) $errors[] = "Valid email is required.";

    // Check if email is used by another user
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already in use by another account.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, $phone, $user_id])) {
            $_SESSION['name'] = $name; // Update session name
            set_flash_message('success', 'Profile updated successfully.');
            redirect('/user/profile.php');
        } else {
            $errors[] = "Error updating profile.";
        }
    }

    if (!empty($errors)) {
        foreach ($errors as $e) {
            set_flash_message('error', $e);
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h4 class="mb-4">My Profile</h4>

                <form method="POST" action="">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= escape($user['email']) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= escape($user['phone']) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary px-4">Update Profile</button>
                </form>
            </div>
        </div>

        <!-- Placeholder for future Business Information (Phase 2) -->
        <div class="card border-0 shadow-sm opacity-50">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 text-muted">Business Information</h5>
                    <span class="badge bg-secondary">Coming Soon</span>
                </div>
                <p class="text-muted small">In the future, you will be able to set your business name, WhatsApp contact, address, logo, and social media links here.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="Business Name" disabled>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="WhatsApp Number" disabled>
                    </div>
                    <div class="col-12">
                        <textarea class="form-control" placeholder="Business Address" disabled></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
