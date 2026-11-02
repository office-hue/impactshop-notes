<?php
/**
 * Sharity donations: package-price foundation. No payment, wallet or ledger writer.
 * The read route exposes prices, never payment availability or connected accounts.
 * Weekly publication is opt-in and runs AFTER the existing FX refresh hook.
 */
declare(strict_types=1);

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
