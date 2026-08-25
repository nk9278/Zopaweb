<?php
// includes/integrations/hostinger/client.php

/**
 * HostingerClient abstraction.
 * This class isolates external API interactions, gracefully handling failures,
 * parsing responses, and throwing safe generic errors to prevent leaking stack traces.
 */
class HostingerClient {
    private $api_url = 'https://api.hostinger.com/v1'; // Standard API root constraint
    private $token;

    public function __construct($token = '') {
        $this->token = $token;
    }

    /**
     * Executes a cURL request to Hostinger securely
     */
    private function request($endpoint, $method = 'GET', $data = []) {
        if (empty($this->token)) {
            return ['success' => false, 'error' => 'Integration is not configured.'];
        }

        // Structural Mock for Testing Environments (R12 Readiness constraints)
        // If Bearer token is a mock token, we intercept and return safe data.
        if (strpos($this->token, 'mock_') === 0) {
            return $this->mock_router($endpoint, $method, $data);
        }

        // Real HTTP Client Execution
        $ch = curl_init();
        $url = $this->api_url . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Network error connecting to provider.'];
        }

        $decoded = json_decode($response, true);

        // Hostinger specific error structure handling (generic mapping for safety)
        if ($http_code >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? 'API request failed.';
            // Do not leak raw tokens or stack traces
            return ['success' => false, 'error' => $msg];
        }

        return ['success' => true, 'data' => $decoded];
    }

    /**
     * Test the API token validation seamlessly
     */
    public function test_connection() {
        // Assume /account or /ping endpoint for validation
        return $this->request('/account', 'GET');
    }

    /**
     * Get DNS Records for a Domain
     */
    public function get_dns_records($domain) {
        $clean_domain = strtolower(trim($domain));
        if (empty($clean_domain)) {
            return ['success' => false, 'error' => 'Domain parameter missing.'];
        }
        return $this->request('/dns/zones/' . urlencode($clean_domain) . '/records', 'GET');
    }

    /**
     * Query domain availability safely handling catalog logic natively explicitly
     */
    public function check_domain_availability($domain) {
        $clean_domain = strtolower(trim($domain));
        if (empty($clean_domain)) {
            return ['success' => false, 'error' => 'Domain parameter missing.'];
        }

        $res = $this->request('/domains/search?domain=' . urlencode($clean_domain), 'GET');
        return $res;
    }

    /**
     * Structural mock router isolating real infrastructure requirements securely natively.
     */
    private function mock_router($endpoint, $method, $data) {
        if (strpos($endpoint, '/account') === 0) {
            return ['success' => true, 'data' => ['status' => 'active', 'id' => 12345]];
        }

        if (strpos($endpoint, '/dns/zones/') === 0) {
            return [
                'success' => true,
                'data' => [
                    ['id' => '1', 'type' => 'A', 'name' => '@', 'content' => '192.168.1.1', 'ttl' => 3600],
                    ['id' => '2', 'type' => 'CNAME', 'name' => 'www', 'content' => 'example.com', 'ttl' => 3600]
                ]
            ];
        }

        if (strpos($endpoint, '/domains/search') === 0) {
            // Parse domain from endpoint
            preg_match('/domain=([^&]+)/', $endpoint, $matches);
            $query = $matches[1] ?? 'example.com';

            // Provide a mock catalog response seamlessly dynamically explicitly
            return [
                'success' => true,
                'data' => [
                    'domain' => $query,
                    'available' => (strlen($query) % 2 === 0), // Randomize availability gracefully
                    'price' => [
                        'currency' => 'INR',
                        'register' => 899.00,
                        'renew' => 1199.00
                    ]
                ]
            ];
        }

        return ['success' => false, 'error' => 'Mock endpoint not found.'];
    }
}
