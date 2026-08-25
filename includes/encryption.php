<?php

/**
 * Secure Encryption Helpers
 *
 * Uses OpenSSL authenticated encryption (AES-256-GCM).
 */

function get_app_key() {
    $key = getenv('APP_KEY');
    if (!$key || empty(trim($key))) {
        throw new Exception("SECRET KEY CONFIGURATION = MANUAL PRODUCTION STEP. Environment APP_KEY missing.");
    }
    // Key should be exactly 32 bytes for AES-256 (base64 decoded or raw)
    // For this implementation, we assume it's base64 encoded and 32 bytes when decoded.
    $decoded = base64_decode($key);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new Exception("APP_KEY must be a 32-byte base64 encoded string.");
    }
    return $decoded;
}

/**
 * Encrypts a plaintext secret.
 * Format: v1:base64(iv):base64(ciphertext):base64(tag)
 */
function encrypt_secret($plaintext) {
    if (empty($plaintext)) {
        return '';
    }

    $key = get_app_key();
    $iv_len = openssl_cipher_iv_length('aes-256-gcm');
    $iv = openssl_random_pseudo_bytes($iv_len);
    $tag = "";

    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($ciphertext === false) {
        throw new Exception("Encryption failed.");
    }

    return 'v1:' . base64_encode($iv) . ':' . base64_encode($ciphertext) . ':' . base64_encode($tag);
}

/**
 * Decrypts a previously encrypted secret.
 */
function decrypt_secret($encrypted_payload) {
    if (empty($encrypted_payload)) {
        return '';
    }

    $parts = explode(':', $encrypted_payload);
    if (count($parts) !== 4 || $parts[0] !== 'v1') {
        throw new Exception("Invalid encrypted payload format.");
    }

    $iv = base64_decode($parts[1]);
    $ciphertext = base64_decode($parts[2]);
    $tag = base64_decode($parts[3]);

    $key = get_app_key();

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($plaintext === false) {
        throw new Exception("Decryption failed. Invalid key or corrupted data.");
    }

    return $plaintext;
}
