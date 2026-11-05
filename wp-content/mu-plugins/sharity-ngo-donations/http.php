<?php
/** Donation HTTP authority. No automatic migration, provider read or reward.
 * Canonical Web Session authentication owns every order lookup. Financial proof
 * remains the later signed webhook/reconciliation path, not this HTTP status.
 */
declare(strict_types=1);

function sharity_donation_order_authorize(WP_REST_Request $request): array|WP_REST_Response
{
    if (!is_callable(['ImpactShop_Sharity_Web_Session', 'authenticate_request'])) { return sharity_donation_quote_error('AUTHORITY_UNAVAILABLE', 503); }
    try { $session = ImpactShop_Sharity_Web_Session::authenticate_request($request); }
    catch (Throwable $error) { return sharity_donation_quote_error('AUTHORITY_UNAVAILABLE', 503); }
    if ($session instanceof WP_REST_Response) {
        $session->header('Cache-Control', 'private, no-store');
        $session->header('Vary', 'Authorization');
        return $session;
    }
    if (!is_array($session) || !is_string($session['subject_ref'] ?? null)
        || !preg_match('/^hmac-sha256:[a-f0-9]{64}$/D', $session['subject_ref'])) { return sharity_donation_quote_error('UNAUTHORIZED', 401); }
    return $session;
}

function sharity_donation_order_map_error(Throwable $error): WP_REST_Response
{
    $map = [
        'INVALID_REQUEST' => ['INVALID_REQUEST', 400],
        'QUOTE_NOT_FOUND' => ['ORDER_NOT_FOUND', 404],
        'ORDER_NOT_FOUND' => ['ORDER_NOT_FOUND', 404],
        'IDEMPOTENCY_CONFLICT' => ['IDEMPOTENCY_CONFLICT', 409],
        'QUOTE_ALREADY_ORDERED' => ['QUOTE_ALREADY_ORDERED', 409],
        'QUOTE_EXPIRED' => ['QUOTE_EXPIRED', 410],
        'DONATION_SESSION_WINDOW_EXPIRED' => ['SESSION_EXPIRED', 410],
    ];
    [$code, $status] = $map[$error->getMessage()] ?? ['ORDER_UNAVAILABLE', 503];
    return sharity_donation_quote_error($code, $status);
}

function sharity_donation_order_response(array $status, ?array $financial = null, ?array $payouts = null): WP_REST_Response
{
    return sharity_donation_quote_private(['ok' => true, 'order' => $status] + ($financial === null ? [] : ['financial'=>$financial]) + ($payouts === null ? [] : ['payouts'=>$payouts]),
        $status['state'] === 'reconciliation_required' ? 202 : 200);
}

function sharity_donation_order_handle(WP_REST_Request $request): WP_REST_Response
{
    $session = sharity_donation_order_authorize($request);
    if ($session instanceof WP_REST_Response) { return $session; }
    try {
        $input = $request->get_json_params();
        $keys = is_array($input) ? array_keys($input) : []; sort($keys);
        $key = $request->get_header('idempotency-key');
        if (!$request->is_json_content_type() || $keys !== ['locale', 'quote_id']
            || !is_string($input['quote_id']) || !preg_match('/^dq_[a-f0-9]{32}$/D', $input['quote_id'])
            || !in_array($input['locale'], ['hu', 'en', 'de', 'fr'], true)
            || !is_string($key) || !preg_match('/^[A-Za-z0-9._:-]{16,80}$/D', $key)) {
            return sharity_donation_quote_error('INVALID_REQUEST', 400);
        }
        $subject = $session['subject_ref'];
        $row = sharity_donation_order_prepare($subject, $input['quote_id'], $key, $input['locale'], time());
        // Historical replay must not require credentials/SDK or enabled writes.
        if ($row['state'] !== 'prepared' || get_option(SHARITY_DONATION_ORDERS_ENABLED, '0') !== '1'
            || get_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '') !== '1') {
            return sharity_donation_order_response(sharity_donation_order_status($subject, $row['order_id'], time()));
        }
        return sharity_donation_order_response(sharity_donation_order_dispatch(
            $subject, $row['order_id'], sharity_donation_runtime_adapter(), time()));
    } catch (Throwable $error) { return sharity_donation_order_map_error($error); }
}

/** Optional finance file may be absent during exact-file rollout. */
function sharity_donation_order_financial(string $subject, string $orderId, int $now): array
{
    if (is_callable('sharity_donation_financial_read')) {
        try { return sharity_donation_financial_read($subject, $orderId, $now); }
        catch (Throwable $error) { /* Preserve the owned historical order response. */ }
    }
    return ['version'=>1, 'status'=>'unavailable', 'snapshot_at_utc'=>null,
        'settlement'=>null, 'provisional_split'=>null, 'settlement_reconciled'=>false,
        'payout_verified'=>false, 'ngo_bank_received'=>false];
}

/** Optional additive owner projection; missing file preserves historical status. */
function sharity_donation_order_payouts(string $subject,string $id,int $now): array
{
    if (is_callable('sharity_donation_payout_owner')) {
        try { return sharity_donation_payout_owner($subject,$id,$now); } catch (Throwable $error) {}
    }
    return ['version'=>1,'status'=>'unavailable','records'=>[],'bank_receipt'=>false];
}

function sharity_donation_order_status_handle(WP_REST_Request $request): WP_REST_Response
{
    $session = sharity_donation_order_authorize($request);
    if ($session instanceof WP_REST_Response) { return $session; }
    try {
        // WP's merged request['id'] can be overridden by query/JSON. URL only.
        $id = $request->get_url_params()['id'] ?? null;
        if (!is_string($id) || !preg_match('/^do_[a-f0-9]{32}$/D', $id)) { return sharity_donation_quote_error('INVALID_REQUEST', 400); }
        $now = time();
        if (is_callable('sharity_donation_owned_status')) {
            $owned = sharity_donation_owned_status($session['subject_ref'], 'order_id', $id, $now);
            return sharity_donation_order_response($owned['order'], $owned['financial'], $owned['payouts']);
        }
        $status = sharity_donation_order_status($session['subject_ref'], $id, $now);
        return sharity_donation_order_response($status, ['version'=>1,'status'=>'unavailable','snapshot_at_utc'=>null,
            'settlement'=>null,'provisional_split'=>null,'settlement_reconciled'=>false,'payout_verified'=>false,'ngo_bank_received'=>false],
            ['version'=>1,'status'=>'unavailable','records'=>[],'bank_receipt'=>false]);
    } catch (Throwable $error) { return sharity_donation_order_map_error($error); }
}

function sharity_donation_order_status_by_quote_handle(WP_REST_Request $request): WP_REST_Response
{
    $session = sharity_donation_order_authorize($request);
    if ($session instanceof WP_REST_Response) { return $session; }
    try {
        // URL route param only; merged query/JSON values must never select the quote.
        $quoteId = $request->get_url_params()['quote_id'] ?? null;
        if (!is_string($quoteId) || !preg_match('/^dq_[a-f0-9]{32}$/D', $quoteId)) { return sharity_donation_quote_error('INVALID_REQUEST', 400); }
        $now = time();
        if (is_callable('sharity_donation_owned_status')) {
            $owned = sharity_donation_owned_status($session['subject_ref'], 'quote_id', $quoteId, $now);
            return sharity_donation_order_response($owned['order'], $owned['financial'], $owned['payouts']);
        }
        $status = sharity_donation_order_by_quote($session['subject_ref'], $quoteId, $now);
        return sharity_donation_order_response($status, ['version'=>1,'status'=>'unavailable','snapshot_at_utc'=>null,
            'settlement'=>null,'provisional_split'=>null,'settlement_reconciled'=>false,'payout_verified'=>false,'ngo_bank_received'=>false],
            ['version'=>1,'status'=>'unavailable','records'=>[],'bank_receipt'=>false]);
    } catch (Throwable $error) { return sharity_donation_order_map_error($error); }
}

add_action('rest_api_init', static function (): void {
    register_rest_route('sharity/v1', '/donations/checkout', [
        'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'sharity_donation_order_handle',
    ]);
    register_rest_route('sharity/v1', '/donations/status/(?P<id>do_[a-f0-9]{32})', [
        'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'sharity_donation_order_status_handle',
    ]);
    register_rest_route('sharity/v1', '/donations/status-by-quote/(?P<quote_id>dq_[a-f0-9]{32})', [
        'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'sharity_donation_order_status_by_quote_handle',
    ]);
});
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations order-schema', static function (array $args, array $assoc): void {
        if (!array_key_exists('apply', $assoc)) { WP_CLI::error('Explicit --apply required; no change made.'); return; }
        if (!sharity_donation_order_schema_apply()) { WP_CLI::error('Order schema incompatible or unconfirmed; no existing schema altered.'); return; }
        WP_CLI::success('Order schema verified. New checkout remains controlled by its separate disabled-by-default switch.');
    });
}
