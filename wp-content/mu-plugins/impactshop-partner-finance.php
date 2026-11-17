<?php
/**
 * Plugin Name: ImpactShop Partner Finance
 * Description: Signed, revisioned partner statements backed by shared bank receipts.
 * Version: 1.0.0
 * Author: Sharity
 * Requires PHP: 8.3
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ImpactShop_Partner_Finance
{
    private const STATEMENTS = 'sharity_partner_statements';
    private const TRANSACTIONS = 'sharity_partner_transactions';

    public static function register(): void
    {
        if (class_exists('ImpactShop_Sharity_Bank_Payout')) {
            ImpactShop_Sharity_Bank_Payout::register_handler('partner-statement', [self::class, 'handle'], 'collector');
        }
    }

    public static function schema_sql(string $prefix): array
    {
        self::prefix($prefix);
        return [
            "CREATE TABLE IF NOT EXISTS {$prefix}" . self::STATEMENTS . " (
                statement_key CHAR(64) NOT NULL, revision BIGINT UNSIGNED NOT NULL,
                content_hash CHAR(64) NOT NULL, policy_hash CHAR(64) NOT NULL,
                original_json LONGTEXT NOT NULL, result_json LONGTEXT NOT NULL,
                PRIMARY KEY (statement_key, revision)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS {$prefix}" . self::TRANSACTIONS . " (
                tx_key CHAR(64) NOT NULL, statement_key CHAR(64) NOT NULL,
                provider_tx_id VARCHAR(128) NOT NULL, original_minor BIGINT UNSIGNED NOT NULL,
                current_minor BIGINT UNSIGNED NOT NULL, entitlement_minor BIGINT UNSIGNED NOT NULL,
                ngo_id VARCHAR(128) NULL, binding_json LONGTEXT NULL,
                latest_revision BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (tx_key), KEY statement_transactions (statement_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
        ];
    }

    /** Explicit administrative DDL only: MySQL DDL is not transactionally rolled back. */
    public static function migrate(): array
    {
        global $wpdb;
        if (class_exists('ImpactShop_Sharity_Bank_Payout')) {
            $active = false;
            try {
                ImpactShop_Sharity_Bank_Payout::connection();
                $active = true;
            } catch (Throwable $error) {
                // The owner throws when no transaction is active.
            }
            if ($active) {
                throw new RuntimeException('partner_schema_migration_inside_transaction');
            }
        }
        $prefix = self::prefix((string) $wpdb->prefix);
        foreach (self::schema_sql($prefix) as $index => $sql) {
            if ($wpdb->query($sql) === false) {
                throw new RuntimeException('partner_schema_partial_migration');
            }
            if (!isset($wpdb->dbh) || !$wpdb->dbh instanceof mysqli
                || !self::check_schema($wpdb->dbh, $prefix, [$index === 0 ? self::STATEMENTS : self::TRANSACTIONS])) {
                throw new RuntimeException('partner_schema_partial_readback_failed');
            }
        }
        if (!self::schema_ready()) {
            throw new RuntimeException('partner_schema_readback_failed');
        }
        return ['schema_ready' => true, 'activation_performed' => false, 'economic_write' => false];
    }

    public static function schema_ready(): bool
    {
        global $wpdb;
        return isset($wpdb->dbh) && $wpdb->dbh instanceof mysqli
            && self::check_schema($wpdb->dbh, self::prefix((string) $wpdb->prefix));
    }

    public static function status(): array
    {
        return ['schema_ready' => self::schema_ready(), 'automatic_payment' => false];
    }

    /** The sole statement writer; called by B only after its signed-command checks. */
    public static function handle(array $payload, array $policy): array
    {
        $db = ImpactShop_Sharity_Bank_Payout::connection();
        ImpactShop_Sharity_Bank_Payout::assert_authorized_operation('partner-statement');
        global $wpdb;
        $prefix = self::prefix((string) $wpdb->prefix);
        if (!self::check_schema($db, $prefix)) {
            throw new RuntimeException('partner_schema_not_ready');
        }
        $statement = self::normalize_statement($payload['data'] ?? null);
        $partner = self::partner_policy($policy, $statement);
        $policyHash = hash('sha256', self::canonical($partner));
        $statementKey = self::key([$partner['provider'], $partner['account_id'], $statement['partner_id'], $statement['statement_id']]);
        $statementsTable = $prefix . self::STATEMENTS;
        $transactionsTable = $prefix . self::TRANSACTIONS;
        $contentHash = hash('sha256', self::canonical($statement));
        $prior = self::one($db, "SELECT * FROM {$statementsTable} WHERE statement_key=? ORDER BY revision DESC LIMIT 1 FOR UPDATE", [$statementKey]);
        if ($prior && (int) $prior['revision'] === $statement['revision']) {
            if (!hash_equals($prior['content_hash'], $contentHash) || !hash_equals($prior['policy_hash'], $policyHash)) {
                throw new DomainException('partner_revision_content_conflict');
            }
            return json_decode($prior['result_json'], true, 64, JSON_THROW_ON_ERROR);
        }
        if (($prior ? (int) $prior['revision'] : 0) !== $statement['previous_revision']
            || $statement['revision'] !== $statement['previous_revision'] + 1) {
            throw new DomainException('partner_revision_conflict');
        }
        $oldStatement = $prior ? json_decode($prior['original_json'], true, 64, JSON_THROW_ON_ERROR) : null;
        if ($prior) {
            if (!hash_equals($prior['policy_hash'], $policyHash)) {
                throw new DomainException('partner_policy_changed');
            }
            foreach (['currency', 'cash', 'part_id', 'policy_revision'] as $field) {
                if (self::canonical($oldStatement[$field]) !== self::canonical($statement[$field])) {
                    throw new DomainException('partner_original_identity_changed');
                }
            }
            if (array_column($oldStatement['lines'], 'provider_tx_id') !== array_column($statement['lines'], 'provider_tx_id')) {
                throw new DomainException('partner_original_transaction_set_changed');
            }
        }
        $claim = ImpactShop_Sharity_Bank_Payout::claim_cash(
            $statement['cash'], $statement['part_id'], 'impact_shop', 'partner:' . $statementKey
        );
        if (($claim['account_id'] ?? null) !== $partner['account_id']
            || ($claim['currency'] ?? null) !== $statement['currency']
            || !is_int($claim['net_minor'] ?? null)
            || ($statement['revision'] === 1 && $claim['net_minor'] !== $statement['net_minor'])
            || $statement['net_minor'] > $claim['net_minor']) {
            throw new DomainException('partner_cash_binding_mismatch');
        }
        $result = ['statement_id' => $statement['statement_id'], 'revision' => $statement['revision'], 'lines' => [], 'automatic_payment' => false];
        $negative = false;
        foreach ($statement['lines'] as $line) {
            $txKey = self::key([$partner['provider'], $partner['account_id'], $line['provider_tx_id']]);
            $old = self::one($db, "SELECT * FROM {$transactionsTable} WHERE tx_key=? FOR UPDATE", [$txKey]);
            if ($old && $old['statement_key'] !== $statementKey) {
                throw new DomainException('partner_transaction_already_owned');
            }
            if (($old && ((int) $old['latest_revision'] !== $statement['previous_revision'] || $line['net_minor'] > (int) $old['original_minor']))
                || (!$old && $statement['revision'] !== 1)) {
                throw new DomainException('partner_line_revision_conflict');
            }
            $mapping = self::resolve_attribution($db, $prefix, $partner, $statement, $line, $old);
            $ngo = $mapping['ngo_id'];
            $amount = $ngo === null ? 0 : self::share($line['net_minor'], $partner['ngo_rate_bps']);
            $delta = $amount - ($old ? (int) $old['entitlement_minor'] : 0);
            if ($delta !== 0) {
                ImpactShop_Sharity_Bank_Payout::record_entitlement([
                    'entry_id' => 'partner:' . self::key([$txKey, $statement['revision']]),
                    'source' => 'impact_shop', 'source_ref' => 'partner:' . $txKey,
                    'revision' => $statement['revision'], 'ngo_id' => $ngo,
                    'currency' => $statement['currency'], 'delta_minor' => $delta,
                    'cash_key' => $claim['cash_key'], 'part_id' => $claim['part_id'],
                    'evidence_ref' => $statement['evidence_ref'],
                ]);
            }
            $negative = $negative || $delta < 0;
            $bindingJson = $mapping['binding'] === null ? null : self::canonical($mapping['binding']);
            if ($old) {
                $write = self::query($db, "UPDATE {$transactionsTable} SET current_minor=?,entitlement_minor=?,ngo_id=?,binding_json=?,latest_revision=? WHERE tx_key=? AND latest_revision=?", [
                    $line['net_minor'], $amount, $ngo, $bindingJson, $statement['revision'], $txKey, $statement['previous_revision'],
                ]);
                if ($write->affected_rows !== 1) {
                    throw new DomainException('partner_line_cas_conflict');
                }
            } else {
                self::query($db, "INSERT INTO {$transactionsTable} (tx_key,statement_key,provider_tx_id,original_minor,current_minor,entitlement_minor,ngo_id,binding_json,latest_revision) VALUES (?,?,?,?,?,?,?,?,?)", [
                    $txKey, $statementKey, $line['provider_tx_id'], $line['net_minor'], $line['net_minor'], $amount, $ngo, $bindingJson, $statement['revision'],
                ]);
            }
            $result['lines'][] = ['provider_tx_id' => $line['provider_tx_id'], 'ngo_id' => $ngo,
                'net_minor' => $line['net_minor'], 'ngo_minor' => $amount, 'held' => $ngo === null, 'reason' => $mapping['reason']];
        }
        if ($negative) {
            ImpactShop_Sharity_Bank_Payout::invalidate_unsubmitted_holds();
        }
        self::query($db, "INSERT INTO {$statementsTable} (statement_key,revision,content_hash,policy_hash,original_json,result_json) VALUES (?,?,?,?,?,?)", [
            $statementKey, $statement['revision'], $contentHash, $policyHash, self::canonical($statement), self::canonical($result),
        ]);
        return $result;
    }

    public static function normalize_statement($value): array
    {
        self::fields($value, ['partner_id', 'statement_id', 'revision', 'previous_revision', 'policy_revision', 'currency', 'net_minor', 'cash', 'part_id', 'evidence_ref', 'source_digest', 'lines']);
        foreach (['partner_id', 'statement_id', 'policy_revision', 'part_id'] as $field) {
            self::identifier($value[$field]);
        }
        self::reference($value['evidence_ref']);
        self::digest($value['source_digest']);
        if (!is_int($value['revision']) || $value['revision'] < 1 || $value['revision'] > 2147483647
            || !is_int($value['previous_revision']) || $value['previous_revision'] < 0
            || $value['previous_revision'] !== $value['revision'] - 1) {
            throw new InvalidArgumentException('partner_invalid_revision');
        }
        if (!in_array($value['currency'], ['HUF', 'EUR'], true) || !is_array($value['cash']) || array_is_list($value['cash'])) {
            throw new InvalidArgumentException('partner_invalid_currency_or_cash');
        }
        self::amount($value['net_minor']);
        if (!is_array($value['lines']) || !array_is_list($value['lines']) || count($value['lines']) < 1 || count($value['lines']) > 1000) {
            throw new InvalidArgumentException('partner_invalid_lines');
        }
        $seen = [];
        $total = 0;
        foreach ($value['lines'] as $line) {
            self::fields($line, ['provider_tx_id', 'net_minor', 'attribution']);
            self::identifier($line['provider_tx_id']);
            self::amount($line['net_minor']);
            if (!is_array($line['attribution']) || ($line['attribution'] !== [] && array_is_list($line['attribution']))
                || isset($seen[$line['provider_tx_id']]) || $line['net_minor'] > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('partner_invalid_or_duplicate_line');
            }
            $seen[$line['provider_tx_id']] = true;
            $total += $line['net_minor'];
        }
        if ($total !== $value['net_minor']) {
            throw new InvalidArgumentException('partner_line_sum_mismatch');
        }
        usort($value['lines'], static fn(array $a, array $b): int => strcmp($a['provider_tx_id'], $b['provider_tx_id']));
        return $value;
    }

    public static function partner_policy(array $policy, array $statement): array
    {
        $partner = $policy['partners'][$statement['partner_id']] ?? null;
        if (!is_array($partner)) {
            throw new InvalidArgumentException('partner_policy_missing');
        }
        foreach (['revision', 'provider', 'account_id', 'source_kind'] as $field) {
            self::identifier($partner[$field] ?? null);
        }
        if ($partner['revision'] !== $statement['policy_revision']
            || !in_array($partner['source_kind'], ['partner_tx', 'dognet_intent', 'operator_evidence'], true)
            || ($partner['rounding'] ?? null) !== 'per_transaction_floor_minor'
            || !is_int($partner['ngo_rate_bps'] ?? null) || $partner['ngo_rate_bps'] < 0 || $partner['ngo_rate_bps'] > 10000
            || (isset($partner['allow_operator_evidence']) && !is_bool($partner['allow_operator_evidence']))) {
            throw new InvalidArgumentException('partner_policy_invalid');
        }
        $selected = array_intersect_key($partner, array_flip(['revision', 'provider', 'account_id', 'source_kind', 'ngo_rate_bps', 'rounding', 'provider_scope_ref']));
        $selected['allow_operator_evidence'] = $partner['allow_operator_evidence'] ?? false;
        if ($partner['source_kind'] === 'dognet_intent') {
            if ($partner['provider'] !== 'dognet') {
                throw new InvalidArgumentException('partner_dognet_provider_mismatch');
            }
            self::identifier($selected['provider_scope_ref'] ?? null);
        }
        return $selected;
    }

    public static function share(int $minor, int $basisPoints): int
    {
        self::amount($minor);
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('partner_invalid_rate');
        }
        return intdiv($minor, 10000) * $basisPoints + intdiv(($minor % 10000) * $basisPoints, 10000);
    }

    private static function resolve_attribution(mysqli $db, string $prefix, array $policy, array $statement, array $line, ?array $old): array
    {
        $attribution = $line['attribution'];
        $kind = $attribution['source_kind'] ?? $policy['source_kind'];
        if ($kind !== $policy['source_kind'] && !($kind === 'operator_evidence' && $policy['allow_operator_evidence'])) {
            throw new InvalidArgumentException('partner_attribution_kind_not_authorized');
        }
        $allowed = ['source_kind', 'ngo_id'];
        $binding = null;
        $ngo = null;
        $reason = 'attribution_missing';
        if ($kind === 'operator_evidence') {
            $allowed = array_merge($allowed, ['evidence_ref', 'source_digest']);
            if ($policy['allow_operator_evidence'] && isset($attribution['ngo_id'], $attribution['evidence_ref'], $attribution['source_digest'])) {
                self::identifier($attribution['ngo_id']);
                self::reference($attribution['evidence_ref']);
                self::digest($attribution['source_digest']);
                if (!hash_equals($statement['source_digest'], $attribution['source_digest'])) {
                    throw new InvalidArgumentException('partner_operator_evidence_digest_mismatch');
                }
                $ngo = $attribution['ngo_id'];
                $binding = ['source_kind' => $kind, 'ngo_id' => $ngo];
            }
        } elseif ($kind === 'partner_tx') {
            $binding = ['source_kind' => $kind, 'provider_tx_id' => $line['provider_tx_id']];
            if (!$old || $old['ngo_id'] === null) {
                try {
                    $row = self::one($db, "SELECT ngo_code,currency,payment_status,status FROM {$prefix}impact_partner_tx WHERE partner_code=? AND event_id=? FOR UPDATE", [$statement['partner_id'], $line['provider_tx_id']]);
                    if ($row && $row['currency'] === $statement['currency'] && $row['payment_status'] === 'paid' && $row['status'] === 'approved') {
                        self::identifier($row['ngo_code']);
                        $ngo = $row['ngo_code'];
                    }
                } catch (mysqli_sql_exception $error) {
                    if (!in_array($error->getCode(), [1146, 1054], true)) {
                        throw $error;
                    }
                    $reason = 'attribution_storage_unavailable';
                }
            }
        } elseif ($kind === 'dognet_intent') {
            $allowed = array_merge($allowed, ['provider_token_hash', 'provider_scope_ref']);
            if (isset($attribution['provider_token_hash'], $attribution['provider_scope_ref'])) {
                self::digest($attribution['provider_token_hash']);
                self::identifier($attribution['provider_scope_ref']);
                if ($attribution['provider_scope_ref'] !== ($policy['provider_scope_ref'] ?? null)) {
                    throw new InvalidArgumentException('partner_dognet_scope_mismatch');
                }
                $binding = ['source_kind' => $kind, 'provider_token_hash' => $attribution['provider_token_hash'], 'provider_scope_ref' => $attribution['provider_scope_ref']];
                if (!$old || $old['ngo_id'] === null) {
                    try {
                        $row = self::one($db, "SELECT ngo_ref,provider_scope_ref,status,redirected_at FROM {$prefix}impactshop_affiliate_intents WHERE provider_token_hash=? AND provider_key='dognet' AND partner_key=? FOR UPDATE", [$attribution['provider_token_hash'], $statement['partner_id']]);
                        if ($row && $row['status'] === 'redirected' && $row['redirected_at'] !== null && $row['redirected_at'] !== '' && $row['provider_scope_ref'] === $policy['provider_scope_ref']) {
                            self::identifier($row['ngo_ref']);
                            $ngo = $row['ngo_ref'];
                        }
                    } catch (mysqli_sql_exception $error) {
                        if (!in_array($error->getCode(), [1146, 1054], true)) {
                            throw $error;
                        }
                        $reason = 'attribution_storage_unavailable';
                    }
                }
            }
        }
        if (array_diff(array_keys($attribution), $allowed)) {
            throw new InvalidArgumentException('partner_unknown_attribution_fields');
        }
        if ($old && $old['ngo_id'] !== null) {
            if ($binding === null || self::canonical($binding) !== $old['binding_json']
                || ($ngo !== null && $ngo !== $old['ngo_id'])) {
                throw new DomainException('partner_original_attribution_changed');
            }
            $ngo = $old['ngo_id'];
        }
        if ($ngo !== null && isset($attribution['ngo_id']) && $attribution['ngo_id'] !== $ngo) {
            throw new DomainException('partner_asserted_ngo_mismatch');
        }
        return ['ngo_id' => $ngo, 'binding' => $ngo === null ? null : $binding, 'reason' => $ngo === null ? $reason : null];
    }

    private static function check_schema(mysqli $db, string $prefix, ?array $only = null): bool
    {
        $expected = [
            self::STATEMENTS => ['statement_key' => 'char(64)', 'revision' => 'bigint unsigned', 'content_hash' => 'char(64)', 'policy_hash' => 'char(64)', 'original_json' => 'longtext', 'result_json' => 'longtext'],
            self::TRANSACTIONS => ['tx_key' => 'char(64)', 'statement_key' => 'char(64)', 'provider_tx_id' => 'varchar(128)', 'original_minor' => 'bigint unsigned', 'current_minor' => 'bigint unsigned', 'entitlement_minor' => 'bigint unsigned', 'ngo_id' => 'varchar(128)', 'binding_json' => 'longtext', 'latest_revision' => 'bigint unsigned'],
        ];
        foreach ($expected as $suffix => $columns) {
            if ($only !== null && !in_array($suffix, $only, true)) {
                continue;
            }
            $table = $prefix . $suffix;
            $engine = self::one($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            if (!$engine || strtoupper($engine['ENGINE']) !== 'INNODB') {
                return false;
            }
            $rows = self::query($db, "SHOW COLUMNS FROM {$table}")->get_result()->fetch_all(MYSQLI_ASSOC);
            $found = array_column($rows, 'Type', 'Field');
            foreach ($columns as $name => $type) {
                if (!isset($found[$name]) || preg_replace('/bigint\(\d+\)/', 'bigint', strtolower($found[$name])) !== $type) {
                    return false;
                }
            }
            $indexes = self::query($db, "SHOW INDEX FROM {$table}")->get_result()->fetch_all(MYSQLI_ASSOC);
            $primary = [];
            foreach ($indexes as $index) {
                if ($index['Key_name'] === 'PRIMARY' && (int) $index['Non_unique'] === 0) {
                    $primary[(int) $index['Seq_in_index']] = $index['Column_name'];
                }
            }
            ksort($primary);
            if (array_values($primary) !== ($suffix === self::STATEMENTS ? ['statement_key', 'revision'] : ['tx_key'])) {
                return false;
            }
            if ($suffix === self::TRANSACTIONS) {
                $statementIndex = array_values(array_filter($indexes, static fn(array $index): bool => $index['Key_name'] === 'statement_transactions'));
                if (count($statementIndex) !== 1 || $statementIndex[0]['Column_name'] !== 'statement_key') {
                    return false;
                }
            }
        }
        return true;
    }

    private static function query(mysqli $db, string $sql, array $params = []): mysqli_stmt
    {
        $statement = $db->prepare($sql);
        if (!$statement) {
            throw new mysqli_sql_exception('partner_storage_prepare_failed', (int) $db->errno);
        }
        if ($params && !$statement->bind_param(str_repeat('s', count($params)), ...$params)) {
            throw new RuntimeException('partner_storage_bind_failed');
        }
        if (!$statement->execute()) {
            throw new mysqli_sql_exception('partner_storage_write_unconfirmed', (int) $statement->errno);
        }
        return $statement;
    }

    private static function one(mysqli $db, string $sql, array $params = []): ?array
    {
        $result = self::query($db, $sql, $params)->get_result();
        if (!$result) {
            throw new RuntimeException('partner_storage_read_failed');
        }
        return $result->fetch_assoc();
    }

    private static function fields($value, array $keys): void
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('partner_object_required');
        }
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new InvalidArgumentException('partner_invalid_fields');
        }
    }

    private static function amount($value): void
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException('partner_minor_integer_required');
        }
    }

    private static function prefix(string $prefix): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D', $prefix)) {
            throw new InvalidArgumentException('partner_invalid_table_prefix');
        }
        return $prefix;
    }

    private static function identifier($value): void
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $value)) {
            throw new InvalidArgumentException('partner_invalid_identifier');
        }
    }

    private static function digest($value): void
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) {
            throw new InvalidArgumentException('partner_invalid_digest');
        }
    }

    private static function reference($value): void
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_:.\/-]{1,191}$/D', $value)) {
            throw new InvalidArgumentException('partner_invalid_evidence_reference');
        }
    }

    private static function canonical($value): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = json_decode(self::canonical($item), true, 64, JSON_THROW_ON_ERROR);
                }
            }
            unset($item);
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function key(array $parts): string
    {
        return hash('sha256', self::canonical($parts));
    }
}

add_action('plugins_loaded', [ImpactShop_Partner_Finance::class, 'register'], 20);
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity partner-finance', static function (array $args): void {
        $operation = $args[0] ?? 'status';
        if (!in_array($operation, ['status', 'migrate'], true)) {
            WP_CLI::error('Only status or explicit migrate is supported.');
        }
        try {
            WP_CLI::line(json_encode($operation === 'migrate' ? ImpactShop_Partner_Finance::migrate() : ImpactShop_Partner_Finance::status(), JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            WP_CLI::error($error->getMessage());
        }
    });
}
