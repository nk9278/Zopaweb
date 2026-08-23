<?php
$c = $section_content ?? [];
$s = $section_settings ?? [];
?>
<section class="reviews-section">
    <div class="container">
        <h2 class="section-title text-center"><?= htmlspecialchars($c['heading'] ?? 'Client Love') ?></h2>

        <div class="reviews-grid">
            <?php if(!empty($reviews)): ?>
                <?php foreach($reviews as $rev): ?>
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="review-text">"<?= htmlspecialchars($rev['text']) ?>"</p>
                    <div class="review-client">- <?= htmlspecialchars($rev['client']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center w-100">No reviews available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>