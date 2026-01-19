<?php
/**
 * Plugin Name: ImpactShop Identity PIN Metrics
 * Description: Prometheus-style metrics for PIN system.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register PIN metrics endpoint.
 */
add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/identity/pin/metrics', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'impactshop_pin_metrics',
        'permission_callback' => 'impactshop_pin_metrics_permission',
    ]);
});

/**
 * Metrics access control based on allowed IPs.
 *
 * @return bool
 */
function impactshop_pin_metrics_permission(): bool
{
    $allowed = getenv('METRICS_ALLOWED_IPS');
    $allowed_ips = $allowed ? array_filter(array_map('trim', explode(',', $allowed))) : ['127.0.0.1'];
    $client_ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

    return in_array($client_ip, $allowed_ips, true);
}

/**
 * Return Prometheus-style metrics output.
 *
 * @return WP_REST_Response
 */
function impactshop_pin_metrics(): WP_REST_Response
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_pin_tokens';

    $active = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE used_at IS NULL AND expires_at > NOW()"
    );
    $used_24h = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE used_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    $locked = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE locked_until IS NOT NULL AND locked_until > NOW()"
    );
    $expired = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE used_at IS NULL AND expires_at < NOW()"
    );

    $output = "# HELP impactshop_pin_active Active (unused, not expired) PINs\n";
    $output .= "# TYPE impactshop_pin_active gauge\n";
    $output .= "impactshop_pin_active $active\n";
    $output .= "# HELP impactshop_pin_used_24h PINs used in last 24 hours\n";
    $output .= "# TYPE impactshop_pin_used_24h counter\n";
    $output .= "impactshop_pin_used_24h $used_24h\n";
    $output .= "# HELP impactshop_pin_locked Locked PINs\n";
    $output .= "# TYPE impactshop_pin_locked gauge\n";
    $output .= "impactshop_pin_locked $locked\n";
    $output .= "# HELP impactshop_pin_expired Expired unused PINs\n";
    $output .= "# TYPE impactshop_pin_expired gauge\n";
    $output .= "impactshop_pin_expired $expired\n";

    $response = new WP_REST_Response($output, 200);
    $response->header('Content-Type', 'text/plain; version=0.0.4');
    return $response;
}
