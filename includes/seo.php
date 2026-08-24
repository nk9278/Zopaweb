<?php
// includes/seo.php
// Centralized SEO and Search Visibility Engine

/**
 * Generates the complete HTML <head> SEO payload for a given page.
 *
 * @param array $data The comprehensive isolated website data array.
 * @return string HTML string of meta tags.
 */
function generate_seo_head($data) {
    global $pdo;

    $site = $data['site'] ?? [];
    $page = $data['page'] ?? [];
    $business = $data['business'] ?? [];
    $theme = $data['theme'] ?? [];
    $social = $data['social'] ?? [];

    // 1. Determine active SEO Configuration
    $seo_stmt = $pdo->prepare("SELECT * FROM website_seo WHERE website_id = ?");
    $seo_stmt->execute([$site['id'] ?? 0]);
    $seo_config = $seo_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 2. Resolve Global Visibility State
    // If the site is unpublished, suspended, or manually set to hidden, block indexing completely.
    $site_visible = ($site['publication_status'] === 'published' && $site['status'] === 'active' && ($seo_config['search_engine_visibility'] ?? 1) == 1);
    $page_visible = ($page['status'] === 'published');

    $is_indexable = $site_visible && $page_visible;

    // 3. Resolve SEO Title
    $base_title = $business['name'] ?? 'Website';
    if (!empty($page['meta_title'])) {
        $title = $page['meta_title'];
    } elseif (!empty($seo_config['default_seo_title']) && !empty($page['is_homepage'])) {
        $title = $seo_config['default_seo_title'];
    } else {
        $title = !empty($page['title']) ? $page['title'] . ' | ' . $base_title : $base_title;
    }

    // 4. Resolve Meta Description
    $description = $page['meta_description'] ?? '';
    if (empty($description)) {
        if (!empty($page['is_homepage']) && !empty($seo_config['default_meta_description'])) {
            $description = $seo_config['default_meta_description'];
        } elseif (!empty($business['about'])) {
            $description = substr(strip_tags($business['about']), 0, 160);
        } else {
            $description = $business['tagline'] ?? '';
        }
    }

    // 5. Generate Canonical URL securely from current Request
    // ZopaWeb enforces trailing slashes removed logically in routing, we reflect that strictly.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = empty($page['is_homepage']) ? '/' . ($page['slug'] ?? '') : '';
    $canonical_url = $protocol . $host . $path;

    // 6. Resolve Open Graph Image
    $og_image_url = '';
    // Resolve helper from template_engine if exists
    global $resolveMediaUrl;
    if (is_callable($resolveMediaUrl)) {
        if (!empty($seo_config['default_og_image_id'])) {
            $og_image_url = $resolveMediaUrl($seo_config['default_og_image_id']);
        } elseif (!empty($business['hero_image'])) {
            $og_image_url = $business['hero_image']; // Already resolved in template_engine
        } elseif (!empty($business['logo_url'])) {
            $og_image_url = $business['logo_url'];
        }
    }
    if (!empty($og_image_url) && strpos($og_image_url, 'http') !== 0) {
        $og_image_url = $protocol . $host . $og_image_url;
    }

    // 7. Generate JSON-LD LocalBusiness Schema (if homepage and indexable)
    $json_ld = '';
    if (!empty($page['is_homepage']) && $is_indexable && !empty($business['name'])) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business['name'],
            'url' => $canonical_url,
        ];
        if (!empty($business['logo_url'])) {
            $logo_full = (strpos($business['logo_url'], 'http') === 0) ? $business['logo_url'] : $protocol . $host . $business['logo_url'];
            $schema['image'] = $logo_full;
            $schema['logo'] = $logo_full;
        }
        if (!empty($business['phone'])) $schema['telephone'] = $business['phone'];
        if (!empty($business['address']) || !empty($business['city'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $business['address'] ?? '',
                'addressLocality' => $business['city'] ?? ''
            ];
        }
        if (!empty($social)) {
            $schema['sameAs'] = array_values($social);
        }
        $json_ld = '<script type="application/ld+json">' . json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    // 8. Build HTML Output string securely
    $out = [];
    $out[] = '<!-- ZopaWeb Dynamic SEO Layer -->';
    $out[] = '<title>' . escape($title) . '</title>';

    if (!empty($description)) {
        $out[] = '<meta name="description" content="' . escape($description) . '">';
    }

    $out[] = '<link rel="canonical" href="' . escape($canonical_url) . '">';

    if (!$is_indexable) {
        $out[] = '<meta name="robots" content="noindex, nofollow">';
    } else {
        $out[] = '<meta name="robots" content="index, follow, max-image-preview:large">';
    }

    // Open Graph
    $out[] = '<meta property="og:site_name" content="' . escape($base_title) . '">';
    $out[] = '<meta property="og:title" content="' . escape($title) . '">';
    $out[] = '<meta property="og:url" content="' . escape($canonical_url) . '">';
    $out[] = '<meta property="og:type" content="website">';
    if (!empty($description)) {
        $out[] = '<meta property="og:description" content="' . escape($description) . '">';
    }
    if (!empty($og_image_url)) {
        $out[] = '<meta property="og:image" content="' . escape($og_image_url) . '">';
        $out[] = '<meta name="twitter:card" content="summary_large_image">';
        $out[] = '<meta name="twitter:image" content="' . escape($og_image_url) . '">';
    }

    $out[] = '<meta name="twitter:title" content="' . escape($title) . '">';
    if (!empty($description)) {
        $out[] = '<meta name="twitter:description" content="' . escape($description) . '">';
    }

    if (!empty($json_ld)) {
        $out[] = $json_ld;
    }

    return implode("\n    ", $out);
}
