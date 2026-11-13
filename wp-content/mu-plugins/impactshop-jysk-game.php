<?php
/**
 * Plugin Name: ImpactShop JYSK Game Bridge
 * Description: Elkülönített, alapból zárt JYSK identitás-, jutalom- és üzenetbridge.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Impactshop_Jysk_Game
{
    public const CALLBACK = 'https://jysk.sharity.hu/api/jysk/game/session/callback';
    public const CLIENT = 'jysk_2026';
    public const PROFILE = 'https://app.sharity.hu/profil/?jysk_return=1#impactshop-signin';
    public const LOGIN_START = 'https://jysk.sharity.hu/api/jysk/game/session/start';
    public const PRIVATE_CONFIG = '/home/sharityh/jysk-game/config.json';
    private static array $wire = [];
    private static ?array $profile = null;
    private static string $profileStatus = 'unread';
    private static ?array $sessionSchema = null;

    private static function profile(): array
    {
        if (self::$profile !== null) {
            return self::$profile;
        }
        self::$profile = [];
        self::$profileStatus = 'absent';
        $path = self::PRIVATE_CONFIG;
        $dir = dirname($path);
        if (!file_exists($path)) {
            return [];
        }
        self::$profileStatus = 'invalid';
        if (is_link($dir) || is_link($path) || !is_dir($dir) || !is_file($path)
            || (fileperms($dir) & 0777) !== 0700 || (fileperms($path) & 0777) !== 0600
            || filesize($path) > 16384 || fileowner($dir) !== fileowner($path)) {
            return [];
        }
        $raw = @file_get_contents($path, false, null, 0, 16385);
        if (!is_string($raw) || strlen($raw) > 16384) {
            return [];
        }
        $value = json_decode($raw, true);
        $keys = ['ENABLED', 'SERVICE_KEY', 'SUBJECT_KEY', 'REWARDS_ENABLED', 'TRANSACTION_READY', 'POLICY_HASH', 'PRODUCER_SHAS', 'NGO_IDS', 'TEST_SUBJECTS', 'SESSION_MODE', 'SESSION_TTL_SECONDS'];
        if (!is_array($value) || array_is_list($value) || array_diff(array_keys($value), $keys)) {
            return [];
        }
        foreach ($value as $key => $item) {
            if ($key === 'SESSION_MODE' && !in_array($item, ['legacy', 'per_device_v1'], true)
                || $key === 'SESSION_TTL_SECONDS' && (!is_int($item) || $item < 900 || $item > 31536000)) {
                return [];
            }
            if (in_array($key, ['ENABLED', 'REWARDS_ENABLED', 'TRANSACTION_READY'], true) && !is_bool($item)) {
                return [];
            }
            if (in_array($key, ['SERVICE_KEY', 'SUBJECT_KEY', 'POLICY_HASH'], true)
                && (!is_string($item) || !preg_match('/^[a-f0-9]{64}$/D', $item))) {
                return [];
            }
            if (in_array($key, ['PRODUCER_SHAS', 'NGO_IDS', 'TEST_SUBJECTS'], true)) {
                if (!is_array($item) || !array_is_list($item) || count($item) > ($key === 'TEST_SUBJECTS' ? 1 : 200)) {
                    return [];
                }
                foreach ($item as $entry) {
                    $pattern = $key === 'PRODUCER_SHAS' ? '/^[a-f0-9]{40}$/D' : ($key === 'TEST_SUBJECTS' ? '/^[a-f0-9]{64}$/D' : '/^[A-Za-z0-9_-]{1,64}$/D');
                    if (!is_string($entry) || !preg_match($pattern, $entry)) {
                        return [];
                    }
                }
            }
        }
        if (($value['ENABLED'] ?? false) && (!isset($value['SERVICE_KEY'], $value['SUBJECT_KEY']) || hash_equals($value['SERVICE_KEY'], $value['SUBJECT_KEY']))) {
            return [];
        }
        self::$profileStatus = 'ready';
        return self::$profile = $value;
    }

    public static function config(string $name, $default = null)
    {
        // Existing explicit constants remain useful for hermetic fixtures.
        return defined('JYSK_GAME_' . $name) ? constant('JYSK_GAME_' . $name) : (self::profile()[$name] ?? $default);
    }

    /** Operator CLI diagnostic only; never returns keys, tokens or recipient data. */
    public static function configurationStatus(): array
    {
        self::profile();
        return ['privateProfile' => self::$profileStatus, 'enabled' => self::enabled(),
            'rewardsEnabled' => self::config('REWARDS_ENABLED', false) === true,
            'transactionFlag' => self::config('TRANSACTION_READY', false) === true,
            'sessionMode' => self::config('SESSION_MODE', 'legacy'),
            'sessionTtlSeconds' => self::config('SESSION_TTL_SECONDS', 900),
            'secretsConfigured' => preg_match('/^[a-f0-9]{64}$/D', (string) self::config('SERVICE_KEY', '')) === 1
                && preg_match('/^[a-f0-9]{64}$/D', (string) self::config('SUBJECT_KEY', '')) === 1
                && self::config('SERVICE_KEY', '') !== self::config('SUBJECT_KEY', '')];
    }

    public static function enabled(): bool
    {
        return self::config('ENABLED', false) === true;
    }

    private static function key(string $name): string
    {
        $key = self::config($name, '');
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new RuntimeException('configuration_unavailable');
        }
        if ($name === 'SUBJECT_KEY' && hash_equals($key, (string) self::config('SERVICE_KEY', ''))) {
            throw new RuntimeException('configuration_unavailable');
        }
        return hex2bin($key);
    }

    private static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jysk_game_' . $name;
    }

    private static function check($result)
    {
        global $wpdb;
        if ($result === false || $wpdb->last_error !== '') {
            throw new RuntimeException('storage_unavailable');
        }
        return $result;
    }

    private static function query(string $sql): int
    {
        global $wpdb;
        return (int) self::check($wpdb->query($sql));
    }

    private static function row(string $sql): ?array
    {
        global $wpdb;
        $row = self::check($wpdb->get_row($sql, ARRAY_A));
        return is_array($row) ? $row : null;
    }

    private static function scalar(string $sql)
    {
        global $wpdb;
        return self::check($wpdb->get_var($sql));
    }

    /** Explicit operator/CLI operation only; never called by a request or cron. */
    public static function install(): void
    {
        if (!defined('WP_CLI') || WP_CLI !== true) {
            throw new RuntimeException('operator_cli_only');
        }
        global $wpdb;
        $schemas = [
            'grants' => 'subject char(64) NOT NULL, pseudo varchar(32) NOT NULL, grant_hash char(64) NOT NULL, generation char(64) NOT NULL, revoked tinyint NOT NULL DEFAULT 0, PRIMARY KEY(subject)',
            'codes' => 'token_hash char(64) NOT NULL, subject char(64) NOT NULL, generation char(64) NOT NULL, challenge varchar(43) NOT NULL, expires bigint NOT NULL, PRIMARY KEY(token_hash)',
            'sessions' => 'token_hash char(64) NOT NULL, subject char(64) NOT NULL, generation char(64) NOT NULL, expires bigint NOT NULL, PRIMARY KEY(token_hash), KEY subject(subject)',
            'nonces' => 'token_hash char(64) NOT NULL, expires bigint NOT NULL, PRIMARY KEY(token_hash)',
            'rewards' => 'event_id char(64) NOT NULL, payload_hash char(64) NOT NULL, payload text NOT NULL, state varchar(24) NOT NULL, points int NOT NULL, PRIMARY KEY(event_id)',
            'message_map' => 'message_id bigint unsigned NOT NULL, subject char(64) NOT NULL, delivered_at datetime NULL, PRIMARY KEY(message_id,subject)',
        ];
        foreach ($schemas as $name => $schema) {
            self::query('CREATE TABLE IF NOT EXISTS ' . self::table($name) . ' (' . $schema . ') ENGINE=InnoDB ' . $wpdb->get_charset_collate());
        }
    }

    private static function sessionStructure(bool $refresh = false): array
    {
        if (!$refresh && self::$sessionSchema !== null) { return self::$sessionSchema; }
        $tables = []; $present = 0;
        foreach (['codes' => ['owner_grant_hash'], 'sessions' => ['owner_grant_hash', 'issued_at']] as $name => $added) {
            global $wpdb;
            $rows = self::check($wpdb->get_results('SHOW COLUMNS FROM ' . self::table($name), ARRAY_A));
            $columns = [];
            foreach ($rows as $row) { $columns[$row['Field']] = $row; }
            foreach (['token_hash', 'subject', 'generation', 'expires'] as $required) {
                if (!isset($columns[$required])) { throw new RuntimeException('session_storage_unavailable'); }
            }
            foreach ($added as $column) {
                if (!isset($columns[$column])) { continue; }
                $present++;
                $type = strtolower($columns[$column]['Type']);
                if ($columns[$column]['Null'] !== 'YES' || ($column === 'owner_grant_hash' ? $type !== 'char(64)' : !preg_match('/^bigint(?:\(20\))?$/D', $type))) {
                    throw new RuntimeException('session_schema_conflict');
                }
            }
            ksort($columns); $tables[$name] = $columns;
        }
        return self::$sessionSchema = ['state' => $present === 0 ? 'legacy' : ($present === 3 ? 'ready' : 'partial'),
            'hash' => hash('sha256', json_encode($tables, JSON_THROW_ON_ERROR)), 'columns' => $tables];
    }

    private static function sessionSettings(): array
    {
        $mode = self::config('SESSION_MODE', 'legacy'); $ttl = self::config('SESSION_TTL_SECONDS', 900);
        if (!in_array($mode, ['legacy', 'per_device_v1'], true) || !is_int($ttl) || $ttl < 900 || $ttl > 31536000
            || $mode === 'legacy' && $ttl !== 900) { throw new RuntimeException('session_configuration_invalid'); }
        $schema = self::sessionStructure();
        if ($schema['state'] === 'partial' || $mode !== 'legacy' && $schema['state'] !== 'ready') {
            throw new RuntimeException('session_schema_unavailable');
        }
        return ['perDevice' => $mode === 'per_device_v1', 'bound' => $schema['state'] === 'ready', 'ttl' => $ttl];
    }

    /** Own central grant; never substitute the latest other device's mapping. */
    private static function ownerGrant(array $mapping, string $hash, bool $lock = false): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || !function_exists('impactshop_identity_owner_grant_table')
            || !function_exists('impactshop_identity_owner_pseudo_hash')) { return null; }
        global $wpdb;
        return self::row($wpdb->prepare('SELECT * FROM ' . impactshop_identity_owner_grant_table()
            . " WHERE grant_hash=%s AND pseudo_hash=%s AND state='active' AND revoked_at IS NULL AND expires_at>%s"
            . ($lock ? ' FOR UPDATE' : ''), $hash, impactshop_identity_owner_pseudo_hash($mapping['pseudo']), gmdate('Y-m-d H:i:s')));
    }

    private static function sessionEarning(array $mapping): bool
    {
        $rewardGrant = self::grant($mapping['subject']);
        return $rewardGrant !== null && self::earning($rewardGrant);
    }

    /** No DDL or options writes inside this transaction; all auth writers share this lock. */
    private static function sessionTransaction(string $subject, callable $operation): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $subject)) { throw new RuntimeException('invalid_session'); }
        $result = self::locked(hash('sha256', 'session:' . $subject), static function ($assert) use ($operation): array {
            global $wpdb;
            $assert();
            if ((string) self::scalar('SELECT @@in_transaction') !== '0') { throw new RuntimeException('session_transaction_active'); }
            foreach (['grants', 'codes', 'sessions'] as $name) {
                if (strtoupper((string) self::scalar($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table($name)))) !== 'INNODB') {
                    throw new RuntimeException('session_storage_unavailable');
                }
            }
            $assert(); self::query('START TRANSACTION');
            try {
                $value = $operation($assert); $assert(); self::query('COMMIT'); $assert();
                return $value;
            } catch (Throwable $error) {
                try { $assert(); $wpdb->query('ROLLBACK'); } catch (Throwable $ignored) { /* readback required */ }
                throw $error;
            }
        });
        if (($result['state'] ?? '') === 'busy') { throw new RuntimeException('session_operation_busy'); }
        return $result;
    }

    public static function sessionsStatus(): array
    {
        global $wpdb;
        $schema = self::sessionStructure(true); $structure = [];
        foreach (['grants', 'codes', 'sessions'] as $name) {
            $structure[$name] = ['engine' => self::scalar($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table($name))),
                'columns' => self::check($wpdb->get_results('SHOW COLUMNS FROM ' . self::table($name), ARRAY_A)),
                'indexes' => self::check($wpdb->get_results('SHOW INDEX FROM ' . self::table($name), ARRAY_A))];
        }
        return ['schema' => $schema['state'], 'structureHash' => hash('sha256', json_encode($structure, JSON_THROW_ON_ERROR)),
            'targetHash' => hash('sha256', (string) self::scalar('SELECT @@hostname') . ':' . (string) self::scalar('SELECT @@port') . ':' . (string) self::scalar('SELECT DATABASE()') . ':' . $wpdb->prefix),
            'sourceHash' => hash_file('sha256', __FILE__), 'mode' => self::config('SESSION_MODE', 'legacy'),
            'ttlSeconds' => self::config('SESSION_TTL_SECONDS', 900),
            'sessions' => (int) self::scalar('SELECT COUNT(*) FROM ' . self::table('sessions')),
            'codes' => (int) self::scalar('SELECT COUNT(*) FROM ' . self::table('codes')),
            'unboundSessions' => $schema['state'] === 'ready' ? (int) self::scalar('SELECT COUNT(*) FROM ' . self::table('sessions') . ' WHERE owner_grant_hash IS NULL OR issued_at IS NULL') : null];
    }

    /** Explicit CLI only, with bridge disabled and independently drained at the hosting layer. */
    public static function upgradeSessions(array $expected): array
    {
        self::operatorOnly();
        if (self::enabled()) { throw new RuntimeException('session_writes_must_be_disabled'); }
        return self::locked(hash('sha256', 'session-schema-v2'), static function ($assert) use ($expected): array {
            global $wpdb;
            $status = self::sessionsStatus();
            foreach (['targetHash', 'structureHash', 'sourceHash'] as $field) {
                if (!is_string($expected[$field] ?? null) || !hash_equals($status[$field], $expected[$field])) { throw new RuntimeException('session_upgrade_conflict'); }
            }
            if ((string) self::scalar('SELECT @@in_transaction') !== '0') { throw new RuntimeException('session_transaction_active'); }
            foreach (['grants', 'codes', 'sessions'] as $name) {
                if (strtoupper((string) self::scalar($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table($name)))) !== 'INNODB') { throw new RuntimeException('session_storage_unavailable'); }
            }
            $schema = self::sessionStructure();
            foreach (['codes' => ['owner_grant_hash' => 'char(64) NULL'], 'sessions' => ['owner_grant_hash' => 'char(64) NULL', 'issued_at' => 'bigint NULL']] as $name => $columns) {
                foreach ($columns as $column => $definition) {
                    if (!isset($schema['columns'][$name][$column])) {
                        $assert(); self::query('ALTER TABLE ' . self::table($name) . ' ADD COLUMN ' . $column . ' ' . $definition); $assert();
                    }
                }
            }
            if (self::sessionStructure(true)['state'] !== 'ready') { throw new RuntimeException('session_schema_unavailable'); }
            $assert(); self::query('START TRANSACTION');
            try {
                // Fixed original grant/generation, before any new handoff may overwrite the mapping.
                self::check($wpdb->get_results('SELECT subject FROM ' . self::table('grants') . ' ORDER BY subject FOR UPDATE', ARRAY_A));
                foreach (['codes', 'sessions'] as $name) {
                    $assert(); self::query('UPDATE ' . self::table($name) . ' s INNER JOIN ' . self::table('grants')
                        . ' g ON g.subject=s.subject AND g.generation=s.generation AND g.revoked=0 SET s.owner_grant_hash=g.grant_hash'
                        . ($name === 'sessions' ? ',s.issued_at=s.expires-900' : '')
                        . ' WHERE s.owner_grant_hash IS NULL' . ($name === 'sessions' ? ' AND s.issued_at IS NULL' : ''));
                }
                $assert(); self::query('COMMIT'); $assert();
            } catch (Throwable $error) {
                try { $assert(); $wpdb->query('ROLLBACK'); } catch (Throwable $ignored) { /* readback required */ }
                throw $error;
            }
            return self::sessionsStatus();
        });
    }

    public static function revokeIssuedSessions(array $selection): array
    {
        self::operatorOnly();
        if (self::enabled() || array_keys($selection) !== ['sessions', 'codes']) { throw new RuntimeException('session_writes_must_be_disabled'); }
        foreach ($selection as $hashes) {
            if (!is_array($hashes) || !array_is_list($hashes) || count($hashes) > 10000 || count(array_unique($hashes, SORT_REGULAR)) !== count($hashes)) { throw new RuntimeException('invalid_session_selection'); }
            foreach ($hashes as $hash) { if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) { throw new RuntimeException('invalid_session_selection'); } }
        }
        return self::locked(hash('sha256', 'session-schema-v2'), static function ($assert) use ($selection): array {
            global $wpdb;
            if ((string) self::scalar('SELECT @@in_transaction') !== '0') { throw new RuntimeException('session_transaction_active'); }
            foreach (['sessions', 'codes'] as $name) {
                if (strtoupper((string) self::scalar($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table($name)))) !== 'INNODB') { throw new RuntimeException('session_storage_unavailable'); }
            }
            $assert(); self::query('START TRANSACTION'); $counts = [];
            try {
                foreach ($selection as $name => $hashes) {
                    sort($hashes); $counts[$name] = 0;
                    foreach ($hashes as $hash) { $assert(); $counts[$name] += self::query($wpdb->prepare('DELETE FROM ' . self::table($name) . ' WHERE token_hash=%s', $hash)); }
                }
                $assert(); self::query('COMMIT'); $assert();
            } catch (Throwable $error) {
                try { $assert(); $wpdb->query('ROLLBACK'); } catch (Throwable $ignored) { /* readback required */ }
                throw $error;
            }
            return ['deleted' => $counts];
        });
    }

    public static function register(): void
    {
        if (!self::enabled()) {
            return;
        }
        register_rest_route('jysk-game/v1', '/handoff', [
            'methods' => 'GET', 'permission_callback' => '__return_true',
            'callback' => [self::class, 'handoff'],
        ]);
        foreach (['exchange', 'context', 'revoke', 'disconnect', 'award', 'reward-status', 'messages', 'messages/delivered', 'results'] as $path) {
            register_rest_route('jysk-game/v1', '/' . $path, [
                'methods' => 'POST', 'permission_callback' => '__return_true',
                'callback' => [self::class, 'service'],
            ]);
        }
    }

    public static function signature(string $timestamp, string $nonce, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $path . "\n" . hash('sha256', $body), self::key('SERVICE_KEY'));
    }

    private static function authenticate($request): string
    {
        $body = $request->get_body();
        $ts = (string) $request->get_header('x-jysk-timestamp');
        $nonce = (string) $request->get_header('x-jysk-nonce');
        $sig = (string) $request->get_header('x-jysk-signature');
        if (strlen($body) > 65536 || !preg_match('/^[0-9]{10}$/D', $ts)
            || abs(time() - (int) $ts) > 60 || !preg_match('/^[a-f0-9]{32}$/D', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/D', $sig)) {
            throw new RuntimeException('unauthorized');
        }
        $expected = self::signature($ts, $nonce, 'POST', '/wp-json' . $request->get_route(), $body);
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('unauthorized');
        }
        global $wpdb;
        if (self::query($wpdb->prepare('INSERT IGNORE INTO ' . self::table('nonces') . ' (token_hash,expires) VALUES (%s,%d)', hash('sha256', $nonce), time() + 120)) !== 1) {
            throw new RuntimeException('replay');
        }
        // Request-driven bounded cleanup; no new scheduler and no live bearer values stored.
        self::query($wpdb->prepare('DELETE FROM ' . self::table('nonces') . ' WHERE expires < %d LIMIT 100', time()));
        return $nonce;
    }

    private static function response($request, array $data, int $status, string $nonce = '')
    {
        $bytes = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($bytes) || strlen($bytes) > 65536) {
            $bytes = '{"error":"response_too_large"}';
            $data = ['error' => 'response_too_large'];
            $status = 503;
        }
        $headers = ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache'];
        if ($nonce !== '') {
            $headers['x-jysk-signature'] = hash_hmac('sha256', "response\n{$nonce}\n{$status}\n" . hash('sha256', $bytes), self::key('SERVICE_KEY'));
        }
        $headers['Content-Type'] = 'application/json; charset=UTF-8';
        self::$wire[spl_object_hash($request)] = [$bytes, $status, $headers];
        return new WP_REST_Response($data, $status, $headers);
    }

    /** Only discard bounded whitespace in plain PHP buffers; never unwind a handler. */
    private static function cleanWirePrefix(): bool
    {
        if (headers_sent()) {
            return false;
        }
        $buffers = ob_get_status(true);
        foreach ($buffers as $index => $buffer) {
            if ($buffer['name'] !== 'default output handler' || $buffer['type'] !== 0
                || ($buffer['flags'] & PHP_OUTPUT_HANDLER_CLEANABLE) === 0
                || ($index < count($buffers) - 1 && $buffer['buffer_used'] !== 0)) {
                return false;
            }
        }
        if ($buffers === []) {
            return true;
        }
        $prefix = ob_get_contents();
        if (!is_string($prefix) || strlen($prefix) > 256 || !preg_match('/\A[ \t\r\n]*\z/D', $prefix)) {
            return false;
        }
        return $prefix === '' || ob_clean();
    }

    /** Sign and serve the same bytes, independent of WordPress JSON encoding filters. */
    public static function serve($served, $result, $request, $server)
    {
        if (!str_starts_with($request->get_route(), '/jysk-game/v1/')) {
            return $served;
        }
        $key = spl_object_hash($request);
        if (!isset(self::$wire[$key])) {
            return $served;
        }
        [$bytes, $status, $headers] = self::$wire[$key];
        unset(self::$wire[$key]);
        if (!self::cleanWirePrefix()) {
            // A late output failure is not an authenticated success or an award retry.
            if (!headers_sent()) {
                status_header(503);
                $server->send_header('x-jysk-signature', '');
            }
            // Flushed headers cannot be retracted; these bytes invalidate the wire MAC.
            echo '{"error":"bridge_unavailable"}';
            return true;
        }
        status_header($status);
        foreach ($headers as $name => $value) {
            $server->send_header($name, $value);
        }
        echo $bytes;
        return true;
    }

    public static function handoff($request)
    {
        try {
            if (!self::enabled() || $request->get_param('client_id') !== self::CLIENT
                || $request->get_param('redirect_uri') !== self::CALLBACK
                || $request->get_param('code_challenge_method') !== 'S256'
                || !preg_match('/^[A-Za-z0-9_-]{43,128}$/D', (string) $request->get_param('state'))
                || !preg_match('/^[A-Za-z0-9_-]{43}$/D', (string) $request->get_param('code_challenge'))) {
                throw new RuntimeException('invalid_handoff');
            }
            if (!function_exists('impactshop_identity_owner_authorized') || !function_exists('impactshop_identity_profile_cookie')) {
                throw new RuntimeException('identity_unavailable');
            }
            $pseudo = impactshop_identity_profile_cookie();
            if ($pseudo === '' || !impactshop_identity_owner_authorized($pseudo)) {
                // Start a fresh PKCE round after login; never keep the old two-minute code alive.
                return new WP_REST_Response(null, 303, ['Location' => self::PROFILE, 'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
            }
            $subject = hash_hmac('sha256', self::CLIENT . ':' . $pseudo, self::key('SUBJECT_KEY'));
            $grant = impactshop_identity_owner_hash(impactshop_identity_owner_cookie());
            $code = bin2hex(random_bytes(32));
            $settings = self::sessionSettings();
            self::sessionTransaction($subject, static function ($assert) use ($subject, $pseudo, $grant, $code, $request, $settings): array {
                global $wpdb;
                if (!self::ownerGrant(['pseudo' => $pseudo], $grant, true)) { throw new RuntimeException('invalid_session'); }
                $assert();
                $prior = self::row($wpdb->prepare('SELECT * FROM ' . self::table('grants') . ' WHERE subject=%s FOR UPDATE', $subject));
                $generation = $settings['perDevice'] && $prior && (int) $prior['revoked'] === 0 ? $prior['generation'] : bin2hex(random_bytes(32));
                self::query($wpdb->prepare('INSERT INTO ' . self::table('grants') . ' (subject,pseudo,grant_hash,generation,revoked) VALUES (%s,%s,%s,%s,0) ON DUPLICATE KEY UPDATE grant_hash=VALUES(grant_hash),generation=VALUES(generation),revoked=0', $subject, $pseudo, $grant, $generation));
                foreach (['sessions', 'codes'] as $name) {
                    self::query($wpdb->prepare('DELETE FROM ' . self::table($name) . ' WHERE subject=%s' . ($settings['perDevice'] ? ' AND expires<=%d' : ''), ...($settings['perDevice'] ? [$subject, time()] : [$subject])));
                }
                if ($settings['bound']) {
                    self::query($wpdb->prepare('INSERT INTO ' . self::table('codes') . ' (token_hash,subject,generation,challenge,expires,owner_grant_hash) VALUES (%s,%s,%s,%s,%d,%s)', hash('sha256', $code), $subject, $generation, $request->get_param('code_challenge'), time() + 120, $grant));
                } else {
                    self::query($wpdb->prepare('INSERT INTO ' . self::table('codes') . ' (token_hash,subject,generation,challenge,expires) VALUES (%s,%s,%s,%s,%d)', hash('sha256', $code), $subject, $generation, $request->get_param('code_challenge'), time() + 120));
                }
                return ['created' => true];
            });
            return new WP_REST_Response(null, 302, ['Location' => self::CALLBACK . '?code=' . $code . '&state=' . rawurlencode($request->get_param('state')), 'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
        } catch (Throwable $error) {
            return self::response($request, ['error' => 'handoff_unavailable'], 403);
        }
    }

    /** A navigation marker only: ownership is still independently required by handoff. */
    public static function profileReturnTarget(string $host, string $method, string $uri): ?string
    {
        if (!self::enabled() || $host !== 'app.sharity.hu' || $method !== 'GET'
            || parse_url($uri, PHP_URL_PATH) !== '/profil/') {
            return null;
        }
        $fields = [];
        foreach (explode('&', (string) parse_url($uri, PHP_URL_QUERY)) as $pair) {
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            if (!in_array($key, ['jysk_return', 'bridge_target', 'impactshop_profile_return_complete'], true)
                || array_key_exists($key, $fields)) {
                return null;
            }
            $fields[$key] = urldecode($parts[1] ?? '');
        }
        if (($fields['jysk_return'] ?? '') !== '1'
            || isset($fields['bridge_target']) && !in_array($fields['bridge_target'], ['account', 'restore'], true)
            || isset($fields['impactshop_profile_return_complete']) && $fields['impactshop_profile_return_complete'] !== '1'
            || !function_exists('impactshop_identity_profile_cookie')
            || !function_exists('impactshop_identity_owner_authorized')) {
            return null;
        }
        $pseudo = impactshop_identity_profile_cookie();
        if ($pseudo !== '' && ($fields['impactshop_profile_return_complete'] ?? '') === '1'
            && function_exists('impactshop_identity_profile_state')) {
            // Native restore issues a pending grant. This subsequent GET must
            // perform the same cookie-bound activation as the profile page.
            impactshop_identity_profile_state($pseudo);
        }
        return $pseudo !== '' && impactshop_identity_owner_authorized($pseudo) ? self::LOGIN_START : null;
    }

    public static function completeProfileReturn(): void
    {
        $target = self::profileReturnTarget((string) ($_SERVER['HTTP_HOST'] ?? ''),
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''), (string) ($_SERVER['REQUEST_URI'] ?? ''));
        if ($target !== null) {
            nocache_headers();
            wp_redirect($target, 303);
            exit;
        }
    }

    private static function grant(string $subject): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $subject) || !function_exists('impactshop_identity_owner_grant_table') || !function_exists('impactshop_identity_owner_pseudo_hash')) {
            return null;
        }
        global $wpdb;
        return self::row($wpdb->prepare('SELECT g.* FROM ' . self::table('grants') . ' g INNER JOIN ' . impactshop_identity_owner_grant_table() . " o ON o.grant_hash=g.grant_hash WHERE g.subject=%s AND g.revoked=0 AND o.pseudo_hash= %s AND o.state='active' AND o.revoked_at IS NULL AND o.expires_at > %s", $subject, self::pseudoHash($subject), gmdate('Y-m-d H:i:s')));
    }

    private static function pseudoHash(string $subject): string
    {
        global $wpdb;
        $row = self::row($wpdb->prepare('SELECT pseudo FROM ' . self::table('grants') . ' WHERE subject=%s', $subject));
        return $row ? impactshop_identity_owner_pseudo_hash($row['pseudo']) : '';
    }

    private static function transactionReady(): bool
    {
        if (self::config('TRANSACTION_READY', false) !== true) {
            return false;
        }
        global $wpdb;
        // A routing drop-in needs a separate reviewed writer-proof adapter. It
        // cannot silently pass a replica read off as authoritative absence.
        if (get_class($wpdb) !== 'wpdb' || !defined('WP_CONTENT_DIR') || is_file(WP_CONTENT_DIR . '/db.php')
            || (string) self::scalar('SELECT @@read_only') !== '0') {
            return false;
        }
        $tables = [self::table('rewards'), $wpdb->prefix . 'point_transactions', $wpdb->prefix . 'user_points', $wpdb->prefix . 'level_history'];
        foreach ($tables as $table) {
            $engine = self::scalar($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table));
            if (strtoupper((string) $engine) !== 'INNODB') {
                return false;
            }
        }
        foreach ([$tables[0] => ['event_id'], $tables[1] => ['pseudo_id', 'dedupe_key'], $tables[2] => ['pseudo_id']] as $table => $columns) {
            $rows = self::check($wpdb->get_results('SHOW INDEX FROM ' . $table, ARRAY_A));
            $unique = [];
            foreach ($rows as $row) {
                if ((int) $row['Non_unique'] === 0) {
                    $unique[$row['Key_name']][] = $row;
                }
            }
            $found = false;
            foreach ($unique as $parts) {
                usort($parts, static fn (array $a, array $b): int => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']);
                if (array_column($parts, 'Column_name') === $columns && array_filter($parts, static fn (array $part): bool => $part['Sub_part'] !== null) === []) {
                    $found = true;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    private static function earning(array $grant): bool
    {
        return self::rewardUnavailableReason() === null
            && apply_filters('sharity_can_earn_points', true, $grant['pseudo'], 'bonus');
    }

    private static function rewardUnavailableReason(): ?string
    {
        if (self::config('REWARDS_ENABLED', false) !== true) {
            return 'rewards_disabled';
        }
        if (!self::transactionReady()) {
            return 'writer_not_ready';
        }
        return class_exists('Sharity_Points_Manager') && method_exists('Sharity_Points_Manager', 'award_points_for_pseudo')
            && class_exists('Sharity_Level_Manager') ? null : 'reward_manager_unavailable';
    }

    private static function session(array $body): array
    {
        global $wpdb;
        $subject = (string) ($body['subject'] ?? '');
        $token = (string) ($body['sessionToken'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !preg_match('/^[a-f0-9]{64}$/D', $subject)) {
            throw new RuntimeException('invalid_session');
        }
        $row = self::row($wpdb->prepare('SELECT * FROM ' . self::table('sessions') . ' WHERE token_hash=%s AND subject=%s AND expires>%d', hash('sha256', $token), $subject, time()));
        if (!$row) { throw new RuntimeException('invalid_session'); }
        $settings = self::sessionSettings();
        $grant = $settings['bound'] ? self::row($wpdb->prepare('SELECT * FROM ' . self::table('grants') . ' WHERE subject=%s AND revoked=0', $subject)) : self::grant($subject);
        if (!$row || !$grant || !hash_equals($grant['generation'], $row['generation'])
            || $settings['bound'] && (!self::ownerGrant($grant, (string) ($row['owner_grant_hash'] ?? ''))
                || !isset($row['issued_at']) || (int) $row['issued_at'] > time()
                || (int) $row['expires'] - (int) $row['issued_at'] > 31536000)) {
            throw new RuntimeException('invalid_session');
        }
        return ['grant' => $grant, 'expires' => (int) $row['expires'], 'owner_grant_hash' => $settings['bound'] ? $row['owner_grant_hash'] : ($grant['grant_hash'] ?? '')];
    }

    /** Normal disconnect invalidates browser access without cancelling accepted awards. */
    private static function endSession(array $body, bool $revoke): array
    {
        $keys = array_keys($body);
        sort($keys);
        if ($keys !== ['sessionToken', 'subject'] || !is_string($body['subject']) || !is_string($body['sessionToken'])
            || !preg_match('/^[a-f0-9]{64}$/D', $body['subject']) || !preg_match('/^[a-f0-9]{64}$/D', $body['sessionToken'])) {
            throw new RuntimeException('invalid_session');
        }
        $before = self::session($body);
        $settings = self::sessionSettings();
        return self::sessionTransaction($body['subject'], static function ($assert) use ($body, $before, $revoke, $settings): array {
            global $wpdb;
            if (!self::ownerGrant($before['grant'], $before['owner_grant_hash'], true)) { throw new RuntimeException('invalid_session'); }
            $assert();
                $grant = self::row($wpdb->prepare('SELECT * FROM ' . self::table('grants') . ' WHERE subject=%s FOR UPDATE', $body['subject']));
                $assert();
                if (!$grant || !hash_equals($before['grant']['generation'], $grant['generation'])) {
                    throw new RuntimeException('invalid_session');
                }
                // The session and central owner must still be valid after acquiring the row lock.
                self::session($body);
                $assert();
                if (!$revoke && $settings['perDevice']) {
                    if (self::query($wpdb->prepare('DELETE FROM ' . self::table('sessions') . ' WHERE token_hash=%s AND subject=%s', hash('sha256', $body['sessionToken']), $body['subject'])) !== 1) { throw new RuntimeException('invalid_session'); }
                    return ['disconnected' => true];
                }
                $generation = bin2hex(random_bytes(32));
                $sql = 'UPDATE ' . self::table('grants') . ' SET generation=%s' . ($revoke ? ',revoked=1' : '') . ' WHERE subject=%s AND generation=%s';
                if (self::query($wpdb->prepare($sql, $generation, $body['subject'], $grant['generation'])) !== 1) {
                    throw new RuntimeException('session_generation_changed');
                }
                $assert();
                self::query($wpdb->prepare('DELETE FROM ' . self::table('sessions') . ' WHERE subject=%s', $body['subject']));
                $assert();
                self::query($wpdb->prepare('DELETE FROM ' . self::table('codes') . ' WHERE subject=%s', $body['subject']));
                $assert();
            return $revoke ? ['revoked' => true] : ['disconnected' => true];
        });
    }

    private static function exchange(array $body): array
    {
        if (($body['clientId'] ?? '') !== self::CLIENT || ($body['redirectUri'] ?? '') !== self::CALLBACK
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($body['code'] ?? ''))
            || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', (string) ($body['codeVerifier'] ?? ''))) {
            throw new RuntimeException('invalid_exchange');
        }
        global $wpdb;
        $hash = hash('sha256', $body['code']);
        $row = self::row($wpdb->prepare('SELECT * FROM ' . self::table('codes') . ' WHERE token_hash=%s AND expires>%d', $hash, time()));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $body['codeVerifier'], true)), '+/', '-_'), '=');
        if (!$row) { throw new RuntimeException('invalid_exchange'); }
        $settings = self::sessionSettings();
        return self::sessionTransaction($row['subject'], static function ($assert) use ($row, $hash, $challenge, $settings): array {
            global $wpdb;
            $mapping = self::row($wpdb->prepare('SELECT * FROM ' . self::table('grants') . ' WHERE subject=%s', $row['subject']));
            $owner = $mapping ? self::ownerGrant($mapping, (string) ($settings['bound'] ? ($row['owner_grant_hash'] ?? '') : $mapping['grant_hash']), true) : null;
            $assert();
            $grant = self::row($wpdb->prepare('SELECT * FROM ' . self::table('grants') . ' WHERE subject=%s FOR UPDATE', $row['subject']));
            $currentCode = self::row($wpdb->prepare('SELECT * FROM ' . self::table('codes') . ' WHERE token_hash=%s AND expires>%d FOR UPDATE', $hash, time()));
            if (!$owner || !$grant || (int) $grant['revoked'] !== 0 || !$currentCode || $currentCode !== $row
                || !hash_equals($row['challenge'], $challenge) || !hash_equals($grant['generation'], $row['generation'])) { throw new RuntimeException('invalid_exchange'); }
            if (self::query($wpdb->prepare('DELETE FROM ' . self::table('codes') . ' WHERE token_hash=%s AND expires>%d', $hash, time())) !== 1) { throw new RuntimeException('invalid_exchange'); }
            $token = bin2hex(random_bytes(32)); $issued = time();
            $expires = min($issued + $settings['ttl'], (new DateTimeImmutable($owner['expires_at'], new DateTimeZone('UTC')))->getTimestamp());
            if ($expires <= $issued) { throw new RuntimeException('invalid_exchange'); }
            if ($settings['bound']) {
                self::query($wpdb->prepare('INSERT INTO ' . self::table('sessions') . ' (token_hash,subject,generation,expires,owner_grant_hash,issued_at) VALUES (%s,%s,%s,%d,%s,%d)', hash('sha256', $token), $row['subject'], $row['generation'], $expires, $row['owner_grant_hash'], $issued));
            } else {
                self::query($wpdb->prepare('INSERT INTO ' . self::table('sessions') . ' (token_hash,subject,generation,expires) VALUES (%s,%s,%s,%d)', hash('sha256', $token), $row['subject'], $row['generation'], $expires));
            }
            return ['subject' => $row['subject'], 'sessionToken' => $token, 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expires), 'earningAllowed' => self::sessionEarning($grant)];
        });
    }

    public static function payload(array $body): array
    {
        $fields = ['eventId', 'subject', 'kind', 'dayIndex', 'slot', 'ngoId', 'points', 'occurredAt', 'policyHash', 'producerSha', 'isTest'];
        $keys = array_keys($body);
        sort($keys);
        $expected = $fields;
        sort($expected);
        if ($keys !== $expected || !is_int($body['dayIndex']) || !is_int($body['slot']) || !is_int($body['points']) || !is_bool($body['isTest'])
            || !is_string($body['subject']) || !preg_match('/^[a-f0-9]{64}$/D', $body['subject'])
            || !is_string($body['eventId']) || !preg_match('/^[a-f0-9]{64}$/D', $body['eventId'])
            || !in_array($body['kind'], ['quiz_answered', 'video_completed'], true)
            || $body['dayIndex'] < 0 || $body['dayIndex'] > 20 || $body['slot'] < 0
            || $body['slot'] > ($body['kind'] === 'quiz_answered' ? 4 : 0)
            || $body['points'] !== ($body['kind'] === 'quiz_answered' ? 5 : 10)
            || !is_string($body['ngoId']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $body['ngoId'])
            || !is_string($body['policyHash']) || !preg_match('/^[a-f0-9]{64}$/D', $body['policyHash'])
            || !is_string($body['producerSha']) || !preg_match('/^[a-f0-9]{40}$/D', $body['producerSha'])
            || !is_string($body['occurredAt']) || !preg_match('/^2026-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z$/D', $body['occurredAt'])) {
            throw new RuntimeException('invalid_payload');
        }
        $eventId = hash('sha256', self::CLIENT . ':' . $body['subject'] . ':' . $body['kind'] . ':' . $body['dayIndex'] . ':' . $body['slot']);
        $at = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.v\Z', $body['occurredAt'], new DateTimeZone('UTC'));
        if (!$at || $at->format('Y-m-d\TH:i:s.v\Z') !== $body['occurredAt']) {
            throw new RuntimeException('invalid_payload');
        }
        $local = $at->setTimezone(new DateTimeZone('Europe/Budapest'));
        $day = (int) $local->format('j') - 9;
        if (!hash_equals($eventId, $body['eventId']) || $day !== $body['dayIndex']
            || $local < new DateTimeImmutable('2026-11-09 12:00:00', new DateTimeZone('Europe/Budapest'))
            || $local >= new DateTimeImmutable('2026-11-30 00:00:00', new DateTimeZone('Europe/Budapest'))
            || $at->getTimestamp() > time() + 60) {
            throw new RuntimeException('invalid_payload');
        }
        $ordered = [];
        foreach ($fields as $field) {
            $ordered[$field] = $body[$field];
        }
        return $ordered;
    }

    public static function payloadHash(array $body): string
    {
        return hash('sha256', wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Locks and connection identity cover every DB operation in the critical section. */
    public static function locked(string $eventId, callable $operation): array
    {
        global $wpdb;
        if (!property_exists($wpdb, 'reconnect_retries')) {
            throw new RuntimeException('connection_guard_unavailable');
        }
        $previous = $wpdb->reconnect_retries;
        $wpdb->reconnect_retries = 0;
        if ($wpdb->reconnect_retries !== 0) {
            $wpdb->reconnect_retries = $previous;
            throw new RuntimeException('connection_guard_unavailable');
        }
        $name = 'jysk:' . substr($eventId, 0, 58);
        $owned = false;
        try {
            $connection = (string) self::scalar('SELECT CONNECTION_ID()');
            $owned = (string) self::scalar($wpdb->prepare('SELECT GET_LOCK(%s,0)', $name)) === '1';
            if (!$owned) {
                return ['state' => 'busy'];
            }
            $assert = static function () use ($wpdb, $connection, $name): void {
                if ($connection === '' || (string) self::scalar('SELECT CONNECTION_ID()') !== $connection
                    || (string) self::scalar($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $name)) !== $connection) {
                    throw new RuntimeException('connection_changed');
                }
            };
            $assert();
            $result = $operation($assert);
            $assert();
            return $result;
        } finally {
            try {
                if ($owned) {
                    $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
                }
            } finally {
                $wpdb->reconnect_retries = $previous;
            }
        }
    }

    private static function receipt(array $payload, array $grant): ?array
    {
        global $wpdb;
        $row = self::row($wpdb->prepare("SELECT pseudo_id,points,type,source_id,source_type,metadata FROM {$wpdb->prefix}point_transactions WHERE dedupe_key=%s", 'jysk_2026:' . $payload['eventId']));
        if (!$row) {
            return null;
        }
        $meta = json_decode((string) $row['metadata'], true);
        if ($row['pseudo_id'] !== $grant['pseudo'] || (int) $row['points'] !== $payload['points'] || $row['type'] !== 'bonus'
            || $row['source_type'] !== 'jysk_campaign' || $row['source_id'] !== $payload['eventId']
            || !is_array($meta) || ($meta['jysk_payload_hash'] ?? '') !== self::payloadHash($payload)) {
            return ['state' => 'conflict'];
        }
        return ['state' => 'confirmed'];
    }

    private static function reward(array $body, bool $write): array
    {
        global $wpdb;
        $payload = self::payload($body);
        $hash = self::payloadHash($payload);
        $base = ['eventId' => $payload['eventId'], 'payloadHash' => $hash, 'points' => $payload['points']];
        $mapping = self::row($wpdb->prepare('SELECT * FROM ' . self::table('grants') . ' WHERE subject=%s', $payload['subject']));
        if (!$mapping) {
            return $base + self::rewardDecision($payload, 'policy_blocked', 'grant_mapping_missing');
        }
        // All technical events share one subject lock: their authorization is
        // 35 real points in total, not 35 points on every campaign date.
        $lockId = $payload['isTest'] ? hash('sha256', 'technical:' . $payload['subject']) : $payload['eventId'];
        $result = self::locked($lockId, static function ($assert) use ($wpdb, $payload, $hash, $mapping, $write): array {
            $claim = self::row($wpdb->prepare('SELECT * FROM ' . self::table('rewards') . ' WHERE event_id=%s', $payload['eventId']));
            if ($claim && (!hash_equals($claim['payload_hash'], $hash) || $claim['payload'] !== wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                return ['state' => 'conflict'];
            }
            // Reconciliation of an existing receipt remains valid after grant expiry/revoke.
            $receipt = self::receipt($payload, $mapping);
            if ($receipt) {
                // Existing unsafe ledger history cannot manufacture verified success.
                return self::transactionReady() ? $receipt : ['state' => 'unknown'];
            }
            if (!$write) {
                if (!self::transactionReady()) {
                    return self::rewardDecision($payload, 'unknown', 'writer_not_ready');
                }
                if ($claim) {
                    $assert();
                    self::query($wpdb->prepare('UPDATE ' . self::table('rewards') . " SET state='absent' WHERE event_id=%s AND payload_hash=%s", $payload['eventId'], $hash));
                    $assert();
                }
                return ['state' => 'absent'];
            }
            $grant = self::grant($payload['subject']);
            if (!$grant) {
                return self::rewardDecision($payload, 'policy_blocked', 'grant_inactive');
            }
            // Request timestamp tolerance never authorizes an award before the
            // actual server-clock campaign start. Late outbox drain stays valid.
            if (time() < (new DateTimeImmutable('2026-11-09T11:00:00Z'))->getTimestamp()) {
                return self::rewardDecision($payload, 'policy_blocked', 'campaign_not_started');
            }
            $tests = self::config('TEST_SUBJECTS', []);
            $technical = is_array($tests) && in_array($payload['subject'], $tests, true);
            $testAllowed = $payload['isTest'] ? ($technical && count($tests) === 1) : !$technical;
            foreach (['test_membership' => !$testAllowed, 'policy_mismatch' => $payload['policyHash'] !== self::config('POLICY_HASH', ''),
                'producer_not_allowed' => !in_array($payload['producerSha'], self::config('PRODUCER_SHAS', []), true),
                'ngo_not_allowed' => !in_array($payload['ngoId'], self::config('NGO_IDS', []), true)] as $reason => $blocked) {
                if ($blocked) {
                    return self::rewardDecision($payload, 'policy_blocked', $reason);
                }
            }
            $unavailable = self::rewardUnavailableReason();
            if ($unavailable !== null) {
                return self::rewardDecision($payload, 'unknown', $unavailable);
            }
            if (!apply_filters('sharity_can_earn_points', true, $grant['pseudo'], 'bonus')) {
                return self::rewardDecision($payload, 'policy_blocked', 'earning_policy');
            }
            if ($payload['isTest']) {
                $used = self::scalar($wpdb->prepare("SELECT COALESCE(SUM(GREATEST(points,0)),0) FROM {$wpdb->prefix}point_transactions WHERE pseudo_id=%s AND source_type='jysk_campaign' AND LEFT(dedupe_key,10)=%s", $grant['pseudo'], 'jysk_2026:'));
                $assert();
                if (!is_numeric($used) || (int) $used < 0) {
                    return self::rewardDecision($payload, 'unknown', 'technical_cap_unavailable');
                }
                if ((int) $used + $payload['points'] > 35) {
                    return self::rewardDecision($payload, 'policy_blocked', 'technical_cap_exhausted');
                }
            }
            // Never replay an ambiguous prior call. Status/reconciliation resolves it.
            if ($claim && $claim['state'] !== 'absent') {
                return self::rewardDecision($payload, 'unknown', 'prior_award_uncertain');
            }
            $assert();
            self::query('START TRANSACTION');
            try {
                $claimSql = $claim
                    ? $wpdb->prepare('UPDATE ' . self::table('rewards') . " SET state='accepted' WHERE event_id=%s AND payload_hash=%s AND state='absent'", $payload['eventId'], $hash)
                    : $wpdb->prepare('INSERT INTO ' . self::table('rewards') . ' (event_id,payload_hash,payload,state,points) VALUES (%s,%s,%s,%s,%d)', $payload['eventId'], $hash, wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'accepted', $payload['points']);
                if (self::query($claimSql) !== 1) {
                    throw new RuntimeException('claim_failed');
                }
                $assert();
                self::query('COMMIT'); // Durable claim precedes the manager-owned transaction.
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');
                throw $error;
            }
            $assert();
            $manager = new Sharity_Points_Manager();
            $answer = $manager->award_points_for_pseudo($grant['pseudo'], $payload['points'], 'bonus', $payload['eventId'], [
                'source_type' => 'jysk_campaign', 'jysk_payload_hash' => $hash,
                'ngo_id' => $payload['ngoId'], 'policy_hash' => $payload['policyHash'], 'producer_sha' => $payload['producerSha'],
                'is_test' => $payload['isTest'],
            ], 'jysk_2026:' . $payload['eventId']);
            $assert();
            if (empty($answer['success'])) {
                return ($answer['error'] ?? '') === 'earning_blocked'
                    ? self::rewardDecision($payload, 'policy_blocked', 'earning_policy')
                    : self::rewardDecision($payload, 'unknown', 'writer_result_unknown');
            }
            $receipt = self::receipt($payload, $grant);
            if (!$receipt || $receipt['state'] !== 'confirmed') {
                return $receipt ?? ['state' => 'unknown'];
            }
            self::query($wpdb->prepare('UPDATE ' . self::table('rewards') . " SET state='confirmed' WHERE event_id=%s AND payload_hash=%s", $payload['eventId'], $hash));
            return ['state' => 'confirmed'];
        });
        return $base + $result;
    }

    /** Fixed decision metadata only; logging cannot turn a failed award into success. */
    private static function rewardDecision(array $payload, string $state, string $reason): array
    {
        try {
            error_log(wp_json_encode(['time' => gmdate('Y-m-d\TH:i:s\Z'), 'eventId' => $payload['eventId'], 'state' => $state, 'reason' => $reason]));
        } catch (Throwable $ignored) { /* existing PHP logging may be unavailable */ }
        return ['state' => $state];
    }

    /** No request-side migration. Old bridge deployments tolerate these additive tables. */
    public static function installResults(): void
    {
        if (!defined('WP_CLI') || WP_CLI !== true || PHP_INT_SIZE < 8) {
            throw new RuntimeException('operator_cli_only');
        }
        global $wpdb;
        $schemas = [
            'results' => "campaign varchar(32) NOT NULL, version int unsigned NOT NULL, state varchar(16) NOT NULL, manifest_hash char(64) NOT NULL, manifest longtext NOT NULL, verification_hash char(64) NULL, seed char(64) NULL, result_json longtext NULL, redraw_json text NULL, published_at datetime NULL, PRIMARY KEY(campaign)",
            'prizes' => "campaign varchar(32) NOT NULL, round_no int unsigned NOT NULL, slot_no int unsigned NOT NULL, person_key char(64) NOT NULL, subject char(64) NOT NULL, message_id bigint unsigned NOT NULL, claim_reference char(32) NOT NULL, delivered_at datetime NULL, claim_deadline datetime NULL, case_state varchar(32) NOT NULL DEFAULT 'notified', version int unsigned NOT NULL DEFAULT 1, PRIMARY KEY(campaign,round_no,slot_no), UNIQUE KEY message_id(message_id), UNIQUE KEY claim_reference(claim_reference)",
            'prize_events' => "id char(64) NOT NULL, campaign varchar(32) NOT NULL, round_no int unsigned NOT NULL, slot_no int unsigned NOT NULL, kind varchar(32) NOT NULL, occurred_at datetime NOT NULL, reference_hash char(64) NOT NULL, details text NOT NULL, PRIMARY KEY(id), KEY prize(campaign,round_no,slot_no)",
        ];
        foreach ($schemas as $name => $schema) {
            self::query('CREATE TABLE IF NOT EXISTS ' . self::table($name) . ' (' . $schema . ') ENGINE=InnoDB ' . $wpdb->get_charset_collate());
        }
    }

    private static function resultsInstalled(): bool
    {
        global $wpdb;
        return (int) self::scalar($wpdb->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table('results'))) === 1;
    }

    private static function resultsReady(): void
    {
        global $wpdb;
        if (PHP_INT_SIZE < 8 || get_class($wpdb) !== 'wpdb' || !defined('WP_CONTENT_DIR') || is_file(WP_CONTENT_DIR . '/db.php') || (string) self::scalar('SELECT @@read_only') !== '0') {
            throw new RuntimeException('results_storage_not_ready');
        }
        foreach (['results', 'prizes', 'prize_events', 'grants', 'message_map'] as $name) {
            $tables[] = self::table($name);
        }
        $tables[] = $wpdb->prefix . 'impact_vote_messages';
        $tables[] = $wpdb->prefix . 'impact_vote_message_targets';
        foreach ($tables as $table) {
            if (strtoupper((string) self::scalar($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table))) !== 'INNODB') {
                throw new RuntimeException('results_storage_not_ready');
            }
        }
        foreach (['results' => ['campaign'], 'prizes' => ['campaign', 'round_no', 'slot_no'], 'prize_events' => ['id'], 'message_map' => ['message_id', 'subject']] as $table => $expected) {
            $rows = self::check($wpdb->get_results('SHOW INDEX FROM ' . self::table($table), ARRAY_A));
            $primary = array_values(array_filter($rows, static fn ($row) => $row['Key_name'] === 'PRIMARY' && (int) $row['Non_unique'] === 0 && $row['Sub_part'] === null));
            usort($primary, static fn ($a, $b) => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']);
            if (array_column($primary, 'Column_name') !== $expected) {
                throw new RuntimeException('results_storage_not_ready');
            }
        }
    }

    private static function resultExact(array $value, array $keys): void
    {
        $actual = array_keys($value); sort($actual, SORT_STRING); sort($keys, SORT_STRING);
        if ($actual !== $keys) {
            throw new RuntimeException('invalid_results');
        }
    }

    /** Validates the operator-owned envelope, not a caller-provided result or seed. */
    public static function validateResults(array $m): array
    {
        self::resultExact($m, ['kind','campaign','sourceCommit','sourceTree','target','configVersion','rosterVersion','policyHash','termsVersion','privacyVersion','endsAt','generatedAt','datasetHash','decisionsHash','rosterHash','reviewReference','eventCount','validEventCount','globalPoints','ngos','members']);
        if ($m['kind'] !== 'jysk-game-results-v1' || $m['campaign'] !== 'jysk_2026' || $m['endsAt'] !== '2026-11-29T23:00:00.000Z'
            || !is_string($m['generatedAt']) || !preg_match('/^2026-[0-9T:.Z-]+$/D', $m['generatedAt']) || strtotime($m['generatedAt']) < strtotime($m['endsAt']) || strtotime($m['generatedAt']) > time()
            || !is_array($m['ngos']) || !array_is_list($m['ngos']) || count($m['ngos']) < 1 || count($m['ngos']) > 200
            || !is_array($m['members']) || !array_is_list($m['members']) || count($m['members']) > 50000
            || $m['policyHash'] !== self::config('POLICY_HASH', '')) {
            throw new RuntimeException('invalid_results');
        }
        foreach (['datasetHash','decisionsHash','rosterHash','reviewReference','policyHash','target'] as $key) {
            if (!is_string($m[$key]) || !preg_match('/^[a-f0-9]{64}$/D', $m[$key])) { throw new RuntimeException('invalid_results'); }
        }
        foreach (['sourceCommit','sourceTree'] as $key) {
            if (!is_string($m[$key]) || !preg_match('/^[a-f0-9]{40}$/D', $m[$key])) { throw new RuntimeException('invalid_results'); }
        }
        if (!in_array($m['sourceCommit'], self::config('PRODUCER_SHAS', []), true)) { throw new RuntimeException('invalid_results'); }
        foreach (['termsVersion','privacyVersion'] as $key) {
            if (!is_string($m[$key]) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{2,63}$/D', $m[$key])) { throw new RuntimeException('invalid_results'); }
        }
        foreach (['configVersion','rosterVersion','eventCount','validEventCount'] as $key) {
            if (!is_int($m[$key]) || $m[$key] < (in_array($key, ['configVersion','rosterVersion'], true) ? 1 : 0) || $m[$key] > 6300000) { throw new RuntimeException('invalid_results'); }
        }
        if ($m['validEventCount'] > $m['eventCount']) { throw new RuntimeException('invalid_results'); }
        self::resultExact($m['globalPoints'], ['queued','sending','unknown','delivered','rejected']);
        foreach ($m['globalPoints'] as $count) {
            if (!is_int($count) || $count < 0 || $count > $m['eventCount']) { throw new RuntimeException('invalid_results'); }
        }
        if (array_sum($m['globalPoints']) !== $m['eventCount']) { throw new RuntimeException('invalid_results'); }
        $ngoIds = []; $voteSum = 0;
        foreach ($m['ngos'] as $ngo) {
            self::resultExact($ngo, ['ngoId','displayName','votes']);
            if (!is_string($ngo['ngoId']) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{2,63}$/D', $ngo['ngoId']) || isset($ngoIds[$ngo['ngoId']])
                || !is_string($ngo['displayName']) || trim($ngo['displayName']) !== $ngo['displayName'] || preg_match('/[\x00-\x1f\x7f]/', $ngo['displayName']) || strlen($ngo['displayName']) < 2 || strlen($ngo['displayName']) > 720
                || !is_int($ngo['votes']) || $ngo['votes'] < 0 || $ngo['votes'] > 36750000 || $ngo['votes'] % 5 !== 0) { throw new RuntimeException('invalid_results'); }
            $ngoIds[$ngo['ngoId']] = true; $voteSum += $ngo['votes'];
        }
        $actualNgos = array_keys($ngoIds); $allowedNgos = self::config('NGO_IDS', []); sort($actualNgos, SORT_STRING); sort($allowedNgos, SORT_STRING);
        if ($actualNgos !== $allowedNgos || strlen(self::resultJson($m['ngos'])) > 61440 || $voteSum < 5 * $m['validEventCount'] || $voteSum > 10 * $m['validEventCount']) { throw new RuntimeException('invalid_results'); }
        $people = []; $subjects = []; $weight = 0;
        foreach ($m['members'] as $member) {
            self::resultExact($member, ['personKey','subject','subjects','weight']);
            if (!is_array($member['subjects']) || !array_is_list($member['subjects']) || count($member['subjects']) < 1 || !in_array($member['subject'], $member['subjects'], true)
                || !is_string($member['personKey']) || !preg_match('/^[a-f0-9]{64}$/D', $member['personKey']) || isset($people[$member['personKey']])
                || !is_int($member['weight']) || $member['weight'] < 5 || $member['weight'] > 735 || $member['weight'] % 5 !== 0) { throw new RuntimeException('invalid_results'); }
            $people[$member['personKey']] = true; $weight += $member['weight'];
            foreach ($member['subjects'] as $subject) {
                if (!is_string($subject) || !preg_match('/^[a-f0-9]{64}$/D', $subject) || isset($subjects[$subject]) || in_array($subject, self::config('TEST_SUBJECTS', []), true)) { throw new RuntimeException('invalid_results'); }
                $subjects[$subject] = true;
            }
        }
        if (count($subjects) > 50000 || $weight > $voteSum) { throw new RuntimeException('invalid_results'); }
        return $m;
    }

    private static function resultJson($value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Fixed v1 domain and byte ordering shared with the TypeScript test vectors. */
    public static function drawResults(array $manifest, string $manifestHash, string $seed, int $round = 0, array $excluded = [], int $slots = 3): array
    {
        if (PHP_INT_SIZE < 8 || !preg_match('/^[a-f0-9]{64}$/D', $seed) || !preg_match('/^[a-f0-9]{64}$/D', $manifestHash) || $round < 0 || $round > 1 || $slots < 1 || $slots > 3) { throw new RuntimeException('invalid_draw'); }
        $counter = 0;
        $integer = static function (int $bound, string $kind, int $slot) use ($seed, $manifestHash, $round, &$counter): int {
            if ($bound < 1 || $bound > 36750000) { throw new RuntimeException('invalid_draw_bound'); }
            $limit = intdiv(281474976710656, $bound) * $bound;
            do {
                $raw = hash_hmac('sha256', "jysk-draw-v1\n{$manifestHash}\n{$round}\n{$kind}\n{$slot}\n" . $counter++, hex2bin($seed), true);
                $sample = 0; for ($i = 0; $i < 6; $i++) { $sample = $sample * 256 + ord($raw[$i]); }
            } while ($sample >= $limit);
            return $sample % $bound;
        };
        $pool = array_values(array_filter($manifest['members'], static fn ($m) => !in_array($m['personKey'], $excluded, true)));
        usort($pool, static fn ($a, $b) => strcmp($a['personKey'], $b['personKey']));
        $winners = [];
        for ($slot = 1; $slot <= $slots && count($pool) > 0; $slot++) {
            $index = 0;
            if (count($pool) > $slots - count($winners)) {
                $draw = $integer(array_sum(array_column($pool, 'weight')), 'player', $slot);
                foreach ($pool as $index => $person) { if ($draw < $person['weight']) { break; } $draw -= $person['weight']; }
            }
            $winners[] = $pool[$index]; array_splice($pool, $index, 1);
        }
        $ngoWinner = null;
        if ($round === 0) {
            $max = max(array_column($manifest['ngos'], 'votes'));
            if ($max > 0) {
                $ties = array_values(array_filter($manifest['ngos'], static fn ($n) => $n['votes'] === $max));
                usort($ties, static fn ($a, $b) => strcmp($a['ngoId'], $b['ngoId']));
                $ngoWinner = ['ngoId' => $ties[count($ties) === 1 ? 0 : $integer(count($ties), 'ngo', 0)]['ngoId'], 'voucherHuf' => 500000];
            }
        }
        return ['winners' => $winners, 'ngoWinner' => $ngoWinner];
    }

    private static function resultTransaction(callable $apply): array
    {
        self::resultsReady();
        return self::locked(hash('sha256', 'jysk-results-2026'), static function ($assert) use ($apply): array {
            self::query('START TRANSACTION');
            try { $result = $apply($assert); $assert(); self::query('COMMIT'); return $result; }
            catch (Throwable $e) { global $wpdb; $wpdb->query('ROLLBACK'); throw $e; }
        });
    }

    /** CLI status excludes the seed, participant identities and private manifest. */
    public static function resultsStatus(): array
    {
        global $wpdb;
        if (!self::resultsInstalled()) { return ['state' => 'not-installed']; }
        $row = self::row("SELECT campaign,version,state,manifest_hash,published_at FROM " . self::table('results') . " WHERE campaign='jysk_2026'");
        return $row ? ['state' => $row['state'], 'version' => (int) $row['version'], 'manifestHash' => $row['manifest_hash'], 'publishedAt' => $row['published_at']] : ['state' => 'empty', 'version' => 0];
    }

    private static function publicResults(): array
    {
        if (!self::resultsInstalled()) { return ['state' => 'pending-review']; }
        $row = self::row("SELECT state,result_json FROM " . self::table('results') . " WHERE campaign='jysk_2026'");
        if (!$row || $row['state'] !== 'published') { return ['state' => 'pending-review']; }
        return json_decode($row['result_json'], true, 32, JSON_THROW_ON_ERROR)['public'];
    }

    private static function mappedRecipient(string $subject): array
    {
        global $wpdb;
        $grant = self::row($wpdb->prepare('SELECT subject,pseudo FROM ' . self::table('grants') . ' WHERE subject=%s', $subject));
        if (!$grant || !preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $grant['pseudo']) || !hash_equals(bin2hex(hash_hmac('sha256', 'jysk_2026:' . $grant['pseudo'], self::key('SUBJECT_KEY'), true)), $subject)) {
            throw new RuntimeException('results_recipient_unmapped');
        }
        return $grant;
    }

    public static function prepareResults(string $raw, string $expectedHash, int $expectedVersion): array
    {
        if (!defined('WP_CLI') || WP_CLI !== true || strlen($raw) > 8388608 || !hash_equals(hash('sha256', $raw), $expectedHash)) { throw new RuntimeException('invalid_results'); }
        $manifest = self::validateResults(json_decode($raw, true, 32, JSON_THROW_ON_ERROR));
        foreach ($manifest['members'] as $member) { self::mappedRecipient($member['subject']); }
        global $wpdb;
        // Includes escaped SQL overhead. No packet-size or provider-limit guessing.
        $insert = $wpdb->prepare('INSERT INTO ' . self::table('results') . " (campaign,version,state,manifest_hash,manifest) VALUES('jysk_2026',1,'prepared',%s,%s)", $expectedHash, $raw);
        if (strlen($insert) + 65536 > (int) self::scalar('SELECT @@max_allowed_packet')) { throw new RuntimeException('results_packet_limit'); }
        return self::resultTransaction(static function ($assert) use ($wpdb, $manifest, $expectedVersion, $expectedHash, $raw, $insert): array {
            $row = self::row("SELECT * FROM " . self::table('results') . " WHERE campaign='jysk_2026' FOR UPDATE");
            if (!$row) {
                if ($expectedVersion !== 0) { throw new RuntimeException('results_version_conflict'); }
                self::query($insert);
            } else {
                if ((int) $row['version'] !== $expectedVersion || $row['state'] !== 'prepared') { throw new RuntimeException('results_version_conflict'); }
                $assert();
                self::query($wpdb->prepare('UPDATE ' . self::table('results') . " SET version=version+1,manifest_hash=%s,manifest=%s WHERE campaign='jysk_2026' AND version=%d AND state='prepared'", $expectedHash, $raw, $expectedVersion));
            }
            return ['state' => 'prepared', 'version' => $expectedVersion + 1, 'manifestHash' => $expectedHash];
        });
    }

    public static function approveResults(string $hash, int $version, array $proof): array
    {
        self::operatorOnly();
        global $wpdb;
        $approved = self::resultTransaction(static function ($assert) use ($wpdb, $hash, $version, $proof): array {
            $row = self::row("SELECT * FROM " . self::table('results') . " WHERE campaign='jysk_2026' FOR UPDATE");
            if (!$row || (int) $row['version'] !== $version || $row['state'] !== 'prepared' || !hash_equals($row['manifest_hash'], $hash)
                || time() < strtotime('2026-11-29T23:00:00Z')) { throw new RuntimeException('results_version_conflict'); }
            $m = self::validateResults(json_decode($row['manifest'], true, 32, JSON_THROW_ON_ERROR));
            self::resultExact($proof, ['kind','manifestHash','datasetHash','decisionsHash','checkedAt','verified']);
            if ($proof['kind'] !== 'jysk-game-results-verification-v1' || $proof['manifestHash'] !== $hash || $proof['datasetHash'] !== $m['datasetHash'] || $proof['decisionsHash'] !== $m['decisionsHash'] || $proof['verified'] !== true
                || !is_string($proof['checkedAt']) || strtotime($proof['checkedAt']) === false || strtotime($proof['checkedAt']) > time() || time() - strtotime($proof['checkedAt']) > 300) { throw new RuntimeException('results_verification_required'); }
            foreach ($m['members'] as $member) { self::mappedRecipient($member['subject']); }
            $assert();
            self::query($wpdb->prepare('UPDATE ' . self::table('results') . " SET state='approved',seed=%s,verification_hash=%s,version=version+1 WHERE campaign='jysk_2026' AND version=%d", bin2hex(random_bytes(32)), hash('sha256', self::resultJson($proof)), $version));
            return ['state' => 'approved'];
        });
        if (($approved['state'] ?? '') === 'busy') { return $approved; }
        return self::resumeResults($hash, $version + 1);
    }

    private static function operatorOnly(): void
    {
        if (!defined('WP_CLI') || WP_CLI !== true) { throw new RuntimeException('operator_cli_only'); }
    }

    public static function resumeResults(string $hash, ?int $expectedVersion = null): array
    {
        self::operatorOnly(); global $wpdb;
        return self::resultTransaction(static function ($assert) use ($wpdb, $hash, $expectedVersion): array {
            $row = self::row("SELECT * FROM " . self::table('results') . " WHERE campaign='jysk_2026' FOR UPDATE");
            if (!$row || !hash_equals($row['manifest_hash'], $hash) || !in_array($row['state'], ['approved','published'], true) || $expectedVersion !== null && (int) $row['version'] !== $expectedVersion) { throw new RuntimeException('results_not_approved'); }
            if ($row['state'] === 'published' && $row['redraw_json'] === null) { return ['state' => 'published', 'version' => (int) $row['version'], 'manifestHash' => $hash]; }
            $m = json_decode($row['manifest'], true, 32, JSON_THROW_ON_ERROR);
            $result = $row['result_json'] === null ? null : json_decode($row['result_json'], true, 32, JSON_THROW_ON_ERROR);
            $round = $result === null ? 0 : 1;
            $replacement = $round === 1 ? json_decode($row['redraw_json'], true, 16, JSON_THROW_ON_ERROR) : null;
            $excluded = $result === null ? [] : array_merge(...array_map(static fn ($r) => array_column($r['winners'], 'personKey'), $result['rounds']));
            $draw = self::drawResults($m, $hash, $row['seed'], $round, $excluded, $replacement ? count($replacement['slots']) : 3);
            $at = gmdate('Y-m-d H:i:s', time()); $winning = [];
            foreach ($draw['winners'] as $index => $person) {
                $slot = $replacement ? $replacement['slots'][$index] : $index + 1;
                $recipient = self::mappedRecipient($person['subject']);
                $assert();
                self::query($wpdb->prepare('INSERT INTO ' . $wpdb->prefix . "impact_vote_messages(type,content,start_at,end_at,priority,created_at) VALUES('targeted',%s,%s,%s,100,%s)", 'JYSK-üzenetetek érkezett. Keresd fel: https://jysk.sharity.hu/jatek', $at, '2027-01-31 23:59:59', $at));
                $message = (int) $wpdb->insert_id; if ($message < 1) { throw new RuntimeException('storage_unavailable'); }
                self::query($wpdb->prepare('INSERT INTO ' . $wpdb->prefix . 'impact_vote_message_targets(message_id,pseudo_id) VALUES(%d,%s)', $message, $recipient['pseudo']));
                self::query($wpdb->prepare('INSERT INTO ' . self::table('message_map') . ' (message_id,subject) VALUES(%d,%s)', $message, $person['subject']));
                $reference = bin2hex(random_bytes(16));
                self::query($wpdb->prepare('INSERT INTO ' . self::table('prizes') . " (campaign,round_no,slot_no,person_key,subject,message_id,claim_reference) VALUES('jysk_2026',%d,%d,%s,%s,%d,%s)", $round, $slot, $person['personKey'], $person['subject'], $message, $reference));
                $winning[] = ['personKey' => $person['personKey'], 'slot' => $slot];
            }
            if ($result === null) {
                $result = ['rounds' => [], 'public' => ['state' => 'published', 'manifestHash' => $hash, 'publishedAt' => gmdate('Y-m-d\TH:i:s\Z', time()), 'ngos' => $m['ngos'], 'winner' => $draw['ngoWinner']]];
            }
            $result['rounds'][] = ['round' => $round, 'winners' => $winning];
            $assert();
            self::query($wpdb->prepare('UPDATE ' . self::table('results') . " SET state='published',result_json=%s,redraw_json=NULL,published_at=COALESCE(published_at,%s),version=version+1 WHERE campaign='jysk_2026' AND version=%d", self::resultJson($result), $at, (int) $row['version']));
            return ['state' => 'published', 'version' => (int) $row['version'] + 1, 'manifestHash' => $hash, 'round' => $round, 'notices' => count($winning)];
        });
    }

    private static function prizeMessages(string $subject, ?string $ack): ?array
    {
        global $wpdb;
        if (!self::resultsInstalled()) { return null; }
        $table = self::table('prizes');
        if ($ack !== null) {
            if (!preg_match('/^[1-9][0-9]{0,18}$/D', $ack)) { throw new RuntimeException('invalid_message'); }
            $prize = self::row($wpdb->prepare("SELECT * FROM {$table} WHERE subject=%s AND message_id=%s", $subject, $ack));
            if (!$prize) { return null; }
            $answer = self::resultTransaction(static function ($assert) use ($wpdb, $subject, $ack, $table): array {
                $p = self::row($wpdb->prepare("SELECT * FROM {$table} WHERE subject=%s AND message_id=%s FOR UPDATE", $subject, $ack));
                if (!$p) { throw new RuntimeException('invalid_message'); }
                if ($p['delivered_at'] === null) {
                    $at = gmdate('Y-m-d H:i:s', time()); $deadline = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
                    $assert();
                    self::query($wpdb->prepare("UPDATE {$table} SET delivered_at=%s,claim_deadline=%s,version=version+1 WHERE subject=%s AND message_id=%s AND delivered_at IS NULL", $at, $deadline, $subject, $ack));
                    self::query($wpdb->prepare('UPDATE ' . self::table('message_map') . ' SET delivered_at=COALESCE(delivered_at,%s) WHERE subject=%s AND message_id=%s', $at, $subject, $ack));
                    $p['delivered_at'] = $at; $p['claim_deadline'] = $deadline;
                }
                return ['delivered' => true, 'messageId' => $ack, 'deliveredAt' => self::iso($p['delivered_at']), 'claimDeadline' => self::iso($p['claim_deadline'])];
            });
            if (($answer['state'] ?? '') === 'busy') { throw new RuntimeException('storage_unavailable'); }
            return $answer;
        }
        $rows = self::check($wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE subject=%s ORDER BY round_no,slot_no", $subject), ARRAY_A));
        return ['messages' => array_map(static fn ($p) => [
            'id' => (string) $p['message_id'], 'title' => 'JYSK nyereményértesítés', 'type' => 'jysk-prize',
            'body' => 'Gratulálunk, 10 000 Ft értékű JYSK vásárlási utalványt nyertetek! Az igazolt megjelenéstől számított 7 napon belül írjatok az office@sharity.hu címre. Hivatkozás: ' . $p['claim_reference'] . '. A hivatkozás mellett személyazonosság-ellenőrzés szükséges; az átadás személyesen vagy postán történik.',
            'deliveredAt' => self::iso($p['delivered_at']), 'claimDeadline' => self::iso($p['claim_deadline']),
        ], $rows)];
    }

    private static function iso(?string $value): ?string
    {
        return $value === null ? null : str_replace(' ', 'T', $value) . 'Z';
    }

    public static function prizeStatus(?string $claimReference = null): array
    {
        self::operatorOnly(); global $wpdb;
        if ($claimReference !== null && !preg_match('/^[a-f0-9]{32}$/D', $claimReference)) { throw new RuntimeException('invalid_claim_reference'); }
        $sql = 'SELECT round_no,slot_no,version,case_state,delivered_at,claim_deadline FROM ' . self::table('prizes') . " WHERE campaign='jysk_2026'";
        if ($claimReference !== null) { $sql .= $wpdb->prepare(' AND claim_reference=%s', $claimReference); }
        return self::check($wpdb->get_results($sql . ' ORDER BY round_no,slot_no', ARRAY_A));
    }

    public static function recordPrize(array $event, int $version): array
    {
        self::operatorOnly(); global $wpdb;
        self::resultExact($event, ['round','slot','kind','occurredAt','reference','details']);
        $kinds = ['received','deficiency-sent','deficiency-delivered','response','disputed','decision','approved','fulfilled','forfeit-decision'];
        if (!is_int($event['round']) || $event['round'] < 0 || $event['round'] > 1 || !is_int($event['slot']) || $event['slot'] < 1 || $event['slot'] > 3
            || !in_array($event['kind'], $kinds, true) || !is_string($event['reference']) || !preg_match('/^[a-f0-9]{64}$/D', $event['reference'])
            || !is_string($event['details']) || strlen($event['details']) > 1000 || !preg_match('/^[a-zA-Z0-9_.:-]{1,1000}$/D', $event['details'])
            || !is_string($event['occurredAt']) || !preg_match('/^20[0-9-]{8}T[0-9:]{8}Z$/D', $event['occurredAt']) || strtotime($event['occurredAt']) === false || strtotime($event['occurredAt']) > time()) { throw new RuntimeException('invalid_prize_event'); }
        return self::resultTransaction(static function ($assert) use ($wpdb, $event, $version): array {
            $p = self::row($wpdb->prepare('SELECT * FROM ' . self::table('prizes') . " WHERE campaign='jysk_2026' AND round_no=%d AND slot_no=%d FOR UPDATE", $event['round'], $event['slot']));
            if (!$p || (int) $p['version'] !== $version || $p['case_state'] === 'fulfilled' || $p['case_state'] === 'forfeit-decision'
                || $event['kind'] === 'fulfilled' && $p['case_state'] !== 'approved'
                || $event['kind'] === 'forfeit-decision' && $p['case_state'] !== 'decision'
                || $event['kind'] === 'deficiency-delivered' && $p['case_state'] !== 'deficiency-sent') { throw new RuntimeException('prize_version_conflict'); }
            $id = hash('sha256', self::resultJson($event));
            $assert();
            self::query($wpdb->prepare('INSERT INTO ' . self::table('prize_events') . " (id,campaign,round_no,slot_no,kind,occurred_at,reference_hash,details) VALUES(%s,'jysk_2026',%d,%d,%s,%s,%s,%s)", $id, $event['round'], $event['slot'], $event['kind'], str_replace(['T','Z'], [' ',''], $event['occurredAt']), $event['reference'], $event['details']));
            self::query($wpdb->prepare('UPDATE ' . self::table('prizes') . " SET case_state=%s,version=version+1 WHERE campaign='jysk_2026' AND round_no=%d AND slot_no=%d AND version=%d", $event['kind'], $event['round'], $event['slot'], $version));
            return ['recorded' => true, 'eventId' => $id, 'version' => $version + 1];
        });
    }

    public static function redrawPrizes(string $hash, int $version, array $decision): array
    {
        self::operatorOnly(); global $wpdb;
        self::resultExact($decision, ['slots','reference','deadlinesReviewed']);
        if (!is_array($decision['slots']) || !array_is_list($decision['slots']) || count($decision['slots']) < 1 || count($decision['slots']) > 3 || count(array_unique($decision['slots'])) !== count($decision['slots'])
            || $decision['deadlinesReviewed'] !== true || !is_string($decision['reference']) || !preg_match('/^[a-f0-9]{64}$/D', $decision['reference'])) { throw new RuntimeException('invalid_redraw'); }
        sort($decision['slots'], SORT_NUMERIC);
        $approved = self::resultTransaction(static function ($assert) use ($wpdb, $hash, $version, $decision): array {
            $row = self::row("SELECT * FROM " . self::table('results') . " WHERE campaign='jysk_2026' FOR UPDATE");
            if (!$row || $row['state'] !== 'published' || (int) $row['version'] !== $version || !hash_equals($row['manifest_hash'], $hash) || $row['redraw_json'] !== null
                || count(json_decode($row['result_json'], true, 32, JSON_THROW_ON_ERROR)['rounds']) !== 1) { throw new RuntimeException('results_version_conflict'); }
            foreach ($decision['slots'] as $slot) {
                if (!is_int($slot) || $slot < 1 || $slot > 3) { throw new RuntimeException('invalid_redraw'); }
                $prize = self::row($wpdb->prepare('SELECT case_state FROM ' . self::table('prizes') . " WHERE campaign='jysk_2026' AND round_no=0 AND slot_no=%d FOR UPDATE", $slot));
                if (!$prize || $prize['case_state'] !== 'forfeit-decision') { throw new RuntimeException('redraw_not_vacant'); }
            }
            $assert();
            self::query($wpdb->prepare('UPDATE ' . self::table('results') . " SET redraw_json=%s,version=version+1 WHERE campaign='jysk_2026' AND version=%d", self::resultJson($decision), $version));
            return ['state' => 'redraw-approved'];
        });
        if (($approved['state'] ?? '') === 'busy') { return $approved; }
        return self::resumeResults($hash, $version + 1);
    }

    private static function messages(array $body, bool $ack): array
    {
        global $wpdb;
        $session = self::session($body);
        $subject = $session['grant']['subject'];
        $pseudo = $session['grant']['pseudo'];
        $map = self::table('message_map');
        $messages = $wpdb->prefix . 'impact_vote_messages';
        $targets = $wpdb->prefix . 'impact_vote_message_targets';
        // Prize reads/ACKs do not inherit the generic notice's validity or read flag.
        $private = self::prizeMessages($subject, $ack ? (string) ($body['messageId'] ?? '') : null);
        if ($private !== null && $ack) {
            return $private;
        }
        if ($ack) {
            $id = (string) ($body['messageId'] ?? '');
            if (!preg_match('/^[1-9][0-9]{0,18}$/D', $id)) {
                throw new RuntimeException('invalid_message');
            }
            // Only an operator-published exact recipient mapping may be acknowledged.
            $row = self::row($wpdb->prepare("SELECT j.message_id FROM {$map} j INNER JOIN {$targets} t ON t.message_id=j.message_id INNER JOIN {$messages} m ON m.id=j.message_id WHERE j.subject=%s AND j.message_id=%s AND t.pseudo_id=%s AND m.type='targeted' AND m.start_at<=%s AND m.end_at>=%s", $subject, $id, $pseudo, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')));
            if (!$row) {
                throw new RuntimeException('invalid_message');
            }
            self::query($wpdb->prepare("UPDATE {$map} SET delivered_at=COALESCE(delivered_at,%s) WHERE subject=%s AND message_id=%s", gmdate('Y-m-d H:i:s'), $subject, $id));
            return ['delivered' => true, 'messageId' => $id];
        }
        $rows = self::check($wpdb->get_results($wpdb->prepare("SELECT DISTINCT m.id,m.type,m.content FROM {$map} j INNER JOIN {$messages} m ON m.id=j.message_id INNER JOIN {$targets} t ON t.message_id=m.id WHERE j.subject=%s AND j.delivered_at IS NULL AND t.pseudo_id=%s AND m.type='targeted' AND m.start_at<=%s AND m.end_at>=%s ORDER BY m.id DESC LIMIT 10", $subject, $pseudo, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')), ARRAY_A));
        return ['messages' => array_merge($private['messages'] ?? [], array_map(static function (array $row): array {
            return ['id' => (string) $row['id'], 'title' => 'JYSK', 'body' => wp_strip_all_tags($row['content']), 'type' => $row['type']];
        }, array_filter($rows, static fn (array $row): bool => !in_array((string) $row['id'], array_column($private['messages'] ?? [], 'id'), true))))];
    }

    public static function service($request)
    {
        $nonce = '';
        try {
            if (!self::cleanWirePrefix()) {
                throw new RuntimeException('response_unavailable');
            }
            if (!self::enabled()) {
                throw new RuntimeException('disabled');
            }
            $nonce = self::authenticate($request);
            $body = json_decode($request->get_body(), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($body)) {
                throw new RuntimeException('invalid_payload');
            }
            $path = substr($request->get_route(), strlen('/jysk-game/v1/'));
            if ($path === 'exchange') {
                $data = self::exchange($body);
            } elseif ($path === 'revoke' || $path === 'disconnect') {
                $data = self::endSession($body, $path === 'revoke');
            } elseif ($path === 'context') {
                $session = self::session($body);
                $data = ['subject' => $body['subject'], 'sessionToken' => $body['sessionToken'], 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $session['expires']), 'earningAllowed' => self::sessionEarning($session['grant'])];
                // Opt-in keeps the strict response contract of deployed older apps intact.
                // Only the authenticated session's profile is used, never a caller pseudo.
                if (($body['includeProfileHint'] ?? false) === true) {
                    $data['profileHint'] = substr($session['grant']['pseudo'], 0, 5);
                }
            } elseif ($path === 'award' || $path === 'reward-status') {
                $data = self::reward($body, $path === 'award');
            } elseif ($path === 'messages' || $path === 'messages/delivered') {
                $data = self::messages($body, $path === 'messages/delivered');
            } elseif ($path === 'results' && $body === []) {
                $data = self::publicResults();
            } else {
                throw new RuntimeException('invalid_route');
            }
            return self::response($request, $data, 200, $nonce);
        } catch (Throwable $error) {
            $known = ['invalid_payload', 'invalid_session', 'invalid_exchange', 'invalid_message', 'unauthorized', 'replay', 'disabled'];
            $message = in_array($error->getMessage(), $known, true) ? $error->getMessage() : 'bridge_unavailable';
            $status = $message === 'unauthorized' ? 401 : ($message === 'replay' ? 409 : ($message === 'bridge_unavailable' ? 503 : 403));
            // A valid MAC is sufficient to sign storage/replay errors too.
            if ($nonce === '' && preg_match('/^[a-f0-9]{32}$/D', (string) $request->get_header('x-jysk-nonce'))) {
                try {
                    $expected = self::signature((string) $request->get_header('x-jysk-timestamp'), $request->get_header('x-jysk-nonce'), 'POST', '/wp-json' . $request->get_route(), $request->get_body());
                    if (hash_equals($expected, (string) $request->get_header('x-jysk-signature'))) {
                        $nonce = $request->get_header('x-jysk-nonce');
                    }
                } catch (Throwable $ignored) {
                    $nonce = '';
                }
            }
            return self::response($request, ['error' => $message], $status, $nonce);
        }
    }
}

add_action('rest_api_init', [Impactshop_Jysk_Game::class, 'register']);
add_action('template_redirect', [Impactshop_Jysk_Game::class, 'completeProfileReturn'], 0);
add_filter('rest_pre_serve_request', [Impactshop_Jysk_Game::class, 'serve'], PHP_INT_MAX, 4);

/** Existing authenticated WP-CLI transport; no browser/admin writer endpoint. */
if (defined('WP_CLI') && WP_CLI === true && class_exists('WP_CLI')) {
    WP_CLI::add_command('jysk-game', 'Impactshop_Jysk_Game_CLI');
}

class Impactshop_Jysk_Game_CLI
{
    private static function input(string $path): string
    {
        $parent = realpath(dirname($path));
        if ($path === '' || $path[0] !== '/' || is_link($path) || $parent === false || $parent !== dirname($path)
            || !is_file($path) || (fileperms($path) & 0777) !== 0600 || (fileperms($parent) & 0777) !== 0700
            || fileowner($path) !== posix_geteuid() || fileowner($parent) !== posix_geteuid() || filesize($path) > 8388608) {
            throw new RuntimeException('private_input_required');
        }
        $handle = fopen($path, 'rb');
        if (!$handle) { throw new RuntimeException('private_input_required'); }
        try {
            $stat = fstat($handle); $lstat = lstat($path);
            if (!$stat || !$lstat || $stat['ino'] !== $lstat['ino'] || $stat['dev'] !== $lstat['dev'] || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600 || $stat['uid'] !== posix_geteuid()) { throw new RuntimeException('private_input_required'); }
            $raw = stream_get_contents($handle, 8388609);
            if ($raw === false || strlen($raw) > 8388608) { throw new RuntimeException('private_input_required'); }
            return $raw;
        } finally { fclose($handle); }
    }

    private static function version(array $options): int
    {
        $v = $options['expected-version'] ?? '';
        if (!is_string($v) || !preg_match('/^(0|[1-9][0-9]{0,8})$/D', $v)) { throw new RuntimeException('expected_version_required'); }
        return (int) $v;
    }

    private static function authorize(string $operation, array $options): array
    {
        $version = self::version($options);
        if (getenv('JYSK_GAME_OPERATOR_CONFIRM') !== "JYSK-GAME:{$operation}:{$version}") { throw new RuntimeException('operator_confirmation_required'); }
        $raw = self::input((string) ($options['intent-file'] ?? ''));
        $intent = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        $parameters = $options; unset($parameters['intent-file']);
        foreach (['input','verification-file'] as $field) {
            if (isset($options[$field])) { $parameters[$field . '-sha256'] = hash('sha256', self::input($options[$field])); }
        }
        $expected = ['kind' => 'jysk-game-operator-intent-v1', 'operation' => $operation, 'expectedVersion' => $version, 'parameters' => $parameters];
        // Array equality preserves field types while ignoring JSON object key order.
        if (!is_array($intent) || count($intent) !== 4 || ($intent['kind'] ?? null) !== $expected['kind'] || ($intent['operation'] ?? null) !== $operation || ($intent['expectedVersion'] ?? null) !== $version
            || !is_array($intent['parameters'] ?? null) || count($intent['parameters']) !== count($parameters) || array_diff(array_keys($intent['parameters']), array_keys($parameters))) { throw new RuntimeException('operator_intent_mismatch'); }
        foreach ($parameters as $key => $value) { if ($intent['parameters'][$key] !== $value) { throw new RuntimeException('operator_intent_mismatch'); } }
        return $parameters;
    }

    public function sessions(array $args, array $options): void
    {
        try {
            $operation = $args[0] ?? '';
            $allowed = ['status' => [], 'upgrade' => ['intent-file', 'expected-version', 'target-hash', 'structure-hash', 'source-hash'],
                'revoke-issued' => ['intent-file', 'expected-version', 'input', 'target-hash', 'structure-hash', 'source-hash']];
            if (count($args) !== 1 || !isset($allowed[$operation]) || array_diff(array_keys($options), $allowed[$operation])) { throw new RuntimeException('invalid_operator_arguments'); }
            if ($operation === 'status') { $result = Impactshop_Jysk_Game::sessionsStatus(); }
            else {
                $authorized = self::authorize('sessions-' . $operation, $options);
                $expected = ['targetHash' => (string) ($options['target-hash'] ?? ''), 'structureHash' => (string) ($options['structure-hash'] ?? ''), 'sourceHash' => (string) ($options['source-hash'] ?? '')];
                if (self::version($options) !== 2) { throw new RuntimeException('expected_version_required'); }
                if ($operation === 'upgrade') { $result = Impactshop_Jysk_Game::upgradeSessions($expected); }
                else {
                    $status = Impactshop_Jysk_Game::sessionsStatus();
                    foreach ($expected as $field => $hash) { if (!hash_equals($status[$field], $hash)) { throw new RuntimeException('session_upgrade_conflict'); } }
                    $raw = self::input($options['input']);
                    if (!hash_equals($authorized['input-sha256'], hash('sha256', $raw))) { throw new RuntimeException('operator_input_changed'); }
                    $selection = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                    if (!is_array($selection)) { throw new RuntimeException('invalid_session_selection'); }
                    $result = Impactshop_Jysk_Game::revokeIssuedSessions($selection);
                }
            }
            WP_CLI::line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) { WP_CLI::error($e->getMessage()); }
    }

    public function results(array $args, array $options): void
    {
        try {
            $operation = $args[0] ?? '';
            $allowed = ['install' => ['intent-file','expected-version'], 'status' => [],
                'prepare' => ['input','expected-hash','expected-version','intent-file'],
                'approve' => ['expected-hash','expected-version','verification-file','intent-file'],
                'resume' => ['expected-hash','expected-version','intent-file']];
            if (count($args) !== 1 || !isset($allowed[$operation]) || array_diff(array_keys($options), $allowed[$operation])) { throw new RuntimeException('invalid_operator_arguments'); }
            if ($operation === 'status') { $result = Impactshop_Jysk_Game::resultsStatus(); }
            else {
                $authorized = self::authorize('results-' . $operation, $options);
                $hash = (string) ($options['expected-hash'] ?? '');
                if ($operation !== 'install' && !preg_match('/^[a-f0-9]{64}$/D', $hash)) { throw new RuntimeException('expected_hash_required'); }
                if ($operation === 'install') {
                    if (self::version($options) !== 0) { throw new RuntimeException('expected_version_required'); }
                    Impactshop_Jysk_Game::installResults(); $result = Impactshop_Jysk_Game::resultsStatus();
                } elseif ($operation === 'prepare') {
                    $raw = self::input($options['input']);
                    if (!hash_equals($authorized['input-sha256'], hash('sha256', $raw))) { throw new RuntimeException('operator_input_changed'); }
                    $result = Impactshop_Jysk_Game::prepareResults($raw, $hash, self::version($options));
                }
                elseif ($operation === 'approve') {
                    $proofRaw = self::input($options['verification-file']); $proof = json_decode($proofRaw, true, 16, JSON_THROW_ON_ERROR);
                    if (!hash_equals($authorized['verification-file-sha256'], hash('sha256', $proofRaw))) { throw new RuntimeException('operator_input_changed'); }
                    if (!is_array($proof)) { throw new RuntimeException('results_verification_required'); }
                    $result = Impactshop_Jysk_Game::approveResults($hash, self::version($options), $proof);
                } else {
                    $status = Impactshop_Jysk_Game::resultsStatus();
                    if (($status['version'] ?? null) !== self::version($options) || ($status['manifestHash'] ?? '') !== $hash) { throw new RuntimeException('results_version_conflict'); }
                    $result = Impactshop_Jysk_Game::resumeResults($hash, self::version($options));
                }
            }
            WP_CLI::line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) { WP_CLI::error($e->getMessage()); }
    }

    public function prize(array $args, array $options): void
    {
        try {
            $operation = $args[0] ?? '';
            $allowed = ['status' => ['input'], 'record' => ['expected-version','input','intent-file'], 'redraw' => ['expected-version','expected-hash','input','intent-file']];
            if (count($args) !== 1 || !isset($allowed[$operation]) || array_diff(array_keys($options), $allowed[$operation])) { throw new RuntimeException('invalid_operator_arguments'); }
            if ($operation === 'status') {
                $lookup = isset($options['input']) ? json_decode(self::input($options['input']), true, 4, JSON_THROW_ON_ERROR) : null;
                if ($lookup !== null && (!is_array($lookup) || array_keys($lookup) !== ['claimReference'] || !is_string($lookup['claimReference']))) { throw new RuntimeException('invalid_claim_reference'); }
                $result = Impactshop_Jysk_Game::prizeStatus($lookup['claimReference'] ?? null);
            }
            else {
                $authorized = self::authorize('prize-' . $operation, $options);
                $raw = self::input($options['input']);
                if (!hash_equals($authorized['input-sha256'], hash('sha256', $raw))) { throw new RuntimeException('operator_input_changed'); }
                $value = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
                $result = $operation === 'record' ? Impactshop_Jysk_Game::recordPrize($value, self::version($options))
                    : Impactshop_Jysk_Game::redrawPrizes((string) ($options['expected-hash'] ?? ''), self::version($options), $value);
            }
            WP_CLI::line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) { WP_CLI::error($e->getMessage()); }
    }
}
