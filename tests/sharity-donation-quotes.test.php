<?php
declare(strict_types=1);
// Isolated transport/failure tests. This does not prove MySQL concurrency or DDL.
const ARRAY_A = 'ARRAY_A';
const WP_CLI = true;
$options = $actions = $routes = []; $checks = 0;
function check($ok, string $message): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null): bool { $GLOBALS['options'][$key] = $value; return true; }
function add_option($key, $value, $old = '', $autoload = null): bool { if (isset($GLOBALS['options'][$key])) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function add_action($hook, $callback, $priority = 10): void { $GLOBALS['actions'][$hook][$priority][] = $callback; }
function register_rest_route($ns, $path, $args): void { $GLOBALS['routes'][$ns . $path] = $args; }
class WP_CLI {
    public static array $commands = [];
    public static function add_command($name, $callback): void { self::$commands[$name] = $callback; }
    public static function error($message): void { throw new RuntimeException($message); }
    public static function success($message): void {}
}
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $data, public int $status = 200) {}
    public function header($key, $value): void { $this->headers[$key] = $value; }
}
class WP_REST_Request {
    public function __construct(public $body, public string $key = 'donation-test-1234567890') {}
    public function get_json_params() { return $this->body; }
    public function get_header($name) { return $name === 'idempotency-key' ? $this->key : ''; }
}
class ImpactShop_Sharity_Web_Session {
    public static $response;
    public static int $calls = 0;
    public static function authenticate_request($request) { self::$calls++; return self::$response; }
}
class QuoteDb {
    public string $prefix = 'wp_'; public string $last_error = '';
    public array $rows = []; public int $writes = 0; public int $ddl = 0;
    public string $failure = ''; public bool $badSchema = false; public $race = null;
    public function prepare($sql, ...$args): array { return [$sql, $args]; }
    public function get_row($prepared, $type) {
        $this->last_error = '';
        [$sql, $args] = $prepared;
        if (str_starts_with($sql, 'SHOW TABLE STATUS')) { return ['Engine' => 'InnoDB']; }
        if ($this->failure === 'read') { $this->last_error = 'synthetic SQL secret'; return null; }
        $row = $this->rows[$args[0]] ?? null;
        return $row !== null && $row['subject_ref'] === $args[1] ? $row : null;
    }
    public function insert($table, $row, $formats) {
        $this->writes++;
        if ($this->race) { ($this->race)($this, $row); $this->race = null; return false; }
        if ($this->failure === 'before') { return false; }
        $this->rows[$row['request_key_hash']] = $row;
        if ($this->failure === 'after') { $this->last_error = 'connection lost after commit'; return false; }
        if ($this->failure === 'throw_after') { throw new RuntimeException('synthetic uncertain insert'); }
        return 1;
    }
    public function query($sql) {
        check(str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS'), 'only additive create');
        check(!preg_match('/\b(ALTER|DROP|DELETE|UPDATE|TRUNCATE)\b/', $sql), 'no existing table mutation');
        $this->ddl++; return 0;
    }
    public function get_results($sql, $type): array {
        if (str_starts_with($sql, 'SHOW FULL COLUMNS')) {
            $out = [];
            foreach (['quote_id' => ['char(35)', 'ascii_bin'], 'request_key_hash' => ['char(64)', 'ascii_bin'], 'request_digest' => ['char(64)', 'ascii_bin'], 'subject_ref' => ['varchar(76)', 'ascii_bin'], 'snapshot_json' => ['longtext', 'utf8mb4_bin'], 'created_at_utc' => ['datetime', null], 'expires_at_utc' => ['datetime', null]] as $name => [$type, $collation]) {
                $out[] = ['Field' => $name, 'Type' => $type, 'Collation' => $collation, 'Null' => 'NO', 'Default' => null, 'Extra' => ''];
            }
            if ($this->badSchema) { $out[0]['Collation'] = 'ascii_general_ci'; } return $out;
        }
        return array_map(static fn($x) => ['Key_name' => $x[0], 'Seq_in_index' => 1, 'Column_name' => $x[1], 'Non_unique' => $x[2], 'Sub_part' => null], [['PRIMARY', 'quote_id', 0], ['request_key_hash', 'request_key_hash', 0], ['expiry', 'expires_at_utc', 1]]);
    }
}
$wpdb = new QuoteDb();
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-support-finance-read.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';
$subject = 'hmac-sha256:' . str_repeat('a', 64);
ImpactShop_Sharity_Web_Session::$response = ['subject_ref' => $subject];
$direct = ['ngo_slug' => 'test-ngo', 'donation_mode' => 'direct', 'currency' => 'HUF', 'amount_minor' => '50000'];
$account = ['binding_version' => 'verified-test-v1', 'account_id' => 'acct_TESTONLY', 'livemode' => false, 'verified_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600), 'modes' => ['direct' => true, 'amplifier' => true],
    'mode_currencies' => ['direct' => ['HUF' => true, 'EUR' => true, 'USD' => true, 'GBP' => true], 'amplifier' => ['HUF' => true, 'EUR' => true, 'USD' => true, 'GBP' => true]], 'currencies' => []];
foreach (['HUF', 'EUR', 'USD', 'GBP'] as $currency) { $account['currencies'][$currency] = ['min_minor' => '100', 'max_minor' => '100000000']; }
$options['sharity_donation_accounts_v1'] = ['test-ngo' => $account];
function issue($body, $key = 'donation-test-1234567890') { return sharity_donation_quote_handle(new WP_REST_Request($body, $key)); }
check($wpdb->writes === 0 && $wpdb->ddl === 0, 'loading never creates schema/data');
check(issue($direct)->status === 503, 'default disabled');
$options[SHARITY_DONATION_QUOTES_ENABLED] = '1';
check(issue($direct)->status === 503 && $wpdb->ddl === 0, 'HTTP does not migrate');
try { (WP_CLI::$commands['sharity donations quote-schema'])([], []); throw new RuntimeException('missing apply accepted'); } catch (RuntimeException $e) { check($wpdb->ddl === 0 && str_contains($e->getMessage(), '--apply'), 'CLI requires explicit apply'); }
check(sharity_donation_quote_schema_apply(), 'isolated valid schema accepted');
$wpdb->badSchema = true; check(!sharity_donation_quote_schema_apply(), 'incompatible collation rejected'); check(get_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION) === '0', 'failed schema verification invalidates prior readiness'); $wpdb->badSchema = false;
$options[SHARITY_DONATION_QUOTES_SCHEMA_OPTION] = '1';
$first = issue($direct);
check($first->status === 200 && $first->data['quote']['amount_minor'] === '50000', 'exact minor amount');
check($first->data['quote']['votes'] === 0 && $first->data['quote']['policy_version'] === 'direct-net100-v1', 'direct no reward');
check($first->headers['Cache-Control'] === 'private, no-store', 'private response');
check(!str_contains(json_encode($first->data), 'acct_') && !str_contains(json_encode($first->data), $subject), 'no account/owner leak');
check(abs(strtotime($first->data['quote']['expires_at']) - time() - 1800) <= 1, '30 minute offer');
$options['sharity_donation_accounts_v1']['test-ngo']['modes']['direct'] = false;
check(issue($direct)->data === $first->data && $wpdb->writes === 1, 'same input replay frozen before changed availability');
check(issue(array_replace($direct, ['amount_minor' => '60000']))->status === 409, 'same key different amount conflict');
$options['sharity_donation_accounts_v1']['test-ngo'] = $account;
$keyHash = sharity_donation_quote_key_hash($subject, 'donation-test-1234567890');
$wpdb->rows[$keyHash]['expires_at_utc'] = gmdate('Y-m-d H:i:s', time());
check(issue($direct)->status === 410 && $wpdb->writes === 1, 'exact expiry blocked, no replacement');
ImpactShop_Sharity_Web_Session::$response = ['subject_ref' => 'hmac-sha256:' . str_repeat('b', 64)];
check(issue($direct)->status === 200 && $wpdb->writes === 2, 'same key different owner independent');
ImpactShop_Sharity_Web_Session::$response = new WP_REST_Response(['ok' => false], 401);
check(issue($direct)->status === 401, 'revoked owner authority preserved');
ImpactShop_Sharity_Web_Session::$response = new WP_REST_Response(['ok' => false], 503);
check(issue($direct)->status === 503, 'authority unavailable preserved');
ImpactShop_Sharity_Web_Session::$response = ['subject_ref' => $subject];
foreach ([null, [], ['account_id' => 'acct_bad'] + $direct, ['votes' => 999] + $direct, ['package_id' => 'starter'] + $direct, array_replace($direct, ['amount_minor' => 50000]), array_replace($direct, ['amount_minor' => '1e3']), array_replace($direct, ['amount_minor' => '101']), array_replace($direct, ['amount_minor' => '12.50']), array_replace($direct, ['ngo_slug' => 'bad--slug'])] as $bad) { check(issue($bad)->status === 400, 'invalid request rejected'); }
foreach (['EUR', 'USD', 'GBP'] as $currency) { $r = issue(array_replace($direct, ['currency' => $currency, 'amount_minor' => '1250']), 'foreign-test-key-' . $currency); check($r->status === 200 && $r->data['quote']['amount_minor'] === '1250', 'foreign cents accepted exactly'); }
foreach (['starter' => 500, 'supporter' => 1100, 'champion' => 3000, 'hero' => 6500, 'legend' => 15000] as $package => $votes) {
    $r = issue(['ngo_slug' => 'test-ngo', 'donation_mode' => 'amplifier', 'currency' => 'HUF', 'package_id' => $package, 'price_book_version' => 'amplifier-net80-v1:HUF'], 'package-test-key-' . $package);
    check($r->status === 200 && $r->data['quote']['votes'] === $votes, 'server package votes once');
}
// Foreign Amplifier quotes consume the validated frozen books, not raw options.
$now = time(); $day = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d');
$quotes = [];
foreach (['EUR' => '400', 'USD' => '370', 'GBP' => '500'] as $currency => $rate) {
    $quotes[$currency] = ['success' => true, 'currency' => $currency, 'rate' => $rate, 'unit' => '1', 'date' => $day];
}
$saved = sharity_donation_build_price_books(['fetched_at' => gmdate('Y-m-d\TH:i:s\Z', $now), 'quotes' => $quotes], $now);
$options[SHARITY_DONATION_PRICES_ENABLED] = '1';
$bookKey = 'sharity_donation_prices_' . sharity_donation_price_week($now)['id']; $options[$bookKey] = $saved;
foreach (['EUR', 'USD', 'GBP'] as $currency) {
    foreach ($saved['books'][$currency]['packages'] as $package) {
        $selection = ['ngo_slug' => 'test-ngo', 'donation_mode' => 'amplifier', 'currency' => $currency, 'package_id' => $package['id'], 'price_book_version' => $saved['books'][$currency]['version']];
        $r = issue($selection, 'foreign-package-' . $currency . '-' . $package['id']);
        check($r->status === 200 && $r->data['quote']['amount_minor'] === $package['amount_minor'] && $r->data['quote']['votes'] === $package['votes'], 'exact foreign package and bonus once');
    }
}
$lastQuote = $r->data; unset($options[$bookKey]);
check(issue($selection, 'foreign-package-' . $currency . '-' . $package['id'])->data === $lastQuote, 'accepted quote survives later book expiry/unavailability');
check(issue($selection, 'new-after-price-unavailable')->status === 409, 'new foreign quote blocked without current book');
foreach (['verified_until' => '2026-02-30T00:00:00Z', 'account_id' => 'acct_BAD/escape', 'livemode' => 'false', 'binding_version' => 'bad value'] as $field => $bad) {
    $options['sharity_donation_accounts_v1']['test-ngo'] = array_replace($account, [$field => $bad]);
    check(issue($direct, 'binding-bad-key-' . $field)->status === 503, 'invalid private binding blocked');
}
$options['sharity_donation_accounts_v1']['test-ngo'] = $account;
// D2j: chosen mode and currency must have a literal true permission.
$matrixAccount = $account;
$matrixAccount['mode_currencies'] = ['direct'=>['HUF'=>true,'EUR'=>true], 'amplifier'=>['HUF'=>true]];
$options['sharity_donation_accounts_v1']['test-ngo']=$matrixAccount;
$amp=['ngo_slug'=>'test-ngo','donation_mode'=>'amplifier','currency'=>'HUF','package_id'=>'starter','price_book_version'=>'amplifier-net80-v1:HUF'];
foreach (['direct','amplifier'] as $mode) { foreach (['HUF','EUR','USD','GBP'] as $currency) {
    $input=$mode==='direct'?array_replace($direct,['currency'=>$currency,'amount_minor'=>'50000']):array_replace($amp,['currency'=>$currency]);
    $allowed=($matrixAccount['mode_currencies'][$mode][$currency]??false)===true;
    $writes=$wpdb->writes;$r=issue($input,'matrix-combination-'.$mode.'-'.$currency);
    check($r->status===($allowed?200:503),'matrix permits only chosen mode/currency');
    check($wpdb->writes===$writes+($allowed?1:0),'denied matrix never writes');
} }
$badMatrices=[null,[],false,'all',['direct'=>true],['direct'=>[]],['direct'=>['HUF'=>false]],['direct'=>['HUF'=>1]],['direct'=>['HUF'=>'true']],['direct'=>['HUF'=>null]],['direct'=>['HUF'=>[]]]];
foreach($badMatrices as $i=>$matrix){
    $options['sharity_donation_accounts_v1']['test-ngo']=array_replace($account,['mode_currencies'=>$matrix]);$writes=$wpdb->writes;
    check(issue($direct,'matrix-malformed-'.$i)->status===503 && $wpdb->writes===$writes,'malformed selected cell denies before write');
}
$legacy=$account;unset($legacy['mode_currencies']);$options['sharity_donation_accounts_v1']['test-ngo']=$legacy;
check(issue($direct,'matrix-legacy-missing')->status===503,'missing matrix never grandfathered for new quote');
$options['sharity_donation_accounts_v1']['test-ngo']=array_replace($matrixAccount,['mode_currencies'=>['direct'=>['HUF'=>true],'amplifier'=>'invalid']]);
check(issue($direct,'matrix-unselected-mode')->status===200,'invalid unselected mode does not change selected permission');
$options['sharity_donation_accounts_v1']['test-ngo']=$account;
foreach (['before' => 503, 'after' => 200, 'throw_after' => 200, 'read' => 503] as $failure => $status) {
    $wpdb->failure = $failure;
    $r = issue($direct, 'uncertain-insert-' . $failure);
    check($r->status === $status, 'readback after ' . $failure);
    check(!str_contains(json_encode($r->data), 'synthetic'), 'no DB detail leakage');
}
$wpdb->failure = '';
$wpdb->race = static function ($db, $row): void { $row['quote_id'] = 'dq_' . str_repeat('c', 32); $db->rows[$row['request_key_hash']] = $row; };
$r = issue($direct, 'raced-same-input-key');
check($r->status === 200 && $r->data['quote']['id'] === 'dq_' . str_repeat('c', 32), 'winner quote accepted without own generated ID equality');
$wpdb->race = static function ($db, $row): void { $row['request_digest'] = str_repeat('d', 64); $db->rows[$row['request_key_hash']] = $row; };
check(issue($direct, 'raced-different-input-key')->status === 409, 'raced different payload conflict');
check($wpdb->ddl === 2, 'no handler DDL ever');
echo "Donation quotes: {$checks} checks passed (isolated transport, not MySQL concurrency)\n";
