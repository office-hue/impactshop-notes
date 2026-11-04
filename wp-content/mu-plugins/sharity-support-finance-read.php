<?php
/** Sharity display-only FX cache. No ledger, payout or per-GET provider requests.
 * Activation is explicit: enable own option and schedule the existing WP-Cron
 * daily hook. Loading this file neither enables nor schedules the refresh.
 */
declare(strict_types=1);

const SHARITY_SUPPORT_FX_CACHE_KEY = 'sharity_support_fx_quotes_v1';
const SHARITY_SUPPORT_FX_DAY_KEY = 'sharity_support_fx_day_v1';
const SHARITY_SUPPORT_FX_ENABLED_KEY = 'sharity_support_fx_enabled';

function sharity_support_fx_unavailable(): array
{
    return ['schema_version' => 'sharity-fx-quotes-v1', 'status' => 'unavailable', 'base_currency' => 'HUF'];
}

/** One validation path for publication and reading; no normalized invalid dates. */
function sharity_support_fx_payload($saved, ?int $now = null): array
{
    $unavailable = sharity_support_fx_unavailable();
    if (!is_array($saved) || !function_exists('sharity_points_money_parse')) { return $unavailable; }
    $now = $now ?? time();
    $stamp = $saved['fetched_at'] ?? null;
    if (!is_string($stamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $stamp)) { return $unavailable; }
    $fetched = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $stamp, new DateTimeZone('UTC'));
    if (!$fetched || $fetched->format('Y-m-d\TH:i:s\Z') !== $stamp ||
        $fetched->getTimestamp() > $now || $now - $fetched->getTimestamp() > 36 * 3600) { return $unavailable; }
    $zone = new DateTimeZone('Europe/Budapest');
    $today = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
    $firstDay = $today->modify('-7 days')->format('Y-m-d');
    $lastDay = $fetched->setTimezone($zone)->format('Y-m-d');
    $map = [];
    $date = null;
    if (!is_array($saved['quotes'] ?? null)) { return $unavailable; }
    foreach (['EUR', 'USD', 'GBP'] as $currency) {
        $q = $saved['quotes'][$currency] ?? null;
        if (!is_array($q) || ($q['success'] ?? null) !== true || ($q['currency'] ?? null) !== $currency ||
            !is_string($q['rate'] ?? null) || !is_string($q['unit'] ?? null) ||
            !preg_match('/^10{0,6}$/D', $q['unit']) || !is_string($q['date'] ?? null) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $q['date'])) { return $unavailable; }
        $parsed = sharity_points_money_parse($q['rate']);
        if (!$parsed['success'] || $parsed['zero']) { return $unavailable; }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $q['date'], $zone);
        if (!$day || $day->format('Y-m-d') !== $q['date'] || $q['date'] < $firstDay || $q['date'] > $lastDay ||
            ($date !== null && $date !== $q['date'])) { return $unavailable; }
        $date = $q['date'];
        $map[$currency] = ['rate' => $q['rate'], 'unit' => $q['unit']];
    }
    return ['schema_version' => 'sharity-fx-quotes-v1', 'status' => 'current', 'base_currency' => 'HUF',
        'fetched_at' => $stamp, 'quote_date' => $date, 'quotes' => $map]; // HUF implicit 1/1.
}

/** A single non-autoload option, atomic insert or exact prior-day CAS. This
 * prevents concurrent cron/CLI refreshes from exceeding three requests/day.
 */
function sharity_support_fx_claim_day(string $today): bool
{
    global $wpdb;
    if (add_option(SHARITY_SUPPORT_FX_DAY_KEY, $today, '', false)) { return true; }
    // Read DB rather than a process-local stale option cache before CAS.
    $old = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", SHARITY_SUPPORT_FX_DAY_KEY));
    if (!is_string($old) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $old) || $old >= $today) { return false; }
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        $today, SHARITY_SUPPORT_FX_DAY_KEY, $old
    ));
    if ($changed !== 1) { return false; }
    wp_cache_delete(SHARITY_SUPPORT_FX_DAY_KEY, 'options');
    return true;
}

/** Explicit cron/CLI entry point. Failures consume today's attempt, retain old
 * cache without re-timestamping it, and return an inspectable reason. No retry.
 */
function sharity_support_fx_refresh(): array
{
    if (get_option(SHARITY_SUPPORT_FX_ENABLED_KEY, '0') !== '1') { return ['status' => 'skipped', 'reason' => 'disabled']; }
    if (!function_exists('sharity_points_mnb_quote') || !function_exists('sharity_points_money_parse')) {
        return ['status' => 'unavailable', 'reason' => 'source_missing'];
    }
    $now = time();
    $today = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d');
    if (!sharity_support_fx_claim_day($today)) { return ['status' => 'skipped', 'reason' => 'day_not_claimed']; }
    $stamp = gmdate('Y-m-d\TH:i:s\Z', $now);
    $quotes = [];
    try {
        foreach (['EUR', 'USD', 'GBP'] as $currency) {
            $q = sharity_points_mnb_quote($currency, $stamp); // Existing fixed MNB origin, timeout10s, no redirects.
            if (($q['success'] ?? false) !== true) { return ['status' => 'unavailable', 'reason' => 'source_failed']; }
            $quotes[$currency] = $q;
        }
    } catch (Throwable $error) { return ['status' => 'unavailable', 'reason' => 'source_failed']; }
    $saved = ['fetched_at' => $stamp, 'quotes' => $quotes];
    $payload = sharity_support_fx_payload($saved);
    if ($payload['status'] !== 'current') { return ['status' => 'unavailable', 'reason' => 'quotes_invalid']; }
    if (!update_option(SHARITY_SUPPORT_FX_CACHE_KEY, $saved, false)) {
        return ['status' => 'unavailable', 'reason' => 'cache_write_unconfirmed'];
    }
    return ['status' => 'current', 'fetched_at' => $stamp, 'quote_date' => $payload['quote_date']];
}

function sharity_support_fx_get(): WP_REST_Response
{
    $response = new WP_REST_Response(sharity_support_fx_payload(get_option(SHARITY_SUPPORT_FX_CACHE_KEY)), 200);
    // The origin reads a cache; prevent an intermediary extending its freshness.
    $response->header('Cache-Control', 'no-store');
    return $response;
}

add_action('sharity_support_fx_refresh', 'sharity_support_fx_refresh');
add_action('rest_api_init', static function (): void {
    register_rest_route('sharity-finance/v1', '/fx-quotes', [
        'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'sharity_support_fx_get',
    ]);
});
