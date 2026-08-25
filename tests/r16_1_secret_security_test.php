<?php

require_once __DIR__ . '/../includes/encryption.php';

echo "Running R16.1 Security Tests...\n\n";

// Set a 32-byte base64 encoded dummy key for testing
$test_key = base64_encode(random_bytes(32));
putenv("APP_KEY=$test_key");

$plaintext = "test_hostinger_secret_12345";
$encrypted = '';

try {
    $encrypted = encrypt_secret($plaintext);
    if (strpos($encrypted, 'v1:') === 0 && $encrypted !== $plaintext) {
        echo "[PASS] Encryption works and is formatted correctly.\n";
    } else {
        echo "[FAIL] Encryption format invalid or equal to plaintext.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Encryption threw exception: " . $e->getMessage() . "\n";
}

try {
    $decrypted = decrypt_secret($encrypted);
    if ($decrypted === $plaintext) {
        echo "[PASS] Decryption works and matches original plaintext.\n";
    } else {
        echo "[FAIL] Decrypted value does not match plaintext.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Decryption threw exception: " . $e->getMessage() . "\n";
}

// Test wrong key
putenv("APP_KEY=" . base64_encode(random_bytes(32)));
try {
    decrypt_secret($encrypted);
    echo "[FAIL] Decryption succeeded with wrong key.\n";
} catch (Exception $e) {
    echo "[PASS] Wrong key fails safely.\n";
}

// Test missing key
putenv("APP_KEY=");
try {
    encrypt_secret($plaintext);
    echo "[FAIL] Encryption succeeded with missing key.\n";
} catch (Exception $e) {
    echo "[PASS] Missing key fails safely.\n";
}

// Test double encryption prevention (simulate logic)
putenv("APP_KEY=$test_key");
try {
    $enc1 = encrypt_secret($plaintext);
    $is_encrypted = (strpos($enc1, 'v1:') === 0);
    if ($is_encrypted) {
        echo "[PASS] Already encrypted token detected by v1: prefix.\n";
    } else {
        echo "[FAIL] Could not detect encrypted prefix.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Prefix detection test failed.\n";
}

echo "\nTests Complete.\n";
