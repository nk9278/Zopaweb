<?php
// templates/theme_elegance/template.php
// Master layout file. $engine array is provided by template_engine.php

$css_url = template_asset_url($engine['folder'], 'css/style.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($seo['title'] ?? ($business['name'] ?? 'Makeup Artist')) ?></title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= escape($seo['description'] ?? '') ?>">
    <link rel="canonical" href="<?= escape($seo['canonical'] ?? '') ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= escape($seo['title'] ?? '') ?>">
    <meta property="og:description" content="<?= escape($seo['description'] ?? '') ?>">
    <meta property="og:image" content="<?= escape($seo['image'] ?? '') ?>">
    <meta property="og:url" content="<?= escape($seo['canonical'] ?? '') ?>">
    <meta property="og:type" content="website">

    <!-- Template isolated CSS -->
    <link rel="stylesheet" href="<?= escape($css_url) ?>">
    <!-- Google Fonts dynamically loaded -->
    <?php if (!empty($engine['google_fonts_url'])): ?>
    <link href="<?= escape($engine['google_fonts_url']) ?>" rel="stylesheet">
    <?php else: ?>
    <!-- Fallback if missing -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Lato:wght@300;400&display=swap" rel="stylesheet">
    <?php endif; ?>

    <!-- Injected Theme CSS from Phase 7 -->
    <?= $engine['theme_css'] ?? '' ?>
</head>
<body class="zopa-template <?= escape($engine['folder']) ?>">

    <?php render_layout($engine['folder'], 'header', $engine['data']); ?>

    <main>
        <?= $engine['page_content'] ?? '' ?>
    </main>

    <?php render_layout($engine['folder'], 'footer', $engine['data']); ?>

    <script>
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'UPDATE_CSS_VAR') {
                document.documentElement.style.setProperty(event.data.variable, event.data.value);
            }
        });
    </script>
</body>
</html>
