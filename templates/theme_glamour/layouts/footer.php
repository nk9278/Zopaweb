<footer class="site-footer" id="contact">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <h3><?= escape($business['name'] ?? 'Brand Name') ?></h3>
                <p><?= escape($business['tagline'] ?? '') ?></p>
            </div>
            <div class="footer-contact">
                <h4>Contact</h4>
                <p><a href="mailto:<?= escape($business['email'] ?? '') ?>"><?= escape($business['email'] ?? '') ?></a></p>
                <?php if(!empty($business['phone'])): ?>
                    <p><?= escape($business['phone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="footer-social">
                <h4>Follow</h4>
                <?php if(!empty($social['instagram'])): ?>
                    <a href="<?= escape($social['instagram']) ?>">Instagram</a>
                <?php endif; ?>
                <?php if(!empty($social['facebook'])): ?>
                    <a href="<?= escape($social['facebook']) ?>">Facebook</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= escape($business['name'] ?? 'Brand Name') ?>. All rights reserved.</p>
            <p class="attribution">Powered by <a href="#">ZopaWeb</a></p>
        </div>
    </div>
</footer>
