<?php
// theme_elegance component: lead_form
$c = $section_content ?? [];
$s = $section_settings ?? [];

$heading = !empty($c['heading']) ? $c['heading'] : 'Book an Appointment';
$desc = !empty($c['text']) ? $c['text'] : 'Fill out the form below and we will get back to you shortly.';
$btn_text = !empty($c['btn_text']) ? $c['btn_text'] : 'Submit Request';
$form_type = !empty($c['form_type']) ? $c['form_type'] : 'appointment';
?>
<section class="lead-form-section" style="background: var(--surface-color); padding: 80px 0;">
    <div class="container" style="max-width: 600px;">
        <div class="text-center mb-4">
            <h2 class="section-title mb-2" style="font-size: 2rem;"><?= htmlspecialchars($heading) ?></h2>
            <p class="text-muted"><?= htmlspecialchars($desc) ?></p>
        </div>

        <form class="zopa-lead-form bg-white p-4 border rounded shadow-sm" onsubmit="submitZopaLead(event, this)">
            <!-- CSRF placeholder: In a static cached page, CSRF needs an AJAX fetch or dynamic insertion. Since these pages are dynamically rendered by PHP, we can generate it -->
            <?php
                if (function_exists('csrf_token')) {
                    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
                }
            ?>
            <input type="hidden" name="website_id" value="<?= htmlspecialchars($site['id']) ?>">
            <input type="hidden" name="lead_type" value="<?= htmlspecialchars($form_type) ?>">

            <div class="alert alert-danger d-none form-error"></div>
            <div class="alert alert-success d-none form-success"></div>

            <div class="mb-3">
                <label class="form-label fw-bold small">Full Name *</label>
                <input type="text" name="name" class="form-control form-control-lg" required>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Phone Number *</label>
                    <input type="tel" name="phone" class="form-control form-control-lg" required pattern="[0-9+\-\(\)\s]{5,20}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small">WhatsApp Number</label>
                    <input type="tel" name="whatsapp" class="form-control form-control-lg" placeholder="If different">
                </div>
            </div>

            <?php if (!empty($services)): ?>
            <div class="mb-3">
                <label class="form-label fw-bold small">Service of Interest</label>
                <select name="service" class="form-select form-select-lg">
                    <option value="">Select a service...</option>
                    <?php foreach($services as $srv): ?>
                        <option value="<?= htmlspecialchars($srv['name']) ?>"><?= htmlspecialchars($srv['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="mb-3">
                <label class="form-label fw-bold small">Service / Enquiry Type</label>
                <input type="text" name="service" class="form-control form-control-lg">
            </div>
            <?php endif; ?>

            <?php if ($form_type === 'appointment'): ?>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Preferred Date</label>
                    <input type="date" name="preferred_date" class="form-control form-control-lg">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Preferred Time</label>
                    <input type="time" name="preferred_time" class="form-control form-control-lg">
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-4">
                <label class="form-label fw-bold small">Message / Details</label>
                <textarea name="message" class="form-control" rows="3" placeholder="Tell us more about what you need..."></textarea>
            </div>

            <div class="mb-3 small text-muted">
                By submitting this form, you agree that we may contact you regarding your enquiry.
            </div>

            <button type="submit" class="btn-primary w-100 py-3 submit-btn" style="border-radius: 4px; border:none;"><?= htmlspecialchars($btn_text) ?></button>
        </form>
    </div>
</section>

<script>
function submitZopaLead(e, form) {
    e.preventDefault();
    const btn = form.querySelector('.submit-btn');
    const errBox = form.querySelector('.form-error');
    const succBox = form.querySelector('.form-success');

    btn.disabled = true;
    btn.innerText = 'Sending...';
    errBox.classList.add('d-none');
    succBox.classList.add('d-none');

    const formData = new FormData(form);

    fetch('/api/lead.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            succBox.innerText = data.message;
            succBox.classList.remove('d-none');
            form.reset();
            btn.innerText = 'Sent';

            // Optional analytics beacon
            if(window.zopaTrackEvent) window.zopaTrackEvent('form_submit', 'lead_form');
        } else {
            errBox.innerText = data.error || 'An error occurred.';
            errBox.classList.remove('d-none');
            btn.disabled = false;
            btn.innerText = '<?= addslashes(htmlspecialchars($btn_text)) ?>';
        }
    })
    .catch(err => {
        errBox.innerText = 'Network error. Please try again.';
        errBox.classList.remove('d-none');
        btn.disabled = false;
        btn.innerText = '<?= addslashes(htmlspecialchars($btn_text)) ?>';
    });
}
</script>
