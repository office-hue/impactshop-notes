# Partner SLA – one‑pager

## Cél
Üzleti/jogi összefoglaló a partner integráció szolgáltatási szintjeiről.

---

## Szolgáltatási szintek (javaslat)
- **Webhook elérhetőség**: 99.5%/hó
- **Idempotency kezelése**: 24 órás dedupe
- **Visszaigazolás**: azonnali (`< 2s`) válasz webhookra

---

## Incidens kezelés
- **P1**: kritikus integrációs hiba → 4 órán belüli válasz
- **P2**: részleges kiesés → 1 munkanap
- **P3**: kisebb hiba → 3 munkanap

---

## Adatmegőrzés
- Payload: 180 nap
- Audit log: 3 év

---

## Dispute SLA
- Első válasz: 2 munkanap
- Döntés: 5 munkanap

---

## Megjegyzés
- SLA-k partner szerződésben rögzítendők.
