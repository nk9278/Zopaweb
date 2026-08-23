<section class="gallery-section" id="gallery">
    <div class="container">
        <h2 class="section-title text-center">Portfolio</h2>
        <div class="gallery-grid">
            <?php if (!empty($gallery)): ?>
                <?php foreach ($gallery as $image_url): ?>
                    <div class="gallery-item">
                        <img src="<?= escape($image_url) ?>" alt="Portfolio Image" loading="lazy">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">Portfolio coming soon.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
