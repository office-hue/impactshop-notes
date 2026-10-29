<?php
/** Explicit local-only SQL integration: actual wpdb/managers, synthetic rows only.
 * JYSK_MYSQL_CONFIG points at the private configuration of the disposable container.
 * JYSK_WPDB_FILE points at the read-only, hash-verified wpdb source from the target.
 * No production clock, profile, hook registration or credentials are changed.
 */
declare(strict_types=1);

$configFile = getenv('JYSK_MYSQL_CONFIG') ?: '';
$wpdbFile = getenv('JYSK_WPDB_FILE') ?: '';
if (getenv('JYSK_MYSQL_SANDBOX_CONFIRM') !== 'LOCAL_JYSK_ONLY'
    || !is_file($configFile) || is_link($configFile) || (fileperms($configFile) & 0777) !== 0600) {
    throw new RuntimeException('Explicit private local sandbox configuration required');
}
$cfg = json_decode(file_get_contents($configFile), true, 16, JSON_THROW_ON_ERROR);
if (($cfg['host'] ?? '') !== '127.0.0.1' || ($cfg['database'] ?? '') !== 'jysk_sandbox'
    || ($cfg['user'] ?? '') !== 'jysk_test' || !is_int($cfg['port']) || $cfg['port'] < 1024
    || !is_file($wpdbFile) || is_link($wpdbFile)
    || hash_file('sha256', $wpdbFile) !== ($cfg['wpdbSha256'] ?? '')) {
    throw new RuntimeException('Refusing nonlocal or unverified database input');
}
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$filters = []; $actions = []; $fault = null; $checks = 0;
function check(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $message); }
}
function add_filter($name, $callback, $priority = 10, ...$rest): void { $GLOBALS['filters'][$name][$priority][] = $callback; }
function has_filter($name, $callback) {
    foreach ($GLOBALS['filters'][$name] ?? [] as $priority => $callbacks) {
        if (in_array($callback, $callbacks, true)) { return $priority; }
    }
    return false;
}
function add_action(...$args): void {}
function did_action($name): int { return $name === 'template_redirect' ? 1 : 0; }
function apply_filters($name, $value, ...$rest) {
    foreach ($GLOBALS['filters'][$name] ?? [] as $callbacks) {
        foreach ($callbacks as $cb) { $value = $cb($value, ...$rest); }
    }
    if ($name === 'query' && is_callable($GLOBALS['fault'])) { $value = ($GLOBALS['fault'])($value); }
    return $value;
}
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function current_time($format) { return '2026-11-15 12:00:00'; }
function wp_load_translations_early(): void {}
function __($text) { return $text; }
function _doing_it_wrong($function, $message, $version): void { throw new RuntimeException($function . ': ' . strip_tags($message)); }
function wp_debug_backtrace_summary(...$args) { return 'isolated-local-jysk-test'; }
function mbstring_binary_safe_encoding(): void {}
function reset_mbstring_encoding(): void {}
function is_wp_error($value): bool { return false; }
function absint($value): int { return abs((int) $value); }
function is_multisite(): bool { return false; }
function do_action($name, ...$args): void {
    global $wpdb, $actions;
    $row = mysqli_fetch_row(mysqli_query($wpdb->dbh, 'SELECT @@in_transaction'));
    check($row[0] === '0', 'reward action is outside the transaction');
    $actions[] = $name;
}
require $wpdbFile;
require __DIR__ . '/../wp-content/mu-plugins/sharity-level-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-manager.php';
function connection(): wpdb {
    global $cfg;
    $db = new wpdb($cfg['user'], $cfg['password'], $cfg['database'], '127.0.0.1:' . $cfg['port']);
    $db->suppress_errors(true); $db->set_prefix('jysk_test_');
    if (!$db->ready) { throw new RuntimeException('Local database unavailable'); }
    return $db;
}
function sql(wpdb $db, string $query) {
    $answer = $db->query($query);
    if ($answer === false || $db->last_error !== '') { throw new RuntimeException('Sandbox SQL failed: ' . $db->last_error); }
    return $answer;
}
function award(string $key = 'jysk:real-sql', string $pseudo = 'abcdef123456'): array {
    return (new Sharity_Points_Manager())->award_points_for_pseudo($pseudo, 5, 'bonus', 'local-sql-test', ['source_type' => 'jysk_campaign'], $key);
}
function loadBridge(): string {
    if (!class_exists('JyskSqlFixture\\Impactshop_Jysk_Game')) {
        define('WP_CLI', true);
        define('JYSK_GAME_ENABLED', true);
        define('JYSK_GAME_SERVICE_KEY', str_repeat('1', 64));
        define('JYSK_GAME_SUBJECT_KEY', str_repeat('2', 64));
        define('JYSK_GAME_REWARDS_ENABLED', true);
        define('JYSK_GAME_TRANSACTION_READY', true);
        define('JYSK_GAME_POLICY_HASH', str_repeat('b', 64));
        define('JYSK_GAME_PRODUCER_SHAS', [str_repeat('c', 40)]);
        define('JYSK_GAME_NGO_IDS', ['ngo-1', 'ngo-2']);
        define('JYSK_GAME_TEST_SUBJECTS', [str_repeat('d', 64)]);
        $GLOBALS['clock'] = strtotime('2026-11-15T12:00:00Z');
        // Same fixture-only namespaced clock technique as the existing unit suite.
        // Production file is never edited and no production database is available.
        $source = str_replace('declare(strict_types=1);', '', substr(file_get_contents(__DIR__ . '/../wp-content/mu-plugins/impactshop-jysk-game.php'), 5));
        eval('declare(strict_types=1); namespace JyskSqlFixture; use \\RuntimeException; use \\Throwable; use \\DateTimeImmutable; use \\DateTimeZone; use \\Sharity_Points_Manager; function time() { return $GLOBALS["clock"]; } ' . $source);
    }
    return 'JyskSqlFixture\\Impactshop_Jysk_Game';
}
function impactshop_identity_owner_grant_table() { return 'jysk_test_identity_grants'; }
function impactshop_identity_owner_pseudo_hash($pseudo) { return hash('sha256', $pseudo); }
function bridgePayload(int $slot = 0, bool $technical = false, bool $video = false, int $day = 6): array {
    $subject = str_repeat($technical ? 'd' : 'a', 64); $kind = $video ? 'video_completed' : 'quiz_answered';
    return ['eventId' => hash('sha256', "jysk_2026:{$subject}:{$kind}:{$day}:{$slot}"), 'subject' => $subject, 'kind' => $kind,
        'dayIndex' => $day, 'slot' => $slot, 'ngoId' => 'ngo-1', 'points' => $video ? 10 : 5,
        'occurredAt' => gmdate('Y-m-d', $GLOBALS['clock']) . 'T11:00:00.000Z', 'policyHash' => str_repeat('b', 64), 'producerSha' => str_repeat('c', 40), 'isTest' => $technical];
}
function bridgeReward(array $payload, bool $write = true): array {
    return (new ReflectionMethod(loadBridge(), 'reward'))->invoke(null, $payload, $write);
}
$wpdb = connection();
if (in_array($argv[1] ?? '', ['--results-worker','--results-approve-worker'], true)) {
    $bridge = loadBridge(); $GLOBALS['clock'] = strtotime('2026-11-30T12:00:00Z');
    $delayed = false;
    $fault = static function ($query) use (&$delayed) {
        if (!$delayed && str_contains($query, 'FOR UPDATE')) { $delayed = true; usleep(200000); }
        return $query;
    };
    try { $result = $argv[1] === '--results-approve-worker' ? $bridge::approveResults($argv[2],1,['kind'=>'jysk-game-results-verification-v1','manifestHash'=>$argv[2],'datasetHash'=>str_repeat('1',64),'decisionsHash'=>str_repeat('2',64),'checkedAt'=>'2026-11-30T12:00:00.000Z','verified'=>true]) : $bridge::resumeResults($argv[2]); }
    catch (Throwable $e) { $result = ['error' => $e->getMessage()]; }
    echo json_encode($result), "\n"; exit;
}
if (in_array($argv[1] ?? '', ['--worker', '--bridge-worker'], true)) {
    $delayed = false; $started = false; $transactionWindow = null;
    $fault = static function ($query) use (&$delayed, &$started, &$transactionWindow) {
        global $wpdb;
        if ($query === 'START TRANSACTION') { $started = true; return $query; }
        if (!$delayed && $started) {
            $delayed = true;
            $row = mysqli_fetch_row(mysqli_query($wpdb->dbh, 'SELECT @@in_transaction'));
            check($row[0] === '1', 'worker delay is inside an actual database transaction');
            $from = microtime(true);
            if (mysqli_query($wpdb->dbh, 'SELECT SLEEP(0.1)') === false) { throw new RuntimeException('Worker overlap delay failed'); }
            $transactionWindow = [$from, microtime(true)];
        }
        return $query;
    };
    echo "READY\n"; fflush(STDOUT);
    check($wpdb->get_var("SELECT GET_LOCK('jysk_local_barrier',15)") === '1', 'worker barrier acquired');
    sql($wpdb, "DO RELEASE_LOCK('jysk_local_barrier')");
    $began = microtime(true);
    if ($argv[1] === '--bridge-worker') { loadBridge(); $result = bridgeReward(bridgePayload()); }
    else { $result = award($argv[2], $argv[3]); }
    $result['testWindow'] = [$began, microtime(true)];
    $result['testTransactionWindow'] = $transactionWindow;
    echo json_encode($result), "\n";
    exit;
}
check(get_class($wpdb) === 'wpdb', 'actual target wpdb, not SQL mock or subclass');
check(str_starts_with((string) $wpdb->get_var('SELECT VERSION()'), '10.11.19-MariaDB'), 'matching MariaDB major/minor/patch');
check($wpdb->get_var('SELECT DATABASE()') === 'jysk_sandbox', 'only isolated database');
$originalRetries = $wpdb->reconnect_retries;
$wpdb->reconnect_retries = 0;
check($wpdb->reconnect_retries === 0, 'real wpdb magic setter disables reconnect');
$wpdb->reconnect_retries = $originalRetries;

// Extract the three exact schema definitions, without executing WP/plugin boot hooks.
$source = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/sharity-points.php');
$tables = ['user_points', 'point_transactions', 'level_history'];
$existing = $wpdb->get_col('SHOW TABLES');
$bridgeTables = ['identity_grants', 'jysk_game_grants', 'jysk_game_codes', 'jysk_game_sessions', 'jysk_game_nonces', 'jysk_game_rewards', 'jysk_game_message_map', 'jysk_game_results', 'jysk_game_prizes', 'jysk_game_prize_events', 'impact_vote_messages', 'impact_vote_message_targets'];
check(array_diff($existing, array_map(fn($t) => 'jysk_test_' . $t, array_merge($tables, $bridgeTables))) === [], 'no foreign tables in sandbox');
foreach ($tables as $table) {
    $pattern = '/\$tables\[\] = "(CREATE TABLE \{\$wpdb->prefix\}' . $table . '.*?) \{\$charset\};";/s';
    check(preg_match($pattern, $source, $m) === 1, 'extract exact schema ' . $table);
    if (!in_array('jysk_test_' . $table, $existing, true)) {
        sql($wpdb, str_replace('{$wpdb->prefix}', 'jysk_test_', $m[1]) . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
function resetRows(bool $seed = true): void {
    global $wpdb, $fault, $actions;
    $fault = null; $actions = [];
    foreach (['point_transactions', 'level_history', 'user_points'] as $t) { sql($wpdb, 'TRUNCATE jysk_test_' . $t); }
    if ($seed) { sql($wpdb, "INSERT INTO jysk_test_user_points(pseudo_id,points_total,points_lifetime,current_level) VALUES('abcdef123456',1995,1995,'basic')"); }
}
function snapshot(wpdb $db): array {
    return [$db->get_results('SELECT pseudo_id,points_total,points_lifetime,current_level FROM jysk_test_user_points ORDER BY pseudo_id', ARRAY_A),
        (int) $db->get_var('SELECT COUNT(*) FROM jysk_test_point_transactions'),
        (int) $db->get_var('SELECT COUNT(*) FROM jysk_test_level_history')];
}
resetRows();
check(award()['success'] === true, 'real award succeeds');
check(snapshot($wpdb)[0][0]['points_total'] === '2000' && snapshot($wpdb)[1] === 1 && snapshot($wpdb)[2] === 1, 'real totals ledger and level history committed');
check($actions === ['sharity_level_changed', 'sharity_points_earned'], 'both actions after confirmed commit');
check(award()['duplicate'] === true && snapshot($wpdb)[1] === 1, 'replay produces exactly one ledger entry');
resetRows(false); check(award()['success'] === true && snapshot($wpdb)[0][0]['points_total'] === '5', 'new pseudo inserted in same real transaction');

// Actual server errors via wpdb query hook, not fabricated SQL return values.
foreach (['SET TRANSACTION', 'START TRANSACTION', 'SELECT COUNT(*)', 'INSERT INTO `jysk_test_user_points`', 'INSERT IGNORE INTO jysk_test_point_transactions', 'UPDATE jysk_test_user_points', 'SELECT points_total', 'SELECT current_level', 'UPDATE `jysk_test_user_points`', 'INSERT INTO `jysk_test_level_history`', 'COMMIT'] as $pattern) {
    resetRows($pattern !== 'INSERT INTO `jysk_test_user_points`'); $before = snapshot($wpdb); $hit = false;
    $fault = static function ($query) use ($pattern, &$hit) {
        if (!$hit && str_contains($query, $pattern)) { $hit = true; return 'SELECT * FROM jysk_deliberately_missing_table'; }
        return $query;
    };
    $result = award(); $fault = null;
    check($hit, 'fault actually exercised ' . $pattern);
    check($result['success'] === false && snapshot($wpdb) === $before, 'actual rollback keeps all tables unchanged ' . $pattern);
    check($actions === [] && $wpdb->reconnect_retries === $originalRetries, 'no action and retries restored ' . $pattern);
}
resetRows(); $hit = false;
$fault = static function ($query) use (&$hit) {
    global $wpdb;
    if ($query === 'COMMIT' && !$hit) { $hit = true; check(mysqli_query($wpdb->dbh, 'COMMIT') === true, 'server commit before lost ACK'); return 'SELECT * FROM jysk_lost_commit_ack'; }
    return $query;
};
$result = award(); $fault = null;
check($hit && $result['success'] === false && snapshot($wpdb)[1] === 1, 'durable ambiguous commit is not reported as success');
check($actions === [], 'lost COMMIT acknowledgement emits no success hooks');
check(award()['duplicate'] === true && snapshot($wpdb)[1] === 1, 'readback plus same-key replay never double credits');

resetRows(); $observer = connection(); $hit = false;
$fault = static function ($query) use (&$hit, $observer) {
    global $wpdb;
    if ($query === 'COMMIT' && !$hit) { $hit = true; sql($observer, 'KILL CONNECTION ' . (int) $wpdb->dbh->thread_id); }
    return $query;
};
$before = snapshot($observer); $result = award(); $fault = null;
check($hit && $result['success'] === false && snapshot($observer) === $before, 'real connection kill rolls back all tables');
check($actions === [] && $wpdb->reconnect_retries === $originalRetries, 'connection loss never reconnects or emits success');
$wpdb = connection();

function parallel(array $keys, bool $bridge = false): array {
    global $wpdb;
    check($wpdb->get_var("SELECT GET_LOCK('jysk_local_barrier',0)") === '1', 'parent acquires barrier');
    $workers = [];
    foreach ($keys as $key) {
        $p = proc_open([PHP_BINARY, __FILE__, $bridge ? '--bridge-worker' : '--worker', $key, 'abcdef123456'], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($p)) { throw new RuntimeException('Worker launch failed'); }
        fclose($pipes[0]); stream_set_timeout($pipes[1], 20);
        check(trim((string) fgets($pipes[1])) === 'READY', 'independent worker connected');
        $workers[] = [$p, $pipes];
    }
    sql($wpdb, "DO RELEASE_LOCK('jysk_local_barrier')"); $answers = [];
    foreach ($workers as [$p, $pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($p) === 0 && $err === '', 'worker completed without hidden failure');
        $answers[] = json_decode(trim($out), true, 16, JSON_THROW_ON_ERROR);
    }
    $overlap = false;
    foreach ($answers as $i => $a) { foreach ($answers as $j => $b) {
        if ($i !== $j && max($a['testWindow'][0], $b['testWindow'][0]) < min($a['testWindow'][1], $b['testWindow'][1])) { $overlap = true; }
    } }
    check($overlap, 'measured concurrent operations actually overlap');
    $sqlOverlap = false;
    foreach ($answers as $i => $a) { foreach ($answers as $j => $b) {
        // Bridge event locks deliberately begin before their durable claim transaction.
        if ($bridge && (!is_array($a['testTransactionWindow']) || ($b['state'] ?? '') !== 'busy')) { continue; }
        $first = $bridge && is_array($a['testTransactionWindow']) ? $a['testWindow'] : $a['testTransactionWindow'];
        $second = $bridge && ($b['state'] ?? '') === 'busy' ? $b['testWindow'] : $b['testTransactionWindow'];
        if ($i !== $j && is_array($first) && is_array($second)
            && max($first[0], $second[0]) < min($first[1], $second[1])) { $sqlOverlap = true; }
    } }
    check($sqlOverlap, $bridge ? 'busy bridge request overlaps the verified lock-holder operation' : 'measured active database transactions actually overlap');
    return $answers;
}
resetRows(); $answers = parallel(array_fill(0, 6, 'jysk:concurrent-same-key'));
check(count(array_filter($answers, fn($r) => ($r['success'] ?? false) === true)) === 6, 'concurrent same-key attempts resolved');
check(snapshot($wpdb)[0][0]['points_total'] === '2000' && snapshot($wpdb)[1] === 1 && snapshot($wpdb)[2] === 1, 'six concurrent requests credit and upgrade once');
resetRows(); $answers = parallel(array_map(fn($i) => 'jysk:parallel-' . $i, range(1, 6)));
check(count(array_filter($answers, fn($r) => ($r['success'] ?? false) === true)) === 6, 'distinct concurrent events all succeed');
check(snapshot($wpdb)[0][0]['points_total'] === '2025' && snapshot($wpdb)[1] === 6 && snapshot($wpdb)[2] === 1, 'atomic increments do not lose updates or duplicate level history');

$bridge = loadBridge(); $bridge::install();
sql($wpdb, 'CREATE TABLE IF NOT EXISTS jysk_test_identity_grants(grant_hash char(64) PRIMARY KEY,pseudo_hash char(64) NOT NULL,state varchar(24) NOT NULL,revoked_at datetime NULL,expires_at datetime NOT NULL) ENGINE=InnoDB ' . $wpdb->get_charset_collate());
sql($wpdb, 'ALTER TABLE jysk_test_identity_grants CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . $wpdb->collate);
function resetBridge(): void {
    global $wpdb;
    resetRows();
    foreach (['identity_grants', 'jysk_game_grants', 'jysk_game_codes', 'jysk_game_sessions', 'jysk_game_nonces', 'jysk_game_rewards', 'jysk_game_message_map'] as $t) { sql($wpdb, 'TRUNCATE jysk_test_' . $t); }
    foreach ([[str_repeat('a', 64), 'abcdef123456'], [str_repeat('d', 64), 'abcdeffedcba']] as [$subject, $pseudo]) {
        $grant = hash('sha256', 'local-owner-' . $subject);
        sql($wpdb, $wpdb->prepare("INSERT INTO jysk_test_identity_grants(grant_hash,pseudo_hash,state,expires_at) VALUES(%s,%s,'active','2099-01-01 00:00:00')", $grant, hash('sha256', $pseudo)));
        sql($wpdb, $wpdb->prepare('INSERT INTO jysk_test_jysk_game_grants(subject,pseudo,grant_hash,generation) VALUES(%s,%s,%s,%s)', $subject, $pseudo, $grant, str_repeat('e', 64)));
    }
}
resetBridge();
check((new ReflectionMethod($bridge, 'transactionReady'))->invoke(null) === true, 'actual engine writer and complete unique indices accepted');
sql($wpdb, 'ALTER TABLE jysk_test_jysk_game_rewards ENGINE=MyISAM');
check((new ReflectionMethod($bridge, 'transactionReady'))->invoke(null) === false, 'nontransactional claim engine rejected');
sql($wpdb, 'ALTER TABLE jysk_test_jysk_game_rewards ENGINE=InnoDB');
sql($wpdb, 'ALTER TABLE jysk_test_point_transactions DROP INDEX uniq_dedupe_pseudo');
check((new ReflectionMethod($bridge, 'transactionReady'))->invoke(null) === false, 'missing canonical economic unique index rejected');
sql($wpdb, 'ALTER TABLE jysk_test_point_transactions ADD UNIQUE KEY uniq_dedupe_pseudo(pseudo_id,dedupe_key)');
check(bridgeReward(bridgePayload())['state'] === 'confirmed', 'bridge claim and actual canonical award confirm');
check(bridgeReward(bridgePayload())['state'] === 'confirmed' && snapshot($wpdb)[1] === 1, 'bridge economic replay does not double credit');
$different = bridgePayload(); $different['ngoId'] = 'ngo-2';
check(bridgeReward($different)['state'] === 'conflict', 'immutable payload conflict rejected');

resetBridge(); $hit = false;
$fault = static function ($query) use (&$hit) {
    if (!$hit && str_contains($query, 'INSERT IGNORE INTO jysk_test_point_transactions')) { $hit = true; return 'SELECT * FROM jysk_failed_award_insert'; }
    return $query;
};
check(bridgeReward(bridgePayload())['state'] === 'unknown' && $hit, 'manager SQL failure leaves durable unknown claim'); $fault = null;
check((int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_rewards') === 1 && snapshot($wpdb)[1] === 0, 'claim durable but no fabricated canonical receipt');
check(bridgeReward(bridgePayload())['state'] === 'unknown' && snapshot($wpdb)[1] === 0, 'unknown claim never blindly retried');
check(bridgeReward(bridgePayload(), false)['state'] === 'absent', 'writer DB under event lock proves absence');
check(bridgeReward(bridgePayload())['state'] === 'confirmed' && snapshot($wpdb)[1] === 1, 'same immutable payload can retry only after absent readback');

resetBridge(); $commits = 0;
$fault = static function ($query) use (&$commits) {
    global $wpdb;
    if ($query === 'COMMIT' && ++$commits === 2) { check(mysqli_query($wpdb->dbh, 'COMMIT') === true, 'actual bridge manager commit before lost ACK'); return 'SELECT * FROM jysk_lost_bridge_commit_ack'; }
    return $query;
};
check(bridgeReward(bridgePayload())['state'] === 'unknown', 'bridge does not claim success after ambiguous commit'); $fault = null;
check(snapshot($wpdb)[1] === 1 && $actions === [], 'ambiguous bridge award stored exactly once without success hook');
check(bridgeReward(bridgePayload(), false)['state'] === 'confirmed', 'canonical writer receipt resolves lost ACK');
check(bridgeReward(bridgePayload())['state'] === 'confirmed' && snapshot($wpdb)[1] === 1, 'replay after lost ACK never calls writer twice');

resetBridge(); $answers = parallel(array_fill(0, 6, 'unused-bridge-key'), true);
check(array_diff(array_column($answers, 'state'), ['confirmed', 'busy']) === [], 'competing real event locks are confirmed or busy');
check(bridgeReward(bridgePayload(), false)['state'] === 'confirmed' && snapshot($wpdb)[1] === 1, 'six concurrent bridge requests yield one canonical receipt');
check($wpdb->reconnect_retries === $originalRetries, 'bridge restores original real wpdb retry configuration');

resetBridge();
for ($i = 0; $i < 5; $i++) { check(bridgeReward(bridgePayload($i, true))['state'] === 'confirmed', 'technical quiz ' . $i); }
check(bridgeReward(bridgePayload(0, true, true))['state'] === 'confirmed', 'technical video completes 35-point cap');
$GLOBALS['clock'] += 86400;
check(bridgeReward(bridgePayload(0, true, false, 7))['state'] === 'policy_blocked' && snapshot($wpdb)[1] === 6, 'technical cap persists across campaign days');
check((int) $wpdb->get_var("SELECT points_total FROM jysk_test_user_points WHERE pseudo_id='abcdeffedcba'") === 35, 'technical SQL ledger total is exactly 35');
// The new result writer runs on the same local-only SQL connection, never staging.
$GLOBALS['clock'] = strtotime('2026-11-30T12:00:00Z'); $fault = null;
$legacy = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/impactshop-vote-jysk.php');
foreach (['messages' => 'impact_vote_messages', 'targets' => 'impact_vote_message_targets'] as $variable => $table) {
    check(preg_match('/\\$sql_' . $variable . ' = "(CREATE TABLE \\{\\$' . $variable . '\\}.*?) \\{\\$charset\\};";/s', $legacy, $m) === 1, 'extract existing message schema ' . $table);
    sql($wpdb, str_replace('{$' . $variable . '}', 'jysk_test_' . $table, str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $m[1])) . ' ENGINE=InnoDB ' . $wpdb->get_charset_collate());
}
$bridge::installResults();
function resetResults(): void {
    global $wpdb, $fault; $fault = null;
    foreach (['jysk_game_results','jysk_game_prizes','jysk_game_prize_events','jysk_game_message_map','impact_vote_messages','impact_vote_message_targets'] as $t) { sql($wpdb, 'TRUNCATE jysk_test_' . $t); }
}
function resultManifest(): array {
    global $wpdb;
    $members = [];
    foreach (range(1, 4) as $i) {
        $pseudo = 'results-' . $i;
        $subject = hash_hmac('sha256', 'jysk_2026:' . $pseudo, hex2bin(str_repeat('2', 64)));
        sql($wpdb, $wpdb->prepare('INSERT INTO jysk_test_jysk_game_grants(subject,pseudo,grant_hash,generation) VALUES(%s,%s,%s,%s) ON DUPLICATE KEY UPDATE pseudo=VALUES(pseudo)', $subject, $pseudo, hash('sha256', $pseudo), str_repeat('e',64)));
        $members[] = ['personKey' => hash('sha256', 'person-' . $i),'subject' => $subject,'subjects' => [$subject],'weight' => $i * 5];
    }
    return ['kind' => 'jysk-game-results-v1','campaign' => 'jysk_2026','sourceCommit' => str_repeat('c',40),'sourceTree' => str_repeat('d',40),'target' => str_repeat('e',64),
        'configVersion' => 8,'rosterVersion' => 1,'policyHash' => str_repeat('b',64),'termsVersion' => 'terms-v1','privacyVersion' => 'privacy-v1',
        'endsAt' => '2026-11-29T23:00:00.000Z','generatedAt' => '2026-11-30T00:00:00.000Z','datasetHash' => str_repeat('1',64),'decisionsHash' => str_repeat('2',64),'rosterHash' => str_repeat('3',64),'reviewReference' => str_repeat('4',64),
        'eventCount' => 10,'validEventCount' => 10,'globalPoints' => ['queued'=>0,'sending'=>0,'unknown'=>0,'delivered'=>10,'rejected'=>0],
        'ngos' => [['ngoId'=>'ngo-1','displayName'=>'Civil one','votes'=>25],['ngoId'=>'ngo-2','displayName'=>'Civil two','votes'=>25]],'members' => $members];
}
function resultProof(string $hash): array { return ['kind'=>'jysk-game-results-verification-v1','manifestHash'=>$hash,'datasetHash'=>str_repeat('1',64),'decisionsHash'=>str_repeat('2',64),'checkedAt'=>'2026-11-30T12:00:00.000Z','verified'=>true]; }
resetResults(); $raw = json_encode(resultManifest(), JSON_UNESCAPED_SLASHES); $hash = hash('sha256', $raw);
check($bridge::prepareResults($raw, $hash, 0)['version'] === 1, 'private exact result prepared');
$answer = $bridge::approveResults($hash, 1, resultProof($hash));
check($answer['state'] === 'published' && $answer['notices'] === 3, 'approval draws and notifies');
check((int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_prizes') === 3 && (int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_impact_vote_messages') === 3, 'three prizes and generic messages committed together');
check((int) $wpdb->get_var("SELECT COUNT(*) FROM jysk_test_impact_vote_messages WHERE content LIKE '%10 000%' OR content LIKE '%Hivatkozás%'") === 0, 'shared messages contain no private prize data');
$before = $wpdb->get_var("SELECT result_json FROM jysk_test_jysk_game_results");
$bridge::resumeResults($hash);
check($wpdb->get_var("SELECT result_json FROM jysk_test_jysk_game_results") === $before && (int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_impact_vote_messages') === 3, 'resume never redraws or duplicates notices');
$p = $wpdb->get_row('SELECT * FROM jysk_test_jysk_game_prizes ORDER BY slot_no LIMIT 1', ARRAY_A);
$privateRead = new ReflectionMethod($bridge, 'prizeMessages');
$first = $privateRead->invoke(null, $p['subject'], null);
check(count($first['messages']) === 1 && str_contains($first['messages'][0]['body'], $p['claim_reference']), 'own private prize readable');
check($privateRead->invoke(null, str_repeat('f',64), null)['messages'] === [], 'foreign subject cannot read prize');
sql($wpdb, "UPDATE jysk_test_impact_vote_messages SET end_at='2000-01-01 00:00:00'");
sql($wpdb, 'UPDATE jysk_test_impact_vote_message_targets SET is_read=1');
$ack = $privateRead->invoke(null, $p['subject'], (string) $p['message_id']);
check($ack['deliveredAt'] === '2026-11-30T12:00:00Z' && $ack['claimDeadline'] === '2026-12-07T12:00:00Z', 'ACK independent of common expiry/read flag');
$GLOBALS['clock'] += 86400;
check($privateRead->invoke(null, $p['subject'], (string) $p['message_id'])['claimDeadline'] === $ack['claimDeadline'], 'second ACK cannot extend deadline');
check(count($privateRead->invoke(null, $p['subject'], null)['messages']) === 1, 'private prize remains readable after ACK');
$found=$bridge::prizeStatus($p['claim_reference']);
check(count($found)===1 && (int)$found[0]['slot_no']===(int)$p['slot_no'],'private claim reference resolves to exact operator case');
$GLOBALS['clock'] = strtotime('2026-11-30T12:00:00Z');
// An independent connection owns the global result lock: ACK must not claim success.
$secondDb=connection(); $lockName='jysk:' . substr(hash('sha256','jysk-results-2026'),0,58);
check($secondDb->get_var($secondDb->prepare('SELECT GET_LOCK(%s,0)',$lockName))==='1','other SQL connection holds result lock');
try { $privateRead->invoke(null,$p['subject'],(string)$p['message_id']); check(false,'busy ACK must fail'); } catch(Throwable $e) { check(str_contains($e->getMessage(),'storage_unavailable'),'busy ACK not reported delivered'); }
sql($secondDb,$secondDb->prepare('DO RELEASE_LOCK(%s)',$lockName)); $secondDb->close();
// Human claim decisions are append-only; no timer forfeits an incomplete claim.
$p=$wpdb->get_row('SELECT * FROM jysk_test_jysk_game_prizes ORDER BY slot_no LIMIT 1',ARRAY_A);
$event=['round'=>0,'slot'=>(int)$p['slot_no'],'kind'=>'received','occurredAt'=>'2026-11-30T12:00:00Z','reference'=>str_repeat('7',64),'details'=>'office.claim.record'];
$v=(int)$p['version']; $bridge::recordPrize($event,$v++);
$event['kind']='deficiency-sent'; $bridge::recordPrize($event,$v++);
$event['kind']='deficiency-delivered'; $bridge::recordPrize($event,$v++);
check($bridge::prizeStatus($p['claim_reference'])[0]['case_state']==='deficiency-delivered','incomplete timely claim remains held, no automatic forfeiture');
try { $bridge::redrawPrizes($hash,3,['slots'=>[(int)$p['slot_no']],'reference'=>str_repeat('8',64),'deadlinesReviewed'=>true]); check(false,'held claim must not redraw'); } catch(Throwable $e) { check(str_contains($e->getMessage(),'redraw_not_vacant'),'only final forfeiture permits replacement'); }
$event['kind']='decision'; $bridge::recordPrize($event,$v++);
$event['kind']='forfeit-decision'; $bridge::recordPrize($event,$v++);
$redraw=$bridge::redrawPrizes($hash,3,['slots'=>[(int)$p['slot_no']],'reference'=>str_repeat('8',64),'deadlinesReviewed'=>true]);
check($redraw['round']===1 && $redraw['notices']===1 && (int)$wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_prizes')===4,'single approved replacement fills one genuinely vacated slot');
try { $bridge::redrawPrizes($hash,5,['slots'=>[(int)$p['slot_no']],'reference'=>str_repeat('8',64),'deadlinesReviewed'=>true]); check(false,'second replacement forbidden'); } catch(Throwable $e) { check(str_contains($e->getMessage(),'results_version_conflict'),'no second replacement round'); }
// Actual SQL error in the notification transaction: seed persists; all partial prizes roll back.
resetResults(); $raw = json_encode(resultManifest()); $hash = hash('sha256', $raw); $bridge::prepareResults($raw, $hash, 0);
$hit = false; $fault = static function ($q) use (&$hit) { if (!$hit && str_starts_with($q, 'INSERT INTO jysk_test_impact_vote_message_targets')) { $hit=true; return 'SELECT * FROM jysk_missing_result_target'; } return $q; };
try { $bridge::approveResults($hash, 1, resultProof($hash)); check(false, 'target insertion must fail'); } catch (Throwable $e) { check($hit, 'actual target SQL fault injected'); }
$fault = null;
check($bridge::resultsStatus()['state'] === 'approved' && (int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_impact_vote_messages') === 0 && (int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_prizes') === 0, 'durable seed but no partial notice/prize');
$seed = $wpdb->get_var('SELECT seed FROM jysk_test_jysk_game_results');
$bridge::resumeResults($hash);
check($wpdb->get_var('SELECT seed FROM jysk_test_jysk_game_results') === $seed && (int) $wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_prizes') === 3, 'resume uses original seed');
// COMMIT succeeds in MariaDB, but the caller receives a failure.
resetResults(); $raw = json_encode(resultManifest()); $hash = hash('sha256',$raw); $bridge::prepareResults($raw,$hash,0);
$commits=0; $fault=static function($q) use (&$commits,$wpdb) { if ($q==='COMMIT' && ++$commits===2) { check(mysqli_query($wpdb->dbh,'COMMIT')===true,'real result commit precedes lost ACK'); return 'SELECT * FROM jysk_lost_results_commit_ack'; } return $q; };
try { $bridge::approveResults($hash,1,resultProof($hash)); check(false,'lost ACK must fail'); } catch(Throwable $e) { check(true,'unknown commit not claimed as success'); }
$fault=null;
check($bridge::resultsStatus()['state']==='published','readback resolves unknown commit');
$bridge::resumeResults($hash);
check((int)$wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_prizes')===3,'unknown commit replay does not duplicate prizes');
// Multiple real processes contend for an already approved immutable snapshot.
resetResults(); $raw=json_encode(resultManifest()); $hash=hash('sha256',$raw); $bridge::prepareResults($raw,$hash,0);
$workers=[];
for($i=0;$i<3;$i++) { $pipes=[]; $proc=proc_open([PHP_BINARY,__FILE__,'--results-approve-worker',$hash],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes); if(!is_resource($proc)){throw new RuntimeException('worker start failed');} fclose($pipes[0]); $workers[]=[$proc,$pipes]; }
$answers=[];
foreach($workers as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); check(proc_close($proc)===0 && $err==='','result worker exited cleanly'); $answers[]=json_decode(trim($out),true,16,JSON_THROW_ON_ERROR); }
check(count(array_filter($answers,fn($r)=>($r['state']??'')==='published'))>=1,'one concurrent result publisher completes');
check(count(array_filter($answers,fn($r)=>isset($r['error']) && $r['error']!=='results_version_conflict'))===0 && array_diff(array_column($answers,'state'),['published','busy'])===[] && (int)$wpdb->get_var('SELECT COUNT(*) FROM jysk_test_jysk_game_prizes')===3,'concurrent approvals cannot duplicate seed, draw or messages');
echo json_encode(['result' => 'PASS', 'checks' => $checks, 'scope' => 'target wpdb + canonical points + durable results/notifications; local MariaDB only',
    'server' => $wpdb->get_var('SELECT VERSION()'), 'wpdbSha256' => hash_file('sha256', $wpdbFile), 'productionWritten' => false,
    'limitations' => ['WP boot, owner authentication, HTTP/TLS and downstream hook execution not exercised', 'fixture clock only; production source and time unchanged', 'not a production deployment or activation proof']]), "\n";
