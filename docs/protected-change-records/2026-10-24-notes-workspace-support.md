# Notes stable support — protected source record

Protected files touched: .github/workflows/ci.yml, docs/bastion-guard-status.md,
scripts/workspace-hooks.py és az új Notes support/adat-root adapter.
Felhatalmazás: a jóváhagyott DEV-retirement célhoz szükséges, külön kért szűk
helyi kompatibilitási javítás; nem hostapply vagy forráspublikálás.

Koherencia/érintettség: source-owned optional hook fallback, Notes checker
tool-root, target-WT adatgyökér és inaktív csomag export. WordPress, Hatás Körök,
affiliate/Impi checkerlogika és termékpayload változatlan.
Kockázat: hamis ellenőrzés vagy idegen WT/guard forrás végrehajtása; védelem:
explicit root/owner/containment, változatlan review-zott product script és
tényleges negatív fixture, nem DEV permission engine.

Rollback: saját delta a 7417e18bad21a31a781917003af7b8ea61335838 preimage-re,
idegen WIP megtartásával. Smoke: izolált secret/protected/foreign-WIP és
affiliate/Impi/root-drift fixture, 22/22 tényleges PASS. Régi regresszió:
34 változatlan eset PASS + a szövegassertion javítása után célzott 1/1 PASS.
Részletek: docs/notes-workspace-support-repair-2026-10-24.md.
Független main-review metadata-típus/ciklus és shell-child importárnyékolás
lelete javítva, valódi renderelt hook/regresszió igazolja.
Kézi termék-UI checklist N/A; termék/UI payload nem változik.
