<?php
// theme_glamour component: cta
$c = $section_content ?? [];
$s = $section_settings ?? [];

$btn_url = $c['btn_url'] ?? '#';
if ($btn_url === 'whatsapp' && !empty($business['whatsapp'])) {
    $clean_wa = preg_replace('/[^0-9]/', '', $business['whatsapp']);
    $btn_url = "https://wa.me/{$clean_wa}";
} elseif ($btn_url === 'call' && !empty($business['phone'])) {
    $clean_tel = preg_replace('/[^0-9+]/', '', $business['phone']);
    $btn_url = "tel:{$clean_tel}";
}
?>
<section class="cta-section">
    <div class="container">
        <?php if(!empty($c['heading'])): ?>
            <h2 class="section-title"><?= htmlspecialchars($c['heading']) ?></h2>
        <?php endif; ?>

        <!-- Content goes here -->
        <?php if(!empty($c['text'])): ?>
            <p><?= htmlspecialchars($c['text']) ?></p>
        <?php endif; ?>
    </div>
</section>
