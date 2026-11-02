<?php
/**
 * Sharity donations: prices, owner-bound quotes and disabled-by-default checkout.
 * No wallet, allocation or ledger writer. Checkout status is not payment proof.
 * The read route exposes prices, never payment availability or connected accounts.
 * Weekly publication is opt-in and runs AFTER the existing FX refresh hook.
 */
declare(strict_types=1);

// Dependency-first exact-file release: an incomplete bundle must not break
// existing prices/quotes. Loading definitions does not load Stripe or migrate.
add_action('plugins_loaded', static function (): void {
    $directory = __DIR__ . '/sharity-ngo-donations/';
    $files = ['stripe-checkout.php', 'orders.php', 'runtime.php', 'http.php'];
    foreach ($files as $file) { if (!is_readable($directory . $file)) { return; } }
    foreach ($files as $file) { require_once $directory . $file; }
    // Additive receiver: a missing new file must not remove existing callbacks.
    if (is_readable($directory . 'webhook.php')) { require_once $directory . 'webhook.php'; }
    if (is_readable($directory . 'finance-read.php')) { require_once $directory . 'finance-read.php'; }
});

const SHARITY_DONATION_PRICES_ENABLED = 'sharity_donation_prices_enabled';

function sharity_donation_packages(): ?array
{
    $path = __DIR__ . '/sharity-ngo-donations/amplifier-packages.json';
    $raw = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || ($data['schema_version'] ?? '') !== 'sharity-amplifier-packages-v1'
        || ($data['version'] ?? '') !== 'amplifier-net80-v1' || !is_array($data['packages'] ?? null)) {
        return null;
    }
    // Exact approved policy, independent of NGO tier and donor multiplier.
    $policy = [
        ['starter', 'Starter', 500, 500, 0], ['supporter', 'Supporter', 1000, 1000, 100],
        ['champion', 'Champion', 2500, 2500, 500], ['hero', 'Hero', 5000, 5000, 1500],
        ['legend', 'Legend', 10000, 10000, 5000],
    ];
    if (count($data['packages']) !== count($policy)) { return null; }
    foreach ($policy as $i => $expected) {
        $p = $data['packages'][$i] ?? [];
        if ([$p['id'] ?? null, $p['name'] ?? null, $p['huf_whole'] ?? null,
            $p['base_votes'] ?? null, $p['bonus_votes'] ?? null] !== $expected) { return null; }
    }
    return $data;
}

function sharity_donation_price_week(int $now): array
{
    $local = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Budapest'));
    $start = $local->setTime(0, 0)->modify('-' . ((int) $local->format('N') - 1) . ' days');
    return ['id' => $start->format('Y-m-d'), 'start' => $start->getTimestamp(),
        'end' => $start->modify('+7 days')->getTimestamp()];
}

/** MNB rate per unit, exact decimal rational. Bounds keep multiplication inside
 * PHP 64-bit integers for every approved package, even a million-unit quote. */
function sharity_donation_whole_price(int $huf, $quote): ?int
{
    if (PHP_INT_SIZE < 8 || !is_array($quote) || !is_string($quote['rate'] ?? null)
        || !preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,6}))?$/D', $quote['rate'], $parts)
        || !is_string($quote['unit'] ?? null) || !preg_match('/^10{0,6}$/D', $quote['unit'])
        || $huf < 1 || $huf > 10000) { return null; }
    $fraction = $parts[2] ?? '';
    $scale = 10 ** strlen($fraction);
    $denominator = (int) $parts[1] * $scale + (int) $fraction;
    if ($denominator <= 0) { return null; }
    $numerator = $huf * (int) $quote['unit'] * $scale;
    $whole = intdiv($numerator, $denominator);
    $remainder = $numerator % $denominator;
    if ($remainder >= intdiv($denominator, 2) + ($denominator % 2)) { $whole++; }
    // Match public exact-money representation bound; never cast an overflow.
    return $whole <= 9999999999 ? max(1, $whole) : null;
}

function sharity_donation_huf_book(array $manifest): array
{
    $items = [];
    foreach ($manifest['packages'] as $p) {
        $items[] = ['id' => $p['id'], 'name' => $p['name'], 'amount_minor' => (string) ($p['huf_whole'] * 100),
            'votes' => $p['base_votes'] + $p['bonus_votes']];
    }
    return ['schema_version' => 'sharity-amplifier-prices-v1', 'version' => $manifest['version'] . ':HUF',
        'currency' => 'HUF', 'valid_until' => null, 'packages' => $items];
}

/** Pure weekly builder. It accepts ONLY the existing validated FX cache, never
 * a live rate call. Publication keeps the first complete book for that week. */
function sharity_donation_build_price_books($cache, int $now): ?array
{
    $manifest = sharity_donation_packages();
    if ($manifest === null || !function_exists('sharity_support_fx_payload')) { return null; }
    $fx = sharity_support_fx_payload($cache, $now);
    if (($fx['status'] ?? '') !== 'current') { return null; }
    $week = sharity_donation_price_week($now);
    $version = $manifest['version'] . ':' . $week['id'] . ':' . substr(hash('sha256', json_encode($fx)), 0, 16);
    $books = [];
    foreach (['EUR', 'USD', 'GBP'] as $currency) {
        $items = [];
        foreach ($manifest['packages'] as $p) {
            $price = sharity_donation_whole_price($p['huf_whole'], $fx['quotes'][$currency] ?? null);
            if ($price === null) { return null; }
            $items[] = ['id' => $p['id'], 'name' => $p['name'], 'amount_minor' => (string) ($price * 100),
                'votes' => $p['base_votes'] + $p['bonus_votes']];
        }
        $books[$currency] = ['schema_version' => 'sharity-amplifier-prices-v1', 'version' => $version,
            'currency' => $currency, 'valid_until' => gmdate('Y-m-d\TH:i:s\Z', $week['end']), 'packages' => $items];
    }
    return ['week' => $week['id'], 'policy_version' => $manifest['version'],
        'fx' => $fx, 'books' => $books, 'published_at' => gmdate('Y-m-d\TH:i:s\Z', $now)];
}

function sharity_donation_publish_price_books(): array
{
    if (get_option(SHARITY_DONATION_PRICES_ENABLED, '0') !== '1') { return ['status' => 'disabled']; }
    $now = time();
    $key = 'sharity_donation_prices_' . sharity_donation_price_week($now)['id'];
    if (get_option($key, false) !== false) { return ['status' => 'already_published']; }
    $saved = sharity_donation_build_price_books(get_option('sharity_support_fx_quotes_v1', null), $now);
    if ($saved === null) { return ['status' => 'unavailable']; }
    // Unique option insert: concurrent refreshers cannot replace accepted prices.
    $inserted = add_option($key, $saved, '', false);
    $readback = get_option($key, null);
    return ['status' => $inserted && $readback === $saved ? 'published' : 'publication_unconfirmed'];
}

function sharity_donation_read_price_books(?int $now = null): array
{
    $now = $now ?? time();
    $manifest = sharity_donation_packages();
    if ($manifest === null) { return []; }
    $books = ['HUF' => sharity_donation_huf_book($manifest)];
    if (get_option(SHARITY_DONATION_PRICES_ENABLED, '0') !== '1') { return $books; }
    $week = sharity_donation_price_week($now);
    $saved = get_option('sharity_donation_prices_' . $week['id'], null);
    if (!is_array($saved) || ($saved['week'] ?? null) !== $week['id']
        || ($saved['policy_version'] ?? null) !== $manifest['version']
        || !is_string($saved['published_at'] ?? null)) { return $books; }
    $published = strtotime($saved['published_at']);
    if ($published === false || $published < $week['start'] || $published > $now) { return $books; }
    // Revalidate at publication time, NOT today's rolling FX age: price is fixed
    // for the whole week, although display FX refreshes daily.
    $rebuilt = sharity_donation_build_price_books([
        'fetched_at' => $saved['fx']['fetched_at'] ?? null,
        'quotes' => array_map(static function ($currency) use ($saved): array {
            return ['success' => true, 'currency' => $currency,
                'rate' => $saved['fx']['quotes'][$currency]['rate'] ?? null,
                'unit' => $saved['fx']['quotes'][$currency]['unit'] ?? null,
                'date' => $saved['fx']['quote_date'] ?? null];
        }, array_combine(['EUR', 'USD', 'GBP'], ['EUR', 'USD', 'GBP'])),
    ], $published);
    if ($rebuilt === null || ($saved['books'] ?? null) !== $rebuilt['books']) { return $books; }
    return $books + $rebuilt['books'];
}

add_action('sharity_support_fx_refresh', 'sharity_donation_publish_price_books', 20);
add_action('rest_api_init', static function (): void {
    register_rest_route('sharity/v1', '/donations/packages', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => static function (): WP_REST_Response {
            $response = new WP_REST_Response(['ok' => true, 'books' => sharity_donation_read_price_books()]);
            $response->header('Cache-Control', 'no-store');
            return $response;
        },
    ]);
});

// Immutable quote preparation only. Stripe checkout consumes this snapshot in D2;
// no payment, vote or financial posting is created by the quote endpoint.
const SHARITY_DONATION_QUOTES_ENABLED = 'sharity_donation_quotes_enabled';
const SHARITY_DONATION_QUOTES_SCHEMA_OPTION = 'sharity_donation_quotes_schema_v1';

function sharity_donation_quote_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sharity_donation_quotes_v1';
}

function sharity_donation_quote_private(array $body, int $status = 200): WP_REST_Response
{
    $response = new WP_REST_Response($body, $status);
    $response->header('Cache-Control', 'private, no-store');
    $response->header('Vary', 'Authorization');
    return $response;
}

function sharity_donation_quote_error(string $code, int $status): WP_REST_Response
{
    return sharity_donation_quote_private(['ok' => false, 'error' => ['code' => $code]], $status);
}

function sharity_donation_quote_minor($amount, string $currency): bool
{
    return PHP_INT_SIZE >= 8 && is_string($amount)
        && preg_match('/^[1-9][0-9]{0,11}$/D', $amount) === 1
        && in_array($currency, ['HUF', 'EUR', 'USD', 'GBP'], true)
        && ($currency !== 'HUF' || (int) $amount % 100 === 0);
}

function sharity_donation_quote_input($value): ?array
{
    if (!is_array($value) || !is_string($value['ngo_slug'] ?? null)
        || strlen($value['ngo_slug']) > 100
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value['ngo_slug'])
        || !in_array($value['currency'] ?? null, ['HUF', 'EUR', 'USD', 'GBP'], true)) { return null; }
    $mode = $value['donation_mode'] ?? null;
    $keys = array_keys($value); sort($keys);
    if ($mode === 'direct') {
        if ($keys !== ['amount_minor', 'currency', 'donation_mode', 'ngo_slug']
            || !sharity_donation_quote_minor($value['amount_minor'], $value['currency'])) { return null; }
    } elseif ($mode === 'amplifier') {
        if ($keys !== ['currency', 'donation_mode', 'ngo_slug', 'package_id', 'price_book_version']
            || !in_array($value['package_id'], ['starter', 'supporter', 'champion', 'hero', 'legend'], true)
            || !is_string($value['price_book_version'])
            || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $value['price_book_version'])) { return null; }
    } else { return null; }
    ksort($value);
    return $value;
}

function sharity_donation_quote_key_hash(string $subject, string $key): string
{
    return hash('sha256', "sharity-donation-quote-v1\0" . $subject . "\0" . $key);
}

function sharity_donation_quote_timestamp($value): ?int
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value)) { return null; }
    $time = strtotime($value);
    return $time !== false && gmdate('Y-m-d\TH:i:s\Z', $time) === $value ? $time : null;
}

/** A private binding is an activation input, not evidence by itself. The later
 * verification command must populate it from account/fee/liability readbacks. */
function sharity_donation_quote_binding(array $input, int $now): ?array
{
    $all = get_option('sharity_donation_accounts_v1', null);
    $binding = is_array($all) ? ($all[$input['ngo_slug']] ?? null) : null;
    if (!is_array($binding) || !is_string($binding['binding_version'] ?? null)
        || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $binding['binding_version'])
        || !is_string($binding['account_id'] ?? null)
        || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $binding['account_id'])
        || !is_bool($binding['livemode'] ?? null)
        || (sharity_donation_quote_timestamp($binding['verified_until'] ?? null) ?? 0) <= $now
        || !is_array($binding['modes'] ?? null) || ($binding['modes'][$input['donation_mode']] ?? null) !== true
        || !is_array($binding['currencies'] ?? null)) { return null; }
    $bounds = $binding['currencies'][$input['currency']] ?? null;
    if (!is_array($bounds) || !sharity_donation_quote_minor($bounds['min_minor'] ?? null, $input['currency'])
        || !sharity_donation_quote_minor($bounds['max_minor'] ?? null, $input['currency'])
        || (int) $bounds['min_minor'] > (int) $bounds['max_minor']) { return null; }
    return $binding;
}

function sharity_donation_quote_read(string $subject, string $keyHash): ?array
{
    global $wpdb;
    $table = sharity_donation_quote_table();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT quote_id, request_digest, subject_ref, snapshot_json, expires_at_utc FROM `{$table}` WHERE request_key_hash = %s AND subject_ref = %s LIMIT 1",
        $keyHash, $subject
    ), ARRAY_A);
    if ($wpdb->last_error !== '') { throw new RuntimeException('quote_read_unavailable'); }
    return is_array($row) ? $row : null;
}

function sharity_donation_quote_replay(array $row, string $digest, int $now): WP_REST_Response
{
    if (!hash_equals((string) $row['request_digest'], $digest)) { return sharity_donation_quote_error('IDEMPOTENCY_CONFLICT', 409); }
    $expiry = strtotime((string) $row['expires_at_utc'] . ' UTC');
    if ($expiry === false) { return sharity_donation_quote_error('QUOTE_UNAVAILABLE', 503); }
    if ($expiry <= $now) { return sharity_donation_quote_error('QUOTE_EXPIRED', 410); }
    $snapshot = json_decode((string) $row['snapshot_json'], true);
    $publicKeys = ['ngo_slug', 'donation_mode', 'currency', 'amount_minor', 'votes', 'price_book_version', 'policy_version'];
    if (!is_array($snapshot) || array_diff($publicKeys, array_keys($snapshot))
        || !is_string($snapshot['currency']) || !sharity_donation_quote_minor($snapshot['amount_minor'], $snapshot['currency'])
        || !preg_match('/^dq_[a-f0-9]{32}$/D', (string) $row['quote_id'])) { return sharity_donation_quote_error('QUOTE_UNAVAILABLE', 503); }
    $quote = array_intersect_key($snapshot, array_flip($publicKeys));
    $quote['id'] = $row['quote_id'];
    $quote['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', $expiry);
    return sharity_donation_quote_private(['ok' => true, 'quote' => $quote]);
}

function sharity_donation_quote_handle(WP_REST_Request $request): WP_REST_Response
{
    if (!is_callable(['ImpactShop_Sharity_Web_Session', 'authenticate_request'])) { return sharity_donation_quote_error('AUTHORITY_UNAVAILABLE', 503); }
    $session = ImpactShop_Sharity_Web_Session::authenticate_request($request);
    if ($session instanceof WP_REST_Response) {
        $session->header('Cache-Control', 'private, no-store');
        $session->header('Vary', 'Authorization');
        return $session;
    }
    if (!is_array($session) || !is_string($session['subject_ref'] ?? null)
        || !preg_match('/^hmac-sha256:[a-f0-9]{64}$/D', $session['subject_ref'])) { return sharity_donation_quote_error('UNAUTHORIZED', 401); }
    if (get_option(SHARITY_DONATION_QUOTES_ENABLED, '0') !== '1'
        || get_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION, '') !== '1') { return sharity_donation_quote_error('QUOTES_DISABLED', 503); }
    $key = $request->get_header('idempotency-key');
    $input = sharity_donation_quote_input($request->get_json_params());
    if (!is_string($key) || !preg_match('/^[A-Za-z0-9._:-]{16,80}$/D', $key) || $input === null) { return sharity_donation_quote_error('INVALID_REQUEST', 400); }
    $subject = $session['subject_ref'];
    $keyHash = sharity_donation_quote_key_hash($subject, $key);
    $digest = hash('sha256', json_encode($input));
    $now = time();
    try {
        $existing = sharity_donation_quote_read($subject, $keyHash);
        if ($existing !== null) { return sharity_donation_quote_replay($existing, $digest, $now); }
        $binding = sharity_donation_quote_binding($input, $now);
        if ($binding === null) { return sharity_donation_quote_error('NGO_UNAVAILABLE', 503); }
        $amount = $input['amount_minor'] ?? null;
        $votes = 0;
        $bookVersion = null;
        if ($input['donation_mode'] === 'amplifier') {
            $book = sharity_donation_read_price_books($now)[$input['currency']] ?? null;
            if (!is_array($book) || $book['version'] !== $input['price_book_version']) { return sharity_donation_quote_error('PRICE_UNAVAILABLE', 409); }
            foreach ($book['packages'] as $package) {
                if ($package['id'] === $input['package_id']) { $amount = $package['amount_minor']; $votes = $package['votes']; }
            }
            $bookVersion = $book['version'];
        }
        $bounds = $binding['currencies'][$input['currency']];
        if (!sharity_donation_quote_minor($amount, $input['currency'])
            || (int) $amount < (int) $bounds['min_minor'] || (int) $amount > (int) $bounds['max_minor']) { return sharity_donation_quote_error('AMOUNT_UNAVAILABLE', 400); }
        $snapshot = [
            'ngo_slug' => $input['ngo_slug'], 'donation_mode' => $input['donation_mode'],
            'currency' => $input['currency'], 'amount_minor' => $amount, 'votes' => $votes,
            'package_id' => $input['package_id'] ?? null, 'price_book_version' => $bookVersion,
            'policy_version' => $input['donation_mode'] === 'direct' ? 'direct-net100-v1' : 'amplifier-net80-v1',
            'account_id' => $binding['account_id'], 'binding_version' => $binding['binding_version'], 'livemode' => $binding['livemode'],
        ];
        global $wpdb;
        // Atomic single-row INSERT + unique key is the only write. A transport
        // failure can mean committed: read the same owner/key before responding.
        try { $wpdb->insert(sharity_donation_quote_table(), [
            'quote_id' => 'dq_' . bin2hex(random_bytes(16)), 'request_key_hash' => $keyHash,
            'request_digest' => $digest, 'subject_ref' => $subject, 'snapshot_json' => json_encode($snapshot),
            'created_at_utc' => gmdate('Y-m-d H:i:s', $now), 'expires_at_utc' => gmdate('Y-m-d H:i:s', $now + 1800),
        ], array_fill(0, 7, '%s')); } catch (Throwable $e) { /* Commit may have succeeded; read back below. */ }
        $stored = sharity_donation_quote_read($subject, $keyHash);
        return $stored === null ? sharity_donation_quote_error('QUOTE_UNCONFIRMED', 503)
            : sharity_donation_quote_replay($stored, $digest, time());
    } catch (Throwable $e) {
        return sharity_donation_quote_error('QUOTE_UNAVAILABLE', 503);
    }
}

/** Additive explicit CLI schema. Never invoked from HTTP, cron or module load. */
function sharity_donation_quote_schema_apply(): bool
{
    // Explicit migration first invalidates its own readiness marker. A failed
    // verification must not retain a stale permission to create new quotes.
    update_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION, '0', false);
    if (get_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION, '') !== '0') { return false; }
    global $wpdb;
    $table = sharity_donation_quote_table();
    $result = $wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        quote_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        request_key_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        request_digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        subject_ref varchar(76) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        snapshot_json longtext NOT NULL,
        created_at_utc datetime NOT NULL,
        expires_at_utc datetime NOT NULL,
        PRIMARY KEY (quote_id), UNIQUE KEY request_key_hash (request_key_hash), KEY expiry (expires_at_utc)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    if ($result === false) { return false; }
    $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
    $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
    $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
    if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB' || !is_array($columns) || !is_array($indexes)) { return false; }
    $expected = ['quote_id' => ['char(35)', 'ascii_bin'], 'request_key_hash' => ['char(64)', 'ascii_bin'],
        'request_digest' => ['char(64)', 'ascii_bin'], 'subject_ref' => ['varchar(76)', 'ascii_bin'],
        'snapshot_json' => ['longtext', 'utf8mb4_bin'], 'created_at_utc' => ['datetime', null], 'expires_at_utc' => ['datetime', null]];
    if (count($columns) !== count($expected)) { return false; }
    foreach ($columns as $column) {
        if (!isset($expected[$column['Field']]) || [$column['Type'], $column['Collation']] !== $expected[$column['Field']]
            || $column['Null'] !== 'NO' || $column['Default'] !== null || $column['Extra'] !== '') { return false; }
    }
    $topology = array_map(static function ($index): array {
        return [$index['Key_name'], (int) $index['Seq_in_index'], $index['Column_name'], (int) $index['Non_unique'], $index['Sub_part']];
    }, $indexes);
    sort($topology);
    $expectedTopology = [['PRIMARY', 1, 'quote_id', 0, null], ['request_key_hash', 1, 'request_key_hash', 0, null], ['expiry', 1, 'expires_at_utc', 1, null]];
    sort($expectedTopology);
    if ($topology !== $expectedTopology) { return false; }
    update_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION, '1', false);
    return get_option(SHARITY_DONATION_QUOTES_SCHEMA_OPTION, '') === '1';
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations quote-schema', static function (array $args, array $assoc): void {
        if (!array_key_exists('apply', $assoc)) { WP_CLI::error('Explicit --apply required; no change made.'); return; }
        if (!sharity_donation_quote_schema_apply()) { WP_CLI::error('Quote schema incompatible or unconfirmed; no existing schema altered.'); return; }
        WP_CLI::success('Quote schema verified. Quotes remain controlled by their separate disabled-by-default switch.');
    });
}
add_action('rest_api_init', static function (): void {
    register_rest_route('sharity/v1', '/donations/quote', [
        'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'sharity_donation_quote_handle',
    ]);
});
