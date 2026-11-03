<?php
declare(strict_types=1);
// Explicit disposable loopback DB only. No provider/WordPress/production access.
$port = getenv('SHARITY_FINANCE_TEST_PORT');
if (!is_string($port) || !preg_match('/^[1-9][0-9]{3,4}$/D', $port) || (int)$port > 65535 || !function_exists('pcntl_fork')) {
    fwrite(STDERR, "Explicit isolated DB port and pcntl required\n"); exit(2);
}
require __DIR__ . '/../wp-content/mu-plugins/sharity-support-finance.php';
use function sharity_support_finance\{apply_schema, claim, credit_identity, lookup, lookup_credit, register_credit, schema_sql, verify_schema};
use sharity_support_finance\FinanceViolation;
$checks = 0; $prefix = 'p4d_' . bin2hex(random_bytes(4)) . '_'; $allPrefixes = [$prefix];
function check(bool $ok, string $message): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function fails(callable $call, string $code): void {
    try { $call(); } catch (FinanceViolation $e) {
        check($e->getMessage() === $code, 'expected ' . $code . ', got ' . $e->getMessage());
        check($e->getPrevious() === null, 'no raw error chain'); return;
    }
    throw new RuntimeException('Expected failure ' . $code);
}
function conn(string $class = mysqli::class): mysqli {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new $class('127.0.0.1', 'root', 'isolated-donation-test-only', 'donation_test', (int)$GLOBALS['port']);
    $db->set_charset('utf8mb4'); $db->query('SET SESSION innodb_lock_wait_timeout=3'); return $db;
}
function credit(string $id, string $amount = '1000'): array {
    return ['provider' => 'wise', 'receiving_account_ref' => 'synthetic-account', 'provider_credit_id' => $id,
        'currency' => 'HUF', 'net_minor' => $amount, 'evidence_ref' => 'synthetic-statement:' . $id, 'evidence_sha256' => str_repeat('a', 64)];
}
function op(string $id, string $amount, string $source = 'affiliate'): array {
    return ['operation_id' => $id, 'source' => $source, 'economic_root_ref' => $source . ':synthetic-root',
        'amount_minor' => $amount, 'payload_digest' => str_repeat('b', 64)];
}
function tx(mysqli $db, callable $run) {
    $db->begin_transaction();
    try { $out = $run(); $db->commit(); return $out; } catch (Throwable $e) { $db->rollback(); throw $e; }
}
function register(mysqli $db, array $c): void { tx($db, static fn() => register_credit($db, $GLOBALS['prefix'], $c)); }
function consume(mysqli $db, array $c, array $o): array { return tx($db, static fn() => claim($db, $GLOBALS['prefix'], $c, $o)); }
/** Children connect independently after inherited parent handles are closed. */
function race(callable $run): array {
    global $db;
    $db->close(); $dir = sys_get_temp_dir() . '/p4d-race-' . bin2hex(random_bytes(8)); mkdir($dir, 0700); $pids = [];
    try {
        foreach ([0, 1] as $slot) {
            $pid = pcntl_fork(); if ($pid < 0) { throw new RuntimeException('fork failed'); }
            if ($pid === 0) {
                $child = conn(); file_put_contents($dir . '/ready-' . $slot, 'ready');
                $deadline = microtime(true) + 8;
                while (!is_file($dir . '/ready-' . (1 - $slot))) { if (microtime(true) > $deadline) { exit(3); } usleep(1000); }
                try { $out = ['value' => $run($child, $slot)]; }
                catch (Throwable $e) { $out = ['error' => $e->getMessage()]; }
                file_put_contents($dir . '/result-' . $slot, json_encode($out)); $child->close(); exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) { pcntl_waitpid($pid, $status); check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'race child successful'); }
        return array_map(static fn($slot) => json_decode(file_get_contents($dir . '/result-' . $slot), true, 32, JSON_THROW_ON_ERROR), [0, 1]);
    } finally {
        foreach (glob($dir . '/*') as $file) { unlink($file); } rmdir($dir); $db = conn();
    }
}
final class LostCommitConnection extends mysqli {
    public function commit(int $flags = 0, ?string $name = null): bool { parent::commit($flags, $name); throw new RuntimeException('synthetic lost commit ACK'); }
}
final class MetadataProbeConnection extends mysqli {
    public $beforeColumns = null;
    public function prepare(string $query): mysqli_stmt|false {
        if ($this->beforeColumns !== null && str_starts_with($query, 'SHOW FULL COLUMNS')) {
            $probe = $this->beforeColumns; $this->beforeColumns = null; $probe();
        }
        return parent::prepare($query);
    }
}
/** Separate process: no inherited parent DB handle or timing-based race guess. */
function attempt_engine_change(string $table): string {
    $code = <<<'PHP'
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$argv[1]);
$db->query('SET SESSION lock_wait_timeout=1');
try { $db->query('ALTER TABLE `' . $argv[2] . '` ENGINE=MyISAM'); echo 'altered'; }
catch (mysqli_sql_exception $e) { echo $e->getCode() === 1205 ? 'blocked' : 'error:' . $e->getCode(); }
$db->close();
PHP;
    $process = proc_open([PHP_BINARY,'-r',$code,$GLOBALS['port'],$table], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('DDL probe process failed'); }
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); check(proc_close($process) === 0 && $err === '', 'DDL probe process clean'); return $out;
}
$db = conn();
try {
    [$creditTable, $operationTable] = array_keys(schema_sql($prefix));
    check(apply_schema($db, $prefix)['applied'] === false, 'default is report only');
    check($db->query("SHOW TABLES LIKE '{$prefix}%'")->num_rows === 0, 'report/include performs no DDL');
    fails(static fn() => verify_schema($db, $prefix), 'FINANCE_SCHEMA_MISSING');
    check(apply_schema($db, $prefix, true) === ['applied' => true], 'explicit apply');
    check(apply_schema($db, $prefix, true) === ['applied' => true], 'compatible repeat');
    $db->query("CREATE TABLE `{$prefix}consumer` (id varchar(160) PRIMARY KEY, amount bigint NOT NULL) ENGINE=InnoDB");
    $db->begin_transaction(); $db->query("INSERT INTO `{$prefix}consumer` VALUES ('no-ddl-commit',1)");
    fails(static fn() => apply_schema($db, $prefix, true), 'FINANCE_SCHEMA_ACTIVE_TRANSACTION');
    check((int)$db->query('SELECT @@in_transaction')->fetch_row()[0] === 1, 'DDL refusal preserves caller transaction');
    $db->rollback();
    check($db->query("SELECT * FROM `{$prefix}consumer`")->num_rows === 0, 'DDL did not implicitly commit caller write');
    fails(static fn() => register_credit($db, $prefix, credit('C1')), 'FINANCE_TRANSACTION_REQUIRED');
    fails(static fn() => claim($db, $prefix, credit('C1'), op('missing-tx', '1')), 'FINANCE_TRANSACTION_REQUIRED');
    $c = credit('C1'); $key = credit_identity($c)['credit_key'];
    $first = tx($db, static fn() => register_credit($db, $prefix, $c));
    check($first['status'] === 'pending_commit' && $first['disposition'] === 'registered', 'helper never claims commit');
    $second = tx($db, static fn() => register_credit($db, $prefix, array_reverse($c, true)));
    check($second['credit'] === $first['credit'] && $second['disposition'] === 'existing', 'canonical duplicate preserves original');
    foreach (['net_minor' => '1001', 'currency' => 'EUR', 'evidence_sha256' => str_repeat('d', 64)] as $k => $v) {
        $changed = array_replace($c, [$k => $v]);
        fails(static fn() => tx($db, static fn() => register_credit($db, $prefix, $changed)), 'FINANCE_CREDIT_CONFLICT');
    }
    check(credit_identity(credit('c1'))['credit_key'] !== $key, 'case-sensitive bank ID');
    check(credit_identity(array_replace($c, ['receiving_account_ref' => 'other']))['credit_key'] !== $key, 'receiving account part of identity');
    $a = consume($db, $c, op('A1', '600')); $b = consume($db, $c, op('B1', '300', 'offerwall'));
    check($b['credit']['claimed_minor'] === '900' && $b['credit']['remaining_minor'] === '100', 'shared capacity across sources');
    check(consume($db, $c, op('A1', '600'))['operation'] === $a['operation'], 'duplicate original operation/time preserved');
    check(lookup($db, $prefix, 'A1')['credit']['claimed_minor'] === '900', 'duplicate does not consume twice');
    fails(static fn() => consume($db, $c, op('B2', '101', 'offerwall')), 'FINANCE_CAPACITY');
    fails(static fn() => consume($db, $c, op('A1', '601')), 'FINANCE_OPERATION_CONFLICT');
    fails(static fn() => consume($db, $c, op('A1', '600', 'offerwall')), 'FINANCE_OPERATION_CONFLICT');
    fails(static fn() => consume($db, array_replace($c, ['currency' => 'EUR']), op('wrong-currency', '1')), 'FINANCE_CREDIT_CONFLICT');
    // A deliberately failing consumer INSERT rolls its claim back on the same connection.
    $db->query("INSERT INTO `{$prefix}consumer` VALUES ('duplicate',1)");
    try { tx($db, static function () use ($db, $prefix, $c): void {
        claim($db, $prefix, $c, op('atomic-failure', '100'));
        $db->query("INSERT INTO `{$prefix}consumer` VALUES ('duplicate',100)");
    }); throw new RuntimeException('expected consumer SQL error'); } catch (mysqli_sql_exception $e) {}
    check(lookup($db, $prefix, 'atomic-failure')['status'] === 'absent', 'consumer failure rolls claim back');
    check(lookup_credit($db, $prefix, $key)['credit']['claimed_minor'] === '900', 'consumer failure restores common counter');
    tx($db, static function () use ($db, $prefix, $c): void {
        claim($db, $prefix, $c, op('atomic-success', '100'));
        $db->query("INSERT INTO `{$prefix}consumer` VALUES ('paired',100)");
    });
    check(lookup($db, $prefix, 'atomic-success')['credit']['remaining_minor'] === '0', 'paired commit consumes once');
    // Refund is a consumer journal correction; historical credit is never freed.
    $db->query("INSERT INTO `{$prefix}consumer` VALUES ('refund',-100)");
    fails(static fn() => consume($db, $c, op('after-refund', '1')), 'FINANCE_CAPACITY');
    foreach (['-1','0','01','1.0','1e3','9223372036854775808',1,1.0,null,true] as $bad) {
        fails(static fn() => credit_identity(array_replace($c, ['net_minor' => $bad])), 'FINANCE_AMOUNT');
    }
    foreach (['', 'bad value', 'id\n', str_repeat('x',191)] as $bad) {
        fails(static fn() => credit_identity(array_replace($c, ['provider_credit_id' => $bad])), 'FINANCE_IDENTIFIER');
    }
    fails(static fn() => schema_sql('bad-'), 'FINANCE_PREFIX');
    fails(static fn() => schema_sql(str_repeat('p',28)), 'FINANCE_PREFIX');
    $maximum = credit('MAX', '9223372036854775807'); register($db, $maximum);
    consume($db, $maximum, op('max-1', '9223372036854775806'));
    check(consume($db, $maximum, op('max-2', '1'))['credit']['remaining_minor'] === '0', 'signed64 exact cap without float overflow');
    fails(static fn() => consume($db, $maximum, op('max-3', '1')), 'FINANCE_CAPACITY');
    // Real COMMIT succeeds but caller observes an exception. Fresh read, no resend.
    $lost = credit('ACK', '300'); register($db, $lost); $unknown = conn(LostCommitConnection::class);
    $unknown->begin_transaction(); claim($unknown, $prefix, $lost, op('lost-ack', '200'));
    try { $unknown->commit(); throw new RuntimeException('expected lost ACK'); } catch (RuntimeException $e) { check($e->getMessage() === 'synthetic lost commit ACK', 'uncertain COMMIT fault'); }
    $unknown->close(); $fresh = conn(); $back = lookup($fresh, $prefix, 'lost-ack'); $fresh->close();
    check($back['status'] === 'found' && $back['operation']['amount_minor'] === '200'
        && $back['credit']['remaining_minor'] === '100' && $back['credit']['evidence_sha256'] === $lost['evidence_sha256'], 'full committed readback includes evidence');
    // Real uncommitted connection loss rolls back both common claim and consumer.
    $killed = conn(); $killed->begin_transaction(); claim($killed, $prefix, $lost, op('disconnect', '100'));
    $killed->query("INSERT INTO `{$prefix}consumer` VALUES ('disconnected',100)"); $killed->close();
    check(lookup($db, $prefix, 'disconnect')['status'] === 'absent', 'disconnect did not persist claim');
    check($db->query("SELECT * FROM `{$prefix}consumer` WHERE id='disconnected'")->num_rows === 0, 'disconnect did not persist consumer');
    // Current locking reads must ignore an older repeatable-read snapshot.
    $stale = credit('STALE', '20'); register($db, $stale); $reader = conn();
    $reader->query('START TRANSACTION WITH CONSISTENT SNAPSHOT'); lookup_credit($reader, $prefix, credit_identity($stale)['credit_key']);
    consume($db, $stale, op('stale-first', '10'));
    check(claim($reader, $prefix, $stale, op('stale-second', '10'))['credit']['claimed_minor'] === '20', 'write reads current locked capacity');
    $reader->commit(); $reader->close();
    $raceCredit = credit('RACE', '100'); register($db, $raceCredit);
    $results = race(static fn(mysqli $child, int $slot) => consume($child, $raceCredit, op('race-' . $slot, '70', $slot ? 'offerwall' : 'affiliate')));
    check(count(array_filter($results, static fn($r) => isset($r['value']))) === 1, 'one cross-source race winner');
    check(in_array('FINANCE_CAPACITY', array_column($results, 'error'), true), 'loser cannot exceed cap');
    check(lookup_credit($db, $prefix, credit_identity($raceCredit)['credit_key'])['credit']['claimed_minor'] === '70', 'race consumed once');
    $dupCredit = credit('DUP', '100'); register($db, $dupCredit);
    $results = race(static fn(mysqli $child, int $slot) => consume($child, $dupCredit, op('same-operation', '70')));
    check(count(array_filter($results, static fn($r) => isset($r['value']))) === 2, 'same-credit concurrent duplicate resolves');
    check(lookup($db, $prefix, 'same-operation')['credit']['version'] === '1', 'concurrent duplicate advances once');
    $x = credit('X', '100'); $y = credit('Y', '100'); register($db,$x); register($db,$y);
    $results = race(static fn(mysqli $child, int $slot) => consume($child, $slot ? $x : $y, op('global-operation', '70')));
    check(count(array_filter($results, static fn($r) => isset($r['value']))) === 1, 'global operation cannot claim two credits');
    check(count(array_filter($results, static fn($r) => in_array($r['error'] ?? '', ['FINANCE_OPERATION_CONFLICT','FINANCE_STORAGE'], true))) === 1, 'conflict/deadlock is explicit, never retry');
    check((int)lookup_credit($db,$prefix,credit_identity($x)['credit_key'])['credit']['claimed_minor']
        + (int)lookup_credit($db,$prefix,credit_identity($y)['credit_key'])['credit']['claimed_minor'] === 70, 'loser capacity rolled back');
    // Deterministically intervene after engine read, before first SHOW/row lock.
    // Both table MDLs must already be retained by the caller transaction.
    $probe = conn(MetadataProbeConnection::class);
    $probe->beforeColumns = static function () use ($creditTable,$operationTable): void {
        check(attempt_engine_change($creditTable) === 'blocked', 'registry ALTER blocked before schema inspection completes');
        check(attempt_engine_change($operationTable) === 'blocked', 'operation ALTER blocked before schema inspection completes');
    };
    $probe->begin_transaction(); register_credit($probe,$prefix,credit('DDL-PROBE')); $probe->rollback(); $probe->close();
    check(lookup_credit($db,$prefix,credit_identity(credit('DDL-PROBE'))['credit_key'])['status'] === 'absent', 'DDL probe registration still rolls back');
    // Altered originals, counters and operation digests are not accepted as success.
    foreach (["UPDATE `{$creditTable}` SET identity_digest='" . str_repeat('0',64) . "' WHERE credit_key='{$key}'",
        "UPDATE `{$creditTable}` SET claimed_minor=999 WHERE credit_key='{$key}'",
        "UPDATE `{$creditTable}` SET version=999 WHERE credit_key='{$key}'",
        "UPDATE `{$operationTable}` SET operation_digest='" . str_repeat('0',64) . "' WHERE operation_id='A1'"] as $corrupt) {
        $db->begin_transaction(); $db->query($corrupt);
        fails(static fn() => lookup($db,$prefix,'A1'), 'FINANCE_CORRUPT'); $db->rollback();
    }
    foreach (['engine','column','index','collation'] as $kind) {
        $drift = $prefix . $kind . '_'; $allPrefixes[] = $drift; apply_schema($db,$drift,true); [$table] = array_keys(schema_sql($drift));
        $sql = match ($kind) {
            'engine' => "ALTER TABLE `{$table}` ENGINE=MyISAM",
            'column' => "ALTER TABLE `{$table}` MODIFY net_minor double NOT NULL",
            'index' => "ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY(provider_credit_id)",
            'collation' => "ALTER TABLE `{$table}` MODIFY provider_credit_id varchar(190) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL",
        };
        $db->query($sql); $code = match ($kind) {'engine'=>'FINANCE_SCHEMA_ENGINE','index'=>'FINANCE_SCHEMA_INDEX',default=>'FINANCE_SCHEMA_COLUMNS'};
        fails(static fn() => verify_schema($db,$drift), $code);
        fails(static fn() => apply_schema($db,$drift,true), $code);
        fails(static fn() => tx($db, static fn() => register_credit($db,$drift,credit('bad-schema'))), $code);
    }
    fwrite(STDOUT, "P4d: {$checks} checks passed; real isolated MariaDB, no live import/financial acceptance.\n");
} finally {
    try { $db->rollback(); } catch (Throwable $e) {}
    foreach ($allPrefixes as $p) { foreach (array_keys(schema_sql($p)) as $table) { $db->query("DROP TABLE IF EXISTS `{$table}`"); } }
    $db->query("DROP TABLE IF EXISTS `{$prefix}consumer`"); $db->close();
}
