<?php
/**
 * ImpactShop Ads Watch - Google/YouTube Ad Campaign with NGO Support
 *
 * - 1 ad view = 1 point + 1 vote (badge weight applied)
 * - Sponsor ad view = 5 points + 5 votes (badge weight applied)
 * - Uses pseudo_id (cookie) instead of login
 *
 * @package ImpactShop
 * @since   2.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────

define('IMPACTSHOP_ADS_WATCH_VERSION', '2.5.23');
define('IMPACTSHOP_ADS_WATCH_SCHEMA_VERSION', '8');
define('IMPACTSHOP_ADS_DONATION_POOL', 500000); // Ft

define('IMPACTSHOP_ADS_POINTS_REGULAR', 1);
define('IMPACTSHOP_ADS_POINTS_SPONSOR', 5);
define('IMPACTSHOP_ADS_POINTS_BANNER', 1);

define('IMPACTSHOP_ADS_VOTES_REGULAR', 1);
define('IMPACTSHOP_ADS_VOTES_SPONSOR', 5);
define('IMPACTSHOP_ADS_VOTES_BANNER', 1);

define('IMPACTSHOP_ADS_TALLY_CACHE_TTL', 60); // seconds

define('IMPACTSHOP_ADS_RATE_LIMIT_VIEW_PER_MIN', 60);
define('IMPACTSHOP_ADS_RATE_LIMIT_ALLOCATE_PER_MIN', 10);
define('IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN', 120);
define('IMPACTSHOP_ADS_RATE_LIMIT_EDU_PER_MIN', 10);

define('IMPACTSHOP_ADS_TALLY_DIRTY_TTL', 300);

define('IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS', 30);
define('IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL', 5);
define('IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL', 5);
define('IMPACTSHOP_ADS_EDU_SESSION_TTL', 1800);

define('IMPACTSHOP_ADS_ROTATE_WEIGHT_AD', 60);
define('IMPACTSHOP_ADS_ROTATE_WEIGHT_BANNER', 20);
define('IMPACTSHOP_ADS_ROTATE_WEIGHT_SPONSOR', 15);
define('IMPACTSHOP_ADS_ROTATE_WEIGHT_EDU', 5);
define('IMPACTSHOP_ADS_ROTATE_MAX_AD_STREAK', 3);

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function impactshop_ads_watch_get_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $value) {
        if (!$value) {
            continue;
        }
        $parts = array_map('trim', explode(',', $value));
        if (!empty($parts[0])) {
            return sanitize_text_field($parts[0]);
        }
    }
    return '0.0.0.0';
}

function impactshop_ads_watch_require_nonce(WP_REST_Request $request): bool
{
    $nonce = (string) $request->get_header('x-wp-nonce');
    if ($nonce === '') {
        return false;
    }
    return wp_verify_nonce($nonce, 'wp_rest') !== false;
}

function impactshop_ads_watch_rate_limit_check(string $key, int $limit, int $window, bool $increment = true): array
{
    $bucket = get_transient($key);
    if (!is_array($bucket)) {
        $bucket = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    if (time() > (int) $bucket['reset']) {
        $bucket = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    $allowed = ((int) $bucket['count'] < $limit);
    if ($allowed && $increment) {
        $bucket['count'] = (int) $bucket['count'] + 1;
        set_transient($key, $bucket, $window);
    }

    return [
        'allowed' => $allowed,
        'limit' => $limit,
        'remaining' => max(0, $limit - (int) $bucket['count']),
        'reset' => (int) $bucket['reset'],
    ];
}

function impactshop_ads_watch_rate_limit_headers(array $rate): array
{
    return [
        'X-RateLimit-Limit' => (string) ($rate['limit'] ?? 0),
        'X-RateLimit-Remaining' => (string) ($rate['remaining'] ?? 0),
        'X-RateLimit-Reset' => (string) ($rate['reset'] ?? 0),
    ];
}

function impactshop_ads_watch_log(string $level, string $message, array $context = []): void
{
    $payload = [
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'timestamp' => current_time('mysql'),
    ];
    error_log('[impactshop_ads_watch] ' . wp_json_encode($payload));
}

function impactshop_ads_watch_track_ga4(string $event_name, array $params = []): void
{
    $measurement_id = (string) get_option('impactshop_ga4_measurement_id', '');
    $api_secret = (string) get_option('impactshop_ga4_api_secret', '');

    if ($measurement_id === '' || $api_secret === '') {
        return;
    }

    $client_id = $_COOKIE['_ga'] ?? '';
    if ($client_id) {
        $parts = explode('.', (string) $client_id);
        if (count($parts) >= 4) {
            $client_id = $parts[2] . '.' . $parts[3];
        }
    }
    if ($client_id === '') {
        $client_id = 'server.' . wp_generate_uuid4();
    }

    $payload = [
        'client_id' => $client_id,
        'events' => [[
            'name' => $event_name,
            'params' => $params,
        ]],
    ];

    wp_remote_post(
        "https://www.google-analytics.com/mp/collect?measurement_id={$measurement_id}&api_secret={$api_secret}",
        [
            'body' => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 2,
            'blocking' => false,
        ]
    );
}

function impactshop_ads_watch_mark_tally_dirty(): void
{
    set_transient('impactshop_ads_tally_dirty', time(), IMPACTSHOP_ADS_TALLY_DIRTY_TTL);
}

function impactshop_ads_watch_clear_tally_cache(): void
{
    delete_transient('impactshop_ads_tally_cache');
    $quarter_key = impactshop_ads_get_active_quarter();
    if ($quarter_key !== '') {
        delete_transient('impactshop_ads_tally_cache_' . $quarter_key);
    }
    delete_transient('impactshop_ads_tally_dirty');
    delete_transient('impactshop_ads_tally_lock');
}

function impactshop_ads_watch_build_cta(string $label, string $url, int $points): array
{
    return [
        'label' => $label,
        'url' => $url,
        'points' => $points,
    ];
}

function impactshop_ads_watch_build_cta_dedupe(string $pseudo_id, string $content_id): string
{
    $date_key = gmdate('Y-m-d');
    return sprintf('cta_click:%s:%s:%s', $content_id, $pseudo_id, $date_key);
}

function impactshop_ads_watch_get_default_cta_label(): string
{
    $label = (string) apply_filters('impactshop_ads_watch_default_cta_label', 'Megnezem');
    return $label !== '' ? $label : 'Megnezem';
}

function impactshop_ads_watch_get_default_cta_url(): string
{
    $url = (string) apply_filters('impactshop_ads_watch_default_cta_url', site_url('/impactshop/'));
    return $url !== '' ? $url : site_url('/impactshop/');
}

function impactshop_ads_watch_get_seen_content(string $pseudo_id): array
{
    if ($pseudo_id === '') {
        return [];
    }

    $key = 'impactshop_ads_seen_' . md5($pseudo_id);
    $seen = get_transient($key);
    if (!is_array($seen)) {
        return [];
    }

    $cutoff = time() - (4 * HOUR_IN_SECONDS);
    $filtered = [];
    foreach ($seen as $content_id => $timestamp) {
        if ((int) $timestamp >= $cutoff) {
            $filtered[$content_id] = (int) $timestamp;
        }
    }

    if (count($filtered) !== count($seen)) {
        set_transient($key, $filtered, 4 * HOUR_IN_SECONDS);
    }

    return $filtered;
}

function impactshop_ads_watch_mark_seen_content(string $pseudo_id, string $content_id): void
{
    if ($pseudo_id === '' || $content_id === '') {
        return;
    }

    $key = 'impactshop_ads_seen_' . md5($pseudo_id);
    $seen = impactshop_ads_watch_get_seen_content($pseudo_id);
    $seen[$content_id] = time();
    set_transient($key, $seen, 4 * HOUR_IN_SECONDS);
}

function impactshop_ads_watch_get_rotation_state(string $pseudo_id): array
{
    if ($pseudo_id === '') {
        return ['ad_streak' => 0, 'last_type' => ''];
    }

    $key = 'impactshop_ads_rotation_' . md5($pseudo_id);
    $state = get_transient($key);
    if (!is_array($state)) {
        return ['ad_streak' => 0, 'last_type' => ''];
    }

    return [
        'ad_streak' => (int) ($state['ad_streak'] ?? 0),
        'last_type' => (string) ($state['last_type'] ?? ''),
    ];
}

function impactshop_ads_watch_set_rotation_state(string $pseudo_id, string $type): void
{
    if ($pseudo_id === '') {
        return;
    }

    $state = impactshop_ads_watch_get_rotation_state($pseudo_id);
    $ad_streak = (int) ($state['ad_streak'] ?? 0);
    $last_type = (string) ($state['last_type'] ?? '');

    if ($type === 'regular' || $type === 'ad') {
        $ad_streak = ($last_type === 'regular' || $last_type === 'ad') ? $ad_streak + 1 : 1;
    } else {
        $ad_streak = 0;
    }

    $key = 'impactshop_ads_rotation_' . md5($pseudo_id);
    set_transient($key, [
        'ad_streak' => $ad_streak,
        'last_type' => $type,
        'updated_at' => time(),
    ], HOUR_IN_SECONDS);
}

function impactshop_ads_watch_pick_weighted(array $weights): string
{
    $total = 0;
    foreach ($weights as $value) {
        $total += max(0, (int) $value);
    }
    if ($total <= 0) {
        return 'regular';
    }
    $pick = wp_rand(1, $total);
    $current = 0;
    foreach ($weights as $key => $value) {
        $current += max(0, (int) $value);
        if ($pick <= $current) {
            return (string) $key;
        }
    }
    return 'regular';
}

function impactshop_ads_watch_pick_education_content(array $videos): ?array
{
    if (empty($videos)) {
        return null;
    }
    $videos = array_values($videos);
    return $videos[array_rand($videos)];
}

function impactshop_ads_watch_get_education_videos(): array
{
    $normalized = [];
    $posts = get_posts([
        'post_type' => 'impact_edu_video',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    foreach ($posts as $post) {
        $item = impactshop_ads_watch_normalize_education_from_cpt($post);
        if (!empty($item)) {
            $normalized[] = $item;
        }
    }

    $legacy = apply_filters('impactshop_ads_watch_education_videos', []);
    if (!is_array($legacy)) {
        $legacy = [];
    }
    $existing_ids = array_column($normalized, 'id');
    foreach ($legacy as $item) {
        $mapped = impactshop_ads_watch_normalize_education_from_filter($item);
        if (!$mapped) {
            continue;
        }
        if (in_array($mapped['id'], $existing_ids, true)) {
            continue;
        }
        $normalized[] = $mapped;
        $existing_ids[] = $mapped['id'];
    }

    return $normalized;
}

function impactshop_ads_watch_normalize_education_from_cpt(WP_Post $post): array
{
    $settings = impactshop_ads_watch_get_education_settings($post->ID);
    $media_type = $settings['media_type'] ?? 'youtube';
    $youtube_id = impactshop_ads_watch_extract_youtube_id($settings['youtube_url'] ?? '');
    $video_url = esc_url_raw((string) ($settings['video_url'] ?? ''));
    $interval_seconds = max(5, (int) ($settings['interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS));
    $duration = max(0, (int) ($settings['duration'] ?? 0));
    $max_intervals = $duration > 0 ? (int) floor($duration / $interval_seconds) : 0;

    if ($media_type === 'youtube' && $youtube_id === '') {
        return [];
    }
    if ($media_type === 'mp4' && $video_url === '') {
        return [];
    }

    return [
        'id' => 'edu_' . $post->ID,
        'title' => sanitize_text_field((string) $post->post_title),
        'description' => sanitize_text_field((string) $post->post_content),
        'media_type' => $media_type,
        'youtube_id' => $youtube_id,
        'video_url' => $video_url,
        'duration_seconds' => $duration,
        'interval_seconds' => $interval_seconds,
        'points_per_interval' => max(0, (int) ($settings['points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL)),
        'votes_per_interval' => max(0, (int) ($settings['votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL)),
        'bonus_points' => max(0, (int) ($settings['bonus_points'] ?? 10)),
        'bonus_votes' => max(0, (int) ($settings['bonus_votes'] ?? 10)),
        'presence_interval' => max(0, (int) ($settings['presence_interval'] ?? 60)),
        'presence_timeout' => max(0, (int) ($settings['presence_timeout'] ?? 30)),
        'skip_enabled' => !empty($settings['skip_enabled']),
        'user_limit' => max(0, (int) ($settings['user_limit'] ?? 0)),
        'cooldown_seconds' => max(0, (int) ($settings['cooldown'] ?? 0)),
        'start_at' => (string) ($settings['start_at_local'] ?? ''),
        'end_at' => (string) ($settings['end_at_local'] ?? ''),
        'max_intervals' => $max_intervals,
        'cta_label' => sanitize_text_field((string) ($settings['cta_label'] ?? 'Tudj meg tobbet')),
        'cta_url' => esc_url_raw((string) ($settings['cta_url'] ?? '')),
    ];
}

function impactshop_ads_watch_normalize_education_from_filter($item): array
{
    if (!is_array($item)) {
        return [];
    }
    $content_id = sanitize_text_field((string) ($item['id'] ?? $item['content_id'] ?? ''));
    if ($content_id === '') {
        return [];
    }
    $interval_seconds = max(5, (int) ($item['interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS));
    $duration = max(0, (int) ($item['duration_seconds'] ?? 0));
    $max_intervals = $duration > 0 ? (int) floor($duration / $interval_seconds) : 0;
    $max_intervals = max(0, (int) ($item['max_intervals'] ?? $max_intervals));

    if (($item['media_type'] ?? 'youtube') === 'youtube' && ($item['youtube_id'] ?? '') === '') {
        return [];
    }
    if (($item['media_type'] ?? 'mp4') === 'mp4' && ($item['video_url'] ?? '') === '') {
        return [];
    }

    return [
        'id' => $content_id,
        'title' => sanitize_text_field((string) ($item['title'] ?? '')),
        'description' => sanitize_text_field((string) ($item['description'] ?? '')),
        'media_type' => sanitize_text_field((string) ($item['media_type'] ?? 'youtube')),
        'youtube_id' => sanitize_text_field((string) ($item['youtube_id'] ?? '')),
        'video_url' => esc_url_raw((string) ($item['video_url'] ?? '')),
        'duration_seconds' => $duration,
        'interval_seconds' => $interval_seconds,
        'points_per_interval' => max(0, (int) ($item['points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL)),
        'votes_per_interval' => max(0, (int) ($item['votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL)),
        'bonus_points' => max(0, (int) ($item['bonus_points'] ?? 10)),
        'bonus_votes' => max(0, (int) ($item['bonus_votes'] ?? 10)),
        'presence_interval' => max(0, (int) ($item['presence_interval'] ?? 60)),
        'presence_timeout' => max(0, (int) ($item['presence_timeout'] ?? 30)),
        'skip_enabled' => !empty($item['skip_enabled']),
        'user_limit' => max(0, (int) ($item['user_limit'] ?? 0)),
        'cooldown_seconds' => max(0, (int) ($item['cooldown_seconds'] ?? 0)),
        'start_at' => sanitize_text_field((string) ($item['start_at'] ?? '')),
        'end_at' => sanitize_text_field((string) ($item['end_at'] ?? '')),
        'max_intervals' => $max_intervals,
        'cta_label' => sanitize_text_field((string) ($item['cta_label'] ?? 'Tudj meg tobbet')),
        'cta_url' => esc_url_raw((string) ($item['cta_url'] ?? '')),
    ];
}

function impactshop_ads_watch_is_education_active(array $content): bool
{
    $start_at = trim((string) ($content['start_at'] ?? ''));
    $end_at = trim((string) ($content['end_at'] ?? ''));
    $now = time();

    if ($start_at !== '') {
        $start_ts = strtotime($start_at);
        if ($start_ts && $now < $start_ts) {
            return false;
        }
    }
    if ($end_at !== '') {
        $end_ts = strtotime($end_at);
        if ($end_ts && $now > $end_ts) {
            return false;
        }
    }
    return true;
}

function impactshop_ads_watch_can_view_education(string $pseudo_id, array $content): bool
{
    if ($pseudo_id === '') {
        return false;
    }
    $user_limit = max(0, (int) ($content['user_limit'] ?? 0));
    $cooldown_seconds = max(0, (int) ($content['cooldown_seconds'] ?? 0));
    if ($user_limit <= 0 && $cooldown_seconds <= 0) {
        return true;
    }

    global $wpdb;
    $table_education = $wpdb->prefix . 'impactshop_education_views';
    $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';
    $table_quarter_results = $wpdb->prefix . 'impactshop_ads_quarter_results';
    $content_id = (string) ($content['id'] ?? '');
    if ($content_id === '') {
        return false;
    }

    if ($user_limit > 0) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_education} WHERE pseudo_id = %s AND content_id = %s",
            $pseudo_id,
            $content_id
        ));
        if ($count >= $user_limit) {
            return false;
        }
    }

    if ($cooldown_seconds > 0) {
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$table_education} WHERE pseudo_id = %s AND content_id = %s ORDER BY created_at DESC LIMIT 1",
            $pseudo_id,
            $content_id
        ));
        if ($last) {
            $last_ts = strtotime((string) $last);
            if ($last_ts && (time() - $last_ts) < $cooldown_seconds) {
                return false;
            }
        }
    }

    return true;
}

function impactshop_ads_watch_filter_education_for_user(string $pseudo_id, array $videos): array
{
    if (empty($videos)) {
        return [];
    }
    $filtered = [];
    foreach ($videos as $video) {
        if (!is_array($video)) {
            continue;
        }
        if (!impactshop_ads_watch_is_education_active($video)) {
            continue;
        }
        if (!impactshop_ads_watch_can_view_education($pseudo_id, $video)) {
            continue;
        }
        $filtered[] = $video;
    }
    return $filtered;
}

function impactshop_ads_watch_create_education_session(string $pseudo_id, array $content): array
{
    $token = wp_generate_uuid4();
    $interval_seconds = max(1, (int) ($content['interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS));
    $duration = max(0, (int) ($content['duration_seconds'] ?? 0));
    $max_intervals = (int) ($content['max_intervals'] ?? 0);
    if ($max_intervals <= 0 && $duration > 0) {
        $max_intervals = (int) floor($duration / $interval_seconds);
    }
    $created_at = time();
    $ttl = max(1800, $duration + 1800);
    $payload = [
        'pseudo_id' => $pseudo_id,
        'content_id' => (string) ($content['id'] ?? ''),
        'max_intervals' => $max_intervals,
        'intervals_awarded' => 0,
        'interval_seconds' => $interval_seconds,
        'points_per_interval' => max(0, (int) ($content['points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL)),
        'votes_per_interval' => max(0, (int) ($content['votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL)),
        'bonus_points' => max(0, (int) ($content['bonus_points'] ?? 0)),
        'bonus_votes' => max(0, (int) ($content['bonus_votes'] ?? 0)),
        'presence_interval' => max(0, (int) ($content['presence_interval'] ?? 0)),
        'presence_timeout' => max(0, (int) ($content['presence_timeout'] ?? 0)),
        'skip_enabled' => !empty($content['skip_enabled']),
        'duration_seconds' => $duration,
        'bonus_awarded' => false,
        'created_at' => $created_at,
        'session_hash' => wp_hash($pseudo_id . '|' . (string) ($content['id'] ?? '') . '|' . $created_at),
        'ttl' => $ttl,
    ];

    set_transient('impactshop_ads_edu_session_' . $token, $payload, $ttl);

    return [
        'token' => $token,
        'max_intervals' => $max_intervals,
        'interval_seconds' => $interval_seconds,
        'points_per_interval' => $payload['points_per_interval'],
        'votes_per_interval' => $payload['votes_per_interval'],
        'bonus_points' => $payload['bonus_points'],
        'bonus_votes' => $payload['bonus_votes'],
        'presence_interval' => $payload['presence_interval'],
        'presence_timeout' => $payload['presence_timeout'],
        'skip_enabled' => $payload['skip_enabled'],
        'duration_seconds' => $duration,
    ];
}

function impactshop_ads_watch_get_education_session(string $token): array
{
    if ($token === '') {
        return [];
    }
    $payload = get_transient('impactshop_ads_edu_session_' . $token);
    return is_array($payload) ? $payload : [];
}

function impactshop_ads_watch_update_education_session(string $token, array $payload): void
{
    if ($token === '') {
        return;
    }
    $ttl = (int) ($payload['ttl'] ?? IMPACTSHOP_ADS_EDU_SESSION_TTL);
    set_transient('impactshop_ads_edu_session_' . $token, $payload, $ttl);
}

// ─────────────────────────────────────────────────────────────────────────────
// DB SETUP (MU plugin: init hook)
// ─────────────────────────────────────────────────────────────────────────────

add_action('init', 'impactshop_ads_watch_ensure_schema', 5);

function impactshop_ads_watch_ensure_schema(): void
{
    $version = (string) get_option('impactshop_ads_watch_schema_version', '');
    if ($version === IMPACTSHOP_ADS_WATCH_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $table_views = $wpdb->prefix . 'impactshop_ads_views';
    $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
    $table_prefs = $wpdb->prefix . 'impactshop_ads_user_ngo';
    $table_user_votes = $wpdb->prefix . 'impactshop_ads_user_votes';
    $table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';
    $table_education = $wpdb->prefix . 'impactshop_education_views';

    $sql_views = "CREATE TABLE IF NOT EXISTS {$table_views} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pseudo_id VARCHAR(32) NOT NULL,
        ngo_slug VARCHAR(190) NULL,
        sponsor_id BIGINT UNSIGNED NULL,
        ad_type ENUM('regular','sponsor') NOT NULL DEFAULT 'regular',
        points INT UNSIGNED NOT NULL DEFAULT 0,
        vote_weight INT UNSIGNED NOT NULL DEFAULT 0,
        dedupe_key VARCHAR(191) NOT NULL,
        day_key CHAR(10) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_dedupe (dedupe_key),
        KEY idx_pseudo (pseudo_id),
        KEY idx_pseudo_day (pseudo_id, day_key),
        KEY idx_pseudo_created (pseudo_id, created_at),
        KEY idx_ngo (ngo_slug),
        KEY idx_sponsor (sponsor_id),
        KEY idx_day (day_key),
        KEY idx_created (created_at)
    ) {$charset};";

    $sql_votes = "CREATE TABLE IF NOT EXISTS {$table_votes} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pseudo_id VARCHAR(32) NOT NULL,
        ngo_slug VARCHAR(190) NOT NULL,
        vote_weight INT UNSIGNED NOT NULL DEFAULT 0,
        base_weight INT UNSIGNED NOT NULL DEFAULT 0,
        donation_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
        ad_type ENUM('regular','sponsor') NOT NULL DEFAULT 'regular',
        quarter_key VARCHAR(10) DEFAULT NULL,
        day_key CHAR(10) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pseudo (pseudo_id),
        KEY idx_pseudo_day (pseudo_id, day_key),
        KEY idx_ngo (ngo_slug),
        KEY idx_ngo_votes (ngo_slug, vote_weight),
        KEY idx_quarter_ngo (quarter_key, ngo_slug, vote_weight),
        KEY idx_day (day_key),
        KEY idx_created (created_at)
    ) {$charset};";

    $sql_prefs = "CREATE TABLE IF NOT EXISTS {$table_prefs} (
        pseudo_id VARCHAR(32) PRIMARY KEY,
        ngo_slug VARCHAR(190) NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) {$charset};";

    $sql_user_votes = "CREATE TABLE IF NOT EXISTS {$table_user_votes} (
        pseudo_id VARCHAR(32) PRIMARY KEY,
        available_votes INT NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) {$charset};";

    $sql_stats = "CREATE TABLE IF NOT EXISTS {$table_stats} (
        pseudo_id VARCHAR(32) PRIMARY KEY,
        total_views INT NOT NULL DEFAULT 0,
        total_votes INT NOT NULL DEFAULT 0,
        streak_days INT NOT NULL DEFAULT 0,
        last_view_day CHAR(10) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_last_day (last_view_day)
    ) {$charset};";

    $sql_education = "CREATE TABLE IF NOT EXISTS {$table_education} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pseudo_id VARCHAR(32) NOT NULL,
        content_id VARCHAR(191) NOT NULL,
        content_type VARCHAR(32) NOT NULL DEFAULT 'education',
        watched_seconds INT NOT NULL DEFAULT 0,
        points_earned INT NOT NULL DEFAULT 0,
        votes_earned INT NOT NULL DEFAULT 0,
        intervals_completed INT NOT NULL DEFAULT 0,
        session_token CHAR(36) DEFAULT NULL,
        dedupe_key VARCHAR(190) DEFAULT NULL,
        day_key CHAR(10) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_dedupe (dedupe_key),
        KEY idx_pseudo (pseudo_id),
        KEY idx_content (content_id),
        KEY idx_day (day_key),
        KEY idx_created (created_at)
    ) {$charset};";

    $sql_quarters = "CREATE TABLE IF NOT EXISTS {$table_quarters} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quarter_key VARCHAR(10) NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        pool_amount INT NOT NULL DEFAULT 0,
        total_votes INT NOT NULL DEFAULT 0,
        status ENUM('active','closing','closed','paid') NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        closed_at DATETIME DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        UNIQUE KEY uniq_quarter (quarter_key)
    ) {$charset};";

    $sql_quarter_results = "CREATE TABLE IF NOT EXISTS {$table_quarter_results} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quarter_key VARCHAR(10) NOT NULL,
        ngo_slug VARCHAR(190) NOT NULL,
        ngo_name VARCHAR(255) DEFAULT NULL,
        votes INT UNSIGNED NOT NULL DEFAULT 0,
        percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
        amount INT UNSIGNED NOT NULL DEFAULT 0,
        rank SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_quarter_ngo (quarter_key, ngo_slug),
        KEY idx_quarter (quarter_key)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_views);
    dbDelta($sql_votes);
    dbDelta($sql_prefs);
    dbDelta($sql_user_votes);
    dbDelta($sql_stats);
    dbDelta($sql_education);
    dbDelta($sql_quarters);
    dbDelta($sql_quarter_results);

    update_option('impactshop_ads_watch_schema_version', IMPACTSHOP_ADS_WATCH_SCHEMA_VERSION, false);
}

// ─────────────────────────────────────────────────────────────────────────────
// SPONSOR VIDEOS (Admin + Settings)
// ─────────────────────────────────────────────────────────────────────────────

add_action('init', 'impactshop_ads_watch_register_sponsor_cpt');

function impactshop_ads_watch_register_sponsor_cpt(): void
{
    register_post_type('impact_sponsor_video', [
        'labels' => [
            'name' => 'Szponzor videók',
            'singular_name' => 'Szponzor videó',
            'add_new_item' => 'Új szponzor videó',
            'edit_item' => 'Szponzor videó szerkesztése',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-video-alt3',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
}

add_action('add_meta_boxes', 'impactshop_ads_watch_sponsor_meta_boxes');

function impactshop_ads_watch_sponsor_meta_boxes(): void
{
    add_meta_box(
        'impactshop_sponsor_settings',
        'Szponzor videó beállítások',
        'impactshop_ads_watch_sponsor_meta_box_render',
        'impact_sponsor_video',
        'normal',
        'high'
    );
}

function impactshop_ads_watch_sponsor_meta_box_render(WP_Post $post): void
{
    wp_nonce_field('impactshop_sponsor_save', 'impactshop_sponsor_nonce');
    $settings = impactshop_ads_watch_get_sponsor_settings($post->ID);
    ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="impactshop_sponsor_media_type">Videó típus</label></th>
            <td>
                <select name="impactshop_sponsor_media_type" id="impactshop_sponsor_media_type">
                    <option value="mp4" <?php selected($settings['media_type'], 'mp4'); ?>>MP4 (direkt link)</option>
                    <option value="youtube" <?php selected($settings['media_type'], 'youtube'); ?>>YouTube</option>
                    <option value="vast" <?php selected($settings['media_type'], 'vast'); ?>>VAST/IMA tag</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_media_url">Videó URL (MP4)</label></th>
            <td>
                <input type="url" name="impactshop_sponsor_media_url" id="impactshop_sponsor_media_url" class="regular-text" value="<?php echo esc_attr($settings['media_url']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_youtube_url">YouTube URL</label></th>
            <td>
                <input type="url" name="impactshop_sponsor_youtube_url" id="impactshop_sponsor_youtube_url" class="regular-text" value="<?php echo esc_attr($settings['youtube_url']); ?>">
                <p class="description">YouTube videó link (pl. https://www.youtube.com/watch?v=...)</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_vast_tag">VAST tag URL</label></th>
            <td>
                <input type="url" name="impactshop_sponsor_vast_tag" id="impactshop_sponsor_vast_tag" class="regular-text" value="<?php echo esc_attr($settings['vast_tag']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_cta_url">CTA gomb link</label></th>
            <td>
                <input type="url" name="impactshop_sponsor_cta_url" id="impactshop_sponsor_cta_url" class="regular-text" value="<?php echo esc_attr($settings['cta_url']); ?>">
                <p class="description">A CTA gomb felirata fix: "Kattints ide!"</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_total_limit">Max megtekintés (összes)</label></th>
            <td>
                <input type="number" name="impactshop_sponsor_total_limit" id="impactshop_sponsor_total_limit" min="0" step="1" value="<?php echo esc_attr($settings['total_limit']); ?>">
                <p class="description">0 = korlátlan.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_user_limit">Max megtekintés / user</label></th>
            <td>
                <input type="number" name="impactshop_sponsor_user_limit" id="impactshop_sponsor_user_limit" min="0" step="1" value="<?php echo esc_attr($settings['user_limit']); ?>">
                <p class="description">0 = korlátlan.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_cooldown">Következő megtekintésig (másodperc)</label></th>
            <td>
                <input type="number" name="impactshop_sponsor_cooldown" id="impactshop_sponsor_cooldown" min="0" step="1" value="<?php echo esc_attr((int) ($settings['cooldown'] / 60)); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_start_at">Kezdete</label></th>
            <td>
                <input type="datetime-local" name="impactshop_sponsor_start_at" id="impactshop_sponsor_start_at" value="<?php echo esc_attr($settings['start_at_local']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_sponsor_end_at">Vége</label></th>
            <td>
                <input type="datetime-local" name="impactshop_sponsor_end_at" id="impactshop_sponsor_end_at" value="<?php echo esc_attr($settings['end_at_local']); ?>">
            </td>
        </tr>
    </table>
    <?php
}

add_action('save_post_impact_sponsor_video', 'impactshop_ads_watch_save_sponsor_meta');

function impactshop_ads_watch_extract_youtube_id(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^[a-zA-Z0-9_-]{6,20}$/', $value)) {
        return $value;
    }
    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([a-zA-Z0-9_-]{6,20})/i', $value, $matches)) {
        return $matches[1];
    }
    return '';
}

function impactshop_ads_watch_save_sponsor_meta(int $post_id): void
{
    if (!isset($_POST['impactshop_sponsor_nonce']) || !wp_verify_nonce((string) $_POST['impactshop_sponsor_nonce'], 'impactshop_sponsor_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $media_type = sanitize_text_field((string) ($_POST['impactshop_sponsor_media_type'] ?? 'mp4'));
    if (!in_array($media_type, ['mp4', 'youtube', 'vast'], true)) {
        $media_type = 'mp4';
    }
    $media_url = esc_url_raw((string) ($_POST['impactshop_sponsor_media_url'] ?? ''));
    $youtube_url = esc_url_raw((string) ($_POST['impactshop_sponsor_youtube_url'] ?? ''));
    $youtube_id = impactshop_ads_watch_extract_youtube_id($youtube_url);
    $vast_tag = esc_url_raw((string) ($_POST['impactshop_sponsor_vast_tag'] ?? ''));
    $cta_label = sanitize_text_field((string) ($_POST['impactshop_sponsor_cta_label'] ?? ''));
    $cta_url = esc_url_raw((string) ($_POST['impactshop_sponsor_cta_url'] ?? ''));

    update_post_meta($post_id, 'impactshop_sponsor_media_type', $media_type);
    update_post_meta($post_id, 'impactshop_sponsor_media_url', $media_url);
    update_post_meta($post_id, 'impactshop_sponsor_youtube_url', $youtube_url);
    update_post_meta($post_id, 'impactshop_sponsor_youtube_id', $youtube_id);
    update_post_meta($post_id, 'impactshop_sponsor_vast_tag', $vast_tag);
    update_post_meta($post_id, 'impactshop_sponsor_cta_label', $cta_label);
    update_post_meta($post_id, 'impactshop_sponsor_cta_url', $cta_url);

    $total_limit = max(0, (int) ($_POST['impactshop_sponsor_total_limit'] ?? 0));
    $user_limit = max(0, (int) ($_POST['impactshop_sponsor_user_limit'] ?? 0));
    $cooldown_minutes = max(0, (int) ($_POST['impactshop_sponsor_cooldown'] ?? 0));

    update_post_meta($post_id, 'impactshop_sponsor_total_limit', $total_limit);
    update_post_meta($post_id, 'impactshop_sponsor_user_limit', $user_limit);
    update_post_meta($post_id, 'impactshop_sponsor_cooldown', $cooldown_minutes * 60);

    $start_local = sanitize_text_field((string) ($_POST['impactshop_sponsor_start_at'] ?? ''));
    $end_local = sanitize_text_field((string) ($_POST['impactshop_sponsor_end_at'] ?? ''));

    update_post_meta($post_id, 'impactshop_sponsor_start_at', $start_local);
    update_post_meta($post_id, 'impactshop_sponsor_end_at', $end_local);

    $errors = [];
    if ($media_type === 'mp4') {
        if ($media_url === '' || !wp_http_validate_url($media_url) || !preg_match('/\\.mp4(\\?|$)/i', $media_url)) {
            $errors[] = 'Az MP4 videó URL hiányzik vagy nem tűnik érvényes MP4 linknek.';
        }
    }
    if ($media_type === 'youtube') {
        if ($youtube_id === '') {
            $errors[] = 'A YouTube videó URL hiányzik vagy érvénytelen.';
        }
    }
    if ($media_type === 'vast') {
        if ($vast_tag === '' || !wp_http_validate_url($vast_tag)) {
            $errors[] = 'A VAST tag URL hiányzik vagy érvénytelen.';
        }
    }

    if (!empty($errors)) {
        update_post_meta($post_id, 'impactshop_sponsor_validation_error', implode(' ', $errors));
    } else {
        delete_post_meta($post_id, 'impactshop_sponsor_validation_error');
    }
}

add_action('admin_notices', 'impactshop_ads_watch_sponsor_validation_notice');

function impactshop_ads_watch_sponsor_validation_notice(): void
{
    if (!is_admin()) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'impact_sponsor_video') {
        return;
    }
    if (!isset($_GET['post'])) {
        return;
    }
    $post_id = absint($_GET['post']);
    if ($post_id <= 0) {
        return;
    }
    $error = get_post_meta($post_id, 'impactshop_sponsor_validation_error', true);
    if (!$error) {
        return;
    }
    echo '<div class="notice notice-warning"><p>' . esc_html($error) . '</p></div>';
}

add_action('admin_menu', 'impactshop_ads_watch_register_stats_page');

function impactshop_ads_watch_register_stats_page(): void
{
    add_submenu_page(
        'edit.php?post_type=impact_sponsor_video',
        'Szponzor videó statisztikák',
        'Statisztikák',
        'manage_options',
        'impactshop-sponsor-stats',
        'impactshop_ads_watch_sponsor_stats_page'
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// EDUCATION VIDEOS (Admin + Settings)
// ─────────────────────────────────────────────────────────────────────────────

add_action('init', 'impactshop_ads_watch_register_education_cpt');

function impactshop_ads_watch_register_education_cpt(): void
{
    register_post_type('impact_edu_video', [
        'labels' => [
            'name' => 'Edukációs videók',
            'singular_name' => 'Edukációs videó',
            'add_new_item' => 'Új edukációs videó',
            'edit_item' => 'Edukációs videó szerkesztése',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-welcome-learn-more',
        'supports' => ['title', 'editor'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
}

add_action('add_meta_boxes', 'impactshop_ads_watch_education_meta_boxes');

function impactshop_ads_watch_education_meta_boxes(): void
{
    add_meta_box(
        'impactshop_education_settings',
        'Edukációs videó beállítások',
        'impactshop_ads_watch_education_meta_box_render',
        'impact_edu_video',
        'normal',
        'high'
    );
}

function impactshop_ads_watch_get_education_settings(int $post_id): array
{
    $media_type = get_post_meta($post_id, 'impactshop_edu_media_type', true);
    $media_type = in_array($media_type, ['youtube', 'mp4'], true) ? $media_type : 'youtube';
    $youtube_url = (string) get_post_meta($post_id, 'impactshop_edu_youtube_url', true);
    $video_url = (string) get_post_meta($post_id, 'impactshop_edu_video_url', true);

    return [
        'media_type' => $media_type,
        'youtube_url' => $youtube_url,
        'video_url' => $video_url,
        'duration' => (int) get_post_meta($post_id, 'impactshop_edu_duration', true),
        'interval_seconds' => (int) get_post_meta($post_id, 'impactshop_edu_interval_seconds', true) ?: IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS,
        'points_per_interval' => (int) get_post_meta($post_id, 'impactshop_edu_points_per_interval', true) ?: IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL,
        'votes_per_interval' => (int) get_post_meta($post_id, 'impactshop_edu_votes_per_interval', true) ?: IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL,
        'bonus_points' => (int) get_post_meta($post_id, 'impactshop_edu_bonus_points', true) ?: 10,
        'bonus_votes' => (int) get_post_meta($post_id, 'impactshop_edu_bonus_votes', true) ?: 10,
        'presence_interval' => (int) get_post_meta($post_id, 'impactshop_edu_presence_interval', true) ?: 60,
        'presence_timeout' => (int) get_post_meta($post_id, 'impactshop_edu_presence_timeout', true) ?: 30,
        'skip_enabled' => (bool) get_post_meta($post_id, 'impactshop_edu_skip_enabled', true),
        'user_limit' => (int) get_post_meta($post_id, 'impactshop_edu_user_limit', true),
        'cooldown' => (int) get_post_meta($post_id, 'impactshop_edu_cooldown', true),
        'start_at_local' => (string) get_post_meta($post_id, 'impactshop_edu_start_at', true),
        'end_at_local' => (string) get_post_meta($post_id, 'impactshop_edu_end_at', true),
        'cta_label' => (string) get_post_meta($post_id, 'impactshop_edu_cta_label', true),
        'cta_url' => (string) get_post_meta($post_id, 'impactshop_edu_cta_url', true),
    ];
}

function impactshop_ads_watch_education_meta_box_render(WP_Post $post): void
{
    wp_nonce_field('impactshop_edu_save', 'impactshop_edu_nonce');
    $settings = impactshop_ads_watch_get_education_settings($post->ID);
    ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="impactshop_edu_media_type">Videó típus</label></th>
            <td>
                <select name="impactshop_edu_media_type" id="impactshop_edu_media_type">
                    <option value="youtube" <?php selected($settings['media_type'], 'youtube'); ?>>YouTube</option>
                    <option value="mp4" <?php selected($settings['media_type'], 'mp4'); ?>>MP4 (direkt link)</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_youtube_url">YouTube URL</label></th>
            <td>
                <input type="url" name="impactshop_edu_youtube_url" id="impactshop_edu_youtube_url" class="regular-text" value="<?php echo esc_attr($settings['youtube_url']); ?>">
                <p class="description">YouTube videó link (pl. https://www.youtube.com/watch?v=...)</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_video_url">MP4 URL</label></th>
            <td>
                <input type="url" name="impactshop_edu_video_url" id="impactshop_edu_video_url" class="regular-text" value="<?php echo esc_attr($settings['video_url']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_duration">Teljes hossz (mp)</label></th>
            <td>
                <input type="number" name="impactshop_edu_duration" id="impactshop_edu_duration" min="0" step="1" value="<?php echo esc_attr($settings['duration']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_interval_seconds">Intervallum (mp)</label></th>
            <td>
                <input type="number" name="impactshop_edu_interval_seconds" id="impactshop_edu_interval_seconds" min="5" step="1" value="<?php echo esc_attr($settings['interval_seconds']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_points_per_interval">Pont/intervallum</label></th>
            <td>
                <input type="number" name="impactshop_edu_points_per_interval" id="impactshop_edu_points_per_interval" min="0" step="1" value="<?php echo esc_attr($settings['points_per_interval']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_votes_per_interval">Szavazat/intervallum</label></th>
            <td>
                <input type="number" name="impactshop_edu_votes_per_interval" id="impactshop_edu_votes_per_interval" min="0" step="1" value="<?php echo esc_attr($settings['votes_per_interval']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_bonus_points">Bónusz pont videó végén</label></th>
            <td>
                <input type="number" name="impactshop_edu_bonus_points" id="impactshop_edu_bonus_points" min="0" step="1" value="<?php echo esc_attr($settings['bonus_points']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_bonus_votes">Bónusz szavazat videó végén</label></th>
            <td>
                <input type="number" name="impactshop_edu_bonus_votes" id="impactshop_edu_bonus_votes" min="0" step="1" value="<?php echo esc_attr($settings['bonus_votes']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_presence_interval">Jelenlét ellenőrzés (mp)</label></th>
            <td>
                <input type="number" name="impactshop_edu_presence_interval" id="impactshop_edu_presence_interval" min="0" step="1" value="<?php echo esc_attr($settings['presence_interval']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_presence_timeout">Jelenlét timeout (mp)</label></th>
            <td>
                <input type="number" name="impactshop_edu_presence_timeout" id="impactshop_edu_presence_timeout" min="0" step="1" value="<?php echo esc_attr($settings['presence_timeout']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_skip_enabled">Skip engedélyezve</label></th>
            <td>
                <label>
                    <input type="checkbox" name="impactshop_edu_skip_enabled" id="impactshop_edu_skip_enabled" value="1" <?php checked($settings['skip_enabled']); ?>>
                    Igen, a felhasználó kihagyhatja
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_user_limit">Max megtekintés / user</label></th>
            <td>
                <input type="number" name="impactshop_edu_user_limit" id="impactshop_edu_user_limit" min="0" step="1" value="<?php echo esc_attr($settings['user_limit']); ?>">
                <p class="description">0 = korlátlan.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_cooldown">Következő megtekintésig (másodperc)</label></th>
            <td>
                <input type="number" name="impactshop_edu_cooldown" id="impactshop_edu_cooldown" min="0" step="1" value="<?php echo esc_attr((int) ($settings['cooldown'] / 60)); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_start_at">Kezdete</label></th>
            <td>
                <input type="datetime-local" name="impactshop_edu_start_at" id="impactshop_edu_start_at" value="<?php echo esc_attr($settings['start_at_local']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_end_at">Vége</label></th>
            <td>
                <input type="datetime-local" name="impactshop_edu_end_at" id="impactshop_edu_end_at" value="<?php echo esc_attr($settings['end_at_local']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="impactshop_edu_cta_url">CTA gomb link</label></th>
            <td>
                <input type="url" name="impactshop_edu_cta_url" id="impactshop_edu_cta_url" class="regular-text" value="<?php echo esc_attr($settings['cta_url']); ?>">
                <p class="description">A CTA gomb felirata fix: "Kattints ide!"</p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('save_post_impact_edu_video', 'impactshop_ads_watch_save_education_meta');

function impactshop_ads_watch_save_education_meta(int $post_id): void
{
    if (!isset($_POST['impactshop_edu_nonce']) || !wp_verify_nonce((string) $_POST['impactshop_edu_nonce'], 'impactshop_edu_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $media_type = sanitize_text_field((string) ($_POST['impactshop_edu_media_type'] ?? 'youtube'));
    if (!in_array($media_type, ['youtube', 'mp4'], true)) {
        $media_type = 'youtube';
    }
    $youtube_url = esc_url_raw((string) ($_POST['impactshop_edu_youtube_url'] ?? ''));
    $youtube_id = impactshop_ads_watch_extract_youtube_id($youtube_url);
    $video_url = esc_url_raw((string) ($_POST['impactshop_edu_video_url'] ?? ''));
    $duration = max(0, (int) ($_POST['impactshop_edu_duration'] ?? 0));
    $interval_seconds = max(5, (int) ($_POST['impactshop_edu_interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS));
    $points_per_interval = max(0, (int) ($_POST['impactshop_edu_points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL));
    $votes_per_interval = max(0, (int) ($_POST['impactshop_edu_votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL));
    $bonus_points = max(0, (int) ($_POST['impactshop_edu_bonus_points'] ?? 10));
    $bonus_votes = max(0, (int) ($_POST['impactshop_edu_bonus_votes'] ?? 10));
    $presence_interval = max(0, (int) ($_POST['impactshop_edu_presence_interval'] ?? 60));
    $presence_timeout = max(0, (int) ($_POST['impactshop_edu_presence_timeout'] ?? 30));
    $skip_enabled = !empty($_POST['impactshop_edu_skip_enabled']);
    $user_limit = max(0, (int) ($_POST['impactshop_edu_user_limit'] ?? 0));
    $cooldown_minutes = max(0, (int) ($_POST['impactshop_edu_cooldown'] ?? 0));
    $start_local = sanitize_text_field((string) ($_POST['impactshop_edu_start_at'] ?? ''));
    $end_local = sanitize_text_field((string) ($_POST['impactshop_edu_end_at'] ?? ''));
    $cta_label = sanitize_text_field((string) ($_POST['impactshop_edu_cta_label'] ?? ''));
    $cta_url = esc_url_raw((string) ($_POST['impactshop_edu_cta_url'] ?? ''));

    update_post_meta($post_id, 'impactshop_edu_media_type', $media_type);
    update_post_meta($post_id, 'impactshop_edu_youtube_url', $youtube_url);
    update_post_meta($post_id, 'impactshop_edu_youtube_id', $youtube_id);
    update_post_meta($post_id, 'impactshop_edu_video_url', $video_url);
    update_post_meta($post_id, 'impactshop_edu_duration', $duration);
    update_post_meta($post_id, 'impactshop_edu_interval_seconds', $interval_seconds);
    update_post_meta($post_id, 'impactshop_edu_points_per_interval', $points_per_interval);
    update_post_meta($post_id, 'impactshop_edu_votes_per_interval', $votes_per_interval);
    update_post_meta($post_id, 'impactshop_edu_bonus_points', $bonus_points);
    update_post_meta($post_id, 'impactshop_edu_bonus_votes', $bonus_votes);
    update_post_meta($post_id, 'impactshop_edu_presence_interval', $presence_interval);
    update_post_meta($post_id, 'impactshop_edu_presence_timeout', $presence_timeout);
    update_post_meta($post_id, 'impactshop_edu_skip_enabled', $skip_enabled ? 1 : 0);
    update_post_meta($post_id, 'impactshop_edu_user_limit', $user_limit);
    update_post_meta($post_id, 'impactshop_edu_cooldown', $cooldown_minutes * 60);
    update_post_meta($post_id, 'impactshop_edu_start_at', $start_local);
    update_post_meta($post_id, 'impactshop_edu_end_at', $end_local);
    update_post_meta($post_id, 'impactshop_edu_cta_label', $cta_label);
    update_post_meta($post_id, 'impactshop_edu_cta_url', $cta_url);

    $errors = [];
    if ($media_type === 'youtube' && $youtube_id === '') {
        $errors[] = 'A YouTube videó URL hiányzik vagy érvénytelen.';
    }
    if ($media_type === 'mp4') {
        if ($video_url === '' || !wp_http_validate_url($video_url) || !preg_match('/\\.mp4(\\?|$)/i', $video_url)) {
            $errors[] = 'Az MP4 videó URL hiányzik vagy nem tűnik érvényes MP4 linknek.';
        }
    }

    if (!empty($errors)) {
        update_post_meta($post_id, 'impactshop_edu_validation_error', implode(' ', $errors));
    } else {
        delete_post_meta($post_id, 'impactshop_edu_validation_error');
    }
}

add_action('admin_notices', 'impactshop_ads_watch_education_validation_notice');

function impactshop_ads_watch_education_validation_notice(): void
{
    if (!is_admin()) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'impact_edu_video') {
        return;
    }
    if (!isset($_GET['post'])) {
        return;
    }
    $post_id = absint($_GET['post']);
    if ($post_id <= 0) {
        return;
    }
    $error = get_post_meta($post_id, 'impactshop_edu_validation_error', true);
    if (!$error) {
        return;
    }
    echo '<div class="notice notice-warning"><p>' . esc_html($error) . '</p></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// REST API ROUTES
// ─────────────────────────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    $namespace = 'impact/v1';

    register_rest_route($namespace, '/ads-watch/config', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_config',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/ads-watch/next', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_next',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/ads-watch/status', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_status',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/ads-watch/view', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_ads_watch_view',
        'permission_callback' => 'impactshop_ads_watch_require_nonce',
    ]);

    register_rest_route($namespace, '/ads-watch/education', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_ads_watch_education',
        'permission_callback' => 'impactshop_ads_watch_require_nonce',
    ]);

    register_rest_route($namespace, '/ads-watch/allocate', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_ads_watch_allocate_votes',
        'permission_callback' => 'impactshop_ads_watch_require_nonce',
    ]);

    register_rest_route($namespace, '/ads-watch/set-ngo', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_ads_watch_set_ngo',
        'permission_callback' => 'impactshop_ads_watch_require_nonce',
    ]);

    register_rest_route($namespace, '/ads-watch/tally', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_tally',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/ads-watch/leaderboard', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_leaderboard',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/ads-watch/ngos', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_ngos',
        'permission_callback' => '__return_true',
    ]);

    // DEBUG endpoint - remove after testing
    register_rest_route($namespace, '/ads-watch/debug-rotation', [
        'methods'             => 'GET',
        'callback'            => 'impactshop_ads_watch_debug_rotation',
        'permission_callback' => '__return_true',
    ]);
});

// DEBUG: Rotation diagnosztika - törölhető tesztelés után
function impactshop_ads_watch_debug_rotation(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);
    
    // Sponsor check
    $sponsor = impactshop_ads_watch_pick_sponsor_for_pseudo($pseudo_id);
    $sponsor_posts = get_posts([
        'post_type' => 'impact_sponsor_video',
        'post_status' => 'publish',
        'numberposts' => 20,
    ]);
    $sponsor_details = [];
    $now = (int) current_time('timestamp');
    foreach ($sponsor_posts as $post) {
        $settings = impactshop_ads_watch_get_sponsor_settings($post->ID);
        $is_active = impactshop_ads_watch_is_sponsor_active($settings, $now);
        $can_view_payload = impactshop_ads_watch_can_view_sponsor_reason($pseudo_id, (int) $post->ID);
        $can_view = $can_view_payload['can_view'];
        $can_view_reason = $can_view_payload['reason'] !== '' ? $can_view_payload['reason'] : 'ok';
        
        $sponsor_details[] = [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'status' => $post->post_status,
            'media_type' => $settings['media_type'],
            'is_active' => $is_active,
            'can_view' => $can_view,
            'can_view_reason' => $can_view_reason,
            'settings' => $settings,
        ];
    }
    
    // Education check
    $education_videos = impactshop_ads_watch_get_education_videos();
    $education_posts = get_posts([
        'post_type' => 'impact_edu_video',
        'post_status' => 'publish',
        'numberposts' => 20,
    ]);
    $education_details = [];
    foreach ($education_posts as $post) {
        $settings = impactshop_ads_watch_get_education_settings($post->ID);
        $education_details[] = [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'status' => $post->post_status,
            'settings' => $settings,
        ];
    }
    
    // Weights calculation
    $has_education = !empty($education_videos);
    $has_sponsor = !empty($sponsor);
    $seen = impactshop_ads_watch_get_seen_content($pseudo_id);
    
    $weights_original = [
        'regular' => IMPACTSHOP_ADS_ROTATE_WEIGHT_AD,
        'auto_banner' => 0,
        'sponsor' => $has_sponsor ? IMPACTSHOP_ADS_ROTATE_WEIGHT_SPONSOR : 0,
        'education' => $has_education ? IMPACTSHOP_ADS_ROTATE_WEIGHT_EDU : 0,
    ];
    
    // Calculate adjusted weights (same logic as in next endpoint)
    $weights_adjusted = $weights_original;
    
    // Check if sponsor is already seen
    $sponsor_already_seen = false;
    if ($has_sponsor) {
        $sponsor_check_id = $sponsor['id'] > 0
            ? 'sponsor:' . $sponsor['id']
            : 'sponsor:' . md5(wp_json_encode($sponsor));
        if (isset($seen[$sponsor_check_id])) {
            $sponsor_already_seen = true;
            if ($weights_adjusted['education'] > 0) {
                $weights_adjusted['education'] += $weights_adjusted['sponsor'];
            }
            $weights_adjusted['sponsor'] = 0;
        }
    }
    
    // Check if all education videos are seen
    $all_edu_seen = true;
    foreach ($education_videos as $edu) {
        if (!isset($seen['edu:' . $edu['id']])) {
            $all_edu_seen = false;
            break;
        }
    }
    $education_already_seen = $all_edu_seen && $has_education;
    if ($education_already_seen) {
        if ($weights_adjusted['sponsor'] > 0) {
            $weights_adjusted['sponsor'] += $weights_adjusted['education'];
        }
        $weights_adjusted['education'] = 0;
    }
    
    return new WP_REST_Response([
        'pseudo_id' => $pseudo_id,
        'sponsor_found' => $sponsor,
        'sponsor_posts_count' => count($sponsor_posts),
        'sponsor_details' => $sponsor_details,
        'education_videos_count' => count($education_videos),
        'education_posts_count' => count($education_posts),
        'education_details' => $education_details,
        'education_normalized' => $education_videos,
        'has_sponsor' => $has_sponsor,
        'has_education' => $has_education,
        'weights_original' => $weights_original,
        'weights_adjusted' => $weights_adjusted,
        'sponsor_already_seen' => $sponsor_already_seen,
        'education_already_seen' => $education_already_seen,
        'seen_content' => $seen,
        'constants' => [
            'WEIGHT_AD' => IMPACTSHOP_ADS_ROTATE_WEIGHT_AD,
            'WEIGHT_BANNER' => IMPACTSHOP_ADS_ROTATE_WEIGHT_BANNER,
            'WEIGHT_SPONSOR' => IMPACTSHOP_ADS_ROTATE_WEIGHT_SPONSOR,
            'WEIGHT_EDU' => IMPACTSHOP_ADS_ROTATE_WEIGHT_EDU,
        ],
    ], 200);
}

// ─────────────────────────────────────────────────────────────────────────────
// REST CALLBACKS
// ─────────────────────────────────────────────────────────────────────────────

function impactshop_ads_watch_config(WP_REST_Request $request): WP_REST_Response
{
    return new WP_REST_Response([
        'donation_pool'     => impactshop_ads_get_active_pool(),
        'points_regular'    => IMPACTSHOP_ADS_POINTS_REGULAR,
        'points_sponsor'    => IMPACTSHOP_ADS_POINTS_SPONSOR,
        'votes_regular'     => IMPACTSHOP_ADS_VOTES_REGULAR,
        'votes_sponsor'     => IMPACTSHOP_ADS_VOTES_SPONSOR,
        'education_interval_seconds' => IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS,
        'education_points_per_interval' => IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL,
        'education_votes_per_interval' => IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL,
        'rotation' => [
            'ad' => IMPACTSHOP_ADS_ROTATE_WEIGHT_AD,
            'auto_banner' => IMPACTSHOP_ADS_ROTATE_WEIGHT_BANNER,
            'sponsor' => IMPACTSHOP_ADS_ROTATE_WEIGHT_SPONSOR,
            'education' => IMPACTSHOP_ADS_ROTATE_WEIGHT_EDU,
            'max_ad_streak' => IMPACTSHOP_ADS_ROTATE_MAX_AD_STREAK,
        ],
        'ad_tag_url'        => impactshop_ads_watch_get_ad_tag_url(),
        'version'           => IMPACTSHOP_ADS_WATCH_VERSION,
    ], 200);
}

function impactshop_ads_watch_next(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);
    $ad_tag_url = impactshop_ads_watch_get_ad_tag_url();
    $cta_label = impactshop_ads_watch_get_default_cta_label();
    $cta_url = impactshop_ads_watch_get_default_cta_url();
    $cta_points_regular = (int) apply_filters('impactshop_ads_watch_cta_points', 1, 'regular');
    $cta_points_sponsor = (int) apply_filters('impactshop_ads_watch_cta_points', 5, 'sponsor');
    $cta_points_education = (int) apply_filters('impactshop_ads_watch_cta_points', 1, 'education');
    $cta_points_banner = (int) apply_filters('impactshop_ads_watch_cta_points', 1, 'auto_banner');

    if ($pseudo_id === '') {
        $cta = impactshop_ads_watch_build_cta($cta_label, $cta_url, $cta_points_regular);
        return new WP_REST_Response([
            'mode' => 'regular',
            'content_type' => 'ad',
            'content_id' => 'ad',
            'ad_tag_url' => $ad_tag_url,
            'cta' => $cta,
            'reward_rules' => [
                'points' => IMPACTSHOP_ADS_POINTS_REGULAR,
                'votes' => IMPACTSHOP_ADS_VOTES_REGULAR,
                'cta_points' => $cta_points_regular,
            ],
        ], 200);
    }

    $sponsor = impactshop_ads_watch_pick_sponsor_for_pseudo($pseudo_id);
    $mock_sponsors = impactshop_ads_watch_get_mock_sponsors();
    $mock_chance = (int) apply_filters('impactshop_ads_watch_mock_chance', 40);

    if (!empty($mock_sponsors) && wp_rand(1, 100) <= max(0, min(100, $mock_chance))) {
        $sponsor = $mock_sponsors[array_rand($mock_sponsors)];
        $sponsor['id'] = 0;
    }

    $education_videos = impactshop_ads_watch_get_education_videos();
    if ($pseudo_id !== '') {
        $education_videos = impactshop_ads_watch_filter_education_for_user($pseudo_id, $education_videos);
    }
    $has_education = !empty($education_videos);
    $auto_banner = function_exists('impactshop_auto_banner_get_active') ? impactshop_auto_banner_get_active() : [];
    $has_banner = !empty($auto_banner);
    $has_sponsor = !empty($sponsor);
    $force_banner = ($ad_tag_url === '' && $has_banner);

    if ($has_sponsor) {
        $media_type = (string) ($sponsor['media_type'] ?? '');
        $has_valid_media = false;

        if ($media_type === 'youtube') {
            $has_valid_media = !empty($sponsor['youtube_id']) || !empty($sponsor['youtube_url']);
        } elseif ($media_type === 'mp4') {
            $has_valid_media = !empty($sponsor['media_url']);
        } else {
            $has_valid_media = !empty($sponsor['vast_tag']) || $ad_tag_url !== '';
        }

        if (!$has_valid_media) {
            $has_sponsor = false;
            $sponsor = [];
        }
    }
    $rotation = impactshop_ads_watch_get_rotation_state($pseudo_id);

    $weights = [
        'regular' => IMPACTSHOP_ADS_ROTATE_WEIGHT_AD,
        'auto_banner' => $has_banner ? IMPACTSHOP_ADS_ROTATE_WEIGHT_BANNER : 0,
        'sponsor' => $has_sponsor ? IMPACTSHOP_ADS_ROTATE_WEIGHT_SPONSOR : 0,
        'education' => $has_education ? IMPACTSHOP_ADS_ROTATE_WEIGHT_EDU : 0,
    ];

    if ((int) ($rotation['ad_streak'] ?? 0) >= IMPACTSHOP_ADS_ROTATE_MAX_AD_STREAK) {
        $weights['regular'] = 0;
    }
    if ($force_banner) {
        $weights['regular'] = 0;
    }

    $max_attempts = (int) $request->get_param('batch_size');
    $max_attempts = $max_attempts > 0 ? min(5, $max_attempts) : 3;
    $seen = impactshop_ads_watch_get_seen_content($pseudo_id);

    // Check if all available content has been seen - if so, clear seen list to allow re-rotation
    $all_content_ids = [];
    foreach ($education_videos as $edu) {
        $all_content_ids[] = 'edu:' . $edu['id'];
    }
    if ($sponsor) {
        $sponsor_content_id = $sponsor['id'] > 0
            ? 'sponsor:' . $sponsor['id']
            : 'sponsor:' . md5(wp_json_encode($sponsor));
        $all_content_ids[] = $sponsor_content_id;
    }
    
    $unseen_content = array_filter($all_content_ids, function($id) use ($seen) {
        return !isset($seen[$id]);
    });
    
    // If all special content has been seen, clear the seen list to allow re-rotation
    if (!empty($all_content_ids) && empty($unseen_content)) {
        $seen = [];
        if ($pseudo_id !== '') {
            $key = 'impactshop_ads_seen_' . md5($pseudo_id);
            delete_transient($key);
        }
    }

    // Pre-adjust weights: if a content type is already seen, zero its weight BEFORE the loop
    // This prevents wasted iterations and allows other content types to be selected
    if ($has_sponsor) {
        $sponsor_check_id = $sponsor['id'] > 0
            ? 'sponsor:' . $sponsor['id']
            : 'sponsor:' . md5(wp_json_encode($sponsor));
        if (isset($seen[$sponsor_check_id])) {
            // Sponsor already seen - redistribute weight to education if available
            if ($weights['education'] > 0) {
                $weights['education'] += $weights['sponsor'];
            }
            $weights['sponsor'] = 0;
        }
    }
    
    // Check if all education videos are seen
    $all_edu_seen = true;
    foreach ($education_videos as $edu) {
        if (!isset($seen['edu:' . $edu['id']])) {
            $all_edu_seen = false;
            break;
        }
    }
    if ($all_edu_seen && $has_education) {
        // All education seen - redistribute weight to sponsor if available
        if ($weights['sponsor'] > 0) {
            $weights['sponsor'] += $weights['education'];
        }
        $weights['education'] = 0;
    }

    for ($i = 0; $i < $max_attempts; $i++) {
        $choice = impactshop_ads_watch_pick_weighted($weights);

        if ($choice === 'education' && $has_education) {
            $content = impactshop_ads_watch_pick_education_content($education_videos);
            if ($content) {
                $content_id = 'edu:' . $content['id'];
                if (isset($seen[$content_id])) {
                    $weights['education'] = 0;
                    continue;
                }
                impactshop_ads_watch_mark_seen_content($pseudo_id, $content_id);
                $session = impactshop_ads_watch_create_education_session($pseudo_id, $content);
                $cta = impactshop_ads_watch_build_cta(
                    $content['cta_label'] !== '' ? $content['cta_label'] : $cta_label,
                    $content['cta_url'] !== '' ? $content['cta_url'] : $cta_url,
                    $cta_points_education
                );
                $cta['dedupe_key'] = impactshop_ads_watch_build_cta_dedupe($pseudo_id, $content_id);
                return new WP_REST_Response([
                    'mode' => 'education',
                    'content_type' => 'education',
                    'content_id' => $content_id,
                    'education' => [
                        'id' => $content['id'],
                        'title' => $content['title'],
                        'description' => $content['description'],
                        'youtube_id' => $content['youtube_id'],
                        'video_url' => $content['video_url'],
                        'duration_seconds' => $session['duration_seconds'] ?? $content['duration_seconds'],
                        'max_intervals' => $session['max_intervals'],
                        'session_token' => $session['token'],
                        'interval_seconds' => $session['interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS,
                        'points_per_interval' => $session['points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL,
                        'votes_per_interval' => $session['votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL,
                        'bonus_points' => $session['bonus_points'] ?? 0,
                        'bonus_votes' => $session['bonus_votes'] ?? 0,
                        'presence_interval' => $session['presence_interval'] ?? 0,
                        'presence_timeout' => $session['presence_timeout'] ?? 0,
                        'skip_enabled' => $session['skip_enabled'] ?? false,
                        'cta_label' => $content['cta_label'],
                        'cta_url' => $content['cta_url'],
                    ],
                    'reward_rules' => [
                        'interval_seconds' => $session['interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS,
                        'points_per_interval' => $session['points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL,
                        'votes_per_interval' => $session['votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL,
                        'bonus_points' => $session['bonus_points'] ?? 0,
                        'bonus_votes' => $session['bonus_votes'] ?? 0,
                        'cta_points' => $cta_points_education,
                    ],
                    'cta' => $cta,
                ], 200);
            }
        }

        if ($choice === 'sponsor' && $has_sponsor) {
            $content_id = $sponsor['id'] > 0
                ? 'sponsor:' . $sponsor['id']
                : 'sponsor:' . md5(wp_json_encode($sponsor));
            if (isset($seen[$content_id])) {
                $weights['sponsor'] = 0;
                continue;
            }
            impactshop_ads_watch_mark_seen_content($pseudo_id, $content_id);
            $cta = impactshop_ads_watch_build_cta(
                $sponsor['cta_label'] !== '' ? $sponsor['cta_label'] : $cta_label,
                $sponsor['cta_url'] !== '' ? $sponsor['cta_url'] : $cta_url,
                $cta_points_sponsor
            );
            $cta['dedupe_key'] = impactshop_ads_watch_build_cta_dedupe($pseudo_id, $content_id);
            return new WP_REST_Response([
                'mode'    => 'sponsor',
                'content_type' => 'sponsor',
                'content_id' => $content_id,
                'sponsor' => $sponsor,
                'reward_rules' => [
                    'points' => IMPACTSHOP_ADS_POINTS_SPONSOR,
                    'votes' => IMPACTSHOP_ADS_VOTES_SPONSOR,
                    'cta_points' => $cta_points_sponsor,
                ],
                'cta' => $cta,
            ], 200);
        }

        if ($choice === 'auto_banner' && $has_banner) {
            $content_id = $auto_banner['id'] > 0
                ? 'banner:' . $auto_banner['id']
                : 'banner:' . md5(wp_json_encode($auto_banner));
            if (isset($seen[$content_id])) {
                $weights['auto_banner'] = 0;
                continue;
            }
            impactshop_ads_watch_mark_seen_content($pseudo_id, $content_id);
            $cta = impactshop_ads_watch_build_cta(
                $cta_label,
                $auto_banner['banner_url'] !== '' ? $auto_banner['banner_url'] : $cta_url,
                $cta_points_banner
            );
            $cta['dedupe_key'] = impactshop_ads_watch_build_cta_dedupe($pseudo_id, $content_id);
            return new WP_REST_Response([
                'mode' => 'auto_banner',
                'content_type' => 'auto_banner',
                'content_id' => $content_id,
                'auto_banner' => $auto_banner,
                'reward_rules' => [
                    'points' => IMPACTSHOP_ADS_POINTS_BANNER,
                    'votes' => IMPACTSHOP_ADS_VOTES_BANNER,
                    'cta_points' => $cta_points_banner,
                ],
                'cta' => $cta,
                'ttl_seconds' => 5,
            ], 200);
        }

        $weights['regular'] = max(0, (int) $weights['regular']);
        break;
    }

    if ($force_banner && $has_banner) {
        $content_id = $auto_banner['id'] > 0
            ? 'banner:' . $auto_banner['id']
            : 'banner:' . md5(wp_json_encode($auto_banner));
        $cta = impactshop_ads_watch_build_cta(
            $cta_label,
            $auto_banner['banner_url'] !== '' ? $auto_banner['banner_url'] : $cta_url,
            $cta_points_banner
        );
        $cta['dedupe_key'] = impactshop_ads_watch_build_cta_dedupe($pseudo_id, $content_id);
        return new WP_REST_Response([
            'mode' => 'auto_banner',
            'content_type' => 'auto_banner',
            'content_id' => $content_id,
            'auto_banner' => $auto_banner,
            'reward_rules' => [
                'points' => IMPACTSHOP_ADS_POINTS_BANNER,
                'votes' => IMPACTSHOP_ADS_VOTES_BANNER,
                'cta_points' => $cta_points_banner,
            ],
            'cta' => $cta,
            'ttl_seconds' => 5,
        ], 200);
    }

    $cta = impactshop_ads_watch_build_cta($cta_label, $cta_url, $cta_points_regular);
    $cta['dedupe_key'] = impactshop_ads_watch_build_cta_dedupe($pseudo_id, 'ad');
    return new WP_REST_Response([
        'mode'       => 'regular',
        'content_type' => 'ad',
        'content_id' => 'ad',
        'ad_tag_url' => $ad_tag_url,
        'reward_rules' => [
            'points' => IMPACTSHOP_ADS_POINTS_REGULAR,
            'votes' => IMPACTSHOP_ADS_VOTES_REGULAR,
            'cta_points' => $cta_points_regular,
        ],
        'cta' => $cta,
    ], 200);
}

function impactshop_ads_watch_status(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);

    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'has_identity' => false,
            'available_votes' => 0,
        ], 200);
    }

    $points_total = 0;
    $level = 'basic';
    $vote_weight_regular = IMPACTSHOP_ADS_VOTES_REGULAR;
    $vote_weight_sponsor = IMPACTSHOP_ADS_VOTES_SPONSOR;
    $donation_multiplier = 1.0;

    if (class_exists('Sharity_Points_Manager')) {
        $points_manager = new Sharity_Points_Manager();
        $snapshot = $points_manager->get_points_snapshot_for_pseudo($pseudo_id);
        $points_total = isset($snapshot['points_total']) ? (int) $snapshot['points_total'] : 0;
    }

    if (class_exists('Sharity_Level_Manager')) {
        $level_manager = new Sharity_Level_Manager();
        $level = $level_manager->calculate_level_for_pseudo($pseudo_id);
        $config = $level_manager->get_level_config($level);
        $vote_weight_regular = (int) ($config['vote_ad'] ?? IMPACTSHOP_ADS_VOTES_REGULAR);
        $vote_weight_sponsor = (int) ($config['vote_sponsor'] ?? IMPACTSHOP_ADS_VOTES_SPONSOR);
        $donation_multiplier = isset($config['multiplier']) ? (float) $config['multiplier'] : $donation_multiplier;
    }

    $selected_slug = impactshop_ads_watch_get_user_ngo_slug($pseudo_id);
    if ($selected_slug === '' && function_exists('impactshop_identity_profile_last_ngo')) {
        $selected_slug = (string) impactshop_identity_profile_last_ngo($pseudo_id);
    }

    $selected_ngo = $selected_slug !== '' ? impactshop_ads_watch_get_ngo_by_slug($selected_slug) : null;

    $today_views = impactshop_ads_watch_get_daily_views($pseudo_id);
    $available_votes = impactshop_ads_watch_get_user_votes($pseudo_id);
    $stats = impactshop_ads_watch_get_user_stats($pseudo_id);
    $achievements = impactshop_ads_watch_get_achievements($stats);

    return new WP_REST_Response([
        'has_identity'       => true,
        'pseudo_id'          => $pseudo_id,
        'points'             => $points_total,
        'level'              => $level,
        'vote_weight_regular'=> $vote_weight_regular,
        'vote_weight_sponsor'=> $vote_weight_sponsor,
        'donation_multiplier'=> $donation_multiplier,
        'selected_ngo'       => $selected_ngo,
        'today_views'        => $today_views,
        'available_votes'    => $available_votes,
        'stats'              => $stats,
        'achievements'       => $achievements,
    ], 200);
}

function impactshop_ads_watch_view(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_identity',
            'message' => 'Hiányzó azonosító.',
        ], 401);
    }

    $ip = impactshop_ads_watch_get_client_ip();
    $rate_pseudo = impactshop_ads_watch_rate_limit_check(
        'ads_watch_view_pseudo:' . $pseudo_id,
        IMPACTSHOP_ADS_RATE_LIMIT_VIEW_PER_MIN,
        60,
        false
    );
    $rate_ip = impactshop_ads_watch_rate_limit_check(
        'ads_watch_view_ip:' . $ip,
        IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN,
        60,
        false
    );

    if (!$rate_pseudo['allowed'] || !$rate_ip['allowed']) {
        $headers = array_merge(
            impactshop_ads_watch_rate_limit_headers($rate_pseudo),
            impactshop_ads_watch_rate_limit_headers($rate_ip)
        );
        $headers['Retry-After'] = (string) max(1, ($rate_pseudo['reset'] ?? time()) - time());
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'rate_limited',
            'message' => 'Túl sok kérés. Próbáld meg később.',
        ], 429, $headers);
    }

    impactshop_ads_watch_rate_limit_check(
        'ads_watch_view_pseudo:' . $pseudo_id,
        IMPACTSHOP_ADS_RATE_LIMIT_VIEW_PER_MIN,
        60
    );
    impactshop_ads_watch_rate_limit_check(
        'ads_watch_view_ip:' . $ip,
        IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN,
        60
    );

    $ad_type = sanitize_text_field($request->get_param('ad_type') ?? 'regular');
    if (!in_array($ad_type, ['regular', 'sponsor', 'auto_banner', 'education'], true)) {
        $ad_type = 'regular';
    }

    $sponsor_id = absint($request->get_param('sponsor_id'));
    if ($sponsor_id > 0) {
        $ad_type = 'sponsor';
        if (!impactshop_ads_watch_can_view_sponsor($pseudo_id, $sponsor_id)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'sponsor_unavailable',
                'message' => 'A szponzor videó jelenleg nem elérhető.',
            ], 409);
        }
    }

    $ngo_slug = impactshop_ads_watch_get_user_ngo_slug($pseudo_id);
    $ngo_slug = ($ngo_slug !== '') ? $ngo_slug : null;

    // Determine points and votes based on ad type
    switch ($ad_type) {
        case 'sponsor':
            $points = IMPACTSHOP_ADS_POINTS_SPONSOR;
            $votes_added = IMPACTSHOP_ADS_VOTES_SPONSOR;
            break;
        case 'auto_banner':
            $points = IMPACTSHOP_ADS_POINTS_BANNER;
            $votes_added = IMPACTSHOP_ADS_VOTES_BANNER;
            break;
        case 'education':
            // Education has its own interval-based rewards, but view complete gives base amount
            $points = IMPACTSHOP_ADS_POINTS_REGULAR;
            $votes_added = IMPACTSHOP_ADS_VOTES_REGULAR;
            break;
        default:
            $points = IMPACTSHOP_ADS_POINTS_REGULAR;
            $votes_added = IMPACTSHOP_ADS_VOTES_REGULAR;
    }

    $completion_ratio = (float) ($request->get_param('completion_ratio') ?? 1);
    if ($completion_ratio < 1 && $completion_ratio >= 0.75) {
        $points = max(1, (int) floor($points / 2));
        $votes_added = max(1, (int) floor($votes_added / 2));
    }

    $stats = impactshop_ads_watch_get_user_stats($pseudo_id);
    if ($ad_type !== 'sponsor') {
        $streak_multiplier = impactshop_ads_watch_get_streak_multiplier((int) ($stats['streak_days'] ?? 0));
        $points = max(1, (int) floor($points * $streak_multiplier));
        $votes_added = max(1, (int) floor($votes_added * $streak_multiplier));
    }
    if (!impactshop_ads_is_quarter_active_now()) {
        $votes_added = 0;
    }

    $day_key = current_time('Y-m-d');
    $block = (int) floor(time() / 5);
    $dedupe_key = sprintf('ads_watch:%s:%d:%s:%s:%s', $ad_type, $sponsor_id, $pseudo_id, $day_key, $block);

    global $wpdb;
    $table_views = $wpdb->prefix . 'impactshop_ads_views';
    $inserted = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table_views}
            (pseudo_id, ngo_slug, sponsor_id, ad_type, points, vote_weight, dedupe_key, day_key, created_at)
         VALUES (%s, %s, NULLIF(%d, 0), %s, %d, %d, %s, %s, %s)",
        $pseudo_id,
        $ngo_slug,
        $sponsor_id,
        $ad_type,
        $points,
        $votes_added,
        $dedupe_key,
        $day_key,
        current_time('mysql')
    ));

    if ($inserted === false) {
        impactshop_ads_watch_log('error', 'ads_watch_view_insert_failed', [
            'pseudo_id' => $pseudo_id,
            'sponsor_id' => $sponsor_id,
            'ad_type' => $ad_type,
            'error' => $wpdb->last_error,
        ]);
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'db_error',
            'message' => 'Adatrögzítési hiba.',
        ], 500);
    }

    if ($inserted === 0) {
        return new WP_REST_Response([
            'success'   => true,
            'duplicate' => true,
            'points'    => 0,
            'votes'     => 0,
        ], 200);
    }

    $points_result = ['success' => false, 'new_total' => null];
    if (class_exists('Sharity_Points_Manager')) {
        $points_manager = new Sharity_Points_Manager();
        $points_result = $points_manager->award_points_for_pseudo(
            $pseudo_id,
            $points,
            $ad_type === 'sponsor' ? 'video_sponsor' : 'video_ad',
            'ads_watch',
            [
                'source_type' => $ad_type === 'sponsor' ? 'sponsor_ad' : 'ad',
                'ngo_slug' => $ngo_slug,
                'sponsor_id' => $sponsor_id > 0 ? $sponsor_id : null,
            ],
            'ads_watch:' . $dedupe_key
        );
    }

    $available_votes = impactshop_ads_watch_add_votes($pseudo_id, $votes_added);
    $stats = impactshop_ads_watch_update_user_stats($pseudo_id, $votes_added, $day_key);

    impactshop_ads_watch_mark_tally_dirty();
    impactshop_ads_watch_set_rotation_state($pseudo_id, $ad_type);
    do_action('impactshop_ads_view_recorded', $pseudo_id, [
        'ad_type' => $ad_type,
        'stats'   => $stats,
    ]);

    impactshop_ads_watch_track_ga4('ads_watch_view_complete', [
        'ad_type' => $ad_type,
        'sponsor_id' => $sponsor_id,
        'points' => $points,
        'votes' => $votes_added,
        'streak_days' => (int) ($stats['streak_days'] ?? 0),
    ]);

    return new WP_REST_Response([
        'success'     => true,
        'points'      => $points,
        'votes'       => $votes_added,
        'available_votes' => $available_votes,
        'new_total'   => $points_result['new_total'] ?? null,
        'ad_type'     => $ad_type,
        'stats'       => $stats,
    ], 200);
}

function impactshop_ads_watch_education(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_identity',
            'message' => 'Hiányzó azonosító.',
        ], 401);
    }

    $ip = impactshop_ads_watch_get_client_ip();
    $rate_pseudo = impactshop_ads_watch_rate_limit_check(
        'ads_watch_education_pseudo:' . $pseudo_id,
        IMPACTSHOP_ADS_RATE_LIMIT_EDU_PER_MIN,
        60,
        false
    );
    $rate_ip = impactshop_ads_watch_rate_limit_check(
        'ads_watch_education_ip:' . $ip,
        IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN,
        60,
        false
    );

    if (!$rate_pseudo['allowed'] || !$rate_ip['allowed']) {
        $headers = array_merge(
            impactshop_ads_watch_rate_limit_headers($rate_pseudo),
            impactshop_ads_watch_rate_limit_headers($rate_ip)
        );
        $headers['Retry-After'] = (string) max(1, ($rate_pseudo['reset'] ?? time()) - time());
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'rate_limited',
            'message' => 'Túl sok kérés. Próbáld meg később.',
        ], 429, $headers);
    }

    impactshop_ads_watch_rate_limit_check(
        'ads_watch_education_pseudo:' . $pseudo_id,
        IMPACTSHOP_ADS_RATE_LIMIT_EDU_PER_MIN,
        60
    );
    impactshop_ads_watch_rate_limit_check(
        'ads_watch_education_ip:' . $ip,
        IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN,
        60
    );

    $session_token = sanitize_text_field((string) $request->get_param('session_token'));
    if ($session_token === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_session',
            'message' => 'Hiányzó edukációs token.',
        ], 400);
    }

    $session = impactshop_ads_watch_get_education_session($session_token);
    if (empty($session) || !is_array($session)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_session',
            'message' => 'Lejárt vagy ismeretlen edukációs token.',
        ], 404);
    }

    if (($session['pseudo_id'] ?? '') !== $pseudo_id) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'session_mismatch',
            'message' => 'A token nem ehhez a felhasználóhoz tartozik.',
        ], 403);
    }

    $expected_hash = wp_hash($pseudo_id . '|' . (string) ($session['content_id'] ?? '') . '|' . (string) ($session['created_at'] ?? ''));
    if (!hash_equals($expected_hash, (string) ($session['session_hash'] ?? ''))) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_session',
            'message' => 'Érvénytelen edukációs token.',
        ], 403);
    }

    $watched_seconds = absint($request->get_param('watched_seconds'));
    $intervals = absint($request->get_param('intervals'));
    $interval_seconds = max(1, (int) ($session['interval_seconds'] ?? IMPACTSHOP_ADS_EDU_INTERVAL_SECONDS));
    if ($intervals <= 0) {
        if ($watched_seconds > 0) {
            $intervals = (int) floor($watched_seconds / $interval_seconds);
        }
    }

    $intervals = max(0, $intervals);
    $max_intervals = (int) ($session['max_intervals'] ?? 0);
    $awarded = (int) ($session['intervals_awarded'] ?? 0);
    $points_per_interval = max(0, (int) ($session['points_per_interval'] ?? IMPACTSHOP_ADS_EDU_POINTS_PER_INTERVAL));
    $votes_per_interval = max(0, (int) ($session['votes_per_interval'] ?? IMPACTSHOP_ADS_EDU_VOTES_PER_INTERVAL));
    $bonus_points = max(0, (int) ($session['bonus_points'] ?? 0));
    $bonus_votes = max(0, (int) ($session['bonus_votes'] ?? 0));
    $duration_seconds = max(0, (int) ($session['duration_seconds'] ?? 0));

    if ($max_intervals > 0) {
        $intervals = min($intervals, max(0, $max_intervals - $awarded));
    }

    if (!impactshop_ads_is_quarter_active_now()) {
        $votes_per_interval = 0;
        $bonus_votes = 0;
    }

    if ($intervals <= 0) {
        return new WP_REST_Response([
            'success' => true,
            'duplicate' => true,
            'points' => 0,
            'votes' => 0,
            'available_votes' => impactshop_ads_watch_get_user_votes($pseudo_id),
        ], 200);
    }

    $content_id = (string) ($session['content_id'] ?? '');
    $day_key = current_time('Y-m-d');
    $points_total = 0;
    $votes_total = 0;

    global $wpdb;
    $table_education = $wpdb->prefix . 'impactshop_education_views';

    $inserted = 0;
    for ($i = 1; $i <= $intervals; $i++) {
        $dedupe_key = sprintf('ads_watch_edu:%s:%d', $session_token, $awarded + $i);
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table_education}
                (pseudo_id, content_id, content_type, watched_seconds, points_earned, votes_earned, intervals_completed, session_token, dedupe_key, day_key, created_at)
             VALUES (%s, %s, %s, %d, %d, %d, %d, %s, %s, %s, %s)",
            $pseudo_id,
            $content_id,
            'education',
            $intervals * $interval_seconds,
            $points_per_interval,
            $votes_per_interval,
            1,
            $session_token,
            $dedupe_key,
            $day_key,
            current_time('mysql')
        ));
        if ($result !== false && $result > 0) {
            $inserted++;
        }
    }

    if ($inserted <= 0) {
        return new WP_REST_Response([
            'success' => true,
            'duplicate' => true,
            'points' => 0,
            'votes' => 0,
            'available_votes' => impactshop_ads_watch_get_user_votes($pseudo_id),
        ], 200);
    }

    $points_total = $inserted * $points_per_interval;
    $votes_total = $inserted * $votes_per_interval;

    $session['intervals_awarded'] = $awarded + $inserted;
    $session_intervals_total = (int) $session['intervals_awarded'];
    $bonus_applies = false;
    if (empty($session['bonus_awarded'])) {
        if ($duration_seconds > 0 && $watched_seconds >= (int) floor($duration_seconds * 0.95)) {
            $bonus_applies = true;
        } elseif ($max_intervals > 0 && $session_intervals_total >= $max_intervals) {
            $bonus_applies = true;
        }
    }
    if ($bonus_applies && ($bonus_points > 0 || $bonus_votes > 0)) {
        $points_total += $bonus_points;
        $votes_total += $bonus_votes;
        $session['bonus_awarded'] = true;
    }
    impactshop_ads_watch_update_education_session($session_token, $session);

    $points_result = ['success' => false, 'new_total' => null];
    if (class_exists('Sharity_Points_Manager')) {
        $points_manager = new Sharity_Points_Manager();
        $points_result = $points_manager->award_points_for_pseudo(
            $pseudo_id,
            $points_total,
            'video_sponsor',
            'ads_watch',
            [
                'source_type' => 'education_video',
                'content_id' => $content_id,
                'bonus_awarded' => !empty($session['bonus_awarded']),
            ],
            'ads_watch_edu:' . $session_token . ':' . ($awarded + $inserted)
        );
    }

    $available_votes = impactshop_ads_watch_add_votes($pseudo_id, $votes_total);
    $stats = impactshop_ads_watch_update_user_stats($pseudo_id, $votes_total, $day_key);

    impactshop_ads_watch_mark_tally_dirty();
    impactshop_ads_watch_set_rotation_state($pseudo_id, 'education');
    do_action('impactshop_ads_view_recorded', $pseudo_id, [
        'ad_type' => 'education',
        'stats'   => $stats,
    ]);
    do_action('impactshop_edu_video_completed', $pseudo_id, [
        'content_id' => (string) ($session['content_id'] ?? ''),
        'points' => $points_total,
        'votes' => $votes_total,
        'intervals' => $inserted,
        'earned_at' => current_time('mysql'),
    ]);

    impactshop_ads_watch_track_ga4('ads_watch_education_complete', [
        'content_id' => $content_id,
        'points' => $points_total,
        'votes' => $votes_total,
        'intervals' => $inserted,
    ]);

    return new WP_REST_Response([
        'success' => true,
        'points' => $points_total,
        'votes' => $votes_total,
        'intervals_awarded' => $session['intervals_awarded'],
        'bonus_awarded' => !empty($session['bonus_awarded']),
        'available_votes' => $available_votes,
        'new_total' => $points_result['new_total'] ?? null,
        'stats' => $stats,
    ], 200);
}

function impactshop_ads_watch_set_ngo(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_identity',
            'message' => 'Hiányzó azonosító.',
        ], 401);
    }

    $ngo_slug = sanitize_title((string) $request->get_param('ngo_slug'));
    if ($ngo_slug === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_ngo',
            'message' => 'Érvénytelen NGO azonosító.',
        ], 400);
    }

    $ngo = impactshop_ads_watch_get_ngo_by_slug($ngo_slug);
    if (!$ngo) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'ngo_not_found',
            'message' => 'A megadott NGO nem található.',
        ], 404);
    }

    global $wpdb;
    $table_prefs = $wpdb->prefix . 'impactshop_ads_user_ngo';
    $wpdb->replace($table_prefs, [
        'pseudo_id' => $pseudo_id,
        'ngo_slug'  => $ngo_slug,
    ], ['%s', '%s']);

    if (function_exists('impactshop_identity_profile_store_last_ngo')) {
        impactshop_identity_profile_store_last_ngo($pseudo_id, $ngo_slug);
    }

    return new WP_REST_Response([
        'success' => true,
        'ngo'     => $ngo,
    ], 200);
}

function impactshop_ads_watch_allocate_votes(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_ads_watch_get_pseudo_from_request($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_identity',
            'message' => 'Hiányzó azonosító.',
        ], 401);
    }

    $ip = impactshop_ads_watch_get_client_ip();
    $rate_pseudo = impactshop_ads_watch_rate_limit_check(
        'ads_watch_allocate_pseudo:' . $pseudo_id,
        IMPACTSHOP_ADS_RATE_LIMIT_ALLOCATE_PER_MIN,
        60,
        false
    );
    $rate_ip = impactshop_ads_watch_rate_limit_check(
        'ads_watch_allocate_ip:' . $ip,
        IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN,
        60,
        false
    );

    if (!$rate_pseudo['allowed'] || !$rate_ip['allowed']) {
        $headers = array_merge(
            impactshop_ads_watch_rate_limit_headers($rate_pseudo),
            impactshop_ads_watch_rate_limit_headers($rate_ip)
        );
        $headers['Retry-After'] = (string) max(1, ($rate_pseudo['reset'] ?? time()) - time());
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'rate_limited',
            'message' => 'Túl sok kérés. Próbáld meg később.',
        ], 429, $headers);
    }

    impactshop_ads_watch_rate_limit_check(
        'ads_watch_allocate_pseudo:' . $pseudo_id,
        IMPACTSHOP_ADS_RATE_LIMIT_ALLOCATE_PER_MIN,
        60
    );
    impactshop_ads_watch_rate_limit_check(
        'ads_watch_allocate_ip:' . $ip,
        IMPACTSHOP_ADS_RATE_LIMIT_IP_PER_MIN,
        60
    );

    if (!impactshop_ads_is_quarter_active_now()) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'quarter_not_started',
            'message' => 'A szavazás még nem indult el.',
        ], 409);
    }

    if (impactshop_ads_is_quarter_locked()) {
        $retry_after = impactshop_ads_get_quarter_lock_retry_after();
        return new WP_REST_Response([
            'success' => false,
            'error' => 'quarter_lock',
            'message' => 'Negyedéves zárás folyamatban, kérjük próbáld meg később.',
        ], 503, [
            'Retry-After' => (string) $retry_after,
        ]);
    }

    $ngo_slug = sanitize_title((string) $request->get_param('ngo_slug'));
    $votes = absint($request->get_param('votes'));
    if ($ngo_slug === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'ngo_required',
            'message' => 'Először válassz egy NGO-t!',
        ], 400);
    }
    if ($votes <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_votes',
            'message' => 'Adj meg pozitív szavazatszámot.',
        ], 400);
    }

    $selected = impactshop_ads_watch_get_user_ngo_slug($pseudo_id);
    if ($selected === '' || $selected !== $ngo_slug) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'ngo_mismatch',
            'message' => 'A kiválasztott NGO nem egyezik.',
        ], 409);
    }

    $spend = impactshop_ads_watch_spend_votes($pseudo_id, $votes);
    if (!$spend['ok']) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'insufficient_votes',
            'message' => 'Nincs elég szavazatod.',
            'remaining' => $spend['remaining'],
        ], 409);
    }

    $weight_info = impactshop_ads_watch_get_vote_weight_info($pseudo_id, 'regular');
    $base_weight = (int) $weight_info['base_weight'];
    $donation_multiplier = (float) $weight_info['multiplier'];
    $weighted_votes = (int) round($votes * $base_weight * $donation_multiplier);
    $weighted_votes = max(1, $weighted_votes);

    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
    $quarter_key = impactshop_ads_get_active_quarter();
    $wpdb->insert($table_votes, [
        'pseudo_id' => $pseudo_id,
        'ngo_slug' => $ngo_slug,
        'vote_weight' => $weighted_votes,
        'base_weight' => $base_weight,
        'donation_multiplier' => $donation_multiplier,
        'ad_type' => 'allocation',
        'quarter_key' => $quarter_key,
        'day_key' => current_time('Y-m-d'),
        'created_at' => current_time('mysql'),
    ], ['%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s']);

    if ($wpdb->last_error) {
        impactshop_ads_watch_log('error', 'ads_watch_vote_insert_failed', [
            'pseudo_id' => $pseudo_id,
            'ngo_slug' => $ngo_slug,
            'error' => $wpdb->last_error,
        ]);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'db_error',
            'message' => 'Nem sikerült rögzíteni a szavazatot.',
        ], 500);
    }

    impactshop_ads_watch_mark_tally_dirty();

    impactshop_ads_watch_track_ga4('ads_watch_vote_cast', [
        'ngo_slug' => $ngo_slug,
        'votes' => $votes,
        'weighted_votes' => $weighted_votes,
    ]);

    return new WP_REST_Response([
        'success' => true,
        'remaining_votes' => $spend['remaining'],
        'votes_spent' => $votes,
        'weighted_votes' => $weighted_votes,
    ], 200);
}

function impactshop_ads_watch_tally(WP_REST_Request $request): WP_REST_Response
{
    $limit = absint($request->get_param('limit') ?? 10);
    $limit = min($limit, 100);
    $quarter_key = sanitize_text_field($request->get_param('quarter') ?? '');
    $lifetime = (bool) $request->get_param('lifetime');
    if ($quarter_key === '' && !$lifetime) {
        $quarter_key = impactshop_ads_get_active_quarter();
    }

    $cache_key = 'impactshop_ads_tally_cache' . ($quarter_key ? ('_' . $quarter_key) : '');
    $dirty_key = 'impactshop_ads_tally_dirty';
    $lock_key = 'impactshop_ads_tally_lock';
    $dirty_at = (int) get_transient($dirty_key);
    $cached = get_transient($cache_key);

    if ($cached !== false && !$request->get_param('nocache')) {
        if ($dirty_at > 0 && (time() - $dirty_at) > IMPACTSHOP_ADS_TALLY_DIRTY_TTL) {
            $cached = false;
            delete_transient($cache_key);
            delete_transient($dirty_key);
        }
    }

    if ($cached !== false && !$request->get_param('nocache')) {
        $tally = $cached;
    } else {
        if (get_transient($lock_key)) {
            $tally = $cached !== false ? $cached : [];
        } else {
            set_transient($lock_key, 1, 10);
            $tally = impactshop_ads_calculate_tally_with_info($quarter_key ?: null);
            set_transient($cache_key, $tally, IMPACTSHOP_ADS_TALLY_CACHE_TTL);
            delete_transient($lock_key);
        }
    }

    $total_votes = array_sum(array_column($tally, 'votes'));
    $donation_pool = $quarter_key !== '' ? impactshop_ads_get_pool_for_quarter($quarter_key) : IMPACTSHOP_ADS_DONATION_POOL;

    $result = [];
    foreach (array_slice($tally, 0, $limit) as $rank => $row) {
        $percentage = $total_votes > 0
            ? round(($row['votes'] / $total_votes) * 100, 2)
            : 0;
        $amount = $total_votes > 0
            ? round(($row['votes'] / $total_votes) * $donation_pool)
            : 0;

        $result[] = [
            'rank'       => $rank + 1,
            'ngo_slug'   => $row['ngo_slug'],
            'ngo_name'   => $row['ngo_name'] ?? $row['ngo_slug'],
            'ngo_logo'   => $row['ngo_logo'] ?? '',
            'votes'      => (int) $row['votes'],
            'percentage' => $percentage,
            'amount'     => $amount,
        ];
    }

    return new WP_REST_Response([
        'donation_pool' => $donation_pool,
        'total_votes'   => $total_votes,
        'quarter_key'   => $quarter_key ?: null,
        'tally'         => $result,
        'cached_at'     => $cached !== false ? 'cached' : 'fresh',
    ], 200);
}

function impactshop_ads_watch_leaderboard(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;
    $limit = absint($request->get_param('limit') ?? 10);
    $limit = min(max(1, $limit), 50);
    $quarter_key = sanitize_text_field($request->get_param('quarter') ?? '');
    $lifetime = (bool) $request->get_param('lifetime');
    if ($quarter_key === '' && !$lifetime) {
        $quarter_key = impactshop_ads_get_active_quarter();
    }

    $table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';
    if ($lifetime || $quarter_key === '') {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pseudo_id, total_views, total_votes, streak_days
                 FROM {$table_stats}
                 ORDER BY total_votes DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    } else {
        $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.pseudo_id,
                        SUM(v.vote_weight) AS quarter_votes,
                        COALESCE(s.total_views, 0) AS total_views,
                        COALESCE(s.total_votes, 0) AS lifetime_votes,
                        COALESCE(s.streak_days, 0) AS streak_days
                 FROM {$table_votes} v
                 LEFT JOIN {$table_stats} s ON s.pseudo_id = v.pseudo_id
                 WHERE v.quarter_key = %s
                 GROUP BY v.pseudo_id
                 ORDER BY quarter_votes DESC
                 LIMIT %d",
                $quarter_key,
                $limit
            ),
            ARRAY_A
        );
    }

    $leaderboard = [];
    foreach ($rows as $row) {
        $pseudo = (string) ($row['pseudo_id'] ?? '');
        $leaderboard[] = [
            'display_id' => $pseudo ? substr($pseudo, 0, 4) . '***' : 'anon',
            'total_views' => (int) ($row['total_views'] ?? 0),
            'total_votes' => (int) ($row['quarter_votes'] ?? $row['total_votes'] ?? 0),
            'lifetime_votes' => (int) ($row['lifetime_votes'] ?? $row['total_votes'] ?? 0),
            'streak_days' => (int) ($row['streak_days'] ?? 0),
        ];
    }

    return new WP_REST_Response([
        'quarter_key' => $lifetime ? null : $quarter_key,
        'leaderboard' => $leaderboard,
    ], 200);
}

function impactshop_ads_watch_ngos(WP_REST_Request $request): WP_REST_Response
{
    $search = sanitize_text_field($request->get_param('search') ?? '');
    $limit = absint($request->get_param('limit') ?? 5000);
    if ($limit === 0) {
        $limit = 5000;
    }
    $limit = min($limit, 5000);

    $ngos = impactshop_ads_watch_get_ngo_list($search, $limit);

    return new WP_REST_Response([
        'ngos'  => $ngos,
        'count' => count($ngos),
    ], 200);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function impactshop_ads_watch_get_pseudo_from_request(WP_REST_Request $request): string
{
    $pseudo = (string) $request->get_param('pseudo_id');
    if ($pseudo === '' && isset($_COOKIE['impactshop_pseudo_id'])) {
        $pseudo = (string) $_COOKIE['impactshop_pseudo_id'];
    }

    return impactshop_ads_watch_normalize_pseudo_id($pseudo);
}

function impactshop_ads_watch_normalize_pseudo_id(string $pseudo_id): string
{
    if (function_exists('sharity_normalize_pseudo_id')) {
        return sharity_normalize_pseudo_id($pseudo_id);
    }

    $pseudo_id = strtolower(sanitize_text_field($pseudo_id));
    if ($pseudo_id === '') {
        return '';
    }

    if (function_exists('impactshop_identity_profile_valid_pseudo')) {
        return impactshop_identity_profile_valid_pseudo($pseudo_id) ? $pseudo_id : '';
    }

    return preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id) ? $pseudo_id : '';
}

function impactshop_ads_get_custom_quarter_key_for_timestamp(int $timestamp): string
{
    $custom_start = gmmktime(0, 0, 0, 3, 1, 2026);
    $custom_end = gmmktime(23, 59, 59, 6, 30, 2026);
    if ($timestamp >= $custom_start && $timestamp <= $custom_end) {
        return '2026Q1';
    }
    return '';
}

function impactshop_ads_get_current_quarter_key(?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $custom = impactshop_ads_get_custom_quarter_key_for_timestamp($timestamp);
    if ($custom !== '') {
        return $custom;
    }
    $year = (int) gmdate('Y', $timestamp);
    $month = (int) gmdate('n', $timestamp);
    $quarter = (int) ceil($month / 3);
    return sprintf('%dQ%d', $year, $quarter);
}

function impactshop_ads_get_quarter_bounds(string $quarter_key): array
{
    if ($quarter_key === '2026Q1') {
        return [
            'start_at' => '2026-03-01 00:00:00',
            'end_at' => '2026-06-30 23:59:59',
        ];
    }
    if (!preg_match('/^(\\d{4})Q([1-4])$/', $quarter_key, $matches)) {
        $quarter_key = impactshop_ads_get_current_quarter_key();
        preg_match('/^(\\d{4})Q([1-4])$/', $quarter_key, $matches);
    }
    $year = (int) $matches[1];
    $quarter = (int) $matches[2];
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3 + 1;
    $start_at = gmdate('Y-m-d 00:00:00', gmmktime(0, 0, 0, $start_month, 1, $year));
    $end_at = gmdate('Y-m-d 23:59:59', gmmktime(0, 0, 0, $end_month, 0, $year));

    return [
        'start_at' => $start_at,
        'end_at' => $end_at,
    ];
}

function impactshop_ads_get_active_quarter_window(): array
{
    $quarter_key = impactshop_ads_get_active_quarter();
    $bounds = impactshop_ads_get_quarter_bounds($quarter_key);
    global $wpdb;
    $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT start_at, end_at FROM {$table_quarters} WHERE quarter_key = %s LIMIT 1",
        $quarter_key
    ), ARRAY_A);
    if (!empty($row['start_at'])) {
        $bounds['start_at'] = (string) $row['start_at'];
    }
    if (!empty($row['end_at'])) {
        $bounds['end_at'] = (string) $row['end_at'];
    }
    $start_ts = $bounds['start_at'] !== '' ? strtotime($bounds['start_at']) : 0;
    $end_ts = $bounds['end_at'] !== '' ? strtotime($bounds['end_at']) : 0;

    return [
        'quarter_key' => $quarter_key,
        'start_at' => $bounds['start_at'] ?? '',
        'end_at' => $bounds['end_at'] ?? '',
        'start_ts' => $start_ts ?: 0,
        'end_ts' => $end_ts ?: 0,
    ];
}

function impactshop_ads_is_quarter_active_now(): bool
{
    $window = impactshop_ads_get_active_quarter_window();
    $now = time();
    if (!empty($window['start_ts']) && $now < (int) $window['start_ts']) {
        return false;
    }
    if (!empty($window['end_ts']) && $now > (int) $window['end_ts']) {
        return false;
    }
    return true;
}

function impactshop_ads_get_active_quarter(): string
{
    $quarter_key = (string) get_option('impactshop_ads_current_quarter', '');
    if ($quarter_key === '') {
        $quarter_key = impactshop_ads_get_current_quarter_key();
        update_option('impactshop_ads_current_quarter', $quarter_key, false);
    }

    global $wpdb;
    $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT quarter_key FROM {$table_quarters} WHERE quarter_key = %s LIMIT 1",
        $quarter_key
    ));
    if (!$exists) {
        $bounds = impactshop_ads_get_quarter_bounds($quarter_key);
        $wpdb->insert($table_quarters, [
            'quarter_key' => $quarter_key,
            'start_at' => $bounds['start_at'],
            'end_at' => $bounds['end_at'],
            'pool_amount' => IMPACTSHOP_ADS_DONATION_POOL,
            'status' => 'active',
        ], ['%s', '%s', '%s', '%d', '%s']);
    }

    return $quarter_key;
}

function impactshop_ads_get_pool_for_quarter(string $quarter_key): int
{
    if ($quarter_key === '') {
        return IMPACTSHOP_ADS_DONATION_POOL;
    }
    global $wpdb;
    $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';
    $pool = $wpdb->get_var($wpdb->prepare(
        "SELECT pool_amount FROM {$table_quarters} WHERE quarter_key = %s LIMIT 1",
        $quarter_key
    ));
    if ($pool === null) {
        return IMPACTSHOP_ADS_DONATION_POOL;
    }
    return (int) $pool;
}

function impactshop_ads_get_active_pool(): int
{
    $quarter_key = impactshop_ads_get_active_quarter();
    return impactshop_ads_get_pool_for_quarter($quarter_key);
}

function impactshop_ads_is_quarter_locked(): bool
{
    $lock = get_option('impactshop_ads_quarter_lock');
    if (!is_array($lock)) {
        return false;
    }
    $expires_at = (int) ($lock['expires_at'] ?? 0);
    if ($expires_at > 0 && $expires_at < time()) {
        delete_option('impactshop_ads_quarter_lock');
        return false;
    }
    return true;
}

function impactshop_ads_set_quarter_lock(int $ttl = 60): void
{
    update_option('impactshop_ads_quarter_lock', [
        'locked_at' => time(),
        'expires_at' => time() + max(30, $ttl),
    ], false);
}

function impactshop_ads_clear_quarter_lock(): void
{
    delete_option('impactshop_ads_quarter_lock');
}

function impactshop_ads_get_quarter_lock_retry_after(): int
{
    $lock = get_option('impactshop_ads_quarter_lock');
    if (!is_array($lock)) {
        return 60;
    }
    $expires_at = (int) ($lock['expires_at'] ?? 0);
    if ($expires_at <= 0) {
        return 60;
    }
    return max(1, $expires_at - time());
}

function impactshop_ads_watch_get_user_ngo_slug(string $pseudo_id): string
{
    if ($pseudo_id === '') {
        return '';
    }

    global $wpdb;
    $table_prefs = $wpdb->prefix . 'impactshop_ads_user_ngo';
    $slug = $wpdb->get_var($wpdb->prepare(
        "SELECT ngo_slug FROM {$table_prefs} WHERE pseudo_id = %s",
        $pseudo_id
    ));

    return is_string($slug) ? $slug : '';
}

function impactshop_ads_watch_get_user_votes(string $pseudo_id): int
{
    if ($pseudo_id === '') {
        return 0;
    }

    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_user_votes';
    $votes = $wpdb->get_var($wpdb->prepare(
        "SELECT available_votes FROM {$table_votes} WHERE pseudo_id = %s",
        $pseudo_id
    ));

    if ($votes === null) {
        $wpdb->insert($table_votes, [
            'pseudo_id' => $pseudo_id,
            'available_votes' => 0,
        ], ['%s', '%d']);
        return 0;
    }

    return max(0, (int) $votes);
}

function impactshop_ads_watch_add_votes(string $pseudo_id, int $votes): int
{
    $votes = max(0, $votes);
    if ($votes === 0) {
        return impactshop_ads_watch_get_user_votes($pseudo_id);
    }
    if (!impactshop_ads_is_quarter_active_now()) {
        return impactshop_ads_watch_get_user_votes($pseudo_id);
    }

    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_user_votes';
    $current = impactshop_ads_watch_get_user_votes($pseudo_id);
    $new_total = $current + $votes;

    $wpdb->update($table_votes, [
        'available_votes' => $new_total,
    ], ['pseudo_id' => $pseudo_id], ['%d'], ['%s']);

    return $new_total;
}

function impactshop_ads_watch_get_user_stats(string $pseudo_id): array
{
    if ($pseudo_id === '') {
        return [
            'total_views' => 0,
            'total_votes' => 0,
            'streak_days' => 0,
            'last_view_day' => null,
        ];
    }

    global $wpdb;
    $table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT total_views, total_votes, streak_days, last_view_day FROM {$table_stats} WHERE pseudo_id = %s",
        $pseudo_id
    ), ARRAY_A);

    if (!$row) {
        $wpdb->insert($table_stats, [
            'pseudo_id' => $pseudo_id,
            'total_views' => 0,
            'total_votes' => 0,
            'streak_days' => 0,
            'last_view_day' => null,
        ], ['%s', '%d', '%d', '%d', '%s']);
        return [
            'total_views' => 0,
            'total_votes' => 0,
            'streak_days' => 0,
            'last_view_day' => null,
        ];
    }

    return [
        'total_views' => (int) ($row['total_views'] ?? 0),
        'total_votes' => (int) ($row['total_votes'] ?? 0),
        'streak_days' => (int) ($row['streak_days'] ?? 0),
        'last_view_day' => $row['last_view_day'] ?? null,
    ];
}

function impactshop_ads_watch_update_user_stats(string $pseudo_id, int $votes_added, string $day_key): array
{
    if ($pseudo_id === '') {
        return impactshop_ads_watch_get_user_stats($pseudo_id);
    }

    $stats = impactshop_ads_watch_get_user_stats($pseudo_id);
    $last_day = $stats['last_view_day'];
    $streak = $stats['streak_days'];

    if ($last_day === $day_key) {
        $streak = $streak > 0 ? $streak : 1;
    } else {
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
        if ($last_day === $yesterday) {
            $streak = $streak + 1;
        } else {
            $streak = 1;
        }
    }

    $stats['total_views'] = $stats['total_views'] + 1;
    $stats['total_votes'] = $stats['total_votes'] + max(0, $votes_added);
    $stats['streak_days'] = $streak;
    $stats['last_view_day'] = $day_key;

    global $wpdb;
    $table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';
    $wpdb->update($table_stats, [
        'total_views' => $stats['total_views'],
        'total_votes' => $stats['total_votes'],
        'streak_days' => $stats['streak_days'],
        'last_view_day' => $stats['last_view_day'],
    ], ['pseudo_id' => $pseudo_id], ['%d', '%d', '%d', '%s'], ['%s']);

    return $stats;
}

function impactshop_ads_watch_get_achievements(array $stats): array
{
    $achievements = [];
    $views = (int) ($stats['total_views'] ?? 0);
    $votes = (int) ($stats['total_votes'] ?? 0);
    $streak = (int) ($stats['streak_days'] ?? 0);

    if ($views >= 1) {
        $achievements[] = ['key' => 'first_view', 'label' => 'Első videó', 'description' => 'Megnézted az első videót.'];
    }
    if ($votes >= 1) {
        $achievements[] = ['key' => 'first_vote', 'label' => 'Első szavazat', 'description' => 'Leadott szavazat.'];
    }
    if ($views >= 10) {
        $achievements[] = ['key' => 'video_marathon', 'label' => 'Video Marathon', 'description' => '10 videó megtekintése.'];
    }
    if ($votes >= 100) {
        $achievements[] = ['key' => 'top_supporter', 'label' => 'Top Supporter', 'description' => '100 szavazat elérése.'];
    }
    if ($streak >= 7) {
        $achievements[] = ['key' => 'streak_7', 'label' => 'Heti streak', 'description' => '7 napos aktivitás.'];
    }

    return $achievements;
}

function impactshop_ads_watch_get_streak_multiplier(int $streak_days): float
{
    $multiplier = 1.0;
    if ($streak_days >= 30) {
        $multiplier = 1.30;
    } elseif ($streak_days >= 14) {
        $multiplier = 1.20;
    } elseif ($streak_days >= 7) {
        $multiplier = 1.10;
    }

    return (float) apply_filters('impactshop_ads_watch_streak_multiplier', $multiplier, $streak_days);
}

function impactshop_ads_watch_spend_votes(string $pseudo_id, int $votes): array
{
    $votes = max(0, $votes);
    if ($votes === 0) {
        return ['ok' => false, 'remaining' => impactshop_ads_watch_get_user_votes($pseudo_id)];
    }

    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_user_votes';
    $wpdb->query('START TRANSACTION');

    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT available_votes FROM {$table_votes} WHERE pseudo_id = %s FOR UPDATE",
        $pseudo_id
    ));

    if ($current === null) {
        $wpdb->query('ROLLBACK');
        return ['ok' => false, 'remaining' => 0];
    }

    $current = (int) $current;
    if ($current < $votes) {
        $wpdb->query('ROLLBACK');
        return ['ok' => false, 'remaining' => $current];
    }

    $remaining = $current - $votes;
    $updated = $wpdb->update($table_votes, [
        'available_votes' => $remaining,
    ], ['pseudo_id' => $pseudo_id], ['%d'], ['%s']);

    if ($updated === false) {
        $wpdb->query('ROLLBACK');
        return ['ok' => false, 'remaining' => $current];
    }

    $wpdb->query('COMMIT');

    return ['ok' => true, 'remaining' => $remaining];
}

function impactshop_ads_watch_get_daily_views(string $pseudo_id): int
{
    if ($pseudo_id === '') {
        return 0;
    }

    global $wpdb;
    $table_views = $wpdb->prefix . 'impactshop_ads_views';
    $day_key = current_time('Y-m-d');

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_views} WHERE pseudo_id = %s AND day_key = %s",
        $pseudo_id,
        $day_key
    ));
}

function impactshop_ads_watch_get_vote_weight_info(string $pseudo_id, string $ad_type): array
{
    $base_weight = $ad_type === 'sponsor' ? IMPACTSHOP_ADS_VOTES_SPONSOR : IMPACTSHOP_ADS_VOTES_REGULAR;
    $multiplier = 1.0;

    if (class_exists('Sharity_Level_Manager')) {
        $level_manager = new Sharity_Level_Manager();
        $level = $level_manager->calculate_level_for_pseudo($pseudo_id);
        $config = $level_manager->get_level_config($level);
        $base_weight = (int) ($ad_type === 'sponsor' ? ($config['vote_sponsor'] ?? $base_weight) : ($config['vote_ad'] ?? $base_weight));
        $multiplier = isset($config['multiplier']) ? (float) $config['multiplier'] : $multiplier;
    }

    $base_weight = max(1, $base_weight);
    $multiplier = max(1.0, $multiplier);
    $weighted = (int) round($base_weight * $multiplier);

    return [
        'base_weight' => $base_weight,
        'multiplier' => $multiplier,
        'weighted_weight' => max(1, $weighted),
    ];
}

function impactshop_ads_watch_get_vote_weight(string $pseudo_id, string $ad_type): int
{
    $info = impactshop_ads_watch_get_vote_weight_info($pseudo_id, $ad_type);
    return (int) $info['base_weight'];
}

function impactshop_ads_calculate_tally(?string $quarter_key = null): array
{
    global $wpdb;
    $table_votes = $wpdb->prefix . 'impactshop_ads_votes';

    if ($quarter_key === null || $quarter_key === '') {
        $results = $wpdb->get_results(
            "SELECT ngo_slug, SUM(vote_weight) as votes
             FROM {$table_votes}
             GROUP BY ngo_slug
             ORDER BY votes DESC",
            ARRAY_A
        );
    } else {
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ngo_slug, SUM(vote_weight) as votes
                 FROM {$table_votes}
                 WHERE quarter_key = %s
                 GROUP BY ngo_slug
                 ORDER BY votes DESC",
                $quarter_key
            ),
            ARRAY_A
        );
    }

    return $results ?: [];
}

function impactshop_ads_calculate_tally_with_info(?string $quarter_key = null): array
{
    $tally = impactshop_ads_calculate_tally($quarter_key);
    if (!$tally) {
        return [];
    }

    $ngo_codes = impactshop_ads_watch_load_ngo_codes();
    foreach ($tally as &$row) {
        $slug = (string) ($row['ngo_slug'] ?? '');
        $row['ngo_name'] = $ngo_codes[$slug]['name'] ?? $slug;
        $row['ngo_logo'] = impactshop_ads_watch_get_ngo_logo_from_dataset($slug);
    }
    unset($row);

    return $tally;
}

function impactshop_ads_watch_get_ngo_by_slug(string $slug): ?array
{
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return null;
    }

    $ngo_codes = impactshop_ads_watch_load_ngo_codes();
    if (isset($ngo_codes[$slug])) {
        $item = $ngo_codes[$slug];
        $logo = impactshop_ads_watch_get_ngo_logo_from_dataset($slug);
        return [
            'slug' => $slug,
            'name' => (string) ($item['name'] ?? $slug),
            'logo' => $logo,
        ];
    }

    if (class_exists('ImpactShop_NGO_Card_API') && method_exists('ImpactShop_NGO_Card_API', 'get_dataset_items')) {
        $items = ImpactShop_NGO_Card_API::get_dataset_items(true);
        if (isset($items[$slug]) && is_array($items[$slug])) {
            $item = $items[$slug];
            return [
                'slug' => $slug,
                'name' => (string) ($item['name'] ?? $slug),
                'logo' => (string) ($item['logo_url'] ?? ''),
            ];
        }
    }

    $ngos = get_option('impactshop_ngo_list', []);
    if (isset($ngos[$slug])) {
        $item = $ngos[$slug];
        return [
            'slug' => $slug,
            'name' => (string) ($item['name'] ?? $slug),
            'logo' => (string) ($item['logo'] ?? ''),
        ];
    }

    return null;
}

function impactshop_ads_watch_get_ngo_list(string $search = '', int $limit = 50): array
{
    $items = [];

    $ngo_codes = impactshop_ads_watch_load_ngo_codes();
    if (!empty($ngo_codes)) {
        foreach ($ngo_codes as $slug => $item) {
            $name = (string) ($item['name'] ?? $slug);
            if ($search && stripos($name, $search) === false && stripos($slug, $search) === false) {
                continue;
            }
            $items[] = [
                'slug' => (string) $slug,
                'name' => $name,
                'logo' => impactshop_ads_watch_get_ngo_logo_from_dataset($slug),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
    }

    if (empty($items)) {
        $ngos = get_option('impactshop_ngo_list', []);
        foreach ($ngos as $slug => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = (string) ($item['name'] ?? $slug);
            if ($search && stripos($name, $search) === false && stripos($slug, $search) === false) {
                continue;
            }
            $items[] = [
                'slug' => (string) $slug,
                'name' => $name,
                'logo' => (string) ($item['logo'] ?? ''),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
    }

    return $items;
}

function impactshop_ads_watch_load_ngo_codes(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cached = wp_cache_get('impactshop_ads_ngo_codes', 'impactshop_ads');
    if (is_array($cached)) {
        $cache = $cached;
        return $cache;
    }

    $cached = get_transient('impactshop_ads_ngo_codes');
    if (is_array($cached)) {
        wp_cache_set('impactshop_ads_ngo_codes', $cached, 'impactshop_ads', HOUR_IN_SECONDS);
        $cache = $cached;
        return $cache;
    }

    $path = dirname(__DIR__, 2) . '/ngo_codes.csv';
    if (!file_exists($path) || !is_readable($path)) {
        $cache = [];
        return $cache;
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        $cache = [];
        return $cache;
    }

    $rows = [];
    $header = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== false) {
        $name = isset($data[0]) ? trim((string) $data[0]) : '';
        $slug = isset($data[1]) ? sanitize_title((string) $data[1]) : '';
        if ($slug === '' || $name === '') {
            continue;
        }
        $rows[$slug] = [
            'slug' => $slug,
            'name' => $name,
        ];
    }
    fclose($handle);

    $cache = $rows;
    set_transient('impactshop_ads_ngo_codes', $cache, HOUR_IN_SECONDS);
    wp_cache_set('impactshop_ads_ngo_codes', $cache, 'impactshop_ads', HOUR_IN_SECONDS);
    return $cache;
}

function impactshop_ads_watch_get_ngo_logo_from_dataset(string $slug): string
{
    if (class_exists('ImpactShop_NGO_Card_API') && method_exists('ImpactShop_NGO_Card_API', 'get_dataset_items')) {
        $dataset = ImpactShop_NGO_Card_API::get_dataset_items(true);
        if (isset($dataset[$slug]['logo_url'])) {
            return (string) $dataset[$slug]['logo_url'];
        }
    }
    return '';
}

function impactshop_ads_watch_get_ad_tag_url(): string
{
    // Default: empty string - no test/sample ads
    // Production ad tags should be provided via filter: 'impactshop_ads_watch_ad_tag_urls' or 'impactshop_ads_watch_ad_tag_url'
    $default = '';
    $urls = apply_filters('impactshop_ads_watch_ad_tag_urls', []);
    if (!is_array($urls) || empty($urls)) {
        $option_urls = get_option('impactshop_ads_watch_ad_tag_urls', []);
        if (is_array($option_urls)) {
            $urls = $option_urls;
        } elseif (is_string($option_urls) && $option_urls !== '') {
            $urls = preg_split('/\r\n|\r|\n/', $option_urls);
        }
    }
    if (is_array($urls) && !empty($urls)) {
        $urls = array_values(array_filter($urls, 'is_string'));
        if (!empty($urls)) {
            $url = $urls[array_rand($urls)];
            return esc_url_raw($url);
        }
    }

    $url = (string) apply_filters('impactshop_ads_watch_ad_tag_url', $default);
    if ($url === '') {
        $option_url = get_option('impactshop_ads_watch_ad_tag_url', '');
        if (is_string($option_url)) {
            $url = $option_url;
        }
    }
    return esc_url_raw($url);
}

function impactshop_ads_watch_get_arukereso_base(): string
{
    if (!function_exists('impactshop_get_shops')) {
        return '';
    }
    foreach (impactshop_get_shops() as $shop) {
        $slug = strtolower((string) ($shop['shop_slug'] ?? ''));
        if ($slug === 'arukereso') {
            return (string) ($shop['dognet_base'] ?? '');
        }
    }
    return '';
}

function impactshop_ads_watch_get_sponsor_settings(int $post_id): array
{
    $media_type = get_post_meta($post_id, 'impactshop_sponsor_media_type', true);
    if (!in_array($media_type, ['mp4', 'youtube', 'vast'], true)) {
        $media_type = 'mp4';
    }
    $media_url = (string) get_post_meta($post_id, 'impactshop_sponsor_media_url', true);
    $youtube_url = (string) get_post_meta($post_id, 'impactshop_sponsor_youtube_url', true);
    $youtube_id = (string) get_post_meta($post_id, 'impactshop_sponsor_youtube_id', true);
    if ($youtube_id === '' && $youtube_url !== '') {
        $youtube_id = impactshop_ads_watch_extract_youtube_id($youtube_url);
    }
    $vast_tag = (string) get_post_meta($post_id, 'impactshop_sponsor_vast_tag', true);
    $cta_label = (string) get_post_meta($post_id, 'impactshop_sponsor_cta_label', true);
    $cta_url = (string) get_post_meta($post_id, 'impactshop_sponsor_cta_url', true);
    $total_limit = (int) get_post_meta($post_id, 'impactshop_sponsor_total_limit', true);
    $user_limit = (int) get_post_meta($post_id, 'impactshop_sponsor_user_limit', true);
    $cooldown = (int) get_post_meta($post_id, 'impactshop_sponsor_cooldown', true);
    $start_at = (string) get_post_meta($post_id, 'impactshop_sponsor_start_at', true);
    $end_at = (string) get_post_meta($post_id, 'impactshop_sponsor_end_at', true);

    $start_at_local = $start_at !== '' ? str_replace(' ', 'T', $start_at) : '';
    $end_at_local = $end_at !== '' ? str_replace(' ', 'T', $end_at) : '';

    return [
        'media_type' => $media_type,
        'media_url' => $media_url,
        'youtube_url' => $youtube_url,
        'youtube_id' => $youtube_id,
        'vast_tag' => $vast_tag,
        'cta_label' => $cta_label !== '' ? $cta_label : 'Tudj meg tobbet',
        'cta_url' => $cta_url,
        'total_limit' => max(0, $total_limit),
        'user_limit' => max(0, $user_limit),
        'cooldown' => max(0, $cooldown),
        'start_at' => $start_at,
        'end_at' => $end_at,
        'start_at_local' => $start_at_local,
        'end_at_local' => $end_at_local,
    ];
}

function impactshop_ads_watch_is_sponsor_active(array $settings, int $now_ts): bool
{
    if ($settings['start_at'] !== '') {
        $start_ts = strtotime($settings['start_at']);
        if ($start_ts && $now_ts < $start_ts) {
            return false;
        }
    }
    if ($settings['end_at'] !== '') {
        $end_ts = strtotime($settings['end_at']);
        if ($end_ts && $now_ts > $end_ts) {
            return false;
        }
    }
    if ($settings['media_type'] === 'mp4' && $settings['media_url'] === '') {
        return false;
    }
    if ($settings['media_type'] === 'youtube' && $settings['youtube_id'] === '') {
        return false;
    }
    if ($settings['media_type'] === 'vast' && $settings['vast_tag'] === '') {
        return false;
    }
    return true;
}

function impactshop_ads_watch_can_view_sponsor(string $pseudo_id, int $sponsor_id): bool
{
    if ($pseudo_id === '' || $sponsor_id <= 0) {
        return false;
    }
    $settings = impactshop_ads_watch_get_sponsor_settings($sponsor_id);
    $now = (int) current_time('timestamp');
    if (!impactshop_ads_watch_is_sponsor_active($settings, $now)) {
        return false;
    }

    global $wpdb;
    $table_views = $wpdb->prefix . 'impactshop_ads_views';

    if ($settings['total_limit'] > 0) {
        $total_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_views} WHERE sponsor_id = %d",
            $sponsor_id
        ));
        if ($total_views >= $settings['total_limit']) {
            return false;
        }
    }

    if ($settings['user_limit'] > 0) {
        $user_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_views} WHERE sponsor_id = %d AND pseudo_id = %s",
            $sponsor_id,
            $pseudo_id
        ));
        if ($user_views >= $settings['user_limit']) {
            return false;
        }
    }

    if ($settings['cooldown'] > 0) {
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$table_views} WHERE sponsor_id = %d AND pseudo_id = %s",
            $sponsor_id,
            $pseudo_id
        ));
        if ($last) {
            $last_ts = strtotime((string) $last);
            if ($last_ts && ($now - $last_ts) < $settings['cooldown']) {
                return false;
            }
        }
    }

    return true;
}

function impactshop_ads_watch_can_view_sponsor_reason(string $pseudo_id, int $sponsor_id): array
{
    if ($pseudo_id === '' || $sponsor_id <= 0) {
        return [
            'can_view' => false,
            'reason' => 'no_pseudo_id',
        ];
    }

    $settings = impactshop_ads_watch_get_sponsor_settings($sponsor_id);
    $now = (int) current_time('timestamp');
    if (!impactshop_ads_watch_is_sponsor_active($settings, $now)) {
        return [
            'can_view' => false,
            'reason' => 'not_active',
        ];
    }

    global $wpdb;
    $table_views = $wpdb->prefix . 'impactshop_ads_views';

    if ($settings['total_limit'] > 0) {
        $total_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_views} WHERE sponsor_id = %d",
            $sponsor_id
        ));
        if ($total_views >= $settings['total_limit']) {
            return [
                'can_view' => false,
                'reason' => 'total_limit_reached:' . $total_views . '/' . $settings['total_limit'],
            ];
        }
    }

    if ($settings['user_limit'] > 0) {
        $user_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_views} WHERE sponsor_id = %d AND pseudo_id = %s",
            $sponsor_id,
            $pseudo_id
        ));
        if ($user_views >= $settings['user_limit']) {
            return [
                'can_view' => false,
                'reason' => 'user_limit_reached:' . $user_views . '/' . $settings['user_limit'],
            ];
        }
    }

    if ($settings['cooldown'] > 0) {
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$table_views} WHERE sponsor_id = %d AND pseudo_id = %s",
            $sponsor_id,
            $pseudo_id
        ));
        if ($last) {
            $last_ts = strtotime((string) $last);
            if ($last_ts && ($now - $last_ts) < $settings['cooldown']) {
                return [
                    'can_view' => false,
                    'reason' => 'cooldown:' . ($now - $last_ts) . 's/' . $settings['cooldown'] . 's',
                ];
            }
        }
    }

    return [
        'can_view' => true,
        'reason' => '',
    ];
}

function impactshop_ads_watch_pick_sponsor_for_pseudo(string $pseudo_id): ?array
{
    if ($pseudo_id === '') {
        return null;
    }

    $posts = get_posts([
        'post_type' => 'impact_sponsor_video',
        'post_status' => 'publish',
        'numberposts' => 20,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    $now = (int) current_time('timestamp');
    foreach ($posts as $post) {
        $settings = impactshop_ads_watch_get_sponsor_settings($post->ID);
        if (!impactshop_ads_watch_is_sponsor_active($settings, $now)) {
            continue;
        }
        if (!impactshop_ads_watch_can_view_sponsor($pseudo_id, (int) $post->ID)) {
            continue;
        }

        return [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'media_type' => $settings['media_type'],
            'media_url' => $settings['media_url'],
            'youtube_id' => $settings['youtube_id'],
            'youtube_url' => $settings['youtube_url'],
            'vast_tag' => $settings['vast_tag'],
            'cta_label' => $settings['cta_label'],
            'cta_url' => $settings['cta_url'],
            'points' => IMPACTSHOP_ADS_POINTS_SPONSOR,
            'votes' => IMPACTSHOP_ADS_VOTES_SPONSOR,
        ];
    }

    return null;
}

function impactshop_ads_watch_get_mock_sponsors(): array
{
    // Mock sponsors disabled - use real sponsors only
    // To re-enable for testing, uncomment the $defaults array below
    $defaults = [];
    /*
    $tag = impactshop_ads_watch_get_ad_tag_url();
    $defaults = [
        [
            'title' => 'JYSK szponzori videó',
            'media_type' => 'vast',
            'media_url' => '',
            'vast_tag' => $tag,
            'cta_label' => 'Tudj meg tobbet',
            'cta_url' => '',
            'points' => IMPACTSHOP_ADS_POINTS_SPONSOR,
            'votes' => IMPACTSHOP_ADS_VOTES_SPONSOR,
        ],
        [
            'title' => 'Impact Ads szponzor',
            'media_type' => 'vast',
            'media_url' => '',
            'vast_tag' => $tag,
            'cta_label' => 'Tudj meg tobbet',
            'cta_url' => '',
            'points' => IMPACTSHOP_ADS_POINTS_SPONSOR,
            'votes' => IMPACTSHOP_ADS_VOTES_SPONSOR,
        ],
        [
            'title' => 'Teszt reklám',
            'media_type' => 'vast',
            'media_url' => '',
            'vast_tag' => $tag,
            'points' => IMPACTSHOP_ADS_POINTS_SPONSOR,
            'votes' => IMPACTSHOP_ADS_VOTES_SPONSOR,
        ],
    ];
    */

    $mocks = apply_filters('impactshop_ads_watch_mock_sponsors', $defaults);
    return is_array($mocks) ? array_values($mocks) : [];
}

function impactshop_ads_watch_sponsor_stats_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $sponsor_id = isset($_GET['sponsor_id']) ? absint($_GET['sponsor_id']) : 0;
    $sponsors = get_posts([
        'post_type' => 'impact_sponsor_video',
        'post_status' => 'publish',
        'numberposts' => 100,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    echo '<div class="wrap"><h1>Szponzor videó statisztikák</h1>';
    echo '<form method="get" style="margin:12px 0;">';
    echo '<input type="hidden" name="post_type" value="impact_sponsor_video">';
    echo '<input type="hidden" name="page" value="impactshop-sponsor-stats">';
    echo '<label for="sponsor_id">Szponzor:</label> ';
    echo '<select name="sponsor_id" id="sponsor_id">';
    echo '<option value="0">Válassz...</option>';
    foreach ($sponsors as $sponsor) {
        printf(
            '<option value="%d"%s>%s</option>',
            (int) $sponsor->ID,
            selected($sponsor_id, (int) $sponsor->ID, false),
            esc_html(get_the_title($sponsor))
        );
    }
    echo '</select> ';
    submit_button('Megjelenítés', 'secondary', '', false);
    echo '</form>';

    if ($sponsor_id > 0) {
        $table_views = $wpdb->prefix . 'impactshop_ads_views';
        $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
        $table_ledger = $wpdb->prefix . 'impact_ledger';

        $total_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_views} WHERE sponsor_id = %d",
            $sponsor_id
        ));
        $unique_viewers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pseudo_id) FROM {$table_views} WHERE sponsor_id = %d",
            $sponsor_id
        ));

        $votes = $wpdb->get_results($wpdb->prepare(
            "SELECT v.ngo_slug, SUM(v.vote_weight) as votes
             FROM {$table_votes} v
             INNER JOIN {$table_views} vw
                ON vw.pseudo_id = v.pseudo_id AND vw.sponsor_id = %d
             GROUP BY v.ngo_slug
             ORDER BY votes DESC",
            $sponsor_id
        ), ARRAY_A);

        $total_donations = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(l.amount_huf) FROM {$table_ledger} l
             INNER JOIN {$table_views} vw
                ON LOWER(vw.pseudo_id) = LOWER(l.pseudo_id)
             WHERE vw.sponsor_id = %d
             AND l.status IN ('approved','pending')",
            $sponsor_id
        ));

        echo '<div class="card" style="max-width:900px;padding:16px 20px;">';
        echo '<h2>Összesítés</h2>';
        echo '<p><strong>Megtekintések:</strong> ' . esc_html(number_format($total_views, 0, ',', ' ')) . '</p>';
        echo '<p><strong>Egyedi nézők:</strong> ' . esc_html(number_format($unique_viewers, 0, ',', ' ')) . '</p>';
        echo '<p><strong>Kapcsolt vásárlások összege:</strong> ' . esc_html(number_format($total_donations, 0, ',', ' ')) . ' Ft</p>';
        echo '</div>';

        echo '<h2 style="margin-top:20px;">Szavazatok NGO szerint</h2>';
        if (!$votes) {
            echo '<p>Nincs szavazati adat.</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:900px;">';
            echo '<thead><tr><th>NGO</th><th>Szavazat</th></tr></thead><tbody>';
            foreach ($votes as $row) {
                $ngo_info = impactshop_ads_watch_get_ngo_by_slug((string) $row['ngo_slug']);
                $name = $ngo_info['name'] ?? $row['ngo_slug'];
                echo '<tr><td>' . esc_html($name) . '</td><td>' . esc_html(number_format((int) $row['votes'], 0, ',', ' ')) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    echo '</div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// SHORTCODE
// ─────────────────────────────────────────────────────────────────────────────

add_shortcode('impactshop_ads_watch', 'impactshop_ads_watch_shortcode');

function impactshop_ads_watch_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'title'           => 'Reklám megtekintése = Adomány',
        'fillout_form_id' => '',
    ], $atts, 'impactshop_ads_watch');

    $fillout_form_id = trim((string) $atts['fillout_form_id']);
    if ($fillout_form_id === '') {
        if (function_exists('impactshop_get_fillout_url')) {
            $fillout_form_id = impactshop_get_fillout_url();
        }
        if ($fillout_form_id === '') {
            $fillout_form_id = 'https://form.fillout.com/t/eM61RLkz6jus';
        }
    }

    wp_enqueue_script(
        'impactshop-ads-watch',
        plugins_url('impactshop-ads-watch.js', __FILE__),
        ['jquery'],
        IMPACTSHOP_ADS_WATCH_VERSION,
        true
    );

    wp_enqueue_style(
        'impactshop-ads-watch',
        plugins_url('impactshop-ads-watch.css', __FILE__),
        [],
        IMPACTSHOP_ADS_WATCH_VERSION
    );

    wp_enqueue_script(
        'impactshop-vote-purchase',
        plugins_url('impactshop-vote-purchase.js', __FILE__),
        ['impactshop-ads-watch'],
        defined('IMPACTSHOP_VOTE_PURCHASE_VERSION') ? IMPACTSHOP_VOTE_PURCHASE_VERSION : IMPACTSHOP_ADS_WATCH_VERSION,
        true
    );

    wp_enqueue_style(
        'impactshop-vote-purchase',
        plugins_url('impactshop-vote-purchase.css', __FILE__),
        [],
        defined('IMPACTSHOP_VOTE_PURCHASE_VERSION') ? IMPACTSHOP_VOTE_PURCHASE_VERSION : IMPACTSHOP_ADS_WATCH_VERSION
    );

    $quarter_window = impactshop_ads_get_active_quarter_window();

    wp_localize_script('impactshop-ads-watch', 'impactshopAdsWatch', [
        'restUrl'        => rest_url('impact/v1/ads-watch'),
        'restNonce'      => wp_create_nonce('wp_rest'),
        'donationPool'   => impactshop_ads_get_active_pool(),
        'filloutFormId'  => $fillout_form_id,
        'adTagUrl'       => impactshop_ads_watch_get_ad_tag_url(),
        'impactShopBaseUrl' => site_url('/impactshop/'),
        'arukeresoDognetBase' => impactshop_ads_watch_get_arukereso_base(),
        'unifiedDisplay' => (bool) apply_filters('impactshop_ads_watch_unified_display', true),
        'quarter' => [
            'key' => $quarter_window['quarter_key'] ?? '',
            'startAt' => $quarter_window['start_at'] ?? '',
            'endAt' => $quarter_window['end_at'] ?? '',
            'startTs' => $quarter_window['start_ts'] ?? 0,
            'endTs' => $quarter_window['end_ts'] ?? 0,
            'nowTs' => time(),
        ],
        'i18n'           => [
            'selectNgo'     => 'Válassz NGO-t',
            'watching'      => 'Reklám lejátszása...',
            'pointsEarned'  => '+%d pont',
            'votesCast'     => '+%d szavazat',
            'thankYou'      => 'Köszönjük!',
            'error'         => 'Hiba történt',
            'noNgoSelected' => 'Először válassz egy NGO-t!',
            'noIdentity'    => 'Azonosító szükséges a pontgyűjtéshez.',
            'loadingAd'     => 'Reklám betöltése...',
            'adError'       => 'Nem sikerült betölteni a reklámot',
            'sponsorVideo'  => 'Szponzori videó',
        ],
    ]);

    $vote_purchase_config = function_exists('impactshop_vote_purchase_get_public_config')
        ? impactshop_vote_purchase_get_public_config()
        : ['enabled' => false];

    wp_localize_script('impactshop-vote-purchase', 'impactshopVotePurchase', [
        'enabled' => (bool) ($vote_purchase_config['enabled'] ?? false),
        'currency' => $vote_purchase_config['currency'] ?? 'huf',
        'packages' => $vote_purchase_config['packages'] ?? [],
        'publicKey' => $vote_purchase_config['publicKey'] ?? '',
        'restBase' => rest_url('impact/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'pseudoId' => isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field((string) $_COOKIE['impactshop_pseudo_id']) : '',
    ]);

    ob_start();
    ?>
    <div class="impactshop-ads-watch-container" id="impactshop-ads-watch">
        <div class="ads-watch-header">
            <h2>Aktivitás = Adomány</h2>
            <div class="ads-watch-subtitle">
                <span class="subtitle-text" id="ads-watch-subtitle-text">🎬 Nézz videókat – minden megtekintés után pontot és szavazatot kapsz.</span>
                <button type="button" class="info-trigger" aria-label="Információ" data-info-trigger>i</button>
                <div class="info-popover" data-info-popover hidden>
                    <strong>Hogyan működik?</strong><br><br>
                    <span id="ads-watch-info-primary">🎬 <strong>Nézz videókat</strong> – minden megtekintés után pontot és szavazatot kapsz</span><br><br>
                    📈 <strong>Emelkedj szintet</strong> – a pontjaid növelik a szinted és a szavazati erőd<br><br>
                    🗳️ <strong>Szavazz</strong> – add le szavazataidat egy általad választott szervezetre<br><br>
                    💰 <strong>Támogass</strong> – a szervezetek a szavazatok arányában részesülnek az adományalapból
                </div>
            </div>
            <div class="donation-pool-display">
                <span class="pool-label">Adományalap:</span>
                <span class="pool-amount"><?php echo number_format(impactshop_ads_get_active_pool(), 0, ',', ' '); ?> Ft</span>
            </div>
        </div>

        <div class="ads-watch-floating-tabs" data-role="ads-watch-tabs">
            <button type="button" class="ads-watch-tab is-active" data-role="ads-watch-tab" data-target="video">🎬 Videó</button>
            <button type="button" class="ads-watch-tab" data-role="ads-watch-tab" data-target="offerwall">🎁 Feladatok</button>
            <a href="<?php echo esc_url(site_url('/impactshop/')); ?>" class="ads-watch-tab ads-watch-impactshop-btn" data-role="impactshop-btn" id="ads-watch-impactshop-btn" target="_blank" rel="noopener">🛍️ Impact Shop</a>
            <a href="https://adomany.sharity.hu/kampanyok" class="ads-watch-tab ads-watch-donate-btn" data-role="donate-btn" id="ads-watch-donate-btn" target="_blank" rel="noopener">❤️ Adományozok</a>
        </div>

        <div class="ads-watch-main" data-role="ads-watch-main">
        <div class="ads-watch-status-bar" id="ads-watch-status-bar">
            <div class="status-item user-points">
                <span class="label">Pontjaid:</span>
                <span class="value" id="user-points-display">-</span>
            </div>
            <div class="status-item user-level">
                <span class="label">Szinted:</span>
                <span class="value" id="user-level-display">-</span>
            </div>
            <div class="status-item vote-weight">
                <span class="label">Szavazat súly:</span>
                <span class="value" id="vote-weight-display">-</span>
            </div>
            <div class="status-item donation-multiplier">
                <span class="label">Adomány bónusz:</span>
                <span class="value" id="donation-multiplier-display">-</span>
            </div>
            <div class="status-item vote-balance">
                <span class="label">Szavazataid:</span>
                <span class="value" id="available-votes-display">-</span>
            </div>
            <div class="status-item streak">
                <span class="label">🔥 Streak:</span>
                <span class="value" id="streak-display">-</span>
            </div>
            <div class="status-item countdown">
                <span class="label">⏳ Visszaszámláló:</span>
                <span class="value" id="impact-challenge-countdown-display">-</span>
            </div>
        </div>

        <div class="ads-watch-purchase" data-role="vote-purchase" id="ads-watch-purchase">
            <div class="purchase-header">
                <button type="button" class="purchase-toggle" data-role="purchase-toggle" aria-expanded="false">
                    🎁 Adományozz és szavazz
                </button>
                <button type="button" class="purchase-info" data-role="purchase-info" aria-expanded="false" title="Információ">
                    i
                </button>
            </div>
            <div class="purchase-info-panel" data-role="purchase-info-panel" hidden>
                Szavazatokat kapsz, pontot nem — a szintlépéshez aktivitás kell.
                <br>
                Az adomány 50%-a a közös adományalapba kerül, 50%-a a Sharity üzemeltetését támogatja.
                <br>⚡ Impact Amplifier: a szavazatok nem csak a saját 50%-os pool‑részből adnak, hanem a teljes közös alap arányát növelik. Példa: 10 000 Ft Legend csomag után a kedvenc NGO részesedése ~10%‑ról ~21,7%‑ra nőhet, így a kapott összeg akár ~12× is lehet a sima befizetéshez képest.
            </div>
            <div class="purchase-body" data-role="purchase-body" hidden>
                <div class="purchase-packages" data-role="purchase-packages"></div>
                <div class="purchase-row">
                    <label for="purchase-currency">Pénznem</label>
                    <select id="purchase-currency" data-role="purchase-currency"></select>
                </div>
                <div class="purchase-row">
                    <label>
                        <input type="checkbox" data-role="purchase-company"> Cégként adományozol
                    </label>
                </div>
                <div data-role="purchase-company-fields" hidden>
                    <div class="purchase-row">
                        <label for="purchase-company-name">Cég neve</label>
                        <input id="purchase-company-name" type="text" data-role="company-name" placeholder="Példa Kft.">
                    </div>
                    <div class="purchase-row">
                        <label for="purchase-company-tax">Adószám</label>
                        <input id="purchase-company-tax" type="text" data-role="company-tax" placeholder="12345678-2-42">
                    </div>
                    <div class="purchase-row">
                        <label for="purchase-company-address">Székhely</label>
                        <input id="purchase-company-address" type="text" data-role="company-address" placeholder="1234 Budapest, Példa u. 1.">
                    </div>
                    <div class="purchase-row">
                        <label for="purchase-company-email">Email</label>
                        <input id="purchase-company-email" type="email" data-role="company-email" placeholder="cfo@pelda.hu">
                    </div>
                    <div class="purchase-row">
                        <label>
                            <input type="checkbox" data-role="company-save"> Adatok mentése
                        </label>
                    </div>
                    <div class="purchase-row">
                        <label>
                            <input type="checkbox" data-role="company-gdpr"> Hozzájárulok az email kezeléséhez (GDPR)
                        </label>
                    </div>
                </div>
                <div class="purchase-row">
                    <label>
                        <input type="checkbox" data-role="purchase-consent"> Elfogadom az ÁSZF-et és az adatvédelmi tájékoztatót
                    </label>
                </div>
                <div class="purchase-actions">
                    <button type="button" data-role="purchase-submit">Adományozom</button>
                </div>
                <div class="purchase-status" data-role="purchase-status"></div>
            </div>
        </div>

        <div class="ads-watch-steps" id="ads-watch-steps">
            <button type="button" class="step-pill" data-scroll-target="#ads-watch-video">1. Videó</button>
            <button type="button" class="step-pill" data-scroll-target="#ads-watch-ngo">2. Szervezet</button>
            <button type="button" class="step-pill" data-scroll-target="#ads-watch-vote-button">3. Szavazás</button>
        </div>

        <div class="ads-watch-insights-title">Gyors infók</div>
        <div class="ads-watch-insights">
            <div class="insight-card" id="ads-watch-live">
                <div class="insight-title">🔥 Élő aktivitás</div>
                <div class="insight-value"><strong id="live-activity-value">-</strong></div>
                <div class="insight-hint">Utóbbi 5 perc szavazatai.</div>
            </div>
            <div class="insight-card" id="ads-watch-message" data-role="ads-watch-message">
                <div class="insight-title">💬 Kampány üzenet</div>
                <div class="insight-value" data-role="ads-watch-message-text">Üzenet hamarosan...</div>
                <div class="insight-hint">Itt jelennek meg a friss hírek.</div>
            </div>
            <div class="insight-card" id="ads-watch-chance">
                <div class="insight-title">🎁 Nyeremény esélyed</div>
                <div class="insight-value"><strong id="chance-value">-</strong></div>
                <div class="insight-hint">A szavazataid arányában nő.</div>
            </div>
        </div>

        <div class="ads-watch-ngo-selection" id="ads-watch-ngo">
            <h3>Válaszd ki az NGO-t, amelyet támogatni szeretnél:</h3>
            <div class="selected-ngo" id="selected-ngo-display">
                <span class="no-ngo-text">Még nem választottál NGO-t</span>
            </div>
            <button type="button" class="btn-change-ngo" id="btn-change-ngo">
                NGO kiválasztása / módosítása
            </button>

            <div class="vote-alloc">
                <label for="vote-amount-input">Hány szavazatot szeretnél leadni?</label>
                <div class="vote-quick">
                    <span class="vote-available">Elérhető: <strong id="available-votes-inline">0</strong> szavazat</span>
                    <div class="vote-quick-buttons">
                        <button type="button" class="btn-quick-vote" data-vote-quick="all">Mind</button>
                        <button type="button" class="btn-quick-vote" data-vote-quick="half">Fele</button>
                        <button type="button" class="btn-quick-vote" data-vote-quick="1">+1</button>
                        <button type="button" class="btn-quick-vote" data-vote-quick="5">+5</button>
                        <button type="button" class="btn-quick-vote" data-vote-quick="10">+10</button>
                    </div>
                </div>
                <div class="vote-alloc-row" id="ads-watch-vote-button">
                    <input type="number" id="vote-amount-input" min="1" step="1" placeholder="pl. 5">
                    <button type="button" class="btn-allocate-votes" id="btn-allocate-votes" disabled>Szavazok most</button>
                </div>
                <div class="vote-alloc-hint">Gyűjts szavazatokat videók megtekintésével, majd add le őket tetszőleges szervezet(ek)re.</div>
                <label class="auto-vote-toggle">
                    <input type="checkbox" id="auto-vote-enabled">
                    Automatikus szavazás (minden új szavazat azonnal a kiválasztott NGO-ra megy)
                </label>
            </div>
        </div>

        <div class="ads-watch-player-area" id="ads-watch-video">
            <div class="video-container" id="video-container">
                <video id="content-video" playsinline>
                    <source src="" type="video/mp4">
                </video>
                <div id="ad-container"></div>
                <button type="button" class="ima-cta-overlay" id="ima-cta-overlay" style="display: none;" title="Kattints a bónusz pontokért!">
                    <span class="ima-cta-icon">👆</span>
                    <span class="ima-cta-text">Kattints a videóra!</span>
                </button>
                <div class="ads-watch-cta sponsor-cta-overlay" id="ads-watch-cta" style="display: none;">
                    <a href="#" target="_blank" rel="noopener" id="ads-watch-cta-link" title="Kattints a bónusz pontokért">
                        <span class="ima-cta-icon">👆</span>
                        <span class="ima-cta-text">Kattints ide!</span>
                    </a>
                </div>
                <div class="education-iframe" id="education-iframe" style="display: none;"></div>
                <div class="presence-check-overlay" id="presence-check-overlay" style="display: none;" aria-live="polite">
                    <div class="presence-check-title">Még itt vagy?</div>
                    <div class="presence-check-subtitle">Kattints a folytatáshoz, hogy tovább kapd a jutalmakat.</div>
                    <button type="button" class="btn-presence-confirm" id="presence-confirm">Igen, folytatom</button>
                    <div class="presence-timeout-bar">
                        <div class="presence-timeout-fill" id="presence-timeout-fill"></div>
                    </div>
                </div>
            </div>
            <div class="player-overlay" id="player-overlay">
                <button type="button" class="btn-watch-ad" id="btn-watch-ad" disabled>
                    <span class="btn-icon">▶</span>
                    <span class="btn-text">Reklám megtekintése</span>
                </button>
            </div>
            <div class="player-loading" id="player-loading" style="display: none;">
                <div class="spinner"></div>
                <span>Reklám betöltése...</span>
            </div>
            <div class="ad-progress-bar" id="ad-progress-bar" style="display: none;">
                <div class="ad-progress-fill" id="ad-progress-fill"></div>
            </div>
            <div class="ad-progress-meta" id="ad-progress-meta" style="display: none;">
                <span id="ad-progress-text">Videó megtekintése szükséges a szavazathoz.</span>
                <span class="ad-progress-help">
                    <span class="ad-progress-help-label">Miért kell végignézni?</span>
                    <span class="ad-progress-help-bubble">A szavazás hitelesítése miatt csak a végignézett videó után jár a jutalom.</span>
                </span>
            </div>
            <button type="button" class="btn-skip-video" id="btn-skip-video" style="display: none;">
                Videó kihagyása
            </button>
            <button type="button" class="btn-resume-ad" id="btn-resume-ad" style="display: none;">
                ▶ Folytatás
            </button>
            <div class="video-info-panel" id="video-info-panel" style="display: none;">
                <div class="video-info-title">
                    <span class="video-info-icon" id="video-info-icon">📺</span>
                    <span id="video-info-title-text"></span>
                </div>
                <div class="video-info-section video-info-watch">
                    <span class="video-info-label">👀 Megnézésért:</span>
                    <span class="video-info-value" id="video-info-watch-reward"></span>
                </div>
                <div class="video-info-section video-info-click" id="video-info-click-section" style="display: none;">
                    <span class="video-info-label">👆 Kattintásért:</span>
                    <span class="video-info-value" id="video-info-click-reward"></span>
                </div>
                <div class="video-info-progress" id="video-info-progress-section" style="display: none;">
                    <span class="video-info-label">⏱️ Eddig:</span>
                    <span id="video-info-watched-time">0:00</span> →
                    <span id="video-info-earned-pts">0</span> pont jóváírva
                </div>
            </div>
            <div class="education-info-bar" id="education-info-bar" style="display: none;">
                <div class="edu-info-title">📚 <span id="edu-video-title"></span></div>
                <div class="edu-info-rewards">
                    💰 Minden <span id="edu-interval-sec">30</span> mp-ért:
                    +<span id="edu-pts-interval">5</span> pont,
                    +<span id="edu-votes-interval">5</span> szavazat
                </div>
                <div class="edu-info-bonus">
                    🎁 Végignézésért: +<span id="edu-bonus-pts">10</span> bónusz pont,
                    +<span id="edu-bonus-votes">10</span> bónusz szavazat
                </div>
                <div class="edu-info-progress">
                    ⏱️ Eddig: <span id="edu-watched-time">0:00</span> →
                    <span id="edu-earned-pts">0</span> pont jóváírva
                </div>
                <button type="button" class="btn-skip-education" id="btn-skip-education" style="display: none;">
                    Videó kihagyása
                </button>
            </div>
            <div class="ads-watch-banner" data-role="auto-banner" hidden>
                <div class="auto-banner-card">
                    <div class="auto-banner-media">
                        <img src="" alt="" data-role="auto-banner-image">
                    </div>
                    <div class="auto-banner-body">
                        <div class="auto-banner-title" data-role="auto-banner-title"></div>
                        <div class="auto-banner-prices" data-role="auto-banner-prices"></div>
                        <a class="auto-banner-link" href="#" target="_blank" rel="noopener" data-role="auto-banner-link">Megnézem</a>
                        <div class="auto-banner-progress">
                            <span class="auto-banner-fill" data-role="auto-banner-progress"></span>
                        </div>
                        <div class="auto-banner-hint">Automatikus ajánló – 15 mp után frissül.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="reward-animation" id="reward-animation" style="display: none;">
            <div class="reward-content">
                <div class="reward-points">+<span id="reward-points-value">1</span> pont</div>
                <div class="reward-votes">+<span id="reward-votes-value">1</span> szavazat</div>
                <div class="reward-ngo" id="reward-ngo-name"></div>
            </div>
        </div>

        <div class="ads-watch-tally" id="ads-watch-vote">
            <div class="tally-header" data-role="tally-toggle" style="cursor:pointer;display:flex;justify-content:space-between;align-items:center">
                <h3 style="margin:0">Top 10 NGO</h3>
                <span class="tally-collapse-icon" data-role="tally-icon" style="font-size:18px;transition:transform .2s">▼</span>
            </div>
            <div class="tally-info">
                <span>Összes szavazat: <strong id="total-votes-display">-</strong></span>
            </div>
            <div class="tally-list" id="tally-list" data-collapsed="true">
                <div class="tally-loading">Betöltés...</div>
            </div>
            <button type="button" class="btn-show-more-ngos" id="btn-show-more-ngos" style="display:none">
                Mutass többet ▼
            </button>
            <button type="button" class="btn-show-all-ngos" id="btn-show-all-ngos">
                Teljes lista megtekintése
            </button>
        </div>

        <div class="ngo-selection-modal" id="ngo-selection-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>NGO kiválasztása</h3>
                    <button type="button" class="modal-close" id="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="ngo-search">
                        <input type="text" id="ngo-search-input" placeholder="Keresés...">
                    </div>
                    <div class="ngo-list" id="ngo-list">
                        <div class="ngo-list-loading">Betöltés...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="full-tally-modal" id="full-tally-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Összes NGO szavazati eredménye</h3>
                    <button type="button" class="modal-close" id="full-tally-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="ngo-search">
                        <input type="text" id="full-tally-search" placeholder="Keresés NGO névre...">
                    </div>
                    <div class="full-tally-list" id="full-tally-list">
                        <div class="tally-loading">Betöltés...</div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}
