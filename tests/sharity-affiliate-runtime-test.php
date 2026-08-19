<?php

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$impactshopTestOptions = [];

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
        return $prepared['args'][0] ?? null;
    }

    public function insert($table, $data)
    {
        foreach ($this->rows as $row) {
            if (
                $row['activation_id'] === $data['activation_id']
                || $row['provider_token_hash'] === $data['provider_token_hash']
                || $row['request_key_hash'] === $data['request_key_hash']
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
        foreach ($this->rows as $row) {
            if (strpos($query, 'request_key_hash = %s') !== false && $row['request_key_hash'] === $needle) {
                return [
                    'activation_id' => $row['activation_id'],
                    'status' => $row['status'],
                    'intent_expires_at' => $row['intent_expires_at'],
                ];
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
            [$redirectedAt, $deleteAfter, $activationId, $now] = $args;
            if (
                !isset($this->rows[$activationId])
                || $this->rows[$activationId]['status'] !== 'ready_to_redirect'
                || $this->rows[$activationId]['intent_expires_at'] <= $now
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

$impactshopTestOptions['impactshop_sharity_affiliate_runtime_enabled'] = '1';
$impactshopTestOptions['impactshop_sharity_affiliate_schema_version'] = '1';
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

echo "sharity affiliate runtime test: PASS\n";
