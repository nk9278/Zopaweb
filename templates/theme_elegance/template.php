<?php
// templates/theme_elegance/template.php
// Master layout file. $engine array is provided by template_engine.php

// Note: In real life we'd load assets cleanly.
$css_url = template_asset_url($engine['folder'], 'css/style.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    require_once __DIR__ . '/../../includes/seo.php';
    echo generate_seo_head($engine['data']);
    ?>

    <!-- Template isolated CSS -->
    <link rel="stylesheet" href="<?= escape($css_url) ?>">
    <!-- Google Fonts for Elegance -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Lato:wght@300;400&display=swap" rel="stylesheet">
</head>
<body class="zopa-template <?= escape($engine['folder']) ?>">

    <?php render_layout($engine['folder'], 'header', $engine['data']); ?>

    <main>
        <?php
        // Render the requested page
        include $engine['page_file'];
        ?>
    </main>

    <?php render_layout($engine['folder'], 'footer', $engine['data']); ?>

</body>
</html>