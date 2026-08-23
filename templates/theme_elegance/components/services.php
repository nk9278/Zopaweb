<section class="services-section" id="services">
    <div class="container">
        <h2 class="section-title text-center">Services & Pricing</h2>
        <div class="services-grid">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div class="service-header">
                            <h3><?= escape($service['name']) ?></h3>
                            <span class="service-price"><?= escape($service['price']) ?></span>
                        </div>
                        <p><?= escape($service['description']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">Services coming soon.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
