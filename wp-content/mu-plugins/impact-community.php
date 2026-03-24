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

define('IC_DB_VERSION', '1.2.0');
define('IC_MAX_CIRCLES', 10);
define('IC_MAX_BODY_LENGTH', 600);
define('IC_POSTS_PER_PAGE', 20);
define('IC_CIRCLES_PER_PAGE', 30);
define('IC_RATE_LIMIT_POSTS_PER_HOUR', 5);

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

function ic_get_pseudo_id(): string {
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

    /* --- Auth / Nonce --------------------------------------------------- */

    register_rest_route($ns, '/auth/status', [
        'methods'  => 'GET',
        'callback' => 'ic_rest_auth_status',
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

function ic_rest_circle_join(WP_REST_Request $req): WP_REST_Response|WP_Error {
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
        $wpdb->update("{$p}ic_memberships", [
            'is_active' => 1,
            'left_at'   => null,
            'joined_at' => current_time('mysql'),
        ], ['id' => $existing->id]);
    } else {
        $wpdb->insert("{$p}ic_memberships", [
            'circle_id' => $id,
            'pid_hash'  => $pid_hash,
            'joined_at' => current_time('mysql'),
            'is_active' => 1,
        ]);
    }

    // Increment member_count
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET member_count = member_count + 1 WHERE id = %d", $id
    ));

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

function ic_rest_circle_leave(WP_REST_Request $req): WP_REST_Response|WP_Error {
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

    $wpdb->update("{$p}ic_memberships", [
        'is_active' => 0,
        'left_at'   => current_time('mysql'),
    ], ['id' => $membership->id]);

    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET member_count = GREATEST(member_count - 1, 0) WHERE id = %d", $id
    ));

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

function ic_rest_post_create(WP_REST_Request $req): WP_REST_Response|WP_Error {
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

    $wpdb->insert("{$p}ic_posts", [
        'circle_id'   => $cid,
        'author_hash' => $pid_hash,
        'author_type' => 'user',
        'post_type'   => $post_type,
        'body'        => $body,
        'meta_json'   => $meta,
        'created_at'  => current_time('mysql'),
    ]);
    $post_id = (int) $wpdb->insert_id;

    // Increment post count
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET post_count = post_count + 1 WHERE id = %d", $cid
    ));

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

    return ic_json_ok([
        'post' => ic_format_post($wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$p}ic_posts WHERE id=%d", $post_id
        )), $cid),
    ], 201);
}

function ic_rest_post_vote(WP_REST_Request $req): WP_REST_Response|WP_Error {
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
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_posts SET vote_count = vote_count + 1 WHERE id = %d", $post_id
    ));

    // If post now has 5+ votes, award the author
    $new_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT vote_count FROM {$p}ic_posts WHERE id = %d", $post_id
    ));
    if ($new_count === 5) {
        ic_award_points($post->author_hash, 50, 'post_5_votes', "post:{$post_id}", "post_5_votes:{$post->author_hash}:{$post_id}");
    }

    return ic_json_ok(['vote_count' => $new_count]);
}

function ic_rest_post_delete(WP_REST_Request $req): WP_REST_Response|WP_Error {
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

    $wpdb->update("{$p}ic_posts", ['is_deleted' => 1], ['id' => $post_id]);

    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}ic_circles SET post_count = GREATEST(post_count - 1, 0) WHERE id = %d", $cid
    ));

    return ic_json_ok(['deleted' => true]);
}

/* --- Auth handler ------------------------------------------------------- */

function ic_rest_auth_status(WP_REST_Request $req): WP_REST_Response {
    $pseudo = ic_get_pseudo_id();
    $pid_hash = ic_pid_hash($pseudo);

    return ic_json_ok([
        'authenticated' => $pseudo !== '',
        'pid_hash'      => $pid_hash ? substr($pid_hash, 0, 8) . '...' : '',
        'nonce'         => wp_create_nonce('wp_rest'),
    ]);
}

/* --- Helpers ------------------------------------------------------------- */

function ic_format_post(object $post, int $circle_id): array {
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

function ic_app_template_redirect(): void {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (!preg_match('~^/hatas-korok/?(\?.*)?$~', $uri)) {
        return;
    }

    $api_url = rest_url('impact/v1');
    $nonce   = wp_create_nonce('wp_rest');
    $pseudo  = ic_get_pseudo_id();

    header('Content-Type: text/html; charset=UTF-8');

    require __DIR__ . '/impact-community-app.php';
    exit;
}
