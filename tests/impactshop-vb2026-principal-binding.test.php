<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$browser_pseudo = '';

class WP_REST_Request
{
    private array $headers;

    public function __construct(array $headers = [])
    {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function get_header(string $name): string
    {
        return (string) ($this->headers[strtolower($name)] ?? '');
    }
}

function add_action(...$args): void {}
function add_filter(...$args): void {}
function sanitize_text_field(string $value): string { return trim($value); }
function wp_unslash(string $value): string { return $value; }
function get_option(string $key, $default = false) { return $key === 'impactshop_factlens_bridge_service_token' ? 'service-secret' : $default; }
function wp_salt(string $scheme): string { return 'fallback-secret'; }
function sharity_points_get_pseudo_from_cookie(): string { global $browser_pseudo; return $browser_pseudo; }
function impactshop_identity_profile_valid_pseudo(string $pseudo): bool { return preg_match('/^[a-z0-9]{10,12}$/', $pseudo) === 1; }

require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pseudo_a = 'aaaaaaaaaa';
$pseudo_b = 'bbbbbbbbbb';
$browser_pseudo = $pseudo_a;

$service = new WP_REST_Request([
    'Authorization' => 'Bearer service-secret',
    'X-Sharity-Pseudo-Id' => $pseudo_b,
]);
$resolved = impactshop_vb2026_resolve_request_pseudo($service, true);
test_assert($resolved === ['pseudo_id' => $pseudo_b, 'service_auth' => true], 'service header must override browser cookie principal');

$service_only = new WP_REST_Request([
    'Authorization' => 'Bearer service-secret',
    'X-Sharity-Pseudo-Id' => $pseudo_b,
]);
$browser_pseudo = '';
test_assert(impactshop_vb2026_resolve_request_pseudo($service_only, true) === ['pseudo_id' => $pseudo_b, 'service_auth' => true], 'service-only principal must resolve');

$browser_pseudo = $pseudo_a;
$invalid = new WP_REST_Request([
    'Authorization' => 'Bearer wrong-secret',
    'X-Sharity-Pseudo-Id' => $pseudo_b,
]);
test_assert(impactshop_vb2026_resolve_request_pseudo($invalid, true) === ['pseudo_id' => '', 'service_auth' => false], 'invalid bearer must not fall back to cookie');

$malformed = new WP_REST_Request(['Authorization' => 'Basic service-secret']);
test_assert(impactshop_vb2026_resolve_request_pseudo($malformed, true) === ['pseudo_id' => '', 'service_auth' => false], 'malformed auth must not fall back to cookie');

$browser = new WP_REST_Request();
test_assert(impactshop_vb2026_resolve_request_pseudo($browser, true) === ['pseudo_id' => $pseudo_a, 'service_auth' => false], 'browser-only principal must use cookie');

echo "impactshop VB2026 principal binding: PASS\n";
