# Dispute policy – SLA és döntési fa

## Cél
Átlátható vita‑kezelés a partner tranzakciókra, SLA‑val és felelősségi körökkel.

---

## SLA (javaslat)
- **Első visszajelzés**: 2 munkanap
- **Döntés**: 5 munkanap
- **Komplex eset**: max 10 munkanap (indoklással)

---

## Felelősségek
- **Partner**: forrás adatok (order/booking) biztosítása, refund státuszok jelzése
- **Impact Shop**: ledger egyezés, audit log, döntés dokumentálása
- **NGO**: nincs közvetlen beavatkozás

---

## Döntési fa (manual review)
```
Start
 ├─ Van érvényes HMAC + idempotency? → nem → Declined
 ├─ Event_id egyedi? → nem → Void (duplicate)
 ├─ Partner bizonyíték rendben? → nem → Disputed
 ├─ Összeg egyezik? → nem → Disputed
 └─ OK → Approved
```

---

## Dispute státuszok
- `disputed` → manuális review
- `approved` → ledger véglegesítés
- `declined` → elutasítva

---

## Kommunikáció
- Partner értesítés email/webhook
- Audit log minden döntéshez
