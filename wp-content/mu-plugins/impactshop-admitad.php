<?php
/**
 * Plugin Name: Impactshop Admitad
 * Description: Ownership marker for the existing Impact Shop ad space.
 */

if (!defined('ABSPATH')) {
    return;
}

function impactshop_admitad_ownership_meta(): void
{
    if (wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'app.sharity.hu') {
        return;
    }
    $path = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/impactshop/' && $path !== '/impactshop') {
        return;
    }
    echo '<meta name="mitgo-verification" content="348a9888-0449-4a2e-8dbb-122bd395fe0a" />' . "\n";
}
add_action('wp_head', 'impactshop_admitad_ownership_meta');
