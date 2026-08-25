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
