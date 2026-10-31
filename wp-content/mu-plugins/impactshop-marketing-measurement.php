<?php
/** Plugin Name: Impact Shop anonymous daily marketing counts (default off) */
if (!defined('ABSPATH')) {
    exit;
}

function impactshop_marketing_measurement_setting(string $name): string
{
    return trim((string) (defined($name) ? constant($name) : getenv($name)));
}

function impactshop_marketing_measurement_enabled(): bool
{
    return impactshop_marketing_measurement_setting('MARKETING_SHARITY_APP_MEASUREMENT_ENABLED') === '1'
        && impactshop_marketing_measurement_setting('MARKETING_MEASUREMENT_GATEWAY_URL') === 'https://sharity-legal-production.up.railway.app'
        && impactshop_marketing_measurement_setting('MARKETING_SHARITY_APP_MEASUREMENT_BFF_TOKEN') !== '';
}

function impactshop_marketing_measurement_route(string $path): ?string
{
    $routes = [
        '/impactshop' => 'sharity-app.impactshop', '/rolunk' => 'sharity-app.about',
        '/cegeknek' => 'sharity-app.companies', '/befektetoknek' => 'sharity-app.investors',
        '/ngo-guides' => 'sharity-app.ngo-guides', '/ngo-guides/impact-shop' => 'sharity-app.ngo-guides',
        '/ngo-guides/impact-challenge' => 'sharity-app.ngo-guides', '/ngo-guides/ngo-card' => 'sharity-app.ngo-guides',
    ];
    return $routes[preg_replace('#/$#', '', $path)] ?? null;
}

function impactshop_marketing_measurement_collect(WP_REST_Request $request): WP_REST_Response
{
    $reply = static fn(int $status, bool $accepted = false) => new WP_REST_Response(['accepted' => $accepted], $status, ['Cache-Control' => 'no-store']);
    if (!impactshop_marketing_measurement_enabled()) {
        return $reply(503);
    }
    if (($_SERVER['HTTP_HOST'] ?? '') !== 'app.sharity.hu'
        || $request->get_header('origin') !== 'https://app.sharity.hu'
        || !in_array($request->get_header('sec-fetch-site'), ['', 'same-origin'], true)
        || ($_COOKIE['__Host-impactshop-measurement-consent'] ?? '') !== 'granted-v2') {
        return $reply(403);
    }
    if (strlen($request->get_body()) > 2048) {
        return $reply(413);
    }
    if (strtolower(trim(explode(';', $request->get_header('content-type'))[0])) !== 'application/json') {
        return $reply(400);
    }
    $body = json_decode($request->get_body(), true);
    if (!is_array($body) || count($body) !== 2 || ($body['schemaVersion'] ?? null) !== 2
        || !isset($body['routeId']) || !is_string($body['routeId'])
        || !in_array($body['routeId'], ['sharity-app.impactshop', 'sharity-app.about', 'sharity-app.companies', 'sharity-app.investors', 'sharity-app.ngo-guides'], true)) {
        return $reply(400);
    }
    $response = wp_remote_post('https://sharity-legal-production.up.railway.app/api/marketing/measurement/events', [
        'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 2048,
        'headers' => [
            'Content-Type' => 'application/json', 'x-marketing-origin' => 'https://app.sharity.hu',
            'x-marketing-bff-token' => impactshop_marketing_measurement_setting('MARKETING_SHARITY_APP_MEASUREMENT_BFF_TOKEN'),
        ],
        'body' => wp_json_encode(['schemaVersion' => 2, 'routeId' => $body['routeId']]),
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 202) {
        return $reply(503);
    }
    $result = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($result) && ($result['accepted'] ?? null) === true ? $reply(202, true) : $reply(503);
}
add_action('rest_api_init', static function (): void {
    register_rest_route('impactshop-marketing/v1', '/events', [
        'methods' => 'POST', 'callback' => 'impactshop_marketing_measurement_collect', 'permission_callback' => '__return_true',
    ]);
});

function impactshop_marketing_measurement_html(string $html, string $contentType, int $status): string
{
    if ($status !== 200 || !preg_match('/^text\/html(?:\s*;|$)/i', $contentType) || stripos($html, '</body>') === false
        || stripos($html, '<html') === false || strpos($html, 'id="impactshop-marketing-script"') !== false) {
        return $html;
    }
    $src = '/wp-content/mu-plugins/impactshop-marketing-measurement.js?v=' . filemtime(__DIR__ . '/impactshop-marketing-measurement.js');
    $tag = '<script id="impactshop-marketing-script" defer src="' . esc_url($src) . '"></script>';
    return preg_replace('/<\/body>/i', $tag . '</body>', $html, 1) ?? $html;
}

// Runs before virtual guide handlers which echo a full document and exit without wp_footer.
add_action('template_redirect', static function (): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!impactshop_marketing_measurement_enabled() || ($_SERVER['HTTP_HOST'] ?? '') !== 'app.sharity.hu'
        || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !is_string($path) || !impactshop_marketing_measurement_route($path)) {
        return;
    }
    ob_start(static function (string $html): string {
        $contentType = '';
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
            }
        }
        return impactshop_marketing_measurement_html($html, $contentType, (int) http_response_code());
    });
}, 0);
