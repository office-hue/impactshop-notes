<?php

declare(strict_types=1);

// CLI orchestrates a loopback HTTP request backed by an empty, disposable MariaDB.
const FIXTURE_DB = 'profile_deadlock_fixture';
const FIXTURE_GRANTS = 'fixture_impactshop_identity_device_grants';
const REPLACEMENT_PSEUDO = 'abcdef123456';
const OTHER_PSEUDO = 'fedcba654321';
const FIXTURE_WPDB_SHA = '0fd1ffb9a24e9db8cb7370965db379a2fecd985dbeb73d319521cc1bca1bd04c';
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('DAY_IN_SECONDS', 86400);
define('JYSK_GAME_ENABLED', true);

function add_action(...$args): void {}
function add_shortcode(...$args): void {}
function add_filter(...$args): void {}
function has_filter(...$args): bool { return false; }
function wp_unslash($value) { return $value; }
function sanitize_text_field(string $value): string { return $value; }
function wp_salt(string $scheme): string { return 'disposable-fixture-' . $scheme; }
function is_multisite(): bool { return false; }
function wp_debug_backtrace_summary(): string { return 'profile-fixture'; }
function __($value) { return $value; }
function absint($value): int { return abs((int) $value); }
function get_option(string $name, $default = false) { return $default; }
function apply_filters($name, $value, ...$args) {
    if ($name !== 'query') {
        return $value;
    }
    if (str_contains($value, 'ORDER BY created_at DESC LIMIT 5')) {
        $GLOBALS['legacy_reads']++;
    }
    if ($value === 'ROLLBACK' && $GLOBALS['fixture_mode'] === 'rollback-error') {
        return '';
    }
    if (str_starts_with($value, 'UPDATE ' . FIXTURE_GRANTS . " SET state = 'active'")) {
        $GLOBALS['activation_started'] = true;
        if ($GLOBALS['fixture_mode'] === 'unclassified') {
            return '';
        }
        if ($GLOBALS['fixture_mode'] === 'zero-rows') {
            return $value . ' AND 1 = 0';
        }
        if ($GLOBALS['fixture_mode'] === 'headers-sent') {
            echo "\n";
            flush();
        }
    }
    return $value;
}

function impactshop_owner_policy_safe_disabled(): bool {
    return $GLOBALS['fixture_closed'];
}
function impactshop_owner_policy_safe_disable_set(bool $enabled): void {
    $GLOBALS['fixture_closed'] = $enabled;
}

final class WP_REST_Response
{
    public function __construct(public array $data, int $status) {}
    public function header(string $name, string $value): void {}
}

$port = filter_var(getenv('PROFILE_TEST_DB_PORT'), FILTER_VALIDATE_INT);
$wpDbFile = getenv('PROFILE_TEST_WPDB');
$fixturePassword = getenv('PROFILE_TEST_DB_PASSWORD');
if (!$port || $port < 1024 || $port > 65535 || getenv('PROFILE_TEST_DISPOSABLE') !== 'yes'
    || !$fixturePassword || !$wpDbFile || !is_file($wpDbFile) || hash_file('sha256', $wpDbFile) !== FIXTURE_WPDB_SHA) {
    fwrite(STDERR, "Requires an empty disposable loopback MariaDB and the pinned WordPress 6.9.4 wpdb file.\n");
    exit(77);
}

// Test-only functions stand in for the surrounding WP framework. The actual
// wpdb implementation, MySQL driver and MU-plugin handlers are loaded below.
require $wpDbFile;
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-panel.php';
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-jysk-game.php';

if (PHP_SAPI === 'cli-server') {
    $GLOBALS['fixture_mode'] = $_GET['mode'] ?? '';
    $GLOBALS['fixture_closed'] = $GLOBALS['fixture_mode'] === 'already-closed';
    $GLOBALS['legacy_reads'] = 0;
    $GLOBALS['activation_started'] = false;
    define('SAVEQUERIES', $GLOBALS['fixture_mode'] === 'savequeries');
    $wpdb = new wpdb('root', $fixturePassword, FIXTURE_DB, '127.0.0.1:' . $port);
    $wpdb->prefix = 'fixture_';
    $wpdb->query('SET SESSION innodb_lock_wait_timeout = 3');
    $pseudo = impactshop_identity_profile_cookie();
    $response = impactshop_identity_profile_get();
    /** @var WP_REST_Response $response */
    $handoff = Impactshop_Jysk_Game::profileReturnTarget(
        'app.sharity.hu',
        'GET',
        '/profil/?jysk_return=1&bridge_target=restore&impactshop_profile_return_complete=1'
    );
    echo json_encode([
        'profile' => $response->data,
        'repeat_allowed' => impactshop_identity_owner_authorized($pseudo),
        'handoff' => $handoff !== null,
        'legacy_read_count' => $GLOBALS['legacy_reads'],
        'globally_closed' => $GLOBALS['fixture_closed'],
        'activation_started' => $GLOBALS['activation_started'],
    ], JSON_THROW_ON_ERROR);
    return;
}

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function connection(int $port, string $password): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    return new mysqli('127.0.0.1', 'root', $password, FIXTURE_DB, $port);
}

function grant(mysqli $db, string $hash, string $pseudo, string $state, int $expiry, ?string $prior = null): void
{
    $db->execute_query(
        'INSERT INTO ' . FIXTURE_GRANTS .
        ' (grant_hash,pseudo_hash,created_at,last_seen_at,expires_at,state,supersedes_grant_hash) VALUES (?,?,?,?,?,?,?)',
        [$hash, impactshop_identity_owner_pseudo_hash($pseudo), gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', $expiry), $state, $prior]
    );
}

function start_request(string $url, string $mode, string $pseudo, string $token): array
{
    $process = proc_open(
        ['curl', '--silent', '--show-error', '--max-time', '8', '--include',
            '--cookie', "impactshop_pseudo_id=$pseudo; __Host-impactshop_owner=$token", "$url/?mode=$mode"],
        [1 => ['pipe', 'w'], 2 => STDERR],
        $pipes
    );
    check(is_resource($process), 'start fixture request');
    return [$process, $pipes[1]];
}

function finish_request(array $request): array
{
    [$process, $stream] = $request;
    $raw = stream_get_contents($stream);
    fclose($stream);
    check(proc_close($process) === 0, 'fixture HTTP transport succeeded');
    [$headers, $body] = explode("\r\n\r\n", $raw, 2);
    return [$headers, json_decode(trim($body), true, flags: JSON_THROW_ON_ERROR)];
}

$db = connection($port, $fixturePassword);
check((int) $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetch_row()[0] === 0,
    'start with an empty fixture database');
$db->query('CREATE TABLE ' . FIXTURE_GRANTS . " (
    grant_hash char(64) PRIMARY KEY, pseudo_hash char(64) NOT NULL,
    created_at datetime NOT NULL, last_seen_at datetime NOT NULL, expires_at datetime NOT NULL,
    revoked_at datetime NULL, state varchar(16) NOT NULL, activated_at datetime NULL,
    supersedes_grant_hash char(64) NULL, version smallint unsigned NOT NULL DEFAULT 2,
    KEY state_expires (state, expires_at), KEY pseudo_active (pseudo_hash, revoked_at, expires_at),
    KEY pseudo_state (pseudo_hash, state, expires_at), KEY supersedes_grant (supersedes_grant_hash),
    KEY expires_at (expires_at)
) ENGINE=InnoDB");
$db->query('CREATE TABLE fixture_weight (id INT PRIMARY KEY, modified INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$db->query('INSERT INTO fixture_weight (id) SELECT seq FROM seq_1_to_128');
$replacementToken = str_repeat('a', 64);
$replacementHash = impactshop_identity_owner_hash($replacementToken);
$priorHash = impactshop_identity_owner_hash(str_repeat('b', 64));
$otherToken = str_repeat('c', 64);
$now = time();
grant($db, $priorHash, REPLACEMENT_PSEUDO, 'active', $now + 3600);
grant($db, $replacementHash, REPLACEMENT_PSEUDO, 'pending', $now + 600, $priorHash);
grant($db, impactshop_identity_owner_hash($otherToken), OTHER_PSEUDO, 'pending', $now + 600);
$activeExpiry = $now + 365 * DAY_IN_SECONDS;
grant($db, str_repeat('d', 64), OTHER_PSEUDO, 'active', $activeExpiry - 120);
grant($db, str_repeat('e', 64), OTHER_PSEUDO, 'active', $activeExpiry + 120);

$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-S', $address, __FILE__],
    [1 => ['file', '/dev/null', 'w'], 2 => STDERR], $unused);
check(is_resource($server), 'start loopback fixture PHP server');
$url = 'http://' . $address;
try {
    $deadline = microtime(true) + 3;
    do {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) {
            fclose($ready);
            break;
        }
        usleep(20_000);
    } while (microtime(true) < $deadline);
    check((bool) $ready, 'HTTP fixture listens');
    // The native WP handle changes the pending row's indexed expiry while it
    // holds its primary lock. The peer holds the destination index range.
    foreach (['deadlock', 'lock-timeout', 'savequeries', 'rollback-error', 'headers-sent'] as $mode) {
        $lock = connection($port, $fixturePassword);
        $lock->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $lock->begin_transaction();
        $lock->query('UPDATE fixture_weight SET modified = modified + 1');
        $lock->execute_query(
            'SELECT grant_hash FROM ' . FIXTURE_GRANTS . " FORCE INDEX (state_expires)
             WHERE state='active' AND expires_at BETWEEN ? AND ? FOR UPDATE",
            [gmdate('Y-m-d H:i:s', $activeExpiry - 75), gmdate('Y-m-d H:i:s', $activeExpiry + 75)]
        );
        $request = start_request($url, $mode, REPLACEMENT_PSEUDO, $replacementToken);
        $deadline = microtime(true) + 2;
        do {
            $wait = $db->execute_query(
                'SELECT waiting.trx_query FROM information_schema.INNODB_LOCK_WAITS w
                 JOIN information_schema.INNODB_TRX waiting ON waiting.trx_id=w.requesting_trx_id
                 JOIN information_schema.INNODB_TRX holding ON holding.trx_id=w.blocking_trx_id
                 WHERE holding.trx_mysql_thread_id=?',
                [$lock->thread_id]
            )->fetch_row();
            if ($wait) {
                break;
            }
            // InnoDB caches the combined transaction/lock tables for 100 ms.
            usleep(160_000);
        } while (microtime(true) < $deadline);
        if (!$wait || !str_starts_with($wait[0], 'UPDATE ' . FIXTURE_GRANTS . " SET state = 'active'")) {
            fwrite(STDERR, 'Fixture wait: ' . ($wait[0] ?? 'no database wait') . "\n");
            $lock->rollback();
            [, $unexpected] = finish_request($request);
            fwrite(STDERR, 'Fixture outcome: ' . json_encode($unexpected) . "\n");
            throw new RuntimeException("$mode did not wait at the final activation UPDATE");
        }
        if ($mode !== 'lock-timeout') {
            $lock->execute_query('SELECT grant_hash FROM ' . FIXTURE_GRANTS . ' WHERE grant_hash=? FOR UPDATE', [$replacementHash]);
        }
        [$headers, $result] = finish_request($request);
        $lock->rollback();
        $lock->close();

        check($result['profile']['identity_state'] === 'unavailable' && $result['profile']['pseudo_id'] === ''
            && $result['profile']['nickname'] === null && $result['profile']['votes_available'] === null
            && $result['profile']['access_code_state'] === 'missing', "$mode withholds the profile");
        check(!$result['repeat_allowed'] && !$result['handoff'] && $result['legacy_read_count'] === 0,
            "$mode keeps repeat authorization and JYSK handoff denied");
        check($result['globally_closed'] === ($mode !== 'deadlock'), "$mode global policy");
        if ($mode !== 'headers-sent') {
            check(preg_match('/^Set-Cookie: __Host-impactshop_owner=deleted;.*max-age=0/im', $headers) === 1
                && preg_match('/^Set-Cookie: impactshop_pseudo_id=.+;.*max-age=0/im', $headers) === 1,
                "$mode emits both expiry headers");
        }
        $rows = $db->execute_query('SELECT state, revoked_at FROM ' . FIXTURE_GRANTS . ' WHERE grant_hash IN (?,?) ORDER BY state',
            [$priorHash, $replacementHash])->fetch_all(MYSQLI_NUM);
        check($rows === [['active', null], ['pending', null]], "$mode preserves prior and pending grants");
    }

    foreach (['unclassified', 'zero-rows', 'already-closed'] as $mode) {
        [, $result] = finish_request(start_request($url, $mode, REPLACEMENT_PSEUDO, $replacementToken));
        check($result['profile']['identity_state'] === 'unavailable' && !$result['repeat_allowed']
            && $result['globally_closed'] && !$result['handoff'], "$mode remains fail-closed");
    }
    [, $result] = finish_request(start_request($url, 'normal', OTHER_PSEUDO, $otherToken));
    check($result['profile']['identity_state'] === 'active' && $result['repeat_allowed'] && $result['handoff']
        && !$result['globally_closed'], 'another profile activates in its own request');
    [, $result] = finish_request(start_request($url, 'normal', REPLACEMENT_PSEUDO, $replacementToken));
    check($result['profile']['identity_state'] === 'active' && $result['repeat_allowed'] && $result['handoff'],
        'a subsequent request can activate the replacement');
} finally {
    proc_terminate($server);
    proc_close($server);
}
echo "WordPress profile activation deadlock, cookies and JYSK handoff: PASS\n";
