<?php
/**
 * Plugin Name: ImpactShop Identity PIN Migration
 * Description: Creates the wp_impact_pin_tokens table for PIN issuance/verify.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('muplugins_loaded', function () {
    global $wpdb;

    $schema_version = '2026-01-18-1';
    $option_key = 'impact_pin_schema_version';
    $current = get_option($option_key);

    if ($current === $schema_version) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'impact_pin_tokens';

    $sql = "CREATE TABLE $table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      pseudo_hash CHAR(64) NOT NULL,
      pin_hash VARCHAR(255) NOT NULL,
      issued_ip_hash CHAR(64) NULL,
      expires_at DATETIME NOT NULL,
      attempts INT NOT NULL DEFAULT 0,
      locked_until DATETIME NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_pseudo (pseudo_hash),
      INDEX idx_expires (expires_at),
      INDEX idx_locked (locked_until),
      INDEX idx_used (used_at),
      INDEX idx_pseudo_expires (pseudo_hash, expires_at),
      INDEX idx_pseudo_used (pseudo_hash, used_at)
    ) $charset;";

    dbDelta($sql);
    update_option($option_key, $schema_version);

    $history_table = $wpdb->prefix . 'impact_pin_migration_history';
    $history_sql = "CREATE TABLE IF NOT EXISTS $history_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      version VARCHAR(50) NOT NULL,
      description TEXT NULL,
      applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_version (version)
    ) $charset;";
    $wpdb->query($history_sql);

    $wpdb->insert(
        $history_table,
        [
            'version'     => $schema_version,
            'description' => 'PIN tokens table + composite indexes',
        ],
        ['%s', '%s']
    );
});
