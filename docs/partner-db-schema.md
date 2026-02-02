# Partner tranzakciók – DB séma + migráció

## Cél
A non‑affiliate partner tranzakciók **auditálhatóan, idempotensen és vitakezelhetően** kerüljenek rögzítésre, miközben a meglévő ledger logikával kompatibilisek maradnak.

## Opció A – Ledger bővítés (minimál invazív)
A meglévő `wp_impact_ledger` táblába kerülnek partner‑specifikus mezők.

### Javasolt mezők
- `partner_code` (varchar(64))
- `event_id` (varchar(128))
- `idempotency_key` (varchar(128))
- `source` (varchar(32)) – `shop|retail|hospitality`
- `status` (varchar(16)) – `pending|approved|declined|void`
- `discount_tier` (varchar(16))
- `partner_max_discount` (decimal(5,4))
- `discount_rate` (decimal(5,4))
- `discount_amount` (int)
- `reconcile_status` (varchar(16)) – `matched|mismatch|missing|extra|disputed`
- `reconcile_batch_id` (varchar(64))
- `proof_hash` (char(64)) – payload HMAC hash
- `payload_json` (longtext)
- `approved_at`, `declined_at`, `void_at` (datetime)

### Indexek
- Unique: `(partner_code, event_id)`
- Index: `status`, `source`, `created_at`, `reconcile_status`

### Pro/kontra
- ✅ Egyszerűbb bevezetés
- ⚠️ Ledger tábla szélesedik (több nullable mező)

---

## Opció B – Új partner tábla + ledger link (ajánlott)
Külön táblában kezeljük a partner tranzakciókat, és csak a jóváhagyott események kerülnek a ledgerbe.

### Új tábla: `wp_impact_partner_tx`

**Javasolt mezők**
- `id` (bigint, PK)
- `partner_code` (varchar(64))
- `event_id` (varchar(128))
- `event_type` (varchar(32))
- `pseudo_id_hash` (char(64))
- `ngo_code` (varchar(64))
- `amount_gross` (int)
- `currency` (char(3))
- `status` (varchar(16)) – `pending|approved|declined|void`
- `discount_tier` (varchar(16))
- `partner_max_discount` (decimal(5,4))
- `discount_rate` (decimal(5,4))
- `discount_amount` (int)
- `idempotency_key` (varchar(128))
- `proof_hash` (char(64))
- `payload_json` (longtext)
- `ledger_id` (bigint, null) – link a `wp_impact_ledger` bejegyzéshez
- `reconcile_status` (varchar(16))
- `reconcile_batch_id` (varchar(64))
- `created_at`, `approved_at`, `declined_at`, `void_at` (datetime)

**Indexek**
- Unique: `(partner_code, event_id)`
- Index: `pseudo_id_hash`, `status`, `created_at`, `reconcile_status`

### Kapcsolat a ledgerrel
- `status=approved` esetén ledger bejegyzés készül
- `wp_impact_partner_tx.ledger_id` hivatkozik a ledger sorra

---

## Migrációs terv (javasolt lépések)
1. **Új tábla létrehozása** (`wp_impact_partner_tx`).
2. **Ledger bővítés minimális mezőkkel**: csak `partner_code`, `event_id`, `source`.
3. **API írás**: webhook tranzakciót először partner táblába ment.
4. **Approval flow**: jóváhagyáskor ledger bejegyzés + `ledger_id` visszaírás.
5. **Backfill**: korábbi pilot adat átmozgatása (ha volt ideiglenes tárolás).

### Példa DDL (MySQL)
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

### Rollback
- Új tábla törölhető, ha még nincs éles adat.
- Ledger bővítés esetén `ALTER TABLE` visszaállítás.

### Adatmegőrzés
- `payload_json` 180 nap után archiválható.
- `proof_hash` + fő mezők maradnak audit célra.
