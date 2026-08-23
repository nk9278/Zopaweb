<?php
// includes/session.php

require_once __DIR__ . '/../config/app.php';

// Configure secure session parameters before starting the session
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => false, // Set to true if using HTTPS in production
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Regenerate session ID securely
 */
function regenerate_session() {
    session_regenerate_id(true);
}

/**
 * Check if the user is logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require a logged-in user. If not logged in, redirect to login page.
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('/auth/login.php');
    }
}

/**
 * Check if the logged-in user has a specific role
 * @param string $role
 * @return bool
 */
function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require a specific role. If not met, redirect or show error.
 * @param string $role
 */
function require_role($role) {
    require_login();
    if (!has_role($role)) {
        // Log unauthorized access attempt here if needed
        if (has_role('admin')) {
            redirect('/admin/dashboard.php');
        } else {
            redirect('/user/dashboard.php');
        }
    }
}

/**
 * Logs out the current user and destroys the session
 */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
