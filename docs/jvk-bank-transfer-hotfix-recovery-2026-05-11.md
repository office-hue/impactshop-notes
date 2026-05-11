# JVK Bank Transfer Confirm Hotfix és Recovery

## Scope
- Repo: `impactshop-notes`
- Kampány: `jovonkvize-2026`
- Érintett runtime fájl: `wp-content/mu-plugins/impactshop-event-donation-widget.php`
- Incidens: admin oldali `Utalás megerősítése` után a flow productionben fatállal megállhatott.

## Gyökérok
- A transfer confirm ág egy nem létező helpert hívott: `impactshop_event_donation_generate_ticket_serial()`.
- Emiatt a megerősítés után a rekord `completed` státuszba kerülhetett, miközben a jegysorszámok és a certificate folyamat nem fejeződött be.

## Javítás
- A hibás hívás az elérhető batch helperre lett cserélve:
  - `impactshop_event_donation_generate_ticket_serials((string)($mergedRow['campaign_slug'] ?? ''), $ticketCount)`
- A fix bastion-approved hotfix sync-kel ment ki productionre és stagingre 2026-05-11-én.

## Helyreállított konkrét rekord
- Donation ID: `ED-20260507190704-CKqNJM`
- Recovery előtti állapot:
  - `status=completed`
  - `ticket_count=2`
  - `ticket_serials=NULL`
  - `request_certificate=1`
  - `donation_cert_status=none`
- Recovery utáni állapot:
  - `ticket_serials=["JOVONKVIZE-2026-2026-00001","JOVONKVIZE-2026-2026-00002"]`
  - `donation_cert_id=SHA-ADOMANY-2026-0008`
  - `donation_cert_status=sent`
  - `donation_cert_sent_at=2026-05-11 12:36:47`

## Production audit
- `completed + bank_transfer` rekordok száma: `2`
- Anomália-sorok száma: `0`
- Következtetés: nem maradt más olyan historical bank transfer rekord, amely a mostani hiba mintájára félbemaradt volna.

## Verifikáció
- Deploy eredmény: `Hotfix sync complete`
- Production adat-audit: a recovery után nincs maradék `ticket_serials` / `donation_cert_status` anomália.
- Célzott log-check a `12:36-12:39 UTC` ablakban nem adott külön app-log sort a donation ID-ra vagy certificate ID-ra, ezért ennél az incidensnél a DB-végállapot a primer bizonyíték.

## Operatív tanulság
- Az admin dashboard `Ujrakuldes` gombja certificate-küldésre jó, de nem teljes transfer-helyreállítási mechanizmus.
- Bank transfer confirm jellegű fatál után nem elég a kódhotfix; kötelező a historical adatok célzott auditja is.