# Notes finding-elsőbbség — protected source record

Protected files touched: docs/bastion-guard-status.md.
Kapcsolódó source: scripts/workspace-hooks.py és tests/notes-workspace-support.test.py.
Explicit engedély: a teljes DEV-retirement és a feltárt P1 célzott javításának,
DOCSYNC-jének és normál forrás-PR-jének jóváhagyása. Nem product-deploy engedély.

Koherencia: a natív/fallback audit összegyűjtött secret/content/product hibája
nem veszhet el későbbi product manifest/path/runtime unavailable miatt.
Hiányzó product adat önmagában advisory; hiányzó check nem sikeres evidence.
Kockázat: fals engedélyezés vagy új kötelező DEV-kapu. A javítás finding1
elsőbbséget és őszinte unavailable2-t ad; nem módosít product checker logikát,
guide/JYSK/WordPress/affiliate/Impi payloadot, workflow-t vagy providerbeállítást.

Érintett funkciók: opcionális audit/hook eredmény és DOCSYNC státusz.
Route/REST/shortcode/reward/import/redirect/adminUI nincs érintve.
Smoke: 3 új negatív fixture, majd25/25 érintett supportfixture PASS20.715s;
missing/malformed manifest+secret deny; missing manifest clean advisory;
későbbi path exception nem nyeli el a findinget. Független main diffreview kész.
Kézi web-UI checklist N/A, termék/UI payload változatlan. Host canary új merge után.

Rollback: csak saját delta célzott revertje a cab09c88ad2a976a8b774798e685148ad3b4fc22
preimage-re, foreign WIP megtartásával. Korábbi Air hostapply saját CAS-rollbackje
már kész,37WT HEAD/index/WIP/source unchanged; current nincs, Mini nem alkalmazott.
Own Git-metadata backup/journal és inactivebundle legalább30napig megőrzendő;
ez külön jóváhagyott incidens-retention, nem általános cleanupengedély.

PR utáni check: normál required CI actualreadback, utána source merge tree;
host csomag/current/hook CAS és installed natív deny/clean/advisory canary külön.
Nincs staging smoke, deploy vagy production acceptance állítva.
