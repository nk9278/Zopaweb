<?php
// templates/theme_elegance/pages/home.php
// $site, $business, $services, $gallery, $reviews are extracted here.

render_component('theme_elegance', 'hero', ['business' => $business]);
render_component('theme_elegance', 'about', ['business' => $business]);
render_component('theme_elegance', 'services', ['services' => $services]);
render_component('theme_elegance', 'gallery', ['gallery' => $gallery]);
render_component('theme_elegance', 'reviews', ['reviews' => $reviews]);
