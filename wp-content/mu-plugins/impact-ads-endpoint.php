<?php
/**
 * WHAT: REST endpoint a NormalizedAdMetric tömb fogadására és ledger-be írására.
 * WHY: Az ads:fetch-to-ledger hívás egy közös HTTP endpointot igényel, ami ugyanazt a validációt használja, mint a CLI import.
 * HOW: POST /wp-json/impact/v1/ads-metrics (body: {metrics: [...]}) + X-Impact-Ads-Secret header; hívja impact_ledger_upsert_metric-et deduppal.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Egyszerű shared secret header az íráshoz
function impact_ads_check_secret($request) {
    $expected = getenv('IMPACT_ADS_SECRET') ?: '';
    if (empty($expected)) {
        return new WP_Error('missing_secret', 'IMPACT_ADS_SECRET nincs beállítva', ['status' => 500]);
    }
    $provided = $request->get_header('x-impact-ads-secret');
    if ($provided !== $expected) {
        return new WP_Error('forbidden', 'Invalid ads secret', ['status' => 403]);
    }
    return true;
}

// Minimális validáció (egyezik a CLI import logikával)
function impact_ads_validate_metric(array $metric)
{
    $required = ['platform', 'date', 'ledger_source', 'est_donation'];
    foreach ($required as $field) {
        if (!isset($metric[$field])) {
            return new WP_Error('missing_field', "Missing: $field");
        }
    }
    if (!in_array($metric['platform'], ['meta', 'google', 'tiktok', 'youtube', 'dognet', 'manual'], true)) {
        return new WP_Error('invalid_platform', $metric['platform']);
    }
    if (!in_array($metric['ledger_source'], ['view', 'click'], true)) {
        return new WP_Error('invalid_source', $metric['ledger_source']);
    }
    if (!is_numeric($metric['est_donation'])) {
        return new WP_Error('invalid_amount', 'est_donation must be numeric');
    }
    return true;
}

add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/ads-metrics', [
        'methods' => 'POST',
        'permission_callback' => 'impact_ads_check_secret',
        'callback' => function (WP_REST_Request $request) {
            $body = $request->get_json_params();
            $metrics = isset($body['metrics']) && is_array($body['metrics']) ? $body['metrics'] : null;
            if (!$metrics) {
                return new WP_Error('invalid_body', 'metrics array required', ['status' => 400]);
            }
            $inserted = 0;
            $skipped = 0;
            $errors = [];
            foreach ($metrics as $metric) {
                if (!is_array($metric)) {
                    $errors[] = ['error' => 'invalid_metric', 'metric' => $metric];
                    continue;
                }
                $valid = impact_ads_validate_metric($metric);
                if (is_wp_error($valid)) {
                    $errors[] = ['error' => $valid->get_error_message(), 'metric' => $metric];
                    continue;
                }
                $res = impact_ledger_upsert_metric($metric);
                if (is_wp_error($res)) {
                    $errors[] = ['error' => $res->get_error_message(), 'metric' => $metric];
                    continue;
                }
                if ($res === 0) {
                    $skipped++;
                } else {
                    $inserted++;
                }
            }
            return [
                'inserted' => $inserted,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        },
    ]);
});
