<?php
$c = $section_content ?? [];
$s = $section_settings ?? [];
?>
<section class="gallery-section">
    <div class="container">
        <h2 class="section-title text-center"><?= htmlspecialchars($c['heading'] ?? 'Gallery') ?></h2>

        <div class="gallery-grid">
            <?php if(!empty($gallery)): ?>
                <?php foreach($gallery as $img): ?>
                <div class="gallery-item">
                    <img src="<?= htmlspecialchars($img) ?>" alt="Gallery Image">
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center w-100">No images available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>