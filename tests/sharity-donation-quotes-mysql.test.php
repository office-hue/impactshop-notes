<?php
declare(strict_types=1);
// Run ONLY against the disposable, loopback-bound donation_test container.
// Example: SHARITY_DONATION_TEST_PORT=<mapped port> php tests/sharity-donation-quotes-mysql.test.php
$port = getenv('SHARITY_DONATION_TEST_PORT');
if (!is_string($port) || !preg_match('/^[1-9][0-9]{3,4}$/D', $port) || (int) $port > 65535) { fwrite(STDERR, "Explicit isolated container port required\n"); exit(2); }
if (!function_exists('pcntl_fork')) { fwrite(STDERR, "pcntl required for actual race test\n"); exit(2); }
const ARRAY_A = 'ARRAY_A';
$options = []; $checks = 0;
function check($ok, string $message): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null): bool { $GLOBALS['options'][$key] = $value; return true; }
function add_action(...$args): void {}
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $data, public int $status = 200) {}
    public function header($key, $value): void { $this->headers[$key] = $value; }
}
class WP_REST_Request {
    public function __construct(public array $body, public string $key) {}
    public function get_json_params(): array { return $this->body; }
    public function get_header($name): string { return $this->key; }
}
class ImpactShop_Sharity_Web_Session {
    public static string $subject = 'hmac-sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    public static function authenticate_request($request): array { return ['subject_ref' => self::$subject]; }
}
class MysqlQuoteDb {
    public string $prefix = 'd2test_'; public string $last_error = '';
    public mysqli $db; public int $writes = 0; public int $ddls = 0;
    public string $failure = ''; public ?string $barrier = null; public int $slot = 0; public bool $firstRead = true;
    public function __construct(int $port) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->db = new mysqli('127.0.0.1', 'root', 'isolated-donation-test-only', 'donation_test', $port);
        $this->db->set_charset('utf8mb4');
    }
    public function prepare(string $sql, ...$args): string {
        $i = 0;
        return preg_replace_callback('/%s/', function () use (&$i, $args): string { return "'" . $this->db->real_escape_string((string) $args[$i++]) . "'"; }, $sql);
    }
    public function query(string $sql) {
        $this->last_error = '';
        if (str_starts_with($sql, 'CREATE')) { $this->ddls++; }
        try { $result = $this->db->query($sql); return $result === true ? $this->db->affected_rows : $result; }
        catch (mysqli_sql_exception $e) { $this->last_error = 'isolated database error'; return false; }
    }
    public function get_results(string $sql, $type): ?array { $r = $this->query($sql); return $r instanceof mysqli_result ? $r->fetch_all(MYSQLI_ASSOC) : null; }
    public function get_row(string $sql, $type): ?array {
        $rows = $this->get_results($sql, $type); $row = $rows[0] ?? null;
        // Force BOTH clients to observe the same absent key before either inserts.
        if ($this->barrier !== null && $this->firstRead && str_starts_with($sql, 'SELECT quote_id')) {
            $this->firstRead = false;
            file_put_contents($this->barrier . '/ready-' . $this->slot, 'ready');
            $deadline = microtime(true) + 5;
            while (!is_file($this->barrier . '/ready-' . (1 - $this->slot))) {
                if (microtime(true) > $deadline) { throw new RuntimeException('race barrier timed out'); }
                usleep(1000);
            }
        }
        return $row;
    }
    public function insert($table, array $row, array $formats) {
        $this->writes++;
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '%s')) . ')';
        $result = $this->query($this->prepare($sql, ...array_values($row)));
        if ($this->failure === 'after_commit') { $this->last_error = 'uncertain acknowledgement'; return false; }
        if ($this->failure === 'throw_after_commit') { throw new RuntimeException('uncertain acknowledgement'); }
        return $result;
    }
}
$wpdb = new MysqlQuoteDb((int) $port);
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';
$table = sharity_donation_quote_table();
check($wpdb->ddls === 0, 'load creates no schema');
check(sharity_donation_quote_schema_apply(), 'actual MariaDB schema verified');
$options[SHARITY_DONATION_QUOTES_ENABLED] = '1';
$options['sharity_donation_accounts_v1'] = ['test-ngo' => ['binding_version' => 'fixture-v1', 'account_id' => 'acct_TESTONLY', 'livemode' => false, 'verified_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600), 'modes' => ['direct' => true, 'amplifier' => true], 'currencies' => ['HUF' => ['min_minor' => '100', 'max_minor' => '1000000']]]];
$body = ['ngo_slug' => 'test-ngo', 'donation_mode' => 'direct', 'currency' => 'HUF', 'amount_minor' => '50000'];
$nonce = bin2hex(random_bytes(8));
function runRace(array $body, string $key, bool $different): array {
    global $wpdb, $port;
    $dir = sys_get_temp_dir() . '/sharity-donation-race-' . bin2hex(random_bytes(8)); mkdir($dir, 0700);
    $wpdb->db->close(); // children create independent connections, never share a live mysqli socket
    $pids = [];
    try {
        for ($slot = 0; $slot < 2; $slot++) {
            $pid = pcntl_fork();
            if ($pid === -1) { throw new RuntimeException('fork failed'); }
            if ($pid === 0) {
                $wpdb = new MysqlQuoteDb((int) $port); $wpdb->barrier = $dir; $wpdb->slot = $slot;
                if ($different && $slot === 1) { $body['amount_minor'] = '60000'; }
                $result = sharity_donation_quote_handle(new WP_REST_Request($body, $key));
                file_put_contents($dir . '/result-' . $slot, json_encode(['status' => $result->status, 'data' => $result->data])); exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) { pcntl_waitpid($pid, $status); check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'race child exited normally'); }
        $out = [];
        foreach ([0, 1] as $slot) { $out[] = json_decode(file_get_contents($dir . '/result-' . $slot), true); }
        return $out;
    } finally {
        foreach (glob($dir . '/*') as $file) { unlink($file); } rmdir($dir);
        $wpdb = new MysqlQuoteDb((int) $port);
    }
}
$sameKey = 'mysql-same-' . $nonce;
$same = runRace($body, $sameKey, false);
check(array_column($same, 'status') === [200, 200], 'concurrent identical requests both succeed');
check($same[0]['data']['quote']['id'] === $same[1]['data']['quote']['id'], 'one winning quote ID');
$row = $wpdb->get_row("SELECT COUNT(*) AS n FROM `{$table}`", ARRAY_A);
check((int) $row['n'] === 1, 'only one durable row after same-key race');
$different = runRace($body, 'mysql-conflict-' . $nonce, true);
$statuses = array_column($different, 'status'); sort($statuses);
check($statuses === [200, 409], 'concurrent different payload returns conflict');
foreach (['after_commit', 'throw_after_commit'] as $failure) {
    $wpdb->failure = $failure;
    $response = sharity_donation_quote_handle(new WP_REST_Request($body, $failure . '-' . $nonce));
    check($response->status === 200 && $response->data['quote']['amount_minor'] === '50000', 'real committed row recovered after ' . $failure);
}
$wpdb->failure = '';
$before = $wpdb->get_row("SELECT COUNT(*) AS n FROM `{$table}`", ARRAY_A)['n'];
$wpdb->db->query("UPDATE `{$table}` SET expires_at_utc = UTC_TIMESTAMP() WHERE request_key_hash = '" . sharity_donation_quote_key_hash(ImpactShop_Sharity_Web_Session::$subject, $sameKey) . "'");
check(sharity_donation_quote_handle(new WP_REST_Request($body, $sameKey))->status === 410, 'actual SQL expiry rejected');
check($wpdb->get_row("SELECT COUNT(*) AS n FROM `{$table}`", ARRAY_A)['n'] === $before, 'expired replay inserts no new row');
// Deliberate incompatible fixture schema only; installer must not repair it silently.
$wpdb->db->query("ALTER TABLE `{$table}` DROP INDEX request_key_hash");
check(!sharity_donation_quote_schema_apply(), 'missing unique index rejected');
check(get_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION) === '0', 'failed verification removes stale readiness');
check(sharity_donation_quote_handle(new WP_REST_Request($body, 'disabled-' . $nonce))->status === 503, 'incompatible schema blocks new quotes');
check($wpdb->get_row("SELECT COUNT(*) AS n FROM `{$table}`", ARRAY_A)['n'] === $before, 'schema apply preserved existing rows');
$wpdb->db->close();
echo "Donation quotes MariaDB: {$checks} checks passed; independent connections and forced concurrent inserts\n";
