<section class="reviews-section" id="reviews">
    <div class="container">
        <h2 class="section-title text-center">Client Love</h2>
        <div class="reviews-grid">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="stars">
                            <?php for($i=0; $i<$review['rating']; $i++): ?>★<?php endfor; ?>
                        </div>
                        <p class="review-text">"<?= escape($review['text']) ?>"</p>
                        <p class="review-client">- <?= escape($review['client']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">Reviews coming soon.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
