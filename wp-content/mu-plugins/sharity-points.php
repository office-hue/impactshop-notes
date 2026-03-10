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

add_action('muplugins_loaded', 'sharity_points_maybe_migrate', 5);
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
