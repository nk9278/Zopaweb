<?php
// user/business.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website strictly verifying ownership
$stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'Please create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/business.php');
    }

    $business_name = trim($_POST['business_name'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $about = trim($_POST['about'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $logo_media_id = !empty($_POST['logo_media_id']) ? (int)$_POST['logo_media_id'] : null;
    $hero_media_id = !empty($_POST['hero_media_id']) ? (int)$_POST['hero_media_id'] : null;

    if (empty($business_name)) {
        set_flash_message('error', 'Business Name is required.');
    } else {
        // Upsert logic
        $stmt = $pdo->prepare("SELECT id FROM business_profiles WHERE website_id = ?");
        $stmt->execute([$website_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $pdo->prepare("UPDATE business_profiles SET business_name=?, tagline=?, about=?, email=?, phone=?, whatsapp=?, address=?, city=?, logo_media_id=?, hero_media_id=? WHERE website_id=?");
            $update->execute([$business_name, $tagline, $about, $email, $phone, $whatsapp, $address, $city, $logo_media_id, $hero_media_id, $website_id]);
        } else {
            $insert = $pdo->prepare("INSERT INTO business_profiles (website_id, business_name, tagline, about, email, phone, whatsapp, address, city, logo_media_id, hero_media_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$website_id, $business_name, $tagline, $about, $email, $phone, $whatsapp, $address, $city, $logo_media_id, $hero_media_id]);
        }
        set_flash_message('success', 'Business profile saved successfully.');
        redirect('/user/business.php');
    }
}

// Fetch current profile
$stmt = $pdo->prepare("SELECT * FROM business_profiles WHERE website_id = ?");
$stmt->execute([$website_id]);
$profile = $stmt->fetch() ?: [];

$page_title = "Business Profile";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Business Profile</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<?php display_flash_message(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="">
            <?php csrf_field(); ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Business Name <span class="text-danger">*</span></label>
                    <input type="text" name="business_name" class="form-control" value="<?= escape($profile['business_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Tagline</label>
                    <input type="text" name="tagline" class="form-control" value="<?= escape($profile['tagline'] ?? '') ?>" placeholder="e.g. Professional Bridal Artistry">
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">About You / Your Business</label>
                    <textarea name="about" class="form-control" rows="4"><?= escape($profile['about'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-medium">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= escape($profile['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= escape($profile['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">WhatsApp Number</label>
                    <input type="text" name="whatsapp" class="form-control" value="<?= escape($profile['whatsapp'] ?? '') ?>" placeholder="+91 XXXXX XXXXX">
                    <small class="text-muted d-block mt-1">Add your WhatsApp number so customers can contact you directly.</small>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-medium">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= escape($profile['address'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">City</label>
                    <input type="text" name="city" class="form-control" value="<?= escape($profile['city'] ?? '') ?>">
                </div>

                                <div class="col-md-6">
                    <label class="form-label fw-medium">Business Logo</label>
                    <select name="logo_media_id" class="form-select">
                        <option value="">-- No Logo --</option>
                        <?php
                        $media_stmt = $pdo->prepare("SELECT id, original_filename FROM media WHERE website_id = ? AND media_type = 'image' AND status = 'active' ORDER BY created_at DESC");
                        $media_stmt->execute([$website_id]);
                        while($m = $media_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = (($profile['logo_media_id'] ?? null) == $m['id']) ? 'selected' : '';
                            echo '<option value="'.$m['id'].'" '.$selected.'>'.escape($m['original_filename']).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Primary Hero Image</label>
                    <select name="hero_media_id" class="form-select">
                        <option value="">-- No Hero Image --</option>
                        <?php
                        $media_stmt->execute([$website_id]);
                        while($m = $media_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = (($profile['hero_media_id'] ?? null) == $m['id']) ? 'selected' : '';
                            echo '<option value="'.$m['id'].'" '.$selected.'>'.escape($m['original_filename']).'</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-2">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
