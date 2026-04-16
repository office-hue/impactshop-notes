<?php
/**
 * Plugin Name: Impact Community — Hatás Körök
 * Description: Community layer for ImpactShop — NGO/settlement circles, posts, activity points, invites.
 * Version:     0.1.0
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

define('IC_DB_VERSION', '1.3.7');
define('IC_MAX_CIRCLES', 10);
define('IC_MAX_BODY_LENGTH', 600);
define('IC_POSTS_PER_PAGE', 20);
define('IC_CIRCLES_PER_PAGE', 30);
define('IC_RATE_LIMIT_POSTS_PER_HOUR', 5);
define('IMPACT_COMMUNITY_TEST_MODE_COOKIE', 'impact_community_test_mode');
define('IMPACT_COMMUNITY_DEV_CLONE_SLUG', 'hatas-korok-dev');
define('IMPACT_COMMUNITY_DEV_CLONE_CAPABILITY', 'manage_options');

/* =========================================================================
   1. Impact Alias — deterministic alias from pid_hash + circle_id
   ========================================================================= */

final class IC_Alias {

    private const ICONS = ['🌱','🔥','💧','🌍','⭐','🎯','🦡','🐾','🌊','🏔️','☀️','🌙','🍀','🦋','🐝','🎭','🔔','💎','🏅','🪴'];

    private const WORDS_A = [
        'Bátrak','Zöld','Csöndes','Lángoló','Fényes','Szabad','Rejtett','Tiszta',
        'Merész','Hűséges','Őszinte','Vidám','Nyugodt','Csillagos','Mosolygós',
        'Derűs','Kitartó','Álmodó','Felkelő','Viharos','Napfényes','Hajnali',
        'Békés','Hamvas','Éjféli','Napsütötte','Szélben','Tengerszínű','Aranyos','Jégvirágos',
    ];

    private const WORDS_B = [
        'Szava','Hírnök','Folyam','Kéz','Tűz','Madár','Mag','Ösvény','Fény','Pajzs',
        'Hullám','Szikra','Part','Csillag','Lélek','Remény','Harang','Kürt','Vándor',
        'Forrás','Mécses','Hajó','Szél','Öböl','Lámpás','Kapu','Kert','Bástyás','Erdő','Torony',
    ];

    /**
     * Deterministic alias: same pseudo_id in same circle always gives the same alias.
     * Different circle → different alias (extra privacy layer).
     */
    public static function generate(string $pid_hash, int $circle_id): string {
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

function ic_boolish($value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return false;
    }
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true);
}

function ic_is_dev_clone_request(): bool {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    return (bool) preg_match('~^/' . preg_quote(IMPACT_COMMUNITY_DEV_CLONE_SLUG, '~') . '/?(\?.*)?$~', $uri);
}

function ic_is_dev_clone_authorized(): bool {
    return is_user_logged_in() && current_user_can(IMPACT_COMMUNITY_DEV_CLONE_CAPABILITY);
}

function ic_is_page_request(): bool {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (preg_match('~^/hatas-korok/?(\?.*)?$~', $uri)) {
        return true;
    }

    if (ic_is_dev_clone_request()) {
        return ic_is_dev_clone_authorized();
    }

    return false;
}

function ic_guard_dev_clone_access(): void {
    if (!ic_is_dev_clone_request()) {
        return;
    }

    if (ic_is_dev_clone_authorized()) {
        return;
    }

    nocache_headers();
    wp_die('Not Found', 'Not Found', ['response' => 404]);
}

function ic_send_nocache_headers(): void {
    if (!ic_is_page_request() || headers_sent()) {
        return;
    }

    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
    header('Pragma: no-cache', true);
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
}

function ic_send_dev_clone_noindex_headers(): void {
    if (!ic_is_dev_clone_request() || !ic_is_dev_clone_authorized() || headers_sent()) {
        return;
    }

    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}

function ic_test_mode_configured(): bool {
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }

    if (defined('IMPACT_COMMUNITY_TEST_MODE')) {
        return $enabled = ic_boolish(IMPACT_COMMUNITY_TEST_MODE);
    }

    $raw = getenv('IMPACT_COMMUNITY_TEST_MODE');
    if ($raw === false || $raw === '') {
        $raw = getenv('PIN_TEST_MODE');
    }

    return $enabled = ic_boolish($raw);
}

function ic_test_mode_set_cookie(bool $enabled): void {
    if (headers_sent()) {
        return;
    }

    $secure = is_ssl();
    $expires = $enabled ? time() + DAY_IN_SECONDS : time() - HOUR_IN_SECONDS;
    $value = $enabled ? '1' : '0';

    if (PHP_VERSION_ID >= 70300) {
        setcookie(IMPACT_COMMUNITY_TEST_MODE_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(IMPACT_COMMUNITY_TEST_MODE_COOKIE, $value, $expires, '/; samesite=Lax', '', $secure, false);
    }

    $_COOKIE[IMPACT_COMMUNITY_TEST_MODE_COOKIE] = $value;
}

function ic_test_mode_capture_request(): void {
    if (isset($_GET['ic_test_mode'])) {
        ic_test_mode_set_cookie(ic_boolish($_GET['ic_test_mode']));
    }
}

add_action('init', 'ic_test_mode_capture_request', 2);

function ic_test_mode_enabled(): bool {
    if (ic_test_mode_configured()) {
        return true;
    }

    if (isset($_GET['ic_test_mode']) && ic_boolish($_GET['ic_test_mode'])) {
        return true;
    }

    if (isset($_SERVER['HTTP_X_IMPACT_TEST_MODE']) && ic_boolish($_SERVER['HTTP_X_IMPACT_TEST_MODE'])) {
        return true;
    }

    return ic_boolish($_COOKIE[IMPACT_COMMUNITY_TEST_MODE_COOKIE] ?? '');
}

function ic_normalize_test_pseudo($value): string {
    if (function_exists('impactshop_identity_normalize_pseudo')) {
        return impactshop_identity_normalize_pseudo($value);
    }
    return strtoupper(substr(preg_replace('~[^A-Za-z0-9]~', '', (string) $value), 0, 12));
}

function ic_test_mode_requested_pseudo(?WP_REST_Request $req = null): string {
    $candidates = [];

    if ($req) {
        $candidates[] = $req->get_param('pseudo_id');
        $candidates[] = $req->get_param('impact_pseudo_id');
        $candidates[] = $req->get_header('X-Impact-Pseudo-Id');
        $candidates[] = $req->get_header('X-Pseudo-Id');
    }

    $candidates[] = $_GET['impact_pseudo_id'] ?? null;
    $candidates[] = $_GET['pseudo_id'] ?? null;
    $candidates[] = $_SERVER['HTTP_X_IMPACT_PSEUDO_ID'] ?? null;
    $candidates[] = $_SERVER['HTTP_X_PSEUDO_ID'] ?? null;

    foreach ($candidates as $candidate) {
        $pseudo = ic_normalize_test_pseudo($candidate);
        if ($pseudo !== '') {
            return $pseudo;
        }
    }

    return '';
}

function ic_test_mode_resolve_ngo_slug(string $candidate = ''): string {
    global $wpdb;
    $p = $wpdb->prefix;

    $candidate = sanitize_title($candidate);
    if ($candidate !== '') {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ref_slug FROM {$p}ic_circles WHERE type='ngo' AND is_active=1 AND ref_slug=%s LIMIT 1",
            $candidate
        ));
        if ($exists) {
            return (string) $exists;
        }
    }

    $fallback = $wpdb->get_var("SELECT ref_slug FROM {$p}ic_circles WHERE type='ngo' AND is_active=1 ORDER BY id ASC LIMIT 1");
    return $fallback ? (string) $fallback : '';
}

function ic_test_mode_requested_ngo_slug(?WP_REST_Request $req = null): string {
    $candidates = [];

    if ($req) {
        $candidates[] = $req->get_param('ngo_slug');
        $candidates[] = $req->get_param('impact_ngo_slug');
        $candidates[] = $req->get_header('X-Impact-Ngo-Slug');
    }

    $candidates[] = $_GET['impact_ngo_slug'] ?? null;
    $candidates[] = $_GET['ngo_slug'] ?? null;
    if (function_exists('impactshop_active_ngo_slug')) {
        $candidates[] = impactshop_active_ngo_slug();
    }
    $candidates[] = $_COOKIE['impactshop_active_ngo'] ?? null;
    $candidates[] = $_SERVER['HTTP_X_IMPACT_NGO_SLUG'] ?? null;

    foreach ($candidates as $candidate) {
        $slug = ic_test_mode_resolve_ngo_slug((string) $candidate);
        if ($slug !== '') {
            return $slug;
        }
    }

    return ic_test_mode_resolve_ngo_slug('');
}

function ic_get_pseudo_id(): string {
    if (ic_test_mode_enabled()) {
        $override = ic_test_mode_requested_pseudo();
        if ($override !== '') {
            return $override;
        }
    }

    $raw = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field($_COOKIE['impactshop_pseudo_id']) : '';
    if ($raw === '' || strlen($raw) < 6) {
        return '';
    }
    if (function_exists('impactshop_identity_normalize_pseudo')) {
        return impactshop_identity_normalize_pseudo($raw);
    }
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $raw);
}

function ic_pid_hash(string $pseudo_id = ''): string {
    if ($pseudo_id === '') {
        $pseudo_id = ic_get_pseudo_id();
    }
    if ($pseudo_id === '') {
        return '';
    }
    return hash('sha256', $pseudo_id);
}

/**
 * Rate limit helper via transients.
 */
function ic_rate_check(string $key, int $max, int $window_seconds): bool {
    $count = (int) get_transient($key);
    if ($count >= $max) {
        return false;
    }
    set_transient($key, $count + 1, $window_seconds);
    return true;
}

function ic_json_error(string $message, int $status = 400): WP_Error {
    return new WP_Error('ic_error', $message, ['status' => $status]);
}

function ic_json_ok(array $data = [], int $status = 200): WP_REST_Response {
    $r = new WP_REST_Response($data, $status);
    return $r;
}

function ic_verify_state_nonce(WP_REST_Request $req): true|WP_Error {
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

function ic_maybe_migrate_db(): void {
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
        vote_count         INT UNSIGNED DEFAULT 0,
        helpful_votes      INT UNSIGNED DEFAULT 0,
        impi_boost         TINYINT(1) DEFAULT 0,
        impi_boost_claimed TINYINT(1) DEFAULT 0,
        is_pinned          TINYINT(1) DEFAULT 0,
        is_deleted    TINYINT(1) DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_circle_created (circle_id, created_at),
        KEY idx_author (author_hash),
        KEY idx_post_type (post_type)
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

    /* Sprint 3 tables */

    dbDelta("CREATE TABLE {$p}ic_post_reactions (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id       BIGINT UNSIGNED NOT NULL,
        circle_id     INT UNSIGNED NOT NULL,
        pid_hash      VARCHAR(64) NOT NULL,
        reaction_type ENUM('thanks','useful','support','done') NOT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reaction (post_id, pid_hash),
        KEY idx_post (post_id),
        KEY idx_circle (circle_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_invites (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id     INT UNSIGNED NOT NULL,
        inviter_hash  VARCHAR(64) NOT NULL,
        ref_code      VARCHAR(32) NOT NULL,
        conversions   SMALLINT UNSIGNED DEFAULT 0,
        max_uses      SMALLINT UNSIGNED DEFAULT 20,
        is_active     TINYINT(1) DEFAULT 1,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_code (ref_code),
        KEY idx_inviter (inviter_hash)
    ) $charset;");

    /* Sprint 4 tables */

    dbDelta("CREATE TABLE {$p}ic_circle_leaderboard (
        id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id    INT UNSIGNED NOT NULL,
        pid_hash     VARCHAR(64) NOT NULL,
        alias        VARCHAR(120) NOT NULL,
        score        INT UNSIGNED DEFAULT 0,
        rank         SMALLINT UNSIGNED DEFAULT 0,
        badge_count  TINYINT UNSIGNED DEFAULT 0,
        period       VARCHAR(10) NOT NULL,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_period_member (circle_id, pid_hash, period),
        KEY idx_circle_period (circle_id, period)
    ) $charset;");

    /* Sprint 10 tables */

    dbDelta("CREATE TABLE {$p}ic_member_trust (
        id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id         INT UNSIGNED NOT NULL,
        pid_hash          VARCHAR(64) NOT NULL,
        trust_level       TINYINT UNSIGNED DEFAULT 0,
        days_active       SMALLINT UNSIGNED DEFAULT 0,
        posts_count       INT UNSIGNED DEFAULT 0,
        votes_received    INT UNSIGNED DEFAULT 0,
        strikes           TINYINT UNSIGNED DEFAULT 0,
        timeout_until     DATETIME NULL DEFAULT NULL,
        constitution_ver  VARCHAR(20) DEFAULT '',
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_trust (circle_id, pid_hash),
        KEY idx_pid (pid_hash)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_reports (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id     INT UNSIGNED NOT NULL,
        post_id       BIGINT UNSIGNED NOT NULL,
        reporter_hash VARCHAR(64) NOT NULL,
        reason        VARCHAR(100) NOT NULL DEFAULT '',
        details       TEXT,
        status        ENUM('pending','reviewed','dismissed','actioned') DEFAULT 'pending',
        reviewed_at   DATETIME NULL DEFAULT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_post (post_id),
        KEY idx_circle_status (circle_id, status)
    ) $charset;");

    /* Sprint A tables — invite claims tracking */

    dbDelta("CREATE TABLE {$p}ic_invite_claims (
        id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ref_code              VARCHAR(32) NOT NULL,
        invitee_hash          VARCHAR(64) NOT NULL,
        inviter_hash          VARCHAR(64) NOT NULL,
        circle_id             INT UNSIGNED NOT NULL,
        first_post_rewarded   TINYINT(1) DEFAULT 0,
        active30_rewarded     TINYINT(1) DEFAULT 0,
        claimed_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_claim (ref_code, invitee_hash),
        KEY idx_invitee (invitee_hash),
        KEY idx_inviter (inviter_hash)
    ) $charset;");

    /* Sprint A/C — bonus_votes column for invite + badge vote rewards */
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_member_trust LIKE 'bonus_votes'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_member_trust ADD COLUMN bonus_votes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER votes_received");
    }

    /* Sprint B tables — tombola & auction */

    dbDelta("CREATE TABLE {$p}ic_tombolas (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id     INT UNSIGNED NOT NULL,
        ngo_slug      VARCHAR(120) NOT NULL,
        title         VARCHAR(200) NOT NULL,
        description   TEXT,
        prize_image   VARCHAR(500),
        prize_json    JSON,
        ticket_cost   INT UNSIGNED DEFAULT 0,
        max_tickets   INT UNSIGNED DEFAULT 100,
        max_per_user  TINYINT UNSIGNED DEFAULT 5,
        tickets_sold  INT UNSIGNED DEFAULT 0,
        status        ENUM('active','drawn','cancelled') DEFAULT 'active',
        ends_at       DATETIME NOT NULL,
        winner_hash   VARCHAR(64),
        drawn_at      DATETIME,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_circle (circle_id),
        INDEX idx_ngo (ngo_slug),
        INDEX idx_status (status, ends_at)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_tombola_tickets (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tombola_id   INT UNSIGNED NOT NULL,
        pid_hash     VARCHAR(64) NOT NULL,
        ticket_count TINYINT UNSIGNED DEFAULT 1,
        pts_spent    INT UNSIGNED DEFAULT 0,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_tombola (tombola_id, pid_hash),
        INDEX idx_tombola (tombola_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_auctions (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id       INT UNSIGNED NOT NULL,
        ngo_slug        VARCHAR(120) NOT NULL,
        title           VARCHAR(200) NOT NULL,
        description     TEXT,
        item_image      VARCHAR(500),
        item_json       JSON,
        starting_bid    INT UNSIGNED DEFAULT 50,
        current_bid     INT UNSIGNED DEFAULT 0,
        current_bidder  VARCHAR(64),
        bid_count       INT UNSIGNED DEFAULT 0,
        ends_at         DATETIME NOT NULL,
        extended_to     DATETIME,
        status          ENUM('active','closed','cancelled') DEFAULT 'active',
        winner_hash     VARCHAR(64),
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_circle (circle_id),
        INDEX idx_ngo (ngo_slug),
        INDEX idx_status (status, ends_at)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_auction_bids (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id  INT UNSIGNED NOT NULL,
        pid_hash    VARCHAR(64) NOT NULL,
        bid_amount  INT UNSIGNED NOT NULL,
        is_active   TINYINT(1) DEFAULT 1,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_auction (auction_id, bid_amount),
        INDEX idx_user (pid_hash)
    ) $charset;");

    /* §10 Sprint tables */

    dbDelta("CREATE TABLE {$p}ic_sprints (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        status       ENUM('active','closed') DEFAULT 'active',
        starts_at    DATETIME NOT NULL,
        ends_at      DATETIME NOT NULL,
        cohort_json  JSON,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_status (status)
    ) $charset;");

    dbDelta("CREATE TABLE {$p}ic_sprint_events (
        id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sprint_id          INT UNSIGNED NOT NULL,
        ngo_slug           VARCHAR(120) NOT NULL,
        pid_hash           VARCHAR(64) NOT NULL,
        activation_type    ENUM('ad','vote','offerwall','post','other') NOT NULL DEFAULT 'other',
        is_validated       TINYINT(1) DEFAULT 0,
        is_pending_review  TINYINT(1) DEFAULT 0,
        created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sprint_pid_ngo (sprint_id, pid_hash, ngo_slug),
        KEY idx_sprint_ngo (sprint_id, ngo_slug),
        KEY idx_pid (pid_hash)
    ) $charset;");

    /* §15 Moderation audit log */

    dbDelta("CREATE TABLE {$p}ic_moderation_actions (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        circle_id   INT UNSIGNED NOT NULL,
        post_id     BIGINT UNSIGNED,
        target_hash VARCHAR(64),
        actor_hash  VARCHAR(64) NOT NULL DEFAULT 'system',
        actor_type  ENUM('admin','ngo_admin','system','trusted_reporter') DEFAULT 'admin',
        action      VARCHAR(60) NOT NULL,
        reason      TEXT,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_circle (circle_id),
        KEY idx_target (target_hash),
        KEY idx_action (action)
    ) $charset;");

    /* §15 Appeal requests */

    dbDelta("CREATE TABLE {$p}ic_appeals (
        id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        modaction_id   BIGINT UNSIGNED NOT NULL,
        appellant_hash VARCHAR(64) NOT NULL,
        appeal_reason  TEXT NOT NULL,
        status         ENUM('pending','approved','upheld') DEFAULT 'pending',
        reviewed_by    VARCHAR(64),
        reviewed_at    DATETIME NULL,
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_appeal (modaction_id, appellant_hash),
        KEY idx_status (status)
    ) $charset;");

    /* §10 new columns */
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_circles LIKE 'visibility_boost_until'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_circles ADD COLUMN visibility_boost_until DATETIME NULL DEFAULT NULL AFTER is_active");
    }
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_circles LIKE 'community_bonus'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_circles ADD COLUMN community_bonus FLOAT NOT NULL DEFAULT 1.0 AFTER visibility_boost_until");
    }

    /* §15 trusted reporter flag */
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_member_trust LIKE 'is_trusted_reporter'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_member_trust ADD COLUMN is_trusted_reporter TINYINT(1) NOT NULL DEFAULT 0 AFTER strikes");
    }

    /* §13 NGO accounts */
    dbDelta("CREATE TABLE {$p}ic_ngo_accounts (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ngo_slug      VARCHAR(120) NOT NULL,
        email         VARCHAR(254) NOT NULL,
        pw_hash       VARCHAR(255) NOT NULL,
        reset_token   VARCHAR(64) DEFAULT NULL,
        reset_expires DATETIME DEFAULT NULL,
        last_login    DATETIME DEFAULT NULL,
        is_active     TINYINT(1) NOT NULL DEFAULT 1,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slug (ngo_slug),
        UNIQUE KEY uq_email (email)
    ) $charset;");

    /* §13 Advisor usage tracking */
    $wpdb->query("CREATE TABLE IF NOT EXISTS `{$p}ic_ngo_advisor_usage` (
        `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `ngo_slug`   VARCHAR(120) NOT NULL,
        `channel`    ENUM('legal','finance','marketing') NOT NULL,
        `year_month` CHAR(7) NOT NULL,
        `units_used` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_ngo_chan_month` (`ngo_slug`, `channel`, `year_month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* §13 Email blast monthly tracking */
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_circles LIKE 'last_blast_at'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_circles ADD COLUMN last_blast_at DATETIME DEFAULT NULL AFTER community_bonus");
    }

    /* § Sprint 12 — Tombola ritual columns */
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_tombolas LIKE 'ritual_enabled'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_tombolas ADD COLUMN ritual_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER max_per_user");
        $wpdb->query("ALTER TABLE {$p}ic_tombolas ADD COLUMN activity_gate_pts INT UNSIGNED NOT NULL DEFAULT 0 AFTER ritual_enabled");
        $wpdb->query("ALTER TABLE {$p}ic_tombolas ADD COLUMN ritual_posted TINYINT(1) NOT NULL DEFAULT 0 AFTER activity_gate_pts");
    }

    /* § Sprint 14 — Sprint fraud_flag for retention compliance (§22.3) */
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}ic_sprint_events LIKE 'fraud_flag'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}ic_sprint_events ADD COLUMN fraud_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pending_review");
    }

    /* § Sprint 16 — ic_point_log: circle-level activity point log (§16 stats cron) */
    $wpdb->query("CREATE TABLE IF NOT EXISTS `{$p}ic_point_log` (
        `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `circle_id`  INT UNSIGNED NOT NULL,
        `pid_hash`   VARCHAR(64) NOT NULL,
        `pts`        INT NOT NULL,
        `type`       VARCHAR(64) NOT NULL,
        `earned_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_circle_date` (`circle_id`, `earned_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* Seed system-level micro-missions if table was just created */
    $has_missions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}ic_missions");
    if ($has_missions === 0) {
        ic_seed_missions($wpdb, $p);
    }

    update_option('ic_db_version', IC_DB_VERSION);
}

function ic_seed_missions(\wpdb $wpdb, string $p): void {
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
function ic_seed_ngo_circles(): int {
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

function ic_register_rest_routes(): void {
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

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)', [
        'methods'  => 'DELETE',
        'callback' => 'ic_rest_post_delete',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/react', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_post_react',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/helpful_vote', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_post_helpful_vote',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/report', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_post_report',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<id>\d+)/leaderboard', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_circle_leaderboard',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/circles/(?P<id>\d+)/invite', [
        'methods'  => 'POST',
        'callback' => 'ic_rest_create_invite',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/invite/(?P<ref_code>[a-zA-Z0-9]{10})', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_invite_landing',
        'permission_callback' => '__return_true',
    ]);

    /* --- Auth / Nonce --------------------------------------------------- */

    /* --- §13 NGO admin auth + advisor --------------------------------------- */
    register_rest_route($ns, '/ngo/login', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_login',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/reset-password', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_reset_request',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/reset-password/confirm', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_reset_confirm',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/circle', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_ngo_circle_stats',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/circle/blast', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_blast',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/advisor/quota', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_ngo_advisor_quota',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/advisor/ask', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_advisor_ask',
        'permission_callback' => '__return_true',
    ]);

    // § Sprint 8 — Impi üzenetek NGO admin
    register_rest_route($ns, '/ngo/impi-posts', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_ngo_impi_posts',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/impi-posts/(?P<post_id>\d+)', [
        'methods'             => 'DELETE',
        'callback'            => 'ic_rest_ngo_impi_delete',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($ns, '/ngo/tombola', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_create_tombola',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/ngo/auction', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_create_auction',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/circles/(?P<id>\d+)/tombolas', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_circle_tombolas',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/tombolas/(?P<id>\d+)/buy', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_tombola_buy',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/tombolas/(?P<id>\d+)/result', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_tombola_result',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/circles/(?P<id>\d+)/auctions', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_circle_auctions',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/auctions/(?P<id>\d+)/bid', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_auction_bid',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/auctions/(?P<id>\d+)/bids', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_auction_bids_list',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/auth/status', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_auth_status',
        'permission_callback' => '__return_true',
    ]);

    /* §10 Sprint routes */
    register_rest_route($ns, '/sprints/current', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_sprint_current',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/sprints/current/leaderboard', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_sprint_leaderboard',
        'permission_callback' => '__return_true',
    ]);
    // § Sprint 9 — aktiváció webhook (NGO token auth)
    register_rest_route($ns, '/ngo/sprint-activate', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_sprint_activate',
        'permission_callback' => '__return_true',
    ]);
    // Sprint pending review queue (platform admin)
    register_rest_route($ns, '/admin/sprint-queue', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_admin_sprint_queue',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/admin/sprint-queue/(?P<event_id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_admin_sprint_queue_action',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);

    /* §15 Moderation routes */
    register_rest_route($ns, '/moderation/mine', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_moderation_mine',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/moderation/action', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_moderation_action',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/appeal', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_post_appeal',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/circles/(?P<id>\d+)/health', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_circle_health',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);

    /* § Sprint 10 — settlement triage + constitution */
    register_rest_route($ns, '/admin/moderation/settlement-queue', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_admin_settlement_queue',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/admin/moderation/settlement-queue/(?P<report_id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_admin_settlement_triage',
        'permission_callback' => function () { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/ngo/constitution/accept', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_constitution_accept',
        'permission_callback' => '__return_true',
    ]);

    // § Sprint 11 — Missions, Decision Post vote, Buddy opt-out
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/missions', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_missions_list',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/missions/(?P<mission_id>\d+)/complete', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_mission_complete',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/buddy/opt-out', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_buddy_optout',
        'permission_callback' => '__return_true',
    ]);
    // § Sprint 12
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/buddy/complete', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_buddy_complete',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/decision-vote', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_decision_vote',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/circles/(?P<circle_id>\d+)/posts/(?P<post_id>\d+)/decision-results', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_decision_results',
        'permission_callback' => '__return_true',
    ]);
    // § Sprint 13 — Appeal review queues
    register_rest_route($ns, '/admin/appeals', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_admin_appeal_queue',
        'permission_callback' => function() { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/admin/appeals/(?P<appeal_id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_admin_appeal_action',
        'permission_callback' => function() { return current_user_can('manage_options'); },
    ]);
    register_rest_route($ns, '/ngo/appeals', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_ngo_appeal_queue',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/appeals/(?P<appeal_id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_appeal_action',
        'permission_callback' => '__return_true',
    ]);
    // § Sprint 14 — NGO moderation panel
    register_rest_route($ns, '/ngo/moderation/reports', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_ngo_moderation_reports',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route($ns, '/ngo/moderation/action', [
        'methods'             => 'POST',
        'callback'            => 'ic_rest_ngo_moderation_action',
        'permission_callback' => '__return_true',
    ]);
}

/* --- Circles handlers --------------------------------------------------- */

function ic_rest_circles_list(WP_REST_Request $req): WP_REST_Response {
    global $wpdb;
    $p    = $wpdb->prefix;
    $type   = sanitize_key($req->get_param('type') ?? '');
    $page   = max(1, (int) ($req->get_param('page') ?? 1));
    $search = sanitize_text_field($req->get_param('search') ?? '');
    $per    = min(500, max(1, (int) ($req->get_param('per_page') ?? IC_CIRCLES_PER_PAGE)));
    $off    = ($page - 1) * $per;

    $where  = "WHERE is_active = 1";
    $params = [];
    if ($type === 'ngo' || $type === 'settlement') {
        $where  .= " AND type = %s";
        $params[] = $type;
    }
    if ($search !== '') {
        $where  .= " AND name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
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

function ic_rest_circles_mine(WP_REST_Request $req): WP_REST_Response {
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
        ];
    }

    return ic_json_ok(['circles' => $circles]);
}

function ic_rest_circle_detail(WP_REST_Request $req): WP_REST_Response|WP_Error {
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

    $post_ids = $posts ? array_map(fn($r) => (int) $r->id, $posts) : [];
    [$reactions_by_post, $my_reactions] = ic_fetch_reactions($post_ids, $pid_hash ?? '');

    $post_list = [];
    foreach ($posts as $post) {
        $post_list[] = ic_format_post($post, $id, $reactions_by_post[(int)$post->id] ?? [], $my_reactions[(int)$post->id] ?? null);
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

function ic_rest_circle_join(WP_REST_Request $req): WP_REST_Response|WP_Error {
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
    if (!ic_test_mode_enabled() && $current_count >= IC_MAX_CIRCLES) {
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
        $updated = $wpdb->update("{$p}ic_memberships", [
            'is_active' => 1,
            'left_at'   => null,
            'joined_at' => current_time('mysql'),
        ], ['id' => $existing->id]);
        if ($updated === false) {
            return ic_json_error('A csatlakozás sikertelen volt.', 500);
        }
    } else {
        $inserted = $wpdb->insert("{$p}ic_memberships", [
            'circle_id' => $id,
            'pid_hash'  => $pid_hash,
            'joined_at' => current_time('mysql'),
            'is_active' => 1,
        ]);
        if ($inserted === false) {
            return ic_json_error('A csatlakozás sikertelen volt.', 500);
        }
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

    // Milestone trigger
    $new_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT member_count FROM {$p}ic_circles WHERE id=%d", $id
    ));
    if (in_array($new_count, [10, 50, 100], true)) {
        do_action('ic_circle_milestone', $id, $new_count);
    }

    // circle_founder badge: first 10 members of a circle
    if ($new_count <= 10) {
        ic_unlock_badge($pid_hash, 'circle_founder', $id);
    }

    // Invite reward: if joining via ref_code (first join only)
    if (!$existing) {
        $ref_code = sanitize_key($req->get_param('ref_code') ?? '');
        if (strlen($ref_code) === 10) {
            ic_process_invite_join($id, $pid_hash, $ref_code);
        }
    }

    return ic_json_ok([
        'joined'  => true,
        'alias'   => $alias,
        'circle'  => ['id' => $id, 'name' => $circle->name],
    ]);
}

function ic_rest_circle_leave(WP_REST_Request $req): WP_REST_Response|WP_Error {
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

    $updated = $wpdb->update("{$p}ic_memberships", [
        'is_active' => 0,
        'left_at'   => current_time('mysql'),
    ], ['id' => $membership->id]);
    if ($updated === false) {
        return ic_json_error('A kilépés sikertelen volt.', 500);
    }

    $count_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET member_count = GREATEST(member_count - 1, 0) WHERE id = %d", $id
    ));
    if ($count_updated === false) {
        return ic_json_error('A kilépés részben sikerült, de a számláló nem frissült.', 500);
    }

    return ic_json_ok(['left' => true]);
}

function ic_rest_circles_seed(WP_REST_Request $req): WP_REST_Response {
    $count = ic_seed_ngo_circles();
    return ic_json_ok(['seeded' => $count]);
}

/* --- Posts handlers ----------------------------------------------------- */

function ic_rest_posts_list(WP_REST_Request $req): WP_REST_Response {
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

    $post_ids = $rows ? array_map(fn($r) => (int) $r->id, $rows) : [];
    $pid_hash = ic_pid_hash();
    [$reactions_by_post, $my_reactions] = ic_fetch_reactions($post_ids, $pid_hash ?? '');

    $posts = [];
    foreach ($rows as $r) {
        $posts[] = ic_format_post($r, $cid, $reactions_by_post[(int)$r->id] ?? [], $my_reactions[(int)$r->id] ?? null);
    }

    // Lazy Impi Boost claim: if the current user has an unclaimed boost, award +20 pts now
    if ($pid_hash) {
        // Drain any queued rewards (e.g. invite referral bonuses)
        ic_claim_pending_points($pid_hash);

        $unclaimed = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$p}ic_posts
             WHERE author_hash=%s AND impi_boost=1 AND impi_boost_claimed=0
             LIMIT 1",
            $pid_hash
        ));
        if ($unclaimed) {
            ic_award_points(
                $pid_hash, 20, 'impi_boost',
                "post:{$unclaimed->id}",
                "impi_boost:{$unclaimed->id}"
            );
            $wpdb->update(
                "{$p}ic_posts",
                ['impi_boost_claimed' => 1],
                ['id' => (int) $unclaimed->id]
            );
        }
    }

    return ic_json_ok([
        'posts'    => $posts,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per,
    ]);
}

function ic_rest_post_create(WP_REST_Request $req): WP_REST_Response|WP_Error {
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
    $test_mode = ic_test_mode_enabled();

    // Membership check
    if (!$test_mode) {
        $is_member = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
            $cid, $pid_hash
        ));
        if (!$is_member) {
            return ic_json_error('Csak körtagok posztolhatnak.', 403);
        }
    }

    // §15 Auto-timeout check
    if (!$test_mode) {
        $timeout_row = $wpdb->get_row($wpdb->prepare(
            "SELECT timeout_until FROM {$p}ic_member_trust WHERE circle_id=%d AND pid_hash=%s LIMIT 1",
            $cid, $pid_hash
        ));
        if ($timeout_row && $timeout_row->timeout_until && strtotime($timeout_row->timeout_until) > time()) {
            $until = date_i18n(get_option('date_format'), strtotime($timeout_row->timeout_until));
            return ic_json_error("Fiókod átmenetileg korlátozva van {$until}-ig.", 403);
        }
    }

    // Rate limit: 5 posts/hour
    if (!$test_mode) {
        $rate_key = 'ic_post_rate:' . $pid_hash;
        if (!ic_rate_check($rate_key, IC_RATE_LIMIT_POSTS_PER_HOUR, 3600)) {
            return ic_json_error('Túl sok posztot küldtél. Próbáld újra később.', 429);
        }
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

    // § Sprint 11 — Decision Post: V1 NGO admin only
    if ($post_type === 'decision' && !$test_mode) {
        $ngo_auth = ic_ngo_auth_from_header();
        if (!$ngo_auth) {
            return ic_json_error('Decision Posztot csak NGO admin tölthet fel (V1).', 403);
        }
    }

    $meta = null;
    $meta_raw = $req->get_param('meta');
    if ($meta_raw && is_array($meta_raw)) {
        $meta = wp_json_encode($meta_raw);
    }

    // Trust-level URL block: trust_level < 1 cannot post links
    if (!$test_mode && ($post_type === 'link' || (bool) preg_match('/https?:\/\//i', $body))) {
        $trust_level = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT trust_level FROM {$p}ic_member_trust WHERE circle_id=%d AND pid_hash=%s",
            $cid, $pid_hash
        ));
        if ($trust_level < 1) {
            do_action('ic_trust_link_blocked', $cid, $pid_hash);
            return ic_json_error(
                'Link poszoláshoz előbb tér alá három szavazatot és válaszolj a heti kérdésre 👋',
                403
            );
        }
    }

    // Political keyword detection — fires an action, does NOT block the post
    $political_kw = ['pártpolitika', 'fidesz', 'dlsz', 'mszp', 'lmp ', 'momentum', 'mi hazánk', 'demokratikus koalíció', 'választás kampány', 'pártlista'];
    $body_lower   = mb_strtolower($body);
    foreach ($political_kw as $kw) {
        if (mb_strpos($body_lower, $kw) !== false) {
            do_action('ic_political_keyword', $cid, $pid_hash, $body);
            break;
        }
    }

    // § Toxicity friction: soft block with slow_mode flag
    // § Sprint 14 — intention bypass: if user explicitly provides their intent, allow through
    $intention = sanitize_text_field($req->get_param('intention') ?? '');
    if (!$test_mode && ic_check_toxicity($body) && $intention === '') {
        do_action('ic_toxicity_friction', $cid, $pid_hash, $body);
        return new WP_REST_Response([
            'success'   => false,
            'slow_mode' => true,
            'message'   => 'Az üzenet hangvétele feszültséget kelthet. Kérjük fogalmazd át, vagy küldd be mégis a szándékod megjelölésével (intention mező).',
        ], 422);
    }
    // If intention provided, store it in meta_json
    if ($intention !== '') {
        $meta_arr = ($meta_raw && is_array($meta_raw)) ? $meta_raw : [];
        $meta_arr['intention'] = $intention;
        $meta = wp_json_encode($meta_arr);
    }

    $inserted = $wpdb->insert("{$p}ic_posts", [
        'circle_id'   => $cid,
        'author_hash' => $pid_hash,
        'author_type' => 'user',
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

    // Streak tracking — fires ic_streak_7day / ic_streak_14day
    ic_update_post_streak($cid, $pid_hash);

    // Invite first-post reward (invitee + inviter)
    ic_check_invite_first_post($cid, $pid_hash);

    // Badge checks: receipt × 5, decision × 5
    if ($post_type === 'receipt' || $post_type === 'decision') {
        ic_check_post_type_badge($cid, $pid_hash, $post_type);
    }

    // §10 Sprint activation: posting counts as 'post' activation type
    $circle_obj = $wpdb->get_row($wpdb->prepare("SELECT ref_slug FROM {$p}ic_circles WHERE id=%d", $cid));
    if ($circle_obj) {
        ic_record_sprint_activation($pid_hash, $circle_obj->ref_slug, 'post');
    }

    // § Trust auto-promote after posting
    ic_trust_auto_promote($cid, $pid_hash);

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

function ic_rest_post_vote(WP_REST_Request $req): WP_REST_Response|WP_Error {
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
    $test_mode = ic_test_mode_enabled();

    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
        $post_id, $cid
    ));
    if (!$post) {
        return ic_json_error('Poszt nem található.', 404);
    }

    if (!$test_mode) {
        $is_member = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
            $cid, $pid_hash
        ));
        if (!$is_member) {
            return ic_json_error('Csak körtagok szavazhatnak.', 403);
        }
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
        ic_award_points($post->author_hash, 50, 'post_5_votes', "post:{$post_id}", "post_5_votes:{$post->author_hash}:{$post_id}");
    }

    // §10 Sprint activation: vote counts as 'vote' type
    $circle_obj = $wpdb->get_row($wpdb->prepare("SELECT ref_slug FROM {$p}ic_circles WHERE id=%d", $cid));
    if ($circle_obj) {
        ic_record_sprint_activation($pid_hash, $circle_obj->ref_slug, 'vote');
    }

    // § Trust auto-promote after voting
    ic_trust_auto_promote($cid, $pid_hash);

    return ic_json_ok(['vote_count' => $new_count]);
}

function ic_rest_post_react(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param('circle_id');
    $post_id = (int) $req->get_param('post_id');
    $allowed = ['thanks', 'useful', 'support', 'done'];
    $type    = sanitize_key($req->get_param('reaction_type') ?? '');

    if (!in_array($type, $allowed, true)) {
        return ic_json_error('Érvénytelen reakció típus.', 422);
    }

    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
        $post_id, $cid
    ));
    if (!$post) {
        return ic_json_error('Poszt nem található.', 404);
    }
    if ($post->author_hash === $pid_hash) {
        return ic_json_error('Saját posztra nem adhatsz reakciót.', 422);
    }

    // Dedupe: 1 reaction per post per user (any type)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT reaction_type FROM {$p}ic_post_reactions WHERE post_id=%d AND pid_hash=%s",
        $post_id, $pid_hash
    ));
    if ($existing !== null) {
        return ic_json_error('Már reagáltál erre a posztra.', 422);
    }

    $wpdb->insert("{$p}ic_post_reactions", [
        'post_id'       => $post_id,
        'circle_id'     => $cid,
        'pid_hash'      => $pid_hash,
        'reaction_type' => $type,
        'created_at'    => current_time('mysql'),
    ]);

    // Increment legacy vote_count for Circle Health Score backward compat
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_posts SET vote_count = vote_count + 1 WHERE id = %d", $post_id
    ));

    // +3 pts to reaction sender
    ic_award_points($pid_hash, 3, 'reaction_sent', "post:{$post_id}", "reaction_sent:{$pid_hash}:{$post_id}");

    // 5-reaction milestone → bonus for post author
    $total_reactions = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT vote_count FROM {$p}ic_posts WHERE id=%d", $post_id
    ));
    if ($total_reactions === 5) {
        ic_award_points($post->author_hash, 50, 'post_5_reactions', "post:{$post_id}", "post_5_reactions:{$post->author_hash}:{$post_id}");
    }

    [$counts] = ic_fetch_reactions([$post_id], '');
    $reactions = array_merge(
        ['thanks' => 0, 'useful' => 0, 'support' => 0, 'done' => 0],
        $counts[$post_id] ?? []
    );

    return ic_json_ok([
        'reacted'       => true,
        'reaction_type' => $type,
        'reactions'     => $reactions,
        'vote_count'    => $total_reactions,
    ]);
}

function ic_rest_post_helpful_vote(WP_REST_Request $req): WP_REST_Response|WP_Error {
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
    if ($post->author_hash === $pid_hash) {
        return ic_json_error('Saját posztot nem jelölhetsz hasznosnak.', 422);
    }

    $dedupe_key = "ic_hvote:{$pid_hash}:{$post_id}";
    if (get_transient($dedupe_key)) {
        return ic_json_error('Már jelölted ezt a posztot hasznosnak.', 422);
    }
    set_transient($dedupe_key, 1, DAY_IN_SECONDS * 365);

    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_posts SET helpful_votes = helpful_votes + 1 WHERE id = %d", $post_id
    ));

    $new_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT helpful_votes FROM {$p}ic_posts WHERE id=%d", $post_id
    ));

    return ic_json_ok(['helpful_votes' => $new_count]);
}

function ic_rest_post_report(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param('circle_id');
    $post_id = (int) $req->get_param('post_id');

    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
        $post_id, $cid
    ));
    if (!$post) {
        return ic_json_error('Poszt nem található.', 404);
    }

    $reason  = sanitize_text_field($req->get_param('reason') ?? '');
    $details = sanitize_textarea_field($req->get_param('details') ?? '');
    if ($reason === '') {
        return ic_json_error('Add meg a bejelentés okát.', 422);
    }

    // Rate: max 3 reports per day per user
    $rate_key = 'ic_report_rate:' . $pid_hash;
    if (!ic_rate_check($rate_key, 3, DAY_IN_SECONDS)) {
        return ic_json_error('Napi bejelentési limit elérve.', 429);
    }

    $wpdb->insert("{$p}ic_reports", [
        'circle_id'     => $cid,
        'post_id'       => $post_id,
        'reporter_hash' => $pid_hash,
        'reason'        => $reason,
        'details'       => $details,
        'status'        => 'pending',
        'created_at'    => current_time('mysql'),
    ]);

    // Report spike detection: 3+ reports in the last 24h → safety nudge
    $recent_reports = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_reports
         WHERE circle_id=%d AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $cid
    ));
    if ($recent_reports >= 3) {
        do_action('ic_report_spike', $cid, $recent_reports);
    }

    return ic_json_ok(['reported' => true]);
}

function ic_rest_circle_leaderboard(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p   = $wpdb->prefix;
    $cid = (int) $req->get_param('id');

    $circle = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE id=%d AND is_active=1", $cid
    ));
    if (!$circle) {
        return ic_json_error('Kör nem található.', 404);
    }

    $period = date('Y-m');

    // Try cached leaderboard first
    $cached = $wpdb->get_results($wpdb->prepare(
        "SELECT alias, score, rank, badge_count FROM {$p}ic_circle_leaderboard
         WHERE circle_id=%d AND period=%s ORDER BY rank ASC LIMIT 10",
        $cid, $period
    ));

    if (!empty($cached)) {
        $leaderboard = [];
        foreach ($cached as $r) {
            $leaderboard[] = [
                'rank'        => (int) $r->rank,
                'alias'       => $r->alias,
                'score'       => (int) $r->score,
                'badge_count' => (int) $r->badge_count,
            ];
        }
    } else {
        // Compute live from posts + reactions in current month
        $month_start = date('Y-m-01');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.author_hash,
                    COUNT(DISTINCT p.id) * 30 + COALESCE(SUM(p.vote_count), 0) * 5 AS score
             FROM {$p}ic_posts p
             WHERE p.circle_id=%d AND p.is_deleted=0 AND p.author_type='user'
               AND p.created_at >= %s
             GROUP BY p.author_hash
             ORDER BY score DESC LIMIT 10",
            $cid, $month_start
        ));
        $leaderboard = [];
        $rank = 1;
        foreach ($rows as $r) {
            $leaderboard[] = [
                'rank'        => $rank++,
                'alias'       => IC_Alias::generate($r->author_hash, $cid),
                'score'       => (int) $r->score,
                'badge_count' => 0,
            ];
        }
    }

    return ic_json_ok(['leaderboard' => $leaderboard, 'period' => $period]);
}

function ic_rest_post_delete(WP_REST_Request $req): WP_REST_Response|WP_Error {
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

/* --- Auth handler ------------------------------------------------------- */

function ic_rest_auth_status(WP_REST_Request $req): WP_REST_Response {
    $pseudo = ic_get_pseudo_id();
    $pid_hash = ic_pid_hash($pseudo);
    $test_mode = ic_test_mode_enabled();

    return ic_json_ok([
        'authenticated' => $pseudo !== '',
        'pid_hash'      => $pid_hash ? substr($pid_hash, 0, 8) . '...' : '',
        'nonce'         => wp_create_nonce('wp_rest'),
        'pseudo_id'     => $pseudo,
        'test_mode'     => $test_mode,
        'ngo_slug'      => $test_mode ? ic_test_mode_requested_ngo_slug($req) : '',
        'ngo_admin_url' => site_url('/impact-shop_ngo/'),
    ]);
}

/* --- Helpers ------------------------------------------------------------- */

/**
 * Batch-fetch reaction counts and the current user's reaction for a set of post IDs.
 *
 * @param int[]  $post_ids
 * @param string $pid_hash Current user's hash; pass '' to skip my_reaction lookup.
 * @return array{0: array<int,array<string,int>>, 1: array<int,string>}
 *              [$counts_by_post_id, $my_reaction_by_post_id]
 */
function ic_fetch_reactions(array $post_ids, string $pid_hash): array {
    if (empty($post_ids)) {
        return [[], []];
    }

    global $wpdb;
    $p            = $wpdb->prefix;
    $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
    $rows         = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, reaction_type, pid_hash FROM {$p}ic_post_reactions WHERE post_id IN ($placeholders)",
            ...$post_ids
        )
    );

    $zero   = ['thanks' => 0, 'useful' => 0, 'support' => 0, 'done' => 0];
    $counts = [];
    $my     = [];

    foreach ($rows as $r) {
        $pid = (int) $r->post_id;
        if (!isset($counts[$pid])) {
            $counts[$pid] = $zero;
        }
        $counts[$pid][$r->reaction_type]++;
        if ($pid_hash !== '' && $r->pid_hash === $pid_hash) {
            $my[$pid] = $r->reaction_type;
        }
    }

    return [$counts, $my];
}

function ic_format_post(object $post, int $circle_id, array $reactions = [], ?string $my_reaction = null): array {
    $zero = ['thanks' => 0, 'useful' => 0, 'support' => 0, 'done' => 0];
    return [
        'id'            => (int) $post->id,
        'circle_id'     => (int) $post->circle_id,
        'author_alias'  => IC_Alias::generate($post->author_hash, $circle_id),
        'author_type'   => $post->author_type,
        'post_type'     => $post->post_type,
        'body'          => esc_html($post->body),
        'meta'          => $post->meta_json ? json_decode($post->meta_json, true) : null,
        'vote_count'    => (int) $post->vote_count,
        'helpful_votes' => (int) $post->helpful_votes,
        'reactions'     => array_merge($zero, $reactions),
        'my_reaction'   => $my_reaction,
        'is_pinned'     => (bool) $post->is_pinned,
        'impi_boost'    => (bool) $post->impi_boost,
        'is_own'        => $post->author_hash === ic_pid_hash(),
        'created_at'    => $post->created_at,
        'time_ago'      => ic_time_ago($post->created_at),
    ];
}

function ic_time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'most';
    if ($diff < 3600) return floor($diff / 60) . ' perce';
    if ($diff < 86400) return floor($diff / 3600) . ' órája';
    if ($diff < 2592000) return floor($diff / 86400) . ' napja';
    return date('Y. m. d.', strtotime($datetime));
}

function ic_award_points(string $pid_hash, int $points, string $type, string $source_id, string $dedupe_key): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // § Sprint 16 — log to ic_point_log for circle-level stats cron (§16)
    // Extract circle_id from source_id patterns like "circle:123" or "post:456"
    $circle_id = 0;
    if (preg_match('/^circle:(\d+)/', $source_id, $m)) {
        $circle_id = (int) $m[1];
    } elseif (preg_match('/^post:(\d+)/', $source_id, $m)) {
        $post_row = $wpdb->get_var($wpdb->prepare(
            "SELECT circle_id FROM {$p}ic_posts WHERE id = %d LIMIT 1", (int) $m[1]
        ));
        $circle_id = (int) ($post_row ?? 0);
    }
    if ($circle_id > 0) {
        $wpdb->insert("{$p}ic_point_log", [
            'circle_id' => $circle_id,
            'pid_hash'  => $pid_hash,
            'pts'       => $points,
            'type'      => $type,
            'earned_at' => current_time('mysql'),
        ]);
    }

    if (!class_exists('Sharity_Points_Manager')) {
        return;
    }
    $mgr = new Sharity_Points_Manager();
    // We need the actual pseudo_id, not the hash. Since we only have hash, we use it directly
    // The points manager needs the pseudo_id, we'll pass through the award_points_for_pseudo method
    $pseudo_id = ic_get_pseudo_id();
    if ($pseudo_id === '') {
        return;
    }
    $mgr->award_points_for_pseudo($pseudo_id, $points, 'community_activity', $source_id, [
        'activity' => $type,
        'source_type' => 'impact_community',
    ], $dedupe_key);
}

function ic_try_buddy_pair(int $circle_id, string $pid_hash): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // Find an unmatched member (who joined recently and doesn't have a buddy yet)
    $candidate = $wpdb->get_var($wpdb->prepare(
        "SELECT m.pid_hash FROM {$p}ic_memberships m
         LEFT JOIN {$p}ic_buddies b1 ON b1.circle_id = m.circle_id AND b1.pid_a = m.pid_hash AND b1.opt_out_at IS NULL
         LEFT JOIN {$p}ic_buddies b2 ON b2.circle_id = m.circle_id AND b2.pid_b = m.pid_hash AND b2.opt_out_at IS NULL
         WHERE m.circle_id = %d AND m.pid_hash != %s AND m.is_active = 1
           AND b1.id IS NULL AND b2.id IS NULL
         ORDER BY m.joined_at DESC LIMIT 1",
        $circle_id, $pid_hash
    ));

    if (!$candidate) {
        return;
    }

    $pair = [$pid_hash, $candidate];
    sort($pair); // deterministic order

    $wpdb->insert("{$p}ic_buddies", [
        'circle_id'  => $circle_id,
        'pid_a'      => $pair[0],
        'pid_b'      => $pair[1],
        'started_at' => current_time('mysql'),
    ]);
}

/* =========================================================================
   Section: Impi — 🦡 Community Bot
   Lightweight, rate-limited bot that posts contextual encouragement.
   Max 3 normal Impi posts per circle per day.
   Priority 1 (safety nudges) bypass normal limit (own 2/day counter).
   ========================================================================= */

/**
 * Write policy gate — must be checked before every public Impi action.
 * low  = auto publish (templates, welcome, weekly Q)
 * medium = template-only OK (safety nudges)
 * high = NGO approval required (not yet implemented → always false)
 */
function impi_can_write(string $risk_level, int $circle_id): bool {
    if (defined('IMPI_READ_ONLY') && IMPI_READ_ONLY) return false;
    if ($risk_level === 'high') return false; // approval pipeline not yet built
    return true; // low + medium auto-OK
}

/**
 * @param int    $circle_id
 * @param string $body
 * @param int    $priority  1=safety (own rate), 2=welcome, 3=milestone, 4=tombola,
 *                          5=sprint/inaktív, 6=scheduled/morning (default, lowest prio)
 */
function ic_impi_post(int $circle_id, string $body, int $priority = 6): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $risk = ($priority === 1) ? 'medium' : 'low';
    if (!impi_can_write($risk, $circle_id)) return;

    if ($priority === 1) {
        // Safety nudges: own counter, max 2/day; never blocked by the normal limit
        $safety_key = 'ic_impi_safety:' . $circle_id . ':' . date('Y-m-d');
        $sc = (int) get_transient($safety_key);
        if ($sc >= 2) return;
        set_transient($safety_key, $sc + 1, DAY_IN_SECONDS);
    } else {
        // Normal rate-limit: max 3 per circle per calendar day
        $rate_key = 'ic_impi_rate:' . $circle_id . ':' . date('Y-m-d');
        $count    = (int) get_transient($rate_key);
        if ($count >= 3) return;
        set_transient($rate_key, $count + 1, DAY_IN_SECONDS);
    }

    $wpdb->insert("{$p}ic_posts", [
        'circle_id'   => $circle_id,
        'author_hash' => 'impi',
        'author_type' => 'impi',
        'post_type'   => 'text',
        'body'        => $body,
        'meta_json'   => null,
        'created_at'  => current_time('mysql'),
    ]);
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET post_count = post_count + 1 WHERE id = %d", $circle_id
    ));
}

add_action('ic_member_joined', 'ic_impi_welcome_member', 10, 3);
function ic_impi_welcome_member(int $circle_id, string $pid_hash, string $alias): void {
    ic_impi_post(
        $circle_id,
        "Üdvözöllek, {$alias}! 🦡 Én Impi vagyok, a körünk kis szurikátája. " .
        "Örülök, hogy csatlakoztál! Merj posztolni — itt mindenki baráti fülekkel hallgat."
    );
}

add_action('ic_circle_milestone', 'ic_impi_milestone_celebrate', 10, 2);
function ic_impi_milestone_celebrate(int $circle_id, int $count): void {
    $messages = [
        10  => "🎉 Elértük a 10 tagot! Gratulálok a körnek — ez csak a kezdet!",
        50  => "🚀 50 tag! Valami különleges épül itt. Köszönjük, hogy velünk tartotok!",
        100 => "💯 100 körtagot értünk el! Ez már igazi közösség. Ti tettétek!",
    ];
    if (isset($messages[$count])) {
        ic_impi_post($circle_id, $messages[$count]);
    }
}

add_action('ic_streak_7day', 'ic_impi_streak_congrats', 10, 2);
function ic_impi_streak_congrats(int $circle_id, string $pid_hash): void {
    $alias = IC_Alias::generate($pid_hash, $circle_id);
    ic_impi_post(
        $circle_id,
        "🔥 {$alias} 7 napos sorozatot ért el! Ez igazi elköteleződés. Ünnepelünk együtt!"
    );
}

/* --- 5b. Morning boost ---------------------------------------------------
   Fires when the first user post of the day is made in a circle.
   ic_first_post_of_day is already triggered in ic_rest_post_create().
   -------------------------------------------------------------------------*/

add_action('ic_first_post_of_day', 'ic_impi_morning_boost', 10, 2);
function ic_impi_morning_boost(int $circle_id, string $pid_hash): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // Confirm this is truly the FIRST user post today in this circle
    $today_posts = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts
         WHERE circle_id=%d AND author_type='user' AND is_deleted=0
           AND DATE(created_at) = CURDATE()",
        $circle_id
    ));
    if ($today_posts !== 1) return;

    $alias = IC_Alias::generate($pid_hash, $circle_id);
    ic_impi_post(
        $circle_id,
        "A kör ébred! 🌅 {$alias} elindította a mai napot — ki csatlakozik?",
        6
    );
}

/* --- 5c. Safety nudge handlers (priority 1 — bypass normal rate limit) -- */

add_action('ic_report_spike', 'ic_impi_safety_nudge_reports', 10, 2);
function ic_impi_safety_nudge_reports(int $circle_id, int $count): void {
    ic_impi_post(
        $circle_id,
        "🛡️ Emlékeztető: a Hatás Körök az ügyekről szólnak — nem egymásról. Mi legyen a következő közös lépés?",
        1
    );
}

add_action('ic_political_keyword', 'ic_impi_safety_nudge_politics', 10, 3);
function ic_impi_safety_nudge_politics(int $circle_id, string $pid_hash, string $body): void {
    // Deduplicate: max 1 political nudge per circle per 6h
    $dedup_key = 'ic_impi_polnudge:' . $circle_id . ':' . date('Y-m-d-H');
    if (get_transient($dedup_key)) return;
    set_transient($dedup_key, 1, 6 * HOUR_IN_SECONDS);

    ic_impi_post(
        $circle_id,
        "💬 Ez pártpolitika-szagú... hozzuk vissza a cselekvésre: mit tudunk ma tenni?",
        1
    );
}

add_action('ic_trust_link_blocked', 'ic_impi_trust_level_reminder', 10, 2);
function ic_impi_trust_level_reminder(int $circle_id, string $pid_hash): void {
    // The REST endpoint already returns an error to the user.
    // Impi posts a gentle reminder to the CIRCLE max once/day.
    $dedup_key = 'ic_impi_trustblock:' . $circle_id . ':' . date('Y-m-d');
    if (get_transient($dedup_key)) return;
    set_transient($dedup_key, 1, DAY_IN_SECONDS);

    ic_impi_post(
        $circle_id,
        "🔗 Emlékeztető: linkeket csak megbízható körtagok posztolhatnak. Az bizalmat posztolással és szavazásokkal lehet felépíteni 👋",
        2
    );
}

add_action('ic_low_positive_reactions', 'ic_impi_gratitude_nudge', 10, 1);
function ic_impi_gratitude_nudge(int $circle_id): void {
    ic_impi_post(
        $circle_id,
        "🙏 Adj egy Köszönetet valakinek — ez a legjobb kultúraépítés!",
        2
    );
}

/* --- 5d. WP Cron setup -------------------------------------------------- */

add_filter('cron_schedules', 'ic_cron_add_schedules');
function ic_cron_add_schedules(array $schedules): array {
    if (!isset($schedules['ic_weekly'])) {
        $schedules['ic_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => 'Hetente egyszer (ImpactCommunity)',
        ];
    }
    return $schedules;
}

add_action('init', 'ic_schedule_crons');
function ic_schedule_crons(): void {
    // Weekly question — every Friday 10:00 (first run: next Friday)
    if (!wp_next_scheduled('ic_weekly_question')) {
        $next_friday = strtotime('next friday 10:00');
        wp_schedule_event($next_friday, 'ic_weekly', 'ic_weekly_question');
    }
    // Impi Boost — every Monday 08:00
    if (!wp_next_scheduled('ic_impi_boost_weekly')) {
        $next_monday = strtotime('next monday 08:00');
        wp_schedule_event($next_monday, 'ic_weekly', 'ic_impi_boost_weekly');
    }
    // Daily inactive member check — 09:00
    if (!wp_next_scheduled('ic_impi_inactive_check')) {
        wp_schedule_event(strtotime('tomorrow 09:00'), 'daily', 'ic_impi_inactive_check');
    }
    // Daily low-reactions check — 20:00
    if (!wp_next_scheduled('ic_low_positive_check')) {
        wp_schedule_event(strtotime('tomorrow 20:00'), 'daily', 'ic_low_positive_check');
    }
    // Monthly badge awards — 1st of month 01:00
    if (!wp_next_scheduled('ic_monthly_badge_award')) {
        wp_schedule_event(strtotime('first day of next month 01:00'), 'monthly', 'ic_monthly_badge_award');
    }
    // Daily tombola draw — 02:00
    if (!wp_next_scheduled('ic_tombola_draw_cron')) {
        wp_schedule_event(strtotime('tomorrow 02:00'), 'daily', 'ic_tombola_draw_cron');
    }
    // Hourly auction close check
    if (!wp_next_scheduled('ic_auction_close_cron')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'ic_auction_close_cron');
    }
    // §10 Sprint: daily check for sprint open/close — 03:00
    if (!wp_next_scheduled('ic_sprint_daily_check')) {
        wp_schedule_event(strtotime('tomorrow 03:00'), 'daily', 'ic_sprint_daily_check');
    }
    // §12 Settlement: monthly check — 1st of month 04:00
    if (!wp_next_scheduled('ic_settlement_monthly')) {
        wp_schedule_event(strtotime('first day of next month 04:00'), 'monthly', 'ic_settlement_monthly');
    }
    // §15 Trusted reporter promotion: weekly Sunday 05:00
    if (!wp_next_scheduled('ic_trusted_reporter_check')) {
        wp_schedule_event(strtotime('next sunday 05:00'), 'ic_weekly', 'ic_trusted_reporter_check');
    }
    // §16 Daily circle stats snapshot: 01:30 daily
    if (!wp_next_scheduled('ic_daily_circle_stats')) {
        wp_schedule_event(strtotime('tomorrow 01:30'), 'daily', 'ic_daily_circle_stats');
    }
    // § Sprint 10 — Monthly Circle Health Score refresh: 1st of month 05:00
    if (!wp_next_scheduled('ic_monthly_health_refresh')) {
        wp_schedule_event(strtotime('first day of next month 05:00'), 'monthly', 'ic_monthly_health_refresh');
    }
    // § Sprint 11 — Buddy retention soft-delete: daily 03:30
    if (!wp_next_scheduled('ic_buddy_retention_daily')) {
        wp_schedule_event(strtotime('tomorrow 03:30'), 'daily', 'ic_buddy_retention_daily');
    }
    // § Sprint 12 — Tombola ritual Impi question: daily 10:00
    if (!wp_next_scheduled('ic_tombola_ritual_cron')) {
        wp_schedule_event(strtotime('tomorrow 10:00'), 'daily', 'ic_tombola_ritual_cron');
    }
    // § Sprint 15 — §21 Data retention: weekly Sunday 02:00
    if (!wp_next_scheduled('ic_data_retention_weekly')) {
        wp_schedule_event(strtotime('next sunday 02:00'), 'weekly', 'ic_data_retention_weekly');
    }
}

/* --- 5e. Weekly Impi question ------------------------------------------- */

add_action('ic_weekly_question', 'ic_impi_post_weekly_question');
function ic_impi_post_weekly_question(): void {
    global $wpdb;
    $p      = $wpdb->prefix;
    $pool   = ic_impi_question_pool();
    $circles = $wpdb->get_col("SELECT id FROM {$p}ic_circles WHERE is_active=1");
    foreach ($circles as $cid) {
        $q = $pool[ array_rand($pool) ];
        ic_impi_post((int) $cid, "🤔 Heti kérdés Impitől: {$q} — te mit gondolsz?", 6);
    }
}

function ic_impi_question_pool(): array {
    $stored = get_option('ic_impi_question_pool', []);
    if (!empty($stored) && is_array($stored)) return $stored;
    return [
        "Melyik volt az idei legbüszkébb hatás-pillanatod? 🌱",
        "Ha egy mondatban kellene leírni a küldetésünket, mi lenne az?",
        "Melyik lokális problémát oldanátok meg, ha 1 hétig mindenki figyelt volna rátok?",
        "Ki az a személy, aki a legjobban inspirált az ügyünkben? (nevet nem kell mondani)",
        "Mi az a kis lépés, amit holnap meg tudnál tenni a változásért?",
        "Mikor érezted utoljára, hogy igazán számít, amit csináltok?",
        "Milyen erőforrást hiányoltok legjobban a munkátokhoz?",
        "Ha 1 éved lenne és bármilyen erőforrás rendelkezésetekre állna — mit valósítanátok meg?",
        "Melyik közösségi projekt töltött fel a legjobban ebben a hónapban?",
        "Mit tanulhattunk az elmúlt hónap kihívásaiból? Melyikre vagytok a legbüszkébbek?",
    ];
}

/* --- 5f. Impi Boost — heti legjobb poszt kiemelése --------------------- */

add_action('ic_impi_boost_weekly', 'ic_impi_calculate_boost');
function ic_impi_calculate_boost(): void {
    global $wpdb;
    $p       = $wpdb->prefix;
    $circles = $wpdb->get_col("SELECT id FROM {$p}ic_circles WHERE is_active=1");

    foreach ($circles as $cid) {
        // Score = vote_count + 0.5 × reaction_count (last 7 days, user posts only)
        $top = $wpdb->get_row($wpdb->prepare(
            "SELECT p.id, p.author_hash,
                    (p.vote_count + 0.5 * COALESCE(r.cnt, 0)) AS score
             FROM {$p}ic_posts p
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS cnt
                 FROM {$p}ic_post_reactions
                 GROUP BY post_id
             ) r ON r.post_id = p.id
             WHERE p.circle_id = %d
               AND p.author_type = 'user'
               AND p.is_deleted = 0
               AND p.impi_boost = 0
               AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY score DESC
             LIMIT 1",
            $cid
        ));

        if (!$top || $top->score < 1) continue;

        // Mark the post as boosted (unclaimed +20 pts — claimed lazily on next feed load)
        $wpdb->update(
            "{$p}ic_posts",
            ['impi_boost' => 1, 'impi_boost_claimed' => 0],
            ['id' => (int) $top->id]
        );

        // Impi recognition post
        $alias = IC_Alias::generate($top->author_hash, (int) $cid);
        ic_impi_post(
            (int) $cid,
            "🦡 A hét Impi-pick! {$alias} posztja mozgatott meg minket a legjobban. Jól ment — +20 pont jár érte!",
            3
        );

        // Impi Jóindulat Radar — legsegítőkészebb komment (helpful_votes/total ratio)
        $helpful = $wpdb->get_row($wpdb->prepare(
            "SELECT p2.id, p2.author_hash, p2.helpful_votes, COUNT(r2.id) AS total_reactions
             FROM {$p}ic_posts p2
             LEFT JOIN {$p}ic_post_reactions r2 ON r2.post_id = p2.id
             WHERE p2.circle_id = %d
               AND p2.author_type = 'user'
               AND p2.is_deleted = 0
               AND p2.helpful_votes > 0
               AND p2.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY p2.id
             ORDER BY (p2.helpful_votes / (COALESCE(COUNT(r2.id),0) + 1)) DESC
             LIMIT 1",
            $cid
        ));
        if ($helpful && $helpful->helpful_votes >= 2) {
            $h_alias = IC_Alias::generate($helpful->author_hash, (int) $cid);
            ic_impi_post(
                (int) $cid,
                "🤝 Impi Jóindulat Radar: {$h_alias} postja volt a leghasznosabb a héten. Köszönjük!",
                3
            );
        }
    }
}

/* --- 5g. Inactive member wake-up (daily cron) -------------------------- */

add_action('ic_impi_inactive_check', 'ic_impi_check_inactive');
function ic_impi_check_inactive(): void {
    global $wpdb;
    $p       = $wpdb->prefix;
    $circles = $wpdb->get_col("SELECT id FROM {$p}ic_circles WHERE is_active=1");

    foreach ($circles as $cid) {
        // Max 1 wake-up per circle per 7 days
        $cooldown = 'ic_impi_wakeup:' . $cid;
        if (get_transient($cooldown)) continue;

        $inactive = $wpdb->get_row($wpdb->prepare(
            "SELECT m.pid_hash, m.joined_at
             FROM {$p}ic_memberships m
             LEFT JOIN {$p}ic_posts lp
                 ON lp.author_hash = m.pid_hash
                AND lp.circle_id = m.circle_id
                AND lp.author_type = 'user'
                AND lp.is_deleted = 0
             WHERE m.circle_id = %d AND m.is_active = 1
             GROUP BY m.pid_hash, m.joined_at
             HAVING (MAX(lp.created_at) < DATE_SUB(NOW(), INTERVAL 14 DAY))
                 OR (MAX(lp.created_at) IS NULL AND DATEDIFF(NOW(), m.joined_at) > 14)
             LIMIT 1",
            $cid
        ));

        if (!$inactive) continue;

        $alias = IC_Alias::generate($inactive->pid_hash, (int) $cid);
        ic_impi_post(
            (int) $cid,
            "Hiányzol, {$alias} 🦡 Már 14 napja csend van tőled — minek örültél mostanában?",
            5
        );
        set_transient($cooldown, 1, 7 * DAY_IN_SECONDS);
    }
}

/* --- 5h. Low positive reactions check (daily cron) --------------------- */

add_action('ic_low_positive_check', 'ic_impi_check_low_reactions');
function ic_impi_check_low_reactions(): void {
    global $wpdb;
    $p       = $wpdb->prefix;
    $circles = $wpdb->get_col("SELECT id FROM {$p}ic_circles WHERE is_active=1");

    foreach ($circles as $cid) {
        $recent_reactions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_post_reactions r
             JOIN {$p}ic_posts p ON p.id = r.post_id
             WHERE p.circle_id = %d
               AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $cid
        ));

        if ($recent_reactions === 0) {
            do_action('ic_low_positive_reactions', (int) $cid);
        }
    }
}

/* =========================================================================
   6. Shortcode — [impact_community_app]
   ========================================================================= */

add_shortcode('impact_community_app', function () {
    ob_start();
    echo '<div id="ic-app" data-api="' . esc_url(rest_url('impact/v1')) . '" data-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '"></div>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.ImpactCommunity)window.ImpactCommunity.init()});</script>';
    return ob_get_clean();
});

/* =========================================================================
   7. Template Redirect — serve the app at /hatas-korok/
   ========================================================================= */

add_action('template_redirect', 'ic_guard_dev_clone_access', 0);
add_action('template_redirect', 'ic_send_nocache_headers', 1);
add_action('template_redirect', 'ic_send_dev_clone_noindex_headers', 2);
add_action('template_redirect', 'ic_app_template_redirect', 4);

function ic_app_template_redirect(): void {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (!preg_match('~^/hatas-korok(?:-dev)?/?(\?.*)?$~', $uri)) {
        return;
    }

    $api_url = rest_url('impact/v1');
    $nonce   = wp_create_nonce('wp_rest');
    $pseudo  = ic_get_pseudo_id();
    $test_mode = ic_test_mode_enabled();
    $test_ngo_slug = $test_mode ? ic_test_mode_requested_ngo_slug() : '';
    $ngo_admin_url = site_url('/impact-shop_ngo/');

    global $wp_query;
    if (isset($wp_query) && method_exists($wp_query, 'is_404')) {
        $wp_query->is_404 = false;
    }
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');

    require __DIR__ . '/impact-community-app.php';
    exit;
}

/* =========================================================================
   8. Invite landing — template redirect for /invite/{ref_code}
   ========================================================================= */

add_action('template_redirect', 'ic_invite_template_redirect', 3);
function ic_invite_template_redirect(): void {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (!preg_match('~^/invite/([a-zA-Z0-9]{10})/?(\?.*)?$~', $uri, $m)) {
        return;
    }

    $ref_code = $m[1];
    $api_url  = rest_url('impact/v1');
    $nonce    = wp_create_nonce('wp_rest');
    $pseudo   = ic_get_pseudo_id();

    header('Content-Type: text/html; charset=UTF-8');

    require __DIR__ . '/impact-community-app.php';
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.ImpactCommunity) {
            window.ImpactCommunity.openInviteLanding(' . wp_json_encode($ref_code) . ');
        }
    });
    </script>';
    exit;
}

/* =========================================================================
   §7. Meghívó — REST handlers
   ========================================================================= */

function ic_rest_create_invite(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p  = $wpdb->prefix;
    $id = (int) $req->get_param('id');

    // Must be an active member
    $is_member = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
        $id, $pid_hash
    ));
    if (!$is_member) {
        return ic_json_error('Csak körtagok küldhetnek meghívót.', 403);
    }

    // Return existing active invite if already created
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ref_code FROM {$p}ic_invites WHERE circle_id=%d AND inviter_hash=%s AND is_active=1",
        $id, $pid_hash
    ));
    if ($existing) {
        return ic_json_ok([
            'ref_code'  => $existing,
            'share_url' => home_url('/invite/' . $existing),
        ]);
    }

    // Generate ref_code: first 8 chars of pid_hash + 2 random alphanum (uppercase)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $attempts = 0;
    do {
        if (++$attempts > 20) {
            return ic_json_error('Nem sikerült meghívót létrehozni.', 500);
        }
        $suffix   = $chars[random_int(0, strlen($chars) - 1)] . $chars[random_int(0, strlen($chars) - 1)];
        $ref_code = strtoupper(substr($pid_hash, 0, 8)) . $suffix;
        $clash    = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$p}ic_invites WHERE ref_code=%s", $ref_code));
    } while ($clash);

    $wpdb->insert("{$p}ic_invites", [
        'circle_id'    => $id,
        'inviter_hash' => $pid_hash,
        'ref_code'     => $ref_code,
    ]);

    return new WP_REST_Response([
        'ref_code'  => $ref_code,
        'share_url' => home_url('/invite/' . $ref_code),
    ], 201);
}

function ic_rest_invite_landing(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p        = $wpdb->prefix;
    $ref_code = sanitize_key($req->get_param('ref_code') ?? '');
    if (strlen($ref_code) !== 10) {
        return ic_json_error('Érvénytelen meghívó kód.', 404);
    }

    $invite = $wpdb->get_row($wpdb->prepare(
        "SELECT i.circle_id, i.inviter_hash, i.conversions, i.max_uses,
                c.name AS circle_name, c.type AS circle_type,
                (SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=i.circle_id AND is_active=1) AS member_count
         FROM {$p}ic_invites i
         JOIN {$p}ic_circles c ON c.id = i.circle_id
         WHERE i.ref_code = %s AND i.is_active = 1",
        $ref_code
    ));

    if (!$invite) {
        return ic_json_error('Meghívó nem található.', 404);
    }
    if ((int) $invite->conversions >= (int) $invite->max_uses) {
        return ic_json_error('Ez a meghívó elérte a felhasználási korlátot.', 410);
    }

    $inviter_alias = $wpdb->get_var($wpdb->prepare(
        "SELECT alias FROM {$p}ic_memberships m
         LEFT JOIN (SELECT 1) t ON 1=1
         WHERE m.circle_id=%d AND m.pid_hash=%s LIMIT 1",
        $invite->circle_id, $invite->inviter_hash
    )) ?: IC_Alias::generate($invite->inviter_hash, (int) $invite->circle_id);

    return ic_json_ok([
        'circle' => [
            'id'           => (int) $invite->circle_id,
            'name'         => $invite->circle_name,
            'type'         => $invite->circle_type,
            'member_count' => (int) $invite->member_count,
        ],
        'inviter_alias' => $inviter_alias,
        'ref_code'      => $ref_code,
    ]);
}

/* =========================================================================
   §7. Invite rewards — join + first post + 30-day active
   ========================================================================= */

/**
 * Called when a user joins a circle via a ref_code (first join only).
 * Awards invitee +30pts, queues inviter +50pts, records the claim.
 */
function ic_process_invite_join(int $circle_id, string $invitee_hash, string $ref_code): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $invite = $wpdb->get_row($wpdb->prepare(
        "SELECT inviter_hash, conversions, max_uses FROM {$p}ic_invites
         WHERE ref_code=%s AND circle_id=%d AND is_active=1",
        $ref_code, $circle_id
    ));
    if (!$invite || (int) $invite->conversions >= (int) $invite->max_uses) {
        return;
    }
    if ($invite->inviter_hash === $invitee_hash) {
        return; // can't invite yourself
    }

    // Check no duplicate claim
    $already = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$p}ic_invite_claims WHERE ref_code=%s AND invitee_hash=%s",
        $ref_code, $invitee_hash
    ));
    if ($already) {
        return;
    }

    $wpdb->insert("{$p}ic_invite_claims", [
        'ref_code'     => $ref_code,
        'invitee_hash' => $invitee_hash,
        'inviter_hash' => $invite->inviter_hash,
        'circle_id'    => $circle_id,
    ]);

    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_invites SET conversions = conversions + 1 WHERE ref_code=%s",
        $ref_code
    ));

    // Invitee reward: +30pts (current HTTP user = invitee, so direct award works)
    ic_award_points($invitee_hash, 30, 'invite_join', "invite:{$ref_code}", "invite_join:{$invitee_hash}");

    // Inviter reward: +50pts (deferred — inviter is not the current user)
    ic_queue_points($invite->inviter_hash, 50, 'invite_referral', "invite:{$ref_code}", "invite_referral:{$invite->inviter_hash}:{$ref_code}");

    // community_puller badge: inviter has recruited 5 people
    $total_conversions = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(conversions) FROM {$p}ic_invites WHERE inviter_hash=%s",
        $invite->inviter_hash
    ));
    if ($total_conversions >= 5) {
        ic_queue_badge($invite->inviter_hash, 'community_puller', $circle_id);
    }
}

/**
 * Called after a user creates their first post — check if invitee, reward both sides.
 */
function ic_check_invite_first_post(int $circle_id, string $invitee_hash): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $claim = $wpdb->get_row($wpdb->prepare(
        "SELECT id, inviter_hash, ref_code FROM {$p}ic_invite_claims
         WHERE invitee_hash=%s AND circle_id=%d AND first_post_rewarded=0
         LIMIT 1",
        $invitee_hash, $circle_id
    ));
    if (!$claim) {
        return;
    }

    // Check this is truly their first post ever in this circle
    $post_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
        $circle_id, $invitee_hash
    ));
    if ($post_count !== 1) {
        return; // already has posts
    }

    $wpdb->update("{$p}ic_invite_claims", ['first_post_rewarded' => 1], ['id' => (int) $claim->id]);

    // Invitee: +50pts, +1 bonus vote (direct award)
    ic_award_points($invitee_hash, 50, 'invite_first_post', "claim:{$claim->id}", "invite_fp_invitee:{$claim->id}");
    ic_award_votes($invitee_hash, 1, $circle_id);

    // Inviter: +100pts, +2 bonus votes (deferred)
    ic_queue_points($claim->inviter_hash, 100, 'invite_first_post_ref', "claim:{$claim->id}", "invite_fp_inviter:{$claim->id}");
    ic_queue_votes($claim->inviter_hash, 2, $circle_id);
}

/* =========================================================================
   §7. Deferred points / votes queue (lazy-claim pattern)
   ========================================================================= */

/**
 * Queue a points award for a user identified by pid_hash.
 * Will be claimed on their next REST request.
 */
function ic_queue_points(string $pid_hash, int $points, string $activity, string $source_id, string $dedupe_key): void {
    $key     = 'ic_pq_' . substr($pid_hash, 0, 32);
    $pending = get_option($key, []);
    if (!is_array($pending)) {
        $pending = [];
    }
    // Dedupe check
    foreach ($pending as $item) {
        if ($item['dedupe_key'] === $dedupe_key) {
            return;
        }
    }
    $pending[] = compact('points', 'activity', 'source_id', 'dedupe_key');
    update_option($key, $pending, false);
}

/**
 * Claim all queued points for the current request's user.
 */
function ic_claim_pending_points(string $pid_hash): void {
    if ($pid_hash === '') {
        return;
    }
    $key     = 'ic_pq_' . substr($pid_hash, 0, 32);
    $pending = get_option($key, []);
    if (empty($pending) || !is_array($pending)) {
        return;
    }
    delete_option($key); // delete first to avoid double processing on concurrent requests
    foreach ($pending as $item) {
        ic_award_points(
            $pid_hash,
            (int) ($item['points'] ?? 0),
            (string) ($item['activity'] ?? 'deferred'),
            (string) ($item['source_id'] ?? ''),
            (string) ($item['dedupe_key'] ?? '')
        );
    }
    // Process pending badge unlocks too
    $bkey    = 'ic_bq_' . substr($pid_hash, 0, 32);
    $badges  = get_option($bkey, []);
    if (!empty($badges)) {
        delete_option($bkey);
        foreach ($badges as $b) {
            ic_unlock_badge($pid_hash, (string) ($b['badge_key'] ?? ''), (int) ($b['circle_id'] ?? 0));
        }
    }
    // Process pending votes
    $vkey   = 'ic_vq_' . substr($pid_hash, 0, 32);
    $vqueue = get_option($vkey, []);
    if (!empty($vqueue)) {
        delete_option($vkey);
        foreach ($vqueue as $v) {
            ic_award_votes($pid_hash, (int) ($v['votes'] ?? 1), (int) ($v['circle_id'] ?? 0));
        }
    }
}

/**
 * Queue a badge unlock for a user (deferred until their next request).
 */
function ic_queue_badge(string $pid_hash, string $badge_key, int $circle_id = 0): void {
    $key    = 'ic_bq_' . substr($pid_hash, 0, 32);
    $badges = get_option($key, []);
    if (!is_array($badges)) {
        $badges = [];
    }
    foreach ($badges as $b) {
        if ($b['badge_key'] === $badge_key) {
            return;
        }
    }
    $badges[] = compact('badge_key', 'circle_id');
    update_option($key, $badges, false);
}

/**
 * Queue bonus votes for a user (deferred).
 */
function ic_queue_votes(string $pid_hash, int $votes, int $circle_id = 0): void {
    $key    = 'ic_vq_' . substr($pid_hash, 0, 32);
    $vqueue = get_option($key, []);
    if (!is_array($vqueue)) {
        $vqueue = [];
    }
    $vqueue[] = compact('votes', 'circle_id');
    update_option($key, $vqueue, false);
}

/**
 * Award bonus votes to a user (direct — only call for current request user, or from cron).
 */
function ic_award_votes(string $pid_hash, int $votes, int $circle_id = 0): void {
    if ($pid_hash === '' || $votes <= 0) {
        return;
    }
    global $wpdb;
    $p = $wpdb->prefix;

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$p}ic_member_trust WHERE circle_id=%d AND pid_hash=%s",
        max(1, $circle_id), $pid_hash
    ));
    if ($exists) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$p}ic_member_trust SET bonus_votes = bonus_votes + %d WHERE circle_id=%d AND pid_hash=%s",
            $votes, max(1, $circle_id), $pid_hash
        ));
    } else {
        $wpdb->insert("{$p}ic_member_trust", [
            'circle_id'   => max(1, $circle_id),
            'pid_hash'    => $pid_hash,
            'bonus_votes' => $votes,
        ]);
    }
}

/* =========================================================================
   §8. Jelvények — badge unlock wrapper + triggers
   ========================================================================= */

/**
 * Thin wrapper around impact_award_badge().
 * Guards against missing plugin, deduplicates via impact_has_badge().
 */
function ic_unlock_badge(string $pid_hash, string $badge_key, int $circle_id = 0, string $tier = 'bronze'): void {
    if ($pid_hash === '' || $badge_key === '') {
        return;
    }
    if (!function_exists('impact_award_badge')) {
        return;
    }
    if (function_exists('impact_has_badge') && impact_has_badge($pid_hash, $badge_key)) {
        return; // already earned
    }
    $meta = $circle_id > 0 ? ['circle_id' => $circle_id] : [];
    impact_award_badge($pid_hash, $badge_key, $tier, 'impact_community', $meta);
}

/**
 * Check receipt/decision post type badges (5×receipt → impact_receipts_5, 5×decision → decision_maker).
 */
function ic_check_post_type_badge(int $circle_id, string $pid_hash, string $post_type): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE author_hash=%s AND post_type=%s AND is_deleted=0",
        $pid_hash, $post_type
    ));

    if ($post_type === 'receipt' && $count >= 5) {
        ic_unlock_badge($pid_hash, 'impact_receipts_5', $circle_id);
    }
    if ($post_type === 'decision' && $count >= 5) {
        ic_unlock_badge($pid_hash, 'decision_maker', $circle_id);
    }
}

/**
 * Posting streak tracker. Called from ic_rest_post_create().
 * Uses wp_options per-user-per-circle to track consecutive posting days.
 * Fires: ic_streak_7day (7 days), ic_streak_14day (14 days).
 */
function ic_update_post_streak(int $circle_id, string $pid_hash): void {
    $key   = 'ic_streak_' . substr($pid_hash, 0, 24) . '_' . $circle_id;
    $data  = get_option($key, ['days' => 0, 'last' => '']);
    $today = current_time('Y-m-d');

    if ($data['last'] === $today) {
        return; // already counted today
    }

    $yesterday = date('Y-m-d', strtotime('-1 day', strtotime(current_time('mysql'))));
    if ($data['last'] === $yesterday) {
        $data['days']++;
    } else {
        $data['days'] = 1; // reset streak
    }
    $data['last'] = $today;
    update_option($key, $data, false);

    if ($data['days'] === 7) {
        do_action('ic_streak_7day', $circle_id, $pid_hash);
    }
    if ($data['days'] === 14) {
        do_action('ic_streak_14day', $circle_id, $pid_hash);
    }
}

add_action('ic_streak_14day', 'ic_badge_wave_rider', 10, 2);
function ic_badge_wave_rider(int $circle_id, string $pid_hash): void {
    ic_unlock_badge($pid_hash, 'wave_rider', $circle_id);
}

/* =========================================================================
   §8. Monthly badge cron — core_member, circle_ambassador, ngo/settlement champions
   ========================================================================= */

add_action('ic_monthly_badge_award', 'ic_run_monthly_badges');
function ic_run_monthly_badges(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // --- core_member: 30+ days active + 20+ posts in a circle ---
    $candidates = $wpdb->get_results(
        "SELECT m.pid_hash, m.circle_id
         FROM {$p}ic_memberships m
         WHERE m.is_active = 1
           AND DATEDIFF(NOW(), m.joined_at) >= 30
           AND (
               SELECT COUNT(*) FROM {$p}ic_posts
               WHERE circle_id = m.circle_id AND author_hash = m.pid_hash AND is_deleted = 0
           ) >= 20"
    );
    foreach ($candidates as $c) {
        ic_unlock_badge($c->pid_hash, 'core_member', (int) $c->circle_id, 'silver');
    }

    // --- circle_ambassador: Törzstag (trust_level >= 2) in 3+ circles ---
    $ambassadors = $wpdb->get_results(
        "SELECT pid_hash, COUNT(*) AS cnt
         FROM {$p}ic_member_trust
         WHERE trust_level >= 2
         GROUP BY pid_hash HAVING cnt >= 3"
    );
    foreach ($ambassadors as $a) {
        ic_unlock_badge($a->pid_hash, 'circle_ambassador', 0, 'silver');
    }

    // --- ngo_champion: top 3 in NGO circle leaderboard ---
    $ngo_circles = $wpdb->get_col(
        "SELECT id FROM {$p}ic_circles WHERE type='ngo' AND is_active=1"
    );
    $period = date('Y-m', strtotime('-1 month'));
    foreach ($ngo_circles as $cid) {
        $top3 = $wpdb->get_col($wpdb->prepare(
            "SELECT pid_hash FROM {$p}ic_circle_leaderboard
             WHERE circle_id=%d AND period=%s
             ORDER BY score DESC LIMIT 3",
            $cid, $period
        ));
        foreach ($top3 as $ph) {
            ic_unlock_badge($ph, 'ngo_champion', (int) $cid, 'gold');
        }
    }

    // --- settlement_hero: top 1 in settlement circle leaderboard ---
    $settlement_circles = $wpdb->get_col(
        "SELECT id FROM {$p}ic_circles WHERE type='settlement' AND is_active=1"
    );
    foreach ($settlement_circles as $cid) {
        $top1 = $wpdb->get_var($wpdb->prepare(
            "SELECT pid_hash FROM {$p}ic_circle_leaderboard
             WHERE circle_id=%d AND period=%s
             ORDER BY score DESC LIMIT 1",
            $cid, $period
        ));
        if ($top1) {
            ic_unlock_badge($top1, 'settlement_hero', (int) $cid, 'gold');
        }
    }

    // --- 30-day invitee active reward ---
    ic_check_invite_30day_active();
}

/**
 * Award inviter +200pts +3votes if invitee has been active for 30+ days.
 */
function ic_check_invite_30day_active(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $claims = $wpdb->get_results(
        "SELECT c.id, c.invitee_hash, c.inviter_hash, c.circle_id
         FROM {$p}ic_invite_claims c
         WHERE c.active30_rewarded = 0
           AND DATEDIFF(NOW(), c.claimed_at) >= 30"
    );
    foreach ($claims as $claim) {
        // Verify invitee is still active member
        $still_active = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
            $claim->circle_id, $claim->invitee_hash
        ));
        if (!$still_active) {
            continue;
        }

        $wpdb->update("{$p}ic_invite_claims", ['active30_rewarded' => 1], ['id' => (int) $claim->id]);

        ic_queue_points($claim->inviter_hash, 200, 'invite_active30', "claim:{$claim->id}", "invite_a30:{$claim->id}");
        ic_queue_votes($claim->inviter_hash, 3, (int) $claim->circle_id);
    }
}

/* =========================================================================
   §8. buddy_mentor badge — awarded when a user completes 3+ Impact Buddy onboardings
   ========================================================================= */

add_action('ic_buddy_completed', 'ic_check_buddy_mentor_badge', 10, 2);
function ic_check_buddy_mentor_badge(int $circle_id, string $mentor_hash): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $completed_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_buddies
         WHERE (pid_a=%s OR pid_b=%s) AND circle_id=%d AND completed_at IS NOT NULL",
        $mentor_hash, $mentor_hash, $circle_id
    ));
    if ($completed_count >= 3) {
        ic_unlock_badge($mentor_hash, 'buddy_mentor', $circle_id);
    }
}

/* =========================================================================
   §9. Tombola & Aukció modul
   ========================================================================= */

/* ---- 9.1 Tombola REST handlers ----------------------------------------- */

function ic_rest_ngo_create_tombola(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_id    = (int) $req->get_param('circle_id');
    $ngo_slug     = sanitize_text_field($req->get_param('ngo_slug') ?? '');
    $title        = sanitize_text_field($req->get_param('title') ?? '');
    $description  = sanitize_textarea_field($req->get_param('description') ?? '');
    $prize_image  = esc_url_raw($req->get_param('prize_image') ?? '');
    $prize_name   = sanitize_text_field($req->get_param('prize_name') ?? '');
    $ticket_cost  = max(0, (int) ($req->get_param('ticket_cost') ?? 0));
    $max_tickets  = max(0, (int) ($req->get_param('max_tickets') ?? 100));
    $max_per_user = min(5, max(1, (int) ($req->get_param('max_per_user') ?? 5)));
    $ends_at      = sanitize_text_field($req->get_param('ends_at') ?? '');

    if (!$circle_id || $ngo_slug === '' || $title === '' || $ends_at === '') {
        return ic_json_error('Hiányzó kötelező mező.');
    }

    $end_ts = strtotime($ends_at);
    if ($end_ts === false || $end_ts < time() + 3 * DAY_IN_SECONDS || $end_ts > time() + 30 * DAY_IN_SECONDS) {
        return ic_json_error('A végdátumnak 3–30 nappal a jövőben kell lennie.');
    }

    // Anti-abuse: max 1 active tombola / NGO
    $active = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_tombolas WHERE ngo_slug=%s AND status='active'",
        $ngo_slug
    ));
    if ($active > 0) {
        return ic_json_error('Már van aktív tombola ehhez az NGO-hoz.');
    }

    // § Sprint 12 — ritual params
    $ritual_enabled   = (int) (bool) $req->get_param('ritual_enabled');
    $activity_gate_pts = max(0, (int) ($req->get_param('activity_gate_pts') ?? 0));

    $wpdb->insert("{$p}ic_tombolas", [
        'circle_id'         => $circle_id,
        'ngo_slug'          => $ngo_slug,
        'title'             => $title,
        'description'       => $description,
        'prize_image'       => $prize_image,
        'prize_json'        => wp_json_encode(['name' => $prize_name]),
        'ticket_cost'       => $ticket_cost,
        'max_tickets'       => $max_tickets,
        'max_per_user'      => $max_per_user,
        'ritual_enabled'    => $ritual_enabled,
        'activity_gate_pts' => $activity_gate_pts,
        'status'            => 'active',
        'ends_at'           => gmdate('Y-m-d H:i:s', $end_ts),
        'created_at'        => current_time('mysql'),
    ]);
    $tombola_id = (int) $wpdb->insert_id;

    ic_impi_post(
        $circle_id,
        "🎟️ Új tombola indul! **{$title}** — Nyerj egy fantasztikus díjat! Vegyél jegyet a körben. Sok sikert mindenkinek! 🍀",
        4
    );

    return ic_json_ok(['tombola_id' => $tombola_id, 'status' => 'created'], 201);
}

function ic_rest_circle_tombolas(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_id = (int) $req->get_param('id');
    $pid_hash  = ic_pid_hash();

    $tombolas = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, CAST(tt.ticket_count AS UNSIGNED) as my_tickets
         FROM {$p}ic_tombolas t
         LEFT JOIN {$p}ic_tombola_tickets tt ON tt.tombola_id=t.id AND tt.pid_hash=%s
         WHERE t.circle_id=%d AND t.status IN ('active','drawn')
         ORDER BY t.status ASC, t.created_at DESC LIMIT 5",
        $pid_hash ?: '', $circle_id
    ), ARRAY_A);

    foreach ($tombolas as &$t) {
        $t['prize_json']   = json_decode($t['prize_json'] ?? '{}', true);
        $t['my_tickets']   = (int) ($t['my_tickets'] ?? 0);
        $t['tickets_sold'] = (int) $t['tickets_sold'];
        $t['ticket_cost']  = (int) $t['ticket_cost'];
        $t['max_per_user'] = (int) $t['max_per_user'];
        $t['max_tickets']  = (int) $t['max_tickets'];
        $t['ends_at_ts']   = $t['ends_at'] ? strtotime($t['ends_at']) : 0;
        if ($t['status'] === 'drawn' && !empty($t['winner_hash'])) {
            $alias = $wpdb->get_var($wpdb->prepare(
                "SELECT alias FROM {$p}ic_circle_leaderboard WHERE pid_hash=%s AND circle_id=%d LIMIT 1",
                $t['winner_hash'], $circle_id
            ));
            $t['winner_alias'] = $alias ?: 'Névtelen nyertes';
        }
        unset($t['winner_hash']);
    }
    unset($t);

    return ic_json_ok(['tombolas' => $tombolas]);
}

function ic_rest_tombola_buy(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $tombola_id = (int) $req->get_param('id');
    $count      = max(1, min(5, (int) ($req->get_param('count') ?? 1)));
    $pid_hash   = ic_pid_hash();

    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    $tombola = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_tombolas WHERE id=%d",
        $tombola_id
    ), ARRAY_A);

    if (!$tombola || $tombola['status'] !== 'active') {
        return ic_json_error('A tombola nem aktív.');
    }
    if (strtotime($tombola['ends_at']) < time()) {
        return ic_json_error('A tombola lejárt.');
    }

    // § Sprint 12 — Activity gate: user must have earned enough activity in the circle
    $gate_pts = (int) ($tombola['activity_gate_pts'] ?? 0);
    if ($gate_pts > 0) {
        $post_count  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
            (int) $tombola['circle_id'], $pid_hash
        ));
        $react_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_post_reactions WHERE pid_hash=%s",
            $pid_hash
        ));
        $vote_count  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(vote_count),0) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
            (int) $tombola['circle_id'], $pid_hash
        ));
        $activity_score = $post_count * 30 + ($react_count + $vote_count) * 3;
        if ($activity_score < $gate_pts) {
            return ic_json_error(
                "Aktivitás szükséges a jegyvásárláshoz (szükséges: {$gate_pts}, jelenlegi: {$activity_score}).",
                403
            );
        }
    }

    $max_per  = (int) $tombola['max_per_user'];
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(ticket_count,0) FROM {$p}ic_tombola_tickets WHERE tombola_id=%d AND pid_hash=%s",
        $tombola_id, $pid_hash
    ));
    $new_total = $existing + $count;
    if ($new_total > $max_per) {
        return ic_json_error("Maximum {$max_per} jegy vehető fejenként. Már van: {$existing}.");
    }

    $max_t = (int) $tombola['max_tickets'];
    $sold  = (int) $tombola['tickets_sold'];
    if ($max_t > 0 && $sold + $count > $max_t) {
        return ic_json_error('Nincs elég szabad jegy.');
    }

    $ticket_cost = (int) $tombola['ticket_cost'];
    $total_cost  = $ticket_cost * $count;

    if ($total_cost > 0) {
        if (!class_exists('Sharity_Points_Manager')) {
            return ic_json_error('Pont-rendszer nem elérhető.', 503);
        }
        $pseudo_id = ic_get_pseudo_id();
        if ($pseudo_id === '') {
            return ic_json_error('Azonosítás szükséges.', 401);
        }
        $mgr     = new Sharity_Points_Manager();
        $snap    = $mgr->get_points_snapshot_for_pseudo($pseudo_id);
        $balance = (int) ($snap['points_total'] ?? 0);
        if ($balance < $total_cost) {
            return ic_json_error("Nincs elég pontod ({$balance} van, {$total_cost} kell).");
        }
        $mgr->award_points_for_pseudo(
            $pseudo_id, -$total_cost, 'tombola_ticket',
            "tombola:{$tombola_id}", ['activity' => 'buy_ticket', 'source_type' => 'impact_community'],
            "tkt:{$tombola_id}:{$pid_hash}:{$new_total}"
        );
    }

    if ($existing > 0) {
        $wpdb->update(
            "{$p}ic_tombola_tickets",
            ['ticket_count' => $new_total, 'pts_spent' => $existing * $ticket_cost + $total_cost],
            ['tombola_id' => $tombola_id, 'pid_hash' => $pid_hash]
        );
    } else {
        $wpdb->insert("{$p}ic_tombola_tickets", [
            'tombola_id'   => $tombola_id,
            'pid_hash'     => $pid_hash,
            'ticket_count' => $count,
            'pts_spent'    => $total_cost,
            'created_at'   => current_time('mysql'),
        ]);
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_tombolas SET tickets_sold=tickets_sold+%d WHERE id=%d",
        $count, $tombola_id
    ));

    // Free tombola entry earns a small engagement reward
    if ($ticket_cost === 0) {
        ic_award_points($pid_hash, 5, 'tombola_join', "tombola:{$tombola_id}", "tj:{$tombola_id}:{$pid_hash}");
    }

    return ic_json_ok(['my_tickets' => $new_total, 'tickets_sold' => $sold + $count]);
}

function ic_rest_tombola_result(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $tombola_id = (int) $req->get_param('id');
    $tombola    = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_tombolas WHERE id=%d", $tombola_id
    ), ARRAY_A);

    if (!$tombola) {
        return ic_json_error('Tombola nem található.', 404);
    }

    $result = [
        'id'           => (int) $tombola['id'],
        'title'        => $tombola['title'],
        'status'       => $tombola['status'],
        'ends_at'      => $tombola['ends_at'],
        'drawn_at'     => $tombola['drawn_at'],
        'tickets_sold' => (int) $tombola['tickets_sold'],
    ];

    if ($tombola['status'] === 'drawn' && !empty($tombola['winner_hash'])) {
        $alias = $wpdb->get_var($wpdb->prepare(
            "SELECT alias FROM {$p}ic_circle_leaderboard WHERE pid_hash=%s AND circle_id=%d LIMIT 1",
            $tombola['winner_hash'], (int) $tombola['circle_id']
        ));
        $result['winner_alias'] = $alias ?: 'Névtelen nyertes';
    }

    return ic_json_ok($result);
}

/* ---- 9.1 Tombola cron — draw winner ------------------------------------ */

add_action('ic_tombola_draw_cron', 'ic_tombola_draw_cron_handler');
function ic_tombola_draw_cron_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $due = $wpdb->get_results(
        "SELECT * FROM {$p}ic_tombolas WHERE status='active' AND ends_at <= NOW()",
        ARRAY_A
    );

    foreach ($due as $tombola) {
        $tid     = (int) $tombola['id'];
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT pid_hash, ticket_count FROM {$p}ic_tombola_tickets WHERE tombola_id=%d",
            $tid
        ), ARRAY_A);

        if (empty($tickets)) {
            $wpdb->update("{$p}ic_tombolas", ['status' => 'cancelled'], ['id' => $tid]);
            continue;
        }

        // Weighted pool
        $pool = [];
        foreach ($tickets as $t) {
            for ($i = 0; $i < (int) $t['ticket_count']; $i++) {
                $pool[] = $t['pid_hash'];
            }
        }
        $winner_hash = $pool[random_int(0, count($pool) - 1)];

        $wpdb->update("{$p}ic_tombolas", [
            'status'      => 'drawn',
            'winner_hash' => $winner_hash,
            'drawn_at'    => current_time('mysql'),
        ], ['id' => $tid]);

        ic_queue_points($winner_hash, 100, 'tombola_win', "tombola:{$tid}", "tw:{$tid}:{$winner_hash}");

        $alias = $wpdb->get_var($wpdb->prepare(
            "SELECT alias FROM {$p}ic_circle_leaderboard WHERE pid_hash=%s AND circle_id=%d LIMIT 1",
            $winner_hash, (int) $tombola['circle_id']
        ));
        $prize_name = json_decode($tombola['prize_json'] ?? '{}', true)['name'] ?? $tombola['title'];
        ic_impi_post(
            (int) $tombola['circle_id'],
            "🎉 A tombola lezárult! A nyertes: **" . ($alias ?: 'Névtelen nyertes') . "** — gratulálunk! 🏆 Díj: {$prize_name}",
            4
        );
    }
}

/* ---- 9.2 Aukció REST handlers ------------------------------------------ */

function ic_rest_ngo_create_auction(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_id    = (int) $req->get_param('circle_id');
    $ngo_slug     = sanitize_text_field($req->get_param('ngo_slug') ?? '');
    $title        = sanitize_text_field($req->get_param('title') ?? '');
    $description  = sanitize_textarea_field($req->get_param('description') ?? '');
    $item_image   = esc_url_raw($req->get_param('item_image') ?? '');
    $item_name    = sanitize_text_field($req->get_param('item_name') ?? '');
    $starting_bid = max(50, (int) ($req->get_param('starting_bid') ?? 50));
    $ends_at      = sanitize_text_field($req->get_param('ends_at') ?? '');

    if (!$circle_id || $ngo_slug === '' || $title === '' || $ends_at === '') {
        return ic_json_error('Hiányzó kötelező mező.');
    }

    $end_ts = strtotime($ends_at);
    if ($end_ts === false || $end_ts < time() + 3 * DAY_IN_SECONDS || $end_ts > time() + 30 * DAY_IN_SECONDS) {
        return ic_json_error('A végdátumnak 3–30 nappal a jövőben kell lennie.');
    }

    $active = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_auctions WHERE ngo_slug=%s AND status='active'",
        $ngo_slug
    ));
    if ($active >= 2) {
        return ic_json_error('Maximum 2 aktív aukció engedélyezett NGO-nként.');
    }

    $wpdb->insert("{$p}ic_auctions", [
        'circle_id'    => $circle_id,
        'ngo_slug'     => $ngo_slug,
        'title'        => $title,
        'description'  => $description,
        'item_image'   => $item_image,
        'item_json'    => wp_json_encode(['name' => $item_name]),
        'starting_bid' => $starting_bid,
        'current_bid'  => 0,
        'status'       => 'active',
        'ends_at'      => gmdate('Y-m-d H:i:s', $end_ts),
        'created_at'   => current_time('mysql'),
    ]);
    $auction_id = (int) $wpdb->insert_id;

    ic_impi_post(
        $circle_id,
        "🔨 Új aukció indul! **{$title}** — licitálj pontokkal és nyerj! Induló licit: {$starting_bid} pont.",
        4
    );

    return ic_json_ok(['auction_id' => $auction_id, 'status' => 'created'], 201);
}

function ic_rest_circle_auctions(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_id = (int) $req->get_param('id');
    $pid_hash  = ic_pid_hash();

    // Drain pending queue so refunded points appear immediately
    if ($pid_hash) {
        ic_claim_pending_points($pid_hash);
    }

    $auctions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ic_auctions
         WHERE circle_id=%d AND status IN ('active','closed')
         ORDER BY status ASC, created_at DESC LIMIT 5",
        $circle_id
    ), ARRAY_A);

    foreach ($auctions as &$a) {
        $a['item_json']      = json_decode($a['item_json'] ?? '{}', true);
        $a['starting_bid']   = (int) $a['starting_bid'];
        $a['current_bid']    = (int) $a['current_bid'];
        $a['bid_count']      = (int) $a['bid_count'];
        $a['ends_at_ts']     = $a['ends_at'] ? strtotime($a['ends_at']) : 0;
        $a['extended_to_ts'] = $a['extended_to'] ? strtotime($a['extended_to']) : null;
        $a['is_my_bid']      = $pid_hash && ($a['current_bidder'] === $pid_hash);

        if ($a['current_bidder']) {
            $alias = $wpdb->get_var($wpdb->prepare(
                "SELECT alias FROM {$p}ic_circle_leaderboard WHERE pid_hash=%s AND circle_id=%d LIMIT 1",
                $a['current_bidder'], $circle_id
            ));
            $a['leader_alias'] = $alias ?: 'Névtelen tag';
        } else {
            $a['leader_alias'] = null;
        }

        if (!empty($a['winner_hash'])) {
            $wa = $wpdb->get_var($wpdb->prepare(
                "SELECT alias FROM {$p}ic_circle_leaderboard WHERE pid_hash=%s AND circle_id=%d LIMIT 1",
                $a['winner_hash'], $circle_id
            ));
            $a['winner_alias'] = $wa ?: 'Névtelen nyertes';
        }
        unset($a['current_bidder'], $a['winner_hash']);
    }
    unset($a);

    return ic_json_ok(['auctions' => $auctions]);
}

function ic_rest_auction_bid(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $auction_id = (int) $req->get_param('id');
    $bid_amount = (int) $req->get_param('amount');
    $pid_hash   = ic_pid_hash();

    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }
    if ($bid_amount <= 0) {
        return ic_json_error('Érvénytelen licit összeg.');
    }

    $auction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_auctions WHERE id=%d",
        $auction_id
    ), ARRAY_A);

    if (!$auction || $auction['status'] !== 'active') {
        return ic_json_error('Az aukció nem aktív.');
    }

    $effective_end = $auction['extended_to']
        ? strtotime($auction['extended_to'])
        : strtotime($auction['ends_at']);
    if ($effective_end < time()) {
        return ic_json_error('Az aukció lezárult.');
    }

    $current_bid = (int) $auction['current_bid'];
    $starting    = (int) $auction['starting_bid'];
    $min_bid     = max($starting, $current_bid + 10);

    if ($bid_amount < $min_bid) {
        return ic_json_error("A minimális licit: {$min_bid} pont.");
    }
    if ($auction['current_bidder'] === $pid_hash) {
        return ic_json_error('Már te vezeted az aukciót.');
    }

    if (!class_exists('Sharity_Points_Manager')) {
        return ic_json_error('Pont-rendszer nem elérhető.', 503);
    }
    $pseudo_id = ic_get_pseudo_id();
    if ($pseudo_id === '') {
        return ic_json_error('Azonosítás szükséges.', 401);
    }
    $mgr     = new Sharity_Points_Manager();
    $snap    = $mgr->get_points_snapshot_for_pseudo($pseudo_id);
    $balance = (int) ($snap['points_total'] ?? 0);
    if ($balance < $bid_amount) {
        return ic_json_error("Nincs elég pontod ({$balance} van, {$bid_amount} kell).");
    }

    // Queue refund for the outbid user
    if ($auction['current_bidder'] && $current_bid > 0) {
        ic_queue_points(
            $auction['current_bidder'],
            $current_bid,
            'auction_refund',
            "auction:{$auction_id}:refund",
            "ar:{$auction_id}:{$auction['current_bidder']}"
        );
    }

    // Deduct from current bidder
    $mgr->award_points_for_pseudo(
        $pseudo_id, -$bid_amount, 'auction_bid',
        "auction:{$auction_id}",
        ['activity' => 'auction_bid', 'source_type' => 'impact_community'],
        'ab:' . $auction_id . ':' . $pid_hash . ':' . time()
    );

    // Deactivate previous winning bid row
    if ($auction['current_bidder']) {
        $wpdb->update(
            "{$p}ic_auction_bids",
            ['is_active' => 0],
            ['auction_id' => $auction_id, 'pid_hash' => $auction['current_bidder'], 'is_active' => 1]
        );
    }

    $wpdb->insert("{$p}ic_auction_bids", [
        'auction_id' => $auction_id,
        'pid_hash'   => $pid_hash,
        'bid_amount' => $bid_amount,
        'is_active'  => 1,
        'created_at' => current_time('mysql'),
    ]);

    // Anti-snipe: extend by 5 min if bid within last 5 min
    $new_extended_to = null;
    if (($effective_end - time()) < 5 * MINUTE_IN_SECONDS) {
        $new_extended_to = gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS);
    }

    $update = [
        'current_bid'    => $bid_amount,
        'current_bidder' => $pid_hash,
        'bid_count'      => (int) $auction['bid_count'] + 1,
    ];
    if ($new_extended_to) {
        $update['extended_to'] = $new_extended_to;
    }
    $wpdb->update("{$p}ic_auctions", $update, ['id' => $auction_id]);

    return ic_json_ok([
        'new_bid'     => $bid_amount,
        'bid_count'   => (int) $auction['bid_count'] + 1,
        'extended_to' => $new_extended_to,
    ]);
}

function ic_rest_auction_bids_list(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $auction_id = (int) $req->get_param('id');
    $bids       = $wpdb->get_results($wpdb->prepare(
        "SELECT b.bid_amount, b.is_active, b.created_at,
                COALESCE(lb.alias,'Névtelen') AS alias
         FROM {$p}ic_auction_bids b
         LEFT JOIN {$p}ic_circle_leaderboard lb ON lb.pid_hash = b.pid_hash
         WHERE b.auction_id=%d
         ORDER BY b.bid_amount DESC LIMIT 20",
        $auction_id
    ), ARRAY_A);

    return ic_json_ok(['bids' => $bids]);
}

/* ---- 9.2 Aukció cron — close & finalize -------------------------------- */

add_action('ic_auction_close_cron', 'ic_auction_close_cron_handler');
function ic_auction_close_cron_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $due = $wpdb->get_results(
        "SELECT * FROM {$p}ic_auctions
         WHERE status='active'
           AND (extended_to IS NULL     AND ends_at     <= NOW()
             OR extended_to IS NOT NULL AND extended_to <= NOW())",
        ARRAY_A
    );

    foreach ($due as $auction) {
        $aid = (int) $auction['id'];

        if (!$auction['current_bidder']) {
            $wpdb->update("{$p}ic_auctions", ['status' => 'cancelled'], ['id' => $aid]);
            continue;
        }

        $wpdb->update("{$p}ic_auctions", [
            'status'      => 'closed',
            'winner_hash' => $auction['current_bidder'],
        ], ['id' => $aid]);

        // Winner bonus
        ic_queue_badge($auction['current_bidder'], 'auction_winner', (int) $auction['circle_id']);
        ic_queue_points(
            $auction['current_bidder'], 50,
            'auction_win', "auction:{$aid}",
            "aw:{$aid}:{$auction['current_bidder']}"
        );

        $alias = $wpdb->get_var($wpdb->prepare(
            "SELECT alias FROM {$p}ic_circle_leaderboard WHERE pid_hash=%s AND circle_id=%d LIMIT 1",
            $auction['current_bidder'], (int) $auction['circle_id']
        ));
        $bid = (int) $auction['current_bid'];
        ic_impi_post(
            (int) $auction['circle_id'],
            "🔨 Az aukció lezárult! A nyertes: **" . ($alias ?: 'Névtelen nyertes') . "** — {$bid} ponttal! Gratulálunk! 🏆",
            4
        );
    }
}

/* =========================================================================
   §10 — NGO Catch-Up Sprint
   14-day sprint for bottom-30% NGOs: 25 credits per validated new user,
   max 80 users / NGO. Top 3 → 7-day visibility boost.
   ========================================================================= */

/**
 * Record a sprint activation event.
 * Called from ic_rest_post_create (type='post') and ic_rest_post_vote (type='vote').
 * Also called externally for 'ad'/'offerwall' activations.
 */
function ic_record_sprint_activation(string $pid_hash, string $ngo_slug, string $type = 'other'): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $sprint = ic_get_current_sprint();
    if (!$sprint) return;

    // Check NGO in cohort
    $cohort = json_decode($sprint['cohort_json'] ?? '[]', true) ?: [];
    if (!in_array($ngo_slug, $cohort, true)) return;

    // Check cap: already 2000 credits for this NGO? (80 users * 25 pts)
    $validated_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_sprint_events
         WHERE sprint_id=%d AND ngo_slug=%s AND is_validated=1",
        (int) $sprint['id'], $ngo_slug
    ));
    if ($validated_count >= 80) return;

    // Upsert: IGNORE duplicate sprint+pid+ngo (unique key), just record activation_type upgrade
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, activation_type, is_validated FROM {$p}ic_sprint_events
         WHERE sprint_id=%d AND pid_hash=%s AND ngo_slug=%s",
        (int) $sprint['id'], $pid_hash, $ngo_slug
    ));

    $valid_types = ['ad', 'vote', 'offerwall', 'post'];
    $is_activated_type = in_array($type, $valid_types, true);

    if (!$existing) {
        // New entry — mark as pending_review if device cluster risk
        $new_pid_rate_key = 'ic_new_pid_rate:' . substr(md5($_SERVER['REMOTE_ADDR'] ?? ''), 0, 16);
        $ip_new_pids = (int) get_transient($new_pid_rate_key);
        $is_pending = ($ip_new_pids >= 5) ? 1 : 0;

        $wpdb->insert("{$p}ic_sprint_events", [
            'sprint_id'        => (int) $sprint['id'],
            'ngo_slug'         => $ngo_slug,
            'pid_hash'         => $pid_hash,
            'activation_type'  => $type,
            'is_validated'     => $is_activated_type ? 1 : 0,
            'is_pending_review'=> $is_pending,
            'created_at'       => current_time('mysql'),
        ]);

        // Track new pseudo_id from this IP for anti-abuse
        set_transient($new_pid_rate_key, $ip_new_pids + 1, DAY_IN_SECONDS);
    } elseif (!$existing->is_validated && $is_activated_type) {
        // Upgrade to validated
        $wpdb->update(
            "{$p}ic_sprint_events",
            ['activation_type' => $type, 'is_validated' => 1],
            ['id' => (int) $existing->id]
        );
    }
}

function ic_get_current_sprint(): ?array {
    global $wpdb;
    $p = $wpdb->prefix;
    return $wpdb->get_row(
        "SELECT * FROM {$p}ic_sprints WHERE status='active' AND starts_at<=NOW() AND ends_at>=NOW() LIMIT 1",
        ARRAY_A
    );
}

function ic_rest_sprint_current(WP_REST_Request $req): WP_REST_Response {
    $sprint = ic_get_current_sprint();
    if (!$sprint) {
        return ic_json_ok(['active' => false]);
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $pid_hash = ic_pid_hash();
    $ngo_slug = sanitize_key($req->get_param('ngo_slug') ?? '');

    $data = [
        'active'    => true,
        'sprint_id' => (int) $sprint['id'],
        'starts_at' => $sprint['starts_at'],
        'ends_at'   => $sprint['ends_at'],
        'days_left' => max(0, (int) ceil((strtotime($sprint['ends_at']) - time()) / DAY_IN_SECONDS)),
    ];

    if ($ngo_slug) {
        $credits = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) * 25 FROM {$p}ic_sprint_events
             WHERE sprint_id=%d AND ngo_slug=%s AND is_validated=1 AND is_pending_review=0",
            (int) $sprint['id'], $ngo_slug
        ));
        $data['ngo_credits'] = $credits;

        // Find rank for this NGO
        $leaderboard = ic_sprint_leaderboard_rows((int) $sprint['id']);
        $rank = 0;
        foreach ($leaderboard as $i => $row) {
            if ($row['ngo_slug'] === $ngo_slug) {
                $rank = $i + 1;
                break;
            }
        }
        $data['ngo_rank'] = $rank;
    }

    return ic_json_ok($data);
}

function ic_rest_sprint_leaderboard(WP_REST_Request $req): WP_REST_Response {
    $sprint = ic_get_current_sprint();
    if (!$sprint) {
        return ic_json_ok(['active' => false, 'leaderboard' => []]);
    }
    return ic_json_ok([
        'active'      => true,
        'leaderboard' => ic_sprint_leaderboard_rows((int) $sprint['id']),
    ]);
}

function ic_sprint_leaderboard_rows(int $sprint_id): array {
    global $wpdb;
    $p = $wpdb->prefix;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ngo_slug, COUNT(*) AS validated_users, COUNT(*) * 25 AS credits
         FROM {$p}ic_sprint_events
         WHERE sprint_id=%d AND is_validated=1 AND is_pending_review=0
         GROUP BY ngo_slug
         ORDER BY credits DESC
         LIMIT 10",
        $sprint_id
    ), ARRAY_A);
    $out = [];
    foreach ($rows as $i => $r) {
        $circle = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$p}ic_circles WHERE ref_slug=%s LIMIT 1",
            $r['ngo_slug']
        ));
        $out[] = [
            'rank'            => $i + 1,
            'ngo_slug'        => $r['ngo_slug'],
            'ngo_name'        => $circle ? $circle->name : $r['ngo_slug'],
            'validated_users' => (int) $r['validated_users'],
            'credits'         => (int) $r['credits'],
        ];
    }
    return $out;
}

/* Sprint daily cron: open new sprint for bottom-30% NGOs; close expired sprints */
add_action('ic_sprint_daily_check', 'ic_sprint_daily_check_handler');
function ic_sprint_daily_check_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // 1. Close expired active sprints
    $expired = $wpdb->get_results(
        "SELECT * FROM {$p}ic_sprints WHERE status='active' AND ends_at < NOW()",
        ARRAY_A
    );
    foreach ($expired as $sprint) {
        $wpdb->update("{$p}ic_sprints", ['status' => 'closed'], ['id' => (int) $sprint['id']]);

        // Award top 3 visibility boost (7 days)
        $top3 = ic_sprint_leaderboard_rows((int) $sprint['id']);
        $boost_until = date('Y-m-d H:i:s', strtotime('+7 days'));
        foreach (array_slice($top3, 0, 3) as $row) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$p}ic_circles SET visibility_boost_until=%s WHERE ref_slug=%s",
                $boost_until, $row['ngo_slug']
            ));
        }

        // Impi sprint summary in each cohort circle
        $cohort = json_decode($sprint['cohort_json'] ?? '[]', true) ?: [];
        foreach ($cohort as $ngo_slug) {
            $circle = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$p}ic_circles WHERE ref_slug=%s AND is_active=1 LIMIT 1",
                $ngo_slug
            ));
            if (!$circle) continue;
            $credits = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) * 25 FROM {$p}ic_sprint_events
                 WHERE sprint_id=%d AND ngo_slug=%s AND is_validated=1 AND is_pending_review=0",
                (int) $sprint['id'], $ngo_slug
            ));
            $rank = 0;
            foreach ($top3 as $i => $r) {
                if ($r['ngo_slug'] === $ngo_slug) { $rank = $i + 1; break; }
            }
            $rank_str = $rank ? "{$rank}." : '?';
            ic_impi_post(
                (int) $circle->id,
                "📊 Sprint lezárult! Körünk: {$credits} kredit, {$rank_str} helyezés. Köszönjük a részvételt! 🏁",
                5
            );
        }
    }

    // 2. Start a new sprint if none active and no sprint closed in last 3 days
    $active = ic_get_current_sprint();
    if ($active) return;

    $recent = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}ic_sprints WHERE status='closed' AND ends_at > DATE_SUB(NOW(), INTERVAL 3 DAY)"
    );
    if ($recent) return;

    // Build cohort: bottom 30% of NGO circles by vote_count in last 30 days
    $total_circles = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}ic_circles WHERE type='ngo' AND is_active=1"
    );
    if ($total_circles < 3) return;
    $limit = max(1, (int) floor($total_circles * 0.30));

    $bottom = $wpdb->get_col($wpdb->prepare(
        "SELECT c.ref_slug
         FROM {$p}ic_circles c
         LEFT JOIN {$p}ic_posts p2 ON p2.circle_id = c.id AND p2.is_deleted=0
             AND p2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         WHERE c.type='ngo' AND c.is_active=1
         GROUP BY c.id
         ORDER BY SUM(COALESCE(p2.vote_count,0)) ASC
         LIMIT %d",
        $limit
    ));

    if (empty($bottom)) return;

    $starts = current_time('mysql');
    $ends   = date('Y-m-d H:i:s', strtotime('+14 days'));
    $wpdb->insert("{$p}ic_sprints", [
        'status'      => 'active',
        'starts_at'   => $starts,
        'ends_at'     => $ends,
        'cohort_json' => wp_json_encode($bottom),
        'created_at'  => $starts,
    ]);
    $sprint_id = (int) $wpdb->insert_id;

    // Notify cohort circles
    foreach ($bottom as $ngo_slug) {
        $circle = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$p}ic_circles WHERE ref_slug=%s AND is_active=1 LIMIT 1",
            $ngo_slug
        ));
        if (!$circle) continue;
        ic_impi_post(
            (int) $circle->id,
            "🚀 NGO Sprint-időszak kezdődött! 14 napig minden új tag, aki aktiválódik, 25 sprintkreditet hoz a körnek. Hajrá!",
            5
        );
    }
}

/* =========================================================================
   §16 — Napi ic_circle_stats snapshot cron
   Runs daily at 01:30 — computes yesterday's activity per active circle
   and upserts into ic_circle_stats (UNIQUE KEY uq_circle_date).
   ========================================================================= */

add_action('ic_daily_circle_stats', 'ic_run_daily_circle_stats');
function ic_run_daily_circle_stats(): void {
    global $wpdb;
    $p        = $wpdb->prefix;
    $yesterday = gmdate('Y-m-d', strtotime('-1 day'));

    $circles = $wpdb->get_col(
        "SELECT id FROM {$p}ic_circles WHERE is_active = 1"
    );

    foreach ($circles as $cid) {
        $cid = (int) $cid;

        // Posts created yesterday in this circle
        $posts_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_posts
             WHERE circle_id = %d AND is_deleted = 0
               AND DATE(created_at) = %s",
            $cid, $yesterday
        ));

        // Active members: at least 1 post/reaction in last 30 days (cached proxy via yesterday's activity)
        $active_members = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT author_hash) FROM {$p}ic_posts
             WHERE circle_id = %d AND is_deleted = 0
               AND created_at >= %s",
            $cid, gmdate('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        // New members who joined yesterday
        $new_members = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_memberships
             WHERE circle_id = %d AND is_active = 1
               AND DATE(joined_at) = %s",
            $cid, $yesterday
        ));

        // Votes generated yesterday (helpful_votes increments on posts created or voted on)
        $votes_generated = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(vote_count), 0) FROM {$p}ic_posts
             WHERE circle_id = %d AND is_deleted = 0
               AND DATE(created_at) = %s",
            $cid, $yesterday
        ));

        // Points generated: sum of pts_earned entries logged via ic_award_points yesterday
        $pts_generated = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(pts), 0) FROM {$p}ic_point_log
             WHERE circle_id = %d AND DATE(earned_at) = %s",
            $cid, $yesterday
        ));

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$p}ic_circle_stats
                (circle_id, stat_date, posts_count, active_members, new_members, votes_generated, pts_generated)
             VALUES (%d, %s, %d, %d, %d, %d, %d)
             ON DUPLICATE KEY UPDATE
                posts_count     = VALUES(posts_count),
                active_members  = VALUES(active_members),
                new_members     = VALUES(new_members),
                votes_generated = VALUES(votes_generated),
                pts_generated   = VALUES(pts_generated)",
            $cid, $yesterday,
            $posts_count, $active_members, $new_members, $votes_generated, $pts_generated
        ));
    }
}

/* =========================================================================
   §12 — Settlement verseny (Szomszéd körök)
   Monthly competition between neighboring ZIP circles.
   Winner gets +10% community_bonus (1.10×) for next month.
   ========================================================================= */

add_action('ic_settlement_monthly', 'ic_settlement_monthly_handler');
function ic_settlement_monthly_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // Reset all community_bonus to 1.0 first
    $wpdb->query("UPDATE {$p}ic_circles SET community_bonus=1.0 WHERE community_bonus != 1.0");

    $circles = $wpdb->get_results(
        "SELECT id, ref_slug, name FROM {$p}ic_circles WHERE type='settlement' AND is_active=1",
        ARRAY_A
    );

    // Group by first-2 chars of ref_slug (represents city/region)
    $by_region = [];
    foreach ($circles as $c) {
        $region = mb_substr($c['ref_slug'], 0, 2);
        $by_region[$region][] = $c;
    }

    $prev_month_start = date('Y-m-01', strtotime('-1 month'));
    $prev_month_end   = date('Y-m-t', strtotime('-1 month'));

    foreach ($by_region as $region => $group) {
        if (count($group) < 2) continue;

        // Score each circle: posts_count + new_members + votes_generated in last month
        $scores = [];
        foreach ($group as $c) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(SUM(posts_count),0) AS posts,
                        COALESCE(SUM(new_members),0) AS members,
                        COALESCE(SUM(votes_generated),0) AS votes
                 FROM {$p}ic_circle_stats
                 WHERE circle_id=%d AND stat_date BETWEEN %s AND %s",
                (int) $c['id'], $prev_month_start, $prev_month_end
            ));
            $scores[$c['id']] = [
                'circle'    => $c,
                'score'     => (int) $row->posts + (int) $row->members + (int) $row->votes,
            ];
        }

        arsort($scores);
        $entries = array_values($scores);
        $winner  = $entries[0];
        $loser   = $entries[1];

        // Award winner: community_bonus = 1.10 for the circle's members
        $wpdb->update(
            "{$p}ic_circles",
            ['community_bonus' => 1.10],
            ['id' => (int) $winner['circle']['id']]
        );

        // Hatás Emlékmű: generate GD image, then pinned Impi post in winner circle
        $w_score      = $winner['score'];
        $w_circle     = $winner['circle'];
        $month_label  = date_i18n( 'Y F', strtotime( '-1 month' ) );

        // Aggregate monthly stats for the winner circle (prev month)
        $w_monthly = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(posts_count),0) AS posts,
                    MAX(active_members)           AS members
             FROM {$p}ic_circle_stats
             WHERE circle_id = %d AND stat_date BETWEEN %s AND %s",
            (int) $w_circle['id'], $prev_month_start, $prev_month_end
        ) );
        $w_posts   = (int) ( $w_monthly->posts   ?? 0 );
        $w_members = (int) ( $w_monthly->members ?? 0 );

        $img_url = ic_generate_emlekmu_png(
            (int) $w_circle['id'],
            (string) $w_circle['name'],
            $region,
            $w_score,
            $w_members,
            $w_posts,
            $month_label
        );

        // Insert Impi post directly to support image type + meta_json
        $post_body = "🏆 Hatás Emlékmű — {$month_label}: ez a körünk volt a {$region} körzet legaktívabb köre! " .
                     "{$w_score} aktivitásponttal nyertük a havi versenyt. Extra +10% pontbónusz jár minden tagnak a következő hónapra! 🎉";

        $rate_key = 'ic_impi_rate:' . $w_circle['id'] . ':' . date('Y-m-d');
        $count    = (int) get_transient( $rate_key );
        if ( $count < 3 ) {
            set_transient( $rate_key, $count + 1, DAY_IN_SECONDS );
            $wpdb->insert( "{$p}ic_posts", [
                'circle_id'   => (int) $w_circle['id'],
                'author_hash' => 'impi',
                'author_type' => 'impi',
                'post_type'   => $img_url ? 'image' : 'text',
                'body'        => $post_body,
                'meta_json'   => $img_url ? wp_json_encode( [ 'img_url' => $img_url ] ) : null,
                'is_pinned'   => 1,
                'created_at'  => current_time( 'mysql' ),
            ] );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}ic_circles SET post_count = post_count + 1 WHERE id = %d",
                (int) $w_circle['id']
            ) );
        }

        // Encouragement for losing circle
        ic_impi_post(
            (int) $loser['circle']['id'],
            "💪 Ezen hónapban a szomszédos kör szaladt be előttünk. Ők dolgoztak keményen — de a következő hónapban mi leszünk erősebbek! Fel a fejjel! 🌱",
            5
        );
    }
}

/* =========================================================================
   §15 — Moderáció, Circle Health, Trust, Appeal
   ========================================================================= */

/** Circle Health Score (0–100) from recent ic_circle_stats + reports + reactions */
function ic_circle_health_score(int $circle_id): int {
    global $wpdb;
    $p = $wpdb->prefix;

    // Positive reaction ratio: (reactions last 7 days) / (posts last 7 days + 1)
    $reactions = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_post_reactions r
         JOIN {$p}ic_posts p2 ON p2.id=r.post_id
         WHERE p2.circle_id=%d AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        $circle_id
    ));
    $posts7 = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND is_deleted=0
         AND author_type='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        $circle_id
    ));
    $reaction_ratio = min(1.0, $reactions / max(1, $posts7));

    // Report rate: reports per 1000 posts
    $total_posts = max(1, (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND is_deleted=0 AND author_type='user'",
        $circle_id
    )));
    $total_reports = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_reports WHERE circle_id=%d",
        $circle_id
    ));
    $report_rate = $total_reports / $total_posts * 1000; // reports per 1000 posts
    $low_report_score = max(0.0, 1.0 - ($report_rate / 10));

    // Retention: 7-day return rate (new members this month who posted again)
    $month_start = date('Y-m-01');
    $new_members = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships
         WHERE circle_id=%d AND DATE(joined_at) >= %s AND is_active=1",
        $circle_id, $month_start
    ));
    $returning = 0;
    if ($new_members > 0) {
        $returning = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT m.pid_hash)
             FROM {$p}ic_memberships m
             JOIN {$p}ic_posts p3 ON p3.author_hash=m.pid_hash AND p3.circle_id=m.circle_id
                 AND p3.author_type='user' AND p3.is_deleted=0
                 AND p3.created_at >= DATE_ADD(m.joined_at, INTERVAL 7 DAY)
             WHERE m.circle_id=%d AND DATE(m.joined_at) >= %s",
            $circle_id, $month_start
        ));
    }
    $retention = ($new_members > 0) ? ($returning / $new_members) : 0.5;

    // Decision post activity
    $decision_total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND post_type='decision' AND is_deleted=0",
        $circle_id
    ));
    $decision_closed = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts
         WHERE circle_id=%d AND post_type='decision' AND is_deleted=0 AND vote_count >= 3",
        $circle_id
    ));
    $decision_score = ($decision_total > 0) ? ($decision_closed / $decision_total) : 0.5;

    // Weighted score: 35% reaction, 30% low-report, 20% retention, 15% decision
    $score = (int) round(
        $reaction_ratio * 35 +
        $low_report_score * 30 +
        $retention * 20 +
        $decision_score * 15
    );

    return max(0, min(100, $score));
}

function ic_rest_circle_health(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p   = $wpdb->prefix;
    $cid = (int) $req->get_param('id');
    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT id, community_bonus FROM {$p}ic_circles WHERE id=%d AND is_active=1", $cid
    ));
    if (!$circle) {
        return ic_json_error('Kör nem található.', 404);
    }
    return ic_json_ok([
        'circle_id'       => $cid,
        'health_score'    => ic_circle_health_score($cid),
        'community_bonus' => (float) $circle->community_bonus,
    ]);
}

/** Record a moderation audit log entry */
function ic_log_moderation_action(
    int $circle_id,
    ?int $post_id,
    ?string $target_hash,
    string $actor_hash,
    string $actor_type,
    string $action,
    string $reason = ''
): void {
    global $wpdb;
    $wpdb->insert("{$wpdb->prefix}ic_moderation_actions", [
        'circle_id'   => $circle_id,
        'post_id'     => $post_id,
        'target_hash' => $target_hash,
        'actor_hash'  => $actor_hash,
        'actor_type'  => $actor_type,
        'action'      => $action,
        'reason'      => $reason,
        'created_at'  => current_time('mysql'),
    ]);
}

/** POST /moderation/action — admin: timeout, remove post, dismiss report */
function ic_rest_moderation_action(WP_REST_Request $req): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_id   = (int) $req->get_param('circle_id');
    $post_id     = (int) ($req->get_param('post_id') ?? 0);
    $target_hash = sanitize_key($req->get_param('target_hash') ?? '');
    $action      = sanitize_key($req->get_param('action') ?? '');
    $reason      = sanitize_textarea_field($req->get_param('reason') ?? '');
    $actor_hash  = 'system'; // admin actions use system identifier

    $allowed_actions = ['remove_post', 'timeout_member', 'dismiss_report', 'approve_appeal', 'uphold_appeal'];
    if (!in_array($action, $allowed_actions, true)) {
        return ic_json_error('Érvénytelen moderációs akció.', 422);
    }

    switch ($action) {
        case 'remove_post':
            if (!$post_id) return ic_json_error('post_id szükséges.', 422);
            $wpdb->update("{$p}ic_posts", ['is_deleted' => 1], ['id' => $post_id]);
            // Pending reports → actioned
            $wpdb->update("{$p}ic_reports", ['status' => 'actioned', 'reviewed_at' => current_time('mysql')], ['post_id' => $post_id]);
            ic_log_moderation_action($circle_id, $post_id, $target_hash, $actor_hash, 'admin', 'remove_post', $reason);
            // § Sprint 13 — Impi értesítés az érintett tagnak
            if ($target_hash) {
                ic_impi_notify_moderation($circle_id, $target_hash, 'remove_post');
            }
            break;

        case 'timeout_member':
            if (!$target_hash) return ic_json_error('target_hash szükséges.', 422);
            $until = date('Y-m-d H:i:s', strtotime('+7 days'));
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$p}ic_member_trust (circle_id, pid_hash, timeout_until)
                 VALUES (%d, %s, %s)
                 ON DUPLICATE KEY UPDATE timeout_until=%s",
                $circle_id, $target_hash, $until, $until
            ));
            ic_log_moderation_action($circle_id, $post_id ?: null, $target_hash, $actor_hash, 'admin', 'timeout', $reason);
            // § Sprint 13 — Impi értesítés az érintett tagnak
            ic_impi_notify_moderation($circle_id, $target_hash, 'timeout_member');
            break;

        case 'dismiss_report':
            if ($post_id) {
                $wpdb->update("{$p}ic_reports", [
                    'status'      => 'dismissed',
                    'reviewed_at' => current_time('mysql'),
                ], ['post_id' => $post_id, 'status' => 'pending']);
            }
            ic_log_moderation_action($circle_id, $post_id ?: null, $target_hash, $actor_hash, 'admin', 'dismiss_report', $reason);
            break;

        case 'approve_appeal':
        case 'uphold_appeal':
            $appeal_id = (int) $req->get_param('appeal_id');
            if (!$appeal_id) return ic_json_error('appeal_id szükséges.', 422);
            $new_status = ($action === 'approve_appeal') ? 'approved' : 'upheld';
            $wpdb->update("{$p}ic_appeals", [
                'status'      => $new_status,
                'reviewed_by' => $actor_hash,
                'reviewed_at' => current_time('mysql'),
            ], ['id' => $appeal_id]);
            ic_log_moderation_action($circle_id, null, null, $actor_hash, 'admin', $action, $reason);
            // § Sprint 13 — Ha approved: eredeti akció visszavonása + Impi értesítés
            if ($action === 'approve_appeal') {
                ic_appeal_reverse($appeal_id, $circle_id);
            }
            break;
    }

    return ic_json_ok(['done' => true, 'action' => $action]);
}

/** GET /moderation/mine — user retrieves their own report history */
function ic_rest_moderation_mine(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.circle_id, r.post_id, r.reason, r.status, r.created_at
         FROM {$p}ic_reports r
         WHERE r.reporter_hash=%s
         ORDER BY r.created_at DESC LIMIT 20",
        $pid_hash
    ), ARRAY_A);

    return ic_json_ok(['reports' => $rows]);
}

/** POST /circles/{circle_id}/posts/{post_id}/appeal */
function ic_rest_post_appeal(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if (!$pid_hash) {
        return ic_json_error('Azonosítás szükséges.', 401);
    }

    global $wpdb;
    $p        = $wpdb->prefix;
    $cid      = (int) $req->get_param('circle_id');
    $post_id  = (int) $req->get_param('post_id');
    $reason   = sanitize_textarea_field($req->get_param('appeal_reason') ?? '');

    if ($reason === '') {
        return ic_json_error('Indokok megadása kötelező.', 422);
    }

    // Find the most recent moderation action for this post
    $mod_action = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_moderation_actions
         WHERE circle_id=%d AND post_id=%d AND action='remove_post'
         ORDER BY created_at DESC LIMIT 1",
        $cid, $post_id
    ));
    if (!$mod_action) {
        return ic_json_error('Nem található moderációs döntés ehhez a poszthoz.', 404);
    }

    // Max 1 appeal per modaction per user
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$p}ic_appeals WHERE modaction_id=%d AND appellant_hash=%s",
        (int) $mod_action->id, $pid_hash
    ));
    if ($existing) {
        return ic_json_error('Már benyújtottál felülvizsgálati kérelmet ehhez a döntéshez.', 422);
    }

    $wpdb->insert("{$p}ic_appeals", [
        'modaction_id'   => (int) $mod_action->id,
        'appellant_hash' => $pid_hash,
        'appeal_reason'  => $reason,
        'status'         => 'pending',
        'created_at'     => current_time('mysql'),
    ]);

    return ic_json_ok(['appeal_submitted' => true]);
}

/* --- §15 Trusted Reporter promotion (weekly cron) ----------------------- */

add_action('ic_trusted_reporter_check', 'ic_promote_trusted_reporters');
function ic_promote_trusted_reporters(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $circles = $wpdb->get_col("SELECT id FROM {$p}ic_circles WHERE is_active=1");
    foreach ($circles as $cid) {
        // Max 1 promotion per week per circle
        $promo_key = 'ic_tr_promo:' . $cid;
        if (get_transient($promo_key)) continue;

        // Candidate: trust_level=2, is_trusted_reporter=0, positive behavioral pattern
        // (high report accuracy: their reports that got actioned / total reports)
        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT t.pid_hash
             FROM {$p}ic_member_trust t
             WHERE t.circle_id=%d AND t.trust_level>=2 AND t.is_trusted_reporter=0
               AND t.strikes=0
             LIMIT 5",
            $cid
        ));

        foreach ($candidates as $c) {
            $total_reports = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}ic_reports WHERE reporter_hash=%s AND circle_id=%d",
                $c->pid_hash, $cid
            ));
            if ($total_reports < 3) continue;

            $actioned = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}ic_reports WHERE reporter_hash=%s AND circle_id=%d AND status='actioned'",
                $c->pid_hash, $cid
            ));
            if ($actioned / $total_reports < 0.5) continue;

            // Promote to trusted reporter
            $wpdb->update(
                "{$p}ic_member_trust",
                ['is_trusted_reporter' => 1],
                ['circle_id' => $cid, 'pid_hash' => $c->pid_hash]
            );
            ic_queue_badge($c->pid_hash, 'trusted_guardian', (int) $cid);
            ic_impi_post(
                (int) $cid,
                "🛡️ Köszönet egy körtagnak, aki segít megőrizni a tér minőségét! Közösségi Őr státuszt nyert. Gratulálunk! 🦡",
                3
            );
            set_transient($promo_key, 1, 7 * DAY_IN_SECONDS);
            break; // max 1 promotion per circle per week
        }
    }
}

/* --- §15 Auto-timeout on 3 strikes (hooked to report actioned) ---------- */

function ic_maybe_auto_timeout(int $circle_id, string $pid_hash): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $trust = $wpdb->get_row($wpdb->prepare(
        "SELECT id, strikes FROM {$p}ic_member_trust WHERE circle_id=%d AND pid_hash=%s",
        $circle_id, $pid_hash
    ));
    if (!$trust) return;

    $new_strikes = (int) $trust->strikes + 1;
    if ($new_strikes >= 3) {
        $until = date('Y-m-d H:i:s', strtotime('+7 days'));
        $wpdb->update(
            "{$p}ic_member_trust",
            ['strikes' => $new_strikes, 'timeout_until' => $until],
            ['id' => (int) $trust->id]
        );
        ic_log_moderation_action($circle_id, null, $pid_hash, 'system', 'system', 'auto_timeout', '3 strikes reached');
    } else {
        $wpdb->update(
            "{$p}ic_member_trust",
            ['strikes' => $new_strikes],
            ['id' => (int) $trust->id]
        );
    }
}

/* ===========================================================================
 * §13  NGO ADMIN AUTH + ADVISOR QUOTA — DB 1.3.5
 * =========================================================================*/

/**
 * Generate a secure session token for an NGO account.
 * Stored as a WP transient; valid for 24 h.
 */
function ic_ngo_generate_token( string $ngo_slug ): string {
    $token = bin2hex( random_bytes( 32 ) );
    set_transient( 'ic_ngo_tok_' . $token, $ngo_slug, DAY_IN_SECONDS );
    return $token;
}

/**
 * Extract ngo_slug from a Bearer token in the request header.
 * Returns the slug on success, or null on failure.
 */
function ic_ngo_verify_token( WP_REST_Request $req ): ?string {
    $auth = $req->get_header( 'Authorization' );
    if ( ! $auth || strpos( $auth, 'Bearer ' ) !== 0 ) {
        return null;
    }
    $token  = substr( $auth, 7 );
    $slug   = get_transient( 'ic_ngo_tok_' . $token );
    return $slug ? (string) $slug : null;
}

/**
 * Guard helper: returns ngo_slug or WP_Error 401.
 */
function ic_ngo_guard( WP_REST_Request $req ) {
    $slug = ic_ngo_verify_token( $req );
    if ( ! $slug ) {
        return new WP_Error( 'ic_unauthorized', __( 'NGO auth required.', 'ic' ), [ 'status' => 401 ] );
    }
    return $slug;
}

/* ===  Auth endpoints ======================================================= */

/** POST /ngo/login */
function ic_rest_ngo_login( WP_REST_Request $req ): WP_REST_Response {
    if (ic_test_mode_enabled()) {
        $pseudo = ic_test_mode_requested_pseudo($req);
        $ngo_slug = ic_test_mode_requested_ngo_slug($req);

        if ($pseudo !== '' && $ngo_slug !== '') {
            if (function_exists('impactshop_identity_set_pseudo_cookie')) {
                impactshop_identity_set_pseudo_cookie($pseudo);
            }
            if (function_exists('impactshop_active_ngo_set_cookie')) {
                impactshop_active_ngo_set_cookie($ngo_slug);
            }

            $token = ic_ngo_generate_token($ngo_slug);
            return new WP_REST_Response([
                'token'     => $token,
                'ngo_slug'  => $ngo_slug,
                'pseudo_id' => $pseudo,
                'test_mode' => true,
            ], 200);
        }
    }

    global $wpdb;
    $p        = $wpdb->prefix;
    $email    = sanitize_email( (string) $req->get_param( 'email' ) );
    $password = (string) $req->get_param( 'password' );

    if ( ! $email || ! $password ) {
        return new WP_REST_Response( [ 'error' => 'missing_fields' ], 400 );
    }

    $account = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ic_ngo_accounts WHERE email = %s AND is_active = 1",
        $email
    ) );

    if ( ! $account || ! wp_check_password( $password, $account->pw_hash ) ) {
        return new WP_REST_Response( [ 'error' => 'invalid_credentials' ], 401 );
    }

    $token = ic_ngo_generate_token( $account->ngo_slug );
    $wpdb->update(
        "{$p}ic_ngo_accounts",
        [ 'last_login' => current_time( 'mysql' ) ],
        [ 'id' => (int) $account->id ]
    );

    return new WP_REST_Response( [ 'token' => $token, 'ngo_slug' => $account->ngo_slug ], 200 );
}

/** POST /ngo/reset-password — sends a reset link to the registered email */
function ic_rest_ngo_reset_request( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $p     = $wpdb->prefix;
    $email = sanitize_email( (string) $req->get_param( 'email' ) );

    if ( ! $email ) {
        return new WP_REST_Response( [ 'error' => 'missing_email' ], 400 );
    }

    $account = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, ngo_slug FROM {$p}ic_ngo_accounts WHERE email = %s AND is_active = 1",
        $email
    ) );

    // Always return 200 to avoid user enumeration
    if ( $account ) {
        $token   = wp_generate_password( 64, false );
        $expires = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
        $wpdb->update(
            "{$p}ic_ngo_accounts",
            [ 'reset_token' => $token, 'reset_expires' => $expires ],
            [ 'id' => (int) $account->id ]
        );
        $reset_url = site_url( '/impact-shop_ngo/?reset=' . rawurlencode( $token ) );
        wp_mail(
            $email,
            __( 'ImpactShop NGO — jelszó visszaállítás', 'ic' ),
            sprintf(
                __( "A jelszó visszaállítási link (1 óráig érvényes):\n%s", 'ic' ),
                $reset_url
            )
        );
    }

    return new WP_REST_Response( [ 'ok' => true ], 200 );
}

/** POST /ngo/reset-password/confirm — validates token and sets new password */
function ic_rest_ngo_reset_confirm( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $p        = $wpdb->prefix;
    $token    = sanitize_text_field( (string) $req->get_param( 'token' ) );
    $password = (string) $req->get_param( 'password' );

    if ( ! $token || strlen( $password ) < 8 ) {
        return new WP_REST_Response( [ 'error' => 'invalid_input' ], 400 );
    }

    $account = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$p}ic_ngo_accounts WHERE reset_token = %s AND reset_expires > %s AND is_active = 1",
        $token, current_time( 'mysql' )
    ) );

    if ( ! $account ) {
        return new WP_REST_Response( [ 'error' => 'invalid_or_expired_token' ], 400 );
    }

    $wpdb->update(
        "{$p}ic_ngo_accounts",
        [ 'pw_hash' => wp_hash_password( $password ), 'reset_token' => null, 'reset_expires' => null ],
        [ 'id' => (int) $account->id ]
    );

    return new WP_REST_Response( [ 'ok' => true ], 200 );
}

/* ===  NGO Circle stats ===================================================== */

/** GET /ngo/circle — circle + advisor quota summary for the authed NGO */
function ic_rest_ngo_circle_stats( WP_REST_Request $req ) {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) {
        return $ngo_slug;
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $circle = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, ref_slug, community_bonus, last_blast_at
         FROM {$p}ic_circles
         WHERE ref_slug = %s AND type = 'ngo'",
        $ngo_slug
    ) );

    if ( ! $circle ) {
        return new WP_REST_Response( [ 'error' => 'circle_not_found' ], 404 );
    }

    // Aggregate this-month stats from daily snapshot rows
    $month_start = gmdate( 'Y-m-01' );
    $today       = gmdate( 'Y-m-d' );
    $monthly = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(posts_count),0)     AS monthly_posts,
                COALESCE(SUM(active_members),0)  AS active_members,
                COALESCE(SUM(votes_generated),0) AS total_votes,
                MAX(stat_date)                   AS last_stat_date
         FROM {$p}ic_circle_stats
         WHERE circle_id = %d AND stat_date BETWEEN %s AND %s",
        (int) $circle->id, $month_start, $today
    ) );
    $health_score = ic_circle_health_score( (int) $circle->id );

    $ym      = gmdate( 'Y-m' );
    $blasted = $circle->last_blast_at && substr( $circle->last_blast_at, 0, 7 ) === $ym;

    $quotas = [];
    foreach ( [ 'legal', 'finance', 'marketing' ] as $ch ) {
        $cap  = ic_ngo_calc_quota( $ngo_slug, $ch );
        $used = ic_ngo_advisor_used( $ngo_slug, $ch, $ym );
        $quotas[ $ch ] = [ 'cap' => $cap, 'used' => $used, 'remaining' => max( 0, $cap - $used ) ];
    }

    return new WP_REST_Response( [
        'circle'       => [
            'id'            => $circle->id,
            'ref_slug'      => $circle->ref_slug,
            'community_bonus' => (float) $circle->community_bonus,
            'last_blast_at' => $circle->last_blast_at,
            'monthly_posts' => (int) ( $monthly->monthly_posts  ?? 0 ),
            'active_members'=> (int) ( $monthly->active_members ?? 0 ),
            'total_votes'   => (int) ( $monthly->total_votes    ?? 0 ),
            'last_stat_date'=> $monthly->last_stat_date ?? null,
            'health_score'  => $health_score,
        ],
        'blast_locked' => $blasted,
        'advisor'      => $quotas,
    ], 200 );
}

/* ===  Email blast ========================================================== */

/** POST /ngo/circle/blast — sends a campaign email to all active circle members (1/month) */
function ic_rest_ngo_blast( WP_REST_Request $req ) {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) {
        return $ngo_slug;
    }

    global $wpdb;
    $p   = $wpdb->prefix;
    $ym  = gmdate( 'Y-m' );

    $circle = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, last_blast_at FROM {$p}ic_circles WHERE ref_slug = %s AND type = 'ngo'",
        $ngo_slug
    ) );

    if ( ! $circle ) {
        return new WP_REST_Response( [ 'error' => 'circle_not_found' ], 404 );
    }

    if ( $circle->last_blast_at && substr( $circle->last_blast_at, 0, 7 ) === $ym ) {
        return new WP_REST_Response( [ 'error' => 'already_blasted_this_month' ], 429 );
    }

    $subject = sanitize_text_field( (string) $req->get_param( 'subject' ) );
    $body    = wp_kses_post( (string) $req->get_param( 'body' ) );

    if ( ! $subject || ! $body ) {
        return new WP_REST_Response( [ 'error' => 'missing_subject_or_body' ], 400 );
    }

    // Collect member emails: memberships are pseudo-anonymous (pid_hash),
    // so we send to WP users whose pseudo_id cookie hash matches a membership.
    // For MVP, this is a no-op (pseudo-anonymous users have no email). 
    // When WP-login backed memberships exist, use a user_id join.
    $emails = [];

    $sent = 0;
    foreach ( $emails as $email ) {
        if ( is_email( $email ) && wp_mail( $email, $subject, $body ) ) {
            $sent++;
        }
    }

    $wpdb->update(
        "{$p}ic_circles",
        [ 'last_blast_at' => current_time( 'mysql' ) ],
        [ 'id' => (int) $circle->id ]
    );

    return new WP_REST_Response( [ 'sent' => $sent ], 200 );
}

/* ===  Advisor quota helpers ================================================ */

/**
 * Calculate the current monthly quota cap for one channel.
 * Base: legal=5, finance=5, marketing=8.
 * Bonuses (+1 per threshold, same for all channels):
 *   +1 per 25 active_members, +1 per 40 monthly_posts, +1 per 20 valid_invites
 * Hard caps: legal=25, finance=20, marketing=30.
 */
function ic_ngo_calc_quota( string $ngo_slug, string $channel ): int {
    global $wpdb;
    $p = $wpdb->prefix;

    $bases    = [ 'legal' => 5, 'finance' => 5, 'marketing' => 8 ];
    $hard_cap = [ 'legal' => 25, 'finance' => 20, 'marketing' => 30 ];
    $base     = $bases[ $channel ] ?? 5;
    $cap      = $hard_cap[ $channel ] ?? 20;

    // Aggregate last-30-day stats from daily snapshot table
    $month_start = gmdate( 'Y-m-01' );
    $today       = gmdate( 'Y-m-d' );
    $circle_id_for_stats = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug = %s AND type = 'ngo'",
        $ngo_slug
    ) );
    $stats = $circle_id_for_stats ? $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(active_members),0) AS active_members,
                COALESCE(SUM(posts_count),0)    AS posts_count
         FROM {$p}ic_circle_stats
         WHERE circle_id = %d AND stat_date BETWEEN %s AND %s",
        $circle_id_for_stats, $month_start, $today
    ) ) : null;

    $active_members = $stats ? (int) $stats->active_members : 0;
    $monthly_posts  = $stats ? (int) $stats->posts_count    : 0;

    // Valid invites: memberships created via invite in last 30 days
    $circle_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug = %s AND type = 'ngo'",
        $ngo_slug
    ) );
    $valid_invites = $circle_id ? (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_invite_claims
         WHERE circle_id = %d AND created_at >= %s",
        $circle_id, gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
    ) ) : 0;

    $bonus = (int) floor( $active_members / 25 )
           + (int) floor( $monthly_posts  / 40 )
           + (int) floor( $valid_invites  / 20 );

    return min( $base + $bonus, $cap );
}

/**
 * How many advisor units has this NGO consumed for a channel in a given month?
 * $year_month format: '2026-03'
 */
function ic_ngo_advisor_used( string $ngo_slug, string $channel, string $year_month ): int {
    global $wpdb;
    $p = $wpdb->prefix;
    $used = $wpdb->get_var( $wpdb->prepare(
        "SELECT units_used FROM {$p}ic_ngo_advisor_usage
         WHERE ngo_slug = %s AND channel = %s AND year_month = %s",
        $ngo_slug, $channel, $year_month
    ) );
    return (int) $used;
}

/**
 * Consume one advisor unit.  Returns true on success, false if quota exceeded.
 */
function ic_ngo_advisor_consume( string $ngo_slug, string $channel ): bool {
    global $wpdb;
    $p  = $wpdb->prefix;
    $ym = gmdate( 'Y-m' );

    $cap  = ic_ngo_calc_quota( $ngo_slug, $channel );
    $used = ic_ngo_advisor_used( $ngo_slug, $channel, $ym );

    if ( $used >= $cap ) {
        return false;
    }

    // Upsert: insert or increment
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$p}ic_ngo_advisor_usage (ngo_slug, channel, year_month, units_used)
         VALUES (%s, %s, %s, 1)
         ON DUPLICATE KEY UPDATE units_used = units_used + 1",
        $ngo_slug, $channel, $ym
    ) );

    return true;
}

/* ===  Advisor REST endpoints ============================================== */

/** GET /ngo/advisor/quota — returns quota breakdown for all 3 channels */
function ic_rest_ngo_advisor_quota( WP_REST_Request $req ) {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) {
        return $ngo_slug;
    }

    $ym     = gmdate( 'Y-m' );
    $result = [];
    foreach ( [ 'legal', 'finance', 'marketing' ] as $ch ) {
        $cap  = ic_ngo_calc_quota( $ngo_slug, $ch );
        $used = ic_ngo_advisor_used( $ngo_slug, $ch, $ym );
        $result[ $ch ] = [ 'cap' => $cap, 'used' => $used, 'remaining' => max( 0, $cap - $used ) ];
    }

    return new WP_REST_Response( [ 'year_month' => $ym, 'quota' => $result ], 200 );
}

/**
 * POST /ngo/advisor/ask — submit an advisor question, consumes one quota unit.
 * Body: { channel: 'legal'|'finance'|'marketing', question: string }
 * For MVP the question is saved to a WP option log; no AI call is made here.
 */
function ic_rest_ngo_advisor_ask( WP_REST_Request $req ) {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) {
        return $ngo_slug;
    }

    $channel  = sanitize_key( (string) $req->get_param( 'channel' ) );
    $question = sanitize_textarea_field( (string) $req->get_param( 'question' ) );

    if ( ! in_array( $channel, [ 'legal', 'finance', 'marketing' ], true ) ) {
        return new WP_REST_Response( [ 'error' => 'invalid_channel' ], 400 );
    }

    if ( ! $question || strlen( $question ) < 10 ) {
        return new WP_REST_Response( [ 'error' => 'question_too_short' ], 400 );
    }

    if ( ! ic_ngo_advisor_consume( $ngo_slug, $channel ) ) {
        return new WP_REST_Response( [ 'error' => 'quota_exceeded' ], 429 );
    }

    // Store for async processing (append to option-based log)
    $log   = get_option( 'ic_advisor_queue', [] );
    $log[] = [
        'ngo_slug'   => $ngo_slug,
        'channel'    => $channel,
        'question'   => $question,
        'created_at' => current_time( 'mysql' ),
    ];
    update_option( 'ic_advisor_queue', array_slice( $log, -500 ) ); // keep last 500

    return new WP_REST_Response( [
        'ok'      => true,
        'message' => __( 'Kérdésed beérkezett — Impi hamarosan válaszol.', 'ic' ),
    ], 200 );
}

/* =========================================================================
   §16 — Hatás Emlékmű: GD library PNG generator
   Generates a 600×340 shareable image for the monthly settlement winner.
   Saved to wp-content/uploads/ic-emblems/YYYY-MM-{circle_id}.png
   Returns the public URL on success, empty string on failure.
   ========================================================================= */

function ic_generate_emlekmu_png(
    int    $circle_id,
    string $circle_name,
    string $region,
    int    $score,
    int    $members,
    int    $posts,
    string $month_label   // e.g. "2026 március"
): string {
    if ( ! function_exists( 'imagecreatetruecolor' ) ) {
        return ''; // GD not available on this host
    }

    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/ic-emblems';
    $url_base = $upload['baseurl'] . '/ic-emblems';

    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    $filename = sanitize_file_name( 'emlekmu-' . gmdate('Y-m') . '-' . $circle_id . '.png' );
    $filepath = $dir . '/' . $filename;

    $W = 600; $H = 340;
    $im = imagecreatetruecolor( $W, $H );

    // Colours
    $bg_top    = imagecolorallocate( $im, 27,  94,  32  ); // deep green
    $bg_bot    = imagecolorallocate( $im, 46, 125,  50  ); // mid green
    $gold      = imagecolorallocate( $im, 255, 193,   7  );
    $white     = imagecolorallocate( $im, 255, 255, 255  );
    $light     = imagecolorallocate( $im, 200, 230, 201  );
    $dark_text = imagecolorallocate( $im,  27,  94,  32  );

    // Gradient background (simple two-band)
    imagefilledrectangle( $im, 0, 0, $W, $H / 2, $bg_top );
    imagefilledrectangle( $im, 0, $H / 2, $W, $H, $bg_bot );

    // Gold top bar
    imagefilledrectangle( $im, 0, 0, $W, 6, $gold );

    // Trophy emoji fallback text
    $font_size = 5; // built-in GD font (1-5)

    // Title
    $title = 'HATAS EMLEKMŰ — ' . mb_strtoupper( $month_label );
    $tw = imagefontwidth( $font_size ) * strlen( $title );
    imagestring( $im, $font_size, (int)(($W - $tw) / 2), 20, $title, $gold );

    // Circle name
    $cn_short = mb_substr( $circle_name, 0, 40 );
    $cnw = imagefontwidth( 5 ) * strlen( $cn_short );
    imagestring( $im, 5, (int)(($W - $cnw) / 2), 55, $cn_short, $white );

    // Region badge
    $region_label = 'Korzetkod: ' . mb_strtoupper( $region );
    imagestring( $im, 3, 30, 100, $region_label, $light );

    // Separator line
    imageline( $im, 30, 125, $W - 30, 125, $gold );

    // Stats row
    $stats_y = 145;
    $col1_x  = 50; $col2_x = 220; $col3_x = 390;
    imagestring( $im, 4, $col1_x, $stats_y,      'Tagok',         $light );
    imagestring( $im, 5, $col1_x, $stats_y + 22, (string) $members, $white );
    imagestring( $im, 4, $col2_x, $stats_y,      'Posztok',       $light );
    imagestring( $im, 5, $col2_x, $stats_y + 22, (string) $posts,   $white );
    imagestring( $im, 4, $col3_x, $stats_y,      'Aktivitas',     $light );
    imagestring( $im, 5, $col3_x, $stats_y + 22, (string) $score,   $white );

    // Separator
    imageline( $im, 30, 210, $W - 30, 210, $gold );

    // Congratulations text
    $line1 = 'A KORZET LEGAKTIVABB KORE EZEN A HONAP!';
    $line2 = '+10% pontbonusz minden tagnak a kovetkezo honapra';
    $l1w = imagefontwidth( 3 ) * strlen( $line1 );
    $l2w = imagefontwidth( 2 ) * strlen( $line2 );
    imagestring( $im, 3, (int)(($W - $l1w) / 2), 225, $line1, $gold );
    imagestring( $im, 2, (int)(($W - $l2w) / 2), 250, $line2, $light );

    // Bottom branding
    $brand = 'impactshop.hu  |  Hatas Korok';
    $bw    = imagefontwidth( 2 ) * strlen( $brand );
    imagestring( $im, 2, (int)(($W - $bw) / 2), $H - 24, $brand, $light );

    // Gold bottom bar
    imagefilledrectangle( $im, 0, $H - 6, $W, $H, $gold );

    ob_start();
    imagepng( $im );
    $raw = ob_get_clean();
    imagedestroy( $im );

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    if ( file_put_contents( $filepath, $raw ) === false ) {
        return '';
    }

    return $url_base . '/' . $filename;
}

/* =========================================================================
   §16 — Admin REST: GET /ic/v1/admin/circles
   Requires manage_options capability (WordPress admin only).
   Returns all active circles with health score, stats summary and bonus info.
   ========================================================================= */

add_action( 'rest_api_init', function () {
    register_rest_route( 'ic/v1', '/admin/circles', [
        'methods'             => 'GET',
        'callback'            => 'ic_rest_admin_circles',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    ] );
} );

function ic_rest_admin_circles( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $p = $wpdb->prefix;

    $circles = $wpdb->get_results(
        "SELECT c.id, c.ref_slug, c.name, c.type, c.is_active,
                c.community_bonus, c.last_blast_at,
                COUNT(DISTINCT m.pid_hash) AS member_count
         FROM {$p}ic_circles c
         LEFT JOIN {$p}ic_memberships m ON m.circle_id = c.id AND m.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.id
         ORDER BY c.type, c.ref_slug",
        ARRAY_A
    );

    $month_start = gmdate( 'Y-m-01' );
    $today       = gmdate( 'Y-m-d' );

    $result = [];
    foreach ( $circles as $c ) {
        $cid = (int) $c['id'];

        $monthly = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(posts_count),0)     AS monthly_posts,
                    COALESCE(SUM(new_members),0)      AS new_members,
                    COALESCE(SUM(votes_generated),0)  AS votes_generated,
                    MAX(stat_date)                    AS last_stat
             FROM {$p}ic_circle_stats
             WHERE circle_id = %d AND stat_date BETWEEN %s AND %s",
            $cid, $month_start, $today
        ) );

        $health = ic_circle_health_score( $cid );

        $result[] = [
            'id'             => $cid,
            'ref_slug'       => $c['ref_slug'],
            'name'           => $c['name'],
            'type'           => $c['type'],
            'member_count'   => (int) $c['member_count'],
            'community_bonus'=> (float) $c['community_bonus'],
            'last_blast_at'  => $c['last_blast_at'],
            'monthly_posts'  => (int) ( $monthly->monthly_posts   ?? 0 ),
            'new_members'    => (int) ( $monthly->new_members      ?? 0 ),
            'votes_generated'=> (int) ( $monthly->votes_generated  ?? 0 ),
            'last_stat'      => $monthly->last_stat ?? null,
            'health_score'   => $health,
        ];
    }

    return new WP_REST_Response( $result, 200 );
}

/* ── Sprint 8: NGO admin Impi üzenetek ────────────────────────────────────── */

/**
 * GET /ngo/impi-posts — legutóbbi Impi posztok az NGO körében (max 50).
 */
function ic_rest_ngo_impi_posts( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) return $ngo_slug;

    global $wpdb;
    $p = $wpdb->prefix;

    $circle = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug = %s AND type = 'ngo'",
        $ngo_slug
    ) );
    if ( ! $circle ) return ic_json_error( 'Kör nem található.', 404 );

    $posts = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, body, created_at
         FROM {$p}ic_posts
         WHERE circle_id = %d
           AND author_type = 'impi'
           AND is_deleted  = 0
         ORDER BY created_at DESC
         LIMIT 50",
        (int) $circle->id
    ), ARRAY_A );

    return new WP_REST_Response( $posts ?: [], 200 );
}

/**
 * DELETE /ngo/impi-posts/{post_id} — Impi poszt törlése (NGO admin joggal).
 */
function ic_rest_ngo_impi_delete( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) return $ngo_slug;

    global $wpdb;
    $p       = $wpdb->prefix;
    $post_id = (int) $req->get_param( 'post_id' );

    $circle = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug = %s AND type = 'ngo'",
        $ngo_slug
    ) );
    if ( ! $circle ) return ic_json_error( 'Kör nem található.', 404 );

    $post = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$p}ic_posts
         WHERE id = %d AND circle_id = %d AND author_type = 'impi' AND is_deleted = 0",
        $post_id, (int) $circle->id
    ) );
    if ( ! $post ) return ic_json_error( 'Impi poszt nem található.', 404 );

    $wpdb->update( "{$p}ic_posts", [ 'is_deleted' => 1 ], [ 'id' => $post_id ] );

    return ic_json_ok( [ 'deleted' => true ] );
}

/* ── Sprint 9: aktiváció webhook + queue ─────────────────────────────────── */

/**
 * POST /ngo/sprint-activate
 * NGO token alapú webhook: rögzíti a sprint aktivációs eseményt.
 * Body: { type: 'ad'|'vote'|'offerwall'|'post'|'other', pid_hash?: string }
 */
function ic_rest_ngo_sprint_activate( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard( $req );
    if ( is_wp_error( $ngo_slug ) ) return $ngo_slug;

    $valid_types = [ 'ad', 'vote', 'offerwall', 'post', 'other' ];
    $type        = sanitize_key( $req->get_param('type') ?? 'other' );
    if ( ! in_array( $type, $valid_types, true ) ) {
        return ic_json_error( 'Érvénytelen activation type.', 400 );
    }

    // pid_hash kötelező — webhook híváskor nincs böngésző cookie
    $pid_hash = sanitize_text_field( $req->get_param('pid_hash') ?? '' );
    if ( ! $pid_hash || strlen( $pid_hash ) < 8 ) {
        return ic_json_error( 'pid_hash kötelező (min. 8 karakter).', 400 );
    }

    ic_record_sprint_activation( $pid_hash, $ngo_slug, $type );

    global $wpdb;
    $p      = $wpdb->prefix;
    $sprint = ic_get_current_sprint();
    if ( ! $sprint ) {
        return ic_json_ok( [ 'recorded' => false, 'reason' => 'no_active_sprint' ] );
    }

    $credits = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) * 25 FROM {$p}ic_sprint_events
         WHERE sprint_id=%d AND ngo_slug=%s AND is_validated=1 AND is_pending_review=0",
        (int) $sprint['id'], $ngo_slug
    ) );

    return ic_json_ok( [ 'recorded' => true, 'ngo_credits' => $credits ] );
}

/**
 * GET /admin/sprint-queue — pending review events listája (platform admin).
 */
function ic_rest_admin_sprint_queue( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $p      = $wpdb->prefix;
    $sprint = ic_get_current_sprint();
    if ( ! $sprint ) {
        return new WP_REST_Response( [ 'sprint' => null, 'queue' => [] ], 200 );
    }

    $events = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, ngo_slug, pid_hash, activation_type, created_at
         FROM {$p}ic_sprint_events
         WHERE sprint_id = %d AND is_pending_review = 1
         ORDER BY created_at ASC
         LIMIT 200",
        (int) $sprint['id']
    ), ARRAY_A );

    return new WP_REST_Response( [
        'sprint_id'  => (int) $sprint['id'],
        'ends_at'    => $sprint['ends_at'],
        'queue_count'=> count( $events ),
        'queue'      => $events ?: [],
    ], 200 );
}

/**
 * POST /admin/sprint-queue/{event_id} — approve vagy reject.
 * Body: { action: 'approve'|'reject' }
 */
function ic_rest_admin_sprint_queue_action( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $p        = $wpdb->prefix;
    $event_id = (int) $req->get_param( 'event_id' );
    $action   = sanitize_key( $req->get_param( 'action' ) ?? '' );

    if ( ! in_array( $action, [ 'approve', 'reject' ], true ) ) {
        return ic_json_error( 'action kell: approve vagy reject.', 400 );
    }

    $event = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ic_sprint_events WHERE id = %d AND is_pending_review = 1",
        $event_id
    ) );
    if ( ! $event ) return ic_json_error( 'Esemény nem található.', 404 );

    if ( $action === 'approve' ) {
        $wpdb->update( "{$p}ic_sprint_events",
            [ 'is_pending_review' => 0, 'is_validated' => 1 ],
            [ 'id' => $event_id ]
        );
    } else {
        // reject: mark as not validated, not pending
        $wpdb->update( "{$p}ic_sprint_events",
            [ 'is_pending_review' => 0, 'is_validated' => 0 ],
            [ 'id' => $event_id ]
        );
    }

    return ic_json_ok( [ 'event_id' => $event_id, 'action' => $action ] );
}

/* ═══════════════════════════════════════════════════════════════════════════
   Sprint 10 — Trust Engine, Toxicity Friction, Moderation Settlement
   ═══════════════════════════════════════════════════════════════════════════ */

/* ── Toxicity keyword check ──────────────────────────────────────────────── */
function ic_check_toxicity( string $body ): bool {
    static $keywords = [
        'idióta', 'hülye', 'taknyos', 'büdös', 'rohadt', 'mocskos',
        'segg', 'picsába', 'kurva', 'barom', 'állat vagy', 'szar vagy',
        'menj el', 'utállak', 'gyűlöllek', 'elpusztulsz', 'kibaszott', 'francba',
    ];
    $lower = mb_strtolower( $body );
    foreach ( $keywords as $kw ) {
        if ( mb_strpos( $lower, $kw ) !== false ) {
            return true;
        }
    }
    return false;
}

/* ── Trust auto-promote (0→1→2) ─────────────────────────────────────────── */
function ic_trust_auto_promote( int $circle_id, string $pid_hash ): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $trust   = $wpdb->get_row( $wpdb->prepare(
        "SELECT trust_level, timeout_until FROM {$p}ic_member_trust WHERE circle_id=%d AND pid_hash=%s",
        $circle_id, $pid_hash
    ) );
    $current = $trust ? (int) $trust->trust_level : 0;

    // Skip promotion while timed-out
    if ( $trust && $trust->timeout_until && strtotime( $trust->timeout_until ) > time() ) {
        return;
    }

    if ( $current === 0 ) {
        // 0→1 threshold: 2+ posts OR 3+ votes received
        $post_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
            $circle_id, $pid_hash
        ) );
        $votes_recv = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(vote_count),0) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
            $circle_id, $pid_hash
        ) );
        if ( $post_count >= 2 || $votes_recv >= 3 ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$p}ic_member_trust (circle_id,pid_hash,trust_level) VALUES(%d,%s,1)
                 ON DUPLICATE KEY UPDATE trust_level=GREATEST(trust_level,1)",
                $circle_id, $pid_hash
            ) );
        }
    } elseif ( $current === 1 ) {
        // 1→2 threshold: 10+ posts AND 50+ votes received
        $posts = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
            $circle_id, $pid_hash
        ) );
        $votes = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(vote_count),0) FROM {$p}ic_posts WHERE circle_id=%d AND author_hash=%s AND is_deleted=0",
            $circle_id, $pid_hash
        ) );
        if ( $posts >= 10 && $votes >= 50 ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}ic_member_trust SET trust_level=2 WHERE circle_id=%d AND pid_hash=%s AND trust_level=1",
                $circle_id, $pid_hash
            ) );
        }
    }
}

/* ── Toxicity Impi rephrase suggestion ───────────────────────────────────── */
add_action( 'ic_toxicity_friction', 'ic_impi_rephrase_suggestion', 10, 3 );
function ic_impi_rephrase_suggestion( int $circle_id, string $pid_hash, string $body ): void {
    ic_impi_post(
        $circle_id,
        '🦡 Érzem, hogy erős érzelmek vannak mögötte. Ha átfogalmazod, nagyobb eséllyel segít a közösség! 💛',
        null,
        'safety'
    );
}

/* ── Admin settlement triage queue (GET) ─────────────────────────────────── */
function ic_rest_admin_settlement_queue(): WP_REST_Response {
    global $wpdb;
    $p = $wpdb->prefix;

    $rows = $wpdb->get_results(
        "SELECT p.id AS post_id,
                LEFT(p.body, 120)  AS excerpt,
                p.circle_id,
                COUNT(r.id)        AS report_count,
                c.type,
                c.ref_slug,
                c.name             AS circle_name,
                MIN(r.created_at)  AS first_report_at
         FROM {$p}ic_reports r
         JOIN {$p}ic_posts   p ON r.post_id  = p.id
         JOIN {$p}ic_circles c ON c.id        = p.circle_id
         WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
           AND r.status      = 'pending'
           AND p.is_deleted  = 0
         GROUP BY p.id
         HAVING report_count >= 3
         ORDER BY report_count DESC
         LIMIT 100",
        ARRAY_A
    );

    return new WP_REST_Response(
        [ 'queue' => $rows ?: [], 'count' => count( $rows ?: [] ) ],
        200
    );
}

/* ── Admin settlement triage action (POST /{report_id}) ─────────────────── */
function ic_rest_admin_settlement_triage( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $report_id = (int) $req->get_param( 'report_id' );
    $action    = sanitize_key( $req->get_param( 'action' ) ?? '' );

    if ( ! in_array( $action, [ 'dismiss', 'remove', 'timeout' ], true ) ) {
        return ic_json_error( 'action értéke: dismiss | remove | timeout', 400 );
    }

    $report = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ic_reports WHERE id=%d",
        $report_id
    ) );
    if ( ! $report ) {
        return ic_json_error( 'Report nem található.', 404 );
    }

    $now = current_time( 'mysql' );

    if ( $action === 'dismiss' ) {
        $wpdb->update(
            "{$p}ic_reports",
            [ 'status' => 'dismissed', 'reviewed_at' => $now ],
            [ 'id' => $report_id ]
        );
    } elseif ( $action === 'remove' ) {
        $wpdb->update( "{$p}ic_posts",   [ 'is_deleted' => 1 ],                             [ 'id' => $report->post_id ] );
        $wpdb->update( "{$p}ic_reports", [ 'status' => 'actioned', 'reviewed_at' => $now ], [ 'post_id' => $report->post_id ] );
    } elseif ( $action === 'timeout' ) {
        $until = date( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
        $post  = $wpdb->get_row( $wpdb->prepare(
            "SELECT author_hash, circle_id FROM {$p}ic_posts WHERE id=%d",
            $report->post_id
        ) );
        if ( $post ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$p}ic_member_trust (circle_id,pid_hash,timeout_until) VALUES(%d,%s,%s)
                 ON DUPLICATE KEY UPDATE timeout_until=%s",
                $post->circle_id, $post->author_hash, $until, $until
            ) );
        }
        $wpdb->update( "{$p}ic_reports", [ 'status' => 'actioned', 'reviewed_at' => $now ], [ 'id' => $report_id ] );
    }

    return ic_json_ok( [ 'action' => $action, 'report_id' => $report_id ] );
}

/* ── NGO constitution accept (POST) ─────────────────────────────────────── */
function ic_rest_ngo_constitution_accept( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if ( ! $pid_hash ) {
        return ic_json_error( 'Azonosítás szükséges.', 401 );
    }

    $cid     = (int) sanitize_text_field( $req->get_param( 'circle_id' ) ?? 0 );
    $version = sanitize_text_field( $req->get_param( 'version' ) ?? '1.0' );

    if ( ! $cid ) {
        return ic_json_error( 'circle_id kötelező.', 400 );
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$p}ic_member_trust (circle_id,pid_hash,constitution_ver,accepted_at)
         VALUES(%d,%s,%s,NOW())
         ON DUPLICATE KEY UPDATE constitution_ver=%s, accepted_at=NOW()",
        $cid, $pid_hash, $version, $version
    ) );

    return ic_json_ok( [ 'accepted' => true, 'version' => $version ] );
}

/* ── Monthly Circle Health Score refresh ────────────────────────────────── */
add_action( 'ic_monthly_health_refresh', 'ic_monthly_health_refresh_handler' );
function ic_monthly_health_refresh_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_ids = $wpdb->get_col( "SELECT id FROM {$p}ic_circles WHERE is_active=1" );
    foreach ( $circle_ids as $cid ) {
        $score = ic_circle_health_score( (int) $cid );
        $wpdb->update( "{$p}ic_circles", [ 'health_score' => $score ], [ 'id' => (int) $cid ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   Sprint 11 — Micro-missziók, Decision Post vote, Impact Buddy opt-out,
               Buddy retention soft-delete
   ═══════════════════════════════════════════════════════════════════════════ */

/* ── GET /circles/{circle_id}/missions — aktív misszió lista ────────────── */
function ic_rest_missions_list( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $p   = $wpdb->prefix;
    $cid = (int) $req->get_param( 'circle_id' );

    $now = current_time( 'mysql' );

    // System-level (circle_id IS NULL) + circle-specific missions
    $missions = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, description, reward_pts, reward_votes,
                valid_from, valid_until, circle_id
         FROM {$p}ic_missions
         WHERE is_active = 1
           AND (circle_id IS NULL OR circle_id = %d)
           AND (valid_from IS NULL OR valid_from <= %s)
           AND (valid_until IS NULL OR valid_until >= %s)
         ORDER BY circle_id DESC, id ASC",
        $cid, $now, $now
    ), ARRAY_A );

    // Attach completion flag for logged-in user
    $pid_hash = ic_pid_hash() ?? '';
    if ( $pid_hash && $missions ) {
        $ids = array_column( $missions, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $completed_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT mission_id FROM {$p}ic_mission_completions
             WHERE pid_hash = %s AND circle_id = %d AND mission_id IN ($placeholders)",
            array_merge( [ $pid_hash, $cid ], $ids )
        ) );
        $completed_set = array_flip( $completed_ids );
        foreach ( $missions as &$m ) {
            $m['completed'] = isset( $completed_set[ $m['id'] ] );
        }
        unset( $m );
    }

    return new WP_REST_Response( [ 'missions' => $missions ?: [] ], 200 );
}

/* ── POST /circles/{circle_id}/missions/{mission_id}/complete ───────────── */
function ic_rest_mission_complete( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if ( ! $pid_hash ) {
        return ic_json_error( 'Azonosítás szükséges.', 401 );
    }

    global $wpdb;
    $p          = $wpdb->prefix;
    $cid        = (int) $req->get_param( 'circle_id' );
    $mission_id = (int) $req->get_param( 'mission_id' );

    // Validate mission exists and is active
    $mission = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ic_missions WHERE id = %d AND is_active = 1",
        $mission_id
    ) );
    if ( ! $mission ) {
        return ic_json_error( 'Misszió nem található vagy már lezárult.', 404 );
    }

    // Check circle membership
    $is_member = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id = %d AND pid_hash = %s AND is_active = 1",
        $cid, $pid_hash
    ) );
    if ( ! $is_member ) {
        return ic_json_error( 'Nem vagy tagja ennek a körnek.', 403 );
    }

    // UNIQUE dedupe: one completion per user per mission (across circles)
    $inserted = $wpdb->insert(
        "{$p}ic_mission_completions",
        [
            'mission_id' => $mission_id,
            'pid_hash'   => $pid_hash,
            'circle_id'  => $cid,
            'created_at' => current_time( 'mysql' ),
        ]
    );

    if ( $inserted === false ) {
        // Duplicate key — already completed
        return ic_json_error( 'Ezt a missziót már teljesítetted! 🎉', 409 );
    }

    // Award points + votes
    $pts   = (int) $mission->reward_pts;
    $votes = (int) $mission->reward_votes;

    if ( $pts > 0 ) {
        ic_award_points(
            $pid_hash, $pts, 'mission_complete',
            "mission:{$mission_id}",
            "mission_complete:{$pid_hash}:{$mission_id}"
        );
    }
    if ( $votes > 0 ) {
        $ngo_slug = $wpdb->get_var( $wpdb->prepare(
            "SELECT ref_slug FROM {$p}ic_circles WHERE id = %d AND type = 'ngo'", $cid
        ) );
        if ( $ngo_slug ) {
            ic_award_ngo_votes( $ngo_slug, $votes );
        }
    }

    // Sprint activation
    $circle_obj = $wpdb->get_row( $wpdb->prepare(
        "SELECT ref_slug FROM {$p}ic_circles WHERE id = %d", $cid
    ) );
    if ( $circle_obj ) {
        ic_record_sprint_activation( $pid_hash, $circle_obj->ref_slug, 'mission' );
    }

    return ic_json_ok( [
        'mission_id'   => $mission_id,
        'reward_pts'   => $pts,
        'reward_votes' => $votes,
    ], 201 );
}

/* ── POST /circles/{circle_id}/buddy/opt-out ────────────────────────────── */
function ic_rest_buddy_optout( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if ( ! $pid_hash ) {
        return ic_json_error( 'Azonosítás szükséges.', 401 );
    }

    global $wpdb;
    $p   = $wpdb->prefix;
    $cid = (int) $req->get_param( 'circle_id' );
    $now = current_time( 'mysql' );

    // Find active buddy pair (pid_a or pid_b)
    $buddy = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$p}ic_buddies
         WHERE circle_id = %d AND opt_out_at IS NULL
           AND (pid_a = %s OR pid_b = %s)
         LIMIT 1",
        $cid, $pid_hash, $pid_hash
    ) );

    if ( ! $buddy ) {
        return ic_json_error( 'Nincs aktív Buddy párosításod ebben a körben.', 404 );
    }

    $wpdb->update(
        "{$p}ic_buddies",
        [ 'opt_out_at' => $now ],
        [ 'id' => $buddy->id ]
    );

    return ic_json_ok( [ 'opted_out' => true ] );
}

/* ── POST /circles/{cid}/posts/{post_id}/decision-vote ──────────────────── */
function ic_rest_decision_vote( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $pid_hash = ic_pid_hash();
    if ( ! $pid_hash ) {
        return ic_json_error( 'Azonosítás szükséges.', 401 );
    }

    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param( 'circle_id' );
    $post_id = (int) $req->get_param( 'post_id' );

    // Load decision post
    $decision = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ic_posts WHERE id = %d AND circle_id = %d AND post_type = 'decision' AND is_deleted = 0",
        $post_id, $cid
    ) );
    if ( ! $decision ) {
        return ic_json_error( 'Decision Poszt nem található.', 404 );
    }

    // Validate options
    $meta        = json_decode( $decision->meta_json ?? '{}', true );
    $valid_ids   = array_column( $meta['options'] ?? [], 'id' );
    $option_ids  = array_filter( (array) ( $req->get_param( 'option_ids' ) ?? [] ), 'is_numeric' );
    $option_ids  = array_map( 'intval', array_values( $option_ids ) );

    if ( empty( $option_ids ) ) {
        return ic_json_error( 'option_ids kötelező (tömbként).', 400 );
    }
    foreach ( $option_ids as $oid ) {
        if ( ! in_array( $oid, $valid_ids, true ) ) {
            return ic_json_error( "Ismeretlen opció ID: {$oid}", 400 );
        }
    }

    // Closed check
    if ( ! empty( $meta['closed_at'] ) && strtotime( $meta['closed_at'] ) < time() ) {
        return ic_json_error( 'Ez a szavazás már lezárult.', 409 );
    }

    // Dedupe: 1 vote per user per decision post
    $dedupe_key = "decision_vote:{$pid_hash}:{$post_id}";
    $already    = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_posts
         WHERE author_hash = %s AND circle_id = %d AND post_type = 'text'
           AND JSON_EXTRACT(meta_json,'$.decision_vote.post_id') = %d",
        $pid_hash, $cid, $post_id
    ) );
    if ( $already > 0 ) {
        return ic_json_error( 'Már szavaztál erre a döntési posztra.', 409 );
    }

    // Store as a text post with decision_vote meta
    $vote_meta = wp_json_encode( [ 'decision_vote' => [ 'post_id' => $post_id, 'option_ids' => $option_ids ] ] );
    $wpdb->insert( "{$p}ic_posts", [
        'circle_id'   => $cid,
        'author_hash' => $pid_hash,
        'author_type' => 'user',
        'post_type'   => 'text',
        'body'        => '', // votes are silent
        'meta_json'   => $vote_meta,
        'created_at'  => current_time( 'mysql' ),
    ] );

    // +8 points for the voter (1×/döntés dedupe via ic_award_points)
    ic_award_points( $pid_hash, 8, 'decision_vote', "post:{$post_id}", $dedupe_key );

    // Sprint activation
    $circle_obj = $wpdb->get_row( $wpdb->prepare( "SELECT ref_slug FROM {$p}ic_circles WHERE id = %d", $cid ) );
    if ( $circle_obj ) {
        ic_record_sprint_activation( $pid_hash, $circle_obj->ref_slug, 'vote' );
    }

    return ic_json_ok( [ 'voted' => true, 'option_ids' => $option_ids ], 201 );
}

/* ── GET /circles/{cid}/posts/{post_id}/decision-results ────────────────── */
function ic_rest_decision_results( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $p       = $wpdb->prefix;
    $cid     = (int) $req->get_param( 'circle_id' );
    $post_id = (int) $req->get_param( 'post_id' );

    $decision = $wpdb->get_row( $wpdb->prepare(
        "SELECT meta_json FROM {$p}ic_posts WHERE id = %d AND circle_id = %d AND post_type = 'decision' AND is_deleted = 0",
        $post_id, $cid
    ) );
    if ( ! $decision ) {
        return ic_json_error( 'Decision Poszt nem található.', 404 );
    }

    $meta    = json_decode( $decision->meta_json ?? '{}', true );
    $options = $meta['options'] ?? [];

    // Tally votes from decision_vote text posts
    $votes_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_json FROM {$p}ic_posts
         WHERE circle_id = %d AND post_type = 'text' AND is_deleted = 0
           AND JSON_EXTRACT(meta_json,'$.decision_vote.post_id') = %d",
        $cid, $post_id
    ) );

    $tally = [];
    $total = 0;
    foreach ( $votes_rows as $row ) {
        $vm = json_decode( $row->meta_json ?? '{}', true );
        foreach ( $vm['decision_vote']['option_ids'] ?? [] as $oid ) {
            $tally[ $oid ] = ( $tally[ $oid ] ?? 0 ) + 1;
        }
        $total++;
    }

    $result = [];
    foreach ( $options as $opt ) {
        $count          = $tally[ $opt['id'] ] ?? 0;
        $result[]       = [
            'id'      => $opt['id'],
            'label'   => $opt['label'],
            'count'   => $count,
            'percent' => $total > 0 ? round( $count / $total * 100 ) : 0,
        ];
    }

    return new WP_REST_Response( [
        'post_id'       => $post_id,
        'total_votes'   => $total,
        'options'       => $result,
        'closed_at'     => $meta['closed_at'] ?? null,
    ], 200 );
}

/* ── Sprint 12: POST /circles/{circle_id}/buddy/complete ──────────────────── */
function ic_rest_buddy_complete( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    global $wpdb;
    $p = $wpdb->prefix;

    $circle_id = (int) $req->get_param( 'circle_id' );
    $pid_hash  = ic_pid_hash();
    if ( ! $pid_hash ) {
        return ic_json_error( 'Azonosítás szükséges.', 401 );
    }

    // Find the active buddy pair for this user in this circle
    $buddy = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ic_buddies
         WHERE circle_id=%d
           AND (pid_a=%s OR pid_b=%s)
           AND completed_at IS NULL
           AND opt_out_at IS NULL
         LIMIT 1",
        $circle_id, $pid_hash, $pid_hash
    ), ARRAY_A );

    if ( ! $buddy ) {
        return ic_json_error( 'Nincs aktív buddy pár ebben a körben.', 404 );
    }

    // Enforce minimum 7 days since pairing (7-day mini-onboarding)
    $paired_at = strtotime( $buddy['created_at'] );
    if ( time() - $paired_at < 7 * DAY_IN_SECONDS ) {
        $days_left = (int) ceil( ( 7 * DAY_IN_SECONDS - ( time() - $paired_at ) ) / DAY_IN_SECONDS );
        return ic_json_error( "A 7 napos mini-onboarding még nem zárult le ({$days_left} nap van hátra).", 422 );
    }

    if ( (int) $buddy['bonus_paid'] === 1 ) {
        return ic_json_error( 'A befejezési bónusz már ki lett fizetve.', 409 );
    }

    // Mark completed + bonus_paid
    $wpdb->update(
        "{$p}ic_buddies",
        [ 'completed_at' => current_time( 'mysql' ), 'bonus_paid' => 1 ],
        [ 'id' => (int) $buddy['id'] ]
    );

    // Award +30 pts to both members (dedupe key ensures idempotency)
    $bid = (int) $buddy['id'];
    foreach ( [ $buddy['pid_a'], $buddy['pid_b'] ] as $recipient ) {
        if ( ! empty( $recipient ) ) {
            ic_award_points(
                $recipient, 30, 'buddy_completion',
                "buddy:{$bid}",
                "buddy_bonus:{$bid}:{$recipient}"
            );
            do_action( 'ic_buddy_completed', $circle_id, $recipient );
        }
    }

    return ic_json_ok( [ 'buddy_id' => $bid, 'bonus_paid' => true ] );
}

/* ── Sprint 12: Tombola ritual cron ──────────────────────────────────────── */
add_action( 'ic_tombola_ritual_cron', 'ic_tombola_ritual_cron_handler' );
function ic_tombola_ritual_cron_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // Find active tombolas with ritual enabled, ending within 24h, not yet posted
    $due = $wpdb->get_results(
        "SELECT * FROM {$p}ic_tombolas
         WHERE status='active'
           AND ritual_enabled=1
           AND ritual_posted=0
           AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)",
        ARRAY_A
    );

    foreach ( $due as $tombola ) {
        $tid = (int) $tombola['id'];
        ic_impi_post(
            (int) $tombola['circle_id'],
            "🤔 Holnap zárul a tombola! Miért fontos ez az ügy a kör számára? Gondold át, amíg még van idő — és vegyél jegyet, ha még nem tetted! 🍀 **{$tombola['title']}**",
            4
        );
        $wpdb->update( "{$p}ic_tombolas", [ 'ritual_posted' => 1 ], [ 'id' => $tid ] );
    }
}

/* ── Buddy 90-day soft-delete retention cron ────────────────────────────── */
add_action( 'ic_buddy_retention_daily', 'ic_buddy_retention_handler' );

/* =========================================================================
   § Sprint 15 — §21 Data Retention Policy weekly cron
   Runs every Sunday 02:00.
   - ic_sprint_events:      anonymize pid_hash → NULL after 6 months (fraud_flag=1 kept 2y)
   - ic_reports:            anonymize pid_hash → NULL after 2 years post-close
   - ic_mission_completions: hard-delete after 1 year
   - ic_circle_leaderboard: hard-delete snapshots older than 6 months
   ========================================================================= */
add_action( 'ic_data_retention_weekly', 'ic_data_retention_handler' );
function ic_data_retention_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // 1. Sprint events: anonymize pid_hash after 6 months
    //    Exception: fraud_flag=1 rows are kept for 2 years (§22.3 / §21)
    $six_months  = date( 'Y-m-d H:i:s', strtotime( '-6 months' ) );
    $two_years   = date( 'Y-m-d H:i:s', strtotime( '-2 years' ) );
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}ic_sprint_events
         SET pid_hash = 'anon'
         WHERE pid_hash != 'anon'
           AND created_at <= %s
           AND (fraud_flag = 0 OR (fraud_flag = 1 AND created_at <= %s))",
        $six_months, $two_years
    ) );

    // 2. Reports: anonymize reporter_hash 2 years after being reviewed
    //    platform_admin-actioned rows stay permanent (preserved by actor_type)
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}ic_reports
         SET reporter_hash = 'anon'
         WHERE reporter_hash != 'anon'
           AND status IN ('actioned','dismissed')
           AND reviewed_at <= %s",
        $two_years
    ) );

    // 3. Mission completions: hard-delete after 1 year
    $one_year = date( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}ic_mission_completions WHERE created_at <= %s",
        $one_year
    ) );

    // 4. Circle leaderboard snapshots: hard-delete older than 6 months
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}ic_circle_leaderboard WHERE updated_at <= %s",
        $six_months
    ) );
}
function ic_buddy_retention_handler(): void {
    global $wpdb;
    $p = $wpdb->prefix;

    // Soft-delete: NULL out pid_a/pid_b where completed_at or opt_out_at is 90+ days ago
    $cutoff = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}ic_buddies
         SET pid_a = NULL, pid_b = NULL
         WHERE (pid_a IS NOT NULL OR pid_b IS NOT NULL)
           AND (
               (completed_at IS NOT NULL AND completed_at <= %s)
               OR (opt_out_at IS NOT NULL AND opt_out_at <= %s)
           )",
        $cutoff, $cutoff
    ) );
}

// ============================================================
// § Sprint 13 — Appeal review queues + Impi moderation notify
// ============================================================

/** GET /admin/appeals — platform admin lists pending appeals */
function ic_rest_admin_appeal_queue(): WP_REST_Response {
    global $wpdb;
    $p    = $wpdb->prefix;
    $rows = $wpdb->get_results(
        "SELECT a.id, a.modaction_id, a.appellant_hash, a.appeal_reason,
                a.status, a.created_at,
                m.action AS action_type, m.circle_id, m.post_id, m.target_hash,
                m.reason AS mod_reason, m.created_at AS actioned_at
         FROM {$p}ic_appeals a
         JOIN {$p}ic_moderation_actions m ON m.id = a.modaction_id
         WHERE a.status = 'pending'
         ORDER BY a.created_at ASC
         LIMIT 100",
        ARRAY_A
    );
    return ic_json_ok(['appeals' => $rows ?: []]);
}

/** POST /admin/appeals/{appeal_id} — approve or uphold an appeal */
function ic_rest_admin_appeal_action(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $appeal_id   = (int) $req->get_param('appeal_id');
    $action      = sanitize_key($req->get_param('action') ?? '');
    $review_note = sanitize_textarea_field($req->get_param('review_note') ?? '');

    if (!in_array($action, ['approve', 'uphold'], true)) {
        return ic_json_error('Érvénytelen akció. Használd: approve vagy uphold.', 422);
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $appeal = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_appeals WHERE id=%d", $appeal_id
    ), ARRAY_A);
    if (!$appeal) return ic_json_error('Fellebbezés nem található.', 404);
    if ($appeal['status'] !== 'pending') return ic_json_error('A fellebbezés már el lett bírálva.', 409);

    $new_status = ($action === 'approve') ? 'approved' : 'upheld';
    $wpdb->update("{$p}ic_appeals", [
        'status'      => $new_status,
        'reviewed_by' => 'admin',
        'reviewed_at' => current_time('mysql'),
    ], ['id' => $appeal_id]);

    $mod = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}ic_moderation_actions WHERE id=%d", (int) $appeal['modaction_id']
    ), ARRAY_A);
    $circle_id = (int) ($mod['circle_id'] ?? 0);

    ic_log_moderation_action($circle_id, null, null, 'admin', 'admin',
        $action === 'approve' ? 'approve_appeal' : 'uphold_appeal', $review_note);

    if ($action === 'approve') {
        ic_appeal_reverse($appeal_id, $circle_id);
    } else {
        // Upheld: notify appellant via Impi
        $appellant = $appeal['appellant_hash'];
        if ($circle_id && $appellant) {
            ic_impi_post_private($circle_id, $appellant,
                "ℹ️ Fellebbezésedet megvizsgáltuk, és az eredeti döntést fenntartjuk. " .
                "Ha további kérdésed van, fordulj a körvezetőhöz."
            );
        }
    }

    return ic_json_ok(['done' => true, 'status' => $new_status]);
}

/** GET /ngo/appeals — NGO admin lists pending appeals in their circles */
function ic_rest_ngo_appeal_queue(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard($req);
    if (is_wp_error($ngo_slug)) return $ngo_slug;

    global $wpdb;
    $p = $wpdb->prefix;

    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug=%s AND type='ngo'", $ngo_slug
    ));
    if (!$circle) return ic_json_error('Kör nem található.', 404);
    $circle_id = (int) $circle->id;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.modaction_id, a.appellant_hash, a.appeal_reason,
                a.status, a.created_at,
                m.action AS action_type, m.post_id, m.target_hash,
                m.reason AS mod_reason, m.created_at AS actioned_at
         FROM {$p}ic_appeals a
         JOIN {$p}ic_moderation_actions m ON m.id = a.modaction_id
         WHERE a.status = 'pending' AND m.circle_id = %d
         ORDER BY a.created_at ASC
         LIMIT 50",
        $circle_id
    ), ARRAY_A);

    return ic_json_ok(['circle_id' => $circle_id, 'appeals' => $rows ?: []]);
}

/** POST /ngo/appeals/{appeal_id} — NGO admin approve or uphold an appeal */
function ic_rest_ngo_appeal_action(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard($req);
    if (is_wp_error($ngo_slug)) return $ngo_slug;

    global $wpdb;
    $p = $wpdb->prefix;

    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug=%s AND type='ngo'", $ngo_slug
    ));
    if (!$circle) return ic_json_error('Kör nem található.', 404);
    $ngo_circle_id = (int) $circle->id;

    $appeal_id   = (int) $req->get_param('appeal_id');
    $action      = sanitize_key($req->get_param('action') ?? '');
    $review_note = sanitize_textarea_field($req->get_param('review_note') ?? '');

    if (!in_array($action, ['approve', 'uphold'], true)) {
        return ic_json_error('Érvénytelen akció. Használd: approve vagy uphold.', 422);
    }

    $appeal = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, m.circle_id AS mod_circle_id
         FROM {$p}ic_appeals a
         JOIN {$p}ic_moderation_actions m ON m.id = a.modaction_id
         WHERE a.id=%d", $appeal_id
    ), ARRAY_A);
    if (!$appeal) return ic_json_error('Fellebbezés nem található.', 404);
    if ((int) $appeal['mod_circle_id'] !== $ngo_circle_id) {
        return ic_json_error('Nincs jogosultságod ehhez a fellebbezéshez.', 403);
    }
    if ($appeal['status'] !== 'pending') return ic_json_error('A fellebbezés már el lett bírálva.', 409);

    $new_status = ($action === 'approve') ? 'approved' : 'upheld';
    $wpdb->update("{$p}ic_appeals", [
        'status'      => $new_status,
        'reviewed_by' => 'ngo:' . $ngo_slug,
        'reviewed_at' => current_time('mysql'),
    ], ['id' => $appeal_id]);

    ic_log_moderation_action($ngo_circle_id, null, null, 'ngo:' . $ngo_slug, 'ngo_admin',
        $action === 'approve' ? 'approve_appeal' : 'uphold_appeal', $review_note);

    if ($action === 'approve') {
        ic_appeal_reverse($appeal_id, $ngo_circle_id);
    } else {
        $appellant = $appeal['appellant_hash'];
        if ($appellant) {
            ic_impi_post_private($ngo_circle_id, $appellant,
                "ℹ️ Fellebbezésedet megvizsgáltuk, és az eredeti döntést fenntartjuk. " .
                "Ha további kérdésed van, fordulj a körvezetőhöz."
            );
        }
    }

    return ic_json_ok(['done' => true, 'status' => $new_status]);
}

/**
 * § Sprint 13 — Appeal reversal: un-delete post or clear timeout
 * Called when an appeal is approved.
 */
function ic_appeal_reverse(int $appeal_id, int $circle_id): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $appeal = $wpdb->get_row($wpdb->prepare(
        "SELECT a.appellant_hash, m.action AS action_type, m.post_id, m.target_hash
         FROM {$p}ic_appeals a
         JOIN {$p}ic_moderation_actions m ON m.id = a.modaction_id
         WHERE a.id=%d", $appeal_id
    ), ARRAY_A);
    if (!$appeal) return;

    if ($appeal['action_type'] === 'remove_post' && $appeal['post_id']) {
        $wpdb->update("{$p}ic_posts", ['is_deleted' => 0], ['id' => (int) $appeal['post_id']]);
    } elseif ($appeal['action_type'] === 'timeout' && $appeal['target_hash']) {
        $wpdb->update("{$p}ic_member_trust",
            ['timeout_until' => null],
            ['circle_id' => $circle_id, 'pid_hash' => $appeal['target_hash']]
        );
    }

    // Notify appellant: approved
    $appellant = $appeal['appellant_hash'];
    if ($circle_id && $appellant) {
        ic_impi_post_private($circle_id, $appellant,
            "✅ Jó hír! Fellebbezésedet elfogadtuk, és az eredeti döntést visszavontuk. " .
            "Köszönjük, hogy jeleztél — közösségünk jobbá válik tőled."
        );
    }
}

/**
 * § Sprint 13 — Impi private notification on moderation decisions (to the affected user)
 */
function ic_impi_notify_moderation(int $circle_id, string $target_hash, string $action_type): void {
    $messages = [
        'remove_post'    => "ℹ️ Egy posztod eltávolításra került a körünkben a közösségi szabályok alapján. " .
                            "Ha úgy gondolod, ez téves döntés volt, kérhetsz felülvizsgálatot.",
        'timeout_member' => "⏸️ Fiókod 7 napra korlátozásra került a körünkben a közösségi szabályok alapján. " .
                            "Ha úgy gondolod, ez téves döntés volt, kérhetsz felülvizsgálatot.",
    ];
    $msg = $messages[$action_type] ?? null;
    if ($msg) {
        ic_impi_post_private($circle_id, $target_hash, $msg);
    }
}

/**
 * § Sprint 13 — Impi directed/private post (only visible to recipient_hash)
 * Stored in ic_posts with meta_json: {"private":true,"recipient_hash":"..."}
 */
function ic_impi_post_private(int $circle_id, string $recipient_hash, string $body): void {
    global $wpdb;
    $p = $wpdb->prefix;

    $wpdb->insert("{$p}ic_posts", [
        'circle_id'   => $circle_id,
        'author_hash' => 'impi',
        'author_type' => 'impi',
        'post_type'   => 'text',
        'body'        => $body,
        'meta_json'   => wp_json_encode(['private' => true, 'recipient_hash' => $recipient_hash]),
        'created_at'  => current_time('mysql'),
    ]);
}

// ============================================================
// § Sprint 14 — NGO moderation panel
// ============================================================

/** GET /ngo/moderation/reports — NGO admin lists pending reports in their circle */
function ic_rest_ngo_moderation_reports(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard($req);
    if (is_wp_error($ngo_slug)) return $ngo_slug;

    global $wpdb;
    $p = $wpdb->prefix;

    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug=%s AND type='ngo'", $ngo_slug
    ));
    if (!$circle) return ic_json_error('Kör nem található.', 404);
    $circle_id = (int) $circle->id;

    $status_filter = sanitize_key($req->get_param('status') ?? 'pending');
    $allowed_statuses = ['pending', 'actioned', 'dismissed'];
    if (!in_array($status_filter, $allowed_statuses, true)) {
        $status_filter = 'pending';
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.post_id, r.reporter_hash, r.reason, r.status,
                r.created_at, r.reviewed_at,
                p.body AS post_body, p.author_hash AS post_author,
                p.post_type
         FROM {$p}ic_reports r
         LEFT JOIN {$p}ic_posts p ON p.id = r.post_id
         WHERE r.circle_id = %d AND r.status = %s
         ORDER BY r.created_at ASC
         LIMIT 50",
        $circle_id, $status_filter
    ), ARRAY_A);

    return ic_json_ok(['circle_id' => $circle_id, 'status' => $status_filter, 'reports' => $rows ?: []]);
}

/** POST /ngo/moderation/action — NGO admin removes post / warns member / dismisses report */
function ic_rest_ngo_moderation_action(WP_REST_Request $req): WP_REST_Response|WP_Error {
    $ngo_slug = ic_ngo_guard($req);
    if (is_wp_error($ngo_slug)) return $ngo_slug;

    global $wpdb;
    $p = $wpdb->prefix;

    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$p}ic_circles WHERE ref_slug=%s AND type='ngo'", $ngo_slug
    ));
    if (!$circle) return ic_json_error('Kör nem található.', 404);
    $circle_id = (int) $circle->id;

    $action      = sanitize_key($req->get_param('action') ?? '');
    $report_id   = (int) ($req->get_param('report_id') ?? 0);
    $post_id     = (int) ($req->get_param('post_id') ?? 0);
    $target_hash = sanitize_key($req->get_param('target_hash') ?? '');
    $reason      = sanitize_textarea_field($req->get_param('reason') ?? '');

    $allowed = ['remove_post', 'warn_member', 'dismiss_report'];
    if (!in_array($action, $allowed, true)) {
        return ic_json_error('Érvénytelen akció. Érvényes: remove_post, warn_member, dismiss_report.', 422);
    }

    switch ($action) {
        case 'remove_post':
            if (!$post_id) return ic_json_error('post_id szükséges.', 422);
            // Verify post belongs to this NGO's circle
            $post = $wpdb->get_row($wpdb->prepare(
                "SELECT id, author_hash FROM {$p}ic_posts WHERE id=%d AND circle_id=%d AND is_deleted=0",
                $post_id, $circle_id
            ), ARRAY_A);
            if (!$post) return ic_json_error('Poszt nem található ebben a körben.', 404);
            $wpdb->update("{$p}ic_posts", ['is_deleted' => 1], ['id' => $post_id]);
            $wpdb->update("{$p}ic_reports", [
                'status'      => 'actioned',
                'reviewed_at' => current_time('mysql'),
            ], ['post_id' => $post_id, 'status' => 'pending']);
            ic_log_moderation_action($circle_id, $post_id, $post['author_hash'], 'ngo:' . $ngo_slug, 'ngo_admin', 'remove_post', $reason);
            // Impi notify the author
            if ($post['author_hash']) {
                ic_impi_notify_moderation($circle_id, $post['author_hash'], 'remove_post');
            }
            break;

        case 'warn_member':
            if (!$target_hash) return ic_json_error('target_hash szükséges.', 422);
            // Add a strike (no timeout — NGO admin can warn, not timeout)
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$p}ic_member_trust (circle_id, pid_hash, strikes)
                 VALUES (%d, %s, 1)
                 ON DUPLICATE KEY UPDATE strikes = LEAST(strikes + 1, 10)",
                $circle_id, $target_hash
            ));
            ic_log_moderation_action($circle_id, null, $target_hash, 'ngo:' . $ngo_slug, 'ngo_admin', 'warn', $reason);
            // Impi private nudge to member
            ic_impi_post_private($circle_id, $target_hash,
                "⚠️ A körünk adminisztrátora emlékeztetett a közösségi szabályokra. " .
                "Kérjük, ügyelj a körünk kultúrájának megőrzésére."
            );
            // Mark report actioned if report_id given
            if ($report_id) {
                $wpdb->update("{$p}ic_reports", [
                    'status'      => 'actioned',
                    'reviewed_at' => current_time('mysql'),
                ], ['id' => $report_id, 'circle_id' => $circle_id]);
            }
            break;

        case 'dismiss_report':
            if (!$report_id) return ic_json_error('report_id szükséges.', 422);
            $updated = $wpdb->update("{$p}ic_reports", [
                'status'      => 'dismissed',
                'reviewed_at' => current_time('mysql'),
            ], ['id' => $report_id, 'circle_id' => $circle_id, 'status' => 'pending']);
            if (!$updated) return ic_json_error('Bejelentés nem található vagy már el lett bírálva.', 404);
            ic_log_moderation_action($circle_id, $post_id ?: null, $target_hash ?: null, 'ngo:' . $ngo_slug, 'ngo_admin', 'dismiss_report', $reason);
            break;
    }

    return ic_json_ok(['done' => true, 'action' => $action]);
}
