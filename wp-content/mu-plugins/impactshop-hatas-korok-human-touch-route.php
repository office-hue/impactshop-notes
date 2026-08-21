<?php
/**
 * Plugin Name: Sharity Hatás Körök Human Touch Route
 * Description: Additive production-only handoff from the legacy app route to the new Sharity experience.
 */

defined('ABSPATH') || exit;

/**
 * Redirect only the public production Hatás Körök document route.
 *
 * The destination is deliberately constant. Request query parameters, cookies,
 * profile identifiers and legacy test-mode values must never enter Location.
 */
function impactshop_hatas_korok_human_touch_redirect(): void
{
    if (is_admin()) {
        return;
    }

    if ((defined('REST_REQUEST') && REST_REQUEST)
        || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
        return;
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return;
    }

    $host = strtolower(rtrim((string) ($_SERVER['HTTP_HOST'] ?? ''), '.'));
    if ($host !== 'app.sharity.hu') {
        return;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || !preg_match('~^/hatas-korok/?$~D', $path)) {
        return;
    }

    nocache_headers();
    wp_redirect('https://sharity.hu/hatas-korok', 302, 'Sharity Hatas Korok');
    exit;
}

add_action('template_redirect', 'impactshop_hatas_korok_human_touch_redirect', 1);
