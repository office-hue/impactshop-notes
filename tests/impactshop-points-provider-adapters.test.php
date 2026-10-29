<?php
/** Pure provider contract fixtures; no network, credentials, database or purchase. */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);
define('DOGNET_AD_CHANNEL_ID', 26081);
$options = [];
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function current_time($format) { return gmdate('Y-m-d H:i:s'); }
function impactshop_sharity_affiliate_correlate($token) { return $GLOBALS['correlation'] ?? null; }
function add_action(...$args) {}
function add_filter(...$args) {}
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {
    public function __construct(private string $code = '', private string $message = '', private mixed $data = []) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Response {
    public function __construct(public mixed $data = [], public int $status = 200) {}
}
class WP_REST_Request {
    public function __construct(private string $body, private array $headers) {}
    public function get_body() { return $this->body; }
    public function get_header($key) { return $this->headers[$key] ?? ''; }
}
function rest_ensure_response($value) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response($value); }
function wp_generate_uuid4() { return 'fixture-uuid'; }
function wp_upload_dir() { return []; }
function apply_filters($name, $value, ...$args) { return $name === 'impactshop_partner_rate_limit_rpm' ? 0 : $value; }
function wp_json_encode($value) { return json_encode($value); }
define('ARRAY_A', 'ARRAY_A');
$captured = [];
function sharity_points_impactshop_capture(array $event): array {
    $GLOBALS['captured'][] = $event;
    return ['success' => true];
}
$requests = [];
$pages = [];
function dognet_api_request($method, $path, $body) {
    $GLOBALS['requests'][] = [$method, $path, $body];
    return array_shift($GLOBALS['pages']) ?? ['data' => []];
}
require __DIR__ . '/../wp-content/mu-plugins/impactshop-cj.php';
require __DIR__ . '/../wp-content/mu-plugins/impactshop-partner-api.php';
require __DIR__ . '/../wp-content/mu-plugins/impactshop-dognet-points.php';
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
$token = 'sat1_' . str_repeat('a', 43);
$p = ['partner_code' => 'test-partner', 'event_id' => 'order-1', 'payment_status' => 'paid',
    'amount_gross' => 10000, 'pseudo_id' => 'untrusted123', 'timestamp' => '2026-10-29T00:00:00Z'];
$event = impactshop_partner_points_event($p, 'test-partner');
check($event['kind'] === 'adjustment' && $event['donation_amount_huf'] === '', 'Cart total must not award');
check($event['provider_token'] === '' && !isset($event['pseudo_id']), 'Raw pseudo is not attribution');
$p += ['donation_amount_huf' => '101.50', 'donation_policy_version' => 'contract-1',
    'original_purchase_id' => 'order-1', 'original_purchase_at' => '2026-10-29T00:00:00Z',
    'revision' => 1, 'attribution_token' => $token];
$event = impactshop_partner_points_event($p, 'test-partner');
check($event['kind'] === 'award' && $event['donation_amount_huf'] === '101.50', 'Exact signed donation');
check($event['namespace'] === 'impactshop:partner:test-partner' && $event['provider_token'] === $token, 'Stable scope');
check(!isset(impactshop_partner_stored_payload($p)['attribution_token']), 'New bearer token not copied into legacy storage');
$invalid = $p; $invalid['original_purchase_at'] = '2026-02-30T00:00:00Z';
check(impactshop_partner_points_event($invalid, 'test-partner')['kind'] === 'adjustment', 'Invalid calendar time cannot award');
$invalid = $p; $invalid['revision'] = []; $invalid['attribution_token'] = [];
check(impactshop_partner_points_event($invalid, 'test-partner')['kind'] === 'adjustment', 'Malformed optional fields stay manual');
check(strlen(impactshop_partner_points_event($p, 'test-partner')['event_id']) <= 128, 'Provider event identity bounded');
$p['payment_status'] = 'refunded';
check(impactshop_partner_points_event($p, 'test-partner')['kind'] === 'adjustment', 'Unspecified refund scope stays manual');
$p['refund_scope'] = 'partial';
check(impactshop_partner_points_event($p, 'test-partner')['kind'] === 'adjustment', 'Partial refund stays manual');
$p['refund_scope'] = 'full';
check(impactshop_partner_points_event($p, 'test-partner')['kind'] === 'refund', 'Explicit signed full refund mapping');
$p['donation_amount_huf'] = 101.5;
check(impactshop_partner_points_event($p, 'test-partner')['kind'] === 'adjustment', 'Float cannot become authority');
$p['payment_status'] = 'unpaid';
check(impactshop_partner_points_event($p, 'test-partner')['kind'] === 'pending', 'Pending binding survives incomplete economics');
$cj = ['commissionId' => 'c1', 'orderId' => 'o1', 'advertiserId' => '42', 'original' => true,
    'actionStatus' => 'new', 'eventDate' => '2026-10-29T00:00:00Z', 'postingDate' => '2026-10-29T01:00:00Z',
    'shopperId' => $token, 'pubCommissionAmountPubCurrency' => '999.99'];
$event = ImpactShop_CJ::points_event($cj, 'publisher:website');
check($event['kind'] === 'pending' && $event['purchase_id'] === '42:o1', 'CJ pending rooted by order');
check($event['merchant'] === '', 'Expired or unknown correlation does not invent a different merchant');
check(ImpactShop_CJ::sync_window_seconds(time(), new DateInterval('PT2H')) === 7200, 'CJ two-hour window must not collapse to zero seconds');
$zeroRejected = false;
try { ImpactShop_CJ::sync_window_seconds(time(), new DateInterval('PT0S')); } catch (RuntimeException $e) { $zeroRejected = true; }
check($zeroRejected, 'Nonpositive window rejected');
$normalize = new ReflectionMethod(ImpactShop_CJ::class, 'normalize_commission');
check($normalize->invoke(null, ['sid' => $token, 'action-id' => 'c1']) === null, 'Unresolved legacy attribution not fabricated');
check(strpos(json_encode($options['impactshop_cj_failed_queue']), $token) === false, 'CJ failure queue contains no raw bearer token');
$GLOBALS['correlation'] = ['provider_key' => 'cj', 'partner_key' => 'actual-cj-shop'];
check(ImpactShop_CJ::points_event($cj, 'publisher:website')['merchant'] === 'actual-cj-shop', 'CJ merchant is server-owned token binding');
$GLOBALS['correlation'] = ['provider_key' => 'dognet', 'partner_key' => 'wrong-provider-shop'];
check(ImpactShop_CJ::points_event($cj, 'publisher:website')['merchant'] !== 'wrong-provider-shop', 'CJ cannot borrow another provider binding');
$GLOBALS['correlation'] = ['provider_key' => 'dognet', 'partner_key' => 'actual-dognet-shop'];
check(impactshop_dognet_points_event(['id' => 1, 'data1' => $token], '26081')['merchant'] === 'actual-dognet-shop', 'Dognet merchant is token binding');
$GLOBALS['correlation'] = null;
$cj['actionStatus'] = 'locked';
check(ImpactShop_CJ::points_event($cj, 'publisher:website')['kind'] === 'adjustment', 'Locked commission is not a donation');
$cj['original'] = false; $cj['commissionId'] = 'c2';
$event = ImpactShop_CJ::points_event($cj, 'publisher:website');
check($event['purchase_id'] === '42:o1' && $event['original_purchase_at'] === '', 'Correction preserves root without inventing original time');
check($event['manual_reason'] === 'cj_delta_correction_requires_review', 'CJ delta is not full refund');
check(impactshop_dognet_points_poll()['status'] === 'disabled' && !$requests, 'Dormant lane makes no request');
$options['sharity_points_impactshop_producer_enabled'] = '1';
check(impactshop_dognet_points_poll()['error'] === 'dognet_scan_contract_unavailable' && !$requests, 'Missing scan contract makes no request');
$options['impactshop_dognet_points_scan_from'] = '2026-10-29';
$pages = [['data' => [['id' => 5, 'rstatus' => 'A', 'data1' => $token, 'commission' => '999.00']]], ['data' => []]];
$result = impactshop_dognet_points_poll();
check($result['success'] && $result['captured'] === 1, 'Bounded manual capture');
check($captured[0]['kind'] === 'adjustment' && $captured[0]['donation_amount_huf'] === '', 'Dognet commission/status cannot award');
check($requests[0][1] === '/raw-transactions/filter' && !isset($requests[0][2]['last_id']), 'Whole cutover window, supported endpoint');
$requests = [];
$pages = array_fill(0, 4, ['data' => [['id' => 5, 'rstatus' => 'A']]]);
check(impactshop_dognet_points_poll()['error'] === 'dognet_pagination_stalled', 'Stalled pagination is explicit');
$requests = []; $pages = [];
for ($i = 1; $i <= 4; $i++) { $pages[] = ['data' => [['id' => $i, 'rstatus' => 'A']]]; }
check(impactshop_dognet_points_poll()['error'] === 'dognet_scan_incomplete' && count($requests) === 4, 'Hard request bound without false completion');
$requests = []; $pages = [new WP_Error()];
check(impactshop_dognet_points_poll()['error'] === 'dognet_provider_request_failed' && count($requests) === 1, 'No provider retry');
// Execute the real signed handler: transport-idempotency rejection must precede capture.
class PartnerFixtureDatabase {
    public string $prefix = 'wp_';
    public array $idempotency = [];
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql, $mode) {
        if (str_contains($sql, 'impact_partner_config')) { return ['status' => 'active', 'allowed_event_types' => 'purchase']; }
        if (str_contains($sql, 'WHERE idempotency_key')) { return $this->idempotency; }
        throw new RuntimeException('Unexpected handler DB operation');
    }
}
$wpdb = new PartnerFixtureDatabase();
$priorSecrets = getenv('IMPACT_PARTNER_SECRETS');
putenv('IMPACT_PARTNER_SECRETS=' . json_encode(['test-partner' => ['api_key' => 'fixture-api-only', 'hmac_secret' => 'fixture-hmac-only']]));
try {
    $payload = ['partner_code' => 'test-partner', 'event_id' => 'order-new', 'event_type' => 'purchase',
        'pseudo_id' => 'untrusted123', 'ngo_code' => 'ngo-test', 'amount_gross' => 1000, 'currency' => 'HUF',
        'timestamp' => '2026-10-29T00:00:00Z', 'payment_status' => 'paid'];
    $raw = json_encode($payload);
    $headers = ['idempotency-key' => 'fixture-key', 'authorization' => 'Bearer fixture-api-only',
        'x-impact-signature' => 'sha256=' . hash_hmac('sha256', $raw, 'fixture-hmac-only'),
        'x-impact-timestamp' => (string) round(microtime(true) * 1000)];
    $wpdb->idempotency = ['proof_hash' => hash('sha256', 'different-old-body'), 'partner_code' => 'test-partner',
        'event_id' => 'order-old', 'response_json' => '{"status":"accepted"}', 'created_at' => gmdate('Y-m-d H:i:s')];
    $captured = [];
    $response = impactshop_partner_transaction(new WP_REST_Request($raw, $headers));
    check($response->status === 409 && !$captured, 'Signed conflicting idempotency body returns409 without capture');
    $wpdb->idempotency['proof_hash'] = hash('sha256', $raw);
    $response = impactshop_partner_transaction(new WP_REST_Request($raw, $headers));
    check($response->status === 200 && count($captured) === 1, 'Canonical signed replay captures before acknowledgement');
} finally {
    putenv($priorSecrets === false ? 'IMPACT_PARTNER_SECRETS' : 'IMPACT_PARTNER_SECRETS=' . $priorSecrets);
}
echo "Provider adapter fixtures passed\n";
