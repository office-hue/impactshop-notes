# Data retention policy – partner payload

## Cél
A partner tranzakciók payload‑jának tárolása **auditálható**, de korlátozott ideig történjen.

---

## Retention időtartam (javaslat)
- **payload_json**: 180 nap
- **proof_hash + fő mezők**: 3 év
- **audit log**: 3 év

---

## Archíválás
- 180 nap után payload kivétele/anonimizálása
- Archívum: CSV export + hash ellenőrzés

---

## Törlés
- GDPR/PII: nincs PII a payloadban, de kérésre anonimizálható
- Archív törlés 3 év után

---

## Megjegyzés
- Retention policy a partner szerződés részévé tehető
