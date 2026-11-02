<?php
declare(strict_types=1);
// Only disposable loopback donation_test DB; no live WordPress/Stripe access.
$port = getenv('SHARITY_DONATION_TEST_PORT');
if (!is_string($port) || !preg_match('/^[1-9][0-9]{3,4}$/D', $port) || (int) $port > 65535
    || !function_exists('pcntl_fork')) { fwrite(STDERR, "Explicit isolated DB port and pcntl required\n"); exit(2); }
const ARRAY_A = 'ARRAY_A';
$options = []; $checks = 0; $testPrefix = 'd2ctest_' . bin2hex(random_bytes(4)) . '_';
function check(bool $ok, string $message): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null): bool { $GLOBALS['options'][$key] = $value; return true; }
function add_action(...$args): void {}
function fails(callable $call, string $code): void {
    try { $call(); } catch (RuntimeException $e) {
        check($e->getMessage() === $code, 'expected ' . $code . ', got ' . $e->getMessage());
        check($e->getPrevious() === null, 'no raw error chain'); return;
    }
    throw new RuntimeException('Expected failure ' . $code);
}
class OrderDb
{
    public string $prefix; public string $last_error = '';
    public mysqli $db; public string $failOn = ''; public string $failure = '';
    public ?string $barrier = null; public int $slot = 0; public string $barrierOn = ''; public bool $barrierUsed = false;
    public int $ddls = 0; public $afterRead = null;
    public function __construct(int $port) {
        $this->prefix = $GLOBALS['testPrefix'];
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->db = new mysqli('127.0.0.1', 'root', 'isolated-donation-test-only', 'donation_test', $port);
        $this->db->set_charset('utf8mb4');
    }
    public function prepare(string $sql, ...$args): string {
        if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
        $i = 0;
        return preg_replace_callback('/%[sd]/', function ($m) use (&$i, $args): string {
            $v = $args[$i++]; return $m[0] === '%d' ? (string) (int) $v : "'" . $this->db->real_escape_string((string) $v) . "'";
        }, $sql);
    }
    public function query(string $sql) {
        $this->last_error = '';
        if (str_starts_with($sql, 'CREATE')) { $this->ddls++; }
        $inject = $this->failOn !== '' && str_contains($sql, $this->failOn);
        if ($inject && $this->failure === 'before') { $this->last_error = 'synthetic SQL secret'; return false; }
        try { $result = $this->db->query($sql); $value = $result === true ? $this->db->affected_rows : $result; }
        catch (mysqli_sql_exception $e) { $this->last_error = 'isolated SQL failure'; return false; }
        if ($inject && $this->failure === 'lost_ack') { $this->last_error = 'synthetic lost ACK'; return false; }
        if ($inject && $this->failure === 'throw_after') { throw new RuntimeException('synthetic SQL secret'); }
        return $value;
    }
    public function get_results(string $sql, $mode): ?array { $r = $this->query($sql); return $r instanceof mysqli_result ? $r->fetch_all(MYSQLI_ASSOC) : null; }
    public function get_row(string $sql, $mode): ?array {
        $result = $this->get_results($sql, $mode); $row = $result[0] ?? null;
        if ($this->barrier !== null && !$this->barrierUsed && str_contains($sql, $this->barrierOn)) {
            $this->barrierUsed = true;
            file_put_contents($this->barrier . '/ready-' . $this->slot, 'ready');
            $deadline = microtime(true) + 5;
            while (!is_file($this->barrier . '/ready-' . (1 - $this->slot))) {
                if (microtime(true) >= $deadline) { throw new RuntimeException('race barrier timeout'); }
                usleep(1000);
            }
        }
        if ($this->afterRead !== null) { ($this->afterRead)($sql,$row); }
        return $row;
    }
    public function insert($table, array $row, array $formats) {
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '%s')) . ')';
        return $this->query($this->prepare($sql, ...array_values($row)));
    }
}
$autoload = getenv('SHARITY_DONATION_SDK_AUTOLOAD') ?: __DIR__ . '/../tmp/sharity-donation-sdk/vendor/autoload.php';
if (!is_file($autoload)) { fwrite(STDERR, "Pinned isolated SDK required\n"); exit(2); }
require $autoload;
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/stripe-checkout.php';

final class OrderHttp implements Stripe\HttpClient\ClientInterface
{
    public string $log; public bool $drop = false;
    public function __construct(string $log) { $this->log = $log; }
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
        global $wpdb;
        if ($method !== 'post' || $absUrl !== 'https://api.stripe.com/v1/checkout/sessions' || $maxNetworkRetries !== 0) { throw new RuntimeException('unexpected provider request'); }
        $tx = $wpdb->db->query('SELECT @@in_transaction AS active')->fetch_assoc();
        if ((int) $tx['active'] !== 0) { throw new RuntimeException('external call inside transaction'); }
        file_put_contents($this->log, json_encode(['order' => $params['client_reference_id'], 'amount' => $params['line_items'][0]['price_data']['unit_amount']]) . "\n", FILE_APPEND | LOCK_EX);
        if ($this->drop) { throw new Stripe\Exception\ApiConnectionException('synthetic uncertain provider reply'); }
        $id = 'cs_test_' . substr(hash('sha256', $params['client_reference_id']), 0, 40);
        return [json_encode(['object' => 'checkout.session', 'id' => $id, 'livemode' => false, 'status' => 'open', 'payment_status' => 'unpaid', 'mode' => 'payment', 'client_reference_id' => $params['client_reference_id'], 'amount_total' => $params['line_items'][0]['price_data']['unit_amount'], 'currency' => $params['line_items'][0]['price_data']['currency'], 'expires_at' => $params['expires_at'], 'metadata' => $params['metadata'], 'payment_intent' => null, 'url' => 'https://checkout.stripe.com/c/pay/' . $id]), 200, []];
    }
}
function race(callable $operation, string $barrierOn = ''): array {
    global $wpdb, $port;
    $dir = sys_get_temp_dir() . '/sharity-order-race-' . bin2hex(random_bytes(8)); mkdir($dir, 0700);
    $wpdb->db->close(); $pids = [];
    try {
        foreach ([0, 1] as $slot) {
            $pid = pcntl_fork(); if ($pid === -1) { throw new RuntimeException('fork failed'); }
            if ($pid === 0) {
                $wpdb = new OrderDb((int) $port);
                if ($barrierOn !== '') { $wpdb->barrier = $dir; $wpdb->barrierOn = $barrierOn; $wpdb->slot = $slot; }
                try { $out = ['value' => $operation($slot)]; }
                catch (Throwable $e) { $out = ['error' => $e->getMessage()]; }
                file_put_contents($dir . '/result-' . $slot, json_encode($out)); exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) { pcntl_waitpid($pid, $status); check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'race child exit'); }
        return array_map(static fn($slot) => json_decode(file_get_contents($dir . '/result-' . $slot), true), [0, 1]);
    } finally {
        foreach (glob($dir . '/*') as $file) { unlink($file); } rmdir($dir); $wpdb = new OrderDb((int) $port);
    }
}
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/orders.php';

// D2d HTTP + real store/SDK integration; canonical auth transport is stubbed.
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $body, public int $status = 200) {}
    public function header($k, $v): void { $this->headers[$k] = $v; }
}
class WP_REST_Request {
    public array $url = []; public string $raw = ""; public string $signature = "";
    public function __construct(public array $json, public string $key) {}
    public function get_json_params(): array { return $this->json; }
    public function get_header($k): string { return $k === 'idempotency-key' ? $this->key : ($k === 'stripe-signature' ? $this->signature : ''); }
    public function is_json_content_type(): bool { return true; }
    public function get_body(): string { return $this->raw; }
    public function get_url_params(): array { return $this->url; }
}
class ImpactShop_Sharity_Web_Session {
    public static ?string $subject = null;
    public static function authenticate_request($request): array { return ['subject_ref' => self::$subject]; }
}
function sharity_donation_runtime_adapter(): Sharity_Stripe_Checkout_Adapter { $GLOBALS['runtimeFactoryCalls'] = ($GLOBALS['runtimeFactoryCalls'] ?? 0) + 1; return $GLOBALS['adapter']; }
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/http.php';

$wpdb = new OrderDb((int) $port);
check($wpdb->ddls === 0, 'no migration at load');
check(sharity_donation_quote_schema_apply(), 'quote fixture schema');
check(sharity_donation_order_schema_apply(), 'real order schema verified');
$subject = 'hmac-sha256:' . str_repeat('a', 64); $other = 'hmac-sha256:' . str_repeat('b', 64);
$now = time(); $nonce = bin2hex(random_bytes(6));
$binding = ['binding_version' => 'fixture-v1', 'account_id' => 'acct_SYNTHETIC', 'livemode' => false,
    'verified_until' => gmdate('Y-m-d\TH:i:s\Z', $now + 7200), 'modes' => ['direct' => true, 'amplifier' => true],
    'mode_currencies' => ['direct' => ['HUF' => true, 'EUR' => true, 'USD' => true, 'GBP' => true], 'amplifier' => ['HUF' => true, 'EUR' => true, 'USD' => true, 'GBP' => true]],
    'currencies' => ['HUF' => ['min_minor' => '100', 'max_minor' => '1000000']]];
$options['sharity_donation_accounts_v1'] = ['test-ngo' => $binding];
$snapshot = ['ngo_slug' => 'test-ngo', 'donation_mode' => 'direct', 'currency' => 'HUF', 'amount_minor' => '50000',
    'votes' => 0, 'package_id' => null, 'price_book_version' => null, 'policy_version' => 'direct-net100-v1',
    'account_id' => 'acct_SYNTHETIC', 'binding_version' => 'fixture-v1', 'livemode' => false];
function fixtureQuote(?string $owner = null, ?array $data = null, ?int $expiry = null): string {
    global $wpdb, $subject, $snapshot, $now;
    $id = 'dq_' . bin2hex(random_bytes(16));
    $wpdb->insert(sharity_donation_quote_table(), ['quote_id' => $id, 'request_key_hash' => hash('sha256', $id), 'request_digest' => hash('sha256', 'fixture'),
        'subject_ref' => $owner ?? $subject, 'snapshot_json' => json_encode($data ?? $snapshot), 'created_at_utc' => gmdate('Y-m-d H:i:s', $now), 'expires_at_utc' => gmdate('Y-m-d H:i:s', $expiry ?? $now + 1800)], []);
    return $id;
}
$log = tempnam(sys_get_temp_dir(), 'donation-http-'); chmod($log, 0600);
$transport = new OrderHttp($log); Stripe\ApiRequestor::setHttpClient($transport);
$adapter = new Sharity_Stripe_Checkout_Adapter(Sharity_Stripe_Checkout_Adapter::client('rk_test_SYNTHETICNOTACREDENTIAL'), static function () use (&$now): int { return $now; });
function calls(): int { global $log; return count(file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)); }
function newOrder(string $tag): array { global $subject, $now, $nonce; return sharity_donation_order_prepare($subject, fixtureQuote(), 'order-' . $tag . '-' . $nonce, 'hu', $now); }
try {
    $quote = fixtureQuote();
    fails(fn() => sharity_donation_order_prepare($subject, $quote, 'key-disabled-' . $nonce, 'hu', $now), 'ORDERS_DISABLED');
    $options[SHARITY_DONATION_ORDERS_ENABLED] = '1';
    $key = 'key-first-' . $nonce;
    $first = sharity_donation_order_prepare($subject, $quote, $key, 'hu', $now);
    check($first['state'] === 'prepared' && calls() === 0, 'prepare durable row without provider call');
    check(sharity_donation_order_prepare($subject, $quote, $key, 'hu', $now) === $first, 'exact immutable replay');
    fails(fn() => sharity_donation_order_prepare($subject, fixtureQuote(), $key, 'hu', $now), 'IDEMPOTENCY_CONFLICT');
    fails(fn() => sharity_donation_order_prepare($subject, $quote, $key, 'en', $now), 'IDEMPOTENCY_CONFLICT');
    fails(fn() => sharity_donation_order_prepare($subject, $quote, 'key-other-' . $nonce, 'hu', $now), 'QUOTE_ALREADY_ORDERED');
    fails(fn() => sharity_donation_order_prepare($other, $quote, $key, 'hu', $now), 'QUOTE_NOT_FOUND');
    fails(fn() => sharity_donation_order_status($other, $first['order_id'], $now), 'ORDER_NOT_FOUND');
    fails(fn() => sharity_donation_order_prepare('invalid', $quote, $key, 'hu', $now), 'INVALID_OWNER');
    fails(fn() => sharity_donation_order_prepare($subject, fixtureQuote(null, null, $now - 1), 'expired-key-' . $nonce, 'hu', $now), 'QUOTE_EXPIRED');
    // New order bounds/account/currency verification is current; replay remains historical.
    $options['sharity_donation_accounts_v1']['test-ngo']['livemode'] = true;
    fails(fn() => newOrder('wrongmode'), 'NGO_UNAVAILABLE');
    $options['sharity_donation_accounts_v1']['test-ngo'] = $binding;
    $options[SHARITY_DONATION_ORDERS_ENABLED] = '0';
    check(sharity_donation_order_prepare($subject, $quote, $key, 'hu', $now + 9000) === $first, 'replay after expiry/disabled');
    fails(fn() => sharity_donation_order_dispatch($subject, $first['order_id'], $adapter, $now), 'ORDERS_DISABLED');
    $options[SHARITY_DONATION_ORDERS_ENABLED] = '1';
    $ready = sharity_donation_order_dispatch($subject, $first['order_id'], $adapter, $now);
    check($ready['state'] === 'session_recorded' && calls() === 1, 'one provider call after durable claim/result');
    check($ready['payment_verified'] === false && $ready['votes'] === 0 && $ready['checkout_url'] !== null, 'checkout neither paid nor vote credit');
    check(!str_contains(json_encode($ready), 'acct_') && !str_contains(json_encode($ready), $subject) && !isset($ready['envelope_json']), 'owner projection no private binding/token');
    check(sharity_donation_order_dispatch($subject, $first['order_id'], $adapter, $now) === $ready && calls() === 1, 'repeat dispatch never posts again');
    check(sharity_donation_order_status($subject, $first['order_id'], $now + 9000)['checkout_url'] === null, 'expired checkout URL hidden');
    // Deterministically insert on a second connection after the first key miss,
    // before its quote lookup; both requests still have exactly the same identity.
    $interleavedQuote = fixtureQuote(); $interleavedKey = 'interleaved-key-'.$nonce; $winner = null;
    $wpdb->afterRead = function($sql,$row) use($interleavedQuote,$interleavedKey,$subject,$now,$port,&$winner): void {
        global $wpdb;
        if (!str_contains($sql,'WHERE `request_key_hash`')) { return; }
        $reader = $wpdb; $reader->afterRead = null;
        check($row === null,'deterministic first key lookup absent');
        $wpdb = new OrderDb((int)$port);
        try { $winner = sharity_donation_order_prepare($subject,$interleavedQuote,$interleavedKey,'hu',$now); }
        finally { $wpdb->db->close(); $wpdb = $reader; }
    };
    $replayed = sharity_donation_order_prepare($subject,$interleavedQuote,$interleavedKey,'hu',$now);
    check($replayed === $winner,'same-key winner between key and quote reads is replayed');
    // Two workers deliberately see absent idempotency key before either inserts.
    $raceQuote = fixtureQuote(); $raceKey = 'parallel-key-' . $nonce;
    $same = race(fn($slot) => sharity_donation_order_prepare($subject, $raceQuote, $raceKey, 'hu', $now), 'WHERE `request_key_hash`');
    check(isset($same[0]['value'], $same[1]['value']) && $same[0]['value']['order_id'] === $same[1]['value']['order_id'], 'same key creates exactly one order: ' . json_encode($same));
    $id = $same[0]['value']['order_id']; $before = calls();
    $sent = race(fn($slot) => sharity_donation_order_dispatch($subject, $id, $adapter, $now), 'WHERE `order_id`');
    check(!isset($sent[0]['error']) && !isset($sent[1]['error']) && calls() === $before + 1, 'parallel claims produce one real SDK HTTP call');
    check(sharity_donation_order_status($subject, $id, $now)['state'] === 'session_recorded', 'parallel result durable');
    $q1 = fixtureQuote(); $q2 = fixtureQuote();
    $conflict = race(fn($slot) => sharity_donation_order_prepare($subject, $slot === 0 ? $q1 : $q2, 'conflict-key-' . $nonce, 'hu', $now), 'WHERE `request_key_hash`');
    check(count(array_filter($conflict, fn($r) => ($r['error'] ?? '') === 'IDEMPOTENCY_CONFLICT')) === 1, 'different input same key one conflict');
    $q3 = fixtureQuote();
    $quoteRace = race(fn($slot) => sharity_donation_order_prepare($subject, $q3, 'quote-key-' . $slot . '-' . $nonce, 'hu', $now), 'WHERE `quote_id`');
    check(count(array_filter($quoteRace, fn($r) => ($r['error'] ?? '') === 'QUOTE_ALREADY_ORDERED')) === 1, 'same quote different keys one order');
    foreach (['lost_ack', 'throw_after'] as $failure) {
        $q = fixtureQuote(); $wpdb->failOn = 'INSERT INTO `' . sharity_donation_order_table() . '`'; $wpdb->failure = $failure;
        $recovered = sharity_donation_order_prepare($subject, $q, 'insert-' . $failure . '-' . $nonce, 'hu', $now);
        check($recovered['state'] === 'prepared', 'committed INSERT recovered after ' . $failure);
        $wpdb->failOn = ''; $wpdb->failure = '';
    }
    foreach (['lost_ack', 'throw_after'] as $failure) {
        $o = newOrder('claim-' . $failure); $before = calls();
        $wpdb->failOn = "SET state = 'attempted'"; $wpdb->failure = $failure;
        $uncertain = sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now);
        $wpdb->failOn = ''; $wpdb->failure = '';
        check($uncertain['state'] === 'reconciliation_required' && calls() === $before, 'uncertain claim never posts');
        sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now);
        check(calls() === $before, 'claimed row never reset/retried after lost ACK');
    }
    $o = newOrder('drop'); $before = calls(); $transport->drop = true;
    check(sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now)['state'] === 'reconciliation_required', 'provider uncertainty persists attempted');
    $transport->drop = false;
    sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now);
    check(calls() === $before + 1, 'provider uncertainty never retry');
    foreach (['before', 'lost_ack', 'throw_after'] as $failure) {
        $o = newOrder('save-' . $failure); $before = calls();
        $wpdb->failOn = "SET state = 'session_recorded'"; $wpdb->failure = $failure;
        $s = sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now);
        $wpdb->failOn = ''; $wpdb->failure = '';
        check($s['state'] === ($failure === 'before' ? 'reconciliation_required' : 'session_recorded'), 'result commit readback ' . $failure);
        sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now);
        check(calls() === $before + 1, 'result uncertainty never second POST');
    }
    // D2j: fresh authority before dispatch, without making historical reads depend on it.
    $o = newOrder('matrix-revoked'); $before = calls();
    unset($options['sharity_donation_accounts_v1']['test-ngo']['mode_currencies']);
    fails(fn()=>sharity_donation_order_dispatch($subject,$o['order_id'],$adapter,$now),'NGO_UNAVAILABLE');
    check(calls()===$before && sharity_donation_order_by_quote($subject,$o['quote_id'],$now)['state']==='prepared','legacy matrix cannot dispatch; owner can read');
    $options['sharity_donation_accounts_v1']['test-ngo']=$binding;
    $options['sharity_donation_accounts_v1']['test-ngo']['mode_currencies']['direct']['HUF']=false;
    fails(fn()=>sharity_donation_order_dispatch($subject,$o['order_id'],$adapter,$now),'NGO_UNAVAILABLE');
    check(calls()===$before,'revocation after prepare causes zero provider POST');
    $options['sharity_donation_accounts_v1']['test-ngo']=$binding;
    $o = newOrder('expired-window'); $before = calls();
    fails(fn() => sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now + 1801), 'DONATION_SESSION_WINDOW_EXPIRED');
    check(calls() === $before, 'expired prepared envelope never rebuilt');
    $o = newOrder('transaction');
    $wpdb->db->begin_transaction();
    $ddls = $wpdb->ddls;
    check(!sharity_donation_order_schema_apply() && $wpdb->ddls === $ddls, 'schema refuses outer transaction without implicit commit');
    fails(fn() => sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now), 'ORDER_TRANSACTION_UNSAFE');
    $wpdb->db->rollback();
    check(calls() === $before, 'no provider POST in outer transaction');
    $q = fixtureQuote(); $wpdb->db->begin_transaction();
    fails(fn() => sharity_donation_order_prepare($subject, $q, 'transaction-insert-' . $nonce, 'hu', $now), 'ORDER_TRANSACTION_UNSAFE');
    $wpdb->db->rollback();
    $row = sharity_donation_order_read($subject, 'order_id', $first['order_id']); $result = json_decode($row['result_json'], true);
    check(sharity_donation_order_save_result($subject, $first['order_id'], $result, $now), 'same stored result idempotent');
    $differentResult = array_replace($result, ['session_id' => 'cs_test_OTHER']);
    fails(fn() => sharity_donation_order_save_result($subject, $first['order_id'], $differentResult, $now), 'ORDER_RESULT_CONFLICT');
    fails(fn() => sharity_donation_order_save_result($subject, $first['order_id'], array_replace($result, ['checkout_url' => 'https://evil.test/c/pay/x']), $now), 'ORDER_RESULT_INVALID');
    // HTTP uses wall-clock time(). A frozen SDK clock can be one second behind
    // the newly prepared envelope and fail before POST at a second boundary.
    $fixtureAdapter = $adapter;
    $adapter = new Sharity_Stripe_Checkout_Adapter(Sharity_Stripe_Checkout_Adapter::client('rk_test_SYNTHETICNOTACREDENTIAL'));
    ImpactShop_Sharity_Web_Session::$subject = $subject;
    foreach (['hu', 'en', 'de', 'fr'] as $locale) {
        $request = new WP_REST_Request(['quote_id' => fixtureQuote(), 'locale' => $locale], 'http-' . $locale . '-' . $nonce);
        $before = calls(); $response = sharity_donation_order_handle($request);
        check($response->status === 200 && $response->body['order']['state'] === 'session_recorded' && calls() === $before + 1, 'HTTP to durable claim and real SDK: ' . $locale);
        $id = $response->body['order']['id'];
        $stored = sharity_donation_order_read($subject, 'order_id', $id);
        check(json_decode($stored['envelope_json'], true)['locale'] === $locale && $response->body['order']['payment_verified'] === false, 'locale frozen, no payment proof');
        $options[SHARITY_DONATION_ORDERS_ENABLED] = '0';
        check(sharity_donation_order_handle($request)->body === $response->body && calls() === $before + 1, 'HTTP replay while off no second POST');
        $request->url = ['id' => $id];
        ImpactShop_Sharity_Web_Session::$subject = $other;
        check(sharity_donation_order_status_handle($request)->status === 404, 'HTTP cannot read other owner');
        ImpactShop_Sharity_Web_Session::$subject = $subject;
        check(sharity_donation_order_status_handle($request)->body === $response->body, 'HTTP own status after disable');
        $options[SHARITY_DONATION_ORDERS_ENABLED] = '1';
    }
    $request = new WP_REST_Request(['quote_id' => fixtureQuote(), 'locale' => 'en'], 'http-uncertain-' . $nonce);
    $transport->drop = true; $before = calls();
    check(sharity_donation_order_handle($request)->status === 202, 'HTTP provider uncertainty returned as pending reconciliation');
    $transport->drop = false;
    check(sharity_donation_order_handle($request)->status === 202 && calls() === $before + 1, 'HTTP uncertainty replay never re-POSTs');
    // Real owner+quote SQL and envelope validation; read path works after disable/unbind.
    $known=sharity_donation_order_read($subject,'order_id',$first['order_id']);
    $expected=sharity_donation_order_status($subject,$known['order_id'],$now);
    $before=calls();$factory=$runtimeFactoryCalls;$savedOptions=$options;
    $options[SHARITY_DONATION_ORDERS_ENABLED]='0';unset($options['sharity_donation_accounts_v1']);
    $byQuote=new WP_REST_Request(['quote_id'=>'dq_'.str_repeat('0',32)],'unused');$byQuote->url=['quote_id'=>$known['quote_id']];
    $wpdb->db->query('START TRANSACTION READ ONLY');
    try {
        $r=sharity_donation_order_status_by_quote_handle($byQuote);
        check($r->status===200 && $r->body['order']===$expected && $r->headers['Cache-Control']==='private, no-store','real quote owner read after disable and binding removal');
        ImpactShop_Sharity_Web_Session::$subject=$other;
        $foreign=sharity_donation_order_status_by_quote_handle($byQuote);
        check($foreign->status===404,'foreign owner cannot read actual quote');
        ImpactShop_Sharity_Web_Session::$subject=$subject;$byQuote->url=['quote_id'=>'dq_'.str_repeat('0',32)];
        check(sharity_donation_order_status_by_quote_handle($byQuote)->body===$foreign->body,'unknown and other owner indistinguishable');
    } finally {$wpdb->db->rollback();ImpactShop_Sharity_Web_Session::$subject=$subject;}
    $byQuote->url=['quote_id'=>$known['quote_id']];
    $wpdb->failOn='WHERE `quote_id`';$wpdb->failure='before';
    check(sharity_donation_order_status_by_quote_handle($byQuote)->status===503,'real SQL read failure is unavailable, not missing');$wpdb->failOn='';$wpdb->failure='';
    $table=sharity_donation_order_table();
    $wpdb->query($wpdb->prepare('UPDATE `'.$table.'` SET envelope_json=%s WHERE order_id=%s','{}',$known['order_id']));
    check(sharity_donation_order_status_by_quote_handle($byQuote)->status===503,'corrupt persisted envelope fails actual projection');
    $wpdb->query($wpdb->prepare('UPDATE `'.$table.'` SET envelope_json=%s WHERE order_id=%s',$known['envelope_json'],$known['order_id']));
    check(calls()===$before && $runtimeFactoryCalls===$factory,'all new reads zero SDK factory or POST');$options=$savedOptions;
    $freshQuote=fixtureQuote();$byQuote->url=['quote_id'=>$freshQuote];
    check(sharity_donation_order_status_by_quote_handle($byQuote)->status===404,'no order yet is a temporary missing read');
    $freshOrder=sharity_donation_order_prepare($subject,$freshQuote,'after-missing-'.$nonce,'hu',$now);
    check(sharity_donation_order_status_by_quote_handle($byQuote)->body['order']['id']===$freshOrder['order_id'] && calls()===$before,'same quote visible after later prepare without provider call');
    $adapter = $fixtureAdapter;
    require __DIR__ . '/sharity-donation-webhook-mysql.test.php';
    require __DIR__ . '/sharity-donation-finance-ledger-mysql.test.php';
    // Schema drift rejection must preserve rows and clear readiness. Fixture-only ALTER.
    $table = sharity_donation_order_table(); $beforeRows = $wpdb->db->query("SELECT COUNT(*) AS n FROM `{$table}`")->fetch_assoc()['n'];
    $wpdb->db->query("ALTER TABLE `{$table}` DROP INDEX quote_id");
    check(!sharity_donation_order_schema_apply() && get_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION) === '0', 'missing unique quote index clears readiness');
    check($wpdb->db->query("SELECT COUNT(*) AS n FROM `{$table}`")->fetch_assoc()['n'] === $beforeRows, 'schema verification preserves all rows');
    fails(fn() => newOrder('bad-schema'), 'ORDERS_DISABLED');
    check(sharity_donation_order_status($subject, $first['order_id'], $now)['state'] === 'session_recorded', 'existing owner status survives disabled schema writes');
} finally { $wpdb->db->close(); unlink($log); }
echo "Donation orders MariaDB: {$checks} checks passed; real parallel connections + offline pinned SDK\n";
