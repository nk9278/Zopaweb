<?php
// templates/fallback/lead_form.php
// Reusable Mobile-First Lead Capture Form for templates

// Ensure $services array is available from template context
$available_services = $services ?? [];
$form_source = $source ?? 'direct';
?>

<div class="zopaweb-lead-form-container">
    <form id="zopaweb-lead-form-<?= escape($form_source) ?>" class="zopaweb-lead-form" onsubmit="submitZopaWebLead(event, this)">
        <input type="hidden" name="source" value="<?= escape($form_source) ?>">
        <input type="hidden" name="form_type" value="inquiry">

        <!-- Honeypot -->
        <input type="text" name="website_url_hp" style="display:none !important" tabindex="-1" autocomplete="off">

        <!-- CSRF token -->
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-group mb-3">
            <label class="fw-bold mb-1 d-block">Name *</label>
            <input type="text" name="name" class="form-control form-control-lg w-100 p-2" required placeholder="Your Full Name">
        </div>

        <div class="form-group mb-3">
            <label class="fw-bold mb-1 d-block">Phone / WhatsApp *</label>
            <input type="tel" name="phone" class="form-control form-control-lg w-100 p-2" required placeholder="e.g. 9876543210" pattern="[+0-9\s-]{7,20}">
        </div>

        <?php if (!empty($available_services)): ?>
        <div class="form-group mb-3">
            <label class="fw-bold mb-1 d-block">Interested In</label>
            <select name="service_id" class="form-select form-select-lg w-100 p-2">
                <option value="">-- Select Service --</option>
                <?php foreach($available_services as $srv): ?>
                    <!-- Assumes we have ID inside the service map in template_engine (We need to ensure template_engine passes ID for services) -->
                    <option value="<?= escape($srv['id'] ?? '') ?>"><?= escape($srv['name']) ?> <?= !empty($srv['price']) ? '('.escape($srv['price']).')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="fw-bold mb-1 d-block">Preferred Date</label>
                <input type="date" name="preferred_date" class="form-control form-control-lg w-100 p-2" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
                <label class="fw-bold mb-1 d-block">Preferred Time</label>
                <select name="preferred_time" class="form-select form-select-lg w-100 p-2">
                    <option value="">-- Any --</option>
                    <option value="Morning">Morning</option>
                    <option value="Afternoon">Afternoon</option>
                    <option value="Evening">Evening</option>
                </select>
            </div>
        </div>

        <div class="form-group mb-4">
            <label class="fw-bold mb-1 d-block">Message</label>
            <textarea name="message" class="form-control w-100 p-2" rows="3" placeholder="Tell us more about what you need..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold submit-btn">Send Enquiry</button>
        <div class="form-response mt-3 text-center" style="display: none;"></div>
    </form>
</div>

<script>
function submitZopaWebLead(e, form) {
    e.preventDefault();
    const btn = form.querySelector('.submit-btn');
    const responseDiv = form.querySelector('.form-response');
    const originalText = btn.innerHTML;

    btn.innerHTML = 'Sending...';
    btn.disabled = true;
    responseDiv.style.display = 'none';

    const formData = new FormData(form);

    fetch('/public/api/leads.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            form.reset();
            responseDiv.innerHTML = '<div style="color: green; padding: 10px; background: #e8f5e9; border-radius: 5px;">' + data.message + '</div>';
            if (data.whatsapp_url) {
                responseDiv.innerHTML += '<a href="' + data.whatsapp_url + '" target="_blank" class="btn btn-success mt-3 w-100" style="background:#25D366;border-color:#25D366;">Continue on WhatsApp</a>';
            }
        } else {
            responseDiv.innerHTML = '<div style="color: red; padding: 10px; background: #ffebee; border-radius: 5px;">' + (data.error || 'Submission failed.') + '</div>';
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
        responseDiv.style.display = 'block';
    })
    .catch(err => {
        responseDiv.innerHTML = '<div style="color: red; padding: 10px; background: #ffebee; border-radius: 5px;">Something went wrong. Please try again.</div>';
        responseDiv.style.display = 'block';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
