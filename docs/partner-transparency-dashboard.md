# Partner & Vásárlói Transzparencia Dashboard – Spec

## Cél
A partner és a vásárló **ugyanazt a valóságot** lássa: minden tranzakció azonosítható, státuszolt, bizonyítható és visszakereshető legyen. Ez csökkenti a vitákat és növeli a bizalmat.

---

## 1) Vásárlói transzparencia (Sharity ID nézet)
### 1.1 Tranzakció lista
**Kötelező mezők**:
- Dátum / idő
- Partner neve + logo
- NGO
- Összeg (bruttó)
- Kedvezmény (szint + %) + nettó összeg
- Státusz (pending/approved/declined/void)
- Tranzakció azonosító (ledger_id)

**Státusz magyarázatok**:
- **Pending**: partner ellenőrzés alatt (pl. fizetés feldolgozás, visszaélés szűrés)
- **Approved**: hitelesített, pont és adomány jóváírva
- **Declined**: visszavonva/refund, pont visszavonva
- **Void**: duplikált/hibás próbálkozás, nem könyveljük

### 1.2 Részletes tranzakció nézet
**Mutatandó mezők**:
- `ledger_id`
- `event_id` (partner rendelés/foglalás azonosító)
- `partner_code`
- `payment_status` (paid/unpaid/refunded)
- `discount_tier`, `discount_rate`, `partner_max_discount`
- `proof_hash` (nem publikus, csak „Bizonyítás: elérhető”) 

**Vita gomb**:
- „Tranzakció vitatása” → ticket nyitás (ok + megjegyzés)

---

## 2) Partner transzparencia dashboard
### 2.1 Tranzakciós log (partner nézet)
**Kötelező oszlopok**:
- `ledger_id`
- `event_id`
- `pseudo_id_hash`
- `ngo_code`
- `amount_gross`, `discount_rate`, `amount_net`
- `status`
- `received_at`, `approved_at`

**Szűrők**:
- Dátum intervallum
- Státusz
- NGO
- Eseménytípus (purchase/booking/retail)
- Kedvezmény szint

### 2.2 Dispute / egyeztetés nézet
**Cél**: minden eltérés kezelhető legyen 1 kattintással.

- Lista: `ledger_id`, `event_id`, státusz, eltérés oka
- Gombok: **Approve**, **Decline**, **Request Info**
- Audit log: ki, mikor, mit döntött

### 2.3 Kedvezmény áttekintő
**Partner által látott logika**:
- Max kedvezmény (pl. 20%)
- Szintarányok (Legend 100% → Basic 50%)
- Érvényes kedvezmény tranzakciónként

---

## 3) Exportok és bizonyíthatóság
### 3.1 CSV/JSON export
**Kötelező mezők**:
- `ledger_id`, `event_id`, `partner_code`
- `amount_gross`, `discount_rate`, `amount_net`
- `status`, `approved_at`, `declined_at`
- `ngo_code`, `pseudo_id_hash`

### 3.2 “Proof bundle” export (vitákhoz)
- Payload hash + HMAC aláírás
- Idempotency key log
- Audit log sor

---

## 4) KPI / bizalom mutatók
- **Approved ratio** (% approved / összes)
- **Pending átlagos idő** (óra)
- **Dispute arány** (% vitatott / összes)
- **Refund/decline arány**

---

## 5) UI irányelvek (egyszerűség)
- 1 fő lista + 1 részletező panel
- Státusz színkódok (pending=szürke, approved=zöld, declined=piros, void=narancs)
- Egyértelmű szövegek: „Feldolgozás alatt”, „Jóváírva”, „Visszavonva”

---

## 6) Következő lépés
- Elfogadod a mezőlistát?
- Melyik partner kapja a pilot dashboardot?
- Kell-e külön „közös” view (partner + vásárló ugyanaz a link)?
