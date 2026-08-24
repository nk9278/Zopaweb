<?php
// includes/functions.php

/**
 * Redirect to a specific URL and exit
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message in the session
 * @param string $type (e.g., 'success', 'error', 'info')
 * @param string $message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display the flash message and clear it from the session
 */
function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = escape($_SESSION['flash']['message']);

        // Basic Bootstrap-like alert classes
        $alertClass = 'alert-info';
        if ($type === 'success') {
            $alertClass = 'alert-success';
        } elseif ($type === 'error') {
            $alertClass = 'alert-danger';
        }

        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";

        unset($_SESSION['flash']);
    }
}

/**
 * Get an old form value (useful for repopulating forms on validation error)
 * @param string $key
 * @param string $default
 * @return string
 */
function old($key, $default = '') {
    return isset($_POST[$key]) ? escape($_POST[$key]) : escape($default);
}

/**
 * Simple logger for activity
 */
function log_activity($pdo, $user_id, $admin_id, $action, $entity_type = null, $entity_id = null) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, admin_id, action, entity_type, entity_id, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $admin_id,
        $action,
        $entity_type,
        $entity_id,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

/**
 * Get onboarding status logic for a website.
 * @param PDO $pdo
 * @param int $website_id
 * @param array $website_data (website row)
 * @return array
 */
function get_onboarding_status($pdo, $website_id, $website_data = []) {
    $steps = [
        'template' => ['label' => 'Choose a Template', 'completed' => false, 'url' => '/user/templates.php'],
        'business' => ['label' => 'Add Business Info', 'completed' => false, 'url' => '/user/business.php'],
        'services' => ['label' => 'Add Services', 'completed' => false, 'url' => '/user/services.php'],
        'gallery'  => ['label' => 'Upload Photos', 'completed' => false, 'url' => '/user/gallery.php'],
        'homepage' => ['label' => 'Create Homepage', 'completed' => false, 'url' => '/user/pages.php'],
        'seo'      => ['label' => 'Basic SEO', 'completed' => false, 'url' => '/user/seo.php'],
        'publish'  => ['label' => 'Publish Website', 'completed' => false, 'url' => '/user/publish.php']
    ];

    if (empty($website_id)) {
        return ['steps' => $steps, 'progress' => 0, 'next_action' => null, 'is_complete' => false];
    }

    if (!empty($website_data['template_id'])) {
        $steps['template']['completed'] = true;
    }

    $bp_stmt = $pdo->prepare("SELECT business_name, tagline, whatsapp FROM business_profiles WHERE website_id = ? LIMIT 1");
    $bp_stmt->execute([$website_id]);
    $bp = $bp_stmt->fetch(PDO::FETCH_ASSOC);
    if ($bp && !empty($bp['business_name'])) {
        $steps['business']['completed'] = true;
    }

    $srv_stmt = $pdo->prepare("SELECT id FROM services WHERE website_id = ? LIMIT 1");
    $srv_stmt->execute([$website_id]);
    if ($srv_stmt->fetch()) {
        $steps['services']['completed'] = true;
    }

    $gal_stmt = $pdo->prepare("SELECT id FROM gallery_items WHERE website_id = ? LIMIT 1");
    $gal_stmt->execute([$website_id]);
    if ($gal_stmt->fetch()) {
        $steps['gallery']['completed'] = true;
    }

    $home_stmt = $pdo->prepare("SELECT id FROM pages WHERE website_id = ? AND is_homepage = 1 LIMIT 1");
    $home_stmt->execute([$website_id]);
    if ($home_stmt->fetch()) {
        $steps['homepage']['completed'] = true;
    }

    $seo_stmt = $pdo->prepare("SELECT id FROM website_seo WHERE website_id = ? AND default_seo_title IS NOT NULL LIMIT 1");
    $seo_stmt->execute([$website_id]);
    if ($seo_stmt->fetch()) {
        $steps['seo']['completed'] = true;
    }

    if (($website_data['publication_status'] ?? '') === 'published') {
        $steps['publish']['completed'] = true;
    }

    $total = count($steps);
    $completed = 0;
    $next_action = null;

    foreach ($steps as $key => $step) {
        if ($step['completed']) {
            $completed++;
        } elseif ($next_action === null) {
            $next_action = $step;
        }
    }

    $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

    return [
        'steps' => $steps,
        'progress' => $progress,
        'next_action' => $next_action,
        'is_complete' => ($completed === $total)
    ];
}
