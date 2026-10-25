## 2026-10-25 — Notes audit: valódi finding elsőbbsége, rollout visszaállítva

A #225 merge `cab09c88ad2a976a8b774798e685148ad3b4fc22` fája
`f5cf2fcb4c0f7c417efbfc50bd03c302c034e12f`; a required hosted CI sikeres,
protection nem változott. A következő Air-aktiválás saját natív canary-je
valódi hibát talált: hiányzó product manifest eldobta a már megtalált secretet.
A támogatott Air rollback és 37WT HEAD/index/WIP/source readback sikeres;
régi hook/config visszaállt, current nincs. Mini26WT érintetlen, nincs deploy.

A follow-up a megtalált content/product hibát megtartja: exit1 elsőbbséget
élvez a későbbi product-data/path/runtime unavailable előtt. Hiányzó adat
önmagában továbbra advisory, nem sikeres product-audit vagy DEV-kapu.
25/25 érintett supportfixture PASS (20.715s), benne 3 új kombinált negatív;
független main diffreview lezárt, további material finding nélkül.
Repair/rollback/öt tengely: `docs/notes-workspace-support-repair-2026-10-24.md`;
rekord: `docs/protected-change-records/2026-10-25-notes-audit-findings.md`.
Új forráspublikálás/CI/merge és csak utána új csomag+hostcanary szükséges.
A lentebbi 2026-10-24-i állapotok történetiek.

## 2026-10-24 — Notes stabil support repair: terv, még nem aktivált

Saját ág: `dev/retirement-notes-support-20261024`; exact base:
`7417e18bad21a31a781917003af7b8ea61335838` (#224), tree
`802bf7224b10cb2de8a807a60025e7b81802a7be`.
Egyszeri exact-commit fetch történt; nincs main-chase. Air 37 + Mini 26 régi
WT-ben nincs helyi helper, mindegyik régi staged/foreign-WIP guardot használ.
Ezért a korábbi forrásmerge nem teljes hookaktiválás.

Repair plan és pontos 13 fájlos kör:
`docs/notes-workspace-support-repair-2026-10-24.md`.
Stabil Notes-specifikus, verziózott csomag + explicit trusted tool-root;
adat/manifest/record mindig a cél-WT, nem a supportcsomag.
A négy product checker ellenőrzési logikája és forrása változatlan.
Elfogadás: tényleges secret/protected/foreign-WIP/affiliate/Impi/root-drift
pozitív-negatív fixture; default local működés változatlan.
Most nincs commit/push/CI-attempt, hostcsomag-/hookapply vagy termékdeploy.

Helyi forrásjelölt: 22/22 új izolált fixture PASS; a régi érintett suite 34/35,
majd az egyetlen szöveges no-PASS regresszió javítva és külön 1/1 PASS.
Négy Python AST + CI YAML parse + diff-check OK; négy productchecker byte-változatlan.
Manifest-nonobject/symlinkciklus unavailable/advisory, nem natív DEV-kapu.
Független main-review két konkrét lelete javítva: metadata-típus/ciklus és
shell-child Python importárnyékolás; markeríró targetmodul nem fut a valódi
protected/lane ellenőrzésnél. Ez nem az alábbi önreview megállapítása.
Részletes eredmény, trust-határ és öt tengelyű review a fenti repair planben.

## 2026-10-24 — DEV-kivezetés: helyi forrás, aktiválás nélkül

A kijelölt `dev/retirement-20261024` clone base-e
`bbf2345416fe4aa2f4b49c8f02e57fa4ceb1a77c`.
Az engine/capsule/selector/modell/admission/repin kényszer kivezetve;
a kompatibilitási belépő `retired/advisory`, nem permission vagy PASS.
Natív Git, opcionális saját hook/audit, explicit backup/CAS/rollback telepítő;
nincs központ/sibling írás, automatikus fetch, hosttelepítés vagy termékdeploy.
A WordPress/Hatás Körök/affiliate/guide/JYSK/identity védelem és tényleges
deploy/restore kód megmaradt. Részletek:
`docs/dev-retirement-2026-10-24.md`;
protected rekord: `docs/protected-change-records/2026-10-24-dev-retirement.md`.

Evidencia: 34/34 retirement fixture + 1 új célzott CI content-only fixture; öt deploy/rollback mockteszt;
affiliate tamper fixture; Impi source audit; helyi natív titokscan sikeres.
Az exact-file fixture saját hiányzó staging-guard warningja látható maradt.
Az implementációs tesztkörben nem futott staging SSH, Codex bridge, provider vagy host-apply.
Forráspublikálás külön jóváhagyott PR-útvonalon; merge és deploy nem engedélyezett.
Ez nem teljes repo-CI/repo-audit, kliensbetöltés vagy élő acceptance.
A korábbi DEV-engine/pin kapuleírások történetiek; a termékszabályokat
az aktuális saját forrásból kell alkalmazni.

# Impactshop-Notes Doc Sync Hub

Datum: 2026-07-01
Statusz: canonical root doc-sync hub
Scope: a repo-root szintu belépőpont, amely a helyi doc-sync mapet, a worktree/continuity minimumot es a governance beléptetést egy helyre rendezi.

## Cél

Ez a fajl a repo gyokerbol elerheto, rovid index arra, hogy egy adott tema helyi dokumentacios allapota hol talalhato.

Hasznalat:

1. elobb ezt a hubot olvasd;
2. utana a helyi canonical mapet;
3. majd a temahoz tartozo governance / QA / continuity anchorokat.

## Helyi canonical anchors

1. `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
2. `docs/impactshop-governance-system-plan-2026-06-16.md`
3. `docs/impactshop-governance-hub-coherence-audit-2026-06-16.md`
4. `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`
5. `docs/worktree-coordination-sync.md`
6. `docs/worktree-continuity-guard.md`

## Worktree / continuity minimum

1. `scripts/start-feature-worktree.sh`
2. `scripts/worktree-task-start.sh`
3. `scripts/worktree-readiness-check.sh`
4. `scripts/worktree-task-start-guard.sh`
5. `scripts/worktree-coordination-sync.sh`
6. `scripts/worktree-continuity-guard.sh`

## Cross-repo root pointer

1. `../ai-agent/DOC-SYNC-HUB.md`

## Continuity targets

1. `notes.md`
2. `system-status-snapshot.md`
3. `docs/bastion-guard-status.md`

## Fenntartasi szabaly

Ha uj helyi doc-sync map, worktree helper, vagy continuity guard jelenik meg, ezt a hubot kell elso helyen frissiteni, utana a local mapet, majd a kapcsolt governance / snapshot / notes anchorokat.
