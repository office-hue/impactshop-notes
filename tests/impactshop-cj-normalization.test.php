<?php
/** Actual legacy normalizer with synthetic authority/transport; no DB/network. */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
$options = $filterCalls = [];
$checks = 0;
function check($ok, string $message): void {
    ++$GLOBALS['checks'];
    if (!$ok) throw new RuntimeException($message);
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function current_time($format) { return '2026-11-03 12:00:00'; }
function add_filter(...$args) {}
function sanitize_title($value) { return strtolower(preg_replace('/[^A-Za-z0-9-]/', '', $value)); }
function impactshop_sharity_affiliate_correlate($token) { return $GLOBALS['correlation'] ?? null; }
function apply_filters($name, $value, ...$args) {
    $GLOBALS['filterCalls'][] = [$name, $args];
    if ($name === 'impactshop_donation_multiplier' && !empty($GLOBALS['forbidMultiplier'])) {
        throw new RuntimeException('Synthetic correlation key reached donor lookup');
    }
    return $value;
}
class NormalizerDatabase {
    public string $prefix = 'wp_';
    public array $queries = [];
    public array $args = [];
    public function prepare($sql, ...$args) { $this->args = $args; return $sql; }
    public function get_var($sql) {
        $this->queries[] = [$sql, $this->args];
        if (str_starts_with($sql, 'SHOW TABLES')) return $this->args[0];
        if (str_contains($sql, 'SELECT new_level')) return 'bronze';
        throw new RuntimeException('Unexpected DB lookup: ' . $sql);
    }
}
class Sharity_Level_Manager {
    public function calculate_level_for_pseudo($pseudo) { throw new RuntimeException('Current level substituted for event history'); }
}
$wpdb = new NormalizerDatabase();
require __DIR__ . '/../wp-content/mu-plugins/impactshop-cj.php';
$normalize = new ReflectionMethod(ImpactShop_CJ::class, 'normalize_commission');
function commission(array $changes = []): array {
    return array_replace(['action-id'=>'synthetic-c1','advertiser-id'=>'42','advertiser-name'=>'Fixture merchant',
        'event-date'=>'2026-10-20T10:15:30Z','action-status'=>'locked','sid'=>'synthetic-ngo|DONOR0000001',
        'commission-amount'=>'1000','commission-currency'=>'HUF'], $changes);
}
$oldTimezone = date_default_timezone_get();
date_default_timezone_set('UTC');
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
    return false;
});
try {
    $row = $normalize->invoke(null, commission());
    check($row['amount_huf'] === 1050, 'Historical Bronze multiplier used, not current level');
    check($row['happened_at'] === '2026-10-20 10:15:30', 'The same valid event time used in ledger');
    check($wpdb->queries[1][1] === ['donor0000001','2026-10-20 10:15:30'], 'History receives event timestamp before resolution');
    check($filterCalls[0][1] === ['DONOR0000001',1792491330], 'Filter receives exact identity and event epoch');
    check($row['source_ref'] === 'CJ:synthetic-c1' && $row['pseudo_id'] === 'DONOR0000001', 'Legacy identity and source stable');
    check($row['ngo_slug'] === 'synthetic-ngo' && $row['status'] === 'approved', 'NGO and status unchanged');

    foreach ([['2026-10-20T12:15:30+02:00','2026-10-20 10:15:30'],
              ['2026-10-20T10:15:30.123Z','2026-10-20 10:15:30'],
              ['2026-10-20','2026-10-20 00:00:00']] as [$date,$expected]) {
        $row = $normalize->invoke(null, commission(['event-date'=>$date]));
        check($row['happened_at'] === $expected && $row['amount_huf'] === 1050, 'Existing explicit-timezone/fraction/date format preserved');
    }
    check($normalize->invoke(null, commission(['sid'=>'synthetic-ngo~DONOR0000001'])) === $normalize->invoke(null, commission()), 'Both supported legacy SID separators agree');
    $queriesBefore = count($wpdb->queries); $filtersBefore = count($filterCalls);
    foreach (['not-a-date','tomorrow','2026-02-30T12:00:00Z','2026-10-20T25:00:00Z',
              '2026-10-20 +1 day','10:15:30','0000-00-00T00:00:00Z'] as $invalid) {
        check($normalize->invoke(null, commission(['event-date'=>$invalid])) === null, 'Invalid/relative date is not today');
        check(end($options['impactshop_cj_failed_queue'])['reason'] === 'invalid commission event date', 'Bad date is visible in existing failure queue');
    }
    check(count($wpdb->queries) === $queriesBefore && count($filterCalls) === $filtersBefore, 'Invalid date stops before any multiplier lookup');

    $token = 'sat1_' . str_repeat('a',43);
    $correlation = ['provider_key'=>'cj','ngo_ref'=>'resolved-ngo'];
    $forbidMultiplier = true;
    $row = $normalize->invoke(null, commission(['sid'=>$token]));
    check($row['amount_huf'] === 1000, 'Opaque token cannot manufacture donor bonus');
    check($row['ngo_slug'] === 'resolved-ngo', 'Server NGO correlation retained');
    check($row['pseudo_id'] === strtoupper(substr(hash('sha256',$token),0,12)), 'Legacy technical row key unchanged');
    check($row['source_ref'] === 'CJ:synthetic-c1', 'Deduplication identity unchanged');
    check(count($wpdb->queries) === $queriesBefore && count($filterCalls) === $filtersBefore, 'No donor DB or multiplier filter on SAT1');
    check($normalize->invoke(null, commission(['sid'=>$token])) === $row, 'Identical replay produces same legacy row');
    check($normalize->invoke(null, commission(['sid'=>$token,'event-date'=>'tomorrow'])) === null, 'Bad SAT1 date rejected');
    check(!str_contains(json_encode($options['impactshop_cj_failed_queue']),$token), 'Bearer token absent from failure queue');
    foreach ([null,['provider_key'=>'dognet','ngo_ref'=>'other']] as $bad) {
        $correlation = $bad;
        check($normalize->invoke(null, commission(['sid'=>$token])) === null, 'Missing/foreign provider correlation rejected');
    }
    check(!str_contains(json_encode($options['impactshop_cj_failed_queue']),$token), 'Unresolved bearer remains hashed');
} finally {
    restore_error_handler(); date_default_timezone_set($oldTimezone);
}
echo "CJ normalization: {$checks} checks passed; synthetic authority, no network/DB\n";
