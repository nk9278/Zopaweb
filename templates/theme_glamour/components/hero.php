<?php
$c = $section_content ?? [];
$s = $section_settings ?? [];
// Fallbacks to $business data for legacy support if content_json is empty
$heading = !empty($c['heading']) ? $c['heading'] : ($business['name'] ?? 'Makeup Artist');
$text = !empty($c['text']) ? $c['text'] : ($business['tagline'] ?? 'Professional Artistry');
$bg = !empty($c['image']) ? $c['image'] : ($business['hero_image'] ?? '');
$align = ($s['alignment'] ?? 'center') === 'left' ? 'text-start' : 'text-center';

$btn_url = $c['btn_url'] ?? '#';
if ($btn_url === 'whatsapp' && !empty($business['whatsapp'])) {
    $clean_wa = preg_replace('/[^0-9]/', '', $business['whatsapp']);
    $btn_url = "https://wa.me/{$clean_wa}";
} elseif ($btn_url === 'call' && !empty($business['phone'])) {
    $clean_tel = preg_replace('/[^0-9+]/', '', $business['phone']);
    $btn_url = "tel:{$clean_tel}";
}
?>
<section class="hero-section" style="background-image: url('<?= htmlspecialchars($bg) ?>');">
    <div class="hero-overlay"></div>
    <div class="hero-content <?= $align ?>">
        <h1><?= htmlspecialchars($heading) ?></h1>
        <p class="lead mb-4"><?= $text ?></p> <!-- Unescaped intentionally to allow rich text/breaks from builder -->

        <?php if(!empty($c['btn_text'])): ?>
            <a href="<?= htmlspecialchars($btn_url) ?>" class="btn-primary"><?= htmlspecialchars($c['btn_text']) ?></a>
        <?php endif; ?>
    </div>
</section>