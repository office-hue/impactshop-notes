# Partner demo forgatókönyv (end‑to‑end)

## Szereplők
- **Partner**: webshop (Shopify)
- **Vásárló**: Sharity ID felhasználó
- **Impact Shop**: partner API + ledger

---

## Lépések
1. **Vásárló belép** → Sharity ID aktív (pseudo‑ID cookie megvan).
2. **Partner checkout** → pseudo‑ID + NGO kód bekerül az order note‑ba.
3. **Partner webhook** elküldi a tranzakciót (`/impact/v1/partner/transaction`).
4. **Impact Shop válaszol** → `accepted` + `ledger_id`.
5. **Kedvezmény igazolás** → `/impact/v1/partner/discount/quote` visszaadja a szintet.
6. **Vásárló dashboard** → látja: tranzakció státusz, kedvezmény, NGO.
7. **Partner dashboard** → exportálja: ledger_id, event_id, státusz.

---

## Elfogadási kritériumok
- A tranzakció **azonosítható** (ledger_id + event_id).
- A kedvezmény **konzisztens** (tier + partner_max_discount).
- A vásárló és a partner **ugyanazt a státuszt** látja.
