<?php
/** Deterministic failure injection; no WordPress runtime, network or real database. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
$GLOBALS['routes'] = [];
$GLOBALS['clock'] = strtotime('2026-11-15T12:00:00Z');
$GLOBALS['owner'] = true;
$GLOBALS['earning'] = true;
function add_action(...$args): void {}
function add_filter(...$args): void {}
function register_rest_route($namespace, $path, $args): void { $GLOBALS['routes'][] = $namespace . $path; }
function wp_json_encode($data, $options = 0) { return json_encode($data, $options); }
function apply_filters($name, $value, ...$args) { return $GLOBALS['earning']; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function status_header($status): void { $GLOBALS['status'] = $status; }
function impactshop_identity_profile_cookie() { return 'abcdef123456'; }
function impactshop_identity_owner_cookie() { return str_repeat('d', 64); }
function impactshop_identity_owner_hash($token) { return hash('sha256', $token); }
function impactshop_identity_owner_pseudo_hash($pseudo) { return hash('sha256', $pseudo); }
function impactshop_identity_owner_grant_table() { return 'wp_identity_grants'; }
function impactshop_identity_owner_authorized($pseudo) { return $GLOBALS['owner']; }
class WP_REST_Response
{
    public function __construct(public $data, public int $status = 200, public array $headers = []) {}
}
class FixtureRequest
{
    public function __construct(public string $route, public array $data, public array $headers = [], public ?string $raw = null) {}
    public function get_route() { return $this->route; }
    public function get_param($name) { return $this->data[$name] ?? null; }
    public function get_body() { return $this->raw ?? json_encode($this->data, JSON_UNESCAPED_SLASHES); }
    public function get_header($name) { return $this->headers[$name] ?? ''; }
}
class wpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    protected int $reconnect_retries = 5;
    public array $prepared = [];
    public array $log = [];
    public array $nonces = [];
    public ?array $claim = null;
    public ?array $receipt = null;
    public ?array $code = null;
    public ?array $session = null;
    public bool $grantActive = true;
    public string $generation = 'generation';
    public int $connection = 71;
    public int $owner = 71;
    public bool $busy = false;
    public string $fail = '';
    public bool $releaseThrows = false;
    public string $engine = 'InnoDB';
    public string $readOnly = '0';
    public bool $missingIndex = false;
    public int $technicalTotal = 0;
    public function __get($name) { return $this->$name; }
    public function __set($name, $value) { $this->$name = $value; }
    public function prepare($sql, ...$args) { $key = 'prepared:' . count($this->prepared); $this->prepared[$key] = [$sql, $args]; return $key; }
    public function unpack($sql) { return $this->prepared[$sql] ?? [$sql, []]; }
    private function record($sql): array
    {
        [$query, $args] = $this->unpack($sql);
        $this->last_error = '';
        $this->log[] = $query;
        if ($this->fail !== '' && str_contains($query, $this->fail)) {
            $this->last_error = 'injected storage error';
        }
        return [$query, $args];
    }
    public function query($sql)
    {
        [$query, $args] = $this->record($sql);
        if ($this->last_error !== '') { return false; }
        if (str_contains($query, 'RELEASE_LOCK') && $this->releaseThrows) { throw new RuntimeException('release_failed'); }
        if (str_contains($query, 'INSERT IGNORE INTO wp_jysk_game_nonces')) {
            if (isset($this->nonces[$args[0]])) { return 0; }
            $this->nonces[$args[0]] = true;
        }
        if (str_contains($query, 'INSERT INTO wp_jysk_game_rewards')) {
            $this->claim = ['event_id' => $args[0], 'payload_hash' => $args[1], 'payload' => $args[2], 'state' => 'accepted', 'points' => $args[4]];
        }
        if (str_contains($query, "SET state='absent'")) { $this->claim['state'] = 'absent'; }
        if (str_contains($query, "SET state='accepted'")) { $this->claim['state'] = 'accepted'; }
        if (str_contains($query, 'DELETE FROM wp_jysk_game_codes WHERE token_hash')) {
            if ($this->code === null) { return 0; }
            $this->code = null;
        }
        if (str_contains($query, 'INSERT INTO wp_jysk_game_sessions')) { $this->session = ['token_hash' => $args[0], 'subject' => $args[1], 'generation' => $args[2], 'expires' => $args[3]]; }
        if (str_contains($query, 'UPDATE wp_jysk_game_grants SET revoked=1')) { $this->grantActive = false; }
        return 1;
    }
    public function get_var($sql)
    {
        [$query] = $this->record($sql);
        if (str_contains($query, 'CONNECTION_ID')) { return $this->connection; }
        if (str_contains($query, 'GET_LOCK')) { return $this->busy ? 0 : 1; }
        if (str_contains($query, 'IS_USED_LOCK')) { return $this->owner; }
        if (str_contains($query, '@@read_only')) { return $this->readOnly; }
        if (str_contains($query, 'SELECT ENGINE')) { return $this->engine; }
        if (str_contains($query, 'SUM(GREATEST(points,0))')) { return $this->technicalTotal; }
        return null;
    }
    public function get_row($sql, $mode)
    {
        [$query] = $this->record($sql);
        if (str_contains($query, 'SELECT pseudo FROM')) { return ['pseudo' => 'abcdef123456']; }
        if (str_contains($query, 'SELECT * FROM wp_jysk_game_grants')) { return ['subject' => str_repeat('a', 64), 'pseudo' => 'abcdef123456', 'generation' => $this->generation]; }
        if (str_contains($query, 'INNER JOIN wp_identity_grants')) { return $this->grantActive ? ['subject' => str_repeat('a', 64), 'pseudo' => 'abcdef123456', 'generation' => $this->generation] : null; }
        if (str_contains($query, 'FROM wp_jysk_game_rewards')) { return $this->claim; }
        if (str_contains($query, 'FROM wp_point_transactions')) { return $this->receipt; }
        if (str_contains($query, 'FROM wp_jysk_game_codes')) { return $this->code; }
        if (str_contains($query, 'FROM wp_jysk_game_sessions')) { return $this->session; }
        if (str_contains($query, 'FROM wp_jysk_game_message_map')) { return null; }
        return null;
    }
    public function get_results($sql, $mode)
    {
        [$query] = $this->record($sql);
        if (str_starts_with($query, 'SHOW INDEX') && !$this->missingIndex) {
            if (str_contains($query, 'point_transactions')) {
                return [['Non_unique' => 0, 'Key_name' => 'canonical_unique', 'Seq_in_index' => 1, 'Column_name' => 'pseudo_id', 'Sub_part' => null], ['Non_unique' => 0, 'Key_name' => 'canonical_unique', 'Seq_in_index' => 2, 'Column_name' => 'dedupe_key', 'Sub_part' => null]];
            }
            $column = str_contains($query, 'point_transactions') ? 'dedupe_key' : (str_contains($query, 'user_points') ? 'pseudo_id' : 'event_id');
            return [['Non_unique' => 0, 'Key_name' => 'fixture_unique', 'Seq_in_index' => 1, 'Column_name' => $column, 'Sub_part' => null]];
        }
        return [];
    }
}
class Sharity_Points_Manager
{
    public static int $calls = 0;
    public static string $behavior = 'success';
    public function award_points_for_pseudo($pseudo, $points, $type, $source, $meta, $dedupe)
    {
        self::$calls++;
        $db = $GLOBALS['wpdb'];
        check($db->claim !== null, 'claim exists before manager');
        check(end($db->log) === 'SELECT IS_USED_LOCK(%s)', 'lock checked immediately before manager');
        check(in_array('COMMIT', $db->log, true), 'claim committed before manager');
        check($db->reconnect_retries === 0, 'reconnect disabled during manager');
        check($type === 'bonus' && $meta['source_type'] === 'jysk_campaign', 'canonical writer type');
        check($dedupe === 'jysk_2026:' . $source, 'stable economic dedupe');
        if (self::$behavior === 'throw') { throw new RuntimeException('manager_failed'); }
        if (self::$behavior === 'disconnect') { $db->connection++; return ['success' => true]; }
        if (self::$behavior === 'failure') { return ['success' => false, 'error' => 'storage_unavailable']; }
        $db->receipt = ['pseudo_id' => $pseudo, 'points' => $points, 'type' => $type, 'source_id' => $source, 'source_type' => $meta['source_type'], 'metadata' => json_encode($meta)];
        return ['success' => true];
    }
}
class Sharity_Level_Manager {}
// The source is evaluated unchanged inside a namespace solely to substitute PHP's
// clock. Class aliases retain the real production dependencies and error types.
$source = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/impactshop-jysk-game.php');
$source = preg_replace('/^<\?php/', '', $source);
$source = str_replace('declare(strict_types=1);', '', $source);
eval('namespace JyskFixture; use \\RuntimeException; use \\Throwable; use \\DateTimeImmutable; use \\DateTimeZone; use \\WP_REST_Response; use \\Sharity_Points_Manager; function time() { return $GLOBALS["clock"]; } ' . $source);
use JyskFixture\Impactshop_Jysk_Game as Bridge;
$checks = 0;
function check($condition, string $name): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
}
function freshDb(): wpdb { $GLOBALS['wpdb'] = new wpdb(); return $GLOBALS['wpdb']; }
function payload(): array
{
    $subject = str_repeat('a', 64);
    return ['eventId' => hash('sha256', "jysk_2026:{$subject}:quiz_answered:6:0"), 'subject' => $subject, 'kind' => 'quiz_answered', 'dayIndex' => 6, 'slot' => 0, 'ngoId' => 'ngo-1', 'points' => 5, 'occurredAt' => '2026-11-15T11:00:00.000Z', 'policyHash' => str_repeat('b', 64), 'producerSha' => str_repeat('c', 40), 'isTest' => false];
}
function request(string $path, array $body, ?string $nonce = null): FixtureRequest
{
    $r = new FixtureRequest('/jysk-game/v1/' . $path, $body);
    $nonce = $nonce ?? bin2hex(random_bytes(16));
    $ts = (string) $GLOBALS['clock'];
    $r->headers = ['x-jysk-timestamp' => $ts, 'x-jysk-nonce' => $nonce, 'x-jysk-signature' => Bridge::signature($ts, $nonce, 'POST', '/wp-json' . $r->route, $r->get_body())];
    return $r;
}
Bridge::register();
check($GLOBALS['routes'] === [], 'routes default off');
check(Bridge::configurationStatus()['privateProfile'] === 'absent' && Bridge::configurationStatus()['secretsConfigured'] === false, 'missing own private profile closes safely with diagnostic');
define('JYSK_GAME_ENABLED', true);
define('JYSK_GAME_SERVICE_KEY', str_repeat('1', 64));
define('JYSK_GAME_SUBJECT_KEY', str_repeat('2', 64));
define('JYSK_GAME_REWARDS_ENABLED', true);
define('JYSK_GAME_POLICY_HASH', str_repeat('b', 64));
define('JYSK_GAME_PRODUCER_SHAS', [str_repeat('c', 40)]);
define('JYSK_GAME_NGO_IDS', ['ngo-1']);
$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/jysk-game-protocol.json'), true, 16, JSON_THROW_ON_ERROR);
check($fixture['fixtureOnly'] === true && $fixture['serviceKeyHex'] === JYSK_GAME_SERVICE_KEY, 'public synthetic fixture key');
check(Bridge::signature($fixture['timestamp'], $fixture['nonce'], $fixture['method'], $fixture['path'], $fixture['rawRequest']) === $fixture['requestSignature'], 'cross-language request fixture');
check(Bridge::payloadHash(Bridge::payload(json_decode($fixture['rawRequest'], true))) === $fixture['payloadHash'], 'cross-language canonical payload fixture');
check(hash_hmac('sha256', "response\n" . $fixture['nonce'] . "\n" . $fixture['status'] . "\n" . hash('sha256', $fixture['rawResponse']), hex2bin($fixture['serviceKeyHex'])) === $fixture['responseSignature'], 'cross-language response fixture');
Bridge::register();
check(count($GLOBALS['routes']) === 9 && in_array('jysk-game/v1/results', $GLOBALS['routes'], true), 'isolated endpoint inventory includes signed results');
$db = freshDb();
$r = Bridge::service(request('award', payload()));
check($r->data['state'] === 'policy_blocked' && Sharity_Points_Manager::$calls === 0, 'transaction readiness defaults closed');
define('JYSK_GAME_TRANSACTION_READY', true);

$db = freshDb();
$req = request('award', payload());
$r = Bridge::service($req);
check($r->data['state'] === 'confirmed', 'canonical receipt confirms');
check($db->reconnect_retries === 5, 'retry config restored');
$calls = Sharity_Points_Manager::$calls;
$r = Bridge::service(request('award', payload()));
check($r->data['state'] === 'confirmed' && Sharity_Points_Manager::$calls === $calls, 'economic replay does not re-award');
$db->grantActive = false;
check(Bridge::service(request('reward-status', payload()))->data['state'] === 'confirmed', 'revoked grant does not hide an already committed receipt');
$db->grantActive = true;
$changed = payload(); $changed['ngoId'] = 'ngo-2';
check(Bridge::service(request('reward-status', $changed))->data['state'] === 'conflict', 'full original payload required');
check(Bridge::service($req)->status === 409, 'nonce replay denied');

$server = new class { public array $headers = []; public function send_header($name, $value): void { $this->headers[$name] = $value; } };
ob_start(); Bridge::serve(false, $r, $req, $server); $bytes = ob_get_clean();
$expected = hash_hmac('sha256', 'response' . "\n" . $req->headers['x-jysk-nonce'] . "\n409\n" . hash('sha256', $bytes), hex2bin(JYSK_GAME_SERVICE_KEY));
check(hash_equals($expected, $server->headers['x-jysk-signature']), 'signature covers actual error wire bytes');

// A real WordPress bootstrap emitted LF before the signed JSON. Keep the exact-wire
// consumer strict: remove only a bounded, inspectable whitespace prefix at the producer.
$db = freshDb();
$req = request('context', ['subject' => str_repeat('0', 64), 'sessionToken' => str_repeat('0', 64)]);
ob_start(); echo "\n \t\r\n";
$level = ob_get_level();
$r = Bridge::service($req);
check(ob_get_contents() === '' && ob_get_level() === $level, 'early whitespace removed without closing buffer');
echo "\n"; // Simulate later WordPress output after dispatch, before pre-serve.
Bridge::serve(false, $r, $req, $server);
$bytes = ob_get_clean();
check($bytes === '{"error":"invalid_session"}' && $r->status === 403, 'actual error body has no unsigned prefix');
$expected = hash_hmac('sha256', "response\n" . $req->headers['x-jysk-nonce'] . "\n403\n" . hash('sha256', $bytes), hex2bin(JYSK_GAME_SERVICE_KEY));
check(hash_equals($expected, $server->headers['x-jysk-signature']), 'HMAC covers exact emitted bytes after whitespace cleanup');

$db = freshDb();
$req = request('award', payload());
ob_start(); echo "\n";
$r = Bridge::service($req);
Bridge::serve(false, $r, $req, $server); $bytes = ob_get_clean();
check($r->status === 200 && json_decode($bytes, true)['state'] === 'confirmed', 'successful fixture response removes prefix without changing receipt');
$expected = hash_hmac('sha256', "response\n" . $req->headers['x-jysk-nonce'] . "\n200\n" . hash('sha256', $bytes), hex2bin(JYSK_GAME_SERVICE_KEY));
check(hash_equals($expected, $server->headers['x-jysk-signature']), 'successful response MAC covers exact emitted JSON');

foreach (["unexpected output", str_repeat(' ', 257)] as $prefix) {
    $db = freshDb();
    ob_start(); echo $prefix;
    $r = Bridge::service(request('context', []));
    $remaining = ob_get_clean();
    check($r->status === 503 && $db->log === [], 'unsafe prefix blocks service before any DB query');
    check($remaining === $prefix, 'unsafe prefix is not silently erased');
}
$db = freshDb();
ob_start(); echo "\n"; ob_start();
$r = Bridge::service(request('context', []));
$inner = ob_get_clean(); $outer = ob_get_clean();
check($r->status === 503 && $db->log === [] && $inner === '' && $outer === "\n", 'non-empty outer buffer fails closed without unwinding');
$db = freshDb();
ob_start(static fn($bytes) => $bytes);
$r = Bridge::service(request('context', [])); ob_end_clean();
check($r->status === 503 && $db->log === [], 'unknown output handler blocks before any DB query');
$db = freshDb();
ob_start(null, 0, PHP_OUTPUT_HANDLER_STDFLAGS & ~PHP_OUTPUT_HANDLER_CLEANABLE);
$r = Bridge::service(request('context', [])); ob_end_clean();
check($r->status === 503 && $db->log === [], 'non-cleanable buffer blocks before any DB query');

$db = freshDb();
$req = request('context', ['subject' => str_repeat('0', 64), 'sessionToken' => str_repeat('0', 64)]);
$r = Bridge::service($req);
ob_start(); echo 'late unexpected output';
Bridge::serve(false, $r, $req, $server); $bytes = ob_get_clean();
check($GLOBALS['status'] === 503 && $server->headers['x-jysk-signature'] === '', 'late unsafe output cannot retain a signed success');
ob_start(); echo "\n";
$unrelated = new FixtureRequest('/other/v1/read', []);
check(Bridge::serve(false, null, $unrelated, $server) === false && ob_get_contents() === "\n", 'unrelated REST output is untouched');
ob_end_clean();

$flushedFixture = <<<'PHP'
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function add_filter(...$args) {}
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
function status_header($status) { throw new RuntimeException('cannot rewrite flushed status'); }
class WP_REST_Response { public function __construct(public $data, public $status, public $headers) {} }
require getcwd() . '/wp-content/mu-plugins/impactshop-jysk-game.php';
$request = new class {
    public function get_route() { return '/jysk-game/v1/context'; }
    public function get_header($name) { return ''; }
};
$GLOBALS['wpdb'] = new class { public function __call($name, $args) { throw new RuntimeException('unexpected database call'); } };
echo "\n";
if (!headers_sent()) { exit(41); }
$response = Impactshop_Jysk_Game::service($request);
if ($response->status !== 503 || $response->data !== ['error' => 'bridge_unavailable']) { exit(42); }
$wire = new ReflectionProperty(Impactshop_Jysk_Game::class, 'wire');
$wire->setValue(null, [spl_object_hash($request) => ['{"ok":true}', 200, ['x-jysk-signature' => 'stale-signature']]]);
$server = new class { public function send_header($name, $value) { throw new RuntimeException('cannot rewrite flushed header'); } };
Impactshop_Jysk_Game::serve(false, $response, $request, $server);
PHP;
$child = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=stderr', '-r', $flushedFixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
check(is_resource($child), 'isolated already-flushed-header fixture starts');
$childOutput = stream_get_contents($pipes[1]); $childError = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]); $childExit = proc_close($child);
check($childExit === 0 && $childError === '' && $childOutput === "\n" . '{"error":"bridge_unavailable"}', 'flushed headers block DB and never attempt header mutation or emit signed success');

foreach (['INSERT INTO wp_jysk_game_rewards', 'COMMIT'] as $failure) {
    $db = freshDb(); $db->fail = $failure;
    $calls = Sharity_Points_Manager::$calls;
    check(Bridge::service(request('award', payload()))->status === 503, 'claim failure returns unavailable ' . $failure);
    check(Sharity_Points_Manager::$calls === $calls && $db->reconnect_retries === 5, 'claim failure prevents manager and restores config');
}
foreach (['throw', 'failure', 'disconnect'] as $behavior) {
    $db = freshDb(); Sharity_Points_Manager::$behavior = $behavior;
    $r = Bridge::service(request('award', payload()));
    check($r->status === 503 || ($r->data['state'] ?? '') === 'unknown', 'manager ambiguity never confirmed ' . $behavior);
    check($db->claim !== null && $db->reconnect_retries === 5, 'durable claim retained and retries restored');
    $db->connection = 71;
    $calls = Sharity_Points_Manager::$calls;
    Bridge::service(request('award', payload()));
    check(Sharity_Points_Manager::$calls === $calls, 'ambiguous call is not automatically retried');
    check(Bridge::service(request('reward-status', payload()))->data['state'] === 'absent', 'same writer connection lock and atomic schema can prove absence');
    Sharity_Points_Manager::$behavior = 'success';
    check(Bridge::service(request('award', payload()))->data['state'] === 'confirmed' && Sharity_Points_Manager::$calls === $calls + 1, 'explicit absent reconciliation permits same-key retry exactly once');
}
Sharity_Points_Manager::$behavior = 'success';
$db = freshDb(); $db->busy = true;
check(Bridge::service(request('award', payload()))->data['state'] === 'busy', 'busy lock is explicit');
check($db->reconnect_retries === 5, 'busy lock restores config');
$db = freshDb(); $db->owner = 72;
check(Bridge::service(request('award', payload()))->status === 503, 'lost lock ownership fails closed');
$db = freshDb(); $db->releaseThrows = true;
Bridge::service(request('award', payload()));
check($db->reconnect_retries === 5, 'release failure still restores reconnect setting');
$db = freshDb(); $db->grantActive = false;
check(Bridge::service(request('award', payload()))->data['state'] === 'policy_blocked', 'revoked owner grant blocks earning');
$db = freshDb();
check(Bridge::service(request('reward-status', payload()))->data['state'] === 'absent', 'only no claim and no receipt is absent');
foreach (['engine' => 'MyISAM', 'readOnly' => '1', 'missingIndex' => true] as $property => $value) {
    $db = freshDb(); $db->$property = $value;
    $calls = Sharity_Points_Manager::$calls;
    check(Bridge::service(request('award', payload()))->data['state'] === 'policy_blocked' && Sharity_Points_Manager::$calls === $calls, 'actual transaction readiness rejects ' . $property);
}
foreach (['points' => 1000, 'isTest' => true, 'policyHash' => str_repeat('f', 64), 'producerSha' => str_repeat('f', 40), 'ngoId' => 'other'] as $field => $value) {
    $db = freshDb(); $p = payload(); $p[$field] = $value;
    $calls = Sharity_Points_Manager::$calls;
    $r = Bridge::service(request('award', $p));
    check($r->status !== 200 || $r->data['state'] === 'policy_blocked', 'server-authoritative policy ' . $field);
    check(Sharity_Points_Manager::$calls === $calls, 'blocked policy never writes');
}
$p = payload(); $p['points'] = '5';
check(Bridge::service(request('award', $p))->data['error'] === 'invalid_payload', 'strict points type');
$p = payload(); $p['dayIndex'] = 5;
check(Bridge::service(request('award', $p))->data['error'] === 'invalid_payload', 'day schedule cannot be spoofed');
$p = payload(); $p['unknown'] = 1;
check(Bridge::service(request('award', $p))->data['error'] === 'invalid_payload', 'unexpected payload field rejected');
$p = array_reverse(payload(), true);
check(Bridge::payloadHash(Bridge::payload($p)) === Bridge::payloadHash(payload()), 'canonical property order');

$db = freshDb();
$req = request('context', []); $req->headers['x-jysk-signature'] = str_repeat('0', 64);
check(Bridge::service($req)->status === 401 && $db->nonces === [], 'bad MAC denied before nonce storage');
$req = request('context', []); $req->headers['x-jysk-timestamp'] = (string) ($GLOBALS['clock'] - 61);
check(Bridge::service($req)->status === 401, 'expired timestamp denied');

$GLOBALS['owner'] = false;
$r = Bridge::handoff(new FixtureRequest('/jysk-game/v1/handoff', ['client_id' => Bridge::CLIENT, 'redirect_uri' => Bridge::CALLBACK, 'state' => str_repeat('s', 43), 'code_challenge' => str_repeat('x', 43), 'code_challenge_method' => 'S256']));
check($r->status === 403, 'raw pseudo cookie is insufficient');
$GLOBALS['owner'] = true;
$db = freshDb();
$verifier = str_repeat('v', 64);
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
$db->code = ['subject' => str_repeat('a', 64), 'challenge' => $challenge, 'generation' => 'generation'];
$exchange = ['clientId' => Bridge::CLIENT, 'redirectUri' => Bridge::CALLBACK, 'code' => str_repeat('e', 64), 'codeVerifier' => $verifier];
$r = Bridge::service(request('exchange', $exchange));
check($r->status === 200 && strlen($r->data['sessionToken']) === 64, 'PKCE exchange issues opaque session');
check(strtotime($r->data['expiresAt']) === $GLOBALS['clock'] + 900, 'session has absolute 900 second expiry');
check(Bridge::service(request('exchange', $exchange))->status === 403, 'code consumed exactly once');
$session = ['subject' => $r->data['subject'], 'sessionToken' => $r->data['sessionToken']];
check(Bridge::service(request('context', $session))->status === 200, 'context validates session and owner');
$db->generation = 'new-owner-generation';
check(Bridge::service(request('context', $session))->status === 403, 'old session cannot revive on new handoff');
$db->generation = 'generation';
check(Bridge::service(request('messages', $session))->data === ['messages' => []], 'legacy messages not implicitly published');
check(Bridge::service(request('messages/delivered', $session + ['messageId' => '1']))->status === 403, 'unmapped delivery ack denied');
Bridge::service(request('revoke', $session));
check(Bridge::service(request('context', $session))->status === 403, 'revocation invalidates context');
define('JYSK_GAME_TEST_SUBJECTS', [str_repeat('a', 64)]);
$db = freshDb();
$p = payload(); $p['isTest'] = true; $p['dayIndex'] = 0; $p['occurredAt'] = '2026-11-09T10:59:59.000Z';
$p['eventId'] = hash('sha256', 'jysk_2026:' . $p['subject'] . ':quiz_answered:0:0');
$calls = Sharity_Points_Manager::$calls;
check(Bridge::service(request('award', $p))->data['error'] === 'invalid_payload' && Sharity_Points_Manager::$calls === $calls, 'approved technical subject has no before-noon bypass');
$originalClock = $GLOBALS['clock'];
$GLOBALS['clock'] = strtotime('2026-11-09T11:00:00Z') - 30;
$db = freshDb(); $p['occurredAt'] = '2026-11-09T11:00:00.000Z';
check(Bridge::service(request('award', $p))->data['state'] === 'policy_blocked' && Sharity_Points_Manager::$calls === $calls, '60 second occurrence tolerance never authorizes early real-server-clock writes');
$GLOBALS['clock'] += 30; $db = freshDb();
check(Bridge::service(request('award', $p))->data['state'] === 'confirmed', 'new award permitted exactly at real-server-clock campaign start');
$GLOBALS['clock'] = $originalClock;
$technicalPoints = 0;
for ($slot = 0; $slot < 6; $slot++) {
    $db = freshDb(); $p = payload(); $p['isTest'] = true;
    $db->technicalTotal = $technicalPoints;
    if ($slot === 5) { $p['kind'] = 'video_completed'; $p['points'] = 10; $p['slot'] = 0; } else { $p['slot'] = $slot; }
    $p['eventId'] = hash('sha256', 'jysk_2026:' . $p['subject'] . ':' . $p['kind'] . ':6:' . $p['slot']);
    $r = Bridge::service(request('award', $p));
    check($r->data['state'] === 'confirmed', 'operator-classified technical subject uses canonical writer after start');
    $technicalPoints += $r->data['points'];
    check(json_decode($db->receipt['metadata'], true)['is_test'] === true, 'technical global receipt remains classified');
}
check($technicalPoints === 35, 'technical canary receives 35 actual canonical-writer fixture points');
$db = freshDb(); $db->technicalTotal = 35; $p = payload(); $p['isTest'] = true; $p['dayIndex'] = 7;
$GLOBALS['clock'] += 86400; $p['occurredAt'] = '2026-11-16T11:00:00.000Z';
$p['eventId'] = hash('sha256', 'jysk_2026:' . $p['subject'] . ':quiz_answered:7:0');
$calls = Sharity_Points_Manager::$calls;
check(Bridge::service(request('award', $p))->data['state'] === 'policy_blocked' && Sharity_Points_Manager::$calls === $calls, 'technical profile total cap blocks next day');
$db = freshDb(); $db->technicalTotal = 30; $db->busy = true;
check(Bridge::service(request('award', $p))->data['state'] === 'busy', 'technical subject lock serializes controlled competing event');
$db = freshDb(); $db->technicalTotal = 30; $db->fail = 'SUM(GREATEST(points,0))';
check(Bridge::service(request('award', $p))->status === 503 && Sharity_Points_Manager::$calls === $calls, 'technical sum read error never awards');
$GLOBALS['clock'] -= 86400;
$db = freshDb();
check(Bridge::service(request('award', payload()))->data['state'] === 'policy_blocked', 'technical subject cannot erase its classification');
echo "PASS {$checks} deterministic assertions (no database integration claim)\n";
