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

define('IC_DB_VERSION', '1.2.1');
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
    foreach (['/wp-json/impact/v1/circles', '/wp-json/impact/v1/feed/mine', '/wp-json/impact/v1/auth/status'] as $fragment) {
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
        alias_string VARCHAR(40),
        auto_joined TINYINT(1) DEFAULT 0,
        joined_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        left_at     DATETIME,
        is_active   TINYINT(1) DEFAULT 1,
        UNIQUE KEY uq_member (circle_id, pid_hash),
        KEY idx_pid (pid_hash),
        KEY idx_alias (alias_string),
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
        $attached_ngo_ids = [];
        if ($r->type === 'settlement' && !empty($r->attached_ngo_ids)) {
            $attached_ngo_ids = json_decode($r->attached_ngo_ids, true) ?: [];
        }
        $circles[] = [
            'id'                => (int) $r->id,
            'type'              => $r->type,
            'ref_slug'          => $r->ref_slug,
            'name'              => $r->name,
            'description'       => $r->description ?? '',
            'icon_url'          => $r->icon_url ?? '',
            'member_count'      => (int) $r->member_count,
            'post_count'        => (int) $r->post_count,
            'is_member'         => in_array((int) $r->id, $my_circles, true),
            'attached_ngo_ids'  => $attached_ngo_ids,
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

    $attached_ngo_ids = [];
    if ($circle->type === 'settlement' && !empty($circle->attached_ngo_ids)) {
        $attached_ngo_ids = json_decode($circle->attached_ngo_ids, true) ?: [];
    }

    return ic_json_ok([
        'circle' => [
            'id'                => (int) $circle->id,
            'type'              => $circle->type,
            'ref_slug'          => $circle->ref_slug,
            'name'              => $circle->name,
            'description'       => $circle->description ?? '',
            'icon_url'          => $circle->icon_url ?? '',
            'member_count'      => (int) $circle->member_count,
            'post_count'        => (int) $circle->post_count,
            'is_member'         => $is_member,
            'my_alias'          => $my_alias,
            'attached_ngo_ids'  => $attached_ngo_ids,
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

    $alias_string = IC_Alias::generate($pid_hash, $id);

    if ($existing) {
        // Re-join
        $result = $wpdb->update("{$p}ic_memberships", [
            'is_active' => 1,
            'alias_string' => $alias_string,
            'left_at'   => null,
            'joined_at' => current_time('mysql'),
        ], ['id' => $existing->id]);
    } else {
        $result = $wpdb->insert("{$p}ic_memberships", [
            'circle_id' => $id,
            'pid_hash'  => $pid_hash,
            'alias_string' => $alias_string,
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
    $is_member = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ic_memberships WHERE circle_id=%d AND pid_hash=%s AND is_active=1",
        $cid, $pid_hash
    ));
    if (!$is_member) {
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

    $meta = null;
    $meta_raw = $req->get_param('meta');
    if ($meta_raw && is_array($meta_raw)) {
        $meta = wp_json_encode($meta_raw);
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
    ]);
}

/* --- Helpers ------------------------------------------------------------- */

function ic_format_post($post, $circle_id, $circle_meta = null) {
    $result = [
        'id'            => (int) $post->id,
        'circle_id'     => (int) $post->circle_id,
        'author_alias'  => IC_Alias::generate($post->author_hash, $circle_id),
        'author_type'   => $post->author_type,
        'post_type'     => $post->post_type,
        'body'          => esc_html($post->body),
        'meta'          => $post->meta_json ? json_decode($post->meta_json, true) : null,
        'vote_count'    => (int) $post->vote_count,
        'helpful_votes' => (int) $post->helpful_votes,
        'is_pinned'     => (bool) $post->is_pinned,
        'impi_boost'    => (bool) $post->impi_boost,
        'is_own'        => $post->author_hash === ic_pid_hash(),
        'created_at'    => $post->created_at,
        'time_ago'      => ic_time_ago($post->created_at),
    ];

    if (is_array($circle_meta) && !empty($circle_meta)) {
        $result = array_merge($result, $circle_meta);
    }

    return $result;
}

function ic_circle_color_token($circle_id) {
    $tokens = ['lagoon', 'mint', 'cobalt', 'amber', 'coral', 'slate', 'moss', 'rose', 'indigo', 'ember'];
    $count = count($tokens);
    if ($count <= 0) {
        return 'slate';
    }
    $idx = ((int) $circle_id) % $count;
    if ($idx < 0) {
        $idx += $count;
    }
    return $tokens[$idx];
}

function ic_time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'most';
    if ($diff < 3600) return floor($diff / 60) . ' perce';
    if ($diff < 86400) return floor($diff / 3600) . ' órája';
    if ($diff < 2592000) return floor($diff / 86400) . ' napja';
    return date('Y. m. d.', strtotime($datetime));
}

function ic_award_points($pid_hash, $points, $type, $source_id, $dedupe_key) {
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

    // Direct award path can only target the current pseudo identity.
    $current_hash = ic_pid_hash($pseudo_id);
    if ($current_hash === '' || $current_hash !== $pid_hash) {
        return;
    }
    $mgr->award_points_for_pseudo($pseudo_id, $points, 'community_activity', $source_id, [
        'activity' => $type,
        'source_type' => 'impact_community',
    ], $dedupe_key);
}

function ic_try_buddy_pair($circle_id, $pid_hash) {
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

add_action('template_redirect', 'ic_app_template_redirect', 4);

function ic_hatas_korok_en_fallback_file(): ?string
{
    $lang = '';

    if (function_exists('impactshop_intl_resolve_lang')) {
        $lang = impactshop_intl_resolve_lang();
    } elseif (isset($_GET['lang'])) {
        $lang = sanitize_key((string) wp_unslash($_GET['lang']));
    }

    if ($lang !== 'en') {
        return null;
    }

    $file = __DIR__ . '/impactshop-ngo-guides/hatas-korok-en.html';

    return is_file($file) ? $file : null;
}

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

    $fallback_file = ic_hatas_korok_en_fallback_file();
    if ($fallback_file !== null) {
        global $wp_query;
        if (isset($wp_query) && method_exists($wp_query, 'is_404')) {
            $wp_query->is_404 = false;
        }
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        readfile($fallback_file);
        exit;
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
