<?php
// includes/security.php

/**
 * Generate a CSRF token and store it in the session
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field with the CSRF token
 */
function csrf_field() {
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify a provided CSRF token against the one in the session
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape a string for safe output in HTML
 * @param string $string
 * @return string
 */
function escape($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize an email address
 * @param string $email
 * @return string
 */
function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Validate an email address
 * @param string $email
 * @return bool
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Log a failed login attempt for rate limiting
 * @param PDO $pdo
 * @param string $email
 */
function log_failed_login($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
    $stmt->execute([$ip, $email]);
}

/**
 * Check if the current IP/Email is rate limited
 * @param PDO $pdo
 * @param string $email
 * @return bool
 */
function is_rate_limited($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Allow max 5 attempts in the last 15 minutes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE (ip_address = ? OR email = ?)
        AND attempt_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$ip, $email]);
    $attempts = $stmt->fetchColumn();

    return $attempts >= 5;
}

/**
 * Clear failed login attempts after a successful login
 * @param PDO $pdo
 * @param string $email
 */
function clear_login_attempts($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR email = ?");
    $stmt->execute([$ip, $email]);
}

/**
 * Output standardized security headers to protect against Clickjacking, MIME sniffing, and XSS
 */
function send_security_headers() {
    if (!headers_sent()) {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Prevent framing (Clickjacking)
        header('X-Frame-Options: SAMEORIGIN');

        // Privacy conscious referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy - Allows Google Fonts, inline styles for dynamic themes, and self images
        // We cannot use default-src 'none' because it breaks template CDNs and Unsplash placeholders.
        $csp = "default-src 'self'; " .
               "img-src 'self' data: https:; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
               "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
               "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "connect-src 'self'; " .
               "frame-ancestors 'self';";

        header("Content-Security-Policy: " . $csp);
    }
}
/**
 * Global API Request Throttler
 * @param PDO $pdo
 * @param string $endpoint The context of the rate limit (e.g., 'lead_form', 'media_upload', 'login')
 * @param int $max_requests Maximum allowed requests
 * @param string $interval MySQL Interval String (e.g., '15 MINUTE', '1 HOUR')
 * @return bool True if rate limit is exceeded, False if safe.
 */
function check_api_rate_limit($pdo, $endpoint, $max_requests, $interval) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Clean old logs sporadically (1% chance to keep table lightweight)
    if (rand(1, 100) === 1) {
        $pdo->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }

    // Insert current request
    $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, ?)");
    $stmt->execute([$ip, $endpoint]);

    // Check total requests in window
    $check = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$interval})");
    $check->execute([$ip, $endpoint]);
    $count = (int)$check->fetchColumn();

    if ($count > $max_requests) {
        return true; // Exceeded
    }
    return false; // Safe
}