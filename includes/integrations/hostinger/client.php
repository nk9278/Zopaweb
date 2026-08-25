<?php

require_once __DIR__ . '/../../encryption.php';

class HostingerClient {
    private $token;

    public function __construct() {
        // Read the encrypted token from the mock DB storage
        $file = __DIR__ . '/../../../storage/hostinger_api_token.txt';
        $encrypted_payload = file_exists($file) ? file_get_contents($file) : null;

        if (!$encrypted_payload) {
            throw new Exception("Hostinger API token is not configured.");
        }

        // At runtime, decrypt it strictly for use in memory
        // If it's a legacy token and migration hasn't happened due to missing key, this might fail,
        // but it will fail securely because decrypt_secret expects 'v1:'
        try {
             $this->token = decrypt_secret($encrypted_payload);
        } catch (Exception $e) {
             throw new Exception("Failed to decrypt Hostinger API token. " . $e->getMessage());
        }
    }

    /**
     * Helper to get the correct Authorization header.
     * The raw token is NEVER returned directly, only the formatted header.
     */
    public function getAuthHeader() {
        if (empty($this->token)) {
             throw new Exception("API Token is empty.");
        }
        return 'Authorization: Bearer ' . $this->token;
    }

    /**
     * Ensures token is wiped from memory when object is destroyed
     */
    public function __destruct() {
        $this->token = null;
    }
}
