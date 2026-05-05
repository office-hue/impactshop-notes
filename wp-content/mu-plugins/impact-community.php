<?php
/**
 * Plugin Name: Impact Community — Hatás Körök
 * Description: Community layer for ImpactShop — NGO/settlement circles, posts, activity points, invites.
 * Version:     0.1.1
 * Author:      ImpactShop
 *
 * Feature flag: IMPACT_COMMUNITY_ENABLED
 * Design doc:   docs/impact-community-hatas-korok-design-2026-03-23.md
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACT_COMMUNITY_ENABLED') || !IMPACT_COMMUNITY_ENABLED) {
    return;
}

/* =========================================================================
   Constants
   ========================================================================= */

define('IC_DB_VERSION', '1.5.0');
define('IC_MAX_CIRCLES', 10);
define('IC_MAX_BODY_LENGTH', 600);
define('IC_POSTS_PER_PAGE', 20);
define('IC_CIRCLES_PER_PAGE', 30);
define('IC_RATE_LIMIT_POSTS_PER_HOUR', 5);
define('IMPACT_COMMUNITY_DEV_CLONE_SLUG', 'hatas-korok-dev');
define('IMPACT_COMMUNITY_DEV_CLONE_CAPABILITY', 'manage_options');

function ic_request_path() {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    $base_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
    if (is_string($base_path) && $base_path !== '' && $base_path !== '/' && str_starts_with($path, $base_path)) {
        $path = substr($path, strlen($base_path));
        if ($path === '') {
            $path = '/';
        }
    }

    // Fallback for prefixed staging routes when home_url base path is not aligned.
    if (str_starts_with($path, '/impactshop-staging/')) {
        $path = substr($path, strlen('/impactshop-staging'));
        if ($path === '') {
            $path = '/';
        }
    }

    return $path;
}

function ic_is_dev_clone_request() {
    $path = ic_request_path();
    return (bool) preg_match('~^/' . preg_quote(IMPACT_COMMUNITY_DEV_CLONE_SLUG, '~') . '/?$~', $path);
}

function ic_is_dev_clone_authorized() {
    return is_user_logged_in() && current_user_can(IMPACT_COMMUNITY_DEV_CLONE_CAPABILITY);
}

function ic_is_page_request() {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }

    $path = ic_request_path();
    if (preg_match('~^/hatas-korok/?$~', $path)) {
        return true;
    }

    if (ic_is_dev_clone_request()) {
        return ic_is_dev_clone_authorized();
    }

    return false;
}

add_action('template_redirect', 'ic_guard_dev_clone_access', 0);
add_action('template_redirect', 'ic_send_nocache_headers', 1);
add_action('template_redirect', 'ic_send_dev_clone_noindex_headers', 2);
add_filter('rest_authentication_errors', 'ic_allow_public_rest_requests', 5);

function ic_guard_dev_clone_access() {
    if (!ic_is_dev_clone_request()) {
        return;
    }

    if (ic_is_dev_clone_authorized()) {
        return;
    }

    nocache_headers();
    wp_die('Not Found', 'Not Found', ['response' => 404]);
}

function ic_send_nocache_headers() {
    if (!ic_is_page_request() || headers_sent()) {
        return;
    }

    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
    header('Pragma: no-cache', true);
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
}

function ic_send_dev_clone_noindex_headers() {
    if (!ic_is_dev_clone_request() || !ic_is_dev_clone_authorized() || headers_sent()) {
        return;
    }

    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}

function ic_allow_public_rest_requests($result) {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($uri === '' || strpos($uri, '/wp-json/impact/v1/') === false) {
        return $result;
    }

    $is_community_route = false;
    foreach (['/wp-json/impact/v1/circles', '/wp-json/impact/v1/feed/mine', '/wp-json/impact/v1/auth/status', '/wp-json/impact/v1/ngo/admin'] as $fragment) {
        if (strpos($uri, $fragment) !== false) {
            $is_community_route = true;
            break;
        }
    }

    if (!$is_community_route) {
        return $result;
    }

    return true;
}

/* =========================================================================
   1. Impact Alias — deterministic alias from pid_hash + circle_id
   ========================================================================= */

final class IC_Alias {

    const ICONS = ['🌱','🔥','💧','🌍','⭐','🎯','🦡','🐾','🌊','🏔️','☀️','🌙','🍀','🦋','🐝','🎭','🔔','💎','🏅','🪴'];

    const WORDS_A = [
        'Bátrak','Zöld','Csöndes','Lángoló','Fényes','Szabad','Rejtett','Tiszta',
        'Merész','Hűséges','Őszinte','Vidám','Nyugodt','Csillagos','Mosolygós',
        'Derűs','Kitartó','Álmodó','Felkelő','Viharos','Napfényes','Hajnali',
        'Békés','Hamvas','Éjféli','Napsütötte','Szélben','Tengerszínű','Aranyos','Jégvirágos',
    ];

    const WORDS_B = [
        'Szava','Hírnök','Folyam','Kéz','Tűz','Madár','Mag','Ösvény','Fény','Pajzs',
        'Hullám','Szikra','Part','Csillag','Lélek','Remény','Harang','Kürt','Vándor',
        'Forrás','Mécses','Hajó','Szél','Öböl','Lámpás','Kapu','Kert','Bástyás','Erdő','Torony',
    ];

    /**
     * Deterministic alias: same pseudo_id in same circle always gives the same alias.
     * Different circle → different alias (extra privacy layer).
     */
    public static function generate($pid_hash, $circle_id) {
        $seed  = $pid_hash . ':' . $circle_id;
        $hash  = hash('sha256', $seed);

        $icon_idx  = hexdec(substr($hash, 0, 4)) % count(self::ICONS);
        $word_a    = hexdec(substr($hash, 4, 4)) % count(self::WORDS_A);
        $word_b    = hexdec(substr($hash, 8, 4)) % count(self::WORDS_B);

        return self::ICONS[$icon_idx] . ' ' . self::WORDS_A[$word_a] . ' ' . self::WORDS_B[$word_b];
    }
}

/* =========================================================================
   2. Helpers
   ========================================================================= */

function ic_get_pseudo_id() {
    $raw = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field($_COOKIE['impactshop_pseudo_id']) : '';
    if ($raw === '' || strlen($raw) < 6) {
        return '';
    }
    if (function_exists('impactshop_identity_normalize_pseudo')) {
        return impactshop_identity_normalize_pseudo($raw);
    }
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $raw);
}

function ic_pid_hash($pseudo_id = '') {
    if ($pseudo_id === '') {
        $pseudo_id = ic_get_pseudo_id();
    }
    if ($pseudo_id === '') {
        return '';
    }
    return hash('sha256', $pseudo_id);
}

function ic_is_circle_member($circle_id, $pid_hash) {
    global $wpdb;
    $p = $wpdb->prefix;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
        (int) $circle_id,
        (string) $pid_hash
    )) > 0;
}

function ic_get_circle($circle_id) {
    global $wpdb;
    $p = $wpdb->prefix;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_circles WHERE id=%d AND is_active=1",
        (int) $circle_id
    ));
}

function ic_get_ngo_account($circle_id, $pid_hash) {
    global $wpdb;
    $p = $wpdb->prefix;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_ngo_accounts WHERE circle_id=%d AND pid_hash=%s LIMIT 1",
        (int) $circle_id,
        (string) $pid_hash
    ));
}

function ic_can_post_as_ngo($circle_id, $pid_hash) {
    $circle = ic_get_circle($circle_id);
    if (!$circle || (string) $circle->type !== 'ngo') {
        return false;
    }

    if (!ic_is_circle_member($circle_id, $pid_hash)) {
        return false;
    }

    $account = ic_get_ngo_account($circle_id, $pid_hash);
    if (!$account) {
        return false;
    }

    return ((string) $account->status === 'active') && ((int) $account->can_post_as_ngo === 1);
}

function ic_ngo_cegjelzo_client() {
    if (!class_exists('ImpactShop_Cegjelzo_Client')) {
        return new WP_Error('cegjelzo_unavailable', 'A Cegjelzo integracio nem erheto el.');
    }

    $client = new ImpactShop_Cegjelzo_Client();
    if (!is_object($client)) {
        return new WP_Error('cegjelzo_unavailable', 'A Cegjelzo kliens nem inicializalhato.');
    }

    return $client;
}

function ic_ngo_first_value($value): string {
    if (is_array($value)) {
        $value = $value[0]['value'] ?? $value[0] ?? '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
        return trim((string) $value);
    }
    return '';
}

function ic_ngo_normalize_company_record($item): array {
    $official_name = ic_ngo_first_value($item['long_name'] ?? $item['official_name'] ?? '');
    $short_name = ic_ngo_first_value($item['short_name'] ?? '');
    $registration_number = ic_ngo_first_value($item['registration_number'] ?? $item['id'] ?? '');
    $tax_number = ic_ngo_first_value($item['tax_number'] ?? '');
    $address = ic_ngo_first_value($item['address'] ?? '');
    $nav_address = ic_ngo_first_value($item['nav_address'] ?? '');
    $activity = ic_ngo_first_value($item['activity'] ?? '');
    $description = ic_ngo_first_value($item['description'] ?? '');
    $org_type = ic_ngo_first_value($item['type'] ?? '');
    $status_label = ic_ngo_first_value($item['status'] ?? '');
    $status_code_raw = ic_ngo_first_value($item['status_code'] ?? '');
    $status_code = $status_code_raw === '' ? null : (int) $status_code_raw;
    $level_of_charity = ic_ngo_first_value($item['level_of_charity'] ?? '');
    $representatives = $item['representatives'] ?? [];
    if (!is_array($representatives)) {
        $representatives = [];
    }
    $proceedings = $item['proceedings'] ?? [];
    if (!is_array($proceedings)) {
        $proceedings = [];
    }

    return [
        'registry_id' => $registration_number,
        'official_name' => $official_name,
        'short_name' => $short_name,
        'display_name' => $official_name !== '' ? $official_name : $short_name,
        'tax_number' => $tax_number,
        'registration_number' => $registration_number,
        'org_type' => $org_type,
        'address' => $address,
        'nav_address' => $nav_address,
        'activity' => $activity,
        'description' => $description,
        'status_label' => $status_label,
        'status_code' => $status_code,
        'level_of_charity' => $level_of_charity,
        'representatives' => $representatives,
        'proceedings' => $proceedings,
        'raw' => is_array($item) ? $item : [],
    ];
}

function ic_ngo_make_circle_slug(array $company): string {
    $tax = preg_replace('/[^0-9]/', '', (string) ($company['tax_number'] ?? ''));
    if ($tax !== '') {
        return 'ngo-tax-' . $tax;
    }

    $reg = sanitize_title((string) ($company['registration_number'] ?? ''));
    if ($reg !== '') {
        return 'ngo-reg-' . $reg;
    }

    $name = sanitize_title((string) ($company['display_name'] ?? $company['official_name'] ?? $company['short_name'] ?? ''));
    if ($name !== '') {
        return 'ngo-' . $name;
    }

    return '';
}

function ic_ngo_normalize_tax_number(string $raw): array {
    $trimmed = trim($raw);
    $digits = preg_replace('/\D+/', '', $trimmed);
    $variants = [];

    if ($trimmed !== '') {
        $variants[] = $trimmed;
    }
    if ($digits !== '') {
        $variants[] = $digits;
        if (strlen($digits) >= 9) {
            $formatted = substr($digits, 0, 8) . '-' . substr($digits, 8, 1);
            if (strlen($digits) >= 11) {
                $formatted .= '-' . substr($digits, 9, 2);
            }
            $variants[] = $formatted;
        }
    }

    return array_values(array_unique(array_filter($variants)));
}

function ic_ngo_company_match_score(array $item, string $query, string $tax_number): float {
    $score = 0.0;

    $company_name = mb_strtolower(trim((string) ($item['display_name'] ?? $item['official_name'] ?? $item['short_name'] ?? '')));
    $official_name = mb_strtolower(trim((string) ($item['official_name'] ?? '')));
    $short_name = mb_strtolower(trim((string) ($item['short_name'] ?? '')));
    $query_norm = mb_strtolower(trim($query));

    if ($query_norm !== '') {
        if ($company_name === $query_norm || $official_name === $query_norm || $short_name === $query_norm) {
            $score += 100;
        } elseif (str_starts_with($company_name, $query_norm) || str_starts_with($official_name, $query_norm) || str_starts_with($short_name, $query_norm)) {
            $score += 70;
        } elseif (str_contains($company_name, $query_norm) || str_contains($official_name, $query_norm) || str_contains($short_name, $query_norm)) {
            $score += 45;
        }
    }

    $tax_norm = preg_replace('/\D+/', '', $tax_number);
    $company_tax_norm = preg_replace('/\D+/', '', (string) ($item['tax_number'] ?? ''));
    if ($tax_norm !== '' && $company_tax_norm !== '') {
        if ($tax_norm === $company_tax_norm) {
            $score += 120;
        } elseif (str_starts_with($company_tax_norm, $tax_norm) || str_starts_with($tax_norm, $company_tax_norm)) {
            $score += 70;
        }
    }

    if (!empty($item['registration_number'])) {
        $score += 10;
    }

    return $score;
}

function ic_ngo_query_variants(string $query): array {
    $q = trim($query);
    if ($q === '') {
        return [];
    }

    $variants = [];
    $variants[] = $q;

    $lower = mb_strtolower($q);

    // ASCII transliteration variant for better Cégjelző matching
    $normalized = $lower;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        if ($converted !== false) {
            $normalized = trim((string) $converted);
        }
    }
    if ($normalized !== '' && $normalized !== $q && $normalized !== $lower) {
        $variants[] = $normalized;
    }

    $stopwords = ['a', 'az', 'egy'];
    $suffixes = ['egyesulet', 'alapitvany', 'kozhasznu', 'szervezet'];

    $normalized_words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower)) ?: [];
    $filtered_words = [];
    foreach ($normalized_words as $word) {
        $word = trim($word);
        if ($word === '' || in_array($word, $stopwords, true)) {
            continue;
        }
        $filtered_words[] = $word;
    }

    if (!empty($filtered_words)) {
        $variants[] = implode(' ', $filtered_words);
        $first_two = array_slice($filtered_words, 0, 2);
        if (!empty($first_two)) {
            $variants[] = implode(' ', $first_two);
        }

        $trimmed = [];
        foreach ($filtered_words as $word) {
            if (in_array($word, $suffixes, true)) {
                continue;
            }
            $trimmed[] = $word;
        }
        if (!empty($trimmed)) {
            $variants[] = implode(' ', $trimmed);
            $variants[] = $trimmed[0];
        }

        // Common Hungarian legal-name alternations: Egyesulet <-> Egyesulete, Alapitvany <-> Alapitvanya
        $alt_words = $filtered_words;
        $last_idx = count($alt_words) - 1;
        if ($last_idx >= 0) {
            $last = $alt_words[$last_idx];
            if ($last === 'egyesulet') {
                $alt_words[$last_idx] = 'egyesulete';
                $variants[] = implode(' ', $alt_words);
            } elseif ($last === 'egyesulete') {
                $alt_words[$last_idx] = 'egyesulet';
                $variants[] = implode(' ', $alt_words);
            }

            if ($last === 'alapitvany') {
                $alt_words[$last_idx] = 'alapitvanya';
                $variants[] = implode(' ', $alt_words);
            } elseif ($last === 'alapitvanya') {
                $alt_words[$last_idx] = 'alapitvany';
                $variants[] = implode(' ', $alt_words);
            }
        }
    }

    $clean = [];
    foreach ($variants as $variant) {
        $variant = trim((string) $variant);
        if ($variant === '' || mb_strlen($variant) < 3) {
            continue;
        }
        $clean[] = $variant;
    }

    return array_values(array_unique($clean));
}

function ic_ngo_find_or_create_circle_from_company(array $company) {
    global $wpdb;
    $p = $wpdb->prefix;

    $display_name = trim((string) ($company['display_name'] ?? $company['official_name'] ?? $company['short_name'] ?? ''));
    if ($display_name === '') {
        return new WP_Error('invalid_company', 'A kivalasztott szervezet neve hianyzik.');
    }

    $slug = ic_ngo_make_circle_slug($company);
    if ($slug === '') {
        return new WP_Error('invalid_company', 'A kivalasztott szervezet azonosito adatai hianyoznak.');
    }

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_circles WHERE type='ngo' AND ref_slug=%s LIMIT 1",
        $slug
    ));
    if ($existing) {
        return $existing;
    }

    $base_slug = $slug;
    $attempt = 1;
    while ($wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE type='ngo' AND ref_slug=%s LIMIT 1",
        $slug
    ))) {
        $attempt++;
        $slug = $base_slug . '-' . $attempt;
    }

    $inserted = $wpdb->insert("{$p}ic_circles", [
        'type' => 'ngo',
        'ref_slug' => $slug,
        'name' => mb_substr($display_name, 0, 200),
        'description' => '',
        'is_active' => 1,
        'created_at' => current_time('mysql'),
    ]);
    if ($inserted === false) {
        return new WP_Error('circle_create_failed', 'Az NGO kor automatikus letrehozasa sikertelen volt.');
    }

    $id = (int) $wpdb->insert_id;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_circles WHERE id=%d LIMIT 1",
        $id
    ));
}

function ic_ngo_company_fallback_from_registry(string $circle_name, string $circle_slug = ''): array {
    global $wpdb;

    $registry_table = $wpdb->prefix . 'impactshop_ngo_registry';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $registry_table));
    if ($exists !== $registry_table) {
        return [];
    }

    $candidates = [];
    $seen = [];
    $append = static function (array $row) use (&$candidates, &$seen): void {
        $normalized = ic_ngo_normalize_company_record([
            'registration_number' => (string) ($row['registration_number'] ?? $row['cegjelzo_id'] ?? ''),
            'id' => (string) ($row['cegjelzo_id'] ?? ''),
            'long_name' => (string) ($row['official_name'] ?? ''),
            'short_name' => (string) ($row['short_name'] ?? ''),
            'type' => (string) ($row['org_type'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'tax_number' => (string) ($row['tax_number'] ?? ''),
            'activity' => (string) ($row['activity'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'status' => (string) ($row['status_label'] ?? ''),
            'status_code' => isset($row['status_code']) ? (int) $row['status_code'] : null,
            'level_of_charity' => (string) ($row['level_of_charity'] ?? ''),
            'representatives' => !empty($row['representatives']) ? (json_decode((string) $row['representatives'], true) ?: []) : [],
            'proceedings' => !empty($row['proceedings']) ? (json_decode((string) $row['proceedings'], true) ?: []) : [],
        ]);
        $key = $normalized['registry_id'] !== '' ? $normalized['registry_id'] : ($normalized['tax_number'] !== '' ? $normalized['tax_number'] : sanitize_title((string) ($normalized['display_name'] ?? '')));
        if ($key === '' || isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $candidates[] = $normalized;
    };

    if (preg_match('/^ngo-tax-(\d{8,})$/', $circle_slug, $tax_match)) {
        $tax_digits = (string) $tax_match[1];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$registry_table} WHERE REPLACE(REPLACE(tax_number, '-', ''), ' ', '') LIKE %s ORDER BY updated_at DESC LIMIT 5",
            '%' . $wpdb->esc_like($tax_digits) . '%'
        ), ARRAY_A);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $append($row);
                }
            }
        }
    }

    if (empty($candidates) && $circle_name !== '') {
        $variants = ic_ngo_query_variants($circle_name);
        foreach ($variants as $variant) {
            $like = '%' . $wpdb->esc_like($variant) . '%';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$registry_table}
                 WHERE official_name LIKE %s OR short_name LIKE %s OR display_name LIKE %s
                 ORDER BY updated_at DESC LIMIT 5",
                $like,
                $like,
                $like
            ), ARRAY_A);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $append($row);
                    }
                }
            }
            if (!empty($candidates)) {
                break;
            }
        }
    }

    if (empty($candidates)) {
        return [];
    }

    foreach ($candidates as $idx => $candidate) {
        $candidates[$idx]['_match_score'] = ic_ngo_company_match_score($candidate, $circle_name, '');
    }
    usort($candidates, static function (array $a, array $b): int {
        $left = (float) ($a['_match_score'] ?? 0);
        $right = (float) ($b['_match_score'] ?? 0);
        return $left === $right ? 0 : ($left > $right ? -1 : 1);
    });
    unset($candidates[0]['_match_score']);
    $candidates[0]['source'] = 'registry';

    return $candidates[0];
}

function ic_ngo_company_fallback_from_cegjelzo(string $circle_name, string $circle_slug = ''): array {
    $client = ic_ngo_cegjelzo_client();
    if (is_wp_error($client)) {
        return [];
    }

    $items = [];
    $append = static function (array $candidate) use (&$items): void {
        $items[] = ic_ngo_normalize_company_record($candidate);
    };

    if (preg_match('/^ngo-tax-(\d{8,})$/', $circle_slug, $tax_match)) {
        $tax_results = $client->search_civil_org((string) $tax_match[1], 'tax_number', null, 3);
        if (!is_wp_error($tax_results)) {
            $tax_items = $tax_results['items'] ?? $tax_results['data'] ?? (isset($tax_results[0]) ? $tax_results : []);
            if (is_array($tax_items)) {
                foreach ($tax_items as $candidate) {
                    if (is_array($candidate)) {
                        $append($candidate);
                    }
                }
            }
        }
    }

    if (empty($items) && $circle_name !== '') {
        $variants = ic_ngo_query_variants($circle_name);
        foreach ($variants as $variant) {
            $name_results = $client->search_civil_org($variant, 'name', null, 5);
            if (is_wp_error($name_results)) {
                continue;
            }
            $name_items = $name_results['items'] ?? $name_results['data'] ?? (isset($name_results[0]) ? $name_results : []);
            if (is_array($name_items)) {
                foreach ($name_items as $candidate) {
                    if (is_array($candidate)) {
                        $append($candidate);
                    }
                }
            }
            if (!empty($items)) {
                break;
            }
        }
    }

    if (empty($items)) {
        return [];
    }

    foreach ($items as $idx => $item) {
        $items[$idx]['_match_score'] = ic_ngo_company_match_score($item, $circle_name, '');
    }
    usort($items, static function (array $a, array $b): int {
        $left = (float) ($a['_match_score'] ?? 0);
        $right = (float) ($b['_match_score'] ?? 0);
        return $left === $right ? 0 : ($left > $right ? -1 : 1);
    });
    unset($items[0]['_match_score']);
    $items[0]['source'] = 'cegjelzo';

    return $items[0];
}

function ic_ngo_get_company_fallback_for_circle(string $circle_name, string $circle_slug = ''): array {
    $from_registry = ic_ngo_company_fallback_from_registry($circle_name, $circle_slug);
    if (!empty($from_registry)) {
        return $from_registry;
    }

    return ic_ngo_company_fallback_from_cegjelzo($circle_name, $circle_slug);
}

/**
 * Rate limit helper via transients.
 */
function ic_rate_check($key, $max, $window_seconds) {
    $count = (int) get_transient($key);
    if ($count >= $max) {
        return false;
    }
    set_transient($key, $count + 1, $window_seconds);
    return true;
}

function ic_json_error($message, $status = 400) {
    return new WP_Error('ic_error', $message, ['status' => $status]);
}

function ic_report_mailer_route($phpmailer) {
    if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
        return;
    }

    $domains = ['sharity.hu'];
    $should_route = false;
    $addresses = array_merge(
        $phpmailer->getToAddresses(),
        $phpmailer->getCcAddresses(),
        $phpmailer->getBccAddresses()
    );

    foreach ($addresses as $entry) {
        if (!is_array($entry) || empty($entry[0])) {
            continue;
        }
        $email = strtolower(trim((string) $entry[0]));
        foreach ($domains as $domain) {
            if (str_ends_with($email, '@' . $domain)) {
                $should_route = true;
                break 2;
            }
        }
    }

    if (!$should_route) {
        return;
    }

    if (trim((string) $phpmailer->FromName) === '') {
        $phpmailer->FromName = 'Sharity';
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = 'aspmx.l.google.com;alt1.aspmx.l.google.com;alt2.aspmx.l.google.com;alt3.aspmx.l.google.com;alt4.aspmx.l.google.com';
    $phpmailer->Port = 25;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPSecure = false;
    $phpmailer->SMTPAutoTLS = false;
    $phpmailer->Timeout = 20;
}

function ic_send_report_mail($recipients, $subject, $message, $headers = []) {
    add_action('phpmailer_init', 'ic_report_mailer_route', 50);
    try {
        return wp_mail($recipients, $subject, $message, $headers);
    } finally {
        remove_action('phpmailer_init', 'ic_report_mailer_route', 50);
    }
}

function ic_json_ok($data = [], $status = 200) {
    $r = new WP_REST_Response($data, $status);
    return $r;
}

function ic_verify_state_nonce($req) {
    $nonce = $req->get_header('x_wp_nonce');
    if (!$nonce) {
        $nonce = $req->get_header('x-wp-nonce');
    }
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return ic_json_error('Érvénytelen vagy hiányzó kérésazonosító.', 403);
    }
    return true;
}

/* =========================================================================
   3. DB Migration
   ========================================================================= */

function ic_maybe_migrate_db() {
    $installed = get_option('ic_db_version', '0');
    if (version_compare($installed, IC_DB_VERSION, '>=')) {
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $p       = $wpdb->prefix;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    /* Sprint 1 tables */

    dbDelta("CREATE TABLE {$p}ic_circles (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type          ENUM('ngo','settlement') NOT NULL,
        ref_slug      VARCHAR(120) NOT NULL,
        name          VARCHAR(200) NOT NULL,
        description   TEXT,
        icon_url      VARCHAR(500),
        member_count  INT UNSIGNED DEFAULT 0,
        post_count    INT UNSIGNED DEFAULT 0,
        is_active     TINYINT(1) DEFAULT 1,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ref (type, ref_slug)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_memberships (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id   INT UNSIGNED NOT NULL,
        pid_hash    VARCHAR(64) NOT NULL,
        auto_joined TINYINT(1) DEFAULT 0,
        joined_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        left_at     DATETIME,
        is_active   TINYINT(1) DEFAULT 1,
        UNIQUE KEY uq_member (circle_id, pid_hash),
        KEY idx_pid (pid_hash),
        KEY idx_circle (circle_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_missions (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id    INT UNSIGNED,
        title        VARCHAR(200) NOT NULL,
        description  TEXT,
        reward_pts   SMALLINT UNSIGNED DEFAULT 20,
        reward_votes TINYINT UNSIGNED DEFAULT 1,
        is_active    TINYINT(1) DEFAULT 1,
        valid_from   DATETIME,
        valid_until  DATETIME,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_circle_active (circle_id, is_active)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_mission_completions (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mission_id  INT UNSIGNED NOT NULL,
        pid_hash    VARCHAR(64) NOT NULL,
        circle_id   INT UNSIGNED NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_completion (mission_id, pid_hash),
        KEY idx_circle (circle_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_buddies (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id        INT UNSIGNED NOT NULL,
        pid_a            VARCHAR(64) NOT NULL,
        pid_b            VARCHAR(64) NOT NULL,
        started_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at     DATETIME NULL DEFAULT NULL,
        bonus_paid       TINYINT(1) DEFAULT 0,
        opt_out_at       DATETIME NULL DEFAULT NULL,
        KEY idx_circle_a (circle_id, pid_a),
        KEY idx_circle_b (circle_id, pid_b),
        UNIQUE KEY uq_pair (circle_id, pid_a, pid_b)
    ) $charset;");

    /* Sprint 2 tables */

    dbDelta("CREATE TABLE {$p}ic_posts (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id     INT UNSIGNED NOT NULL,
        author_hash   VARCHAR(64) NOT NULL,
        author_type   ENUM('user','ngo','impi') DEFAULT 'user',
        post_type     ENUM('text','image','event','link','receipt','decision') DEFAULT 'text',
        body          TEXT NOT NULL,
        meta_json     JSON,
        vote_count    INT UNSIGNED DEFAULT 0,
        helpful_votes INT UNSIGNED DEFAULT 0,
        impi_boost    TINYINT(1) DEFAULT 0,
        is_pinned     TINYINT(1) DEFAULT 0,
        is_deleted    TINYINT(1) DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_circle_created (circle_id, created_at),
        KEY idx_author (author_hash),
        KEY idx_post_type (post_type)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_reports (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id       INT UNSIGNED NOT NULL,
        post_id         BIGINT UNSIGNED NOT NULL,
        reporter_hash   VARCHAR(64) NOT NULL,
        reason          VARCHAR(100) NOT NULL,
        details         TEXT,
        status          ENUM('pending','reviewed','dismissed','actioned') DEFAULT 'pending',
        reviewed_at     DATETIME NULL DEFAULT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_circle (circle_id),
        KEY idx_post (post_id),
        KEY idx_status_created (status, created_at),
        KEY idx_reporter (reporter_hash)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_ngo_accounts (
        id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id         INT UNSIGNED NOT NULL,
        ngo_slug          VARCHAR(120) NOT NULL,
        ngo_name          VARCHAR(200) NOT NULL,
        pid_hash          VARCHAR(64) NOT NULL,
        registry_id       VARCHAR(80) DEFAULT '',
        tax_number        VARCHAR(32) DEFAULT '',
        registration_number VARCHAR(80) DEFAULT '',
        official_name     VARCHAR(500) DEFAULT '',
        short_name        VARCHAR(255) DEFAULT '',
        org_type          VARCHAR(120) DEFAULT '',
        address           TEXT,
        nav_address       TEXT,
        activity          TEXT,
        description       TEXT,
        status_label      VARCHAR(120) DEFAULT '',
        status_code       SMALLINT DEFAULT NULL,
        level_of_charity  VARCHAR(120) DEFAULT '',
        representatives_json LONGTEXT,
        proceedings_json  LONGTEXT,
        company_payload_json LONGTEXT,
        company_last_checked_at DATETIME DEFAULT NULL,
        contact_email     VARCHAR(254) DEFAULT '',
        display_name      VARCHAR(120) DEFAULT '',
        status            ENUM('active','disabled') DEFAULT 'active',
        can_post_as_ngo   TINYINT(1) DEFAULT 1,
        ngo_pseudo_id     VARCHAR(120) NOT NULL DEFAULT '',
        phone             VARCHAR(32) DEFAULT ''
        phone_verified    TINYINT(1) DEFAULT 0,
        bank_account_number VARCHAR(64) DEFAULT '',
        bank_account_status ENUM('none','pending','verified') DEFAULT 'none',
        bank_account_stripe_session_id VARCHAR(128) DEFAULT NULL,
        registered_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_circle_pid (circle_id, pid_hash),
        KEY idx_ngo_slug (ngo_slug),
        KEY idx_pid (pid_hash)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_sms_otp (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pid_hash   VARCHAR(64) NOT NULL,
        phone      VARCHAR(32) NOT NULL,
        otp_hash   VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used       TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pid_phone (pid_hash, phone)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_circle_stats (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id       INT UNSIGNED NOT NULL,
        stat_date       DATE NOT NULL,
        posts_count     INT UNSIGNED DEFAULT 0,
        active_members  INT UNSIGNED DEFAULT 0,
        new_members     INT UNSIGNED DEFAULT 0,
        votes_generated INT UNSIGNED DEFAULT 0,
        pts_generated   INT UNSIGNED DEFAULT 0,
        UNIQUE KEY uq_circle_date (circle_id, stat_date)
    ) $charset;");

    /* Fix old ic_ngo_accounts schema: drop conflicting unique key on ngo_slug,
       and ensure all required columns exist (dbDelta adds them). */
    $old_key = $wpdb->get_var("SHOW INDEX FROM {$p}ic_ngo_accounts WHERE Key_name='uq_slug'");
    if ($old_key) {
        $wpdb->query("ALTER TABLE {$p}ic_ngo_accounts DROP INDEX uq_slug");
    }
    // Add missing columns if upgrading from old email/pw schema
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$p}ic_ngo_accounts LIKE 'circle_id'");
    if (empty($cols)) {
        $wpdb->query("ALTER TABLE {$p}ic_ngo_accounts
            ADD COLUMN circle_id        INT UNSIGNED NOT NULL DEFAULT 0 AFTER id,
            ADD COLUMN ngo_name         VARCHAR(200) NOT NULL DEFAULT '' AFTER ngo_slug,
            ADD COLUMN pid_hash         VARCHAR(64) NOT NULL DEFAULT '' AFTER ngo_name,
            ADD COLUMN registry_id      VARCHAR(80) DEFAULT '' AFTER pid_hash,
            ADD COLUMN tax_number       VARCHAR(32) DEFAULT '' AFTER registry_id,
            ADD COLUMN registration_number VARCHAR(80) DEFAULT '' AFTER tax_number,
            ADD COLUMN official_name    VARCHAR(500) DEFAULT '' AFTER registration_number,
            ADD COLUMN short_name       VARCHAR(255) DEFAULT '' AFTER official_name,
            ADD COLUMN org_type         VARCHAR(120) DEFAULT '' AFTER short_name,
            ADD COLUMN address          TEXT AFTER org_type,
            ADD COLUMN nav_address      TEXT AFTER address,
            ADD COLUMN activity         TEXT AFTER nav_address,
            ADD COLUMN description      TEXT AFTER activity,
            ADD COLUMN status_label     VARCHAR(120) DEFAULT '' AFTER description,
            ADD COLUMN status_code      SMALLINT DEFAULT NULL AFTER status_label,
            ADD COLUMN level_of_charity VARCHAR(120) DEFAULT '' AFTER status_code,
            ADD COLUMN representatives_json LONGTEXT AFTER level_of_charity,
            ADD COLUMN proceedings_json LONGTEXT AFTER representatives_json,
            ADD COLUMN company_payload_json LONGTEXT AFTER proceedings_json,
            ADD COLUMN company_last_checked_at DATETIME DEFAULT NULL AFTER company_payload_json,
            ADD COLUMN contact_email    VARCHAR(254) DEFAULT '' AFTER company_last_checked_at,
            ADD COLUMN display_name     VARCHAR(120) DEFAULT '' AFTER contact_email,
            ADD COLUMN status           ENUM('active','disabled') DEFAULT 'active' AFTER display_name,
            ADD COLUMN can_post_as_ngo  TINYINT(1) DEFAULT 0 AFTER status,
            ADD COLUMN phone            VARCHAR(32) DEFAULT '' AFTER can_post_as_ngo,
            ADD COLUMN phone_verified   TINYINT(1) DEFAULT 0 AFTER phone,
            ADD COLUMN bank_account_number VARCHAR(64) DEFAULT '' AFTER phone_verified,
            ADD COLUMN bank_account_status ENUM('none','pending','verified') DEFAULT 'none' AFTER bank_account_number,
            ADD COLUMN bank_account_stripe_session_id VARCHAR(128) DEFAULT NULL AFTER bank_account_status,
            ADD COLUMN registered_at    DATETIME DEFAULT CURRENT_TIMESTAMP AFTER bank_account_stripe_session_id,
            ADD COLUMN updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER registered_at,
            ADD UNIQUE KEY uq_circle_pid (circle_id, pid_hash),
            ADD KEY idx_ngo_slug (ngo_slug),
            ADD KEY idx_pid (pid_hash)
        ");
    }

    /* 1.5.0: add ngo_pseudo_id column for NGO login support */
    $has_ngo_pseudo_id = $wpdb->get_col("SHOW COLUMNS FROM {$p}ic_ngo_accounts LIKE 'ngo_pseudo_id'");
    if (empty($has_ngo_pseudo_id)) {
        $wpdb->query("ALTER TABLE {$p}ic_ngo_accounts ADD COLUMN ngo_pseudo_id VARCHAR(120) NOT NULL DEFAULT '' AFTER can_post_as_ngo");
    }

    /* Seed system-level micro-missions if table was just created */
    $has_missions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}ic_missions");
    if ($has_missions === 0) {
        ic_seed_missions($wpdb, $p);
    }

    update_option('ic_db_version', IC_DB_VERSION);
}

function ic_seed_missions($wpdb, $p) {
    $missions = [
        ['Köszönj meg egy posztot!', 'Köszönd meg egy körtársad posztját a 🙏 gombbal.', 15, 1],
        ['Írd le 3 mondatban, miért fontos neked ez az ügy!', 'Készíts egy rövid posztot arról, miért csatlakoztál a körhöz.', 25, 1],
        ['Hozz egy embert a körbe!', 'Hívj meg valakit a meghívó linkeddel — de csak olyat, aki tényleg aktív lesz.', 40, 2],
    ];
    foreach ($missions as $m) {
        $wpdb->insert("{$p}ic_missions", [
            'circle_id'   => null,
            'title'       => $m[0],
            'description' => $m[1],
            'reward_pts'  => $m[2],
            'reward_votes'=> $m[3],
            'is_active'   => 1,
            'created_at'  => current_time('mysql'),
        ]);
    }
}

add_action('admin_init', 'ic_maybe_migrate_db');
add_action('rest_api_init', function () {
    ic_maybe_migrate_db();
});

/* =========================================================================
   4. NGO Circle Seeding
   ========================================================================= */

/**
 * CLI or one-time seed: create a circle for each NGO slug in ngo_codes.csv.
 */
function ic_seed_ngo_circles() {
    global $wpdb;
    $p = $wpdb->prefix;

    $csv_url = 'https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv';
    $resp    = wp_remote_get($csv_url, ['timeout' => 15]);
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        return 0;
    }

    $body  = wp_remote_retrieve_body($resp);
    $lines = explode("\n", $body);
    $count = 0;

    foreach ($lines as $i => $line) {
        if ($i === 0 || trim($line) === '') {
            continue; // skip header
        }
        $cols = str_getcsv($line);
        if (count($cols) < 2) {
            continue;
        }
        $slug = sanitize_title($cols[0]);
        $name = sanitize_text_field($cols[1]);
        if ($slug === '' || $name === '') {
            continue;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}ic_circles WHERE type='ngo' AND ref_slug=%s",
            $slug
        ));
        if ($exists) {
            continue;
        }

        $wpdb->insert("{$p}ic_circles", [
            'type'     => 'ngo',
            'ref_slug' => $slug,
            'name'     => $name,
            'is_active'=> 1,
            'created_at' => current_time('mysql'),
        ]);
        $count++;
    }
    return $count;
}

/* =========================================================================
   5. REST API — impact/v1
   ========================================================================= */

add_action('rest_api_init', 'ic_register_rest_routes');

function ic_register_rest_routes() {
    $ns = 'impact/v1';

    /* --- Circles -------------------------------------------------------- */

    register_rest_route($ns, '/circles', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_circles_list',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/mine', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_circles_mine',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/feed/mine', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_feed_mine',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/seed', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_circles_seed',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route($ns, '/circles/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_circle_detail',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<id>\d+)/join', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_circle_join',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<id>\d+)/join', [
        'methods'  => 'DELETE',
        'callback' => 'ic_rest_circle_leave',
        'permission_callback' => '__return_true',
    ]);

    /* --- Posts ----------------------------------------------------------- */

    register_rest_route($ns, '/circles/(?P<id>\d+)/posts', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_posts_list',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<id>\d+)/posts', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_post_create',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/vote', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_post_vote',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/report', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_post_report',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)', [
        'methods'  => 'DELETE',
        'callback' => 'ic_rest_post_delete',
        'permission_callback' => '__return_true',
    ]);

    /* --- Auth / Nonce --------------------------------------------------- */

    register_rest_route($ns, '/auth/status', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_auth_status',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/mine', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_ngo_admin_mine',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/register', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_admin_register',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/company-search', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_admin_company_search',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/phone/send-otp', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_phone_send_otp',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/phone/verify-otp', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_phone_verify_otp',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/bank/init-verify', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_bank_init_verify',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/bank/stripe-webhook', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_bank_stripe_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/legal/review', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_admin_legal_review',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/impi/review', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_ngo_admin_legal_review',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/admin/impi/capabilities', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_ngo_admin_impi_capabilities',
        'permission_callback' => '__return_true',
    ]);
}

/* --- Circles handlers --------------------------------------------------- */

function ic_rest_circles_list($req) {
    global $wpdb;
    $p    = $wpdb->prefix;
    $type = sanitize_key($req->get_param('type') ?? '');
    $page = max(1, (int) ($req->get_param('page') ?? 1));
    $per  = IC_CIRCLES_PER_PAGE;
    $off  = ($page - 1) * $per;

    $where = "WHERE is_active = 1";
    $params = [];
    if ($type === 'ngo' || $type === 'settlement') {
        $where .= " AND type = %s";
        $params[] = $type;
    }

    $total = (int) $wpdb->get_var(
        $params
            ? $wpdb->prepare("SELECT COUNT(*) FROM {$p}ic_circles $where", ...$params)
            : "SELECT COUNT(*) FROM {$p}ic_circles $where"
    );

    $sql = "SELECT * FROM {$p}ic_circles $where ORDER BY member_count DESC, name ASC LIMIT %d OFFSET %d";
    $params[] = $per;
    $params[] = $off;
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    $pid_hash    = ic_pid_hash();
    $my_circles  = [];
    if ($pid_hash) {
        $my_raw = $wpdb->get_col($wpdb->prepare(
            "SELECT circle_id FROM {$p}ic_memberships WHERE pid_hash=%s AND is_active=1",
            $pid_hash
        ));
        $my_circles = array_map('intval', $my_raw);
    }

    $circles = [];
    foreach ($rows as $r) {
        $circles[] = [
            'id'           => (int) $r->id,
            'type'         => $r->type,
            'ref_slug'     => $r->ref_slug,
            'name'         => $r->name,
            'description'  => $r->description ?? '',
            'icon_url'     => $r->icon_url ?? '',
            'member_count' => (int) $r->member_count,
            'post_count'   => (int) $r->post_count,
            'is_member'    => in_array((int) $r->id, $my_circles, true),
        ];
    }

    return ic_json_ok([
        'circles'  => $circles,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per,
    ]);
}

function ic_rest_circles_mine($req) {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_ok(['circles' => []]);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.* FROM {$p}ic_circles c
         INNER JOIN {$p}ic_memberships m ON m.circle_id = c.id
         WHERE m.pid_hash = %s AND m.is_active = 1 AND c.is_active = 1
         ORDER BY m.joined_at DESC",
        $pid_hash
    ));

    $circles = [];
    foreach ($rows as $r) {
        $circles[] = [
            'id'           => (int) $r->id,
            'type'         => $r->type,
            'ref_slug'     => $r->ref_slug,
            'name'         => $r->name,
            'description'  => $r->description ?? '',
            'icon_url'     => $r->icon_url ?? '',
            'member_count' => (int) $r->member_count,
            'post_count'   => (int) $r->post_count,
            'is_member'    => true,
            'my_alias'     => IC_Alias::generate($pid_hash, (int) $r->id),
        ];
    }

    return ic_json_ok(['circles' => $circles]);
}

function ic_rest_feed_mine($req) {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_ok([
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => IC_POSTS_PER_PAGE,
            'has_more' => false,
            'unread_count' => 0,
        ]);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $page = max(1, (int) ($req->get_param('page') ?? 1));
    $per = (int) ($req->get_param('per_page') ?? IC_POSTS_PER_PAGE);
    if ($per < 1) {
        $per = IC_POSTS_PER_PAGE;
    }
    if ($per > 50) {
        $per = 50;
    }
    $off = ($page - 1) * $per;

    $type = sanitize_key($req->get_param('type') ?? '');
    $circle_id = (int) ($req->get_param('circle_id') ?? 0);
    $since = trim((string) ($req->get_param('since') ?? ''));

    $where = [
        'p.is_deleted = 0',
        'm.pid_hash = %s',
        'm.is_active = 1',
        'c.is_active = 1',
    ];
    $params = [$pid_hash];

    if ($type === 'ngo' || $type === 'settlement') {
        $where[] = 'c.type = %s';
        $params[] = $type;
    }

    if ($circle_id > 0) {
        $where[] = 'p.circle_id = %d';
        $params[] = $circle_id;
    }

    if ($since !== '') {
        $ts = strtotime($since);
        if ($ts !== false) {
            $where[] = 'p.created_at >= %s';
            $params[] = gmdate('Y-m-d H:i:s', $ts);
        }
    }

    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*)
        FROM {$p}ic_posts p
        INNER JOIN {$p}ic_memberships m ON m.circle_id = p.circle_id
        INNER JOIN {$p}ic_circles c ON c.id = p.circle_id
        WHERE {$where_sql}";
    $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$params));

    $rows_sql = "SELECT p.*, c.name AS circle_name, c.type AS circle_type, c.ref_slug AS circle_ref_slug
        FROM {$p}ic_posts p
        INNER JOIN {$p}ic_memberships m ON m.circle_id = p.circle_id
        INNER JOIN {$p}ic_circles c ON c.id = p.circle_id
        WHERE {$where_sql}
        ORDER BY p.is_pinned DESC, p.created_at DESC
        LIMIT %d OFFSET %d";
    $rows_params = $params;
    $rows_params[] = $per;
    $rows_params[] = $off;
    $rows = $wpdb->get_results($wpdb->prepare($rows_sql, ...$rows_params));

    $items = [];
    foreach ($rows as $row) {
        $meta = [
            'circle_name' => (string) $row->circle_name,
            'circle_type' => (string) $row->circle_type,
            'circle_ref_slug' => (string) $row->circle_ref_slug,
            'circle_color_token' => ic_circle_color_token((int) $row->circle_id),
        ];
        $items[] = ic_format_post($row, (int) $row->circle_id, $meta);
    }

    return ic_json_ok([
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $per,
        'has_more' => ($off + $per) < $total,
        'unread_count' => 0,
    ]);
}

function ic_format_post($post, $circle_id, $circle_meta = null) {
    $author_alias = IC_Alias::generate($post->author_hash, $circle_id);
    if ((string) $post->author_type === 'ngo') {
        $author_alias = '';
        if (is_array($circle_meta) && !empty($circle_meta['circle_name'])) {
            $author_alias = (string) $circle_meta['circle_name'];
        }
        if ($author_alias === '') {
            $circle = ic_get_circle($circle_id);
            if ($circle && !empty($circle->name)) {
                $author_alias = (string) $circle->name;
            }
        }
        if ($author_alias === '') {
            $author_alias = 'NGO';
        }
    }

    $result = [
        'id' => (int) $post->id,
        'circle_id' => (int) $post->circle_id,
        'author_alias' => $author_alias,
        'author_type' => $post->author_type,
        'post_type' => $post->post_type,
        'body' => esc_html($post->body),
        'meta' => $post->meta_json ? json_decode($post->meta_json, true) : null,
        'vote_count' => (int) $post->vote_count,
        'helpful_votes' => (int) $post->helpful_votes,
        'is_pinned' => (bool) $post->is_pinned,
        'impi_boost' => (bool) $post->impi_boost,
        'is_own' => $post->author_hash === ic_pid_hash(),
        'created_at' => $post->created_at,
        'time_ago' => ic_time_ago($post->created_at),
    ];

    if (is_array($circle_meta) && !empty($circle_meta)) {
        $result = array_merge($result, $circle_meta);
    }

    return $result;
}

function ic_time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'most';
    if ($diff < 3600) return floor($diff / 60) . ' perce';
    if ($diff < 86400) return floor($diff / 3600) . ' órája';
    if ($diff < 2592000) return floor($diff / 86400) . ' napja';
    return date('Y. m. d.', strtotime($datetime));
}

function ic_circle_color_token($circle_id) {
    $tokens = ['lagoon', 'mint', 'cobalt', 'amber', 'coral', 'slate', 'moss', 'rose', 'indigo', 'ember'];
    $count  = count($tokens);
    if ($count <= 0) {
        return 'slate';
    }

    $idx = ((int) $circle_id) % $count;
    if ($idx < 0) {
        $idx += $count;
    }
    return $tokens[$idx];
}

function ic_rest_circle_detail($req) {
    global $wpdb;
    $p  = $wpdb->prefix;
    $id = (int) $req->get_param('id');

    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_circles WHERE id=%d AND is_active=1", $id
    ));
    if (!$circle) {
        return ic_json_error('Kör nem található.', 404);
    }

    $pid_hash  = ic_pid_hash();
    $is_member = false;
    $my_alias  = '';
    if ($pid_hash) {
        $membership = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
            $id, $pid_hash
        ));
        $is_member = (bool) $membership;
        $my_alias  = IC_Alias::generate($pid_hash, $id);
    }

    // Last 3 posts
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE circle_id=%d AND is_deleted=0 ORDER BY is_pinned DESC, created_at DESC LIMIT 3",
        $id
    ));

    $post_list = [];
    foreach ($posts as $post) {
        $post_list[] = ic_format_post($post, $id);
    }

    return ic_json_ok([
        'circle' => [
            'id'           => (int) $circle->id,
            'type'         => $circle->type,
            'ref_slug'     => $circle->ref_slug,
            'name'         => $circle->name,
            'description'  => $circle->description ?? '',
            'icon_url'     => $circle->icon_url ?? '',
            'member_count' => (int) $circle->member_count,
            'post_count'   => (int) $circle->post_count,
            'is_member'    => $is_member,
            'my_alias'     => $my_alias,
        ],
        'recent_posts' => $post_list,
    ]);
}

function ic_rest_circle_join($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p  = $wpdb->prefix;
    $id = (int) $req->get_param('id');

    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_circles WHERE id=%d AND is_active=1", $id
    ));
    if (!$circle) {
        return ic_json_error('Kör nem található.', 404);
    }

    // Max circles check
    $current_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships WHERE pid_hash=%s AND is_active=1",
        $pid_hash
    ));
    if ($current_count >= IC_MAX_CIRCLES) {
        return ic_json_error('Maximum ' . IC_MAX_CIRCLES . ' körhöz csatlakozhatsz.', 422);
    }

    // Check if already member (or previously left)
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s",
        $id, $pid_hash
    ));

    if ($existing && (int) $existing->is_active === 1) {
        return ic_json_ok(['already_member' => true, 'alias' => IC_Alias::generate($pid_hash, $id)]);
    }

    if ($existing) {
        // Re-join
        $result = $wpdb->update("{$p}ic_memberships", [
            'is_active' => 1,
            'left_at'   => null,
            'joined_at' => current_time('mysql'),
        ], ['id' => $existing->id]);
    } else {
        $result = $wpdb->insert("{$p}ic_memberships", [
            'circle_id' => $id,
            'pid_hash'  => $pid_hash,
            'joined_at' => current_time('mysql'),
            'is_active' => 1,
        ]);
    }

    if ($result === false) {
        return ic_json_error('A csatlakozás mentése sikertelen volt.', 500);
    }

    // Increment member_count
    $count_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET member_count = member_count + 1 WHERE id = %d", $id
    ));
    if ($count_updated === false) {
        return ic_json_error('A csatlakozás részben sikerült, de a számláló nem frissült.', 500);
    }

    // Award points for first 3 circles
    if ($current_count < 3) {
        ic_award_points($pid_hash, 20, 'circle_join', "circle:{$id}", "circle_join:{$pid_hash}:{$id}");
    }

    // Buddy pairing attempt
    ic_try_buddy_pair($id, $pid_hash);

    $alias = IC_Alias::generate($pid_hash, $id);

    do_action('ic_member_joined', $id, $pid_hash, $alias);

    return ic_json_ok([
        'joined'  => true,
        'alias'   => $alias,
        'circle'  => ['id' => $id, 'name' => $circle->name],
    ]);
}

function ic_rest_circle_leave($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p  = $wpdb->prefix;
    $id = (int) $req->get_param('id');

    $membership = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
        $id, $pid_hash
    ));
    if (!$membership) {
        return ic_json_error('Nem vagy tagja ennek a körnek.', 404);
    }

    $left = $wpdb->update("{$p}ic_memberships", [
        'is_active' => 0,
        'left_at'   => current_time('mysql'),
    ], ['id' => $membership->id]);
    if ($left === false) {
        return ic_json_error('A kilépés mentése sikertelen volt.', 500);
    }

    $count_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET member_count = GREATEST(member_count - 1, 0) WHERE id = %d", $id
    ));
    if ($count_updated === false) {
        return ic_json_error('A kilépés részben sikerült, de a számláló nem frissült.', 500);
    }

    return ic_json_ok(['left' => true]);
}

function ic_rest_circles_seed($req) {
    $count = ic_seed_ngo_circles();
    return ic_json_ok(['seeded' => $count]);
}

/* --- Posts handlers ----------------------------------------------------- */

function ic_rest_posts_list($req) {
    global $wpdb;
    $p    = $wpdb->prefix;
    $cid  = (int) $req->get_param('id');
    $page = max(1, (int) ($req->get_param('page') ?? 1));
    $per  = IC_POSTS_PER_PAGE;
    $off  = ($page - 1) * $per;

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND is_deleted=0", $cid
    ));

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE circle_id=%d AND is_deleted=0
         ORDER BY is_pinned DESC, created_at DESC LIMIT %d OFFSET %d",
        $cid, $per, $off
    ));

    $posts = [];
    foreach ($rows as $r) {
        $posts[] = ic_format_post($r, $cid);
    }

    return ic_json_ok([
        'posts'    => $posts,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per,
    ]);
}

function ic_rest_post_create($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p   = $wpdb->prefix;
    $cid = (int) $req->get_param('id');

    // Membership check
    if (!ic_is_circle_member($cid, $pid_hash)) {
        return ic_json_error('Csak körtagok posztolhatnak.', 403);
    }

    // Rate limit: 5 posts/hour
    $rate_key = 'ic_post_rate:' . $pid_hash;
    if (!ic_rate_check($rate_key, IC_RATE_LIMIT_POSTS_PER_HOUR, 3600)) {
        return ic_json_error('Túl sok posztot küldtél. Próbáld újra később.', 429);
    }

    $body = trim(sanitize_textarea_field($req->get_param('body') ?? ''));
    if ($body === '') {
        return ic_json_error('A poszt szövege nem lehet üres.', 422);
    }
    if (mb_strlen($body) > IC_MAX_BODY_LENGTH) {
        return ic_json_error('Maximum ' . IC_MAX_BODY_LENGTH . ' karakter engedélyezett.', 422);
    }

    $post_type = sanitize_key($req->get_param('post_type') ?? 'text');
    if (!in_array($post_type, ['text', 'image', 'event', 'link', 'receipt', 'decision'], true)) {
        $post_type = 'text';
    }

    $as_ngo = filter_var($req->get_param('as_ngo'), FILTER_VALIDATE_BOOLEAN);
    $author_type = 'user';
    if ($as_ngo) {
        if (!ic_can_post_as_ngo($cid, $pid_hash)) {
            return ic_json_error('Ehhez a körhöz még nincs NGO-neves posztolási jogosultságod.', 403);
        }
        $author_type = 'ngo';
    }

    if ($post_type === 'decision' && $author_type !== 'ngo') {
        return ic_json_error('Decision posztot csak NGO admin jogosultsággal lehet közzétenni.', 403);
    }

    $meta = null;
    $meta_raw = $req->get_param('meta');
    if ($meta_raw && is_array($meta_raw)) {
        $meta = wp_json_encode($meta_raw);
    }

    $inserted = $wpdb->insert("{$p}ic_posts", [
        'circle_id'   => $cid,
        'author_hash' => $pid_hash,
        'author_type' => $author_type,
        'post_type'   => $post_type,
        'body'        => $body,
        'meta_json'   => $meta,
        'created_at'  => current_time('mysql'),
    ]);
    if ($inserted === false) {
        return ic_json_error('A poszt mentése sikertelen volt.', 500);
    }

    $post_id = (int) $wpdb->insert_id;
    if ($post_id <= 0) {
        return ic_json_error('A poszt mentése sikertelen volt.', 500);
    }

    // Increment post count
    $count_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET post_count = post_count + 1 WHERE id = %d", $cid
    ));
    if ($count_updated === false) {
        return ic_json_error('A poszt elment, de a számláló nem frissült.', 500);
    }

    // Activity points
    $today = current_time('Y-m-d');

    // First post in circle
    $prev_posts = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0 AND id != %d",
        $cid, $pid_hash, $post_id
    ));
    if ($prev_posts === 0) {
        ic_award_points($pid_hash, 75, 'first_post', "circle:{$cid}", "first_post:{$pid_hash}:{$cid}");
    }

    // Daily post points
    ic_award_points($pid_hash, 30, 'daily_post', "circle:{$cid}", "daily_post:{$pid_hash}:{$cid}:{$today}");

    do_action('ic_first_post_of_day', $cid, $pid_hash);

    $created_post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE id=%d", $post_id
    ));
    if (!$created_post) {
        return ic_json_error('A poszt létrejött, de a visszaolvasás sikertelen volt.', 500);
    }

    return ic_json_ok([
        'post' => ic_format_post($created_post, $cid),
    ], 201);
}

function ic_rest_post_vote($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param('circle_id');
    $post_id = (int) $req->get_param('post_id');

    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
        $post_id, $cid
    ));
    if (!$post) {
        return ic_json_error('Poszt nem található.', 404);
    }

    $is_member = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
        $cid, $pid_hash
    ));
    if (!$is_member) {
        return ic_json_error('Csak körtagok szavazhatnak.', 403);
    }

    // Can't vote own post
    if ($post->author_hash === $pid_hash) {
        return ic_json_error('Saját posztra nem szavazhatsz.', 422);
    }

    // Dedupe — one vote per post per user (using transient as simple dedupe)
    $dedupe_key = "ic_vote:{$pid_hash}:{$post_id}";
    if (get_transient($dedupe_key)) {
        return ic_json_error('Már szavaztál erre a posztra.', 422);
    }
    set_transient($dedupe_key, 1, DAY_IN_SECONDS * 365);

    // Increment vote
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_posts SET vote_count = vote_count + 1 WHERE id = %d", $post_id
    ));
    if ($updated === false) {
        delete_transient($dedupe_key);
        return ic_json_error('A szavazat mentése sikertelen volt.', 500);
    }

    // If post now has 5+ votes, award the author
    $new_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT vote_count FROM {$p}ic_posts WHERE id = %d", $post_id
    ));
    if ($new_count <= 0) {
        delete_transient($dedupe_key);
        return ic_json_error('A szavazat mentése sikertelen volt.', 500);
    }
    if ($new_count === 5) {
        // Only the current pseudo can be awarded directly. For other users, queue if supported.
        if ($post->author_hash === $pid_hash) {
            ic_award_points($post->author_hash, 50, 'post_5_votes', "post:{$post_id}", "post_5_votes:{$post->author_hash}:{$post_id}");
        } elseif (function_exists('ic_queue_points')) {
            ic_queue_points($post->author_hash, 50, 'post_5_votes', "post:{$post_id}", "post_5_votes:{$post->author_hash}:{$post_id}");
        }
    }

    return ic_json_ok(['vote_count' => $new_count]);
}

function ic_rest_post_delete($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param('circle_id');
    $post_id = (int) $req->get_param('post_id');

    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
        $post_id, $cid
    ));
    if (!$post) {
        return ic_json_error('Poszt nem található.', 404);
    }

    if ($post->author_hash !== $pid_hash) {
        return ic_json_error('Csak saját posztot törölhetsz.', 403);
    }

    $deleted = $wpdb->update("{$p}ic_posts", ['is_deleted' => 1], ['id' => $post_id]);
    if ($deleted === false) {
        return ic_json_error('A poszt törlése sikertelen volt.', 500);
    }

    $count_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET post_count = GREATEST(post_count - 1, 0) WHERE id = %d", $cid
    ));
    if ($count_updated === false) {
        return ic_json_error('A poszt törlődött, de a számláló nem frissült.', 500);
    }

    return ic_json_ok(['deleted' => true]);
}

function ic_rest_post_report($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param('circle_id');
    $post_id = (int) $req->get_param('post_id');

    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT id, circle_id, author_hash, body FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
        $post_id, $cid
    ));
    if (!$post) {
        return ic_json_error('Poszt nem található.', 404);
    }

    $is_member = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
        $cid, $pid_hash
    ));
    if (!$is_member) {
        return ic_json_error('Csak körtagok jelenthetnek.', 403);
    }

    if ($post->author_hash === $pid_hash) {
        return ic_json_error('Saját posztot nem lehet jelenteni.', 422);
    }

    $reason = trim(sanitize_textarea_field((string) ($req->get_param('reason') ?? '')));
    if ($reason === '') {
        return ic_json_error('A jelentés indoka kötelező.', 422);
    }
    if (mb_strlen($reason) > 300) {
        return ic_json_error('A jelentés indoka legfeljebb 300 karakter lehet.', 422);
    }

    $rate_key = 'ic_report_rate:' . $pid_hash;
    if (!ic_rate_check($rate_key, 20, HOUR_IN_SECONDS)) {
        return ic_json_error('Túl sok jelentést küldtél rövid idő alatt. Próbáld újra később.', 429);
    }

    $circle_name = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT name FROM {$p}ic_circles WHERE id=%d LIMIT 1",
        $cid
    ));
    $reporter_alias = IC_Alias::generate($pid_hash, $cid);
    $post_author_alias = IC_Alias::generate($post->author_hash, $cid);
    $reason_summary = mb_substr($reason, 0, 100);
    $details = wp_json_encode([
        'reason_full' => $reason,
        'post_author_alias' => $post_author_alias,
        'reporter_alias' => $reporter_alias,
        'post_excerpt' => mb_substr(wp_strip_all_tags((string) $post->body), 0, 400),
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $inserted = $wpdb->insert("{$p}ic_reports", [
        'circle_id' => $cid,
        'post_id' => $post_id,
        'reporter_hash' => $pid_hash,
        'reason' => $reason_summary,
        'details' => $details,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
    ]);
    if ($inserted === false) {
        error_log('ic_post_report_insert_failed: ' . $wpdb->last_error);
        return ic_json_error('A jelentés mentése sikertelen volt.', 500);
    }

    $report_id = (int) $wpdb->insert_id;

    $payload = [
        'report_id' => $report_id,
        'post_id' => $post_id,
        'circle_id' => $cid,
        'reporter_hash' => $pid_hash,
        'reason' => $reason,
        'created_at' => current_time('mysql'),
    ];

    $subject = sprintf('[Hatas Korok] Uj posztjelentes #%d', $report_id);
    $message = implode("\n", [
        'Új posztjelentés érkezett a Hatás Körökből.',
        '',
        'Riport azonosító: ' . $report_id,
        'Kör: ' . ($circle_name !== '' ? $circle_name : ('#' . $cid)) . ' (#' . $cid . ')',
        'Poszt ID: ' . $post_id,
        'Bejelentő álnév: ' . $reporter_alias,
        'Poszt szerzőjének álneve: ' . $post_author_alias,
        'Indok: ' . $reason,
        '',
        'Poszt részlet:',
        mb_substr(wp_strip_all_tags((string) $post->body), 0, 600),
        '',
        'Státusz: pending',
        'Oldal: ' . home_url('/hatas-korok/'),
    ]);
    $mail_headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: Sharity Impact <office@sharity.hu>',
        'Reply-To: Sharity Impact <office@sharity.hu>',
        'X-Sharity-Channel: impact-community-report',
    ];
    $mail_sent = ic_send_report_mail(['office@sharity.hu'], $subject, $message, $mail_headers);
    error_log('ic_post_report_mail_result: ' . wp_json_encode([
        'report_id' => $report_id,
        'sent' => (bool) $mail_sent,
        'to' => ['office@sharity.hu'],
        'subject' => $subject,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if (!$mail_sent) {
        error_log('ic_post_report_mail_failed: report_id=' . $report_id);
    }

    do_action('ic_post_reported', $payload);
    error_log('ic_post_report: ' . wp_json_encode($payload));

    return ic_json_ok(['reported' => true, 'report_id' => $report_id, 'emailed' => (bool) $mail_sent]);
}

/* --- Auth handler ------------------------------------------------------- */

function ic_rest_auth_status($req) {
    $pseudo = ic_get_pseudo_id();
    $pid_hash = ic_pid_hash($pseudo);

    return ic_json_ok([
        'authenticated' => $pseudo !== '',
        'pid_hash'      => $pid_hash ? substr($pid_hash, 0, 8) . '...' : '',
        'nonce'         => wp_create_nonce('wp_rest'),
        'ngo_admin_available' => true,
    ]);
}

function ic_rest_ngo_admin_mine($req) {
    $pid_hash = ic_pid_hash();
    if ($pid_hash === '') {
        return ic_json_ok(['ngo_admin' => []]);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id AS circle_id, c.name AS circle_name, c.ref_slug AS circle_slug, c.type AS circle_type,
                a.id AS account_id, a.status AS account_status, a.can_post_as_ngo, a.contact_email, a.display_name, a.registered_at,
                a.registry_id, a.tax_number, a.registration_number, a.official_name, a.short_name,
                a.org_type, a.address, a.nav_address, a.activity, a.description,
                a.status_label, a.status_code, a.level_of_charity,
                a.representatives_json, a.proceedings_json, a.company_last_checked_at,
                a.phone_verified, a.bank_account_status
         FROM {$p}ic_circles c
         INNER JOIN {$p}ic_memberships m ON m.circle_id=c.id
         LEFT JOIN {$p}ic_ngo_accounts a ON a.circle_id=c.id AND a.pid_hash=%s
         WHERE c.is_active=1 AND c.type='ngo' AND m.pid_hash=%s AND m.is_active=1
         ORDER BY c.name ASC",
        $pid_hash,
        $pid_hash
    ));

    $items = [];
    foreach ($rows as $row) {
        $fallback = [];
        $needs_fallback = (int) ($row->account_id ?? 0) <= 0 || empty($row->official_name);
        if ($needs_fallback) {
            $fallback = ic_ngo_get_company_fallback_for_circle((string) ($row->circle_name ?? ''), (string) ($row->circle_slug ?? ''));
            // Backfill the existing account row if it has an id but empty company data
            if ((int) ($row->account_id ?? 0) > 0 && !empty($fallback['official_name'])) {
                $wpdb->update(
                    "{$p}ic_ngo_accounts",
                    [
                        'official_name' => $fallback['official_name'] ?? '',
                        'tax_number' => $fallback['tax_number'] ?? '',
                        'registration_number' => $fallback['registration_number'] ?? '',
                        'address' => $fallback['address'] ?? '',
                        'org_type' => $fallback['org_type'] ?? '',
                        'description' => $fallback['description'] ?? '',
                        'level_of_charity' => $fallback['level_of_charity'] ?? '',
                        'representatives_json' => !empty($fallback['representatives']) ? wp_json_encode($fallback['representatives']) : null,
                        'company_last_checked_at' => current_time('mysql'),
                    ],
                    ['id' => (int) $row->account_id],
                    ['%s','%s','%s','%s','%s','%s','%s','%s','%s'],
                    ['%d']
                );
            }
        }
        $items[] = [
            'circle_id' => (int) $row->circle_id,
            'circle_name' => (string) $row->circle_name,
            'circle_slug' => (string) $row->circle_slug,
            'is_registered' => (int) $row->account_id > 0,
            'account_id' => (int) ($row->account_id ?? 0),
            'account_status' => (string) ($row->account_status ?? ''),
            'can_post_as_ngo' => (int) ($row->can_post_as_ngo ?? 0) === 1,
            'contact_email' => (string) ($row->contact_email ?? ''),
            'display_name' => (string) ($row->display_name ?? ''),
            'registered_at' => (string) ($row->registered_at ?? ''),
            'registry_id' => (string) ($row->registry_id ?: ($fallback['registry_id'] ?? '')),
            'tax_number' => (string) ($row->tax_number ?: ($fallback['tax_number'] ?? '')),
            'registration_number' => (string) ($row->registration_number ?: ($fallback['registration_number'] ?? '')),
            'official_name' => (string) ($row->official_name ?: ($fallback['official_name'] ?? '')),
            'short_name' => (string) ($row->short_name ?: ($fallback['short_name'] ?? '')),
            'org_type' => (string) ($row->org_type ?: ($fallback['org_type'] ?? '')),
            'address' => (string) ($row->address ?: ($fallback['address'] ?? '')),
            'nav_address' => (string) ($row->nav_address ?: ($fallback['nav_address'] ?? '')),
            'activity' => (string) ($row->activity ?: ($fallback['activity'] ?? '')),
            'description' => (string) ($row->description ?: ($fallback['description'] ?? '')),
            'status_label' => (string) ($row->status_label ?: ($fallback['status_label'] ?? '')),
            'status_code' => $row->status_code === null ? ($fallback['status_code'] ?? null) : (int) $row->status_code,
            'level_of_charity' => (string) ($row->level_of_charity ?: ($fallback['level_of_charity'] ?? '')),
            'representatives' => !empty($row->representatives_json) ? (json_decode((string) $row->representatives_json, true) ?: []) : ($fallback['representatives'] ?? []),
            'proceedings' => !empty($row->proceedings_json) ? (json_decode((string) $row->proceedings_json, true) ?: []) : ($fallback['proceedings'] ?? []),
            'company_last_checked_at' => (string) ($row->company_last_checked_at ?? ''),
            'bank_account_status' => (string) ($row->bank_account_status ?? 'none'),
            'phone_verified' => (int) ($row->phone_verified ?? 0) === 1,
            'company_data_source' => (int) ($row->account_id ?? 0) > 0 && !empty($row->official_name) ? 'ngo_account' : (string) ($fallback['source'] ?? ''),
        ];
    }

    return ic_json_ok(['ngo_admin' => $items]);
}

function ic_rest_ngo_admin_company_search($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if ($pid_hash === '') {
        return ic_json_error('Azonositas szukseges.', 401);
    }

    $query = trim((string) ($req->get_param('query') ?? ''));
    $query_variants = ic_ngo_query_variants($query);
    $tax_number = trim((string) ($req->get_param('tax_number') ?? ''));
    $tax_variants = ic_ngo_normalize_tax_number($tax_number);
    $limit = max(1, min(10, (int) ($req->get_param('limit') ?? 8)));

    if ($query === '' && $tax_number === '') {
        return ic_json_error('Adj meg nevet vagy adoszamot a kereseshez.', 422);
    }

    $client = ic_ngo_cegjelzo_client();
    if (is_wp_error($client)) {
        return ic_json_error($client->get_error_message(), 503);
    }

    $items = [];
    $seen = [];

    $append_company = static function (array $candidate) use (&$items, &$seen): void {
        $normalized = ic_ngo_normalize_company_record($candidate);
        $key = $normalized['registry_id'] !== '' ? $normalized['registry_id'] : ($normalized['tax_number'] !== '' ? $normalized['tax_number'] : sanitize_title($normalized['display_name']));
        if ($key === '' || isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $items[] = $normalized;
    };

    if (!empty($tax_variants)) {
        if (strlen(preg_replace('/\D+/', '', $tax_number)) < 8) {
            return ic_json_error('Tul rovid adoszam.', 422);
        }
        foreach ($tax_variants as $tax_search) {
            $tax_results = $client->search_civil_org($tax_search, 'tax_number', null, $limit);
            if (is_wp_error($tax_results)) {
                continue;
            }
            $tax_items = $tax_results['items'] ?? $tax_results['data'] ?? (isset($tax_results[0]) ? $tax_results : []);
            if (is_array($tax_items)) {
                foreach ($tax_items as $candidate) {
                    if (!is_array($candidate)) {
                        continue;
                    }
                    $append_company($candidate);
                }
            }
        }
    }

    if ($query !== '') {
        if (mb_strlen($query) < 3) {
            return ic_json_error('Minimum 3 karakter szukseges a nev szerinti kereseshez.', 422);
        }

        foreach ($query_variants as $query_variant) {
            $name_results = $client->search_civil_org($query_variant, 'name', null, $limit);
            if (is_wp_error($name_results)) {
                continue;
            }
            $name_items = $name_results['items'] ?? $name_results['data'] ?? (isset($name_results[0]) ? $name_results : []);
            if (is_array($name_items)) {
                foreach ($name_items as $candidate) {
                    if (!is_array($candidate)) {
                        continue;
                    }
                    $append_company($candidate);
                }
            }

            if (count($items) >= $limit) {
                break;
            }
        }

        if (count($items) < $limit) {
            foreach ($query_variants as $query_variant) {
                $auto_results = $client->autocomplete_civil_org($query_variant, $limit);
                if (!is_wp_error($auto_results)) {
                    $auto_items = $auto_results['items'] ?? $auto_results['data'] ?? (isset($auto_results[0]) ? $auto_results : []);
                    if (is_array($auto_items)) {
                        foreach ($auto_items as $auto_item) {
                            if (!is_array($auto_item)) {
                                continue;
                            }
                            $reg_number = ic_ngo_first_value($auto_item['id'] ?? $auto_item['registration_number'] ?? '');
                            if ($reg_number === '') {
                                continue;
                            }

                            $detail = $client->search_civil_org($reg_number, 'reg_number', null, 1);
                            if (is_wp_error($detail)) {
                                continue;
                            }
                            $detail_items = $detail['items'] ?? $detail['data'] ?? (isset($detail[0]) ? $detail : []);
                            if (!is_array($detail_items)) {
                                continue;
                            }
                            foreach ($detail_items as $candidate) {
                                if (!is_array($candidate)) {
                                    continue;
                                }
                                $append_company($candidate);
                            }
                        }
                    }
                }

                if (count($items) >= $limit) {
                    break;
                }
            }
        }
    }

    if (count($items) < $limit) {
        global $wpdb;
        $registry_table = $wpdb->prefix . 'impactshop_ngo_registry';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $registry_table));
        if ($exists === $registry_table) {
            $where_parts = [];
            $params = [];

            if ($query !== '') {
                $needle = '%' . $wpdb->esc_like($query) . '%';
                $where_parts[] = '(official_name LIKE %s OR short_name LIKE %s OR display_name LIKE %s)';
                $params[] = $needle;
                $params[] = $needle;
                $params[] = $needle;
            }

            if (!empty($tax_variants)) {
                $tax_wheres = [];
                foreach ($tax_variants as $tax) {
                    $tax_wheres[] = 'tax_number LIKE %s';
                    $params[] = '%' . $wpdb->esc_like($tax) . '%';
                }
                if (!empty($tax_wheres)) {
                    $where_parts[] = '(' . implode(' OR ', $tax_wheres) . ')';
                }
            }

            if (!empty($where_parts)) {
                $sql = "SELECT * FROM {$registry_table} WHERE " . implode(' AND ', $where_parts) . ' ORDER BY updated_at DESC LIMIT %d';
                $params[] = $limit;
                $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $append_company([
                            'registration_number' => (string) ($row['registration_number'] ?? $row['cegjelzo_id'] ?? ''),
                            'id' => (string) ($row['cegjelzo_id'] ?? ''),
                            'long_name' => (string) ($row['official_name'] ?? ''),
                            'short_name' => (string) ($row['short_name'] ?? ''),
                            'type' => (string) ($row['org_type'] ?? ''),
                            'address' => (string) ($row['address'] ?? ''),
                            'tax_number' => (string) ($row['tax_number'] ?? ''),
                            'activity' => (string) ($row['activity'] ?? ''),
                            'description' => (string) ($row['description'] ?? ''),
                            'status' => (string) ($row['status_label'] ?? ''),
                            'status_code' => isset($row['status_code']) ? (int) $row['status_code'] : null,
                            'level_of_charity' => (string) ($row['level_of_charity'] ?? ''),
                            'representatives' => !empty($row['representatives']) ? (json_decode((string) $row['representatives'], true) ?: []) : [],
                            'proceedings' => !empty($row['proceedings']) ? (json_decode((string) $row['proceedings'], true) ?: []) : [],
                        ]);
                    }
                }
            }
        }
    }

    if (!empty($items)) {
        foreach ($items as $idx => $item) {
            $items[$idx]['_match_score'] = ic_ngo_company_match_score($item, $query, $tax_number);
        }
        usort($items, static function (array $a, array $b): int {
            $left = (float) ($a['_match_score'] ?? 0);
            $right = (float) ($b['_match_score'] ?? 0);
            if ($left === $right) {
                return strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
            }
            return ($left > $right) ? -1 : 1;
        });
        foreach ($items as $idx => $item) {
            unset($items[$idx]['_match_score']);
        }
    }

    return ic_json_ok([
        'items' => array_slice($items, 0, $limit),
        'total' => count($items),
    ]);
}

function ic_rest_ngo_admin_register($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if ($pid_hash === '') {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    $circle_id = (int) ($req->get_param('circle_id') ?? 0);
    $selected_company = $req->get_param('selected_company');
    $normalized_company = [];

    if (is_array($selected_company) && !empty($selected_company)) {
        $normalized_company = ic_ngo_normalize_company_record($selected_company);
    }

    if ($circle_id > 0) {
        $circle = ic_get_circle($circle_id);
        if (!$circle) {
            return ic_json_error('Kör nem található.', 404);
        }
        if ((string) $circle->type !== 'ngo') {
            return ic_json_error('Csak NGO körhöz igényelhető NGO admin jogosultság.', 422);
        }
    } else {
        if (empty($normalized_company)) {
            return ic_json_error('Valassz ki egy NGO szervezetet Cegjelzo keresessel.', 422);
        }

        $circle = ic_ngo_find_or_create_circle_from_company($normalized_company);
        if (is_wp_error($circle)) {
            return ic_json_error($circle->get_error_message(), 422);
        }
        $circle_id = (int) $circle->id;
    }

    $is_member = ic_is_circle_member($circle_id, $pid_hash);

    $contact_email = sanitize_email((string) ($req->get_param('contact_email') ?? ''));
    $display_name = sanitize_text_field((string) ($req->get_param('display_name') ?? ''));
    $account_email = 'ngo-' . $circle_id . '-' . substr($pid_hash, 0, 16) . '@pseudo.local';

    global $wpdb;
    $p = $wpdb->prefix;

    if (!$is_member) {
        $existing_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s LIMIT 1",
            $circle_id,
            $pid_hash
        ));

        if ($existing_membership) {
            $membership_updated = $wpdb->update("{$p}ic_memberships", [
                'is_active' => 1,
                'auto_joined' => 1,
                'left_at' => null,
                'joined_at' => current_time('mysql'),
            ], ['id' => (int) $existing_membership->id]);

            if ($membership_updated === false) {
                return ic_json_error('A körtagság automatikus aktiválása sikertelen volt.', 500);
            }
        } else {
            $membership_inserted = $wpdb->insert("{$p}ic_memberships", [
                'circle_id' => $circle_id,
                'pid_hash' => $pid_hash,
                'auto_joined' => 1,
                'joined_at' => current_time('mysql'),
                'is_active' => 1,
            ]);

            if ($membership_inserted === false) {
                return ic_json_error('A körtagság automatikus létrehozása sikertelen volt.', 500);
            }
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$p}ic_circles SET member_count = member_count + 1 WHERE id=%d",
            $circle_id
        ));
    }

    $existing = ic_get_ngo_account($circle_id, $pid_hash);
    if ($existing) {
        $updated = $wpdb->update("{$p}ic_ngo_accounts", [
            'ngo_slug' => (string) $circle->ref_slug,
            'ngo_name' => (string) $circle->name,
            'registry_id' => (string) ($normalized_company['registry_id'] ?? ''),
            'tax_number' => (string) ($normalized_company['tax_number'] ?? ''),
            'registration_number' => (string) ($normalized_company['registration_number'] ?? ''),
            'official_name' => (string) ($normalized_company['official_name'] ?? ''),
            'short_name' => (string) ($normalized_company['short_name'] ?? ''),
            'org_type' => (string) ($normalized_company['org_type'] ?? ''),
            'address' => (string) ($normalized_company['address'] ?? ''),
            'nav_address' => (string) ($normalized_company['nav_address'] ?? ''),
            'activity' => (string) ($normalized_company['activity'] ?? ''),
            'description' => (string) ($normalized_company['description'] ?? ''),
            'status_label' => (string) ($normalized_company['status_label'] ?? ''),
            'status_code' => isset($normalized_company['status_code']) ? $normalized_company['status_code'] : null,
            'level_of_charity' => (string) ($normalized_company['level_of_charity'] ?? ''),
            'representatives_json' => wp_json_encode($normalized_company['representatives'] ?? []),
            'proceedings_json' => wp_json_encode($normalized_company['proceedings'] ?? []),
            'company_payload_json' => wp_json_encode($normalized_company['raw'] ?? []),
            'company_last_checked_at' => current_time('mysql'),
            'contact_email' => $contact_email,
            'display_name' => $display_name,
            'status' => 'active',
            'can_post_as_ngo' => 1,
        ], ['id' => (int) $existing->id]);

        if ($updated === false) {
            return ic_json_error('Az NGO admin jogosultság frissítése sikertelen volt.', 500);
        }
    } else {
        $inserted = $wpdb->insert("{$p}ic_ngo_accounts", [
            'circle_id' => $circle_id,
            'ngo_slug' => (string) $circle->ref_slug,
            'email' => $account_email,
            'ngo_name' => (string) $circle->name,
            'pid_hash' => $pid_hash,
            'registry_id' => (string) ($normalized_company['registry_id'] ?? ''),
            'tax_number' => (string) ($normalized_company['tax_number'] ?? ''),
            'registration_number' => (string) ($normalized_company['registration_number'] ?? ''),
            'official_name' => (string) ($normalized_company['official_name'] ?? ''),
            'short_name' => (string) ($normalized_company['short_name'] ?? ''),
            'org_type' => (string) ($normalized_company['org_type'] ?? ''),
            'address' => (string) ($normalized_company['address'] ?? ''),
            'nav_address' => (string) ($normalized_company['nav_address'] ?? ''),
            'activity' => (string) ($normalized_company['activity'] ?? ''),
            'description' => (string) ($normalized_company['description'] ?? ''),
            'status_label' => (string) ($normalized_company['status_label'] ?? ''),
            'status_code' => isset($normalized_company['status_code']) ? $normalized_company['status_code'] : null,
            'level_of_charity' => (string) ($normalized_company['level_of_charity'] ?? ''),
            'representatives_json' => wp_json_encode($normalized_company['representatives'] ?? []),
            'proceedings_json' => wp_json_encode($normalized_company['proceedings'] ?? []),
            'company_payload_json' => wp_json_encode($normalized_company['raw'] ?? []),
            'company_last_checked_at' => current_time('mysql'),
            'contact_email' => $contact_email,
            'display_name' => $display_name,
            'status' => 'active',
            'can_post_as_ngo' => 0,
            'registered_at' => current_time('mysql'),
        ]);

        if ($inserted === false) {
            return ic_json_error('Az NGO admin jogosultság mentése sikertelen volt.', 500);
        }
    }

    return ic_json_ok([
        'registered'     => true,
        'circle_id'      => $circle_id,
        'circle_name'    => (string) $circle->name,
        'company'        => $normalized_company,
        'joined_circle'  => !$is_member,
        'can_post_as_ngo'=> false,
    ]);
}

/**
 * GET /ngo/admin/impi/capabilities
 * Returns authoritative mode support flags based on current WP runtime configuration.
 */
function ic_rest_ngo_admin_impi_capabilities($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if ($pid_hash === '') {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    $legal_url = defined('IC_IMPI_LEGAL_REVIEW_URL') ? trim((string) IC_IMPI_LEGAL_REVIEW_URL) : '';
    $legal_token = defined('IC_IMPI_LEGAL_REVIEW_TOKEN') ? trim((string) IC_IMPI_LEGAL_REVIEW_TOKEN) : '';

    $image_url = defined('IC_IMPI_IMAGE_GENERATE_URL') ? trim((string) IC_IMPI_IMAGE_GENERATE_URL) : '';
    $image_token = defined('IC_IMPI_IMAGE_GENERATE_TOKEN') ? trim((string) IC_IMPI_IMAGE_GENERATE_TOKEN) : '';

    $marketing_url = defined('IC_IMPI_MARKETING_COPY_URL') ? trim((string) IC_IMPI_MARKETING_COPY_URL) : '';
    $marketing_token = defined('IC_IMPI_MARKETING_COPY_TOKEN') ? trim((string) IC_IMPI_MARKETING_COPY_TOKEN) : '';

    $ask_supported = $legal_url !== '' && $legal_token !== '';
    $image_supported = $image_url !== '' && $image_token !== '';
    $marketing_supported = $marketing_url !== '' && $marketing_token !== '';

    $capabilities = [
        'ask' => [
            'supported' => $ask_supported,
            'reason' => $ask_supported ? '' : 'Az Impi legal review mód jelenleg nem elérhető.',
        ],
        'image_generation' => [
            'supported' => $image_supported,
            'reason' => $image_supported ? '' : 'Az Impi kép generálás mód jelenleg nem elérhető.',
        ],
        'marketing_copy' => [
            'supported' => $marketing_supported,
            'reason' => $marketing_supported ? '' : 'Az Impi marketing szöveg mód jelenleg nem elérhető.',
        ],
    ];

    $supported_modes = [];
    foreach ($capabilities as $mode => $meta) {
        if (!empty($meta['supported'])) {
            $supported_modes[] = $mode;
        }
    }

    return ic_json_ok([
        'capabilities' => $capabilities,
        'supported_modes' => $supported_modes,
        'default_mode' => in_array('ask', $supported_modes, true) ? 'ask' : (isset($supported_modes[0]) ? $supported_modes[0] : null),
        'checked_at' => gmdate('c'),
    ]);
}

/**
 * POST /ngo/admin/legal/review
 * Body: { question, mode?, account_id?, circle_id?, context? }
 *
 * Review-only bridge to the Hataskorok Impi legal runtime.
 * Uses IC_IMPI_LEGAL_REVIEW_URL + IC_IMPI_LEGAL_REVIEW_TOKEN from wp-config.
 */
function ic_rest_ngo_admin_legal_review($req) {
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }

    $pid_hash = ic_pid_hash();
    if ($pid_hash === '') {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    $question = trim((string) ($req->get_param('question') ?? ''));
    if ($question === '') {
        return ic_json_error('A review kérdés kötelező.', 422);
    }

    $mode = sanitize_key((string) ($req->get_param('mode') ?? 'ask'));
    if ($mode === '') {
        $mode = 'ask';
    }

    $account_id = (int) ($req->get_param('account_id') ?? 0);
    $circle_id  = (int) ($req->get_param('circle_id') ?? 0);
    $context    = $req->get_param('context');
    if (!is_array($context)) {
        $context = [];
    }

    if ($account_id > 0) {
        global $wpdb;
        $p = $wpdb->prefix;
        $account = $wpdb->get_row($wpdb->prepare(
            "SELECT id, circle_id FROM {$p}ic_ngo_accounts WHERE id=%d AND pid_hash=%s LIMIT 1",
            $account_id,
            $pid_hash
        ));
        if (!$account) {
            return ic_json_error('NGO admin account nem található vagy nincs jogosultság.', 403);
        }
        if ($circle_id <= 0) {
            $circle_id = (int) $account->circle_id;
        }
    }

    if ($mode === 'image_generation') {
        $image = ic_impi_run_image_generation($question, [
            'mode'       => $mode,
            'circle_id'  => $circle_id,
            'account_id' => $account_id,
            'context'    => $context,
        ]);

        if (!is_wp_error($image)) {
            return ic_json_ok($image);
        }

        $fallback_question = 'Adj kepgeneralashoz reszletes, hasznalhato promptot a kovetkezo keresbol: ' . $question;
        $fallback = ic_impi_run_legal_review($fallback_question, [
            'mode'       => 'ask',
            'circle_id'  => $circle_id,
            'account_id' => $account_id,
            'context'    => $context,
        ]);

        if (is_wp_error($fallback)) {
            return ic_json_error($image->get_error_message(), 502);
        }

        if (is_array($fallback)) {
            $fallback['degraded_mode_fallback'] = true;
            $fallback['degraded_mode_reason'] = $image->get_error_message();
        }

        return ic_json_ok($fallback);
    }

    if ($mode === 'marketing_copy') {
        $marketing = ic_impi_run_marketing_copy($question, [
            'mode'       => $mode,
            'circle_id'  => $circle_id,
            'account_id' => $account_id,
            'context'    => $context,
        ]);

        if (!is_wp_error($marketing)) {
            return ic_json_ok($marketing);
        }

        $fallback_question = 'Keszits rovid, hasznalhato marketing szoveget a kovetkezo keresbol: ' . $question;
        $fallback = ic_impi_run_legal_review($fallback_question, [
            'mode'       => 'ask',
            'circle_id'  => $circle_id,
            'account_id' => $account_id,
            'context'    => $context,
        ]);

        if (is_wp_error($fallback)) {
            return ic_json_error($marketing->get_error_message(), 502);
        }

        if (is_array($fallback)) {
            $fallback['degraded_mode_fallback'] = true;
            $fallback['degraded_mode_reason'] = $marketing->get_error_message();
        }

        return ic_json_ok($fallback);
    }

    $review = ic_impi_run_legal_review($question, [
        'mode'       => $mode,
        'circle_id'  => $circle_id,
        'account_id' => $account_id,
        'context'    => $context,
    ]);

    if (is_wp_error($review)) {
        return ic_json_error($review->get_error_message(), 502);
    }

    return ic_json_ok($review);
}

/**
 * Runtime call to the dedicated Impi image-generation API gateway endpoint.
 */
function ic_impi_run_image_generation(string $question, array $options = []) {
    $image_url = defined('IC_IMPI_IMAGE_GENERATE_URL') ? trim((string) IC_IMPI_IMAGE_GENERATE_URL) : '';
    $image_token = defined('IC_IMPI_IMAGE_GENERATE_TOKEN') ? trim((string) IC_IMPI_IMAGE_GENERATE_TOKEN) : '';

    if ($image_url === '' || $image_token === '') {
        return new WP_Error('impi_image_generate_not_configured', 'Az Impi image-generation bridge nincs konfigurálva.');
    }

    $context = [];
    if (!empty($options['context']) && is_array($options['context'])) {
        $context = $options['context'];
    }

    $payload = [
        'prompt' => $question,
        'size' => sanitize_text_field((string) ($context['size'] ?? '1024x1024')),
        'ngo_context' => [
            'circle_id' => !empty($options['circle_id']) ? (int) $options['circle_id'] : 0,
            'account_id' => !empty($options['account_id']) ? (int) $options['account_id'] : 0,
            'context' => $context,
        ],
    ];

    $response = wp_remote_post($image_url, [
        'timeout' => 90,
        'headers' => [
            'Authorization' => 'Bearer ' . $image_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('impi_image_generate_http_error', 'Impi image-generation hívás sikertelen: ' . $response->get_error_message());
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($raw_body, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($decoded)) {
        return new WP_Error('impi_image_generate_bad_response', 'Impi image-generation válasz hibás vagy feldolgozhatatlan.');
    }

    $data = $decoded;
    if (array_key_exists('data', $decoded) && is_array($decoded['data'])) {
        $data = $decoded['data'];
    }

    $image_path = trim((string) ($data['image_url'] ?? ''));
    $answer = trim((string) ($data['answer'] ?? ''));
    if ($answer === '' && $image_path !== '') {
        $answer = 'Generalt kep: ' . $image_path;
    }

    if ($answer === '' && $image_path === '') {
        return new WP_Error('impi_image_generate_empty', 'Az Impi image-generation válasz nem tartalmazott megjeleníthető eredményt.');
    }

    return [
        'ok' => true,
        'mode' => 'image_generation',
        'image_url' => $image_path,
        'answer' => $answer,
        'moderation' => is_array($data['moderation'] ?? null) ? $data['moderation'] : null,
        'raw' => $data,
    ];
}

/**
 * Runtime call to the dedicated Impi marketing-copy API gateway endpoint.
 */
function ic_impi_run_marketing_copy(string $question, array $options = []) {
    $marketing_url = defined('IC_IMPI_MARKETING_COPY_URL') ? trim((string) IC_IMPI_MARKETING_COPY_URL) : '';
    $marketing_token = defined('IC_IMPI_MARKETING_COPY_TOKEN') ? trim((string) IC_IMPI_MARKETING_COPY_TOKEN) : '';

    if ($marketing_url === '' || $marketing_token === '') {
        return new WP_Error('impi_marketing_copy_not_configured', 'Az Impi marketing-copy bridge nincs konfigurálva.');
    }

    $context = [];
    if (!empty($options['context']) && is_array($options['context'])) {
        $context = $options['context'];
    }

    $payload = [
        'prompt' => $question,
        'tone' => sanitize_text_field((string) ($context['tone'] ?? 'friendly')),
        'ngo_context' => [
            'circle_id' => !empty($options['circle_id']) ? (int) $options['circle_id'] : 0,
            'account_id' => !empty($options['account_id']) ? (int) $options['account_id'] : 0,
            'context' => $context,
        ],
    ];

    $response = wp_remote_post($marketing_url, [
        'timeout' => 45,
        'headers' => [
            'Authorization' => 'Bearer ' . $marketing_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('impi_marketing_copy_http_error', 'Impi marketing-copy hívás sikertelen: ' . $response->get_error_message());
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($raw_body, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($decoded)) {
        return new WP_Error('impi_marketing_copy_bad_response', 'Impi marketing-copy válasz hibás vagy feldolgozhatatlan.');
    }

    $data = $decoded;
    if (array_key_exists('data', $decoded) && is_array($decoded['data'])) {
        $data = $decoded['data'];
    }

    $output = $data['output'] ?? null;
    $output_summary = '';
    if (is_array($output) && !empty($output['summary'])) {
        $output_summary = (string) $output['summary'];
    } elseif (is_string($output)) {
        $output_summary = $output;
    }

    $copy = trim((string) ($data['copy'] ?? $data['answer'] ?? $output_summary ?? ''));
    if ($copy === '') {
        return new WP_Error('impi_marketing_copy_empty', 'Az Impi marketing-copy válasz nem tartalmazott megjeleníthető szöveget.');
    }

    return [
        'ok' => true,
        'mode' => 'marketing_copy',
        'copy' => $copy,
        'answer' => $copy,
        'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : [],
        'raw' => $data,
    ];
}

/**
 * Runtime call to the legal review API gateway.
 */
function ic_impi_run_legal_review(string $question, array $options = []) {
    $review_url = defined('IC_IMPI_LEGAL_REVIEW_URL') ? trim((string) IC_IMPI_LEGAL_REVIEW_URL) : '';
    $review_token = defined('IC_IMPI_LEGAL_REVIEW_TOKEN') ? trim((string) IC_IMPI_LEGAL_REVIEW_TOKEN) : '';

    if ($review_url === '' || $review_token === '') {
        return new WP_Error('impi_review_not_configured', 'Az Impi legal review bridge nincs konfigurálva.');
    }

    $mode = sanitize_key((string) ($options['mode'] ?? 'ask'));
    if ($mode === '') {
        $mode = 'ask';
    }

    $payload = [
        'query' => $question,
        'mode'  => $mode,
    ];

    if (!empty($options['context']) && is_array($options['context'])) {
        $payload['context'] = $options['context'];
    }

    if (!empty($options['circle_id'])) {
        $payload['circle_id'] = (int) $options['circle_id'];
    }

    if (!empty($options['account_id'])) {
        $payload['account_id'] = (int) $options['account_id'];
    }

    $response = wp_remote_post($review_url, [
        'timeout' => 45,
        'headers' => [
            'Authorization' => 'Bearer ' . $review_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('impi_review_http_error', 'Impi review hívás sikertelen: ' . $response->get_error_message());
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($raw_body, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($decoded)) {
        return new WP_Error('impi_review_bad_response', 'Impi review válasz hibás vagy feldolgozhatatlan.');
    }

    $data = $decoded;
    if (array_key_exists('data', $decoded) && is_array($decoded['data'])) {
        $data = $decoded['data'];
    }

    $citation_check = is_array($data['citation_check'] ?? null) ? $data['citation_check'] : null;
    $hallucination_guard = null;
    if (is_array($data['hallucination_guard'] ?? null)) {
        $hallucination_guard = $data['hallucination_guard'];
    } elseif (is_array($data['hallucinationGuard'] ?? null)) {
        $hallucination_guard = $data['hallucinationGuard'];
    }

    $release_blocked = true;
    if (is_array($citation_check) && array_key_exists('blocked', $citation_check)) {
        $release_blocked = (bool) $citation_check['blocked'];
    } elseif (array_key_exists('release_blocked', $data)) {
        $release_blocked = (bool) $data['release_blocked'];
    }

    return [
        'ok'                  => true,
        'answer'              => (string) ($data['answer'] ?? ''),
        'confidence'          => $data['confidence'] ?? null,
        'sources'             => is_array($data['sources'] ?? null) ? $data['sources'] : [],
        'citation_check'      => $citation_check,
        'hallucination_guard' => $hallucination_guard,
        'release_blocked'     => $release_blocked,
        'raw'                 => $data,
    ];
}

/* --- SMS OTP — Vonage --------------------------------------------------- */

/**
 * Send a 6-digit OTP via Vonage SMS REST API.
 * Credentials come from wp-config constants VONAGE_API_KEY / VONAGE_API_SECRET / VONAGE_SMS_FROM.
 */
function ic_vonage_send_sms(string $to, string $text): bool
{
    $key    = defined('VONAGE_API_KEY')    ? (string) VONAGE_API_KEY    : '';
    $secret = defined('VONAGE_API_SECRET') ? (string) VONAGE_API_SECRET : '';
    $from   = defined('VONAGE_SMS_FROM')   ? (string) VONAGE_SMS_FROM   : 'ImpactShop';
    if ($key === '' || $secret === '') {
        error_log('ic_vonage_send_sms: VONAGE credentials missing');
        return false;
    }
    $response = wp_remote_post('https://rest.nexmo.com/sms/json', [
        'timeout' => 10,
        'body'    => [
            'api_key'    => $key,
            'api_secret' => $secret,
            'from'       => $from,
            'to'         => $to,
            'text'       => $text,
        ],
    ]);
    if (is_wp_error($response)) {
        error_log('ic_vonage_send_sms error: ' . $response->get_error_message());
        return false;
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status = (int) (($body['messages'][0]['status'] ?? -1));
    return $status === 0;
}

/**
 * POST /ngo/admin/phone/send-otp
 * Body: { phone, nonce }
 * Sends a 6-digit SMS OTP to the phone number and stores hashed OTP in ic_sms_otp.
 */
function ic_rest_ngo_phone_send_otp($req)
{
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Bejelentkezés szükséges.', 401);
    }
    $phone = sanitize_text_field((string) ($req->get_param('phone') ?? ''));
    // Normalize: keep only digits and leading +
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (strlen($phone) < 7 || strlen($phone) > 20) {
        return ic_json_error('Érvénytelen telefonszám.', 400);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    // Rate limit: max 3 OTP / phone / hour
    $recent = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_sms_otp WHERE phone=%s AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $phone
    ));
    if ($recent >= 3) {
        return ic_json_error('Túl sok kísérlet. Próbáld újra egy óra múlva.', 429);
    }

    $otp     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash = hash('sha256', $otp . $phone);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    $wpdb->insert("{$p}ic_sms_otp", [
        'pid_hash'   => $pid_hash,
        'phone'      => $phone,
        'otp_hash'   => $otp_hash,
        'expires_at' => $expires,
        'used'       => 0,
    ]);

    $sent = ic_vonage_send_sms($phone, "ImpactShop megerősítő kód: {$otp}. Érvényes 10 percig.");
    if (!$sent) {
        return ic_json_error('Az SMS küldése nem sikerült. Próbáld újra.', 500);
    }

    return ic_json_ok(['sent' => true]);
}

/**
 * POST /ngo/admin/phone/verify-otp
 * Body: { phone, otp, ngo_account_id, nonce }
 * Verifies the OTP and sets phone + phone_verified on the ic_ngo_accounts row.
 */
function ic_rest_ngo_phone_verify_otp($req)
{
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Bejelentkezés szükséges.', 401);
    }

    $phone          = preg_replace('/[^\d+]/', '', sanitize_text_field((string) ($req->get_param('phone') ?? '')));
    $otp            = preg_replace('/\D/', '', sanitize_text_field((string) ($req->get_param('otp') ?? '')));
    $ngo_account_id = (int) ($req->get_param('ngo_account_id') ?? 0);

    if (strlen($otp) !== 6 || strlen($phone) < 7) {
        return ic_json_error('Hiányzó adat.', 400);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $expected_hash = hash('sha256', $otp . $phone);

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_sms_otp
         WHERE pid_hash=%s AND phone=%s AND otp_hash=%s AND used=0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1",
        $pid_hash, $phone, $expected_hash
    ));

    if (!$row) {
        return ic_json_error('Hibás vagy lejárt kód.', 400);
    }

    // Mark as used
    $wpdb->update("{$p}ic_sms_otp", ['used' => 1], ['id' => (int) $row->id]);

    // Update ngo_accounts if account_id given and belongs to this pid_hash
    if ($ngo_account_id > 0) {
        $wpdb->update(
            "{$p}ic_ngo_accounts",
            ['phone' => $phone, 'phone_verified' => 1, 'can_post_as_ngo' => 1],
            ['id' => $ngo_account_id, 'pid_hash' => $pid_hash]
        );
    }

    return ic_json_ok(['verified' => true, 'phone' => $phone]);
}

/* --- Bank account Stripe verification ------------------------------------ */

/**
 * POST /ngo/admin/bank/init-verify
 * Body: { ngo_account_id, bank_account_number, nonce }
 * Creates a Stripe Checkout session (500 HUF) for bank account ownership verification.
 */
function ic_rest_ngo_bank_init_verify($req)
{
    $nonce_check = ic_verify_state_nonce($req);
    if ($nonce_check !== true) {
        return $nonce_check;
    }
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Bejelentkezés szükséges.', 401);
    }

    $ngo_account_id    = (int) ($req->get_param('ngo_account_id') ?? 0);
    $bank_account_number = sanitize_text_field((string) ($req->get_param('bank_account_number') ?? ''));

    if ($ngo_account_id <= 0 || $bank_account_number === '') {
        return ic_json_error('Hiányzó adat.', 400);
    }

    // Validate IBAN/HU format minimally
    $iban_clean = ic_iban_normalize($bank_account_number);
    if (strlen($iban_clean) < 8) {
        return ic_json_error('Érvénytelen bankszámlaszám.', 400);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $account = $wpdb->get_row($wpdb->prepare(
        "SELECT id, official_name, ngo_name FROM {$p}ic_ngo_accounts WHERE id=%d AND pid_hash=%s LIMIT 1",
        $ngo_account_id, $pid_hash
    ));
    if (!$account) {
        return ic_json_error('NGO admin bejegyzés nem található.', 404);
    }

    if (!defined('IMPACT_STRIPE_SECRET_KEY') || IMPACT_STRIPE_SECRET_KEY === '') {
        return ic_json_error('Stripe nincs konfigurálva.', 503);
    }

    $stripe_key = (string) IMPACT_STRIPE_SECRET_KEY;
    $site_url   = trailingslashit(home_url());
    $success_url = add_query_arg(['ic_bank_verified' => '1', 'session_id' => '{CHECKOUT_SESSION_ID}'], $site_url . '#ngo-admin');
    $cancel_url  = $site_url . '#ngo-admin';

    $official_name = (string) ($account->official_name ?: $account->ngo_name);

    $payload = [
        'mode'                 => 'payment',
        'payment_method_types' => ['card'],
        'line_items'           => [
            [
                'price_data' => [
                    'currency'     => 'huf',
                    'unit_amount'  => 50000, // 500 HUF in fillérs (Stripe HUF: 100 fillér = 1 HUF)
                    'product_data' => [
                        'name'        => 'Bankszámla-hitelesítés',
                        'description' => 'Sharity Adományszervező Alapítvány — visszatérítendő hitelesítési díj',
                    ],
                ],
                'quantity' => 1,
            ],
        ],
        'metadata' => [
            'ic_bank_verify'     => '1',
            'ngo_account_id'     => (string) $ngo_account_id,
            'official_name'      => $official_name,
            'bank_account_number'=> $bank_account_number,
        ],
        'success_url' => $success_url,
        'cancel_url'  => $cancel_url,
    ];

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($stripe_key . ':'),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => ic_stripe_encode($payload),
    ]);

    if (is_wp_error($response)) {
        error_log('ic_rest_ngo_bank_init_verify Stripe error: ' . $response->get_error_message());
        return ic_json_error('Stripe hiba. Próbáld újra.', 500);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['id']) || empty($body['url'])) {
        error_log('ic_rest_ngo_bank_init_verify Stripe bad response: ' . wp_json_encode($body));
        return ic_json_error('Stripe munkamenet létrehozása sikertelen.', 500);
    }

    // Store pending stripe session on the account
    $wpdb->update(
        "{$p}ic_ngo_accounts",
        [
            'bank_account_number'           => $bank_account_number,
            'bank_account_status'           => 'pending',
            'bank_account_stripe_session_id'=> sanitize_text_field($body['id']),
        ],
        ['id' => $ngo_account_id, 'pid_hash' => $pid_hash]
    );

    return ic_json_ok([
        'checkout_url' => esc_url_raw($body['url']),
        'session_id'   => sanitize_text_field($body['id']),
    ]);
}

/**
 * Recursively encode a nested array for Stripe's x-www-form-urlencoded body.
 */
function ic_stripe_encode(array $data, string $prefix = ''): string
{
    $parts = [];
    foreach ($data as $key => $value) {
        $full_key = $prefix !== '' ? "{$prefix}[{$key}]" : (string) $key;
        if (is_array($value)) {
            $parts[] = ic_stripe_encode($value, $full_key);
        } else {
            $parts[] = rawurlencode($full_key) . '=' . rawurlencode((string) $value);
        }
    }
    return implode('&', $parts);
}

/**
 * Normalize IBAN: remove spaces, dashes, uppercase.
 * Hungarian format: 2 × 8 digit groups separated by dashes → 16 digits raw.
 */
function ic_iban_normalize(string $raw): string
{
    return strtoupper(preg_replace('/[\s\-]/', '', $raw));
}

/**
 * Fuzzy name match: returns true if similarity >= $threshold (0–100).
 * Uses similar_text() which is available in all PHP versions on the server.
 */
function ic_fuzzy_name_match(string $a, string $b, int $threshold = 60): bool
{
    $a = mb_strtolower(trim($a));
    $b = mb_strtolower(trim($b));
    if ($a === '' || $b === '') {
        return false;
    }
    similar_text($a, $b, $pct);
    return (int) round($pct) >= $threshold;
}

/**
 * POST /ngo/admin/bank/stripe-webhook (no nonce — Stripe-signed)
 * Handles checkout.session.completed: verifies IBAN + fuzzy name, activates bank account.
 */
function ic_rest_ngo_bank_stripe_webhook($req)
{
    if (!defined('IMPACT_STRIPE_WEBHOOK_SECRET') || IMPACT_STRIPE_WEBHOOK_SECRET === '') {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $payload   = (string) file_get_contents('php://input');
    $sig       = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    $secret    = (string) IMPACT_STRIPE_WEBHOOK_SECRET;

    // Reuse the signature verifier from impactshop-event-donation-widget if available, else inline
    if (function_exists('impactshop_event_donation_verify_stripe_signature')) {
        $valid = impactshop_event_donation_verify_stripe_signature($payload, $sig, $secret);
    } else {
        $valid = ic_verify_stripe_signature($payload, $sig, $secret);
    }

    if (!$valid) {
        return new WP_REST_Response(['error' => 'invalid_signature'], 400);
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        return new WP_REST_Response(['error' => 'invalid_payload'], 400);
    }

    if ((string) ($event['type'] ?? '') !== 'checkout.session.completed') {
        return new WP_REST_Response(['status' => 'ignored'], 200);
    }

    $session  = (array) (($event['data']['object'] ?? []) ?: []);
    $metadata = (array) ($session['metadata'] ?? []);

    if (($metadata['ic_bank_verify'] ?? '') !== '1') {
        return new WP_REST_Response(['status' => 'ignored'], 200);
    }

    $ngo_account_id      = (int) ($metadata['ngo_account_id'] ?? 0);
    $official_name_meta  = (string) ($metadata['official_name'] ?? '');
    $bank_account_number = (string) ($metadata['bank_account_number'] ?? '');

    if ($ngo_account_id <= 0 || $bank_account_number === '') {
        return new WP_REST_Response(['error' => 'missing_metadata'], 400);
    }

    // Payer name from Stripe billing details
    $payer_name  = (string) ($session['customer_details']['name'] ?? '');
    $stored_iban = ic_iban_normalize($bank_account_number);

    global $wpdb;
    $p = $wpdb->prefix;

    $account = $wpdb->get_row($wpdb->prepare(
        "SELECT id, official_name, ngo_name, bank_account_number, bank_account_stripe_session_id
         FROM {$p}ic_ngo_accounts WHERE id=%d LIMIT 1",
        $ngo_account_id
    ));

    if (!$account) {
        return new WP_REST_Response(['error' => 'account_not_found'], 404);
    }

    // Verify stored session id matches
    $stored_session = (string) ($account->bank_account_stripe_session_id ?? '');
    $incoming_session = (string) ($session['id'] ?? '');
    if ($stored_session !== '' && $incoming_session !== '' && !hash_equals($stored_session, $incoming_session)) {
        error_log("ic_ngo_bank_stripe_webhook: session_id mismatch for account {$ngo_account_id}");
        return new WP_REST_Response(['error' => 'session_mismatch'], 400);
    }

    // IBAN exact match (normalized)
    $db_iban = ic_iban_normalize((string) ($account->bank_account_number ?? ''));
    if ($db_iban !== $stored_iban) {
        // IBAN doesn't match what was stored at init time
        return new WP_REST_Response(['error' => 'iban_mismatch'], 400);
    }

    // Fuzzy name match (payer name vs official NGO name)
    $ngo_official = (string) ($account->official_name ?: $account->ngo_name);
    $name_ok = ic_fuzzy_name_match($payer_name, $ngo_official, 55)
            || ic_fuzzy_name_match($payer_name, $official_name_meta, 55);

    if (!$name_ok) {
        error_log("ic_ngo_bank_stripe_webhook: name mismatch — payer='{$payer_name}' ngo='{$ngo_official}'");
        // Soft-fail: store as pending, log for manual review
        $wpdb->update(
            "{$p}ic_ngo_accounts",
            ['bank_account_status' => 'pending'],
            ['id' => $ngo_account_id]
        );
        return new WP_REST_Response(['status' => 'name_mismatch_pending'], 200);
    }

    $wpdb->update(
        "{$p}ic_ngo_accounts",
        ['bank_account_status' => 'verified'],
        ['id' => $ngo_account_id]
    );

    return new WP_REST_Response(['status' => 'verified'], 200);
}

/**
 * Inline Stripe HMAC-SHA256 signature verifier (fallback if donation widget not loaded).
 */
function ic_verify_stripe_signature(string $payload, string $sigHeader, string $secret): bool
{
    if ($payload === '' || $sigHeader === '' || $secret === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $sigHeader) as $item) {
        $pair = explode('=', trim($item), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }
    if (empty($parts['t']) || empty($parts['v1'])) {
        return false;
    }
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);
    foreach (explode(' ', str_replace(',', ' ', (string) $parts['v1'])) as $sig) {
        if ($sig !== '' && hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

/* =========================================================================
   7. Template Redirect — serve the app at /hatas-korok/
   ========================================================================= */

add_action('template_redirect', 'ic_app_template_redirect', 4);

function ic_app_template_redirect() {
    $path = ic_request_path();
    $raw_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $raw_path = parse_url($raw_uri, PHP_URL_PATH);
    if (!is_string($raw_path) || $raw_path === '') {
        $raw_path = '/';
    }

    $is_hatas_route = (bool) preg_match('~^/hatas-korok(?:-dev)?/?$~', $path)
        || (bool) preg_match('~^/(?:impactshop-staging/)?hatas-korok(?:-dev)?/?$~', $raw_path);

    if (!$is_hatas_route) {
        return;
    }

    $api_url = rest_url('impact/v1');
    $nonce   = wp_create_nonce('wp_rest');
    $pseudo  = ic_get_pseudo_id();

    global $wp_query;
    if (isset($wp_query) && method_exists($wp_query, 'is_404')) {
        $wp_query->is_404 = false;
    }
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');

    require __DIR__ . '/impact-community-app.php';
    exit;
}

