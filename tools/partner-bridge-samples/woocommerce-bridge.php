<?php
/**
 * WooCommerce webhook forwarder (sample)
 * Place in a custom plugin and set Woo webhook target to this endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('impactshop/v1', '/woo-webhook', [
        'methods' => 'POST',
        'callback' => 'impactshop_woo_webhook_forward',
        'permission_callback' => '__return_true',
    ]);
});

function impactshop_woo_webhook_forward($request)
{
    $body = $request->get_body();
    $timestamp = (string) (int) round(microtime(true) * 1000);
    $signature = impactshop_partner_hmac('POST', '/impact/v1/partner/transaction', $body, $timestamp, getenv('IMPACT_PARTNER_SECRET'));

    $response = wp_remote_post(getenv('IMPACT_API_BASE'), [
        'headers' => [
            'Authorization' => 'Bearer ' . getenv('IMPACT_PARTNER_KEY'),
            'Content-Type' => 'application/json',
            'X-Impact-Signature' => $signature,
            'X-Impact-Timestamp' => $timestamp,
            'Idempotency-Key' => wp_generate_uuid4(),
        ],
        'body' => $body,
        'timeout' => 5,
    ]);

    return new WP_REST_Response([
        'status' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response),
    ], 200);
}

function impactshop_partner_hmac($method, $path, $body, $timestamp, $secret)
{
    $base = $method . "\n" . $path . "\n" . $body . "\n" . $timestamp;
    return 'sha256=' . hash_hmac('sha256', $base, $secret);
}
