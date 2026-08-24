<?php
// includes/session.php

require_once __DIR__ . '/../config/app.php';

// Apply basic security headers to backend scripts globally if not already sent
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// Configure secure session parameters before starting the session
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
$is_secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => $is_secure,
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
        redirect('/public/403.php');
    }
}

/**
 * Verify remember me token if session doesn't exist
 * @param PDO $pdo
 */
function verify_remember_me($pdo) {
    if (!is_logged_in() && isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) !== 2) {
            return;
        }
        list($selector, $validator) = $parts;

        $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires_at >= NOW()");
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            // Get user details
            $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL");
            $userStmt->execute([$token['user_id']]);
            $user = $userStmt->fetch();

            if ($user) {
                // Log them in
                regenerate_session();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];

                // Update last login
                $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
            }
        }
    }
}

/**
 * Helper to get the PDO instance inside session if needed, avoiding globals when possible
 * This is just a proxy to getDB() if it's already loaded, else it assumes caller passes PDO
 */

/**
 * Logs out the current user and destroys the session and remember me tokens
 */
function logout_user($pdo = null) {
    if ($pdo && isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            list($selector, $validator) = $parts;
            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        }
    }

    if (isset($_COOKIE['remember_me'])) {
        $is_secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_me', '', time() - 3600, '/', '', $is_secure, true);
    }

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
