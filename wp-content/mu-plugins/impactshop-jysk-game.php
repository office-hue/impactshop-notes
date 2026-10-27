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
    public const PRIVATE_CONFIG = '/home/sharityh/jysk-game/config.json';
    private static array $wire = [];
    private static ?array $profile = null;
    private static string $profileStatus = 'unread';

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
        $keys = ['ENABLED', 'SERVICE_KEY', 'SUBJECT_KEY', 'REWARDS_ENABLED', 'TRANSACTION_READY', 'POLICY_HASH', 'PRODUCER_SHAS', 'NGO_IDS', 'TEST_SUBJECTS'];
        if (!is_array($value) || array_is_list($value) || array_diff(array_keys($value), $keys)) {
            return [];
        }
        foreach ($value as $key => $item) {
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

    public static function register(): void
    {
        if (!self::enabled()) {
            return;
        }
        register_rest_route('jysk-game/v1', '/handoff', [
            'methods' => 'GET', 'permission_callback' => '__return_true',
            'callback' => [self::class, 'handoff'],
        ]);
        foreach (['exchange', 'context', 'revoke', 'award', 'reward-status', 'messages', 'messages/delivered'] as $path) {
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

    /** Sign and serve the same bytes, independent of WordPress JSON encoding filters. */
    public static function serve($served, $result, $request, $server)
    {
        $key = spl_object_hash($request);
        if (!isset(self::$wire[$key])) {
            return $served;
        }
        [$bytes, $status, $headers] = self::$wire[$key];
        unset(self::$wire[$key]);
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
                throw new RuntimeException('owner_required');
            }
            $subject = hash_hmac('sha256', self::CLIENT . ':' . $pseudo, self::key('SUBJECT_KEY'));
            $grant = impactshop_identity_owner_hash(impactshop_identity_owner_cookie());
            $code = bin2hex(random_bytes(32));
            $generation = bin2hex(random_bytes(32));
            global $wpdb;
            self::query('START TRANSACTION');
            try {
                self::query($wpdb->prepare('INSERT INTO ' . self::table('grants') . ' (subject,pseudo,grant_hash,generation,revoked) VALUES (%s,%s,%s,%s,0) ON DUPLICATE KEY UPDATE grant_hash=VALUES(grant_hash),generation=VALUES(generation),revoked=0', $subject, $pseudo, $grant, $generation));
                self::query($wpdb->prepare('DELETE FROM ' . self::table('sessions') . ' WHERE subject=%s', $subject));
                self::query($wpdb->prepare('DELETE FROM ' . self::table('codes') . ' WHERE subject=%s', $subject));
                self::query($wpdb->prepare('INSERT INTO ' . self::table('codes') . ' (token_hash,subject,generation,challenge,expires) VALUES (%s,%s,%s,%s,%d)', hash('sha256', $code), $subject, $generation, $request->get_param('code_challenge'), time() + 120));
                self::query('COMMIT');
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');
                throw $error;
            }
            return new WP_REST_Response(null, 302, ['Location' => self::CALLBACK . '?code=' . $code . '&state=' . rawurlencode($request->get_param('state')), 'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
        } catch (Throwable $error) {
            return self::response($request, ['error' => 'handoff_unavailable'], 403);
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
        return self::config('REWARDS_ENABLED', false) === true
            && self::transactionReady()
            && class_exists('Sharity_Points_Manager')
            && method_exists('Sharity_Points_Manager', 'award_points_for_pseudo')
            && class_exists('Sharity_Level_Manager')
            && apply_filters('sharity_can_earn_points', true, $grant['pseudo'], 'bonus');
    }

    private static function session(array $body): array
    {
        global $wpdb;
        $subject = (string) ($body['subject'] ?? '');
        $token = (string) ($body['sessionToken'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new RuntimeException('invalid_session');
        }
        $row = self::row($wpdb->prepare('SELECT * FROM ' . self::table('sessions') . ' WHERE token_hash=%s AND subject=%s AND expires>%d', hash('sha256', $token), $subject, time()));
        $grant = self::grant($subject);
        if (!$row || !$grant || !hash_equals($grant['generation'], $row['generation'])) {
            throw new RuntimeException('invalid_session');
        }
        return ['grant' => $grant, 'expires' => (int) $row['expires']];
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
        $grant = $row ? self::grant($row['subject']) : null;
        if (!$row || !$grant || !hash_equals($row['challenge'], $challenge) || !hash_equals($grant['generation'], $row['generation'])) {
            throw new RuntimeException('invalid_exchange');
        }
        // Conditional delete is the durable one-use claim; failure afterwards burns the code.
        if (self::query($wpdb->prepare('DELETE FROM ' . self::table('codes') . ' WHERE token_hash=%s AND expires>%d', $hash, time())) !== 1) {
            throw new RuntimeException('invalid_exchange');
        }
        $token = bin2hex(random_bytes(32));
        $expires = time() + 900;
        self::query($wpdb->prepare('INSERT INTO ' . self::table('sessions') . ' (token_hash,subject,generation,expires) VALUES (%s,%s,%s,%d)', hash('sha256', $token), $row['subject'], $row['generation'], $expires));
        $current = self::grant($row['subject']);
        if (!$current || !hash_equals($current['generation'], $row['generation'])) {
            throw new RuntimeException('invalid_exchange');
        }
        return ['subject' => $row['subject'], 'sessionToken' => $token, 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expires), 'earningAllowed' => self::earning($current)];
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
            return $base + ['state' => 'policy_blocked'];
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
                if ($claim) {
                    if (!self::transactionReady()) {
                        return ['state' => 'unknown'];
                    }
                    $assert();
                    self::query($wpdb->prepare('UPDATE ' . self::table('rewards') . " SET state='absent' WHERE event_id=%s AND payload_hash=%s", $payload['eventId'], $hash));
                    $assert();
                }
                return ['state' => 'absent'];
            }
            $grant = self::grant($payload['subject']);
            if (!$grant) {
                return ['state' => 'policy_blocked'];
            }
            // Request timestamp tolerance never authorizes an award before the
            // actual server-clock campaign start. Late outbox drain stays valid.
            if (time() < (new DateTimeImmutable('2026-11-09T11:00:00Z'))->getTimestamp()) {
                return ['state' => 'policy_blocked'];
            }
            $tests = self::config('TEST_SUBJECTS', []);
            $technical = is_array($tests) && in_array($payload['subject'], $tests, true);
            $testAllowed = $payload['isTest'] ? ($technical && count($tests) === 1) : !$technical;
            if (!$testAllowed || !self::earning($grant) || $payload['policyHash'] !== self::config('POLICY_HASH', '')
                || !in_array($payload['producerSha'], self::config('PRODUCER_SHAS', []), true)
                || !in_array($payload['ngoId'], self::config('NGO_IDS', []), true)) {
                return ['state' => 'policy_blocked'];
            }
            if ($payload['isTest']) {
                $used = self::scalar($wpdb->prepare("SELECT COALESCE(SUM(GREATEST(points,0)),0) FROM {$wpdb->prefix}point_transactions WHERE pseudo_id=%s AND source_type='jysk_campaign' AND LEFT(dedupe_key,10)=%s", $grant['pseudo'], 'jysk_2026:'));
                $assert();
                if (!is_numeric($used) || (int) $used < 0 || (int) $used + $payload['points'] > 35) {
                    return ['state' => 'policy_blocked'];
                }
            }
            // Never replay an ambiguous prior call. Status/reconciliation resolves it.
            if ($claim && $claim['state'] !== 'absent') {
                return ['state' => 'unknown'];
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
                return ['state' => ($answer['error'] ?? '') === 'earning_blocked' ? 'policy_blocked' : 'unknown'];
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

    private static function messages(array $body, bool $ack): array
    {
        global $wpdb;
        $session = self::session($body);
        $subject = $session['grant']['subject'];
        $pseudo = $session['grant']['pseudo'];
        $map = self::table('message_map');
        $messages = $wpdb->prefix . 'impact_vote_messages';
        $targets = $wpdb->prefix . 'impact_vote_message_targets';
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
        return ['messages' => array_map(static function (array $row): array {
            return ['id' => (string) $row['id'], 'title' => 'JYSK', 'body' => wp_strip_all_tags($row['content']), 'type' => $row['type']];
        }, $rows)];
    }

    public static function service($request)
    {
        $nonce = '';
        try {
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
            } elseif ($path === 'context' || $path === 'revoke') {
                $session = self::session($body);
                if ($path === 'revoke') {
                    global $wpdb;
                    self::query($wpdb->prepare('UPDATE ' . self::table('grants') . ' SET revoked=1 WHERE subject=%s', $body['subject']));
                    $data = ['revoked' => true];
                } else {
                    $data = ['subject' => $body['subject'], 'sessionToken' => $body['sessionToken'], 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $session['expires']), 'earningAllowed' => self::earning($session['grant'])];
                }
            } elseif ($path === 'award' || $path === 'reward-status') {
                $data = self::reward($body, $path === 'award');
            } elseif ($path === 'messages' || $path === 'messages/delivered') {
                $data = self::messages($body, $path === 'messages/delivered');
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
add_filter('rest_pre_serve_request', [Impactshop_Jysk_Game::class, 'serve'], PHP_INT_MAX, 4);
