<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// ayeT Offerwall callback handler (pending ledger + immediate rewards)
// ============================================================

const IMPACTSHOP_AYET_PROVIDER = 'ayet';

if (!defined('AYET_API_KEY')) {
    define('AYET_API_KEY', getenv('AYET_API_KEY') ?: '');
}
if (!defined('AYET_MAX_AMOUNT_HUF')) {
    define('AYET_MAX_AMOUNT_HUF', 50000);
}
if (!defined('AYET_RATE_LIMIT_IP_RPM')) {
    define('AYET_RATE_LIMIT_IP_RPM', 60);
}
if (!defined('AYET_RATE_LIMIT_PSEUDO_RPM')) {
    define('AYET_RATE_LIMIT_PSEUDO_RPM', 10);
}
if (!defined('AYET_OFFERWALL_ADSLOT')) {
    define('AYET_OFFERWALL_ADSLOT', getenv('AYET_OFFERWALL_ADSLOT') ?: '');
}
if (!defined('AYET_OFFERWALL_ADSLOT_FALLBACK')) {
    define('AYET_OFFERWALL_ADSLOT_FALLBACK', '25643');
}
if (!defined('AYET_SURVEYWALL_ADSLOT')) {
    define('AYET_SURVEYWALL_ADSLOT', getenv('AYET_SURVEYWALL_ADSLOT') ?: '25740');
}
if (!defined('AYET_SURVEYWALL_PROFILE_HASH')) {
    define('AYET_SURVEYWALL_PROFILE_HASH', getenv('AYET_SURVEYWALL_PROFILE_HASH') ?: 'b970533bbaf884d085d7c0e6734da1c2');
}
if (!defined('AYET_OFFERWALL_API_BASE')) {
    define('AYET_OFFERWALL_API_BASE', 'https://www.ayetstudios.com/offers/offerwall_api/');
}
if (!defined('AYET_SURVEYWALL_API_BASE')) {
    define('AYET_SURVEYWALL_API_BASE', 'https://www.ayetstudios.com/surveys/surveywall_api/');
}
if (!defined('AYET_OFFERWALL_CACHE_TTL')) {
    define('AYET_OFFERWALL_CACHE_TTL', 45);
}
if (!defined('AYET_SURVEYWALL_CACHE_TTL')) {
    define('AYET_SURVEYWALL_CACHE_TTL', 45);
}

// --- Calibrated reward constants (UX-REWARD-PLAN §1.2) ---
if (!defined('AYET_POINTS_MULTIPLIER')) {
    define('AYET_POINTS_MULTIPLIER', 50);      // pont = ceil(payout_usd × 50)
}
if (!defined('AYET_VOTES_MULTIPLIER')) {
    define('AYET_VOTES_MULTIPLIER', 10);        // szavazat = ceil(payout_usd × 10)
}
if (!defined('AYET_MIN_POINTS')) {
    define('AYET_MIN_POINTS', 10);              // minimum pont / tranzakció
}
if (!defined('AYET_MIN_VOTES')) {
    define('AYET_MIN_VOTES', 5);                // minimum szavazat / tranzakció
}
if (!defined('AYET_MAX_POINTS_PER_TX')) {
    define('AYET_MAX_POINTS_PER_TX', 2000);     // max pont / tranzakció
}
if (!defined('AYET_MAX_VOTES_PER_TX')) {
    define('AYET_MAX_VOTES_PER_TX', 500);       // max szavazat / tranzakció
}

// --- Daily abuse caps (UX-REWARD-PLAN §4.4) ---
if (!defined('AYET_DAILY_POINTS_CAP')) {
    define('AYET_DAILY_POINTS_CAP', 1000);      // max pont / nap / pseudo_id
}
if (!defined('AYET_DAILY_VOTES_CAP')) {
    define('AYET_DAILY_VOTES_CAP', 100);        // max szavazat / nap / pseudo_id
}
if (!defined('AYET_DAILY_TX_CAP')) {
    define('AYET_DAILY_TX_CAP', 50);            // max tranzakció / nap / pseudo_id
}

add_action('rest_api_init', function (): void {
    register_rest_route('impact/v1', '/ayet-surveys', [
        'methods' => 'GET',
        'callback' => 'impactshop_ayet_surveys',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/ayet-callback', [
        'methods' => 'GET',
        'callback' => 'impactshop_ayet_callback',
        'permission_callback' => '__return_true',
    ]);
});

function impactshop_ayet_offerwall_cache_key(string $pseudo_id, string $adslot, string $ua_key = ''): string
{
    return 'impactshop_ayet_offers_' . md5($pseudo_id . '|' . $adslot . '|' . $ua_key);
}

function impactshop_ayet_parse_adslot_from_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $query = (string) parse_url($url, PHP_URL_QUERY);
    if ($query !== '') {
        parse_str($query, $params);
        foreach (['adSlot', 'adslot', 'placementId', 'placement_id'] as $key) {
            $value = isset($params[$key]) ? trim((string) $params[$key]) : '';
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function impactshop_ayet_get_admin_configured_adslot(): string
{
    if (!function_exists('impactshop_offerwall_get_providers')) {
        return '';
    }

    $providers = impactshop_offerwall_get_providers();
    $ayet = is_array($providers['ayet'] ?? null) ? $providers['ayet'] : [];
    return impactshop_ayet_parse_adslot_from_url((string) ($ayet['iframe_url'] ?? ''));
}

function impactshop_ayet_get_effective_adslot(): string
{
    $candidates = [
        trim((string) AYET_OFFERWALL_ADSLOT),
        impactshop_ayet_get_admin_configured_adslot(),
        trim((string) AYET_OFFERWALL_ADSLOT_FALLBACK),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function impactshop_ayet_get_effective_surveywall_adslot(): string
{
    $candidate = trim((string) AYET_SURVEYWALL_ADSLOT);
    return $candidate !== '' ? $candidate : '';
}

function impactshop_ayet_get_effective_surveywall_profile_hash(): string
{
    $candidate = trim((string) AYET_SURVEYWALL_PROFILE_HASH);
    return $candidate !== '' ? $candidate : '';
}

function impactshop_ayet_get_adslot_diagnostics(): array
{
    $env = trim((string) AYET_OFFERWALL_ADSLOT);
    $fallback = trim((string) AYET_OFFERWALL_ADSLOT_FALLBACK);
    $admin = impactshop_ayet_get_admin_configured_adslot();
    $effective = '';
    $source = '';

    if ($env !== '') {
        $effective = $env;
        $source = 'env';
    } elseif ($admin !== '') {
        $effective = $admin;
        $source = 'admin';
    } elseif ($fallback !== '') {
        $effective = $fallback;
        $source = 'fallback';
    }

    return [
        'effective' => $effective,
        'source' => $source,
        'env' => $env,
        'fallback' => $fallback,
        'admin' => $admin,
        'env_active' => $env !== '',
        'admin_mismatch' => ($admin !== '' && $effective !== '' && $admin !== $effective),
        'using_fallback' => ($source === 'fallback'),
    ];
}

function impactshop_ayet_get_surveywall_diagnostics(): array
{
    $adslot = impactshop_ayet_get_effective_surveywall_adslot();
    $profile_hash = impactshop_ayet_get_effective_surveywall_profile_hash();

    return [
        'effective' => $adslot,
        'env' => trim((string) AYET_SURVEYWALL_ADSLOT),
        'profile_hash_configured' => $profile_hash !== '',
        'active' => $adslot !== '',
    ];
}

function impactshop_ayet_offerwall_flush_cache(string $pseudo_id): void
{
    $adslot = impactshop_ayet_get_effective_adslot();
    if ($adslot === '') {
        return;
    }
    delete_transient(impactshop_ayet_offerwall_cache_key($pseudo_id, $adslot));
}

function impactshop_ayet_surveywall_cache_key(string $pseudo_id, string $adslot, string $ua_key = ''): string
{
    return 'impactshop_ayet_surveys_' . md5($pseudo_id . '|' . $adslot . '|' . $ua_key);
}

function impactshop_ayet_surveywall_flush_cache(string $pseudo_id): void
{
    $adslot = impactshop_ayet_get_effective_surveywall_adslot();
    if ($adslot === '') {
        return;
    }
    delete_transient(impactshop_ayet_surveywall_cache_key($pseudo_id, $adslot));
    delete_transient(impactshop_ayet_surveywall_cache_key($pseudo_id, $adslot, 'default'));
}

function impactshop_ayet_offerwall_fetch_offers_with_ua(string $pseudo_id, string $ip, string $user_agent, string $ua_key = 'default'): array
{
    $adslot = impactshop_ayet_get_effective_adslot();
    if ($adslot === '') {
        impactshop_ayet_log('warn', 'missing_adslot');
        return [];
    }

    $cache_key = impactshop_ayet_offerwall_cache_key($pseudo_id, $adslot, $ua_key);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $base = rtrim((string) AYET_OFFERWALL_API_BASE, '/') . '/';
    $url = $base . rawurlencode($adslot);
    $query = [
        'external_identifier' => $pseudo_id,
        'include_cpe'         => 'true',
        'language'            => 'hu',
        'num_offers'          => '30',
        'offer_sorting'       => 'ecpm',
    ];
    if ($ip !== '') {
        $query['ip'] = $ip;
    }
    if ($user_agent !== '') {
        $query['user_agent'] = $user_agent;
    }
    $url = add_query_arg($query, $url);

    $response = wp_remote_get($url, [
        'timeout' => 12,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        impactshop_ayet_log('warn', 'offerwall_api_error', ['error' => $response->get_error_message()]);
        return [];
    }

    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['offers']) || !is_array($data['offers'])) {
        return [];
    }

    $offers = array_values(array_filter($data['offers'], 'impactshop_ayet_offerwall_offer_allowed'));
    $offers = array_map('impactshop_ayet_offerwall_transform_offer', $offers);

    // Do not pin empty responses into cache, because stale inventory gaps are
    // better handled by a fresh follow-up fetch than by serving a false zero.
    if (!empty($offers)) {
        set_transient($cache_key, $offers, (int) AYET_OFFERWALL_CACHE_TTL);
    } else {
        delete_transient($cache_key);
    }
    return $offers;
}

function impactshop_ayet_offerwall_fetch_offers(string $pseudo_id, string $ip, string $user_agent): array
{
    return impactshop_ayet_offerwall_fetch_offers_with_ua($pseudo_id, $ip, $user_agent, 'default');
}

function impactshop_ayet_surveywall_transform_survey(array $raw): array
{
    $points = max(0, (int) round((float) ($raw['cpi'] ?? 0)));
    $votes = $points > 0 ? (int) ceil($points / 5) : 0;
    $category = strtolower(trim((string) ($raw['category'] ?? '')));
    $labels = [
        'miscellaneous' => 'Általános',
        'games' => 'Játékok',
        'education' => 'Oktatás',
        'shopping' => 'Vásárlás',
        'finance' => 'Pénzügy',
        'health' => 'Egészség',
        'technology' => 'Technológia',
    ];
    $categoryLabel = $labels[$category] ?? ($category !== '' ? ucfirst($category) : 'Általános');

    return [
        'survey_id' => (int) ($raw['id'] ?? 0),
        'name' => 'AyeT kérdőív',
        'category' => $category,
        'category_label' => $categoryLabel,
        'icon' => (string) ($raw['category_icon_svg'] ?? $raw['category_icon_gif'] ?? ''),
        'estimated_minutes' => max(1, (int) ($raw['loi'] ?? 0)),
        'remaining_completes' => max(0, (int) ($raw['remaining_completes'] ?? 0)),
        'is_new' => !empty($raw['is_new']),
        'points' => $points,
        'votes' => $votes,
        'url' => (string) ($raw['url'] ?? ''),
        'missing_qualifications' => max(0, (int) ($raw['missing_qualifications'] ?? 0)),
        'conversion_rate' => (float) ($raw['cr'] ?? 0),
    ];
}

function impactshop_ayet_surveywall_fetch_surveys_with_ua(string $pseudo_id, string $ip, string $user_agent, string $ua_key = 'default'): array
{
    $adslot = impactshop_ayet_get_effective_surveywall_adslot();
    if ($adslot === '') {
        impactshop_ayet_log('warn', 'missing_surveywall_adslot');
        return [];
    }

    $cache_key = impactshop_ayet_surveywall_cache_key($pseudo_id, $adslot, $ua_key);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $base = rtrim((string) AYET_SURVEYWALL_API_BASE, '/') . '/';
    $url = $base . rawurlencode($adslot);
    $query = [
        'external_identifier' => $pseudo_id,
        'language' => 'hu',
        'num_surveys' => '20',
        'survey_sorting' => 'eepm',
    ];
    $profile_hash = impactshop_ayet_get_effective_surveywall_profile_hash();
    if ($profile_hash !== '') {
        $query['hash'] = $profile_hash;
    }
    if ($ip !== '') {
        $query['ip'] = $ip;
    }
    if ($user_agent !== '') {
        $query['user_agent'] = $user_agent;
    }
    $url = add_query_arg($query, $url);

    $response = wp_remote_get($url, [
        'timeout' => 12,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        impactshop_ayet_log('warn', 'surveywall_api_error', ['error' => $response->get_error_message()]);
        return [];
    }

    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data) || strtolower((string) ($data['status'] ?? '')) !== 'success') {
        return [];
    }

    $surveys = isset($data['surveys']) && is_array($data['surveys']) ? $data['surveys'] : [];
    $surveys = array_values(array_filter(array_map('impactshop_ayet_surveywall_transform_survey', $surveys), static function (array $survey): bool {
        return !empty($survey['url']);
    }));

    if (!empty($surveys)) {
        set_transient($cache_key, $surveys, (int) AYET_SURVEYWALL_CACHE_TTL);
    } else {
        delete_transient($cache_key);
    }

    return $surveys;
}

/**
 * Transform a raw AyeT API offer into our enriched card format.
 * Pre-calculates reward, difficulty, and normalizes CPE steps.
 */
function impactshop_ayet_offerwall_transform_offer(array $raw): array
{
    $payout_usd      = (float) ($raw['payout_usd'] ?? 0);
    $currency_amount  = (float) ($raw['currency_amount'] ?? 0);
    $max_days         = (int) ($raw['max_conversion_time'] ?? 0);
    $cpe_raw          = is_array($raw['cpe_instructions'] ?? null) ? $raw['cpe_instructions'] : [];

    // --- Reward pre-calculation ---
    $total_points = impactshop_ayet_calculate_points($payout_usd, $currency_amount);
    $total_votes  = impactshop_ayet_calculate_votes($payout_usd, $currency_amount);

    // --- CPE steps ---
    $cpe_steps = [];
    foreach ($cpe_raw as $step) {
        $step_payout = (float) ($step['payout_usd'] ?? 0);
        $step_currency = (float) ($step['currency_amount'] ?? 0);
        $cpe_steps[] = [
            'task_name'      => (string) ($step['task_name'] ?? $step['event_name'] ?? ''),
            'type'           => (string) ($step['type'] ?? 'regular'),
            'status'         => (string) ($step['status'] ?? 'available'),
            'remaining_time' => $step['remaining_time'] ?? null,
            'points'         => impactshop_ayet_calculate_points($step_payout, $step_currency),
            'votes'          => impactshop_ayet_calculate_votes($step_payout, $step_currency),
        ];
    }

    // --- Difficulty ---
    $difficulty = impactshop_ayet_difficulty($raw);

    // --- Categories ---
    $categories = [];
    if (!empty($raw['categories']) && is_array($raw['categories'])) {
        $categories = array_values(array_map('strval', $raw['categories']));
    } elseif (!empty($raw['category'])) {
        $categories = [(string) $raw['category']];
    }

    $tracking_link = (string) ($raw['tracking_link'] ?? $raw['landing_page'] ?? '');
    $platform = strtolower((string) ($raw['platform'] ?? ''));
    $mobile_only = in_array($platform, ['android', 'ios'], true);

    return [
        'id'               => $raw['id'] ?? null,
        'offer_id'         => $raw['offer_id'] ?? ($raw['id'] ?? null),
        'name'             => (string) ($raw['name'] ?? $raw['offer_name'] ?? ''),
        'icon'             => (string) ($raw['icon_large'] ?? $raw['icon_url'] ?? $raw['icon'] ?? ''),
        'introduction'     => (string) ($raw['introduction'] ?? ''),
        'rules'            => (string) ($raw['rules_requirements'] ?? ''),
        'categories'       => $categories,
        'rating'           => (float) ($raw['rating'] ?? 0),
        'tracking_link'    => $tracking_link,
        'impression_url'   => (string) ($raw['impression_url'] ?? ''),
        'support_url'      => (string) ($raw['support_url'] ?? ''),
        'platform'         => $platform,
        'mobile_only'      => $mobile_only,
        'offer_status'     => (string) ($raw['offer_status'] ?? 'new'),
        'days_left'        => $raw['offer_status_days_left'] ?? null,
        'max_days'         => $max_days,
        'payout_usd'       => $payout_usd,
        'total_points'     => $total_points,
        'total_votes'      => $total_votes,
        'points_display'   => $total_points,
        'votes_display'    => $total_votes,
        'difficulty'       => $difficulty,
        'cpe_steps'        => $cpe_steps,
        'has_cpe'          => !empty($cpe_steps),
    ];
}

/**
 * Compute difficulty tier from AyeT offer data.
 * Uses offer_complexity if available, otherwise payout/days/tasks heuristic.
 */
function impactshop_ayet_difficulty(array $offer): array
{
    $api_complexity = strtolower(trim((string) ($offer['offer_complexity'] ?? '')));
    if ($api_complexity !== '') {
        $map = [
            'easy'   => ['tier' => 1, 'label' => '⭐ Könnyű',      'color' => '#22c55e', 'est' => '1–5 perc'],
            'medium' => ['tier' => 2, 'label' => '⭐⭐ Közepes',    'color' => '#f59e0b', 'est' => '5–30 perc'],
            'hard'   => ['tier' => 3, 'label' => '⭐⭐⭐ Kihívás',  'color' => '#ef4444', 'est' => '30–120 perc'],
        ];
        if (isset($map[$api_complexity])) {
            return $map[$api_complexity];
        }
    }

    $payout = (float) ($offer['payout_usd'] ?? 0);
    $days   = (int) ($offer['max_conversion_time'] ?? 0);
    $tasks  = count($offer['cpe_instructions'] ?? []);

    if ($payout < 0.50 && $days <= 1 && $tasks <= 1) {
        return ['tier' => 1, 'label' => '⭐ Könnyű',      'color' => '#22c55e', 'est' => '1–5 perc'];
    }
    if ($payout < 2.00 && $days <= 7 && $tasks <= 3) {
        return ['tier' => 2, 'label' => '⭐⭐ Közepes',    'color' => '#f59e0b', 'est' => '5–30 perc'];
    }
    if ($payout < 5.00 && $days <= 14) {
        return ['tier' => 3, 'label' => '⭐⭐⭐ Kihívás',  'color' => '#ef4444', 'est' => '30–120 perc'];
    }
    return ['tier' => 4, 'label' => '🏆 Nagykihívás', 'color' => '#7c3aed', 'est' => '2+ óra'];
}

function impactshop_ayet_offerwall_is_hu(): bool
{
    $country = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if ($country === '') {
        return true;
    }
    return $country === 'HU';
}

function impactshop_ayet_offerwall_offer_allowed(array $offer): bool
{
    $blocked = ['casino', 'games - casino', 'casino games', 'gambling'];
    $candidates = [];

    if (!empty($offer['category']) && is_string($offer['category'])) {
        $candidates[] = $offer['category'];
    }

    if (!empty($offer['tags']) && is_array($offer['tags'])) {
        $tag_categories = $offer['tags']['categories'] ?? [];
        if (is_array($tag_categories)) {
            $candidates = array_merge($candidates, $tag_categories);
        }
    }

    foreach ($candidates as $category) {
        $value = strtolower((string) $category);
        foreach ($blocked as $needle) {
            if ($value !== '' && strpos($value, $needle) !== false) {
                return false;
            }
        }
    }

    return true;
}

function impactshop_ayet_callback(WP_REST_Request $request): WP_REST_Response
{
    $ip = impactshop_ayet_resolve_ip($request);
    if (!impactshop_ayet_ip_allowed($ip)) {
        impactshop_ayet_log('warn', 'blocked_ip', ['ip' => $ip]);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    if (!impactshop_ayet_hmac_valid($request)) {
        impactshop_ayet_log('warn', 'hmac_invalid', ['ip' => $ip]);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    $transaction_id = sanitize_text_field((string) $request->get_param('transaction_id'));
    $pseudo_id = sanitize_text_field((string) $request->get_param('external_identifier'));
    $payout_usd = (float) $request->get_param('payout_usd');
    $currency_amount = (float) $request->get_param('currency_amount');
    $offer_name = sanitize_text_field((string) ($request->get_param('offer_name') ?? ''));
    $reversal_flag = (string) ($request->get_param('reversal') ?? '');

    if ($transaction_id === '' || $pseudo_id === '') {
        impactshop_ayet_log('warn', 'missing_params', ['tx' => $transaction_id, 'pseudo' => $pseudo_id]);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    if (function_exists('impactshop_identity_profile_valid_pseudo')
        && !impactshop_identity_profile_valid_pseudo($pseudo_id)
    ) {
        impactshop_ayet_log('warn', 'invalid_pseudo', ['pseudo' => $pseudo_id]);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    $is_reversal = ($reversal_flag === '1') || (strpos($transaction_id, 'r-') === 0);
    $original_tx = $is_reversal ? preg_replace('/^r-/', '', $transaction_id) : $transaction_id;

    if (!impactshop_ayet_rate_limit('ayet_ip_' . md5($ip), AYET_RATE_LIMIT_IP_RPM, 60)) {
        impactshop_ayet_log('warn', 'rate_limited_ip', ['ip' => $ip]);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
    if (!impactshop_ayet_rate_limit('ayet_pseudo_' . md5($pseudo_id), AYET_RATE_LIMIT_PSEUDO_RPM, 60)) {
        impactshop_ayet_log('warn', 'rate_limited_pseudo', ['pseudo' => $pseudo_id]);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    if ($is_reversal) {
        impactshop_ayet_handle_reversal($pseudo_id, $original_tx, $offer_name);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    $points_awarded = impactshop_ayet_calculate_points($payout_usd, $currency_amount);
    $votes_awarded = impactshop_ayet_calculate_votes($payout_usd, $currency_amount);

    // Daily cap check (§4.4)
    $cap_status = impactshop_ayet_daily_cap_check($pseudo_id, $points_awarded, $votes_awarded);
    if ($cap_status['capped']) {
        impactshop_ayet_log('info', 'daily_cap_reached', [
            'pseudo' => $pseudo_id,
            'cap_type' => $cap_status['reason'],
            'points_today' => $cap_status['points_today'],
            'votes_today' => $cap_status['votes_today'],
            'tx_today' => $cap_status['tx_today'],
        ]);
        // Still store completion but with 'capped' status, no reward
        $points_awarded = 0;
        $votes_awarded = 0;
    }

    $completion_status = $cap_status['capped'] ? 'capped' : 'pending';

    $completion_id = impactshop_ayet_store_completion([
        'pseudo_id' => $pseudo_id,
        'transaction_id' => $original_tx,
        'offer_name' => $offer_name,
        'payout_usd' => $payout_usd,
        'currency_amount' => $currency_amount,
        'points_awarded' => $points_awarded,
        'votes_awarded' => $votes_awarded,
        'user_ip' => $ip,
        'postback_data' => $request->get_query_params(),
        'status' => $completion_status,
    ]);
    if ($completion_id === 0) {
        return new WP_REST_Response(['status' => 'duplicate'], 200);
    }

    if ($points_awarded > 0 && class_exists('Sharity_Points_Manager')) {
        $points_manager = new Sharity_Points_Manager();
        $points_manager->award_points_for_pseudo(
            $pseudo_id,
            $points_awarded,
            'ayet_offerwall',
            $original_tx,
            [
                'source_type' => 'offerwall',
                'provider' => 'ayet',
                'offer_name' => $offer_name,
                'payout_usd' => $payout_usd,
            ],
            'ayet:' . $original_tx
        );
    }

    if ($votes_awarded > 0 && function_exists('impactshop_ads_watch_add_votes')) {
        impactshop_ads_watch_add_votes($pseudo_id, $votes_awarded);
    }

    impactshop_ayet_insert_ledger($pseudo_id, $original_tx, $offer_name, $payout_usd, $currency_amount);

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_ayet_surveys(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = '';
    if (function_exists('impactshop_identity_profile_cookie')) {
        $pseudo_id = (string) impactshop_identity_profile_cookie();
    }
    if ($pseudo_id === '' && isset($_COOKIE['impactshop_pseudo_id'])) {
        $pseudo_id = strtolower(sanitize_text_field((string) wp_unslash($_COOKIE['impactshop_pseudo_id'])));
    }

    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'status' => 'missing_pseudo',
            'surveys' => [],
        ], 200);
    }

    if (function_exists('impactshop_identity_profile_valid_pseudo')
        && !impactshop_identity_profile_valid_pseudo($pseudo_id)
    ) {
        return new WP_REST_Response([
            'status' => 'missing_pseudo',
            'surveys' => [],
        ], 200);
    }

    $adslot = impactshop_ayet_get_effective_surveywall_adslot();
    if ($adslot === '') {
        return new WP_REST_Response([
            'status' => 'missing_adslot',
            'surveys' => [],
        ], 200);
    }

    if ((string) $request->get_param('refresh') === '1') {
        $refresh_key = 'impactshop_ayet_surveywall_refresh_' . md5($pseudo_id);
        $last_refresh = (int) get_transient($refresh_key);
        $min_refresh_interval = 60;
        if ($last_refresh === 0 || (time() - $last_refresh) >= $min_refresh_interval) {
            impactshop_ayet_surveywall_flush_cache($pseudo_id);
            set_transient($refresh_key, time(), $min_refresh_interval);
        }
    }

    $ip = impactshop_ayet_resolve_ip($request);
    $user_agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $surveys = impactshop_ayet_surveywall_fetch_surveys_with_ua($pseudo_id, $ip, $user_agent);

    return new WP_REST_Response([
        'status' => 'ok',
        'surveys' => $surveys,
        'count' => count($surveys),
    ], 200);
}

function impactshop_ayet_handle_reversal(string $pseudo_id, string $transaction_id, string $offer_name): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, points_awarded, votes_awarded FROM {$table}
         WHERE provider = %s AND transaction_id = %s LIMIT 1",
        IMPACTSHOP_AYET_PROVIDER,
        $transaction_id
    ), ARRAY_A);

    $wpdb->update($table, [
        'status' => 'reversed',
        'reversed_at' => current_time('mysql'),
    ], [
        'provider' => IMPACTSHOP_AYET_PROVIDER,
        'transaction_id' => $transaction_id,
    ], ['%s', '%s'], ['%s', '%s']);

    if ($row) {
        $points = (int) ($row['points_awarded'] ?? 0);
        $votes = (int) ($row['votes_awarded'] ?? 0);

        if ($points > 0 && class_exists('Sharity_Points_Manager')) {
            $points_manager = new Sharity_Points_Manager();
            $points_manager->award_points_for_pseudo(
                $pseudo_id,
                -1 * $points,
                'ayet_reversal',
                $transaction_id,
                [
                    'source_type' => 'offerwall',
                    'provider' => 'ayet',
                    'offer_name' => $offer_name,
                    'reversal' => true,
                ],
                'ayet:' . $transaction_id . ':reversal'
            );
        }

        if ($votes > 0) {
            impactshop_ayet_revoke_votes($pseudo_id, $votes);
        }
    }

    impactshop_ayet_update_ledger_reversal($transaction_id);
    impactshop_ayet_send_decline_message($pseudo_id);
}

function impactshop_ayet_store_completion(array $data): int
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    if (!impactshop_ayet_table_exists($table)) {
        impactshop_ayet_log('error', 'missing_completions_table');
        return 0;
    }

    $inserted = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table}
            (pseudo_id, provider, offer_id, offer_name, offer_type, transaction_id, payout_usd, currency, points_awarded, votes_awarded, user_ip, user_agent, postback_data, status, request_id, awarded_at, created_at)
         VALUES (%s, %s, %s, %s, %s, %s, %f, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s)",
        $data['pseudo_id'],
        IMPACTSHOP_AYET_PROVIDER,
        '',
        $data['offer_name'],
        '',
        $data['transaction_id'],
        $data['payout_usd'],
        'USD',
        (int) $data['points_awarded'],
        (int) $data['votes_awarded'],
        $data['user_ip'],
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        wp_json_encode($data['postback_data']),
        (string) ($data['status'] ?? 'pending'),
        wp_generate_uuid4(),
        current_time('mysql'),
        current_time('mysql')
    ));

    if ($inserted === 0) {
        return 0;
    }
    return (int) $wpdb->insert_id;
}

function impactshop_ayet_insert_ledger(string $pseudo_id, string $transaction_id, string $offer_name, float $payout_usd, float $currency_amount): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    if (!impactshop_ayet_table_exists($table)) {
        return;
    }

    $amount_huf = impactshop_ayet_amount_huf($payout_usd, $currency_amount);
    $amount_huf = min($amount_huf, (int) AYET_MAX_AMOUNT_HUF);

    $wpdb->suppress_errors(true);
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table}
            (pseudo_id, ngo_slug, ngo_display, shop_slug, shop_display, amount_huf, channel, status, happened_at, source_ref)
         VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %s, %s)",
        strtolower($pseudo_id),
        '',
        '',
        'ayet-offerwall',
        $offer_name !== '' ? $offer_name : 'ayeT Feladat',
        $amount_huf,
        'ayet',
        'pending',
        current_time('mysql'),
        'ayet:' . $transaction_id
    ));
    $wpdb->suppress_errors(false);
}

function impactshop_ayet_update_ledger_reversal(string $transaction_id): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    if (!impactshop_ayet_table_exists($table)) {
        return;
    }
    $wpdb->update($table, [
        'status' => 'declined',
    ], [
        'source_ref' => 'ayet:' . $transaction_id,
    ], ['%s'], ['%s']);
}

function impactshop_ayet_send_decline_message(string $pseudo_id): void
{
    global $wpdb;
    $messages = $wpdb->prefix . 'impact_vote_messages';
    $targets = $wpdb->prefix . 'impact_vote_message_targets';
    if (!impactshop_ayet_table_exists($messages) || !impactshop_ayet_table_exists($targets)) {
        return;
    }

    $content = 'Szia! A legutóbbi feladat jóváírása a szolgáltató ellenőrzése után nem lett elfogadva, ' .
        'ezért a pontjaidat és szavazataidat korrigáltuk. Ha kérdésed van, írj nekünk: office@sharity.hu';
    $start = gmdate('Y-m-d H:i:s');
    $end = gmdate('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS));

    $wpdb->insert($messages, [
        'type' => 'targeted',
        'content' => $content,
        'start_at' => $start,
        'end_at' => $end,
        'priority' => 100,
        'created_at' => $start,
    ], ['%s', '%s', '%s', '%s', '%d', '%s']);

    $message_id = (int) $wpdb->insert_id;
    if ($message_id <= 0) {
        return;
    }

    $wpdb->update($targets, [
        'is_read' => 1,
        'read_at' => $start,
    ], [
        'pseudo_id' => $pseudo_id,
        'is_read' => 0,
    ], ['%d', '%s'], ['%s', '%d']);

    $wpdb->insert($targets, [
        'message_id' => $message_id,
        'pseudo_id' => $pseudo_id,
        'is_read' => 0,
    ], ['%d', '%s', '%d']);
}

function impactshop_ayet_revoke_votes(string $pseudo_id, int $votes): void
{
    if ($votes <= 0) {
        return;
    }
    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_user_votes';
    $table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';

    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT available_votes FROM {$table_votes} WHERE pseudo_id = %s",
        $pseudo_id
    ));
    if ($current !== null) {
        $new_total = max(0, (int) $current - $votes);
        $wpdb->update($table_votes, [
            'available_votes' => $new_total,
        ], [
            'pseudo_id' => $pseudo_id,
        ], ['%d'], ['%s']);
    }

    $stats = $wpdb->get_var($wpdb->prepare(
        "SELECT total_votes FROM {$table_stats} WHERE pseudo_id = %s",
        $pseudo_id
    ));
    if ($stats !== null) {
        $new_stats = max(0, (int) $stats - $votes);
        $wpdb->update($table_stats, [
            'total_votes' => $new_stats,
        ], [
            'pseudo_id' => $pseudo_id,
        ], ['%d'], ['%s']);
    }
}

function impactshop_ayet_calculate_points(float $payout_usd, float $currency_amount): int
{
    // Calibrated: §1.2 — pont = ceil(payout_usd × AYET_POINTS_MULTIPLIER)
    // If AyeT dashboard conversion rate is set to 50, currency_amount already equals payout_usd × 50.
    $points = 0;
    if ($currency_amount > 0) {
        $points = (int) ceil($currency_amount);
    } elseif ($payout_usd > 0) {
        $points = (int) ceil($payout_usd * AYET_POINTS_MULTIPLIER);
    }
    $points = max((int) AYET_MIN_POINTS, min((int) AYET_MAX_POINTS_PER_TX, $points));
    $points = (int) apply_filters('impactshop_ayet_points_awarded', $points, $payout_usd, $currency_amount);
    return max(0, $points);
}

function impactshop_ayet_calculate_votes(float $payout_usd, float $currency_amount): int
{
    // Calibrated: §1.2 — szavazat = ceil(payout_usd × AYET_VOTES_MULTIPLIER)
    // Always derive from payout_usd (not currency_amount) for consistent vote ratio.
    $votes = 0;
    if ($payout_usd > 0) {
        $votes = (int) ceil($payout_usd * AYET_VOTES_MULTIPLIER);
    } elseif ($currency_amount > 0) {
        // Fallback: reverse-engineer payout from currency_amount / AYET_POINTS_MULTIPLIER
        $est_payout = $currency_amount / max(1, AYET_POINTS_MULTIPLIER);
        $votes = (int) ceil($est_payout * AYET_VOTES_MULTIPLIER);
    }
    $votes = max((int) AYET_MIN_VOTES, min((int) AYET_MAX_VOTES_PER_TX, $votes));
    $votes = (int) apply_filters('impactshop_ayet_votes_awarded', $votes, $payout_usd, $currency_amount);
    return max(0, $votes);
}

function impactshop_ayet_amount_huf(float $payout_usd, float $currency_amount): int
{
    if ($currency_amount > 0) {
        return (int) round($currency_amount);
    }
    $fx_rate = 392.0;
    if (function_exists('impactshop_get_huf_rate')) {
        $fx_rate = (float) impactshop_get_huf_rate() ?: $fx_rate;
    } elseif (defined('IMPACTSHOP_FX_HUF')) {
        $fx_rate = (float) IMPACTSHOP_FX_HUF;
    }
    return (int) round($payout_usd * $fx_rate);
}

function impactshop_ayet_rate_limit(string $key, int $limit, int $window): bool
{
    if ($limit <= 0) {
        return true;
    }
    $bucket = get_transient($key);
    $now = time();
    if (!is_array($bucket)) {
        set_transient($key, ['count' => 1, 'reset' => $now + $window], $window);
        return true;
    }
    $count = (int) ($bucket['count'] ?? 0);
    if ($count >= $limit) {
        return false;
    }
    $bucket['count'] = $count + 1;
    set_transient($key, $bucket, $window);
    return true;
}

function impactshop_ayet_build_sorted_query(array $params): string
{
    $encoded = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $encoded[rawurlencode((string) $key)] = rawurlencode((string) $value);
    }
    ksort($encoded, SORT_STRING);
    $parts = [];
    foreach ($encoded as $key => $value) {
        $parts[] = $key . '=' . $value;
    }
    return implode('&', $parts);
}

function impactshop_ayet_strip_query_param(string $query, string $param): string
{
    if ($query === '') {
        return '';
    }
    $pairs = explode('&', $query);
    $kept = [];
    foreach ($pairs as $pair) {
        if ($pair === '') {
            continue;
        }
        $key = explode('=', $pair, 2)[0];
        if ($key === $param || $key === 'rest_route') {
            continue;
        }
        $kept[] = $pair;
    }
    return implode('&', $kept);
}

function impactshop_ayet_hmac_valid(WP_REST_Request $request): bool
{
    $secret = (string) AYET_API_KEY;
    if ($secret === '') {
        return false;
    }
    $signature = (string) ($request->get_header('x-ayetstudios-security-hash')
        ?: ($_SERVER['HTTP_X_AYETSTUDIOS_SECURITY_HASH'] ?? '')
        ?: $request->get_param('security_hash')
        ?: ($_GET['security_hash'] ?? '')
    );
    if ($signature === '') {
        return false;
    }

    $params = $request->get_params();
    if (!is_array($params)) {
        $params = [];
    }
    unset($params['rest_route'], $params['security_hash']);
    $sorted_query = impactshop_ayet_build_sorted_query($params);
    $computed_sorted = hash_hmac('sha256', $sorted_query, $secret);

    $query_params = $_GET ?? [];
    unset($query_params['rest_route'], $query_params['security_hash']);
    $sorted_query_get = impactshop_ayet_build_sorted_query($query_params);
    $computed_sorted_get = hash_hmac('sha256', $sorted_query_get, $secret);

    $raw_query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $raw_query = impactshop_ayet_strip_query_param($raw_query, 'security_hash');
    $computed_raw = $raw_query !== '' ? hash_hmac('sha256', $raw_query, $secret) : '';

    $matches = hash_equals($computed_sorted, $signature)
        || hash_equals($computed_sorted_get, $signature)
        || ($computed_raw !== '' && hash_equals($computed_raw, $signature));

    if (!$matches) {
        impactshop_ayet_log('warn', 'hmac_mismatch', [
            'sig_prefix' => substr($signature, 0, 8),
            'sorted_prefix' => substr($computed_sorted, 0, 8),
            'sorted_get_prefix' => substr($computed_sorted_get, 0, 8),
            'raw_prefix' => $computed_raw ? substr($computed_raw, 0, 8) : '',
            'param_count' => count($params),
            'method' => $request->get_method(),
        ]);
    }

    return $matches;
}

function impactshop_ayet_ip_allowed(string $ip): bool
{
    $allowlist = (array) apply_filters('impactshop_ayet_allowed_ips', defined('AYET_ALLOWED_IPS') ? AYET_ALLOWED_IPS : []);
    if (!$allowlist) {
        return true;
    }
    return in_array($ip, $allowlist, true);
}

function impactshop_ayet_resolve_ip(WP_REST_Request $request): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = (string) ($request->get_header('cf-connecting-ip') ?: ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && impactshop_ayet_is_cloudflare_ip($remote)) {
        return trim($cf);
    }

    $candidates = [
        $request->get_header('x-forwarded-for'),
        $request->get_header('x-real-ip'),
        $request->get_header('client-ip'),
    ];
    foreach ($candidates as $candidate) {
        $candidate = (string) $candidate;
        if ($candidate === '') {
            continue;
        }
        return trim(explode(',', $candidate)[0]);
    }
    return $remote;
}

function impactshop_ayet_is_cloudflare_ip(string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    $ranges = array_merge(impactshop_ayet_cloudflare_ipv4(), impactshop_ayet_cloudflare_ipv6());
    foreach ($ranges as $cidr) {
        if (impactshop_ayet_ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function impactshop_ayet_cloudflare_ipv4(): array
{
    return [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];
}

function impactshop_ayet_cloudflare_ipv6(): array
{
    return [
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];
}

function impactshop_ayet_ip_in_cidr(string $ip, string $cidr): bool
{
    if (strpos($cidr, '/') === false) {
        return false;
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $ip_bin = inet_pton($ip);
    $subnet_bin = inet_pton($subnet);
    if ($ip_bin === false || $subnet_bin === false) {
        return false;
    }
    if (strlen($ip_bin) !== strlen($subnet_bin)) {
        return false;
    }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;
    if ($bytes > 0) {
        if (substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
            return false;
        }
    }
    if ($remainder > 0) {
        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        if ((ord($ip_bin[$bytes]) & ord($mask)) !== (ord($subnet_bin[$bytes]) & ord($mask))) {
            return false;
        }
    }
    return true;
}

/**
 * Check daily caps for a pseudo_id.
 * Returns ['capped' => bool, 'reason' => string, 'points_today' => int, 'votes_today' => int, 'tx_today' => int]
 */
function impactshop_ayet_daily_cap_check(string $pseudo_id, int $points, int $votes): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $today = current_time('Y-m-d');

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS tx_count,
                COALESCE(SUM(points_awarded), 0) AS points_sum,
                COALESCE(SUM(votes_awarded), 0) AS votes_sum
         FROM {$table}
         WHERE pseudo_id = %s
           AND provider = %s
           AND DATE(created_at) = %s
           AND status NOT IN ('reversed', 'capped')",
        $pseudo_id,
        IMPACTSHOP_AYET_PROVIDER,
        $today
    ), ARRAY_A);

    $tx_today     = (int) ($row['tx_count'] ?? 0);
    $points_today = (int) ($row['points_sum'] ?? 0);
    $votes_today  = (int) ($row['votes_sum'] ?? 0);

    $result = [
        'capped'       => false,
        'reason'       => '',
        'points_today' => $points_today,
        'votes_today'  => $votes_today,
        'tx_today'     => $tx_today,
    ];

    if ($tx_today >= (int) AYET_DAILY_TX_CAP) {
        $result['capped'] = true;
        $result['reason'] = 'tx_cap';
    } elseif (($points_today + $points) > (int) AYET_DAILY_POINTS_CAP) {
        $result['capped'] = true;
        $result['reason'] = 'points_cap';
    } elseif (($votes_today + $votes) > (int) AYET_DAILY_VOTES_CAP) {
        $result['capped'] = true;
        $result['reason'] = 'votes_cap';
    }

    return $result;
}

function impactshop_ayet_table_exists(string $table): bool
{
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
}

function impactshop_ayet_log(string $level, string $message, array $context = []): void
{
    $payload = [
        'provider' => IMPACTSHOP_AYET_PROVIDER,
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'ts' => gmdate('c'),
    ];
    error_log('[impactshop_ayet] ' . wp_json_encode($payload));
}
