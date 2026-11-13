<?php

declare(strict_types=1);

// Focused protocol/SQL-port stub only. This is NOT MariaDB transaction proof.
define('ABSPATH', __DIR__ . '/fixture-not-wordpress/');
define('ARRAY_A', 'ARRAY_A');

final class ObservationDbStub
{
    public string $prefix = 'synthetic_';
    public array $rows = [];
    public array $sql = [];
    public array $prepared = [];
    public bool $suppressed = false;
    public bool $failWrite = false;
    public bool $failRead = false;
    public string $active = '0';
    public string $autocommit = '1';
    public int $insert_id = 812;
    public string $last_error = 'existing-caller-error';
    public int $rows_affected = 4;
    public int $num_rows = 3;
    public string $last_query = 'existing-caller-query';
    public array $last_result = [['existing' => true]];

    public function suppress_errors(bool $enabled): bool
    {
        $old = $this->suppressed;
        $this->suppressed = $enabled;
        return $old;
    }

    public function prepare(string $sql, ...$args): string
    {
        $id = '__prepared_' . count($this->prepared);
        $this->prepared[$id] = [$sql, $args];
        return $id;
    }

    public function get_row(string $query, $format): ?array
    {
        $query = str_starts_with($query, 'SET STATEMENT max_statement_time=0.015 FOR ') ? substr($query, strlen('SET STATEMENT max_statement_time=0.015 FOR ')) : $query;
        $this->last_error = '';
        $this->last_result = [];
        $this->num_rows = 1;
        if ($this->failRead) {
            return null;
        }
        if (str_starts_with($query, 'SELECT @@autocommit')) {
            return ['enabled' => $this->autocommit, 'active' => $this->active];
        }
        [$sql, $args] = $this->prepared[$query];
        $this->sql[] = $sql;
        return isset($this->rows[$args[0]]) ? ['expected_digest' => $this->rows[$args[0]]['expected_digest']] : null;
    }

    public function query(string $query)
    {
        $query = str_starts_with($query, 'SET STATEMENT max_statement_time=0.015 FOR ') ? substr($query, strlen('SET STATEMENT max_statement_time=0.015 FOR ')) : $query;
        [$sql, $args] = $this->prepared[$query] ?? [$query, []];
        $this->insert_id = 999;
        $this->rows_affected = 1;
        $this->last_query = $sql;
        $this->sql[] = $sql;
        if ($this->failWrite) {
            throw new RuntimeException('SYNTHETIC_PRIVATE_DATABASE_ERROR');
        }
        if (str_starts_with($sql, 'CREATE TABLE')) {
            return 0;
        }
        if (str_starts_with($sql, 'INSERT INTO')) {
            [$key, $owner, $lane, $dedupe, $digest, $meta, $points, $type, $source, $sourceType, $kind, $subject, $finalDedupe, $attempt] = $args;
            if (!isset($this->rows[$key])) {
                $this->rows[$key] = ['observation_id' => count($this->rows) + 1, 'event_key' => $key, 'owner_ref' => $owner, 'lane' => $lane, 'dedupe_hash' => $dedupe,
                    'expected_digest' => $digest, 'metadata_hash' => $meta, 'points' => $points, 'point_type' => $type,
                    'source_ref_hash' => $source, 'source_type' => $sourceType, 'latest_attempt_ref' => $attempt,
                    'private_subject_kind' => $kind, 'private_subject_id' => $subject, 'private_dedupe_key' => $finalDedupe,
                    'state' => 'candidate', 'eligibility_seen' => 0, 'candidate_attempts' => 1, 'content_conflicts' => 0,
                    'transaction_ref' => null, 'private_transaction_id' => null, 'result_code' => null];
            } else {
                $row = &$this->rows[$key];
                $row['candidate_attempts']++;
                if ($row['expected_digest'] !== $digest) {
                    $row['content_conflicts']++;
                } elseif ($row['state'] !== 'fulfilled') {
                    $row['latest_attempt_ref'] = $attempt;
                    $row['state'] = 'candidate';
                }
            }
            return 1;
        }
        if (str_starts_with($sql, 'UPDATE')) {
            [$eligible, $attempt, $allow, $code, $_attempt2, $_allow2, $transaction, $_attempt3, $_allow3,
                $_attempt5, $_allow5, $transactionId,
                $_attempt4, $_allow4, $state, $key, $digest] = $args;
            if (!isset($this->rows[$key])) {
                return 0;
            }
            $row = &$this->rows[$key];
            if ($row['expected_digest'] !== $digest) {
                return 0;
            }
            $row['eligibility_seen'] = max($row['eligibility_seen'], $eligible);
            if ($row['state'] !== 'fulfilled' && ($row['latest_attempt_ref'] === $attempt || $allow === 'fulfilled')) {
                $row['state'] = $state;
                $row['result_code'] = $code;
                $row['transaction_ref'] = $transaction !== '' ? $transaction : null;
                $row['private_transaction_id'] = $transactionId > 0 ? $transactionId : null;
            }
            return 1;
        }
        throw new RuntimeException('unexpected_test_sql');
    }
}

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}
function expected(array $patch = []): array
{
    return array_replace(['lane' => 'point-events.login_daily', 'subject_kind' => 'pseudo', 'subject_id' => 'PRIVATE_SUBJECT_123',
        'dedupe_key' => 'PRIVATE_DEDUPE_456', 'points' => 2, 'type' => 'login_daily', 'source_id' => 'PRIVATE_SOURCE_789',
        'source_type' => 'session', 'metadata' => ['source_type' => 'session', 'private_comment' => 'PRIVATE_COMMENT_ABC']], $patch);
}
function fresh(): ObservationDbStub
{
    global $wpdb;
    return $wpdb = new ObservationDbStub();
}

$wpdb = fresh();
require __DIR__ . '/../wp-content/mu-plugins/sharity-reward-observation.php';
check($wpdb->sql === [], 'loading module never queries or installs');
check(Sharity_Reward_Observation::before(expected()) === null, 'default-off before');
check(Sharity_Reward_Observation::beforeAward('api.pseudo_earn', 'pseudo', 'ABCDEFGHIJ', 3, 'bonus', null, [], null) === null && $wpdb->sql === [], 'default-off argument adapter does no database work');
check(Sharity_Reward_Observation::after(null, ['success' => true]) === false, 'default-off after');
check(Sharity_Reward_Observation::migrate() === false && $wpdb->sql === [], 'migration requires explicit separate gate');
define('SHARITY_REWARD_OBSERVATION_ENABLED', true);

foreach ([
    [null, ['date' => '2026-11-12'], 'bonus:source:2026-11-12'],
    [null, ['dedupe_key' => 'private:existing'], 'private:existing'],
    ['explicit:key', ['dedupe_key' => 'ignored'], 'explicit:key'],
] as [$dedupe, $metadata, $finalDedupe]) {
    $db = fresh();
    $token = Sharity_Reward_Observation::beforeAward('api.pseudo_earn', 'pseudo', 'ABCDEFGHIJ', 3, 'bonus', 'source', $metadata, $dedupe);
    check(is_array($token) && $db->rows[$token['event_key']]['private_dedupe_key'] === $finalDedupe &&
        $db->rows[$token['event_key']]['private_subject_id'] === 'abcdefghij', 'inspected manager null-dedupe and canonical subject contract');
}
$db = fresh();
check(Sharity_Reward_Observation::beforeAward('api.pseudo_earn', 'pseudo', 'abcdefghij', 3, 'bonus', 'source', [], '') === null && $db->rows === [], 'empty explicit dedupe is not silently replaced');
foreach ([['dedupe_key' => ['invalid']], ['date' => ['invalid']]] as $badMetadata) {
    $db = fresh(); $warnings = 0;
    set_error_handler(static function () use (&$warnings): bool { $warnings++; return true; });
    try {
        check(Sharity_Reward_Observation::beforeAward('api.pseudo_earn', 'pseudo', 'abcdefghij', 3, 'bonus', null, $badMetadata, null) === null && $db->rows === [], 'unrepresentable fallback is unknown');
    } finally { restore_error_handler(); }
    check($warnings === 0, 'observer never adds array-cast warnings before the existing earning gate');
}
$wpdb = fresh();

$metadata = ['source_type' => 'session', 'ratio' => 1.0, 'fraction' => 1.25];
$floatToken = Sharity_Reward_Observation::beforeAward('api.signed_earn', 'user', 17, 3, 'bonus', null, $metadata, 'fixture:float');
$storedToken = Sharity_Reward_Observation::before(['lane'=>'api.signed_earn','subject_kind'=>'user','subject_id'=>17,
    'points'=>3,'type'=>'bonus','source_id'=>null,'source_type'=>'session','dedupe_key'=>'fixture:float',
    'metadata'=>['source_type'=>'session','ratio'=>1,'fraction'=>1.25]]);
check($floatToken['expected_digest'] === $storedToken['expected_digest'] && $metadata['ratio'] === 1.0,
    'default manager JSON storage semantics normalized only in observation copy');
$wpdb = fresh();

$input = expected();
$original = $input;
$token = Sharity_Reward_Observation::before($input);
check(is_array($token) && $input === $original, 'candidate created without altering business arguments');
$row = $wpdb->rows[$token['event_key']];
check($row['state'] === 'candidate' && $row['eligibility_seen'] === 0, 'candidate is not eligible');
check($wpdb->insert_id === 812 && $wpdb->last_error === 'existing-caller-error' && $wpdb->rows_affected === 4 &&
    $wpdb->num_rows === 3 && $wpdb->last_query === 'existing-caller-query' && $wpdb->last_result === [['existing' => true]], 'caller wpdb result context preserved');
$persisted = json_encode([$wpdb->rows, $wpdb->prepared], JSON_THROW_ON_ERROR);
foreach (['PRIVATE_SOURCE', 'PRIVATE_COMMENT'] as $private) {
    check(!str_contains($persisted, $private), 'no raw source ID, metadata or comment stored or sent to SQL');
}
check($row['private_subject_kind'] === 'pseudo' && $row['private_subject_id'] === $input['subject_id'] &&
    $row['private_dedupe_key'] === $input['dedupe_key'], 'only authorized private selectors support indexed canonical owner+dedupe lookup');
check(!str_contains(json_encode($token, JSON_THROW_ON_ERROR), 'PRIVATE_'), 'producer API token never returns private selectors');
// Reader contract: export an explicit hash/state allowlist, never SELECT * rows.
$publicProjection = array_intersect_key($row, array_flip(['event_key', 'owner_ref', 'lane', 'dedupe_hash', 'expected_digest', 'metadata_hash', 'state']));
check(!str_contains(json_encode($publicProjection, JSON_THROW_ON_ERROR), 'PRIVATE_') &&
    !array_filter(array_keys($publicProjection), static fn(string $key): bool => str_starts_with($key, 'private_')), 'public snapshot projection excludes every private selector');
$reordered = expected(['metadata' => ['private_comment' => 'PRIVATE_COMMENT_ABC', 'source_type' => 'session']]);
$same = Sharity_Reward_Observation::before($reordered);
check($same['event_key'] === $token['event_key'] && $same['expected_digest'] === $token['expected_digest'], 'map metadata order canonicalized');
$different = Sharity_Reward_Observation::before(expected(['points' => 3]));
check($different === null && $wpdb->rows[$token['event_key']]['points'] === 2, 'immutable expected-content conflict');
check($wpdb->rows[$token['event_key']]['content_conflicts'] === 1, 'conflict remains durable observation');
check(Sharity_Reward_Observation::before(expected(['lane' => 'point-events.other']))['event_key'] !== $token['event_key'], 'lane is part of identity');
check(Sharity_Reward_Observation::before(expected(['subject_kind' => 'user']))['event_key'] !== $token['event_key'], 'subject namespace is part of identity');

$db = fresh(); $token = Sharity_Reward_Observation::before(expected());
check(Sharity_Reward_Observation::after($token, ['success' => false, 'error' => 'earning_blocked']), 'blocked result recorded');
check($db->rows[$token['event_key']]['state'] === 'excluded' && $db->rows[$token['event_key']]['eligibility_seen'] === 0, 'only observed gate block excludes');
foreach (['transaction_begin_failed', 'balance_write_failed', 'balance_read_failed', 'ledger_read_failed', 'ledger_write_failed', 'level_write_failed', 'dedupe_conflict'] as $error) {
    $db = fresh(); $token = Sharity_Reward_Observation::before(expected());
    check(Sharity_Reward_Observation::after($token, ['success' => false, 'error' => $error]), 'recognized post-gate result');
    check($db->rows[$token['event_key']]['state'] === 'eligible_failed' && $db->rows[$token['event_key']]['eligibility_seen'] === 1, 'known failure proves gate passed');
}
$retry = Sharity_Reward_Observation::before(expected());
Sharity_Reward_Observation::after($retry, ['success' => false, 'error' => 'earning_blocked']);
check($db->rows[$retry['event_key']]['eligibility_seen'] === 1, 'later block cannot erase earlier eligibility');

foreach ([null, ['success' => true], ['success' => false, 'error' => 'invalid_input'],
    ['success' => false, 'error' => 'commit_unconfirmed'], ['success' => false, 'error' => 'PRIVATE_COMMENT_ABC']] as $result) {
    $db = fresh(); $token = Sharity_Reward_Observation::before(expected());
    Sharity_Reward_Observation::after($token, $result);
    check($db->rows[$token['event_key']]['state'] === 'unknown', 'lost/unrecognized/pre-gate/uncertain result stays unknown');
    check(!str_contains(json_encode($db->rows), 'PRIVATE_COMMENT'), 'raw result error not stored');
}

$db = fresh(); $first = Sharity_Reward_Observation::before(expected()); $second = Sharity_Reward_Observation::before(expected());
Sharity_Reward_Observation::after($first, ['success' => false, 'error' => 'balance_write_failed']);
check($db->rows[$first['event_key']]['state'] === 'candidate', 'stale negative cannot overwrite newer attempt');
check($db->rows[$first['event_key']]['eligibility_seen'] === 1, 'stale recognized failure still preserves proven eligibility');
Sharity_Reward_Observation::after($first, ['success' => true, 'transaction_id' => 42, 'duplicate' => true]);
$fulfilled = $db->rows[$first['event_key']];
check($fulfilled['state'] === 'fulfilled' && $fulfilled['eligibility_seen'] === 1, 'checked success from any matching attempt fulfills');
check($fulfilled['private_transaction_id'] === 42, 'only checked positive manager transaction ID retained privately');
Sharity_Reward_Observation::after($second, ['success' => false, 'error' => 'commit_unconfirmed']);
$third = Sharity_Reward_Observation::before(expected());
Sharity_Reward_Observation::after($third, ['success' => false, 'error' => 'earning_blocked']);
check($db->rows[$first['event_key']]['state'] === 'fulfilled' && $db->rows[$first['event_key']]['transaction_ref'] === $fulfilled['transaction_ref'] &&
    $db->rows[$first['event_key']]['private_transaction_id'] === 42, 'fulfilled and private target reference cannot regress');
foreach ([0, -1, '42', null] as $invalidId) {
    $db = fresh(); $candidate = Sharity_Reward_Observation::before(expected());
    Sharity_Reward_Observation::after($candidate, ['success' => true, 'transaction_id' => $invalidId]);
    check($db->rows[$candidate['event_key']]['private_transaction_id'] === null &&
        $db->rows[$candidate['event_key']]['state'] === 'unknown', 'invalid/untyped transaction ID never stored as a successful target');
}

$db = fresh(); $db->failWrite = true;
$token = Sharity_Reward_Observation::before(expected());
$businessCalls = 0;
$businessResult = (static function () use (&$businessCalls) { $businessCalls++; return ['success' => true, 'transaction_id' => 77]; })();
check($token === null && $businessCalls === 1 && Sharity_Reward_Observation::after($token, $businessResult) === false, 'observation failure never blocks or replays successful earning');
check($businessResult === ['success' => true, 'transaction_id' => 77] && $db->suppressed === false, 'business result and error mode preserved');
$db = fresh(); $token = Sharity_Reward_Observation::before(expected()); $db->failWrite = true;
check(Sharity_Reward_Observation::after($token, $businessResult) === false, 'result write failure is unconfirmed');
$db->failWrite = false;
check($db->rows[$token['event_key']]['state'] === 'candidate', 'lost result leaves durable candidate for unknown monitoring');

$db = fresh(); $db->active = '1';
check(Sharity_Reward_Observation::before(expected()) === null && $db->sql === [], 'active caller transaction refused without writes');
$db->active = '0'; $db->autocommit = '0';
check(Sharity_Reward_Observation::before(expected()) === null && $db->sql === [], 'autocommit-off caller refused');
foreach ([null, 'invalid', expected(['metadata' => ['bad' => new stdClass()]]), expected(['metadata' => ['huge' => str_repeat('x', 16385)]]) ] as $bad) {
    $db = fresh(); check(Sharity_Reward_Observation::before($bad) === null && $db->rows === [], 'bad observation input fail-open');
}
check(Sharity_Reward_Observation::after('bad-token', $businessResult) === false, 'invalid result token fail-open');

$db = fresh(); define('SHARITY_REWARD_OBSERVATION_MIGRATE', true);
check(Sharity_Reward_Observation::migrate(), 'explicit CLI migration prepares additive table');
check(count($db->sql) === 1 && str_contains($db->sql[0], 'ENGINE=InnoDB') && str_contains($db->sql[0], 'KEY updated (updated_at_utc,event_key)') &&
    str_contains($db->sql[0], 'PRIMARY KEY (observation_id), UNIQUE KEY event_identity (event_key)'), 'schema defines indexed insertion watermark, immutable event identity and result-update cursor');
$db->active = '1'; $db->sql = [];
check(!Sharity_Reward_Observation::migrate() && $db->sql === [], 'DDL cannot implicitly commit existing work');

$source = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/sharity-reward-observation.php');
check(!preg_match('/add_(?:action|filter)\s*\(/', $source), 'new module has no producer/runtime hook');
check(!preg_match('/[\x27\x22](?:START TRANSACTION|BEGIN|COMMIT|ROLLBACK)[\x27\x22]/', $source), 'recorder never owns business transaction');
check(str_contains($source, "state=IF(state='fulfilled' OR (latest_attempt_ref<>%s AND %s<>'fulfilled'),state,%s)"), 'actual SQL encodes sticky success and concurrent attempt guard');

// Each configuration is isolated: constants are immutable and never request input.
$directory = realpath(sys_get_temp_dir()) . '/reward-observation-log-' . bin2hex(random_bytes(8));
check(mkdir($directory, 0700), 'private log fixture');
$existing = $directory . '/existing.log'; $native = $directory . '/native.log';
file_put_contents($existing, "existing-prefix\n"); chmod($existing, 0600);
file_put_contents($native, ''); chmod($native, 0600);
$link = $directory . '/linked.log'; symlink($existing, $link);
try {
    foreach (['existing' => $existing, 'missing' => $directory . '/missing.log', 'symlink' => $link,
        'relative' => 'relative.log', 'directory' => $directory, 'unset' => null] as $mode => $destination) {
        file_put_contents($existing, "existing-prefix\n"); file_put_contents($native, '');
        $program = 'define("ABSPATH", "/fixture/"); define("SHARITY_REWARD_OBSERVATION_ENABLED", true);' .
            ($destination !== null ? 'define("SHARITY_REWARD_OBSERVATION_ERROR_LOG", ' . var_export($destination, true) . ');' : '') .
            'ini_set("error_log", ' . var_export($native, true) . ');' .
            'require ' . var_export(__DIR__ . '/../wp-content/mu-plugins/sharity-reward-observation.php', true) . ';' .
            '$wpdb=null; $token=Sharity_Reward_Observation::before([]); $businessCalls=0;' .
            '$business=(static function() use (&$businessCalls) {$businessCalls++; return ["success"=>true,"transaction_id"=>77];})();' .
            'echo json_encode([$token,$businessCalls,$business]);';
        $process = proc_open([PHP_BINARY, '-r', $program], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        check(is_resource($process), 'log fixture process'); fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0 && $stderr === '', 'log failure stays nonthrowing and silent to response');
        check(json_decode($output,true,512,JSON_THROW_ON_ERROR) === [null,1,['success'=>true,'transaction_id'=>77]], 'log destination never changes or repeats successful earning');
        $explicitBytes = file_get_contents($existing); $nativeBytes = file_get_contents($native);
        if ($mode === 'existing') {
            check($explicitBytes === "existing-prefix\nsharity_reward_observation_incomplete code=recording_unconfirmed\n" && $nativeBytes === '', 'configured existing owner log gets exact safe marker');
        } else {
            check($explicitBytes === "existing-prefix\n", 'unconfirmed destination never appends through symlink or to another target');
            check(str_contains($nativeBytes, $mode === 'unset' ? 'code=recording_unconfirmed' : 'code=error_destination_unconfirmed'), 'native fallback identifies explicit destination uncertainty');
        }
        check(!file_exists($directory . '/missing.log'), 'missing log is never intentionally created');
    }
} finally {
    unlink($link); unlink($existing); unlink($native); rmdir($directory);
}
echo "PASS: reward observation protocol/SQL-stub checks; real MariaDB evidence is a separate integration test\n";
