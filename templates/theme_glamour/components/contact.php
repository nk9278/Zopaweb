<?php
// theme_glamour component: contact
$c = $section_content ?? [];
$s = $section_settings ?? [];
?>
<section class="contact-section">
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
