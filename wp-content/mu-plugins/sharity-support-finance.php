<?php
/**
 * Internal, manually evidenced bank-credit claims shared by affiliate/Offerwall.
 * Inclusion performs no I/O. No HTTP/cron, provider, points, votes or payout API.
 * Evidence pointers do not authenticate statements; the importer verifies them.
 *
 * register_credit()/claim() require the caller's pinned mysqli transaction. They
 * NEVER begin/commit/rollback/reconnect. Any exception requires caller rollback;
 * success is pending commit, atomic with the caller's journal/allocation.
 * Lock sorted credit keys BEFORE consumer root/settlement/allocation locks.
 * No external call under lock. Unknown commit: fresh connection + full lookup,
 * then reconcile the consumer journal too. An absent row is not a retry permit.
 * Historical claimed capacity cannot be released by refund, payout or reversal.
 * Existing V1 imports are NOT protected until explicitly integrated/migrated.
 */
declare(strict_types=1);
namespace sharity_support_finance;

const MAX_MINOR = '9223372036854775807';
final class FinanceViolation extends \DomainException {}

function exact_keys(array $input, array $keys): void
{
    if (count($input) !== count($keys) || array_diff($keys, array_keys($input))) {
        throw new FinanceViolation('FINANCE_KEYS');
    }
}

function token($value, int $limit = 190): string
{
    if (!is_string($value) || strlen($value) > $limit || !preg_match('/^[A-Za-z0-9._:-]+$/D', $value)) {
        throw new FinanceViolation('FINANCE_IDENTIFIER');
    }
    return $value;
}

function sha($value): string
{
    if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) { throw new FinanceViolation('FINANCE_DIGEST'); }
    return $value;
}

/** Untyped to reject numeric/float coercion even from weak callers. */
function minor($value, bool $positive = true): int
{
    if (PHP_INT_SIZE !== 8 || !is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D', $value)
        || strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, MAX_MINOR) > 0)
        || ($positive && $value === '0')) { throw new FinanceViolation('FINANCE_AMOUNT'); }
    return (int)$value;
}

function digest(array $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function credit_identity(array $input): array
{
    exact_keys($input, ['provider', 'receiving_account_ref', 'provider_credit_id', 'currency', 'net_minor', 'evidence_ref', 'evidence_sha256']);
    if (!is_string($input['currency']) || !in_array($input['currency'], ['HUF', 'EUR', 'USD', 'GBP'], true)) {
        throw new FinanceViolation('FINANCE_CURRENCY');
    }
    $credit = ['provider' => token($input['provider']), 'receiving_account_ref' => token($input['receiving_account_ref']),
        'provider_credit_id' => token($input['provider_credit_id']), 'currency' => $input['currency'],
        'net_minor' => (string)minor($input['net_minor']), 'evidence_ref' => token($input['evidence_ref']), 'evidence_sha256' => sha($input['evidence_sha256'])];
    $credit['credit_key'] = digest([$credit['provider'], $credit['receiving_account_ref'], $credit['provider_credit_id']]);
    $credit['identity_digest'] = digest($credit);
    return $credit;
}

function operation_identity(array $credit, array $input): array
{
    exact_keys($input, ['operation_id', 'source', 'economic_root_ref', 'amount_minor', 'payload_digest']);
    if (!is_string($input['source']) || !in_array($input['source'], ['affiliate', 'offerwall'], true)) { throw new FinanceViolation('FINANCE_SOURCE'); }
    $operation = ['operation_id' => token($input['operation_id'], 160), 'credit_key' => $credit['credit_key'],
        'credit_identity_digest' => $credit['identity_digest'], 'source' => $input['source'], 'economic_root_ref' => token($input['economic_root_ref']),
        'amount_minor' => (string)minor($input['amount_minor']), 'payload_digest' => sha($input['payload_digest'])];
    $operation['operation_digest'] = digest($operation);
    return $operation;
}

/** Fixed definitions are also used to verify existing schema, not repair it. */
function definition(string $prefix): array
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,26}$/D', $prefix)) { throw new FinanceViolation('FINANCE_PREFIX'); }
    $ascii = ' CHARACTER SET ascii COLLATE ascii_bin NOT NULL';
    return [
        $prefix . 'sharity_support_cash_claims' => [
            'columns' => ['credit_key' => 'char(64)' . $ascii, 'provider' => 'varchar(190)' . $ascii,
                'receiving_account_ref' => 'varchar(190)' . $ascii, 'provider_credit_id' => 'varchar(190)' . $ascii,
                'currency' => 'char(3)' . $ascii, 'net_minor' => 'bigint NOT NULL', 'evidence_ref' => 'varchar(190)' . $ascii,
                'evidence_sha256' => 'char(64)' . $ascii, 'identity_digest' => 'char(64)' . $ascii,
                'claimed_minor' => 'bigint NOT NULL', 'version' => 'bigint NOT NULL', 'created_at_utc' => 'datetime NOT NULL'],
            'indexes' => ['PRIMARY' => [0, ['credit_key']]]],
        $prefix . 'sharity_support_cash_claim_operations' => [
            'columns' => ['operation_id' => 'varchar(160)' . $ascii, 'credit_key' => 'char(64)' . $ascii,
                'credit_identity_digest' => 'char(64)' . $ascii, 'source' => 'varchar(16)' . $ascii,
                'economic_root_ref' => 'varchar(190)' . $ascii, 'amount_minor' => 'bigint NOT NULL',
                'payload_digest' => 'char(64)' . $ascii, 'operation_digest' => 'char(64)' . $ascii, 'created_at_utc' => 'datetime NOT NULL'],
            'indexes' => ['PRIMARY' => [0, ['operation_id']], 'credit_key_idx' => [1, ['credit_key']]]],
    ];
}

/** Uses only the given handle; exceptions deliberately omit SQL/identity data. */
function query(\mysqli $db, string $sql, array $params = []): array
{
    try {
        $statement = $db->prepare($sql);
        if ($statement === false) { throw new \RuntimeException(); }
        try {
            if ($params && !$statement->bind_param(str_repeat('s', count($params)), ...$params)) { throw new \RuntimeException(); }
            if (!$statement->execute()) { throw new \RuntimeException(); }
            $result = $statement->get_result();
            if ($result === false) {
                if ($statement->field_count > 0) { throw new \RuntimeException(); }
                return [];
            }
            try {
                return array_map(static fn(array $row): array => array_map(static fn($value) => is_int($value) ? (string)$value : $value, $row), $result->fetch_all(MYSQLI_ASSOC));
            } finally { $result->free(); }
        } finally { $statement->close(); }
    } catch (\Throwable $e) { throw new FinanceViolation('FINANCE_STORAGE'); }
}

function transaction_active(\mysqli $db): bool
{
    $row = query($db, 'SELECT @@in_transaction AS active')[0] ?? null;
    if ($row === null || !in_array((string)$row['active'], ['0', '1'], true)) { throw new FinanceViolation('FINANCE_TRANSACTION_UNKNOWN'); }
    return (string)$row['active'] === '1';
}

function schema_sql(string $prefix): array
{
    $out = [];
    foreach (definition($prefix) as $table => $def) {
        $parts = [];
        foreach ($def['columns'] as $name => $type) { $parts[] = "`{$name}` {$type}"; }
        foreach ($def['indexes'] as $name => [$nonUnique, $columns]) {
            $parts[] = ($name === 'PRIMARY' ? 'PRIMARY KEY' : "KEY `{$name}`") . ' (`' . implode('`,`', $columns) . '`)';
        }
        $out[$table] = "CREATE TABLE IF NOT EXISTS `{$table}` (" . implode(',', $parts) . ') ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin';
    }
    return $out;
}

function verify_table(\mysqli $db, string $table, array $def): bool
{
    $info = query($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
    if (!$info) { return false; }
    if (count($info) !== 1 || $info[0]['ENGINE'] !== 'InnoDB') { throw new FinanceViolation('FINANCE_SCHEMA_ENGINE'); }
    $columns = query($db, "SHOW FULL COLUMNS FROM `{$table}`");
    if (array_column($columns, 'Field') !== array_keys($def['columns'])) { throw new FinanceViolation('FINANCE_SCHEMA_COLUMNS'); }
    foreach ($columns as $row) {
        $expected = explode(' ', $def['columns'][$row['Field']])[0];
        $type = preg_replace('/^bigint\([0-9]+\)$/', 'bigint', strtolower($row['Type']));
        $collation = str_contains($expected, 'char(') ? 'ascii_bin' : null;
        if ($type !== $expected || $row['Collation'] !== $collation || $row['Null'] !== 'NO'
            || $row['Default'] !== null || $row['Extra'] !== '') { throw new FinanceViolation('FINANCE_SCHEMA_COLUMNS'); }
    }
    $indexes = [];
    foreach (query($db, "SHOW INDEX FROM `{$table}`") as $row) {
        if ($row['Sub_part'] !== null || $row['Index_type'] !== 'BTREE' || $row['Collation'] !== 'A') { throw new FinanceViolation('FINANCE_SCHEMA_INDEX'); }
        $name = $row['Key_name'];
        $indexes[$name][0] = (int)$row['Non_unique'];
        $indexes[$name][1][(int)$row['Seq_in_index']] = $row['Column_name'];
    }
    foreach ($indexes as &$index) { ksort($index[1]); $index[1] = array_values($index[1]); } unset($index);
    if ($indexes != $def['indexes']) { throw new FinanceViolation('FINANCE_SCHEMA_INDEX'); }
    if (query($db, 'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_TYPE NOT IN (\'PRIMARY KEY\') LIMIT 1', [$table])
        || query($db, 'SELECT 1 FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=? LIMIT 1', [$table])) {
        throw new FinanceViolation('FINANCE_SCHEMA_CONSTRAINT');
    }
    return true;
}

function verify_schema(\mysqli $db, string $prefix): void
{
    foreach (definition($prefix) as $table => $def) { if (!verify_table($db, $table, $def)) { throw new FinanceViolation('FINANCE_SCHEMA_MISSING'); } }
}

/** False reports DDL only. Explicit true refuses to implicitly commit a transaction. */
function apply_schema(\mysqli $db, string $prefix, bool $apply = false): array
{
    $sql = schema_sql($prefix);
    if (!$apply) { return ['applied' => false, 'statements' => array_values($sql)]; }
    if (transaction_active($db)) { throw new FinanceViolation('FINANCE_SCHEMA_ACTIVE_TRANSACTION'); }
    // Inspect all existing tables before any DDL; drift is never silently fixed.
    foreach (definition($prefix) as $table => $def) { verify_table($db, $table, $def); }
    foreach ($sql as $statement) { query($db, $statement); }
    verify_schema($db, $prefix);
    return ['applied' => true];
}

function write_ready(\mysqli $db, string $prefix): array
{
    if (!transaction_active($db)) { throw new FinanceViolation('FINANCE_TRANSACTION_REQUIRED'); }
    $tables = array_keys(definition($prefix));
    // Hold metadata locks until caller commit/rollback BEFORE inspecting engine
    // and shape. Otherwise a concurrent ALTER could invalidate our InnoDB proof.
    foreach ($tables as $table) { query($db, "SELECT 1 FROM `{$table}` LIMIT 0"); }
    verify_schema($db, $prefix);
    return $tables;
}

function time_valid($value): string
{
    if (!is_string($value)) { throw new FinanceViolation('FINANCE_CORRUPT'); }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $value || $date->getTimestamp() <= 0) { throw new FinanceViolation('FINANCE_CORRUPT'); }
    return $value;
}

function validate_credit(array $row): array
{
    $fields = ['provider', 'receiving_account_ref', 'provider_credit_id', 'currency', 'net_minor', 'evidence_ref', 'evidence_sha256'];
    $rebuilt = credit_identity(array_intersect_key($row, array_flip($fields)));
    foreach ($rebuilt as $key => $value) { if (($row[$key] ?? null) !== $value) { throw new FinanceViolation('FINANCE_CORRUPT'); } }
    $claimed = minor($row['claimed_minor'] ?? null, false); $version = minor($row['version'] ?? null, false);
    if ($claimed > minor($rebuilt['net_minor']) || $claimed !== minor($row['consumed_minor'] ?? null, false)
        || $version !== minor($row['operation_count'] ?? null, false)) { throw new FinanceViolation('FINANCE_CORRUPT'); }
    return $rebuilt + ['claimed_minor' => (string)$claimed, 'remaining_minor' => (string)(minor($rebuilt['net_minor']) - $claimed),
        'version' => (string)$version, 'created_at_utc' => time_valid($row['created_at_utc'] ?? null)];
}

function validate_operation(array $row, array $credit): array
{
    $rebuilt = operation_identity($credit, array_intersect_key($row, array_flip(['operation_id', 'source', 'economic_root_ref', 'amount_minor', 'payload_digest'])));
    foreach ($rebuilt as $key => $value) { if (($row[$key] ?? null) !== $value) { throw new FinanceViolation('FINANCE_CORRUPT'); } }
    return $rebuilt + ['created_at_utc' => time_valid($row['created_at_utc'] ?? null)];
}

function locked_credit(\mysqli $db, array $tables, string $key): ?array
{
    [$credits, $operations] = $tables;
    $rows = query($db, "SELECT * FROM `{$credits}` WHERE credit_key=? FOR UPDATE", [$key]);
    if (!$rows) { return null; }
    // Current locking read, not an older REPEATABLE READ snapshot.
    $totals = query($db, "SELECT COALESCE(SUM(amount_minor),0) AS consumed_minor, COUNT(*) AS operation_count FROM `{$operations}` WHERE credit_key=? FOR UPDATE", [$key])[0];
    return validate_credit($rows[0] + array_map('strval', $totals));
}

function insert(\mysqli $db, string $table, array $row): void
{
    query($db, "INSERT INTO `{$table}` (`" . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
}

function register_credit(\mysqli $db, string $prefix, array $input): array
{
    $credit = credit_identity($input); $tables = write_ready($db, $prefix);
    $row = locked_credit($db, $tables, $credit['credit_key']); $disposition = 'existing';
    if ($row === null) {
        insert($db, $tables[0], $credit + ['claimed_minor' => '0', 'version' => '0', 'created_at_utc' => gmdate('Y-m-d H:i:s')]);
        $row = locked_credit($db, $tables, $credit['credit_key']); $disposition = 'registered';
    }
    if ($row === null || array_intersect_key($row, $credit) !== $credit) { throw new FinanceViolation('FINANCE_CREDIT_CONFLICT'); }
    return ['status' => 'pending_commit', 'disposition' => $disposition, 'credit' => $row];
}

function claim(\mysqli $db, string $prefix, array $creditInput, array $input): array
{
    $expected = credit_identity($creditInput); $operation = operation_identity($expected, $input);
    $tables = write_ready($db, $prefix); [$credits, $operations] = $tables;
    $credit = locked_credit($db, $tables, $expected['credit_key']);
    if ($credit === null) { throw new FinanceViolation('FINANCE_CREDIT_UNKNOWN'); }
    if (array_intersect_key($credit, $expected) !== $expected) { throw new FinanceViolation('FINANCE_CREDIT_CONFLICT'); }
    $existing = query($db, "SELECT * FROM `{$operations}` WHERE operation_id=? FOR UPDATE", [$operation['operation_id']]);
    if ($existing) {
        // Check association before rebuilding against this credit, including cross-credit reuse.
        if ($existing[0]['credit_key'] !== $credit['credit_key']) { throw new FinanceViolation('FINANCE_OPERATION_CONFLICT'); }
        $stored = validate_operation($existing[0], $credit);
        if (array_intersect_key($stored, $operation) !== $operation) { throw new FinanceViolation('FINANCE_OPERATION_CONFLICT'); }
        return ['status' => 'pending_commit', 'disposition' => 'duplicate', 'credit' => $credit, 'operation' => $stored];
    }
    $amount = minor($operation['amount_minor']); $consumed = minor($credit['claimed_minor'], false); $version = minor($credit['version'], false);
    if ($amount > minor($credit['net_minor']) - $consumed) { throw new FinanceViolation('FINANCE_CAPACITY'); }
    if ($version === PHP_INT_MAX) { throw new FinanceViolation('FINANCE_AMOUNT'); }
    insert($db, $operations, $operation + ['created_at_utc' => gmdate('Y-m-d H:i:s')]);
    query($db, "UPDATE `{$credits}` SET claimed_minor=?,version=? WHERE credit_key=? AND version=?",
        [(string)($consumed + $amount), (string)($version + 1), $credit['credit_key'], $credit['version']]);
    if ((string)(query($db, 'SELECT ROW_COUNT() AS n')[0]['n'] ?? '') !== '1') { throw new FinanceViolation('FINANCE_STORAGE'); }
    $storedCredit = locked_credit($db, $tables, $credit['credit_key']);
    $storedRows = query($db, "SELECT * FROM `{$operations}` WHERE operation_id=? FOR UPDATE", [$operation['operation_id']]);
    if ($storedCredit === null || !$storedRows || $storedCredit['claimed_minor'] !== (string)($consumed + $amount)) { throw new FinanceViolation('FINANCE_READBACK'); }
    $stored = validate_operation($storedRows[0], $storedCredit);
    if (array_intersect_key($stored, $operation) !== $operation) { throw new FinanceViolation('FINANCE_READBACK'); }
    return ['status' => 'pending_commit', 'disposition' => 'claimed', 'credit' => $storedCredit, 'operation' => $stored];
}

/** One non-locking SQL statement: joined originals/counters form one read view. */
function readback(\mysqli $db, string $prefix, string $identifier, bool $byOperation): array
{
    verify_schema($db, $prefix); $defs = definition($prefix); [$credits, $operations] = array_keys($defs);
    $select = [];
    foreach ($defs[$credits]['columns'] as $name => $_) { $select[] = "c.`{$name}` AS `c_{$name}`"; }
    if ($byOperation) { foreach ($defs[$operations]['columns'] as $name => $_) { $select[] = "o.`{$name}` AS `o_{$name}`"; } }
    $select[] = "(SELECT COALESCE(SUM(amount_minor),0) FROM `{$operations}` s WHERE s.credit_key=c.credit_key) AS consumed_minor";
    $select[] = "(SELECT COUNT(*) FROM `{$operations}` n WHERE n.credit_key=c.credit_key) AS operation_count";
    $from = $byOperation ? "`{$operations}` o LEFT JOIN `{$credits}` c ON c.credit_key=o.credit_key WHERE o.operation_id=?" : "`{$credits}` c WHERE c.credit_key=?";
    $rows = query($db, 'SELECT ' . implode(',', $select) . ' FROM ' . $from, [$identifier]);
    if (!$rows) { return ['status' => 'absent']; }
    $row = $rows[0]; $credit = []; $operation = [];
    foreach ($defs[$credits]['columns'] as $name => $_) { $credit[$name] = $row['c_' . $name]; }
    // Native prepared statements return BIGINT values as integers on 64-bit PHP.
    foreach (['net_minor', 'claimed_minor', 'version'] as $name) { if (is_int($credit[$name])) { $credit[$name] = (string)$credit[$name]; } }
    $credit = validate_credit($credit + ['consumed_minor' => (string)$row['consumed_minor'], 'operation_count' => (string)$row['operation_count']]);
    if (!$byOperation) { return ['status' => 'found', 'credit' => $credit]; }
    foreach ($defs[$operations]['columns'] as $name => $_) { $operation[$name] = $row['o_' . $name]; }
    if (is_int($operation['amount_minor'])) { $operation['amount_minor'] = (string)$operation['amount_minor']; }
    return ['status' => 'found', 'credit' => $credit, 'operation' => validate_operation($operation, $credit)];
}

function lookup(\mysqli $db, string $prefix, string $operationId): array { return readback($db, $prefix, token($operationId, 160), true); }
function lookup_credit(\mysqli $db, string $prefix, string $creditKey): array { return readback($db, $prefix, sha($creditKey), false); }
