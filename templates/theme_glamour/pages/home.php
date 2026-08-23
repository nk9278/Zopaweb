<?php
// templates/theme_glamour/pages/home.php
?>
<section class="hero" style="background-image: url('<?= escape($business['hero_image'] ?? '') ?>');">
    <div class="hero-content">
        <h1><?= strtoupper(escape($business['tagline'] ?? 'MUA')) ?></h1>
    </div>
</section>

<section class="content-section">
    <h2>THE ARTIST</h2>
    <p><?= escape($business['about'] ?? '') ?></p>
</section>

<section class="content-section dark-bg">
    <h2>PORTFOLIO</h2>
    <div class="grid">
        <?php if (!empty($gallery)): ?>
            <?php foreach ($gallery as $img): ?>
                <img src="<?= escape($img) ?>" alt="Gallery Image" class="grid-img">
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
