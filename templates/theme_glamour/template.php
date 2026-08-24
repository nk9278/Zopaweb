<?php
// templates/theme_glamour/template.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    require_once __DIR__ . '/../../../includes/seo.php';
    echo generate_seo_head($engine['data']);
    ?>

    <!-- Template isolated CSS -->
    <link rel="stylesheet" href="<?= escape($css_url) ?>">
    <!-- Google Fonts for Glamour -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
</head>
<body class="zopa-template <?= escape($engine['folder']) ?>">
    <?php include $engine['page_file']; ?>
</body>
</html>