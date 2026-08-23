<?php
// templates/theme_glamour/template.php
$css_url = template_asset_url($engine['folder'], 'css/style.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($seo['title'] ?? ($business['name'] ?? 'Glamour Artist')) ?></title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= escape($seo['description'] ?? '') ?>">
    <link rel="canonical" href="<?= escape($seo['canonical'] ?? '') ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= escape($seo['title'] ?? '') ?>">
    <meta property="og:description" content="<?= escape($seo['description'] ?? '') ?>">
    <meta property="og:image" content="<?= escape($seo['image'] ?? '') ?>">
    <meta property="og:url" content="<?= escape($seo['canonical'] ?? '') ?>">
    <meta property="og:type" content="website">

    <link rel="stylesheet" href="<?= escape($css_url) ?>">
    <!-- Google Fonts for Glamour -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
</head>
<body class="zopa-template <?= escape($engine['folder']) ?>">

    <header class="header">
        <div class="logo"><?= strtoupper(escape($business['name'] ?? 'GLAMOUR')) ?></div>
    </header>

    <main>
        <?php include $engine['page_file']; ?>
    </main>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> <?= escape($business['name'] ?? 'Brand Name') ?>.</p>
    </footer>

</body>
</html>
