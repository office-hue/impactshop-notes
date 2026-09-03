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
                    'provider_key', 'provider_program_ref',
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
            if ($cjHandoff) {
                [$redirectedAt, $deleteAfter, $activationId, $handoffHash, $now] = $args;
            } else {
                [$redirectedAt, $deleteAfter, $activationId, $now] = $args;
                $handoffHash = null;
            }
            if (
                !isset($this->rows[$activationId])
                || $this->rows[$activationId]['status'] !== 'ready_to_redirect'
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
                if ($row['status'] === 'ready_to_redirect' && $row['intent_expires_at'] <= $args[0]) {
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
