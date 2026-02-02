<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_BADGE_SCHEMA = '1.0.0';
const IMPACTSHOP_BADGE_OPTION_SCHEMA = 'impactshop_badge_schema_version';
const IMPACTSHOP_BADGE_OPTION_SEEDED = 'impactshop_badge_seeded';
const IMPACTSHOP_HEROWALL_OPTION_MIGRATED = 'impactshop_herowall_migrated';

add_action('muplugins_loaded', 'impactshop_badge_boot');

function impactshop_badge_boot(): void
{
    impactshop_badge_maybe_install();
    add_action('rest_api_init', 'impactshop_badge_register_routes');
    add_shortcode('impactshop_herowall', 'impactshop_herowall_shortcode');
    add_shortcode('impactshop_challenges', 'impactshop_challenges_shortcode');

    add_action('impactshop_ads_view_recorded', 'impactshop_badge_on_ads_view', 10, 2);
    add_action('impactshop_offerwall_rewards_awarded', 'impactshop_badge_on_offerwall', 10, 2);
    add_action('impactshop_edu_video_completed', 'impactshop_badge_on_edu_video', 10, 2);
    add_action('sharity_points_earned', 'impactshop_badge_on_points_earned', 10, 4);
}

function impactshop_badge_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_BADGE_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_BADGE_SCHEMA) {
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $tables = [];
    $tables[] = "CREATE TABLE {$wpdb->prefix}impact_badge_definitions (
        badge_key VARCHAR(64) NOT NULL,
        category VARCHAR(64) NOT NULL,
        name_hu VARCHAR(190) NOT NULL,
        description_hu TEXT,
        default_tier VARCHAR(16) DEFAULT 'bronze',
        icon_url TEXT,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        PRIMARY KEY (badge_key),
        KEY idx_category (category)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}impact_user_badges (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        badge_key VARCHAR(64) NOT NULL,
        tier VARCHAR(16) NOT NULL,
        source VARCHAR(64) DEFAULT '',
        awarded_at DATETIME NOT NULL,
        metadata LONGTEXT,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_badge (pseudo_id, badge_key),
        KEY idx_pseudo (pseudo_id)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}impact_badge_progress (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        badge_key VARCHAR(64) NOT NULL,
        current_value INT DEFAULT 0,
        target_value INT DEFAULT 0,
        last_updated DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_progress (pseudo_id, badge_key),
        KEY idx_pseudo (pseudo_id)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}impact_herowall (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        nickname VARCHAR(64) DEFAULT '',
        badge_points INT DEFAULT 0,
        badge_count INT DEFAULT 0,
        herowall_tier VARCHAR(16) DEFAULT 'bronze',
        tier_achieved_at DATETIME NOT NULL,
        legacy_message VARCHAR(280) DEFAULT '',
        is_visible TINYINT(1) DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_pseudo (pseudo_id),
        KEY idx_tier (herowall_tier)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    update_option(IMPACTSHOP_BADGE_OPTION_SCHEMA, IMPACTSHOP_BADGE_SCHEMA, false);

    if (!get_option(IMPACTSHOP_BADGE_OPTION_SEEDED)) {
        impactshop_badge_seed_definitions();
        update_option(IMPACTSHOP_BADGE_OPTION_SEEDED, '1', false);
    }
}

function impactshop_badge_seed_definitions(): void
{
    $defs = [
        ['streak_3', 'aktivitas', '3 napos streak', 'Egymás után 3 nap aktivitás.', 'bronze'],
        ['streak_7', 'aktivitas', '7 napos streak', 'Egymás után 7 nap aktivitás.', 'silver'],
        ['streak_30', 'aktivitas', '30 napos streak', 'Egymás után 30 nap aktivitás.', 'gold'],
        ['streak_100', 'aktivitas', '100 napos streak', 'Egymás után 100 nap aktivitás.', 'platinum'],
        ['streak_365', 'aktivitas', '365 napos streak', 'Egymás után 365 nap aktivitás.', 'diamond'],
        ['views_1', 'aktivitas', 'Első videó', '1 megtekintett videó.', 'bronze'],
        ['views_10', 'aktivitas', '10 videó', '10 megtekintett videó.', 'silver'],
        ['views_100', 'aktivitas', '100 videó', '100 megtekintett videó.', 'gold'],
        ['views_1000', 'aktivitas', '1000 videó', '1000 megtekintett videó.', 'platinum'],
        ['views_5000', 'aktivitas', '5000 videó', '5000 megtekintett videó.', 'diamond'],
        ['views_10000', 'aktivitas', '10000 videó', '10000 megtekintett videó.', 'legend'],
        ['first_vote', 'tamogatas', 'Első szavazat', 'Első szavazat leadása.', 'bronze'],
        ['votes_10', 'tamogatas', '10 szavazat', '10 leadott szavazat.', 'silver'],
        ['votes_100', 'tamogatas', '100 szavazat', '100 leadott szavazat.', 'gold'],
        ['votes_1000', 'tamogatas', '1000 szavazat', '1000 leadott szavazat.', 'platinum'],
        ['votes_5000', 'tamogatas', '5000 szavazat', '5000 leadott szavazat.', 'diamond'],
        ['votes_10000', 'tamogatas', '10000 szavazat', '10000 leadott szavazat.', 'legend'],
        ['ngo_1', 'tamogatas', 'Első szervezet', 'Első támogatott szervezet.', 'bronze'],
        ['ngo_10', 'tamogatas', '10 szervezet', '10 különböző szervezet támogatása.', 'silver'],
        ['ngo_100', 'tamogatas', '100 szervezet', '100 különböző szervezet támogatása.', 'gold'],
        ['ngo_loyal', 'tamogatas', 'Hűséges támogató', 'Ugyanazon szervezet támogatása 30 napon át.', 'silver'],
        ['first_edu_video', 'tanulas', 'Első edukáció', 'Első edukációs videó.', 'bronze'],
        ['edu_complete_5', 'tanulas', '5 edukáció', '5 edukációs videó.', 'silver'],
        ['edu_complete_20', 'tanulas', '20 edukáció', '20 edukációs videó.', 'gold'],
        ['edu_complete_50', 'tanulas', '50 edukáció', '50 edukációs videó.', 'platinum'],
        ['edu_complete_100', 'tanulas', '100 edukáció', '100 edukációs videó.', 'diamond'],
        ['quiz_master', 'tanulas', 'Quiz mester', '10 kvíz sikeres teljesítése.', 'platinum'],
        ['first_offer', 'offerwall', 'Első offer', 'Első offerwall teljesítés.', 'bronze'],
        ['offers_10', 'offerwall', '10 offer', '10 offerwall teljesítés.', 'silver'],
        ['offers_100', 'offerwall', '100 offer', '100 offerwall teljesítés.', 'gold'],
        ['offers_500', 'offerwall', '500 offer', '500 offerwall teljesítés.', 'platinum'],
        ['offers_1000', 'offerwall', '1000 offer', '1000 offerwall teljesítés.', 'diamond'],
        ['high_value_offer', 'offerwall', 'Prémium offer', 'Egy kiemelkedő értékű teljesítés.', 'platinum'],
        ['early_adopter', 'specialis', 'Korai belépő', 'Korai használó.', 'bronze'],
        ['seasonal_xmas', 'specialis', 'Téli kihívás', 'Különleges téli kampány jelvény.', 'silver'],
        ['referral_1', 'kozosseg', 'Ajánlás', 'Első sikeres ajánlás.', 'bronze'],
        ['anniversary_1', 'kozosseg', 'Évforduló', '1 éve Sharity tag.', 'bronze'],
        ['anniversary_2', 'kozosseg', 'Évforduló', '2 éve Sharity tag.', 'silver'],
        ['anniversary_3', 'kozosseg', 'Évforduló', '3 éve Sharity tag.', 'gold'],
        ['anniversary_4', 'kozosseg', 'Évforduló', '4 éve Sharity tag.', 'platinum'],
        ['anniversary_5', 'kozosseg', 'Évforduló', '5 éve Sharity tag.', 'legend'],
    ];

    global $wpdb;
    $table = $wpdb->prefix . 'impact_badge_definitions';
    foreach ($defs as $def) {
        $wpdb->replace(
            $table,
            [
                'badge_key' => $def[0],
                'category' => $def[1],
                'name_hu' => $def[2],
                'description_hu' => $def[3],
                'default_tier' => $def[4],
                'is_active' => 1,
                'sort_order' => 0,
            ],
            ['%s','%s','%s','%s','%s','%d','%d']
        );
    }

    $active_keys = array_map(function ($row) {
        return (string) $row[0];
    }, $defs);
    $placeholders = implode(',', array_fill(0, count($active_keys), '%s'));
    if ($placeholders !== '') {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0 WHERE badge_key NOT IN ({$placeholders})",
            $active_keys
        ));
    }
}

function impactshop_badge_points_for_tier(string $tier): int
{
    switch ($tier) {
        case 'diamond':
            return 200;
        case 'legend':
            return 320;
        case 'silver':
            return 25;
        case 'gold':
            return 60;
        case 'platinum':
            return 120;
        case 'bronze':
        default:
            return 10;
    }
}

function impactshop_herowall_tier_for_points(int $points): string
{
    if ($points >= 1400) {
        return 'legend';
    }
    if ($points >= 800) {
        return 'diamond';
    }
    if ($points >= 400) {
        return 'platinum';
    }
    if ($points >= 200) {
        return 'gold';
    }
    if ($points >= 80) {
        return 'silver';
    }
    if ($points >= 20) {
        return 'bronze';
    }
    return 'bronze';
}

function impact_award_badge(string $pseudo_id, string $badge_key, string $tier = 'bronze', string $source = '', array $metadata = []): bool
{
    if ($pseudo_id === '' || $badge_key === '') {
        return false;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impact_user_badges';
    $inserted = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table} (pseudo_id, badge_key, tier, source, awarded_at, metadata)
         VALUES (%s, %s, %s, %s, %s, %s)",
        $pseudo_id,
        $badge_key,
        $tier,
        $source,
        current_time('mysql'),
        !empty($metadata) ? wp_json_encode($metadata) : null
    ));

    if ($inserted > 0) {
        do_action('impact_badge_awarded', $pseudo_id, $badge_key, $tier);
        do_action('impact_track_badge_award', $pseudo_id, $badge_key, $tier, $source);
        impact_update_herowall($pseudo_id);
        if (function_exists('impactshop_ads_watch_track_ga4')) {
            impactshop_ads_watch_track_ga4('badge_awarded', [
                'badge_key' => $badge_key,
                'tier' => $tier,
                'source' => $source,
            ]);
        }
        return true;
    }

    return false;
}

function impact_upgrade_badge(string $pseudo_id, string $badge_key, string $new_tier): bool
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_user_badges';
    $updated = $wpdb->update(
        $table,
        ['tier' => $new_tier, 'awarded_at' => current_time('mysql')],
        ['pseudo_id' => $pseudo_id, 'badge_key' => $badge_key],
        ['%s','%s'],
        ['%s','%s']
    );

    if ($updated) {
        do_action('impact_badge_awarded', $pseudo_id, $badge_key, $new_tier);
        do_action('impact_track_badge_award', $pseudo_id, $badge_key, $new_tier, 'upgrade');
        impact_update_herowall($pseudo_id);
        if (function_exists('impactshop_ads_watch_track_ga4')) {
            impactshop_ads_watch_track_ga4('badge_awarded', [
                'badge_key' => $badge_key,
                'tier' => $new_tier,
                'source' => 'upgrade',
            ]);
        }
        return true;
    }

    return false;
}

function impact_get_user_badges(string $pseudo_id): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_user_badges';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT badge_key, tier, source, awarded_at, metadata FROM {$table} WHERE pseudo_id = %s",
        $pseudo_id
    ), ARRAY_A);
}

function impact_has_badge(string $pseudo_id, string $badge_key): bool
{
    if ($pseudo_id === '' || $badge_key === '') {
        return false;
    }
    $badges = impact_get_user_badges($pseudo_id);
    foreach ($badges as $badge) {
        if (($badge['badge_key'] ?? '') === $badge_key) {
            return true;
        }
    }
    return false;
}

function impact_get_herowall_tier(string $pseudo_id): string
{
    if ($pseudo_id === '') {
        return 'bronze';
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impact_herowall';
    $tier = (string) $wpdb->get_var(
        $wpdb->prepare("SELECT herowall_tier FROM {$table} WHERE pseudo_id = %s", $pseudo_id)
    );
    return $tier !== '' ? $tier : impactshop_herowall_tier_for_points(impact_calculate_badge_points($pseudo_id));
}

function impact_increment_badge_progress(string $pseudo_id, string $badge_key, int $increment, int $target): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_badge_progress';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, current_value FROM {$table} WHERE pseudo_id = %s AND badge_key = %s",
        $pseudo_id,
        $badge_key
    ), ARRAY_A);

    $current = $row ? (int) $row['current_value'] : 0;
    $current += $increment;

    if ($row) {
        $wpdb->update(
            $table,
            ['current_value' => $current, 'target_value' => $target, 'last_updated' => current_time('mysql')],
            ['id' => (int) $row['id']],
            ['%d','%d','%s'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            $table,
            [
                'pseudo_id' => $pseudo_id,
                'badge_key' => $badge_key,
                'current_value' => $current,
                'target_value' => $target,
                'last_updated' => current_time('mysql'),
            ],
            ['%s','%s','%d','%d','%s']
        );
    }

    return ['current' => $current, 'target' => $target];
}

function impact_calculate_badge_points(string $pseudo_id): int
{
    $badges = impact_get_user_badges($pseudo_id);
    $points = 0;
    foreach ($badges as $badge) {
        $points += impactshop_badge_points_for_tier((string) $badge['tier']);
    }
    return $points;
}

function impact_update_herowall(string $pseudo_id): void
{
    if ($pseudo_id === '') {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impact_herowall';
    $badge_points = impact_calculate_badge_points($pseudo_id);
    $badge_count = count(impact_get_user_badges($pseudo_id));
    $new_tier = impactshop_herowall_tier_for_points($badge_points);

    $nickname = '';
    if (function_exists('impactshop_identity_profile_load')) {
        $nickname = (string) impactshop_identity_profile_load($pseudo_id);
    }

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, herowall_tier FROM {$table} WHERE pseudo_id = %s",
        $pseudo_id
    ), ARRAY_A);

    if ($existing) {
        $current_tier = (string) $existing['herowall_tier'];
        $tiers = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4, 'legend' => 5];
        $should_update_tier = ($tiers[$new_tier] ?? 0) > ($tiers[$current_tier] ?? 0);
        $wpdb->update(
            $table,
            [
                'nickname' => $nickname,
                'badge_points' => $badge_points,
                'badge_count' => $badge_count,
                'herowall_tier' => $should_update_tier ? $new_tier : $current_tier,
                'tier_achieved_at' => $should_update_tier ? current_time('mysql') : $existing['tier_achieved_at'],
            ],
            ['id' => (int) $existing['id']],
            ['%s','%d','%d','%s','%s'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            $table,
            [
                'pseudo_id' => $pseudo_id,
                'nickname' => $nickname,
                'badge_points' => $badge_points,
                'badge_count' => $badge_count,
                'herowall_tier' => $new_tier,
                'tier_achieved_at' => current_time('mysql'),
            ],
            ['%s','%s','%d','%d','%s','%s']
        );
    }
}

function impact_set_legacy_message(string $pseudo_id, string $message): bool
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_herowall';
    $message = trim(wp_strip_all_tags($message));
    if (mb_strlen($message) > 280) {
        $message = mb_substr($message, 0, 280);
    }

    return (bool) $wpdb->update(
        $table,
        ['legacy_message' => $message],
        ['pseudo_id' => $pseudo_id],
        ['%s'],
        ['%s']
    );
}

function impactshop_badge_register_routes(): void
{
    register_rest_route('impact/v1', '/badges/user', [
        'methods' => 'GET',
        'callback' => 'impactshop_badges_user',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/badges/progress', [
        'methods' => 'GET',
        'callback' => 'impactshop_badges_progress',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/badges/available', [
        'methods' => 'GET',
        'callback' => 'impactshop_badges_available',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/herowall', [
        'methods' => 'GET',
        'callback' => 'impactshop_herowall_api',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/herowall/legacy', [
        'methods' => 'POST',
        'callback' => 'impactshop_herowall_legacy',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/health', [
        'methods' => 'GET',
        'callback' => 'impactshop_badge_health',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_badges_user(): WP_REST_Response
{
    $pseudo_id = impactshop_badge_get_pseudo();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['badges' => [], 'badge_points' => 0], 200);
    }
    $badges = impact_get_user_badges($pseudo_id);
    $points = impact_calculate_badge_points($pseudo_id);
    return new WP_REST_Response(['badges' => $badges, 'badge_points' => $points], 200);
}

function impactshop_badges_progress(): WP_REST_Response
{
    $pseudo_id = impactshop_badge_get_pseudo();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['progress' => []], 200);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impact_badge_progress';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT badge_key, current_value, target_value, last_updated FROM {$table} WHERE pseudo_id = %s",
        $pseudo_id
    ), ARRAY_A);
    return new WP_REST_Response(['progress' => $rows], 200);
}

function impactshop_badges_available(): WP_REST_Response
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_badge_definitions';
    $rows = $wpdb->get_results("SELECT badge_key, category, name_hu, description_hu, default_tier, icon_url, sort_order FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A);
    return new WP_REST_Response(['badges' => $rows], 200);
}

function impactshop_badge_definitions_map(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impact_badge_definitions';
    $rows = $wpdb->get_results(
        "SELECT badge_key, category, name_hu, description_hu, default_tier, icon_url FROM {$table} WHERE is_active = 1",
        ARRAY_A
    );
    $cache = [];
    foreach ($rows as $row) {
        if (!empty($row['badge_key'])) {
            $cache[(string) $row['badge_key']] = $row;
        }
    }
    return $cache;
}

function impactshop_badge_group_key(string $badge_key): string
{
    if ($badge_key === '') {
        return '';
    }
    if (preg_match('/^(.*)_\d+$/', $badge_key, $matches)) {
        return $matches[1];
    }
    return $badge_key;
}

function impactshop_badge_suffix_number(string $badge_key): int
{
    if (preg_match('/_(\d+)$/', $badge_key, $matches)) {
        return (int) $matches[1];
    }
    return 0;
}

function impactshop_badge_tier_rank(string $tier): int
{
    switch (strtolower($tier)) {
        case 'silver':
            return 2;
        case 'gold':
            return 3;
        case 'platinum':
            return 4;
        case 'diamond':
            return 5;
        case 'legend':
            return 6;
        case 'bronze':
        default:
            return 1;
    }
}

function impactshop_badge_icon_for_key(string $badge_key, string $category = ''): string
{
    if (strpos($badge_key, 'views') === 0) {
        return '🎬';
    }
    if (strpos($badge_key, 'votes') === 0 || strpos($badge_key, 'vote') !== false) {
        return '🗳️';
    }
    if (strpos($badge_key, 'offers') === 0 || strpos($badge_key, 'offer') !== false) {
        return '🎁';
    }
    if (strpos($badge_key, 'edu') === 0 || strpos($badge_key, 'quiz') === 0) {
        return '📚';
    }
    if (strpos($badge_key, 'streak') === 0) {
        return '🔥';
    }
    if (strpos($badge_key, 'multi') === 0 || strpos($badge_key, 'ngo') !== false) {
        return '🏛️';
    }
    if (strpos($badge_key, 'referral') === 0) {
        return '🤝';
    }
    if (strpos($badge_key, 'seasonal') === 0) {
        return '❄️';
    }
    if (strpos($badge_key, 'anniversary') === 0) {
        return '🎉';
    }
    if (strpos($badge_key, 'early') === 0) {
        return '✨';
    }
    if ($category === 'kozosseg') {
        return '🤝';
    }
    return '🏆';
}

function impactshop_badge_compact_list(array $badges, array $defs): array
{
    $grouped = [];
    foreach ($badges as $badge) {
        $key = (string) ($badge['badge_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $group = impactshop_badge_group_key($key);
        $meta = $defs[$key] ?? [];
        if (!$meta) {
            continue;
        }
        $tier = (string) ($badge['tier'] ?? ($meta['default_tier'] ?? 'bronze'));
        $rank = impactshop_badge_tier_rank($tier);
        $existing = $grouped[$group] ?? null;
        if (!$existing || $rank > $existing['rank']) {
            $grouped[$group] = ['badge' => $badge, 'meta' => $meta, 'rank' => $rank];
            continue;
        }
        if ($existing && $rank === $existing['rank']) {
            $curr_num = impactshop_badge_suffix_number($key);
            $prev_key = (string) ($existing['badge']['badge_key'] ?? '');
            $prev_num = impactshop_badge_suffix_number($prev_key);
            if ($curr_num > $prev_num) {
                $grouped[$group] = ['badge' => $badge, 'meta' => $meta, 'rank' => $rank];
            }
        }
    }

    $items = array_values($grouped);
    usort($items, function ($a, $b) {
        $a_time = strtotime((string) ($a['badge']['awarded_at'] ?? '')) ?: 0;
        $b_time = strtotime((string) ($b['badge']['awarded_at'] ?? '')) ?: 0;
        return $b_time <=> $a_time;
    });

    $result = [];
    foreach ($items as $item) {
        $meta = $item['meta'] ?? [];
        $key = (string) ($item['badge']['badge_key'] ?? '');
        $label = (string) ($meta['name_hu'] ?? $key);
        $category = (string) ($meta['category'] ?? '');
        $result[] = [
            'icon' => impactshop_badge_icon_for_key($key, $category),
            'label' => $label,
            'description' => (string) ($meta['description_hu'] ?? $label),
        ];
    }
    return $result;
}

function impactshop_herowall_api(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_herowall';
    $points_table = $wpdb->prefix . 'user_points';
    $limit = min(50, absint($request->get_param('limit') ?? 10));

    $rows = $wpdb->get_results(
        "SELECT h.pseudo_id, h.nickname, h.legacy_message, COALESCE(p.points_total, 0) AS points_total
         FROM {$table} h
         LEFT JOIN {$points_table} p ON p.pseudo_id = h.pseudo_id
         ORDER BY points_total DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    $defs = impactshop_badge_definitions_map();
    foreach ($rows as &$row) {
        $badges = impact_get_user_badges((string) ($row['pseudo_id'] ?? ''));
        $row['badges'] = impactshop_badge_compact_list($badges, $defs);
    }
    unset($row);

    $pseudo_id = impactshop_badge_get_pseudo();
    $position = null;
    if ($pseudo_id !== '') {
        $user_points = $wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$points_table} WHERE pseudo_id = %s",
            $pseudo_id
        ));
        if ($user_points !== null) {
            $position = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) + 1 FROM {$points_table} WHERE points_total > %d",
                (int) $user_points
            ));
        }
    }

    return new WP_REST_Response([
        'entries' => $rows,
        'user_position' => $position,
    ], 200);
}

function impactshop_herowall_legacy(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_badge_get_pseudo();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'missing_identity'], 401);
    }
    $message = (string) ($request->get_json_params()['message'] ?? '');
    $points = impactshop_get_points_total_by_pseudo($pseudo_id);
    $level = impactshop_get_level_by_pseudo($pseudo_id);
    if (!in_array($level, ['platinum', 'legend'], true)) {
        return new WP_REST_Response(['message' => 'forbidden'], 403);
    }
    impact_set_legacy_message($pseudo_id, $message);
    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_badge_health(): WP_REST_Response
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_user_badges';
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    return new WP_REST_Response(['status' => 'ok', 'badges' => $count], 200);
}

function impactshop_badge_get_pseudo(): string
{
    if (function_exists('impactshop_identity_profile_cookie')) {
        return (string) impactshop_identity_profile_cookie();
    }
    return !empty($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field((string) $_COOKIE['impactshop_pseudo_id']) : '';
}

function impactshop_herowall_shortcode(): string
{
    $pseudo_id = impactshop_badge_get_pseudo();
    $html = '<div class="impactshop-herowall" data-role="herowall" data-pseudo="' . esc_attr($pseudo_id) . '">';
    $html .= '<div class="herowall-header">Legacy Pool</div>';
    $html .= '<div class="herowall-list" data-role="herowall-list">Betöltés...</div>';
    $html .= '</div>';
    $html .= '<style>' . impactshop_herowall_css() . '</style>';
    $html .= '<script>' . impactshop_herowall_js() . '</script>';
    return $html;
}

function impactshop_herowall_css(): string
{
    return '.impactshop-herowall{padding:24px;border-radius:24px;background:linear-gradient(135deg, rgba(255,255,255,.7), rgba(226,232,240,.55));border:1px solid rgba(148,163,184,.45);box-shadow:0 24px 50px rgba(15,23,42,.12);color:#0f172a;display:grid;gap:16px;backdrop-filter:blur(10px)}' .
        '.impactshop-herowall .herowall-header{font-size:20px;font-weight:800;color:#0f172a;letter-spacing:.02em}' .
        '.impactshop-herowall .herowall-entry{display:grid;gap:6px;padding:12px;border-radius:16px;background:rgba(255,255,255,.7);border:1px solid rgba(148,163,184,.25)}' .
        '.impactshop-herowall .herowall-entry-main{display:flex;justify-content:space-between;align-items:center;gap:12px}' .
        '.impactshop-herowall .herowall-name{font-weight:700}' .
        '.impactshop-herowall .herowall-points{font-weight:800}' .
        '.impactshop-herowall .herowall-legacy{font-size:12px;color:#475569}' .
        '.impactshop-herowall .herowall-badges{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}' .
        '.impactshop-herowall .herowall-badge{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:rgba(15,23,42,.06);border:1px solid rgba(148,163,184,.35);font-size:14px}' .
        '.impactshop-herowall .herowall-highlight{outline:2px solid rgba(59,130,246,.6);outline-offset:2px}';
}

function impactshop_herowall_js(): string
{
    $rest = esc_url_raw(rest_url('impact/v1/herowall'));
    return "(function(){var root=document.querySelector('[data-role=herowall]');if(!root)return;var current=(root.getAttribute('data-pseudo')||'').toLowerCase();fetch('{$rest}').then(function(r){return r.json();}).then(function(data){var list=root.querySelector('[data-role=herowall-list]');if(!list)return;var entries=data.entries||[];if(!entries.length){list.textContent='Még nincs Legacy Pool bejegyzés.';return;}list.innerHTML='';entries.forEach(function(row){var entry=document.createElement('div');entry.className='herowall-entry';var name=(row.nickname||row.pseudo_id||'Anonim');if(current && row.pseudo_id && row.pseudo_id.toLowerCase()===current){entry.className+=' herowall-highlight';}var main=document.createElement('div');main.className='herowall-entry-main';var left=document.createElement('span');left.className='herowall-name';left.textContent=name;var right=document.createElement('span');right.className='herowall-points';right.textContent=(row.points_total||0)+' pont';main.appendChild(left);main.appendChild(right);entry.appendChild(main);if(row.badges && row.badges.length){var badgeRow=document.createElement('div');badgeRow.className='herowall-badges';row.badges.forEach(function(b){var badge=document.createElement('span');badge.className='herowall-badge';badge.textContent=b.icon||'🏆';if(b.description){badge.title=b.description;}else if(b.label){badge.title=b.label;}badgeRow.appendChild(badge);});entry.appendChild(badgeRow);}if(row.legacy_message){var legacy=document.createElement('div');legacy.className='herowall-legacy';legacy.textContent=row.legacy_message;entry.appendChild(legacy);}list.appendChild(entry);});});})();";
}

function impactshop_challenges_shortcode(): string
{
    $defs = impactshop_badge_definitions_map();
    if (!$defs) {
        return '<div class="impactshop-challenges-empty">Még nincsenek kihívások.</div>';
    }
    $grouped = [];
    foreach ($defs as $key => $row) {
        $row['badge_key'] = (string) $key;
        $group = impactshop_badge_group_key((string) $key);
        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][] = $row;
    }

    $pseudo_id = impactshop_badge_get_pseudo();
    $earned = [];
    if ($pseudo_id !== '' && function_exists('impact_get_user_badges')) {
        $badges = impact_get_user_badges($pseudo_id);
        foreach ($badges as $badge) {
            $key = (string) ($badge['badge_key'] ?? '');
            if ($key === '' || !isset($defs[$key])) {
                continue;
            }
            $group = impactshop_badge_group_key($key);
            $tier = (string) ($badge['tier'] ?? ($defs[$key]['default_tier'] ?? 'bronze'));
            $rank = impactshop_badge_tier_rank($tier);
            $num = impactshop_badge_suffix_number($key);
            $current = $earned[$group] ?? null;
            if (!$current || $rank > $current['rank'] || ($rank === $current['rank'] && $num > $current['num'])) {
                $earned[$group] = ['rank' => $rank, 'num' => $num];
            }
        }
    }

    $next_rows = [];
    foreach ($grouped as $group => $items) {
        usort($items, function ($a, $b) {
            $rank_a = impactshop_badge_tier_rank((string) ($a['default_tier'] ?? 'bronze'));
            $rank_b = impactshop_badge_tier_rank((string) ($b['default_tier'] ?? 'bronze'));
            if ($rank_a === $rank_b) {
                $num_a = impactshop_badge_suffix_number((string) ($a['badge_key'] ?? ''));
                $num_b = impactshop_badge_suffix_number((string) ($b['badge_key'] ?? ''));
                return $num_a <=> $num_b;
            }
            return $rank_a <=> $rank_b;
        });

        $show_tier = count($items) > 1;
        $current = $earned[$group] ?? null;
        $selected = null;

        foreach ($items as $item) {
            $rank = impactshop_badge_tier_rank((string) ($item['default_tier'] ?? 'bronze'));
            $num = impactshop_badge_suffix_number((string) ($item['badge_key'] ?? ''));
            if (!$current) {
                $selected = $item;
                break;
            }
            if ($rank > $current['rank'] || ($rank === $current['rank'] && $num > $current['num'])) {
                $selected = $item;
                break;
            }
        }

        if ($selected) {
            $next_rows[] = [
                'row' => $selected,
                'show_tier' => $show_tier,
            ];
        }
    }

    usort($next_rows, function ($a, $b) {
        return ((int) ($a['row']['sort_order'] ?? 0)) <=> ((int) ($b['row']['sort_order'] ?? 0));
    });

    if (!$next_rows) {
        return '<div class="impactshop-challenges-empty">Nincs új kihívás elérhető.</div>';
    }

    $html = '<div class="impactshop-challenges">';
    $html .= '<h3>Kihívások</h3>';
    $html .= '<p class="impactshop-challenges-hint">Teljesíts mérföldköveket, szerezz jelvényeket és extra pontot.</p>';
    $html .= '<div class="impactshop-challenges-grid">';

    foreach ($next_rows as $entry) {
        $row = $entry['row'];
        $show_tier = (bool) $entry['show_tier'];
        $key = (string) ($row['badge_key'] ?? '');
        $name = (string) ($row['name_hu'] ?? $key);
        $desc = (string) ($row['description_hu'] ?? '');
        $tier = (string) ($row['default_tier'] ?? 'bronze');
        $points = impactshop_badge_points_for_tier($tier);
        $icon = impactshop_badge_icon_for_key($key, (string) ($row['category'] ?? ''));

        $html .= '<div class="impactshop-challenge-card">';
        $html .= '<div class="impactshop-challenge-icon" title="' . esc_attr($name) . '">' . esc_html($icon) . '</div>';
        $html .= '<div class="impactshop-challenge-body">';
        $html .= '<div class="impactshop-challenge-title">' . esc_html($name) . '</div>';
        if ($desc !== '') {
            $html .= '<div class="impactshop-challenge-desc">' . esc_html($desc) . '</div>';
        }
        $html .= '<div class="impactshop-challenge-meta">';
        if ($show_tier) {
            $html .= '<span class="impactshop-challenge-tier">' . esc_html(strtoupper($tier)) . '</span>';
        }
        $html .= '<span class="impactshop-challenge-points">+' . esc_html((string) $points) . ' pont</span>';
        $html .= '</div>';
        $html .= '</div></div>';
    }

    $html .= '</div></div>';
    $html .= '<style>
        .impactshop-challenges{margin-top:20px;padding:18px;border-radius:18px;background:rgba(248,250,252,.9);border:1px solid rgba(148,163,184,.25)}
        .impactshop-challenges h3{margin:0 0 6px;font-size:20px;color:#0f172a}
        .impactshop-challenges-hint{margin:0 0 14px;color:#64748b;font-size:13px}
        .impactshop-challenges-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}
        .impactshop-challenge-card{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border-radius:14px;background:#fff;border:1px solid rgba(148,163,184,.2)}
        .impactshop-challenge-icon{font-size:24px;width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(226,232,240,.7)}
        .impactshop-challenge-title{font-weight:700;color:#0f172a;margin-bottom:4px}
        .impactshop-challenge-desc{font-size:12px;color:#475569;line-height:1.4}
        .impactshop-challenge-meta{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;font-size:11px;color:#0f172a}
        .impactshop-challenge-tier{padding:2px 8px;border-radius:999px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);font-weight:700;letter-spacing:.02em}
        .impactshop-challenge-points{padding:2px 8px;border-radius:999px;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);font-weight:700}
    </style>';

    return $html;
}

function impactshop_get_points_total_by_pseudo(string $pseudo_id): int
{
    if ($pseudo_id === '') {
        return 0;
    }
    global $wpdb;
    $points_table = $wpdb->prefix . 'user_points';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT points_total FROM {$points_table} WHERE pseudo_id = %s",
        $pseudo_id
    ));
}

function impactshop_get_level_by_pseudo(string $pseudo_id): string
{
    if ($pseudo_id === '') {
        return 'basic';
    }
    global $wpdb;
    $points_table = $wpdb->prefix . 'user_points';
    $level = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT current_level FROM {$points_table} WHERE pseudo_id = %s",
        $pseudo_id
    ));
    return $level !== '' ? $level : 'basic';
}

function impactshop_badge_on_ads_view(string $pseudo_id, array $view_data): void
{
    if ($pseudo_id === '') {
        return;
    }
    $stats = [];
    if (is_array($view_data)) {
        $stats = $view_data['stats'] ?? [];
    }
    if (!$stats && function_exists('impactshop_ads_watch_get_user_stats')) {
        $stats = impactshop_ads_watch_get_user_stats($pseudo_id);
    }
    $views = (int) ($stats['total_views'] ?? 0);
    if ($views >= 1) {
        impact_award_badge($pseudo_id, 'views_1', 'bronze', 'ads_watch');
    }
    if ($views >= 10) {
        impact_award_badge($pseudo_id, 'views_10', 'silver', 'ads_watch');
    }
    if ($views >= 100) {
        impact_award_badge($pseudo_id, 'views_100', 'gold', 'ads_watch');
    }
    if ($views >= 1000) {
        impact_award_badge($pseudo_id, 'views_1000', 'platinum', 'ads_watch');
    }
    if ($views >= 5000) {
        impact_award_badge($pseudo_id, 'views_5000', 'diamond', 'ads_watch');
    }
    if ($views >= 10000) {
        impact_award_badge($pseudo_id, 'views_10000', 'legend', 'ads_watch');
    }
    $streak = (int) ($stats['streak_days'] ?? 0);
    if ($streak >= 3) {
        impact_award_badge($pseudo_id, 'streak_3', 'bronze', 'ads_watch');
    }
    if ($streak >= 7) {
        impact_award_badge($pseudo_id, 'streak_7', 'silver', 'ads_watch');
    }
    if ($streak >= 30) {
        impact_award_badge($pseudo_id, 'streak_30', 'gold', 'ads_watch');
    }
    if ($streak >= 100) {
        impact_award_badge($pseudo_id, 'streak_100', 'platinum', 'ads_watch');
    }
    if ($streak >= 365) {
        impact_award_badge($pseudo_id, 'streak_365', 'diamond', 'ads_watch');
    }

    $votes = (int) ($stats['total_votes'] ?? 0);
    if ($votes > 0) {
        impact_award_badge($pseudo_id, 'first_vote', 'bronze', 'ads_watch');
    }
    if ($votes >= 10) {
        impact_award_badge($pseudo_id, 'votes_10', 'silver', 'ads_watch');
    }
    if ($votes >= 100) {
        impact_award_badge($pseudo_id, 'votes_100', 'gold', 'ads_watch');
    }
    if ($votes >= 1000) {
        impact_award_badge($pseudo_id, 'votes_1000', 'platinum', 'ads_watch');
    }
    if ($votes >= 5000) {
        impact_award_badge($pseudo_id, 'votes_5000', 'diamond', 'ads_watch');
    }
    if ($votes >= 10000) {
        impact_award_badge($pseudo_id, 'votes_10000', 'legend', 'ads_watch');
    }

    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
    $distinct_ngos = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT ngo_slug) FROM {$table_votes} WHERE pseudo_id = %s",
        $pseudo_id
    ));
    if ($distinct_ngos >= 1) {
        impact_award_badge($pseudo_id, 'ngo_1', 'bronze', 'ads_watch');
    }
    if ($distinct_ngos >= 10) {
        impact_award_badge($pseudo_id, 'ngo_10', 'silver', 'ads_watch');
    }
    if ($distinct_ngos >= 100) {
        impact_award_badge($pseudo_id, 'ngo_100', 'gold', 'ads_watch');
    }
}

function impactshop_badge_on_offerwall(string $pseudo_id, array $payload): void
{
    if ($pseudo_id === '') {
        return;
    }
    impact_award_badge($pseudo_id, 'first_offer', 'bronze', 'offerwall');

    global $wpdb;
    $table_offers = $wpdb->prefix . 'impactshop_offerwall_completions';
    $offer_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_offers} WHERE pseudo_id = %s",
            $pseudo_id
        )
    );
    if ($offer_count >= 10) {
        impact_award_badge($pseudo_id, 'offers_10', 'silver', 'offerwall');
    }
    if ($offer_count >= 100) {
        impact_award_badge($pseudo_id, 'offers_100', 'gold', 'offerwall');
    }
    if ($offer_count >= 500) {
        impact_award_badge($pseudo_id, 'offers_500', 'platinum', 'offerwall');
    }
    if ($offer_count >= 1000) {
        impact_award_badge($pseudo_id, 'offers_1000', 'diamond', 'offerwall');
    }
}

function impactshop_badge_on_edu_video(string $pseudo_id, array $payload): void
{
    if ($pseudo_id === '') {
        return;
    }
    impact_award_badge($pseudo_id, 'first_edu_video', 'bronze', 'education');

    global $wpdb;
    $table_edu = $wpdb->prefix . 'impactshop_education_views';
    $edu_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_edu} WHERE pseudo_id = %s",
            $pseudo_id
        )
    );
    if ($edu_count >= 5) {
        impact_award_badge($pseudo_id, 'edu_complete_5', 'silver', 'education');
    }
    if ($edu_count >= 20) {
        impact_award_badge($pseudo_id, 'edu_complete_20', 'gold', 'education');
    }
    if ($edu_count >= 50) {
        impact_award_badge($pseudo_id, 'edu_complete_50', 'platinum', 'education');
    }
    if ($edu_count >= 100) {
        impact_award_badge($pseudo_id, 'edu_complete_100', 'diamond', 'education');
    }
}

function impactshop_badge_on_points_earned($user_id, int $points, string $type, array $metadata): void
{
    if ($type === 'purchase') {
        $pseudo_id = (string) ($metadata['pseudo_id'] ?? '');
        if ($pseudo_id !== '') {
            impact_award_badge($pseudo_id, 'first_vote', 'bronze', 'points');
        }
    }
}

function impactshop_badge_definition_keys(): array
{
    static $keys = null;
    if (is_array($keys)) {
        return $keys;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impact_badge_definitions';
    $rows = $wpdb->get_col("SELECT badge_key FROM {$table}");
    $keys = [];
    foreach ($rows as $row) {
        $keys[(string) $row] = true;
    }
    return $keys;
}

function impact_migrate_achievements_to_badges(int $limit = 200, int $offset = 0): array
{
    global $wpdb;

    $defs = impactshop_badge_definition_keys();
    $table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';
    $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
    $table_offers = $wpdb->prefix . 'impactshop_offerwall_completions';
    $table_edu = $wpdb->prefix . 'impactshop_education_views';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pseudo_id, total_views, total_votes, streak_days
             FROM {$table_stats}
             ORDER BY updated_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ),
        ARRAY_A
    );

    $updated = 0;
    foreach ($rows as $row) {
        $pseudo_id = (string) ($row['pseudo_id'] ?? '');
        if ($pseudo_id === '') {
            continue;
        }

        $views = (int) ($row['total_views'] ?? 0);
        if ($views >= 1 && isset($defs['views_1'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'views_1', 'bronze', 'migration');
        }
        if ($views >= 10 && isset($defs['views_10'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'views_10', 'silver', 'migration');
        }
        if ($views >= 100 && isset($defs['views_100'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'views_100', 'gold', 'migration');
        }
        if ($views >= 1000 && isset($defs['views_1000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'views_1000', 'platinum', 'migration');
        }
        if ($views >= 5000 && isset($defs['views_5000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'views_5000', 'diamond', 'migration');
        }
        if ($views >= 10000 && isset($defs['views_10000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'views_10000', 'legend', 'migration');
        }

        $streak = (int) ($row['streak_days'] ?? 0);
        if ($streak >= 3 && isset($defs['streak_3'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'streak_3', 'bronze', 'migration');
        }
        if ($streak >= 7 && isset($defs['streak_7'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'streak_7', 'silver', 'migration');
        }
        if ($streak >= 30 && isset($defs['streak_30'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'streak_30', 'gold', 'migration');
        }
        if ($streak >= 100 && isset($defs['streak_100'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'streak_100', 'platinum', 'migration');
        }
        if ($streak >= 365 && isset($defs['streak_365'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'streak_365', 'diamond', 'migration');
        }

        $votes = (int) ($row['total_votes'] ?? 0);
        if ($votes > 0 && isset($defs['first_vote'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'first_vote', 'bronze', 'migration');
        }
        if ($votes >= 10 && isset($defs['votes_10'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'votes_10', 'silver', 'migration');
        }
        if ($votes >= 100 && isset($defs['votes_100'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'votes_100', 'gold', 'migration');
        }
        if ($votes >= 1000 && isset($defs['votes_1000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'votes_1000', 'platinum', 'migration');
        }
        if ($votes >= 5000 && isset($defs['votes_5000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'votes_5000', 'diamond', 'migration');
        }
        if ($votes >= 10000 && isset($defs['votes_10000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'votes_10000', 'legend', 'migration');
        }

        $offer_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_offers} WHERE pseudo_id = %s",
                $pseudo_id
            )
        );
        if ($offer_count > 0 && isset($defs['first_offer'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'first_offer', 'bronze', 'migration');
        }
        if ($offer_count >= 10 && isset($defs['offers_10'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'offers_10', 'silver', 'migration');
        }
        if ($offer_count >= 100 && isset($defs['offers_100'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'offers_100', 'gold', 'migration');
        }
        if ($offer_count >= 500 && isset($defs['offers_500'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'offers_500', 'platinum', 'migration');
        }
        if ($offer_count >= 1000 && isset($defs['offers_1000'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'offers_1000', 'diamond', 'migration');
        }

        $edu_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_edu} WHERE pseudo_id = %s",
                $pseudo_id
            )
        );
        if ($edu_count > 0 && isset($defs['first_edu_video'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'first_edu_video', 'bronze', 'migration');
        }
        if ($edu_count >= 5 && isset($defs['edu_complete_5'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'edu_complete_5', 'silver', 'migration');
        }
        if ($edu_count >= 20 && isset($defs['edu_complete_20'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'edu_complete_20', 'gold', 'migration');
        }
        if ($edu_count >= 50 && isset($defs['edu_complete_50'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'edu_complete_50', 'platinum', 'migration');
        }
        if ($edu_count >= 100 && isset($defs['edu_complete_100'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'edu_complete_100', 'diamond', 'migration');
        }

        $distinct_ngos = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT ngo_slug) FROM {$table_votes} WHERE pseudo_id = %s",
                $pseudo_id
            )
        );
        if ($distinct_ngos >= 1 && isset($defs['ngo_1'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'ngo_1', 'bronze', 'migration');
        }
        if ($distinct_ngos >= 10 && isset($defs['ngo_10'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'ngo_10', 'silver', 'migration');
        }
        if ($distinct_ngos >= 100 && isset($defs['ngo_100'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'ngo_100', 'gold', 'migration');
        }

        $loyal_days = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(day_count) FROM (
                    SELECT COUNT(DISTINCT day_key) AS day_count
                    FROM {$table_votes}
                    WHERE pseudo_id = %s
                    GROUP BY ngo_slug
                ) AS days",
                $pseudo_id
            )
        );
        if ($loyal_days >= 30 && isset($defs['ngo_loyal'])) {
            $updated += (int) impact_award_badge($pseudo_id, 'ngo_loyal', 'silver', 'migration');
        }
    }

    return [
        'processed' => count($rows),
        'updated' => $updated,
        'limit' => $limit,
        'offset' => $offset,
    ];
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('impactshop badges migrate-achievements', function($args, $assoc_args) {
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 200;
        $offset = isset($assoc_args['offset']) ? (int) $assoc_args['offset'] : 0;
        $result = impact_migrate_achievements_to_badges($limit, $offset);
        WP_CLI::success(sprintf(
            'Badges migrated. processed=%d updated=%d limit=%d offset=%d',
            $result['processed'],
            $result['updated'],
            $result['limit'],
            $result['offset']
        ));
    });
}
