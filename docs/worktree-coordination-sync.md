# Worktree Coordination Sync

Datum: 2026-09-10
Statusz: multi-active source candidate
Scope: helyi `impactshop-notes` worktree starter es publikacios koordinacio.

## Cel

Ez a helper a helyi `worktree-task-start` lane utan frissiti a repo-szintu
koordinacios snapshotot ugy, hogy:

1. legyen egy reviewer-visible, explicit primary write target;
2. latszodjanak az osszes helyi worktree dirty/clean allapotai;
3. a task-start marker mellett a decision artifact is visszakeresheto legyen;
4. egy uj vagy publikalo worktree regisztracioja ne vegye at hallgatolagosan a
   primary szerepet;
5. a stale vagy prunable worktree-k ne boritsak fel a teljes starter bootstrapot.

## Kanonikus fajlok

- `scripts/worktree-coordination-sync.sh`
- `scripts/worktree-task-start.sh`
- `scripts/worktree-readiness-check.sh`

## Kimenetek

A helper a repository common Git directoryjan beluli privat nevterbe ir:

- `$GIT_COMMON_DIR/office-hue-worktree-coordination/ACTIVE_WORKTREE.md`
- `$GIT_COMMON_DIR/office-hue-worktree-coordination/ACTIVE_WORKTREES.md`

A ket fajl azonos `generation` azonositot kap, ideiglenes fajlbol, lock alatt
kerul a helyere. A continuity guard a generacios paritast kotelezoen ellenorzi,
ezert egy felbeszakadt ketfajlos frissites nem adhat ervenyes publikacios truthot.
A directory `0700`, a ket evidence fajl `0600` modot kap. Emiatt ket, azonos
workspace-ben levo repository nem osztozik pointeren, snapshoton vagy lockon.

## Legacy migracio

A regi workspace-szintu `.worktrees/ACTIVE_WORKTREE.md` csak egyszeri, read-only
migracios forras:

1. ha ugyanahhoz a common Git directoryhoz tartozo ervenyes worktree-re mutat,
   az elso repo-szintu snapshot megorzi primarykent;
2. ha mas repositoryra mutat, hianyos vagy ervenytelen, a helper figyelmen kivul
   hagyja es a regisztralt helyi worktree lesz a kezdeti repo-primary;
3. a regi pointert es a regi `.worktrees/ACTIVE_WORKTREES.md` snapshotot a helper
   soha nem irja es nem torli;
4. amint a repo-szintu pointer letezik, kizarolag az a continuity authority.

## Runtime szabaly

Az `impactshop-notes` helyi starter lane-ben a koordinacios snapshot a marker,
a readiness es a task-start guard utan kotelezo lepes. A starter es a push lane
`--register <worktree>` modot hasznal: ez az aktualis worktree HEAD/dirty/decision
allapotat frissiti, de a letezo ervenyes primary pointert megorzi.

Primary valtas csak explicit `--primary <worktree>` paranccsal tortenhet. A regi
`--active` kapcsolo kompatibilitasi alias, ugyanilyen explicit dontest jelent.
Hianyzo pointer eseten az elso regisztralt worktree lesz a kezdeti primary;
hibas repo-szintu pointert a helper nem ir felul automatikusan.

Jelenlegi fail-closed/fail-open hatar:

1. a marker bootstrap hiba: `blocked`
2. a koordinacios helper hiba: `blocked`
3. egy stale/prunable sibling worktree: nem blocker, hanem snapshot-szintu jelzes
4. parhuzamos sync lock: `blocked`, nincs reszleges feluliras

## Prunable tolerancia

Ha a `git worktree list` matrixban marad egy nem letezo vagy prunable worktree:

1. a snapshot nem all meg;
2. az adott sor `invalid_worktree: yes` jelzest kap;
3. az aggregate output `summary_invalid_worktrees` mezoben osszesiti ezt.

Ezzel a fresh worktree inditas nem bukik el egy regi, mar nem letezo sibling path miatt.

## Decision evidence

A snapshot most mar nem csak a `worktree-active.json` marker metadatajat emeli vissza, hanem a `worktree-task-start-decision.json` artefaktot is.

Reviewer-visible minimum mezok:

1. `task_start_decision_status`
2. `task_start_decision_value`
3. `task_start_decision_path`
4. `task_start_decision_doc_sync_*`
5. blokkolo okok vagy warningok, ha vannak

## Kapcsolat a continuity guarddal

A koordinacios snapshot most mar nem csak informacios output.

Az N4 continuity/guard szeletben a `scripts/worktree-continuity-guard.sh` push
elott explicit azt is ellenorzi, hogy:

1. a primary pointer es a snapshot azonos generaciobol szarmazzon;
2. a primary section egyezzen a pointer branch/full-HEAD truthjaval;
3. a jelenlegi worktree sectionje egyezzen a sajat branch/full-HEAD es clean
   truthjaval;
4. a decision evidence valoban bekerult a repo-szintu riportokba;
5. a task-start dontes ne maradjon csak lokalis JSON-sziget.

Ez repo-local contract: a helper nem keres, nem hiv es nem hasznal sibling
`ai-agent` worktree-t vagy annak dependency/memoria futasidejet.
