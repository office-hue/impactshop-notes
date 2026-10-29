<?php
/**
 * Plugin Name: Sharity Points (MU)
 * Description: Fiók pontrendszer és szint kezelés alapmodul.
 * Version: 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SHARITY_POINTS_VERSION')) {
    define('SHARITY_POINTS_VERSION', '0.1.0');
}

if (!defined('SHARITY_POINTS_SCHEMA')) {
    define('SHARITY_POINTS_SCHEMA', '2026-03-10-01');
}

define('SHARITY_POINTS_PATH', __DIR__);

require_once __DIR__ . '/sharity-points-helpers.php';
require_once __DIR__ . '/sharity-points-money.php';
require_once __DIR__ . '/sharity-points-impactshop-attribution.php';
require_once __DIR__ . '/sharity-points-manager.php';
require_once __DIR__ . '/sharity-level-manager.php';
require_once __DIR__ . '/sharity-decay-manager.php';
require_once __DIR__ . '/sharity-vacation-manager.php';
require_once __DIR__ . '/sharity-referral-manager.php';
require_once __DIR__ . '/sharity-points-api.php';
require_once __DIR__ . '/sharity-points-cron.php';
require_once __DIR__ . '/sharity-points-ledger-sync.php';
require_once __DIR__ . '/sharity-points-events.php';
require_once __DIR__ . '/sharity-points-webhooks.php';
require_once __DIR__ . '/sharity-points-discount.php';
require_once __DIR__ . '/sharity-points-admin.php';
require_once __DIR__ . '/sharity-points-notifications.php';

// Schema changes are explicit operations, never side effects of an HTTP request.
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity points migrate', function ($args, $assoc): void {
        $result = sharity_points_migrate_impactshop(isset($assoc['dry-run']));
        if (!$result['success']) {
            WP_CLI::error($result['error']);
        }
        WP_CLI::success(isset($assoc['dry-run']) ? 'Read-only schema preflight completed.' : 'Points schema verified.');
    });
}
add_action('admin_init', 'sharity_points_register_caps');

function sharity_points_register_caps(): void
{
    $role = get_role('administrator');
    if (!$role) {
        return;
    }

    if (!$role->has_cap('manage_sharity_points')) {
        $role->add_cap('manage_sharity_points');
    }
}

function sharity_points_maybe_migrate(): void
{
    $current = get_option('sharity_points_schema_version');
    if ($current === SHARITY_POINTS_SCHEMA) {
        return;
    }

    if (get_transient('sharity_points_schema_lock')) {
        return;
    }

    set_transient('sharity_points_schema_lock', 1, 60);

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $tables = [];
    $tables[] = "CREATE TABLE {$wpdb->prefix}user_points (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL UNIQUE,
        pseudo_id VARCHAR(64) NULL,
        points_total INT NOT NULL DEFAULT 0,
        points_lifetime INT NOT NULL DEFAULT 0,
        points_decayed INT NOT NULL DEFAULT 0,
        current_level ENUM('basic','bronze','silver','gold','platinum','legend') NOT NULL DEFAULT 'basic',
        level_locked_until DATETIME NULL,
        level_upgraded_at DATETIME NULL,
        freeze_until DATETIME NULL,
        freeze_count_yearly INT NOT NULL DEFAULT 0,
        last_activity_at DATETIME NULL,
        last_decay_check_at DATETIME NULL,
        streak_days INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_pseudo (pseudo_id),
        KEY idx_user_level (user_id, current_level),
        KEY idx_points_total (points_total),
        KEY idx_last_activity (last_activity_at)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}point_transactions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        pseudo_id VARCHAR(64) NULL,
        points INT NOT NULL,
        type ENUM(
            'purchase',
            'video_sponsor',
            'video_ad',
            'share',
            'referral',
            'referral_bonus',
            'profile_complete',
            'nickname',
            'first_purchase',
            'wallet_download',
            'login_daily',
            'streak_bonus',
            'tombola',
            'shop_discovery',
            'feedback',
            'bonus',
            'offerwall',
            'ayet_offerwall',
            'ayet_reversal',
            'decay',
            'vacation_start',
            'vacation_end',
            'admin_adjustment'
        ) NOT NULL,
        source_id VARCHAR(100) NULL,
        source_type VARCHAR(50) NULL,
        dedupe_key VARCHAR(150) NULL,
        metadata JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_dedupe_user (user_id, dedupe_key),
        UNIQUE KEY uniq_dedupe_pseudo (pseudo_id, dedupe_key),
        KEY idx_user_transactions (user_id, created_at),
        KEY idx_pseudo_transactions (pseudo_id, created_at),
        KEY idx_type (type),
        KEY idx_created (created_at),
        KEY idx_source (source_id)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}level_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        pseudo_id VARCHAR(64) NULL,
        old_level ENUM('basic','bronze','silver','gold','platinum','legend') NULL,
        new_level ENUM('basic','bronze','silver','gold','platinum','legend') NOT NULL,
        points_at_change INT NOT NULL,
        reason ENUM('upgrade','downgrade','decay','admin') NOT NULL DEFAULT 'upgrade',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_user_history (user_id, created_at),
        KEY idx_pseudo_history (pseudo_id, created_at)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}decay_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        pseudo_id VARCHAR(64) NULL,
        points_before INT NOT NULL,
        points_after INT NOT NULL,
        decay_amount INT NOT NULL,
        decay_percentage DECIMAL(5,2) NOT NULL,
        days_inactive INT NOT NULL,
        last_activity_date DATETIME NOT NULL,
        applied TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_user_decay (user_id, created_at),
        KEY idx_pseudo_decay (pseudo_id, created_at)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}leaderboard_cache (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        pseudo_id VARCHAR(64) NULL,
        rank_global INT NOT NULL,
        rank_ngo INT NULL,
        ngo_slug VARCHAR(100) NULL,
        points_total INT NOT NULL,
        current_level VARCHAR(20) NOT NULL,
        cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_user_ngo (user_id, ngo_slug),
        UNIQUE KEY uniq_pseudo_ngo (pseudo_id, ngo_slug),
        KEY idx_rank_global (rank_global),
        KEY idx_expires (expires_at)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}user_referrals (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        referrer_user_id BIGINT UNSIGNED NULL,
        referrer_pseudo_id VARCHAR(64) NULL,
        referred_pseudo_id VARCHAR(255) NULL,
        referral_code VARCHAR(20) NOT NULL UNIQUE,
        status ENUM('pending','active','completed','expired') NOT NULL DEFAULT 'pending',
        click_count INT NOT NULL DEFAULT 0,
        first_click_at DATETIME NULL,
        registered_at DATETIME NULL,
        first_purchase_at DATETIME NULL,
        referrer_transaction_id BIGINT UNSIGNED NULL,
        referred_transaction_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY idx_referrer (referrer_user_id),
        KEY idx_referrer_pseudo (referrer_pseudo_id),
        KEY idx_referred (referred_pseudo_id),
        KEY idx_code (referral_code),
        KEY idx_status (status),
        KEY idx_expires (expires_at)
    ) {$charset};";

    $tables[] = "CREATE TABLE {$wpdb->prefix}referral_clicks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        referral_code VARCHAR(20) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        referer_url TEXT NULL,
        utm_source VARCHAR(100) NULL,
        utm_medium VARCHAR(100) NULL,
        utm_campaign VARCHAR(100) NULL,
        cookie_set TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_code (referral_code),
        KEY idx_created (created_at)
    ) {$charset};";

    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    sharity_points_maybe_alter_nullable_columns();
    sharity_points_maybe_alter_point_transaction_types();

    update_option('sharity_points_schema_version', SHARITY_POINTS_SCHEMA);
    delete_transient('sharity_points_schema_lock');
}

function sharity_points_maybe_alter_nullable_columns(): void
{
    global $wpdb;

    $tables = [
        "{$wpdb->prefix}user_points" => ['user_id' => 'BIGINT UNSIGNED NULL'],
        "{$wpdb->prefix}point_transactions" => ['user_id' => 'BIGINT UNSIGNED NULL'],
        "{$wpdb->prefix}level_history" => ['user_id' => 'BIGINT UNSIGNED NULL'],
        "{$wpdb->prefix}decay_logs" => ['user_id' => 'BIGINT UNSIGNED NULL'],
        "{$wpdb->prefix}leaderboard_cache" => ['user_id' => 'BIGINT UNSIGNED NULL'],
        "{$wpdb->prefix}user_referrals" => ['referrer_user_id' => 'BIGINT UNSIGNED NULL'],
    ];

    foreach ($tables as $table => $columns) {
        foreach ($columns as $column => $definition) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                $table,
                $column
            ));
            if (!$exists) {
                continue;
            }
            $wpdb->query("ALTER TABLE {$table} MODIFY {$column} {$definition}");
        }
    }

    $referrals_table = "{$wpdb->prefix}user_referrals";
    $column_exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        $referrals_table,
        'referrer_pseudo_id'
    ));
    if ($column_exists === 0) {
        $wpdb->query("ALTER TABLE {$referrals_table} ADD COLUMN referrer_pseudo_id VARCHAR(64) NULL");
        $wpdb->query("ALTER TABLE {$referrals_table} ADD KEY idx_referrer_pseudo (referrer_pseudo_id)");
    }
}

function sharity_points_maybe_alter_point_transaction_types(): void
{
    global $wpdb;

    $table = "{$wpdb->prefix}point_transactions";
    $column = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'type'", ARRAY_A);
    if (!$column) {
        return;
    }

    $required = [
        "'purchase'",
        "'video_sponsor'",
        "'video_ad'",
        "'share'",
        "'referral'",
        "'referral_bonus'",
        "'profile_complete'",
        "'nickname'",
        "'first_purchase'",
        "'wallet_download'",
        "'login_daily'",
        "'streak_bonus'",
        "'tombola'",
        "'shop_discovery'",
        "'feedback'",
        "'bonus'",
        "'offerwall'",
        "'ayet_offerwall'",
        "'ayet_reversal'",
        "'decay'",
        "'vacation_start'",
        "'vacation_end'",
        "'admin_adjustment'",
    ];

    $typeDef = (string) ($column['Type'] ?? '');
    $missing = false;
    foreach ($required as $token) {
        if (strpos($typeDef, $token) === false) {
            $missing = true;
            break;
        }
    }

    if (!$missing) {
        return;
    }

    $enumSql = implode(',', $required);
    $wpdb->query(
        "ALTER TABLE {$table} MODIFY `type` ENUM({$enumSql}) NOT NULL"
    );
}

/** Explicit additive migration. Existing point/history data is never rebuilt. */
function sharity_points_migrate_impactshop(bool $dry_run = false): array
{
    global $wpdb;
    $required = ['user_points', 'point_transactions', 'level_history'];
    foreach ($required as $suffix) {
        $engine = $wpdb->get_var($wpdb->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',
            $wpdb->prefix . $suffix
        ));
        if (strtoupper((string) $engine) !== 'INNODB') {
            return ['success' => false, 'error' => 'points_table_not_innodb:' . $suffix];
        }
    }
    if (PHP_INT_SIZE < 8) {
        return ['success' => false, 'error' => 'php_64_bit_required'];
    }
    if ($dry_run) {
        return ['success' => true];
    }
    if (!add_option('sharity_points_impactshop_migration_lock', gmdate('c'), '', false)) {
        return ['success' => false, 'error' => 'migration_locked'];
    }
    try {
        update_option('sharity_points_impactshop_migration', ['state' => 'started', 'at' => gmdate('c')], false);
        sharity_points_impactshop_sql($wpdb->query('SET SESSION lock_wait_timeout=5'));
        sharity_points_impactshop_sql($wpdb->query('SET SESSION innodb_lock_wait_timeout=5'));
        $charset = $wpdb->get_charset_collate();
        $statements = sharity_points_impactshop_schema_sql($charset);
        foreach ($statements as $sql) {
            sharity_points_impactshop_sql($wpdb->query($sql));
        }
        $table = $wpdb->prefix . 'user_points';
        $column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'impactshop_subject_digest'");
        if ($column === null) {
            sharity_points_impactshop_sql($wpdb->query("ALTER TABLE {$table} ADD COLUMN impactshop_subject_digest BINARY(32) NULL, ALGORITHM=INPLACE, LOCK=NONE"));
        }
        $index = $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name='uniq_impactshop_subject'");
        if ($index === null) {
            sharity_points_impactshop_sql($wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_impactshop_subject (impactshop_subject_digest), ALGORITHM=INPLACE, LOCK=NONE"));
        }
        $history = $wpdb->prefix . 'point_transactions';
        $type = $wpdb->get_row("SHOW COLUMNS FROM {$history} LIKE 'type'", ARRAY_A);
        if (!is_array($type) || !str_starts_with((string) $type['Type'], 'enum(')) {
            throw new RuntimeException('unexpected_history_type');
        }
        if (!str_contains($type['Type'], "'purchase_reversal'")) {
            // Retain every existing enum value, including unrelated reward types.
            $enum = substr($type['Type'], 0, -1) . ",'purchase_reversal')";
            sharity_points_impactshop_sql($wpdb->query("ALTER TABLE {$history} MODIFY type {$enum} NOT NULL, ALGORITHM=INPLACE, LOCK=NONE"));
        }
        $subject = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'impactshop_subject_digest'", ARRAY_A);
        if (!is_array($subject) || strtolower($subject['Type']) !== 'binary(32)') {
            throw new RuntimeException('subject_column_unverified');
        }
        foreach (['impactshop_point_settlements', 'impactshop_point_changes'] as $suffix) {
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->prefix . $suffix
            ));
            if (strtoupper((string) $engine) !== 'INNODB') {
                throw new RuntimeException('impactshop_schema_unverified');
            }
        }
        foreach (['impactshop_point_settlements' => ['root_id','subject_digest','awarded_points','reversed_points','forgiven_points','version','needs_reconciliation'],
                  'impactshop_point_changes' => ['root_id','logical_id','payload_digest','event_json','actual_delta','run_id','resolution_digest','outcome_json']] as $suffix => $columns) {
            $tableName = $wpdb->prefix . $suffix;
            $actual = $wpdb->get_col("SHOW COLUMNS FROM {$tableName}");
            if (!is_array($actual) || array_diff($columns, $actual)) { throw new RuntimeException('schema_columns_unverified'); }
        }
        $eventsTable = $wpdb->prefix . 'impactshop_point_changes';
        $unique = $wpdb->get_results("SHOW INDEX FROM {$eventsTable} WHERE Key_name='exact_event'", ARRAY_A);
        if (array_column((array) $unique, 'Column_name') !== ['logical_id','payload_digest']
            || array_filter((array) $unique, static fn($i) => (int) $i['Non_unique'] !== 0)) {
            throw new RuntimeException('event_uniqueness_unverified');
        }
        update_option('sharity_points_impactshop_schema', '2026-10-29-simple1', false);
        if ((string) get_option('sharity_points_impactshop_schema') !== '2026-10-29-simple1') {
            throw new RuntimeException('schema_option_unverified');
        }
        update_option('sharity_points_impactshop_migration', ['state' => 'completed', 'at' => gmdate('c')], false);
        return ['success' => true];
    } catch (Throwable $error) {
        update_option('sharity_points_impactshop_migration', ['state' => 'incomplete', 'at' => gmdate('c')], false);
        return ['success' => false, 'error' => 'migration_incomplete'];
    } finally {
        delete_option('sharity_points_impactshop_migration_lock');
    }
}

function sharity_points_impactshop_schema_sql(string $charset): array
{
    global $wpdb;
    return [
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}impactshop_point_settlements (
            root_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            provider VARCHAR(16) NOT NULL,
            namespace VARCHAR(96) NOT NULL,
            subject_digest BINARY(32) NULL,
            original_purchase_at DATETIME NULL,
            merchant VARCHAR(128) NOT NULL DEFAULT '',
            state VARCHAR(16) NOT NULL DEFAULT 'pending',
            version BIGINT UNSIGNED NOT NULL DEFAULT 0,
            awarded_points INT UNSIGNED NOT NULL DEFAULT 0,
            reversed_points INT UNSIGNED NOT NULL DEFAULT 0,
            forgiven_points INT UNSIGNED NOT NULL DEFAULT 0,
            purchase_transaction_id BIGINT UNSIGNED NULL,
            refund_transaction_id BIGINT UNSIGNED NULL,
            needs_reconciliation TINYINT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            KEY subject_state (subject_digest, state)
        ) ENGINE=InnoDB {$charset}",
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}impactshop_point_changes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            root_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            logical_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            payload_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_json LONGTEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'queued',
            reason VARCHAR(64) NOT NULL DEFAULT '',
            run_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
            actual_delta INT NOT NULL DEFAULT 0,
            forgiven_points INT UNSIGNED NOT NULL DEFAULT 0,
            transaction_id BIGINT UNSIGNED NULL,
            resolution_digest CHAR(64) NULL,
            outcome_json LONGTEXT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            next_retry_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY exact_event (logical_id, payload_digest),
            KEY economic_root (root_id, id),
            KEY pending (status, next_retry_at, id),
            KEY run_budget (run_id, actual_delta)
        ) ENGINE=InnoDB {$charset}",
    ];
}
