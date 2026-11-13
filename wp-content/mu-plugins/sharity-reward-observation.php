<?php

declare(strict_types=1);

/** Plugin Name: Sharity reward observations (default off, no producer hooks)
 *
 * before()/after() observe an EXISTING caller; they never invoke an award.
 * Call before with final normalized subject, dedupe and expected arguments,
 * then pass the existing unmodified manager result to after. A null token must
 * never prevent earning. Do not place either call inside a business transaction.
 * The rollout/exporter binds the inspected award_checked manager contract; no
 * per-request file hashing is performed. `fulfilled` records that manager's
 * checked result, not independent monitor acceptance: the reader must still
 * verify the exact canonical target ledger. eligibility_seen is sticky, so a
 * later blocked/unknown attempt cannot erase earlier proven eligibility.
 * PRIVATE normalized subject/dedupe selectors and a checked transaction ID stay
 * in this database solely for indexed canonical lookup. Never export them or
 * return them from this API. No raw source ID, metadata, comment or exception
 * is stored; public snapshot fields remain fingerprints only.
 * Domain-separated SHA-256 lets the private SELECT-only reader reproduce these
 * fingerprints from authorized ledger rows without WordPress/auth secrets.
 * These private references are not a public anonymization or lookup endpoint.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class Sharity_Reward_Observation
{
    private const FAILURES = [
        'transaction_begin_failed', 'balance_write_failed', 'balance_read_failed',
        'ledger_read_failed', 'ledger_write_failed', 'level_write_failed', 'dedupe_conflict',
    ];

    public static function enabled(): bool
    {
        return defined('SHARITY_REWARD_OBSERVATION_ENABLED') && SHARITY_REWARD_OBSERVATION_ENABLED === true;
    }

    /** Observe the inspected manager's public arguments; never call its earning gate.
     * Callers already sanitize pseudo IDs. The manager lowercases them and uses
     * this fallback only for a null dedupe argument (an empty string is distinct).
     * This compatibility contract is bound to manager + points-helpers at rollout.
     */
    public static function beforeAward($lane, $kind, $subject, $points, $type, $sourceId, $metadata, $dedupe): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        try {
            if (!is_array($metadata) || !is_string($type)) {
                throw new InvalidArgumentException('arguments_invalid');
            }
            if ($dedupe === null) {
                if (!empty($metadata['dedupe_key'])) {
                    if (!is_scalar($metadata['dedupe_key'])) {
                        throw new InvalidArgumentException('dedupe_unrepresentable');
                    }
                    $dedupe = (string) $metadata['dedupe_key'];
                } else {
                    $parts = [$type];
                    if ($sourceId) $parts[] = $sourceId;
                    if (!empty($metadata['date'])) {
                        if (!is_scalar($metadata['date'])) {
                            throw new InvalidArgumentException('date_unrepresentable');
                        }
                        $parts[] = (string) $metadata['date'];
                    }
                    $dedupe = implode(':', $parts);
                }
            }
            // The inspected manager stores wp_json_encode($metadata), default
            // flags, then its readback decodes associative arrays. Match that
            // storage representation (1.0 becomes 1) in this observation copy;
            // the caller still passes its original metadata to the manager.
            // Validate the bounded original first; never convert objects into
            // apparently supported metadata or silently repair invalid input.
            self::canonical($metadata);
            $storedMetadata = json_decode(json_encode($metadata, JSON_THROW_ON_ERROR), true, 16, JSON_THROW_ON_ERROR);
            return self::before([
                'lane' => $lane, 'subject_kind' => $kind,
                'subject_id' => $kind === 'pseudo' && is_string($subject) ? strtolower($subject) : $subject,
                'points' => $points, 'type' => $type, 'source_id' => $sourceId,
                'source_type' => $metadata['source_type'] ?? null,
                'metadata' => $storedMetadata, 'dedupe_key' => $dedupe,
            ]);
        } catch (Throwable $ignored) {
            self::incomplete('recording_unconfirmed');
            return null;
        }
    }

    /** Explicit operator/CLI call only; never called at plugin load or page boot. */
    public static function migrate(): bool
    {
        if (PHP_SAPI !== 'cli' || !defined('SHARITY_REWARD_OBSERVATION_MIGRATE') || SHARITY_REWARD_OBSERVATION_MIGRATE !== true) {
            return false;
        }
        return self::safely(static function ($db, string $table): bool {
            return $db->query("CREATE TABLE IF NOT EXISTS {$table} (
                observation_id bigint unsigned NOT NULL AUTO_INCREMENT,
                event_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                owner_ref char(64) NOT NULL, lane varchar(64) NOT NULL,
                dedupe_hash char(64) NOT NULL, expected_digest char(64) NOT NULL,
                private_subject_kind varchar(8) NOT NULL,
                private_subject_id varchar(128) NOT NULL,
                private_dedupe_key varchar(191) NOT NULL,
                metadata_hash char(64) NOT NULL, points bigint NOT NULL,
                point_type varchar(64) NOT NULL, source_ref_hash char(64) NULL,
                source_type varchar(64) NULL, state varchar(24) NOT NULL,
                latest_attempt_ref char(64) NOT NULL, eligibility_seen tinyint(1) NOT NULL DEFAULT 0,
                candidate_attempts bigint unsigned NOT NULL DEFAULT 1,
                content_conflicts bigint unsigned NOT NULL DEFAULT 0,
                first_candidate_at_utc datetime(6) NOT NULL,
                last_candidate_at_utc datetime(6) NOT NULL,
                updated_at_utc datetime(6) NOT NULL,
                last_result_at_utc datetime(6) NULL, result_code varchar(40) NULL,
                transaction_ref char(64) NULL, private_transaction_id bigint unsigned NULL,
                PRIMARY KEY (observation_id), UNIQUE KEY event_identity (event_key),
                KEY pending (state,first_candidate_at_utc,event_key),
                KEY updated (updated_at_utc,event_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") !== false;
        }, false);
    }

    /** @return array{event_key:string,expected_digest:string,attempt_ref:string}|null */
    public static function before($expected): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        return self::safely(static function ($db, string $table) use ($expected): ?array {
            if (!is_array($expected)) {
                throw new InvalidArgumentException('expected_invalid');
            }
            $record = self::expected($expected);
            $attempt = bin2hex(random_bytes(32));
            $sql = $db->prepare("INSERT INTO {$table}
                (event_key,owner_ref,lane,dedupe_hash,expected_digest,metadata_hash,points,point_type,
                 source_ref_hash,source_type,private_subject_kind,private_subject_id,private_dedupe_key,
                 state,latest_attempt_ref,first_candidate_at_utc,last_candidate_at_utc,updated_at_utc)
                VALUES (%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,'candidate',%s,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
                ON DUPLICATE KEY UPDATE
                 content_conflicts=content_conflicts+IF(expected_digest=VALUES(expected_digest),0,1),
                 candidate_attempts=candidate_attempts+1,
                 updated_at_utc=UTC_TIMESTAMP(6),
                 last_candidate_at_utc=IF(expected_digest=VALUES(expected_digest),UTC_TIMESTAMP(6),last_candidate_at_utc),
                 latest_attempt_ref=IF(expected_digest=VALUES(expected_digest) AND state<>'fulfilled',VALUES(latest_attempt_ref),latest_attempt_ref),
                 state=IF(expected_digest=VALUES(expected_digest) AND state<>'fulfilled','candidate',state)",
                $record['event_key'], $record['owner_ref'], $record['lane'], $record['dedupe_hash'],
                $record['expected_digest'], $record['metadata_hash'], $record['points'], $record['point_type'],
                $record['source_ref_hash'], $record['source_type'], $record['private_subject_kind'],
                $record['private_subject_id'], $record['private_dedupe_key'], $attempt
            );
            if ($db->query(self::bounded($sql)) === false) {
                throw new RuntimeException('candidate_write_failed');
            }
            $row = $db->get_row(self::bounded($db->prepare("SELECT expected_digest FROM {$table} WHERE event_key=%s", $record['event_key'])), ARRAY_A);
            if (!is_array($row) || !hash_equals($record['expected_digest'], (string) ($row['expected_digest'] ?? ''))) {
                throw new RuntimeException('candidate_unconfirmed');
            }
            return ['event_key' => $record['event_key'], 'expected_digest' => $record['expected_digest'], 'attempt_ref' => $attempt];
        }, null);
    }

    /** False means observation unconfirmed, never permission to retry an award.
     * Stale non-success results may be ignored; a checked success from any
     * matching attempt can fulfill the event. Unknown/blocked cannot undo it.
     */
    public static function after($token, $result): bool
    {
        if (!self::enabled() || $token === null) {
            return false;
        }
        return self::safely(static function ($db, string $table) use ($token, $result): bool {
            if (!is_array($token)) {
                throw new InvalidArgumentException('token_invalid');
            }
            foreach (['event_key', 'expected_digest', 'attempt_ref'] as $key) {
                if (!isset($token[$key]) || !is_string($token[$key]) || !preg_match('/^[a-f0-9]{64}$/D', $token[$key])) {
                    throw new RuntimeException('token_invalid');
                }
            }
            $state = 'unknown';
            $code = 'result_unconfirmed';
            $eligible = 0;
            $transaction = '';
            $transactionId = 0;
            if (is_array($result)) {
                $error = $result['error'] ?? null;
                if (($result['success'] ?? null) === true && is_int($result['transaction_id'] ?? null) && $result['transaction_id'] > 0) {
                    $state = 'fulfilled';
                    $code = 'manager_receipt_reported';
                    $eligible = 1;
                    $transaction = self::digest('point_transaction', (string) $result['transaction_id']);
                    $transactionId = $result['transaction_id'];
                } elseif (($result['success'] ?? null) === false && $error === 'earning_blocked') {
                    $state = 'excluded';
                    $code = 'earning_blocked';
                } elseif (($result['success'] ?? null) === false && in_array($error, self::FAILURES, true)) {
                    $state = 'eligible_failed';
                    $code = $error;
                    $eligible = 1;
                } elseif ($error === 'commit_unconfirmed') {
                    $code = 'commit_unconfirmed';
                }
            }
            $sql = $db->prepare("UPDATE {$table} SET
                eligibility_seen=GREATEST(eligibility_seen,%d),
                result_code=IF(state='fulfilled' OR (latest_attempt_ref<>%s AND %s<>'fulfilled'),result_code,%s),
                transaction_ref=IF(state='fulfilled' OR (latest_attempt_ref<>%s AND %s<>'fulfilled'),transaction_ref,NULLIF(%s,'')),
                last_result_at_utc=IF(state='fulfilled' OR (latest_attempt_ref<>%s AND %s<>'fulfilled'),last_result_at_utc,UTC_TIMESTAMP(6)),
                private_transaction_id=IF(state='fulfilled' OR (latest_attempt_ref<>%s AND %s<>'fulfilled'),private_transaction_id,NULLIF(%d,0)),
                updated_at_utc=UTC_TIMESTAMP(6),
                state=IF(state='fulfilled' OR (latest_attempt_ref<>%s AND %s<>'fulfilled'),state,%s)
                WHERE event_key=%s AND expected_digest=%s",
                $eligible, $token['attempt_ref'], $state, $code,
                $token['attempt_ref'], $state, $transaction, $token['attempt_ref'], $state,
                $token['attempt_ref'], $state, $transactionId,
                $token['attempt_ref'], $state, $state, $token['event_key'], $token['expected_digest']
            );
            if ($db->query(self::bounded($sql)) === false) {
                throw new RuntimeException('result_write_failed');
            }
            return true;
        }, false);
    }

    private static function expected(array $e): array
    {
        if (!isset($e['lane'], $e['subject_kind'], $e['subject_id'], $e['dedupe_key'], $e['points'], $e['type'], $e['metadata']) ||
            !is_string($e['lane']) || !preg_match('/^[a-z][a-z0-9_.:-]{0,63}$/D', $e['lane']) ||
            !in_array($e['subject_kind'], ['pseudo', 'user'], true) ||
            (!is_string($e['subject_id']) && !is_int($e['subject_id'])) || (string) $e['subject_id'] === '' || strlen((string) $e['subject_id']) > 128 ||
            !is_string($e['dedupe_key']) || $e['dedupe_key'] === '' || strlen($e['dedupe_key']) > 191 ||
            !is_int($e['points']) || $e['points'] === 0 || abs($e['points']) > 9007199254740991 ||
            !is_string($e['type']) || !preg_match('/^[a-z][a-z0-9_:-]{0,63}$/D', $e['type']) || !is_array($e['metadata'])) {
            throw new InvalidArgumentException('expected_invalid');
        }
        foreach (['source_id', 'source_type'] as $key) {
            if (($e[$key] ?? null) !== null && (!is_string($e[$key]) || strlen($e[$key]) > ($key === 'source_id' ? 255 : 64))) {
                throw new InvalidArgumentException('expected_invalid');
            }
        }
        // Source type is a public fixed diagnostic label; source IDs are hashed.
        if (($e['source_type'] ?? null) !== null && !preg_match('/^[a-z][a-z0-9_:-]{0,63}$/D', $e['source_type'])) {
            throw new InvalidArgumentException('expected_invalid');
        }
        $metadata = self::canonical($e['metadata']);
        if (strlen($metadata) > 16384) {
            throw new InvalidArgumentException('metadata_limit');
        }
        $owner = self::digest('subject', $e['subject_kind'] . ':' . (string) $e['subject_id']);
        $dedupe = self::digest('dedupe', $e['dedupe_key']);
        $meta = self::digest('metadata', $metadata);
        $source = self::digest('source', self::canonical($e['source_id'] ?? null));
        return ['event_key' => self::digest('event', self::canonical([$e['lane'], $owner, $dedupe])), 'owner_ref' => $owner,
            'private_subject_kind' => $e['subject_kind'], 'private_subject_id' => (string) $e['subject_id'], 'private_dedupe_key' => $e['dedupe_key'],
            'lane' => $e['lane'], 'dedupe_hash' => $dedupe, 'metadata_hash' => $meta, 'points' => $e['points'],
            'point_type' => $e['type'], 'source_ref_hash' => $source, 'source_type' => $e['source_type'] ?? '',
            'expected_digest' => self::digest('expected', self::canonical([$owner, $dedupe, $e['points'], $e['type'], $source, $e['source_type'] ?? null, $meta]))];
    }

    private static function canonical($value, int $depth = 0): string
    {
        if ($depth > 8 || is_object($value) || is_resource($value) || is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('metadata_invalid');
        }
        if (is_array($value)) {
            if (count($value) > 2048) {
                throw new InvalidArgumentException('metadata_limit');
            }
            $list = array_is_list($value);
            if (!$list) {
                ksort($value, SORT_STRING);
            }
            $parts = [];
            $bytes = 0;
            foreach ($value as $key => $item) {
                if (count($parts) >= 2048 || strlen((string) $key) > 16384) {
                    throw new InvalidArgumentException('metadata_limit');
                }
                $part = ($list ? '' : json_encode((string) $key, JSON_THROW_ON_ERROR) . ':') . self::canonical($item, $depth + 1);
                $bytes += strlen($part) + 1;
                if ($bytes > 16384) {
                    throw new InvalidArgumentException('metadata_limit');
                }
                $parts[] = $part;
            }
            return ($list ? '[' : '{') . implode(',', $parts) . ($list ? ']' : '}');
        }
        if (is_string($value) && strlen($value) > 16384) {
            throw new InvalidArgumentException('metadata_limit');
        }
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($encoded) > 16384) {
            throw new InvalidArgumentException('metadata_limit');
        }
        return $encoded;
    }

    private static function digest(string $domain, string $value): string
    {
        return hash('sha256', 'reward-observation:v1:' . $domain . ':' . $value);
    }

    /** MariaDB statement-local ceiling; never changes the caller's session or
     * business-query timeout. Unsupported storage remains coverage unknown.
     * Explicit migration DDL is outside this request-path ceiling.
     */
    private static function bounded(string $sql): string
    {
        return 'SET STATEMENT max_statement_time=0.015 FOR ' . $sql;
    }

    /** Operator configuration only, used solely on recorder failure. Never
     * accepts a request path or intentionally creates a log. A successful
     * append is not producer-completeness authority: rollout/readback must
     * bind the same configuration and existing file. PHP's native type-3
     * append cannot atomically exclude rotation; an inode change is unknown.
     */
    private static function incomplete(string $code): bool
    {
        $marker = 'sharity_reward_observation_incomplete code=' . $code;
        try {
            if (!defined('SHARITY_REWARD_OBSERVATION_ERROR_LOG')) {
                return @error_log($marker);
            }
            $path = SHARITY_REWARD_OBSERVATION_ERROR_LOG;
            if (!is_string($path) || strlen($path) > 1024 || !str_starts_with($path, '/') ||
                preg_match('/[\x00-\x1f\x7f]/', $path) || !function_exists('posix_geteuid')) {
                throw new RuntimeException('destination_unconfirmed');
            }
            clearstatcache(true, $path);
            $before = @lstat($path);
            if (!is_array($before) || ($before['mode'] & 0170000) !== 0100000 ||
                $before['uid'] !== posix_geteuid() || @realpath($path) !== $path ||
                !@is_readable($path) || !@is_writable($path)) {
                throw new RuntimeException('destination_unconfirmed');
            }
            if (!@error_log($marker . "\n", 3, $path)) {
                throw new RuntimeException('destination_unconfirmed');
            }
            clearstatcache(true, $path);
            $after = @lstat($path);
            if (!is_array($after) || $after['dev'] !== $before['dev'] || $after['ino'] !== $before['ino'] ||
                ($after['mode'] & 0170000) !== 0100000 || $after['uid'] !== $before['uid'] || @realpath($path) !== $path) {
                throw new RuntimeException('destination_unconfirmed');
            }
            return true;
        } catch (Throwable $ignored) {
            // An explicit destination failure never silently becomes a proven
            // native destination and never propagates into the earning call.
            try { @error_log('sharity_reward_observation_incomplete code=error_destination_unconfirmed'); }
            catch (Throwable $ignoredAgain) {}
            return false;
        }
    }

    /** No BEGIN/COMMIT/ROLLBACK and no business tables. Refuse active sessions
     * before any observation/DDL write; recording cannot commit a caller's work.
     * This safe error_log marker requires persistent sink/readback verification
     * at rollout. It is NOT proof that an incomplete interval was delivered.
     */
    private static function safely(callable $operation, $fallback)
    {
        global $wpdb;
        $previous = null;
        $saved = [];
        $db = null;
        try {
            if (!is_object($wpdb) || !isset($wpdb->prefix) || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->prefix)) {
                throw new RuntimeException('storage_unavailable');
            }
            $db = $wpdb;
            $public = get_object_vars($db);
            foreach (['insert_id', 'last_error', 'rows_affected', 'num_rows', 'last_query', 'last_result', 'col_info'] as $key) {
                if (array_key_exists($key, $public)) {
                    $saved[$key] = $public[$key];
                }
            }
            $previous = $wpdb->suppress_errors(true);
            $session = $wpdb->get_row(self::bounded('SELECT @@autocommit AS enabled,@@in_transaction AS active'), ARRAY_A);
            if (!is_array($session) || (string) ($session['enabled'] ?? '') !== '1' || (string) ($session['active'] ?? '') !== '0') {
                throw new RuntimeException('transaction_context_unverified');
            }
            return $operation($wpdb, '`' . $wpdb->prefix . 'sharity_reward_observations`');
        } catch (Throwable $ignored) {
            self::incomplete('recording_unconfirmed');
            return $fallback;
        } finally {
            if ($previous !== null) {
                try {
                    $wpdb->suppress_errors($previous);
                } catch (Throwable $ignored) {
                    self::incomplete('error_mode_unconfirmed');
                }
            }
            // A recorder must not replace the caller's accepted source insert
            // ID, error or fetched result. Actual query counters remain honest.
            foreach ($saved as $key => $value) {
                try {
                    $db->$key = $value;
                } catch (Throwable $ignored) {
                    self::incomplete('result_context_unconfirmed');
                }
            }
        }
    }
}
