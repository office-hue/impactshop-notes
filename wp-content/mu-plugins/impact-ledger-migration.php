<?php
/**
 * WHAT: Idempotens migráció az egységes impact ledger és rate/cap táblákhoz.
 * WHY: A shop + social/ads adományokat egységes, auditálható struktúrában kell tartani.
 * HOW: muplugins_loaded hookból dbDelta-vel létrehozza/frissíti a wp_impact_rates és wp_impact_ledger táblákat, opcionális seed alap beállításokkal.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$schema_version = '2025-12-09-1';
$option_key = 'impact_ledger_schema_version';

$current = get_option($option_key);
if ($current !== $schema_version) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    // Rates / cap config (platform + source + rate/cap idősávval)
    $rates_table = $wpdb->prefix . 'impact_rates';
    $sql_rates = "CREATE TABLE $rates_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      platform VARCHAR(50) NOT NULL,
      source ENUM('view','click','shop','challenge','match') NOT NULL,
      advertiser_code VARCHAR(100) NULL,
      ngo_code VARCHAR(100) NULL,
      rate_huf DECIMAL(10,4) NOT NULL,
      cap_huf DECIMAL(10,2) NULL,
      valid_from DATE NOT NULL,
      valid_to DATE NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      INDEX idx_platform (platform),
      INDEX idx_valid (valid_from, valid_to)
    ) $charset;";
    dbDelta($sql_rates);

    // Egységes ledger kibővítve payout/árfolyam/event dedup mezőkkel
    $ledger_table = $wpdb->prefix . 'impact_ledger';
    $sql_ledger = "CREATE TABLE $ledger_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      source ENUM('shop','view','click','challenge','match','donation') NOT NULL,
      status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
      rejection_reason TEXT NULL,
      created_by VARCHAR(100) NULL,
      updated_at TIMESTAMP NULL,
      platform VARCHAR(50) NULL,
      ngo_code VARCHAR(100) NULL,
      advertiser_code VARCHAR(100) NULL,
      campaign_id VARCHAR(255) NULL,
      ad_id VARCHAR(255) NULL,
      currency CHAR(3) NOT NULL DEFAULT 'HUF',
      amount_gross DECIMAL(10,2) NOT NULL,
      amount_net DECIMAL(10,2) NULL,
      amount_huf DECIMAL(10,2) NULL,
      exchange_rate DECIMAL(10,6) NULL,
      payout_batch VARCHAR(50) NULL,
      event_id VARCHAR(255) NULL,
      meta JSON NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      approved_at TIMESTAMP NULL,
      paid_at TIMESTAMP NULL,
      PRIMARY KEY (id),
      INDEX idx_source (source),
      INDEX idx_status (status),
      INDEX idx_ngo (ngo_code),
      INDEX idx_event (event_id),
      INDEX idx_created (created_at),
      INDEX idx_payout (payout_batch)
    ) $charset;";
    dbDelta($sql_ledger);

    // Audit trail a státuszváltások követéséhez
    $audit_table = $wpdb->prefix . 'impact_ledger_audit';
    $sql_audit = "CREATE TABLE $audit_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      ledger_id BIGINT UNSIGNED NOT NULL,
      old_status ENUM('pending','approved','paid','rejected') NULL,
      new_status ENUM('pending','approved','paid','rejected') NOT NULL,
      changed_by VARCHAR(100) NULL,
      changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      INDEX idx_ledger (ledger_id),
      INDEX idx_changed (changed_at)
    ) $charset;";
    dbDelta($sql_audit);

    // Opcionális seed alap sorok, csak ha üres a rates tábla
    $rates_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $rates_table");
    if ($rates_count === 0) {
        $seed = [
            [
                'platform' => 'dognet',
                'source' => 'shop',
                'rate_huf' => 0, // ténylegesen a commission 50%-a, itt meta megjegyzés
                'cap_huf' => null,
                'valid_from' => gmdate('Y-m-d'),
            ],
            [
                'platform' => 'meta',
                'source' => 'view',
                'rate_huf' => 0,
                'cap_huf' => null,
                'valid_from' => gmdate('Y-m-d'),
            ],
        ];
        foreach ($seed as $row) {
            $wpdb->insert($rates_table, $row);
        }
    }

    update_option($option_key, $schema_version, true);
}
