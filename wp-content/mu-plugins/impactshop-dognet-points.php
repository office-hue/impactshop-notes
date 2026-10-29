<?php
/**
 * Plugin Name: ImpactShop Dognet Points Capture
 * Description: Bounded raw-transaction capture; economic contract remains disabled until verified.
 */
if (!defined('ABSPATH')) { exit; }

/** Raw transaction status/commission alone is not final donation authority. */
function impactshop_dognet_points_event(array $row, string $channel): array
{
    $id = (string) ($row['id'] ?? '');
    $token = (string) ($row['data1'] ?? '');
    if (!preg_match('/^sat1_[A-Za-z0-9_-]{43}$/D', $token)) { $token = ''; }
    $correlation = $token !== '' && function_exists('impactshop_sharity_affiliate_correlate')
        ? impactshop_sharity_affiliate_correlate($token) : null;
    $merchant = is_array($correlation) && ($correlation['provider_key'] ?? '') === 'dognet'
        && is_string($correlation['partner_key'] ?? null) && $correlation['partner_key'] !== ''
        ? $correlation['partner_key'] : '';
    return [
        'provider' => 'dognet',
        'namespace' => 'impactshop:dognet:' . $channel,
        'purchase_id' => $id,
        'event_id' => $id,
        'revision' => (string) ($row['rstatus'] ?? 'unknown'),
        // created_at is not verified to be original purchase time.
        'original_purchase_at' => '',
        'kind' => 'adjustment',
        'donation_amount_huf' => '',
        'provider_token' => $token,
        'merchant' => $merchant,
        'authority' => true,
        'authority_ref' => '',
        'manual_reason' => 'dognet_original_time_finality_donation_contract_unverified',
        'payload_ref' => hash('sha256', json_encode($row, JSON_UNESCAPED_SLASHES) ?: ''),
    ];
}

/**
 * Revisit the whole configured cutover window, never only newer IDs. Four
 * requests maximum per invocation. An incomplete scan is explicit, not success.
 * No automatic enabling, provider retries, status guesses or cron registration.
 */
function impactshop_dognet_points_poll(): array
{
    if ((string) get_option('sharity_points_impactshop_producer_enabled', '0') !== '1') {
        return ['success' => true, 'status' => 'disabled', 'captured' => 0];
    }
    $from = (string) get_option('impactshop_dognet_points_scan_from', '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $from)
        || strtotime($from . 'T00:00:00Z') === false
        || gmdate('Y-m-d', strtotime($from . 'T00:00:00Z')) !== $from
        || !defined('DOGNET_AD_CHANNEL_ID') || !(int) DOGNET_AD_CHANNEL_ID) {
        return ['success' => false, 'error' => 'dognet_scan_contract_unavailable'];
    }
    if (!function_exists('dognet_api_request') || !function_exists('sharity_points_impactshop_capture')) {
        return ['success' => false, 'error' => 'dognet_runtime_unavailable'];
    }
    $last = null;
    $captured = 0;
    for ($page = 0; $page < 4; ++$page) {
        $body = ['per-page' => 200, 'filter' => [
            ['created_at' => ['gte' => $from . ' 00:00:00']],
            ['created_at' => ['lte' => gmdate('Y-m-d H:i:s')]],
            ['ad_channel_id' => ['eq' => (int) DOGNET_AD_CHANNEL_ID]],
        ]];
        if ($last !== null) { $body['last_id'] = $last; }
        $response = dognet_api_request('POST', '/raw-transactions/filter', $body, false);
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => 'dognet_provider_request_failed', 'captured' => $captured];
        }
        if (!is_array($response) || (!isset($response['data']) && !isset($response['items']))) {
            return ['success' => false, 'error' => 'dognet_response_invalid', 'captured' => $captured];
        }
        $rows = $response['data'] ?? $response['items'];
        if (!is_array($rows)) { return ['success' => false, 'error' => 'dognet_response_invalid']; }
        if (!$rows) { return ['success' => true, 'status' => 'captured_manual_only', 'captured' => $captured]; }
        $next = $last ?? 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !ctype_digit((string) ($row['id'] ?? '')) || (int) $row['id'] <= 0) {
                return ['success' => false, 'error' => 'dognet_transaction_identity_unverified', 'captured' => $captured];
            }
            $result = sharity_points_impactshop_capture(impactshop_dognet_points_event($row, (string) DOGNET_AD_CHANNEL_ID));
            if (!($result['success'] ?? false)) {
                return ['success' => false, 'error' => 'dognet_capture_failed', 'captured' => $captured];
            }
            ++$captured;
            $next = max($next, (int) $row['id']);
        }
        if ($last !== null && $next <= $last) {
            return ['success' => false, 'error' => 'dognet_pagination_stalled', 'captured' => $captured];
        }
        $last = $next;
    }
    return ['success' => false, 'error' => 'dognet_scan_incomplete', 'captured' => $captured];
}
