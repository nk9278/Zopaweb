<header class="site-header">
    <div class="container header-inner">
        <div class="logo">
            <a href="/"><?= escape($business['name'] ?? 'Brand Name') ?></a>
        </div>
                <nav class="main-nav">
            <ul>
                <?php
                $nav_items = $engine['data']['navigation'] ?? [];
                if (!empty($nav_items)):
                    foreach ($nav_items as $item):
                ?>
                    <li><a href="/<?= $item['slug'] === 'home' ? '' : htmlspecialchars($item['slug']) ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                <?php
                    endforeach;
                else:
                ?>
                    <li><a href="/">Home</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
