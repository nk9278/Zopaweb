<?php
// user/legal.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('user');

$page_title = "Legal & Business Policies";
include __DIR__ . '/../includes/user_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Legal & Business Policies</h1>
    <a href="/user/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
</div>

<div class="alert alert-warning border-0 shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Important:</strong> These are boilerplate placeholders. You must have your legal counsel review them before deploying them to your public website.
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body p-4">
                <i class="bi bi-shield-lock display-4 text-primary mb-3 d-block"></i>
                <h5 class="fw-bold">Privacy Policy</h5>
                <p class="text-muted small mb-4">Standard privacy policy explaining how you collect and process user data (e.g., from your WhatsApp forms).</p>
                <button class="btn btn-outline-primary w-100" disabled>Generate Draft (Coming Soon)</button>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body p-4">
                <i class="bi bi-file-earmark-text display-4 text-primary mb-3 d-block"></i>
                <h5 class="fw-bold">Terms of Service</h5>
                <p class="text-muted small mb-4">Standard terms and conditions governing the use of your services and website content.</p>
                <button class="btn btn-outline-primary w-100" disabled>Generate Draft (Coming Soon)</button>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center">
            <div class="card-body p-4">
                <i class="bi bi-arrow-counterclockwise display-4 text-primary mb-3 d-block"></i>
                <h5 class="fw-bold">Refund Policy</h5>
                <p class="text-muted small mb-4">Clear refund, deposit, and cancellation rules to protect your business from no-shows.</p>
                <button class="btn btn-outline-primary w-100" disabled>Generate Draft (Coming Soon)</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_footer.php'; ?>
