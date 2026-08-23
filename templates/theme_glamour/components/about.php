<?php
$c = $section_content ?? [];
$s = $section_settings ?? [];
$heading = !empty($c['heading']) ? $c['heading'] : 'About Me';
$text = !empty($c['text']) ? $c['text'] : ($business['about'] ?? '');
?>
<section class="about-section">
    <div class="container text-center">
        <h2 class="section-title"><?= htmlspecialchars($heading) ?></h2>
        <div class="about-text">
            <?= $text ?> <!-- Unescaped to allow rich text from builder -->
        </div>
    </div>
</section>