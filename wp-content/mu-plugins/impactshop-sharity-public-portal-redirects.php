<?php
/**
 * Plugin Name: Sharity Public Portal Redirects
 * Description: Additive handoff from retired app portal URLs to the public Sharity home page.
 */

defined('ABSPATH') || exit;

function impactshop_sharity_public_portal_request_matches(): bool
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }

    $host = strtolower(rtrim((string) ($_SERVER['HTTP_HOST'] ?? ''), '.'));
    if ($host !== 'app.sharity.hu') {
        return false;
    }

    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!is_string($path)) {
        return false;
    }

    return in_array(rtrim($path, '/'), [
        '/adomany-automata-portal-1',
        '/adomany-automata-portal-2',
    ], true);
}

/**
 * Hand two retired public routes to the new Sharity home page.
 *
 * The Location value is a constant: request query parameters, cookies,
 * profile identifiers and legacy campaign values are deliberately discarded.
 */
function impactshop_sharity_public_portal_redirect(): void
{
    $protectedRequest = is_admin()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (function_exists('wp_doing_ajax') && wp_doing_ajax());

    if ($protectedRequest || !impactshop_sharity_public_portal_request_matches()) {
        return;
    }

    nocache_headers();
    wp_redirect('https://sharity.hu/', 302, 'Sharity Public Portal');
    exit;
}

add_action('template_redirect', 'impactshop_sharity_public_portal_redirect', 1);
