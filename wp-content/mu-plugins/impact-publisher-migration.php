<?php
/**
 * WHAT: Idempotens migráció az AI Publishing Loop alap tábláihoz (token store, queue, AB teszt).
 * WHY: A publikációs orchestrátorhoz szükséges perzisztens tárolók létrehozása DNS/proxy nélkül is előkészíti a backendet.
 * HOW: muplugins_loaded hookból dbDelta-vel létrehozza/frissíti a wp_impact_tokens, wp_impact_token_audit,
 *      wp_impact_publish_queue és wp_impact_ab_tests táblákat, verziózva az option tárolóban.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('muplugins_loaded', function () {
    global $wpdb;

    $schema_version = '2025-12-11-1';
    $option_key = 'impact_publisher_schema_version';
    $current = get_option($option_key);

    if ($current === $schema_version) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    // Token Store: multi-tenant, token típus, rotáció/adatvédelmi audit támogatással
    $tokens_table = $wpdb->prefix . 'impact_tokens';
    $sql_tokens = "CREATE TABLE $tokens_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      platform VARCHAR(50) NOT NULL,
      account_id VARCHAR(255) NOT NULL,
      tenant_id VARCHAR(100) NULL,
      ngo_id VARCHAR(100) NULL,
      token_type VARCHAR(50) NOT NULL DEFAULT 'default',
      access_token TEXT NOT NULL,
      refresh_token TEXT NULL,
      expires_at DATETIME NULL,
      scope TEXT NULL,
      created_by VARCHAR(100) NULL,
      last_used DATETIME NULL,
      rotation_count INT NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_platform_account_type (platform, account_id, token_type),
      INDEX idx_expires (expires_at),
      INDEX idx_tenant (tenant_id)
    ) $charset;";
    dbDelta($sql_tokens);

    // Token audit: minden read/write/refresh művelet naplózása
    $token_audit = $wpdb->prefix . 'impact_token_audit';
    $sql_token_audit = "CREATE TABLE $token_audit (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      platform VARCHAR(50) NOT NULL,
      account_id VARCHAR(255) NOT NULL,
      tenant_id VARCHAR(100) NULL,
      action ENUM('read','write','refresh','rotate','delete') NOT NULL,
      user_id VARCHAR(100) NULL,
      ip VARCHAR(64) NULL,
      note TEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      INDEX idx_platform (platform),
      INDEX idx_account (account_id),
      INDEX idx_action (action)
    ) $charset;";
    dbDelta($sql_token_audit);

    // Publishing queue: idempotencia, prioritás, státusz, dry-run, hibalog
    $queue_table = $wpdb->prefix . 'impact_publish_queue';
    $sql_queue = "CREATE TABLE $queue_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id VARCHAR(64) NOT NULL,
      idempotency_key VARCHAR(128) NULL,
      platform VARCHAR(50) NOT NULL,
      account_id VARCHAR(255) NOT NULL,
      status ENUM('queued','pending','approved','processing','published','failed','rejected','suspended','awaiting_budget','dry_run_complete') NOT NULL DEFAULT 'queued',
      priority TINYINT NOT NULL DEFAULT 5,
      requires_approval TINYINT(1) NOT NULL DEFAULT 0,
      dry_run TINYINT(1) NOT NULL DEFAULT 0,
      content JSON NOT NULL,
      metadata JSON NULL,
      ab_test_id VARCHAR(64) NULL,
      ab_bucket CHAR(1) NULL,
      spend_cap DECIMAL(12,2) NULL,
      attempts INT NOT NULL DEFAULT 0,
      max_attempts INT NOT NULL DEFAULT 3,
      scheduled_at DATETIME NULL,
      processed_at DATETIME NULL,
      error TEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_job (job_id),
      UNIQUE KEY uq_idempotency (idempotency_key),
      INDEX idx_status (status),
      INDEX idx_platform (platform),
      INDEX idx_priority (priority),
      INDEX idx_scheduled (scheduled_at),
      INDEX idx_ab (ab_test_id, ab_bucket)
    ) $charset;";
    dbDelta($sql_queue);

    // A/B tesztek összefoglaló táblája
    $ab_table = $wpdb->prefix . 'impact_ab_tests';
    $sql_ab = "CREATE TABLE $ab_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      ab_test_id VARCHAR(64) NOT NULL,
      status ENUM('running','completed','cancelled') NOT NULL DEFAULT 'running',
      winner_bucket CHAR(1) NULL,
      completed_at DATETIME NULL,
      metrics_summary JSON NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_ab (ab_test_id),
      INDEX idx_status (status)
    ) $charset;";
    dbDelta($sql_ab);

    update_option($option_key, $schema_version, true);
});
