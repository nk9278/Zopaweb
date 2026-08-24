<?php
$c = $section_content ?? [];
$s = $section_settings ?? [];
?>
<section class="services-section">
    <div class="container">
        <h2 class="section-title text-center"><?= htmlspecialchars($c['heading'] ?? 'Our Services') ?></h2>

        <div class="services-grid">
            <?php if(!empty($services)): ?>
                <?php foreach($services as $srv): ?>
                <div class="service-card">
                    <div class="service-header">
                        <h3><?= htmlspecialchars($srv['name']) ?></h3>
                        <span class="service-price"><?= htmlspecialchars($srv['price']) ?></span>
                    </div>
                    <p class="text-muted"><?= htmlspecialchars($srv['description']) ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center w-100">No services available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>