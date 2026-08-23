<?php
// includes/host_resolver.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Normalizes a hostname by converting to lowercase and stripping port numbers
 * @param string $host
 * @return string
 */
function normalize_host($host) {
    if (empty($host)) return '';
    $host = strtolower(trim($host));
    // Remove port if present
    if (strpos($host, ':') !== false) {
        $parts = explode(':', $host);
        $host = $parts[0];
    }
    return $host;
}

/**
 * Validates a requested subdomain/slug against basic rules and reserved list
 * @param string $slug
 * @return bool
 */
function is_valid_slug($slug) {
    if (empty($slug)) return false;

    // Check against reserved list
    if (in_array($slug, RESERVED_SUBDOMAINS)) {
        return false;
    }

    // Only lowercase letters, numbers, hyphens. No leading/trailing hyphens. Max 64 chars.
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,62}[a-z0-9]$/', $slug) !== 1 && preg_match('/^[a-z0-9]$/', $slug) !== 1) {
        return false;
    }

    return true;
}

/**
 * Resolves the incoming HTTP Host to a specific website database record.
 * Supports custom domains and ZopaWeb subdomains.
 *
 * @param PDO $pdo
 * @param string $host The raw incoming HTTP_HOST
 * @return array { error: string|null, website_id: int|null, context: string|null }
 */
function resolveWebsiteFromHost($pdo, $host) {
    $normalized_host = normalize_host($host);
    $platform_domain = normalize_host(PRIMARY_PLATFORM_DOMAIN);

    // 1. Is this the primary platform domain (e.g. zopaweb.com)?
    if ($normalized_host === $platform_domain || $normalized_host === 'www.' . $platform_domain) {
        return ['error' => 'platform', 'website_id' => null, 'context' => 'platform'];
    }

    // 2. Is this a ZopaWeb subdomain? (e.g., client.zopaweb.com)
    $slug = null;
    if (str_ends_with($normalized_host, '.' . $platform_domain)) {
        $slug = str_replace('.' . $platform_domain, '', $normalized_host);

        if (!is_valid_slug($slug)) {
            return ['error' => 'invalid_subdomain', 'website_id' => null, 'context' => 'invalid'];
        }

        $stmt = $pdo->prepare("SELECT id, status, publication_status, subscription_status FROM websites WHERE website_slug = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$slug]);
        $website = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$website) {
            return ['error' => 'not_found', 'website_id' => null, 'context' => 'subdomain'];
        }

        return [
            'error' => null,
            'website_id' => (int)$website['id'],
            'status' => $website['status'],
            'publication_status' => $website['publication_status'],
            'subscription_status' => $website['subscription_status'],
            'context' => 'subdomain'
        ];
    }

    // 3. Fallback: Custom Domain Lookups (Phase 5/11 preparation)
    // Lookup domains table to see if it matches a custom domain mapped to a website
    $domain_stmt = $pdo->prepare("SELECT website_id, status FROM domains WHERE domain_name = ? AND domain_type = 'custom' LIMIT 1");
    $domain_stmt->execute([$normalized_host]);
    $custom_domain = $domain_stmt->fetch(PDO::FETCH_ASSOC);

    if ($custom_domain) {
        if ($custom_domain['status'] !== 'active') {
            return ['error' => 'domain_inactive', 'website_id' => null, 'context' => 'custom'];
        }

        $w_stmt = $pdo->prepare("SELECT id, status, publication_status, subscription_status FROM websites WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $w_stmt->execute([$custom_domain['website_id']]);
        $website = $w_stmt->fetch(PDO::FETCH_ASSOC);

        if ($website) {
            return [
                'error' => null,
                'website_id' => (int)$website['id'],
                'status' => $website['status'],
                'publication_status' => $website['publication_status'],
                'subscription_status' => $website['subscription_status'],
                'context' => 'custom'
            ];
        }
    }

    // Unrecognized host
    return ['error' => 'not_found', 'website_id' => null, 'context' => 'unknown'];
}
