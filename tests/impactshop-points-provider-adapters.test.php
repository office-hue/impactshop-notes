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
    return ['success' => !($GLOBALS['capture_fail'] ?? false)];
}
$requests = [];
$pages = [];
function dognet_api_request($method, $path, $body, $auth = true, $raw = false) {
    $GLOBALS['requests'][] = [$method, $path, $body, $auth, $raw];
    $value = array_shift($GLOBALS['pages']) ?? ['data' => []];
    return $raw && is_array($value) ? json_encode($value) : $value;
}
function impactshop_get_shops() { return [['shop_slug'=>'actual-dognet-shop','dognet_base'=>'https://go.dognet.com/?cid=357']]; }
function dognet_extract_campaign_id_from_base($base) { parse_str(parse_url($base, PHP_URL_QUERY),$q);return $q['cid']??0; }

require __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';
function get_transient($key) { return false; }
function set_transient($key, $value, $ttl) {}
$mnb_requests = 0;
function wp_remote_get($url, $args) {
    ++$GLOBALS['mnb_requests'];
    return ['status' => ($GLOBALS['mnb_status'] ?? 200), 'body' => file_get_contents(__DIR__ . '/fixtures/impactshop-mnb-' . (str_contains($url, 'devizaSelected=EUR') ? 'eur' : 'usd') . '-20261029.html')];
}
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
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
// Dognet identity/economics are tested below with actual sale fields and snapshot database.
$GLOBALS['correlation'] = null;
$cj['actionStatus'] = 'locked';
check(ImpactShop_CJ::points_event($cj, 'publisher:website')['kind'] === 'adjustment', 'Locked commission is not a donation');
$cj['original'] = false; $cj['commissionId'] = 'c2';
$event = ImpactShop_CJ::points_event($cj, 'publisher:website');
check($event['purchase_id'] === '42:o1' && $event['original_purchase_at'] === '', 'Correction preserves root without inventing original time');
check($event['manual_reason'] === 'cj_delta_correction_requires_review', 'CJ delta is not full refund');
// Original CJ finality, exact USD source, frozen quote, and full delta evidence.
check(ImpactShop_CJ::points_poll()['status'] === 'disabled', 'CJ off makes no provider request');
$options['sharity_points_impactshop_cutover_utc'] = '2026-10-26 00:00:00';
$cj = ['commissionId' => 'c1', 'originalActionId' => 'original-action1', 'orderId' => 'o1', 'advertiserId' => '42',
    'original' => true, 'actionType' => 'sim_sale', 'actionStatus' => 'locked',
    'eventDate' => '2026-10-27T00:00:00Z', 'postingDate' => '2026-10-27T01:00:00Z',
    'lockingDate' => '2026-10-28T12:00:00Z', 'shopperId' => $token,
    'pubCommissionAmountUsd' => '10.00', 'pubCommissionAmountPubCurrency' => '999999.99', 'saleAmountUsd' => '100'];
$award = ImpactShop_CJ::points_event($cj, 'publisher:website');
check($award['kind'] === 'award' && $award['donation_amount_huf'] === '1650', 'CJ final USD commission ×50%× MNB once');
check(str_contains($award['authority_ref'], ':329.93/1') && $mnb_requests === 1, 'Exact rate and quote day retained');
class AwardSnapshotDatabase {
    public string $prefix = 'wp_'; public string $last_error = ''; public string $snapshot = '';
    public array $snapshots = []; public array $history = []; public array $args = []; public bool $owns = true;
    public function prepare($sql, ...$args) { $this->args=$args; return $sql; }
    public function get_var($sql) {
        if (str_contains($sql,'IS_USED_LOCK')) return $this->owns ? 17 : 0;
        if (str_contains($sql,'GET_LOCK') || str_contains($sql,'RELEASE_LOCK')) return 1;
        if (str_contains($sql,'CONNECTION_ID')) return 17;
        if (str_contains($sql,'logical_id IN')) { foreach($this->args as $id){if(isset($this->history[$id])) return $this->history[$id];}return null; }
        return $this->snapshots[$this->args[0] ?? ''] ?? $this->snapshot;
    }
}
$wpdb = new AwardSnapshotDatabase(); $wpdb->snapshot = json_encode($award);
$cj['actionStatus'] = 'closed';
check(ImpactShop_CJ::points_event($cj, 'publisher:website') === $award && $mnb_requests === 1, 'Locked→closed repeats frozen semantic event without quote fetch');
$changed = $cj; $changed['pubCommissionAmountUsd'] = '11';
check(ImpactShop_CJ::points_event($changed, 'publisher:website')['manual_reason'] === 'cj_final_changed', 'Changed final commission stays manual');
$refund = $cj; $refund['original'] = false; $refund['commissionId'] = 'correction1';
$refund['pubCommissionAmountUsd'] = '-10'; $refund['saleAmountUsd'] = '-100.00';
$reversal = ImpactShop_CJ::points_event($refund, 'publisher:website');
check($reversal['kind'] === 'refund' && $reversal['original_purchase_at'] === $award['original_purchase_at'], 'Full linked correction preserves original time and needs no FX');
$refund['saleAmountUsd'] = '-50';
check(ImpactShop_CJ::points_event($refund, 'publisher:website')['kind'] === 'adjustment', 'Partial sale refund cannot be full reversal');
$refund['saleAmountUsd'] = '-100'; $refund['originalActionId'] = 'different';
check(ImpactShop_CJ::points_event($refund, 'publisher:website')['kind'] === 'adjustment', 'Different original action cannot reverse');
$wpdb->snapshot = ''; $cj['pubCommissionAmountUsd'] = 10.0;
check(ImpactShop_CJ::points_event($cj, 'publisher:website')['kind'] === 'adjustment', 'CJ float is never donation authority');
$cj['pubCommissionAmountUsd'] = '10'; $cj['eventDate'] = '2026-10-25T00:00:00Z';
check(ImpactShop_CJ::points_event($cj, 'publisher:website')['kind'] === 'adjustment' && $mnb_requests === 1, 'Historical purchase makes no FX request');
unset($wpdb);
check(impactshop_dognet_points_poll()['status'] === 'disabled' && !$requests, 'Dormant lane makes no request');
$options['sharity_points_impactshop_producer_enabled'] = '1';
check(impactshop_dognet_points_poll()['status'] === 'disabled' && !$requests, 'Empty scan switch makes no request');
$options['impactshop_dognet_points_scan_from'] = '2026-10-26';
$pages = [['data' => [['id' => 5, 'rstatus' => 'A', 'data1' => $token, 'commission' => '999.00']]], ['data' => []]];
$options['impactshop_dognet_points_channel_id'] = '26081';
$wpdb = new AwardSnapshotDatabase();
$GLOBALS['correlation'] = ['provider_key'=>'dognet','partner_key'=>'actual-dognet-shop','redirected_at'=>'2026-10-28 08:00:00'];
$row = ['id'=>'100','campaign_id'=>'357','ad_channel_id'=>'26081','order_id'=>'order-1',
    'data1'=>$token,'rstatus'=>'P','rtype'=>'S','created_at'=>'2026-10-28 10:00:00',
    'commission'=>'10.0000','total_cost'=>'100.0000','date_approved'=>'2026-10-28 14:00:00'];
function dognet_snapshot($event) {
    $event['token_digest'] = hash('sha256',$event['provider_token']);
    unset($event['provider_token']);
    $event['root_id']=hash('sha256',json_encode(['dognet',$event['namespace'],$event['purchase_id']]));
    $GLOBALS['wpdb']->snapshots[$event['root_id']] = json_encode($event);
    $logical=hash('sha256',json_encode(['dognet',$event['namespace'],$event['event_id'],$event['revision']]));
    $GLOBALS['wpdb']->history[$logical] ??= json_encode($event);
}
$pending=impactshop_dognet_points_event($row,'26081');
check($pending['kind']==='pending' && $pending['original_purchase_at']==='2026-10-28 08:00:00','Pending persists post-cutover SAT1 lower bound');
$changed=$row;$changed['commission']='12';$changed['total_cost']='120';
check(impactshop_dognet_points_event($changed,'26081')===$pending,'Provisional pending economics never cause conflict');
dognet_snapshot($pending); $GLOBALS['correlation']=null;
$row['rstatus']='A';$award=impactshop_dognet_points_event($row,'26081');
check($award['kind']==='award' && $award['donation_amount_huf']==='2000','Expired correlation uses stored binding, exact EUR ×50% ×400');
dognet_snapshot($award);$before=$mnb_requests;
$changed=$row;$changed['commission']='10.00';$changed['updated_at']='volatile';
check(impactshop_dognet_points_event($changed,'26081')===$award && $before===$mnb_requests,'Same final money frozen, no FX or metadata conflict');
$changed['commission']='11';check(impactshop_dognet_points_event($changed,'26081')['manual_reason']==='dognet_final_changed','Changed final money manual');
$changed=$row;$changed['id']='101';check(impactshop_dognet_points_event($changed,'26081')['manual_reason']==='dognet_binding_changed','Multiple raw records for one order manual');
$changed=$row;$changed['rstatus']='D';check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','D is not proof of full refund');
$changed=$row;$changed['data1']='sat1_'.str_repeat('b',43);check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','Token cannot be rebound');
$wpdb->snapshots=[]; $wpdb->history=[]; $GLOBALS['correlation']=['provider_key'=>'dognet','partner_key'=>'actual-dognet-shop','redirected_at'=>'2026-10-28 08:00:00'];
check(impactshop_dognet_points_event($row,'26081')['kind']==='award','First seen approved sale eligible');
$changed=$row;$changed['commission']=10.0;check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','Decoded float never money authority');
$changed=$row;$changed['total_cost']='0';check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','CPC approved commission not purchase');
$changed['rstatus']='P';check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','Zero-sale pending cannot reserve an eligible purchase');
$changed=$row;$changed['rstatus']='P';$changed['order_id']='';$badP=impactshop_dognet_points_event($changed,'26081');dognet_snapshot($badP);
check(impactshop_dognet_points_event($row,'26081')['manual_reason']==='dognet_identity_changed','Manual unresolved P cannot escape into new valid order root');
$wpdb->snapshots=[];$wpdb->history=[];
$changed=$row;$changed['campaign_id']='897';check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','Campaign must match trusted intent merchant');
$large=$row;$large['id']='990';$large['rstatus']='P';$large['order_id']=str_repeat('á',90);
check(impactshop_dognet_points_event($large,'26081')['manual_reason']==='dognet_basis_too_large','Oversize Unicode pending basis explicit manual, no silent loss');
$changed=$row;$changed['date_approved']='2026-10-28 09:00:00';check(impactshop_dognet_points_event($changed,'26081')['kind']==='adjustment','Finality cannot precede creation');
check(impactshop_dognet_points_utc('2026-10-25 02:30:00')==='' && impactshop_dognet_points_utc('2026-03-29 02:30:00')==='','Ambiguous and nonexistent local DST times rejected');
check(impactshop_dognet_points_utc('2026-10-28 10:00:00')==='2026-10-28 09:00:00','Provider account timezone converted');
$wire='{"data":[{"id":100,"commission":0.073400,"note":"123 \\"commission\\":0.1"}],"meta":{"last_id":100}}';
check(impactshop_dognet_points_decode($wire)['data'][0]['commission']==='0.073400','Wire decimals exact, quoted numeric content preserved');
try { impactshop_dognet_points_decode('{bad'); check(false,'Invalid wire must throw'); } catch (JsonException $e) {}
$days=['2026-10-24'=>true,'2026-10-25'=>true,'2026-10-26'=>true,'2026-10-27'=>true];
try { impactshop_dognet_points_event($row,'26081',$days);check(false,'Fifth FX day must defer'); } catch(RuntimeException $e){check($e->getMessage()==='dognet_fx_budget','FX budget explicit');}
$rows=[];for($i=100;$i<300;$i++){ $x=$row;$x['id']=(string)$i;$x['order_id']='order-'.$i;$x['rstatus']='P';$rows[]=$x; }
$pages=[['data'=>$rows,'meta'=>['last_id'=>299]],['data'=>[]]];$requests=[];
$result=impactshop_dognet_points_poll();
check($result['success'] && $result['status']==='completed' && count($requests)===2,'Explicit ascending scan completes only on empty page');
check($requests[0][2]['sort']==='id' && $requests[1][2]['last_id']===299 && $requests[0][3]===false && $requests[0][4]===true,'Documented cursor with exact no-refresh transport');
$pages=[];for($i=0;$i<4;$i++){$x=$row;$x['id']=(string)(400+$i);$x['rstatus']='P';$pages[]=['data'=>[$x],'meta'=>['last_id'=>400+$i]];}
$requests=[];$result=impactshop_dognet_points_poll();
check($result['status']==='in_progress' && count($requests)===4 && $options['impactshop_dognet_points_scan']['last_id']===403,'Four request bound with durable resume');
$pages=[['data'=>[]]];$requests=[];impactshop_dognet_points_poll();check($requests[0][2]['last_id']===403,'Next cron resumes instead of starving old pages');
$pages=[['data'=>[$row,$row],'meta'=>['last_id'=>100]]];check(impactshop_dognet_points_poll()['error']==='dognet_pagination_stalled','Nonascending page explicit error');
$pages=[new WP_Error('rate_limit','429')];$requests=[];
check(impactshop_dognet_points_poll()['error']==='dognet_provider_request_failed' && count($requests)===1,'Provider failure no retry');
$wpdb->owns=false;$pages=[['data'=>[]]];check(impactshop_dognet_points_poll()['error']==='dognet_lock_lost','Lost network-era lock cannot advance scan');$wpdb->owns=true;
$options['impactshop_dognet_points_scan_from']='';$requests=[];check(impactshop_dognet_points_poll()['status']==='disabled' && !$requests,'Own scan switch disables without HTTP');
// The real CJ poll must revisit locking dates and retain a partially completed page.
function sharity_points_impactshop_cutover() { return get_option('sharity_points_impactshop_cutover_utc'); }
$graphql_requests = []; $graphql_pages = [];
function wp_remote_post($url, $args) {
    check($url === 'https://commissions.api.cj.com/query', 'No guessed CJ endpoint');
    $GLOBALS['graphql_requests'][] = json_decode($args['body'], true);
    return ['status' => 200, 'body' => json_encode(['data' => ['publisherCommissions' => array_shift($GLOBALS['graphql_pages'])]])];
}
$prior_cj_env = [];
foreach (['CJ_PUBLISHER_ID' => 'fixture-publisher', 'CJ_WEBSITE_ID' => 'fixture-website', 'CJ_PUBLISHER_PAT' => 'fixture-not-a-real-token'] as $key => $value) {
    $prior_cj_env[$key] = getenv($key); putenv($key . '=' . $value);
}
try {
    $options['impactshop_cj_points_enabled'] = '1';
    $since = time() - 1800;
    $options['impactshop_cj_points_scan'] = ['posting' => ['since' => $since], 'locking' => ['since' => $since]];
    $graphql_pages = array_fill(0, 2, ['records' => [], 'payloadComplete' => true]);
    check(ImpactShop_CJ::points_poll()['success'] && count($graphql_requests) === 2, 'Two bounded pages, no unbounded polling');
    check(str_contains($graphql_requests[0]['query'], 'sincePostingDate:') && str_contains($graphql_requests[1]['query'], 'sinceLockingDate:'), 'Posting-only scan cannot miss in-place locking');
    check(str_contains($graphql_requests[1]['query'], 'originalActionId') && str_contains($graphql_requests[1]['query'], 'saleAmountUsd'), 'Exact refund evidence requested');
    $options['impactshop_cj_points_scan'] = ['posting' => ['since' => $since], 'locking' => ['since' => $since]];
    $graphql_pages = [['records' => [], 'payloadComplete' => false, 'maxCommissionId' => 'page2'], ['records' => [], 'payloadComplete' => true]];
    check(ImpactShop_CJ::points_poll()['success'], 'Incomplete page can persist its cursor');
    $saved_scan = $options['impactshop_cj_points_scan'];
    check($saved_scan['posting']['cursor'] === 'page2' && $saved_scan['posting']['since'] === $since, 'Pagination never advances window prematurely');
    $graphql_pages = [['records' => [], 'payloadComplete' => false, 'maxCommissionId' => 'page2']];
    check(!ImpactShop_CJ::points_poll()['success'] && $options['impactshop_cj_points_scan'] === $saved_scan, 'Stalled pagination is visible with state retained');
    $options['impactshop_cj_points_scan'] = ['posting' => ['since' => $since], 'locking' => ['since' => $since]];
    $before = $options['impactshop_cj_points_scan'];
    $pending_record = $cj; $pending_record['original'] = true; $pending_record['actionStatus'] = 'new';
    $graphql_pages = [['records' => [$pending_record], 'payloadComplete' => true]];
    $capture_fail = true;
    check(!ImpactShop_CJ::points_poll()['success'] && $options['impactshop_cj_points_scan'] === $before, 'Failed durable capture retains provider cursor');
    $capture_fail = false;
    $options['impactshop_cj_points_enabled'] = '0';
    $graphql_pages = [['records' => [[]], 'payloadComplete' => true]];
    $captured = []; $before_mnb = $mnb_requests;
    $legacy_sync = new ReflectionMethod(ImpactShop_CJ::class, 'sync_ledger');
    $legacy_result = $legacy_sync->invoke(null, 'PT2H', null, 'replace');
    check(!$captured && $mnb_requests === $before_mnb && isset($options['impactshop_cj_last_cursor']), 'Disabled CJ points does not capture/fetch FX or stop the legacy ledger cursor');
} finally {
    foreach ($prior_cj_env as $key => $value) { putenv($value === false ? $key : $key . '=' . $value); }
    $options['impactshop_cj_points_enabled'] = '0';
}
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
