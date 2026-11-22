<?php

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$impactshopTestOptions = [];
$impactshopTestRoutes = [];

class WP_Error
{
    public $code;
    public function __construct($code)
    {
        $this->code = $code;
    }
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

function sanitize_title($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9-]+/', '-', $value);
    return trim($value, '-');
}

function get_option($name, $default = false)
{
    global $impactshopTestOptions;
    return array_key_exists($name, $impactshopTestOptions) ? $impactshopTestOptions[$name] : $default;
}

function update_option($name, $value)
{
    global $impactshopTestOptions;
    $impactshopTestOptions[$name] = $value;
    return true;
}

function wp_salt()
{
    return str_repeat('w', 48);
}

function wp_next_scheduled()
{
    return false;
}

function wp_schedule_event()
{
    return true;
}

function add_filter()
{
}

function add_action()
{
}

function dbDelta()
{
    return true;
}

function register_rest_route($namespace, $route, $definition)
{
    global $impactshopTestRoutes;
    $impactshopTestRoutes[$namespace . $route] = $definition;
}

function home_url($path = '')
{
    return 'https://app.sharity.hu' . $path;
}

class WP_REST_Response
{
    private $data;
    private $status;
    private $headers = [];

    public function __construct($data, $status)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function header($name, $value)
    {
        $this->headers[strtolower($name)] = $value;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function get_headers()
    {
        return $this->headers;
    }
}

class ImpactshopAffiliateFakeRequest
{
    private $headers;
    private $params;
    private $payload;

    public function __construct($headers = [], $params = [], $payload = [])
    {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->params = $params;
        $this->payload = $payload;
    }

    public function get_header($name)
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function get_param($name)
    {
        return $this->params[$name] ?? null;
    }

    public function get_json_params()
    {
        return $this->payload;
    }
}

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

class ImpactshopAffiliateFakeWpdb
{
    public $prefix = 'wp_';
    public $rows = [];
    public $sessions = [];
    public $missingSchemaColumn = '';

    public function get_charset_collate()
    {
        return '';
    }

    public function prepare($query, ...$args)
    {
        return ['query' => $query, 'args' => $args];
    }

    public function get_var($prepared)
    {
        if (strpos($prepared['query'], 'SHOW COLUMNS') !== false) {
            $column = $prepared['args'][0] ?? '';
            return $column === $this->missingSchemaColumn ? null : $column;
        }
        return $prepared['args'][0] ?? null;
    }

    public function insert($table, $data)
    {
        foreach ($this->rows as $row) {
            if (
                $row['activation_id'] === $data['activation_id']
                || $row['provider_token_hash'] === $data['provider_token_hash']
                || $row['request_key_hash'] === $data['request_key_hash']
                || (!empty($row['handoff_token_hash']) && !empty($data['handoff_token_hash'])
                    && $row['handoff_token_hash'] === $data['handoff_token_hash'])
            ) {
                return false;
            }
        }
        $this->rows[$data['activation_id']] = $data;
        return 1;
    }

    public function get_row($prepared)
    {
        $query = $prepared['query'];
        $needle = $prepared['args'][0] ?? '';
        if (strpos($query, 'SHOW COLUMNS') !== false) {
            return $needle === $this->missingSchemaColumn ? null
                : ['Field' => $needle, 'Type' => $needle === 'provider_scope_ref' ? 'varchar(96)' : 'char(64)', 'Null' => 'YES'];
        }
        if (strpos($query, 'impact_sharity_web_sessions') !== false) {
            return $this->sessions[$needle] ?? null;
        }
        foreach ($this->rows as $row) {
            if (strpos($query, 'request_key_hash = %s') !== false && $row['request_key_hash'] === $needle) {
                return [
                    'activation_id' => $row['activation_id'],
                    'status' => $row['status'],
                    'intent_expires_at' => $row['intent_expires_at'],
                ];
            }
            if (
                strpos($query, 'handoff_token_hash = %s') !== false
                && ($row['handoff_token_hash'] ?? '') === $needle
            ) {
                return array_intersect_key($row, array_flip([
                    'activation_id', 'status', 'intent_expires_at', 'partner_key',
                    'provider_key', 'provider_program_ref', 'provider_scope_ref', 'source_placement',
                ]));
            }
            if (
                strpos($query, 'provider_token_hash = %s') !== false
                && $row['provider_token_hash'] === $needle
                && $row['status'] === 'redirected'
                && $row['delete_after'] > ($prepared['args'][1] ?? '')
            ) {
                return array_intersect_key($row, array_flip([
                    'activation_id', 'subject_ref', 'ngo_ref', 'partner_key', 'provider_key',
                    'provider_program_ref', 'source_placement', 'redirected_at', 'delete_after',
                ]));
            }
        }
        return null;
    }

    public function query($prepared)
    {
        if (is_string($prepared)) {
            return in_array($prepared, ['START TRANSACTION', 'COMMIT', 'ROLLBACK'], true) ? true : 0;
        }
        $query = $prepared['query'];
        $args = $prepared['args'];
        if (strpos($query, "SET status = 'redirected'") !== false) {
            $cjHandoff = strpos($query, 'handoff_token_hash = %s') !== false;
            $expectedStatus = 'ready_to_redirect'; $expectedSource = null;
            if ($cjHandoff && strpos($query, 'AND status = %s') !== false) {
                [$redirectedAt, $deleteAfter, $activationId, $handoffHash, $expectedStatus, $expectedSource, $now] = $args;
            } elseif ($cjHandoff) {
                [$redirectedAt, $deleteAfter, $activationId, $handoffHash, $now] = $args;
            } else {
                [$redirectedAt, $deleteAfter, $activationId, $now] = $args;
                $handoffHash = null;
            }
            if (
                !isset($this->rows[$activationId])
                || $this->rows[$activationId]['status'] !== $expectedStatus
                || ($expectedSource !== null && $this->rows[$activationId]['source_placement'] !== $expectedSource)
                || $this->rows[$activationId]['intent_expires_at'] <= $now
                || ($cjHandoff && ($this->rows[$activationId]['handoff_token_hash'] ?? '') !== $handoffHash)
            ) {
                return 0;
            }
            $this->rows[$activationId]['status'] = 'redirected';
            $this->rows[$activationId]['redirected_at'] = $redirectedAt;
            $this->rows[$activationId]['delete_after'] = $deleteAfter;
            return 1;
        }
        if (strpos($query, "SET status = 'expired'") !== false) {
            $count = 0;
            foreach ($this->rows as &$row) {
                if (in_array($row['status'], ['ready_to_redirect', 'confirmed_cj_ready'], true) && $row['intent_expires_at'] <= $args[0]) {
                    $row['status'] = 'expired';
                    $count++;
                }
            }
            unset($row);
            return $count;
        }
        if (strpos($query, 'DELETE FROM') !== false) {
            $count = 0;
            foreach ($this->rows as $activationId => $row) {
                $staleIntent = in_array($row['status'], ['expired', 'blocked'], true)
                    && $row['intent_expires_at'] <= $args[0];
                $staleRedirect = $row['status'] === 'redirected' && $row['delete_after'] <= $args[1];
                if ($staleIntent || $staleRedirect) {
                    unset($this->rows[$activationId]);
                    $count++;
                }
            }
            return $count;
        }
        return 0;
    }
}

$wpdb = new ImpactshopAffiliateFakeWpdb();

require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php';

$secret = str_repeat('s', 48);
$subject = impactshop_sharity_affiliate_subject_ref('ABC123XYZ789', $secret);
assert_true((bool) preg_match('/^hmac-sha256:[a-f0-9]{64}$/', $subject), 'subject must be a versioned HMAC');
assert_true(strpos($subject, 'ABC123XYZ789') === false, 'subject must not contain raw pseudo');
assert_true(impactshop_sharity_affiliate_subject_ref('***', $secret) === '', 'invalid pseudo must fail');
assert_true(impactshop_sharity_affiliate_subject_ref('ABC123', 'short') === '', 'weak secret must fail');

$activation = 'act1_' . str_repeat('a', 32);
$token = impactshop_sharity_affiliate_provider_token($activation, $secret);
assert_true((bool) preg_match('/^sat1_[A-Za-z0-9_-]{43}$/', $token), 'token format');
assert_true($token === impactshop_sharity_affiliate_provider_token($activation, $secret), 'token regeneration');
assert_true(strpos($token, $activation) === false, 'token must not expose activation id');
assert_true(impactshop_sharity_affiliate_provider_token('bad', $secret) === '', 'bad activation must fail');

$valid = impactshop_sharity_affiliate_validate_context([
    'shop' => 'arukereso',
    'ngo' => 'gyoztesek-egyesulete',
    'pseudo' => 'ABC123XYZ789',
    'provider' => 'dognet',
    'source' => 'shopping-assistant',
]);
assert_true(!is_wp_error($valid), 'valid Dognet context');
assert_true($valid['ngo'] === 'gyoztesek-egyesulete', 'NGO preserved locally');

foreach ([
    ['shop' => 'arukereso', 'ngo' => 'gyoztesek-egyesulete', 'pseudo' => 'ABC123XYZ789', 'provider' => 'cj', 'source' => 'shopping-assistant'],
    ['shop' => 'arukereso', 'ngo' => 'gyoztesek-egyesulete', 'pseudo' => 'ABC123XYZ789', 'provider' => 'dognet', 'source' => 'impi'],
    ['shop' => 'arukereso', 'ngo' => 'gyoztesek-egyesulete', 'pseudo' => '', 'provider' => 'dognet', 'source' => 'shopping-assistant'],
    ['shop' => 'arukereso', 'ngo' => 'gyoztesek-egyesulete', 'pseudo' => 'ABC123XYZ789', 'provider' => 'dognet', 'source' => 'shopping-assistant', 'url' => 'https://evil.example'],
] as $invalid) {
    assert_true(is_wp_error(impactshop_sharity_affiliate_validate_context($invalid)), 'unsafe context must fail');
}

assert_true(IMPACTSHOP_SHARITY_AFFILIATE_TTL === 900, '15 minute TTL');
assert_true(IMPACTSHOP_SHARITY_AFFILIATE_RETENTION === 3888000, '45 day retention');
assert_true(IMPACTSHOP_SHARITY_AFFILIATE_CRON === 'impactshop_sharity_affiliate_retention_cleanup', 'cron identity');
$validVb = impactshop_sharity_affiliate_validate_context([
    'shop' => 'arukereso',
    'ngo' => 'gyoztesek-egyesulete',
    'pseudo' => 'ABC123XYZ789',
    'provider' => 'dognet',
    'source' => 'vb2026-autobanner',
]);
assert_true(!is_wp_error($validVb), 'valid VB2026 Dognet context');
assert_true($validVb['source'] === 'vb2026-autobanner', 'VB2026 source preserved exactly');

$impactshopTestOptions['impactshop_sharity_affiliate_runtime_enabled'] = '1';
$impactshopTestOptions['impactshop_sharity_affiliate_schema_version'] = '1';
$wpdb->missingSchemaColumn = 'handoff_token_hash';
assert_true(impactshop_sharity_affiliate_install_schema() === false, 'missing v2 column fails closed');
assert_true($impactshopTestOptions['impactshop_sharity_affiliate_schema_version'] === '1', 'failed migration is not marked complete');
$wpdb->missingSchemaColumn = '';
assert_true(impactshop_sharity_affiliate_install_schema() === true, 'complete v2 schema accepted');
assert_true($impactshopTestOptions['impactshop_sharity_affiliate_schema_version'] === '2', 'verified migration marked complete');
$context = [
    'shop' => 'arukereso',
    'ngo' => 'gyoztesek-egyesulete',
    'pseudo' => 'ABC123XYZ789',
    'provider' => 'dognet',
    'source' => 'shopping-assistant',
];
$issued = impactshop_sharity_affiliate_prepare(null, $context);
assert_true(!is_wp_error($issued) && $issued['authorized'] === true, 'intent issue');
assert_true($issued['idempotent_replay'] === false, 'first issue is not replay');
assert_true(count($wpdb->rows) === 1, 'one stored intent');
$stored = reset($wpdb->rows);
assert_true($stored['ngo_ref'] === 'gyoztesek-egyesulete', 'NGO mapping stored locally');
assert_true(strpos(json_encode($stored), 'ABC123XYZ789') === false, 'stored row excludes raw pseudo');
assert_true(strpos(json_encode($stored), $issued['provider_token']) === false, 'stored row excludes raw provider token');
assert_true(strpos(json_encode($stored), 'http') === false, 'stored row excludes destination URL');

$replayed = impactshop_sharity_affiliate_prepare(null, $context);
assert_true(!is_wp_error($replayed), 'ready intent replay');
assert_true($replayed['provider_token'] === $issued['provider_token'], 'replay returns same opaque token');
assert_true($replayed['idempotent_replay'] === true, 'replay is labelled');
assert_true(count($wpdb->rows) === 1, 'replay creates no duplicate row');

assert_true(
    impactshop_sharity_affiliate_mark_redirected(null, $issued['activation_id']) === true,
    'first redirect transition'
);
assert_true(
    impactshop_sharity_affiliate_mark_redirected(null, $issued['activation_id']) === false,
    'redirect transition is one-time'
);
$correlated = impactshop_sharity_affiliate_correlate($issued['provider_token']);
assert_true(is_array($correlated), 'redirected token correlates');
assert_true($correlated['ngo_ref'] === 'gyoztesek-egyesulete', 'correlation recovers NGO mapping');
assert_true((bool) preg_match('/^hmac-sha256:[a-f0-9]{64}$/', $correlated['subject_ref']), 'correlation returns HMAC subject');
assert_true($correlated['purchase_confirmed'] === false, 'click does not prove purchase');
assert_true($correlated['commission_confirmed'] === false, 'click does not prove commission');
assert_true($correlated['settlement_authorized'] === false, 'correlation cannot authorize settlement');
assert_true($stored['source_placement'] === 'shopping-assistant', 'Shopping source placement stored exactly');
assert_true(is_wp_error(impactshop_sharity_affiliate_prepare(null, $context)), 'redirected intent cannot replay');

$wpdb->rows[$issued['activation_id']]['delete_after'] = '2000-01-01 00:00:00';
assert_true(impactshop_sharity_affiliate_retention_cleanup() === true, 'retention cleanup');
assert_true(count($wpdb->rows) === 0, 'expired retained mapping deleted');
assert_true(isset($impactshopTestOptions['impactshop_sharity_affiliate_last_cleanup']['at']), 'sanitized cleanup marker');

$impactshopTestOptions['impactshop_sharity_affiliate_runtime_enabled'] = '0';
assert_true(
    is_wp_error(impactshop_sharity_affiliate_prepare(null, $context)),
    'disabled runtime fails closed'
);

$impactshopTestOptions['impactshop_sharity_affiliate_runtime_enabled'] = '1';
$vbContext = [
    'shop' => 'arukereso',
    'ngo' => 'gyoztesek-egyesulete',
    'pseudo' => 'VB2026ABC999',
    'provider' => 'dognet',
    'source' => 'vb2026-autobanner',
];
$vbIssued = impactshop_sharity_affiliate_prepare(null, $vbContext);
assert_true(!is_wp_error($vbIssued) && $vbIssued['authorized'] === true, 'VB2026 intent issue');
$vbStored = reset($wpdb->rows);
assert_true($vbStored['source_placement'] === 'vb2026-autobanner', 'VB2026 source placement stored exactly');
assert_true($vbStored['ngo_ref'] === 'gyoztesek-egyesulete', 'VB2026 selected NGO stored locally');
assert_true(strpos(json_encode($vbStored), 'VB2026ABC999') === false, 'VB2026 raw pseudo not stored');
assert_true(
    impactshop_sharity_affiliate_mark_redirected(null, $vbIssued['activation_id']) === true,
    'VB2026 redirect transition'
);
$vbCorrelated = impactshop_sharity_affiliate_correlate($vbIssued['provider_token']);
assert_true(is_array($vbCorrelated), 'VB2026 token correlates');
assert_true($vbCorrelated['source_placement'] === 'vb2026-autobanner', 'VB2026 correlation preserves source');
assert_true($vbCorrelated['ngo_ref'] === 'gyoztesek-egyesulete', 'VB2026 correlation preserves selected NGO');
assert_true($vbCorrelated['purchase_confirmed'] === false, 'VB2026 click is not purchase proof');
assert_true($vbCorrelated['commission_confirmed'] === false, 'VB2026 click is not commission proof');
assert_true($vbCorrelated['settlement_authorized'] === false, 'VB2026 click cannot authorize settlement');

impactshop_sharity_affiliate_register_routes();
assert_true(isset($impactshopTestRoutes['sharity/v1/shopping/cj-intent']), 'CJ intent route registered');
assert_true(
    isset($impactshopTestRoutes['sharity/v1/shopping/cj-handoff/(?P<handoffToken>shp1_[A-Za-z0-9_-]{43})']),
    'CJ handoff route registered'
);

$impactshopTestOptions['impactshop_sharity_affiliate_cj_canary_enabled'] = '1';
$impactshopTestOptions['impactshop_sharity_web_session_service_token'] = str_repeat('K', 43);
$sessionToken = 'sw_session_' . str_repeat('S', 43);
$canonicalSubject = 'hmac-sha256:' . str_repeat('b', 64);
$wpdb->sessions[hash('sha256', $sessionToken)] = [
    'subject_ref' => $canonicalSubject,
    'status' => 'active',
    'expires_at_utc' => gmdate('Y-m-d H:i:s', time() + 600),
];
$cjPayload = [
    'authoritySnapshotId' => 'sharity-shopping-production-v2-20260903-01',
    'disclosureVersion' => 'shopping-affiliate-v1',
    'ngoSlug' => 'gyoztesek-egyesulete',
    'partnerKey' => 'unice',
    'programRef' => 'cj-5824323-15487360',
    'providerKey' => 'cj',
];
$cjRequest = new ImpactshopAffiliateFakeRequest([
    'authorization' => 'Bearer ' . $sessionToken,
    'x-sharity-service-authorization' => 'Bearer ' . str_repeat('K', 43),
], [], $cjPayload);
$cjIssued = impactshop_sharity_affiliate_cj_issue($cjRequest);
assert_true($cjIssued->get_status() === 201, 'CJ intent issue status');
$cjIssueData = $cjIssued->get_data();
$handoffUrl = $cjIssueData['data']['handoff_url'] ?? '';
assert_true((bool) preg_match('~^https://app\.sharity\.hu/wp-json/sharity/v1/shopping/cj-handoff/shp1_[A-Za-z0-9_-]{43}$~', $handoffUrl), 'opaque Sharity handoff URL');
assert_true(strpos(json_encode($cjIssueData), 'sat1_') === false, 'CJ issue response excludes provider token');
assert_true(strpos(json_encode($cjIssueData), $sessionToken) === false, 'CJ issue response excludes session token');

$cjStored = null;
foreach ($wpdb->rows as $row) {
    if (($row['provider_key'] ?? '') === 'cj') {
        $cjStored = $row;
        break;
    }
}
assert_true(is_array($cjStored), 'CJ intent stored');
assert_true($cjStored['subject_ref'] === $canonicalSubject, 'canonical session subject stored');
assert_true($cjStored['ngo_ref'] === 'gyoztesek-egyesulete', 'trusted CJ NGO stored');
assert_true($cjStored['provider_program_ref'] === 'cj-5824323-15487360', 'fixed CJ program stored');
assert_true($cjStored['authority_snapshot_ref'] === 'sharity-shopping-production-v2-20260903-01', 'v2 snapshot stored');
assert_true(strpos(json_encode($cjStored), $sessionToken) === false, 'stored CJ row excludes session token');

$handoffToken = basename(parse_url($handoffUrl, PHP_URL_PATH));
$handoffRequest = new ImpactshopAffiliateFakeRequest([], ['handoffToken' => $handoffToken]);
$cjRedirect = impactshop_sharity_affiliate_cj_handoff($handoffRequest);
assert_true($cjRedirect->get_status() === 303, 'CJ handoff redirects');
$cjLocation = $cjRedirect->get_headers()['location'] ?? '';
$cjLocationParts = parse_url($cjLocation);
parse_str($cjLocationParts['query'] ?? '', $cjLocationQuery);
assert_true(($cjLocationParts['scheme'] ?? '') === 'https', 'CJ redirect uses HTTPS');
assert_true(($cjLocationParts['host'] ?? '') === 'www.tkqlhce.com', 'CJ redirect host fixed');
assert_true(($cjLocationParts['path'] ?? '') === '/click-101302202-15487360', 'CJ PID/link path fixed');
assert_true(array_keys($cjLocationQuery) === ['sid'], 'CJ redirect carries only sid');
assert_true((bool) preg_match('/^sat1_[A-Za-z0-9_-]{43}$/', $cjLocationQuery['sid'] ?? ''), 'CJ sid is opaque sat1');
assert_true(($cjRedirect->get_headers()['referrer-policy'] ?? '') === 'no-referrer', 'CJ redirect suppresses referrer');
$cjCorrelation = impactshop_sharity_affiliate_correlate($cjLocationQuery['sid']);
assert_true(is_array($cjCorrelation), 'CJ sid correlates after redirect');
assert_true($cjCorrelation['subject_ref'] === $canonicalSubject, 'CJ correlation returns canonical HMAC subject');
assert_true($cjCorrelation['purchase_confirmed'] === false, 'CJ click is not purchase proof');
assert_true(impactshop_sharity_affiliate_cj_handoff($handoffRequest)->get_status() === 409, 'CJ handoff replay rejected');

$badService = new ImpactshopAffiliateFakeRequest([
    'authorization' => 'Bearer ' . $sessionToken,
    'x-sharity-service-authorization' => 'Bearer ' . str_repeat('X', 43),
], [], $cjPayload);
assert_true(impactshop_sharity_affiliate_cj_issue($badService)->get_status() === 401, 'bad service auth rejected');
$badSession = new ImpactshopAffiliateFakeRequest([
    'authorization' => 'Bearer sw_session_' . str_repeat('Z', 43),
    'x-sharity-service-authorization' => 'Bearer ' . str_repeat('K', 43),
], [], $cjPayload);
assert_true(impactshop_sharity_affiliate_cj_issue($badSession)->get_status() === 401, 'unknown session rejected');
$badPayload = $cjPayload;
$badPayload['programRef'] = 'cj-5824323-99999999';
$badProgram = new ImpactshopAffiliateFakeRequest([
    'authorization' => 'Bearer ' . $sessionToken,
    'x-sharity-service-authorization' => 'Bearer ' . str_repeat('K', 43),
], [], $badPayload);
assert_true(impactshop_sharity_affiliate_cj_issue($badProgram)->get_status() === 400, 'wrong CJ program rejected');

$impactshopTestOptions['impactshop_sharity_affiliate_cj_canary_enabled'] = '0';
assert_true(impactshop_sharity_affiliate_cj_issue($cjRequest)->get_status() === 403, 'CJ canary defaults fail-closed');

echo "sharity affiliate runtime test: PASS\n";

// Confirmed shopping uses the canonical owner-bound session, never the web subject HMAC.
class ImpactShop_Sharity_Web_Session {
    public static $active = true;
    public static $owner = true;
    public static function authenticate_request($request) {
        return self::$active ? ['pseudo_id'=>'abcdefgh1234', 'subject_ref'=>'different-web-hmac',
            'session_hash'=>str_repeat('s',64), 'owner_grant_hash'=>self::$owner ? str_repeat('a',64) : ''] : null;
    }
    public static function owner_grant_active($hash, $pseudo, $lock = false) { return self::$owner; }
}
function sharity_points_impactshop_bind_subject($pseudo) { return ['success'=>$pseudo === 'abcdefgh1234']; }
function isb_get_shops() { return [['shop_slug'=>'test-shop','site'=>'https://shop.example',
    'dognet_base'=>'https://go.dognet.com/?cid=357&chid=26081&refid=publisher','deeplink_param'=>'url','network_source'=>'dognet']]; }
class ShoppingPurchaseFakeWpdb extends ImpactshopAffiliateFakeWpdb {
    public $last_error = '';
    public $activeNgo = true;
    public $ngoSlug = 'chosen-ngo';
    public $loseCommit = false;
    public $race = false;
    public $lockedOwner = true;
    public $reconnect_retries = 5;
    public function get_var($q) { return $q === 'SELECT CONNECTION_ID()' ? '123' : parent::get_var($q); }
    public function get_results($query, ...$rest) {
        if (is_array($query) && strpos($query['query'], 'SELECT option_name, option_value') === 0) {
            $options = $GLOBALS['ttDatabaseOptions'] ?? $GLOBALS['impactshopTestOptions'];
            $out = [];
            foreach ($query['args'] as $name) {
                if (array_key_exists($name, $options)) { $out[] = ['option_name'=>$name, 'option_value'=>is_array($options[$name]) ? serialize($options[$name]) : (string) $options[$name]]; }
            }
            return $out;
        }
        return $this->activeNgo ? [['sharity_ngo_id'=>'7','slug'=>$this->ngoSlug,'name'=>'Chosen NGO']] : [];
    }
    public function get_row($q, ...$rest) {
        if (strpos($q['query'], 'impact_sharity_web_sessions') !== false) {
            return ['pseudo_id'=>'abcdefgh1234','owner_grant_hash'=>str_repeat($this->lockedOwner ? 'a' : 'b',64), 'status'=>'active','expires_at_utc'=>gmdate('Y-m-d H:i:s',time()+3600)];
        }
        if (strpos($q['query'], 'SELECT *') !== false && strpos($q['query'], 'request_key_hash') !== false) {
            foreach ($this->rows as $row) { if ($row['request_key_hash'] === $q['args'][0]) return $row; }
            return null;
        }
        return parent::get_row($q);
    }
    public function query($q) {
        if ($q === 'COMMIT' && $this->loseCommit) { $this->rows=[]; return false; }
        return parent::query($q);
    }
    public function insert($t, $data) { $r=parent::insert($t,$data); return $this->race ? false : $r; }
}
$wpdb = new ShoppingPurchaseFakeWpdb();
$impactshopTestOptions['impactshop_sharity_affiliate_runtime_enabled']='1';
$impactshopTestOptions['impactshop_sharity_shopping_purchase_enabled']='1';
$impactshopTestOptions['impactshop_sharity_affiliate_binding_schema']='1';
$headers=['x-sharity-service-authorization'=>'Bearer '.str_repeat('K',43)];
$purchase=['authoritySnapshotId'=>'sharity-shopping-production-v2-20261102-01',
    'confirmationId'=>'12345678-1234-4123-8123-123456789abc','disclosureVersion'=>'shopping-purchase-v1',
    'merchantInput'=>'https://shop.example/product?variant.id=red&variant_id=blue&sku=a&sku=b&id=red%252Fblue',
    'ngoId'=>'7','partnerKey'=>'test-shop','programRef'=>'dognet:test-shop','providerKey'=>'dognet'];
$call=static function ($p) use ($headers) { return impactshop_shopping_dognet_activate(new ImpactshopAffiliateFakeRequest($headers,[],$p)); };
// These exercise the product GET + locked POST validation and stored reference.
// sanitize_title above is only a legacy-context stub, not proof of real WP slug roundtrip.
foreach (['chosen-ngo', 'civil-%c3%a1rviz', '%e2%9d%a4-alapitvany', str_repeat('a', 128)] as $slug) {
    $wpdb = new ShoppingPurchaseFakeWpdb(); $wpdb->ngoSlug = $slug;
    $catalogResponse = impactshop_shopping_ngos(new ImpactshopAffiliateFakeRequest($headers, [], []));
    assert_true($catalogResponse->get_status() === 200 && $catalogResponse->get_data()['data']['ngos'][0]['slug'] === $slug, 'GET preserves opaque canonical slug');
    assert_true($call($purchase)->get_status() === 200, 'POST accepts same canonical slug');
    assert_true(array_values($wpdb->rows)[0]['ngo_ref'] === $slug, 'POST stores exact canonical bytes without decoding');
}
foreach (['Civil', 'civil-%C3%A1', 'civil-%c', 'civil-%zz', 'civil--ngo', 'civil/ngo', "civil-ngo\n", str_repeat('a', 129)] as $slug) {
    $wpdb = new ShoppingPurchaseFakeWpdb(); $wpdb->ngoSlug = $slug;
    $catalogResponse = impactshop_shopping_ngos(new ImpactshopAffiliateFakeRequest($headers, [], []));
    assert_true($catalogResponse->get_data()['data']['ngos'] === [], 'GET omits noncanonical or overlong slug');
    assert_true($call($purchase)->get_status() === 422 && count($wpdb->rows) === 0, 'POST rejects invalid slug before intent insertion');
}
$wpdb = new ShoppingPurchaseFakeWpdb();
$r=$call($purchase); assert_true($r->get_status()===200, 'confirmed purchase issued');
parse_str(parse_url($r->get_data()['redirectUrl'],PHP_URL_QUERY),$query);
assert_true($query['url']===$purchase['merchantInput'], 'product query lossless');
assert_true($query['cid']==='357' && $query['chid']==='26081' && strpos($query['d1'],'sat1_')===0, 'exact program and SAT1');
assert_true(array_values($wpdb->rows)[0]['provider_scope_ref']==='26081', 'confirmed scope is server-defined channel');
$row=array_values($wpdb->rows)[0];
assert_true($row['subject_ref']===impactshop_sharity_affiliate_subject_ref('abcdefgh1234') && $row['ngo_ref']==='chosen-ngo','authenticated identity and chosen NGO');
assert_true(strtotime($row['delete_after'].' UTC')-strtotime($row['redirected_at'].' UTC')===3888000,'45 day correlation retention');
assert_true($call($purchase)->get_data()===$r->get_data() && count($wpdb->rows)===1,'repeat reuses one intent');
$changed=$purchase; $changed['merchantInput'].='&size=2';
assert_true($call($changed)->get_status()===409,'same UUID different product conflict');
$changed['confirmationId']='22345678-1234-4123-8123-123456789abc';
assert_true($call($changed)->get_status()===200 && count($wpdb->rows)===2,'different confirmation distinct intent');
$wpdb->rows[$row['activation_id']]['intent_expires_at']='2000-01-01 00:00:00';
assert_true($call($purchase)->get_status()===410,'expired confirmation not reissued');
$wpdb->activeNgo=false; assert_true($call($changed)->get_status()===422,'NGO rechecked before repeat'); $wpdb->activeNgo=true;
ImpactShop_Sharity_Web_Session::$owner=false; assert_true($call($changed)->get_status()===401,'legacy unbound session rejected');
ImpactShop_Sharity_Web_Session::$owner=true; ImpactShop_Sharity_Web_Session::$active=false;
assert_true($call($changed)->get_status()===401,'revoked session rejected'); ImpactShop_Sharity_Web_Session::$active=true;
$wpdb->missingSchemaColumn='request_payload_hash'; assert_true($call($changed)->get_status()===503,'missing migration fails closed'); $wpdb->missingSchemaColumn='';
$impactshopTestOptions['impactshop_sharity_shopping_purchase_enabled']='0'; assert_true($call($changed)->get_status()===403,'OFF no activation');
$impactshopTestOptions['impactshop_sharity_shopping_purchase_enabled']='1';
$wpdb=new ShoppingPurchaseFakeWpdb(); $wpdb->loseCommit=true; assert_true($call($purchase)->get_status()===503,'unconfirmed absent commit never releases URL');
$wpdb=new ShoppingPurchaseFakeWpdb(); $wpdb->race=true; assert_true($call($purchase)->get_status()===200 && count($wpdb->rows)===1,'unique race readback no second insert');
$shop=isb_get_shops()[0];
foreach (['https://evil.example/product','https://shop.example/a#x','https://shop.example/a?d1=foreign','https://shop.example/a\\b'] as $url) {
    assert_true(impactshop_shopping_dognet_binding($shop,$url)===null,'unsafe or mismatched destination rejected');
}
$shop['dognet_base'].='&cid=other'; assert_true(impactshop_shopping_dognet_binding($shop,$purchase['merchantInput'])===null,'duplicate base parameter rejected');
assert_true(is_wp_error(impactshop_sharity_affiliate_validate_context(['shop'=>'test-shop','ngo'=>'chosen-ngo','pseudo'=>'ABCDEF123456','provider'=>'dognet','source'=>'shopping-confirmed-v1'])),'legacy GET cannot create confirmed source');
echo "confirmed shopping purchase tests: PASS\n";

// CJ shares the confirmed owner/NGO boundary but keeps the opaque, one-use legacy handoff URL shape.
$oldPublisher = getenv('CJ_PUBLISHER_ID'); $oldWebsite = getenv('CJ_WEBSITE_ID');
putenv('CJ_PUBLISHER_ID=55555'); putenv('CJ_WEBSITE_ID=101302202');
$impactshopTestOptions['impactshop_sharity_affiliate_cj_canary_enabled'] = '1';
$review = ['publisherId'=>'55555','websiteId'=>'101302202','advertiserId'=>'5824323','linkId'=>'15487360',
    'merchantUrl'=>IMPACTSHOP_SHARITY_AFFILIATE_CJ_MERCHANT,'reviewExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)];
$cjPurchase = array_merge($purchase, ['merchantInput'=>IMPACTSHOP_SHARITY_AFFILIATE_CJ_MERCHANT,
    'partnerKey'=>'unice','programRef'=>IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM,'providerKey'=>'cj']);
$cjCall = static function ($p) use ($headers) { return impactshop_sharity_affiliate_cj_issue(new ImpactshopAffiliateFakeRequest($headers,[],$p)); };
$wpdb = new ShoppingPurchaseFakeWpdb();
assert_true($cjCall($cjPurchase)->get_status()===422 && count($wpdb->rows)===0,'CJ missing review stays OFF');
foreach (['reviewExpiresAt'=>'2000-01-01T00:00:00Z','merchantUrl'=>'https://www.unice.com/another-product',
    'websiteId'=>'999','publisherId'=>'666','linkId'=>'999','advertiserId'=>'999'] as $field=>$bad) {
    $impactshopTestOptions['impactshop_sharity_affiliate_cj_purchase_review']=array_merge($review,[$field=>$bad]);
    assert_true($cjCall($cjPurchase)->get_status()===422 && count($wpdb->rows)===0,'CJ invalid/expired review rejected: '.$field);
}
$impactshopTestOptions['impactshop_sharity_affiliate_cj_purchase_review']=$review;
$bad=$cjPurchase; $bad['merchantInput'].='?next=foreign';
assert_true($cjCall($bad)->get_status()===422,'CJ only exact reviewed target');
$bad=$cjPurchase; $bad['ngoSlug']=IMPACTSHOP_SHARITY_AFFILIATE_NGO;
assert_true($cjCall($bad)->get_status()===400 && count($wpdb->rows)===0,'legacy NGO cannot override confirmed choice');
ImpactShop_Sharity_Web_Session::$owner=false;
assert_true($cjCall($cjPurchase)->get_status()===401,'CJ ownerless session rejected'); ImpactShop_Sharity_Web_Session::$owner=true;
$wpdb->lockedOwner=false;
assert_true($cjCall($cjPurchase)->get_status()===401 && count($wpdb->rows)===0,'CJ locked owner switch rejected'); $wpdb->lockedOwner=true;
$wpdb->activeNgo=false;
assert_true($cjCall($cjPurchase)->get_status()===422 && count($wpdb->rows)===0,'CJ inactive NGO rejected'); $wpdb->activeNgo=true;
$cjResult=$cjCall($cjPurchase); assert_true($cjResult->get_status()===200,'confirmed CJ intent issued');
$cjData=$cjResult->get_data(); $cjRow=array_values($wpdb->rows)[0];
assert_true($cjData['contract']==='shopping-confirmed-v1','CJ confirmed marker excludes mixed-version fallback');
assert_true($cjRow['ngo_ref']==='chosen-ngo' && $cjRow['ngo_ref']!==IMPACTSHOP_SHARITY_AFFILIATE_NGO,'CJ stores selected NGO exactly');
assert_true($cjRow['subject_ref']===impactshop_sharity_affiliate_subject_ref('abcdefgh1234') && $cjRow['provider_scope_ref']==='55555:101302202','CJ stores owner and configured publisher/site scope');
assert_true($cjRow['status']==='confirmed_cj_ready' && $cjRow['redirected_at']===null,'CJ issuance alone is not outbound proof');
assert_true($cjCall($cjPurchase)->get_data()===$cjData && count($wpdb->rows)===1,'duplicate CJ confirmation reuses one opaque handoff');
$wpdb->ngoSlug='other-ngo';assert_true($cjCall($cjPurchase)->get_status()===409 && count($wpdb->rows)===1,'same CJ confirmation changed NGO conflicts');$wpdb->ngoSlug='chosen-ngo';
assert_true($call($purchase)->get_status()===409 && count($wpdb->rows)===1,'same confirmation cannot switch provider');
$cjToken=basename(parse_url($cjData['data']['handoff_url'],PHP_URL_PATH));
$cjGet=new ImpactshopAffiliateFakeRequest([],['handoffToken'=>$cjToken],[]);
require __DIR__.'/fixtures/shopping-cj-legacy-handoff.php';
assert_true(shopping_test_legacy_cj_handoff($cjGet)->get_status()===409 && $wpdb->rows[$cjRow['activation_id']]['status']==='confirmed_cj_ready','exact base GET cannot consume a new confirmed CJ intent');
$wpdb->rows[$cjRow['activation_id']]['provider_scope_ref']='wrong:site';
assert_true(impactshop_sharity_affiliate_cj_handoff($cjGet)->get_status()===409,'confirmed CJ GET rejects wrong scope');
$wpdb->rows[$cjRow['activation_id']]['provider_scope_ref']=$cjRow['provider_scope_ref'];
$wpdb->rows[$cjRow['activation_id']]['source_placement']='shopping-assistant';
assert_true(impactshop_sharity_affiliate_cj_handoff($cjGet)->get_status()===409,'confirmed status cannot enter new legacy GET branch');
$wpdb->rows[$cjRow['activation_id']]['source_placement']='shopping-confirmed-v1';
$wpdb->rows[$cjRow['activation_id']]['status']='ready_to_redirect';
assert_true(impactshop_sharity_affiliate_cj_handoff($cjGet)->get_status()===409 && $cjCall($cjPurchase)->get_status()===410,'confirmed source cannot use legacy pending status or idempotent result');
$wpdb->rows[$cjRow['activation_id']]['status']='confirmed_cj_ready';
$impactshopTestOptions['impactshop_sharity_affiliate_cj_purchase_review']['reviewExpiresAt']='2000-01-01T00:00:00Z';
assert_true(impactshop_sharity_affiliate_cj_handoff($cjGet)->get_status()===409 && $wpdb->rows[$cjRow['activation_id']]['status']==='confirmed_cj_ready','CJ rechecks review before outbound');
assert_true(shopping_test_legacy_cj_handoff($cjGet)->get_status()===409,'exact base GET rejects confirmed row even when review expired');
$impactshopTestOptions['impactshop_sharity_affiliate_cj_purchase_review']=$review;
$cjRedirect=impactshop_sharity_affiliate_cj_handoff($cjGet);
assert_true($cjRedirect->get_status()===303,'confirmed CJ handoff redirects after commit');
assert_true(strtotime($wpdb->rows[$cjRow['activation_id']]['delete_after'].' UTC')-strtotime($wpdb->rows[$cjRow['activation_id']]['redirected_at'].' UTC')===3888000,'CJ outbound retains binding for existing 45 days');
assert_true(impactshop_sharity_affiliate_cj_handoff($cjGet)->get_status()===409,'CJ handoff replay denied');
$wpdb=new ShoppingPurchaseFakeWpdb();$wpdb->loseCommit=true;
assert_true($cjCall($cjPurchase)->get_status()===503,'CJ unknown absent commit returns no handoff');
$wpdb=new ShoppingPurchaseFakeWpdb();$wpdb->race=true;
assert_true($cjCall($cjPurchase)->get_status()===200 && count($wpdb->rows)===1,'CJ unique race resolves via readback without write retry');
$pending=array_values($wpdb->rows)[0]; $wpdb->rows[$pending['activation_id']]['intent_expires_at']='2000-01-01 00:00:00';
assert_true(impactshop_sharity_affiliate_retention_cleanup()===true && count($wpdb->rows)===0,'confirmed-only pending state expires and deletes on existing TTL');
$impactshopTestOptions['impactshop_sharity_affiliate_cj_canary_enabled']='0';
assert_true($cjCall($cjPurchase)->get_status()===403,'CJ provider OFF enforced');
$oldPublisher===false ? putenv('CJ_PUBLISHER_ID') : putenv('CJ_PUBLISHER_ID='.$oldPublisher);
$oldWebsite===false ? putenv('CJ_WEBSITE_ID') : putenv('CJ_WEBSITE_ID='.$oldWebsite);
echo "confirmed CJ purchase tests: PASS\n";

// TT uses the confirmed owner transaction and original SAT1 hash; these are synthetic qualifications only.
require_once __DIR__ . '/../wp-content/mu-plugins/impactshop-tradetracker.php';
$ttChecks = 0;
$ttAssert = static function ($ok, $label) use (&$ttChecks) { $ttChecks++; assert_true($ok, 'TT: ' . $label); };
$oldTtCustomer = getenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID=12345');
$ttReview = ['customerId'=>'12345','siteId'=>'481183','campaignId'=>'18982','partnerKey'=>'vidaxl',
    'programRef'=>'tradetracker-18982','merchantUrl'=>'https://www.vidaxl.hu/black-friday',
    'qualificationRef'=>'synthetic-offline-only','referenceReportVerified'=>true,'donationCompatible'=>true,
    'retentionSeconds'=>2592000,'requiredRetentionSeconds'=>2592000,'reviewExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)];
$ttPurchase = array_merge($purchase,['merchantInput'=>'https://www.vidaxl.hu/black-friday','partnerKey'=>'vidaxl',
    'providerKey'=>'tradetracker','programRef'=>'tradetracker-18982']);
$ttCall = static function ($p) use ($headers) { return impactshop_shopping_tt_activate(new ImpactshopAffiliateFakeRequest($headers,[],$p)); };
$wpdb = new ShoppingPurchaseFakeWpdb();
$ttAssert($ttCall($ttPurchase)->get_status()===403 && !$wpdb->rows,'default OFF writes no intent');
$impactshopTestOptions['impactshop_sharity_affiliate_tt_outbound_enabled']='1';
$ttAssert($ttCall($ttPurchase)->get_status()===422 && !$wpdb->rows,'missing review writes no intent');
$impactshopTestOptions['impactshop_sharity_affiliate_tt_purchase_review']=$ttReview;
foreach (['customerId'=>'999','siteId'=>'999','campaignId'=>'999','partnerKey'=>'other','programRef'=>'dognet:vidaxl',
    'merchantUrl'=>'https://www.vidaxl.hu/','qualificationRef'=>'','referenceReportVerified'=>1,'donationCompatible'=>false,
    'retentionSeconds'=>3888001,'requiredRetentionSeconds'=>2592001,'reviewExpiresAt'=>'2000-01-01T00:00:00Z'] as $field=>$bad) {
    $impactshopTestOptions['impactshop_sharity_affiliate_tt_purchase_review']=array_merge($ttReview,[$field=>$bad]);
    $ttAssert($ttCall($ttPurchase)->get_status()===422 && !$wpdb->rows,'invalid qualification '.$field);
}
foreach ([0,-1,'2592000',2592000.0,null] as $bad) {
    $impactshopTestOptions['impactshop_sharity_affiliate_tt_purchase_review']=array_merge($ttReview,['requiredRetentionSeconds'=>$bad]);
    $ttAssert(impactshop_shopping_tt_review()===null,'strict required retention type/range');
}
$impactshopTestOptions['impactshop_sharity_affiliate_tt_purchase_review']=$ttReview;
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');
$ttAssert(impactshop_shopping_tt_review()===null,'missing server account does not adopt review account');
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID=12345');
foreach (['merchantInput'=>'https://www.vidaxl.hu/black-friday?other=1','programRef'=>'tradetracker-999','providerKey'=>'cj','partnerKey'=>'other',
    'authoritySnapshotId'=>'sharity-shopping-production-dognet-20261102-01'] as $field=>$bad) {
    $p=array_merge($ttPurchase,[$field=>$bad]);
    $ttAssert($ttCall($p)->get_status()>=400 && !$wpdb->rows,'wrong request binding '.$field);
}
$p=$ttPurchase;$p['customerId']='12345';
$ttAssert($ttCall($p)->get_status()===400 && !$wpdb->rows,'request cannot supply account or review');
$ttAssert(impactshop_shopping_confirmed_activate(new ImpactshopAffiliateFakeRequest($headers,[],$ttPurchase),'unknown')->get_status()===422,'unknown provider cannot fall into Dognet');
$wpdb->lockedOwner=false;
$ttAssert($ttCall($ttPurchase)->get_status()===401 && !$wpdb->rows,'owner switch under lock');$wpdb->lockedOwner=true;
$wpdb->activeNgo=false;
$ttAssert($ttCall($ttPurchase)->get_status()===422 && !$wpdb->rows,'inactive NGO under lock');$wpdb->activeNgo=true;
$r=$ttCall($ttPurchase);$ttAssert($r->get_status()===200,'qualified synthetic request');
$ttUrl=$r->get_data()['redirectUrl'];$ttRow=array_values($wpdb->rows)[0];
parse_str(parse_url($ttUrl,PHP_URL_QUERY),$ttQuery);
$ttAssert(parse_url($ttUrl,PHP_URL_SCHEME)==='https' && parse_url($ttUrl,PHP_URL_HOST)==='tc.tradetracker.net' && parse_url($ttUrl,PHP_URL_PATH)==='/','fixed tracking origin/path');
$ttAssert(array_keys($ttQuery)===['c','m','a','r','u'] && $ttQuery['c']==='18982' && $ttQuery['m']==='12' && $ttQuery['a']==='481183' && $ttQuery['u']==='/black-friday','own merchant link, not coupon material');
$ttToken=ImpactShop_TradeTracker::token_for_reference($ttQuery['r']);
$ttAssert(strlen($ttQuery['r'])===68 && hash('sha256',$ttToken)===$ttRow['provider_token_hash'],'wire reference preserves stored original token hash');
$ttAssert($ttRow['provider_key']==='tradetracker' && $ttRow['provider_scope_ref']==='12345:481183' && $ttRow['provider_program_ref']==='tradetracker-18982','durable provider/account/site/program');
$ttAssert($ttRow['ngo_ref']==='chosen-ngo' && $ttRow['subject_ref']===impactshop_sharity_affiliate_subject_ref('abcdefgh1234'),'original authenticated owner and selected NGO');
$ttAssert(strtotime($ttRow['delete_after'].' UTC')-strtotime($ttRow['created_at'].' UTC')===2592000,'approved per-row retention, not global extension');
$ttAssert($ttCall($ttPurchase)->get_data()['redirectUrl']===$ttUrl && count($wpdb->rows)===1,'identical UUID replay');
$p=$ttPurchase;$p['authoritySnapshotId']='sharity-shopping-production-v2-20261103-01';
$ttAssert($ttCall($p)->get_status()===409 && $wpdb->rows[$ttRow['activation_id']]['authority_snapshot_ref']===$ttRow['authority_snapshot_ref'],'snapshot retry cannot rebind');
$p=$ttPurchase;$p['ngoId']='8';
$ttAssert($ttCall($p)->get_status()===409,'UUID cannot switch NGO');
foreach (['provider_key'=>'dognet','provider_scope_ref'=>'999:481183','provider_program_ref'=>'tradetracker-999',
    'partner_key'=>'other','subject_ref'=>'hmac-sha256:'.str_repeat('0',64),'ngo_ref'=>'other-ngo',
    'authority_snapshot_ref'=>'other','source_placement'=>'shopping-assistant','provider_token_hash'=>str_repeat('0',64)] as $field=>$bad) {
    $wpdb->rows[$ttRow['activation_id']][$field]=$bad;
    $ttAssert($ttCall($ttPurchase)->get_status()===409,'persisted binding tamper '.$field);
    $wpdb->rows[$ttRow['activation_id']]=$ttRow;
}
$wpdb->rows[$ttRow['activation_id']]['delete_after']='2000-01-01 00:00:00';
$ttAssert($ttCall($ttPurchase)->get_status()===403,'expired mapping is not reissued');$wpdb->rows[$ttRow['activation_id']]=$ttRow;
$wpdb->rows[$ttRow['activation_id']]['intent_expires_at']='2000-01-01 00:00:00';
$ttAssert($ttCall($ttPurchase)->get_status()===410,'expired confirmation is not reissued');$wpdb->rows[$ttRow['activation_id']]=$ttRow;
$impactshopTestOptions['impactshop_sharity_affiliate_tt_purchase_review']['retentionSeconds']=3000000;
$ttAssert($ttCall($ttPurchase)->get_status()===409 && $wpdb->rows[$ttRow['activation_id']]['delete_after']===$ttRow['delete_after'],'review change never extends existing row');
$impactshopTestOptions['impactshop_sharity_affiliate_tt_purchase_review']=$ttReview;
$ttAssert(impactshop_shopping_purchase_result($ttRow,$ttRow['request_payload_hash'],impactshop_shopping_tt_review(),$ttPurchase['merchantInput'])->get_status()===409,'TT cannot use unscoped legacy result');
$wpdb=new ShoppingPurchaseFakeWpdb();$wpdb->loseCommit=true;
$ttAssert($ttCall($ttPurchase)->get_status()===503 && !$wpdb->rows,'lost absent COMMIT never releases URL');
$wpdb=new ShoppingPurchaseFakeWpdb();$wpdb->race=true;
$ttAssert($ttCall($ttPurchase)->get_status()===200 && count($wpdb->rows)===1,'unique race readback, no second insert');
class TtDisableAfterCommitFakeWpdb extends ShoppingPurchaseFakeWpdb {
    public function query($q) { if ($q==='COMMIT') { $GLOBALS['ttDatabaseOptions']['impactshop_sharity_affiliate_tt_outbound_enabled']='0'; } return parent::query($q); }
}
$ttDatabaseOptions=$impactshopTestOptions;
$wpdb=new TtDisableAfterCommitFakeWpdb();
$ttAssert($ttCall($ttPurchase)->get_status()===403 && count($wpdb->rows)===1,'uncached post-COMMIT revocation withholds URL from durable row');
$ttAssert(get_option('impactshop_sharity_affiliate_tt_outbound_enabled')==='1','per-request option cache really remains enabled');
unset($ttDatabaseOptions);
class TtReviewAfterCommitFakeWpdb extends ShoppingPurchaseFakeWpdb {
    public function query($q) { if ($q==='COMMIT') { $GLOBALS['ttDatabaseOptions']['impactshop_sharity_affiliate_tt_purchase_review']['qualificationRef']='changed-proof'; } return parent::query($q); }
}
$ttDatabaseOptions=$impactshopTestOptions;$wpdb=new TtReviewAfterCommitFakeWpdb();
$ttAssert($ttCall($ttPurchase)->get_status()===403 && count($wpdb->rows)===1,'uncached changed review denies committed result');
$ttAssert(get_option('impactshop_sharity_affiliate_tt_purchase_review')['qualificationRef']==='synthetic-offline-only','review cache really remains unchanged');
unset($ttDatabaseOptions);
class TtDisableBeforeInsertFakeWpdb extends ShoppingPurchaseFakeWpdb {
    public function get_row($q, ...$rest) {
        $row=parent::get_row($q,...$rest);
        if (strpos($q['query'],'SELECT *')===0 && strpos($q['query'],'request_key_hash')!==false && strpos($q['query'],'FOR UPDATE')!==false) {
            $GLOBALS['ttDatabaseOptions']['impactshop_sharity_affiliate_tt_outbound_enabled']='0';
        }
        return $row;
    }
}
$ttDatabaseOptions=$impactshopTestOptions;$wpdb=new TtDisableBeforeInsertFakeWpdb();
$ttAssert($ttCall($ttPurchase)->get_status()===403 && !$wpdb->rows,'uncached revocation before INSERT writes no intent');
unset($ttDatabaseOptions);
$impactshopTestOptions['impactshop_sharity_affiliate_tt_outbound_enabled']='0';
$ttAssert($ttCall($ttPurchase)->get_status()===403,'explicit OFF remains closed');
putenv($oldTtCustomer===false?'IMPACTSHOP_TRADETRACKER_CUSTOMER_ID':'IMPACTSHOP_TRADETRACKER_CUSTOMER_ID='.$oldTtCustomer);
echo 'confirmed TT purchase tests: '.$ttChecks." assertions PASS\n";
