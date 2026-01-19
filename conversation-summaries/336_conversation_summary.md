# 336. Beszélgetés összefoglaló: PIN SMS/QR runbook

Kérés: runbook készítése a PIN SMS/QR smoke tesztekhez.

- Új dokumentum: `docs/pin-sms-runbook.md` (staging smoke + rate limit reset).
- Kiegészítve: production deploy + smoke + rollback lépések.
- Go/No-go checklist bekerült a prod részhez.
- Top-level checklist kivonat is bekerült a runbookba.
- Pre-smoke mini-checklist bekerült a staging részhez.
- Post-smoke ellenőrzés (debug log + delivery log) hozzáadva.
- Prod post-smoke ellenőrzés (debug log + delivery log) hozzáadva.
- Gyors log-tail parancs bekerült a prod post-smoke részhez.
- Gyors log-tail parancs bekerült a staging post-smoke részhez.
- Egyparancsos staging smoke + log tail blokk hozzáadva.
- Egyparancsos prod smoke + log tail blokk hozzáadva.
- Gyors parancsok szekció bekerült a runbook elejére.
- Gyors parancsok blokk konkrét parancsokkal bővítve.
- Gyors parancsok blokk paraméterezhető `PSEUDO_ID`/`PHONE` változókkal.
- Gyors parancsok blokk minta változók szekcióval bővítve.
- Minta változók megjegyzéssel (teszt pseudo-ID/telefon, staging; ékezetes).
