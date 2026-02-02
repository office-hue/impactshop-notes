# Partner DB migration template (WP-CLI + SQL)

## Cél
Egységes sablon a partner táblák létrehozásához és módosításához staging/prod környezetben.

---

## A) WP-CLI migrációs sablon (ajánlott)

**Fájl helye (javaslat)**: `wp-content/mu-plugins/impactshop-partner-migrations.php`

```php
<?php
// impactshop-partner-migrations.php (sablon)

if (!defined('ABSPATH')) {
    exit;
}

function impactshop_partner_migration_v1() {
    global $wpdb;

    $table = $wpdb->prefix . 'impact_partner_tx';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      partner_code VARCHAR(64) NOT NULL,
      event_id VARCHAR(128) NOT NULL,
      event_type VARCHAR(32) NOT NULL DEFAULT 'purchase',
      pseudo_id_hash CHAR(64) NOT NULL,
      ngo_code VARCHAR(64) NULL,
      amount_gross INT NOT NULL,
      currency CHAR(3) NOT NULL DEFAULT 'HUF',
      status VARCHAR(16) NOT NULL DEFAULT 'pending',
      discount_tier VARCHAR(16) NULL,
      partner_max_discount DECIMAL(5,4) NULL,
      discount_rate DECIMAL(5,4) NULL,
      discount_amount INT NULL,
      idempotency_key VARCHAR(128) NOT NULL,
      proof_hash CHAR(64) NULL,
      payload_json LONGTEXT NULL,
      ledger_id BIGINT UNSIGNED NULL,
      reconcile_status VARCHAR(16) NULL,
      reconcile_batch_id VARCHAR(64) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      approved_at DATETIME NULL,
      declined_at DATETIME NULL,
      void_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_partner_event (partner_code, event_id),
      KEY idx_status (status),
      KEY idx_created (created_at),
      KEY idx_reconcile (reconcile_status)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Admin only: trigger migration via WP-CLI or admin action.
```

**WP-CLI futtatás (példa)**:
```bash
wp eval 'impactshop_partner_migration_v1();'
```

---

## B) SQL migrációs sablon (direct)

```sql
CREATE TABLE wp_impact_partner_tx (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_code VARCHAR(64) NOT NULL,
  event_id VARCHAR(128) NOT NULL,
  event_type VARCHAR(32) NOT NULL DEFAULT 'purchase',
  pseudo_id_hash CHAR(64) NOT NULL,
  ngo_code VARCHAR(64) NULL,
  amount_gross INT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'HUF',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  discount_tier VARCHAR(16) NULL,
  partner_max_discount DECIMAL(5,4) NULL,
  discount_rate DECIMAL(5,4) NULL,
  discount_amount INT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  proof_hash CHAR(64) NULL,
  payload_json LONGTEXT NULL,
  ledger_id BIGINT UNSIGNED NULL,
  reconcile_status VARCHAR(16) NULL,
  reconcile_batch_id VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  declined_at DATETIME NULL,
  void_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_partner_event (partner_code, event_id),
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_reconcile (reconcile_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Rollback sablon
```sql
DROP TABLE IF EXISTS wp_impact_partner_tx;
```

---

## Megjegyzés
- Stagingen futtasd először.
- Prod futtatás előtt: mentés + impactall guard (ha releváns).
