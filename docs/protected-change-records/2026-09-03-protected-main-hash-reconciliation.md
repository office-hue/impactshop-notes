# Protected main hash reconciliation

Date: 2026-09-03

Status: operator-approved release prerequisite

## Protected files touched and provenance

The exact-file UNice CJ release preflight stopped before any staging or
production write because two protected files already present on clean
`origin/main` did not match the older guard hash registry. The operator
explicitly approved reconciling exactly these two current main artifacts:

- `wp-content/mu-plugins/impactshop-boot.php` at SHA-256
  `845d284f8869eb18b131de935e21a27702f8cf324299d519004dc1090f44c67a`,
  introduced by reviewed commit `da343a6a` for the bounded
  `vb2026-autobanner` SAT1 source;
- `.github/workflows/ci.yml` at SHA-256
  `ca2a0a14dc32883cdda0d7bf24512fbb7d5f6c51007f66ea10606aa358251759`,
  introduced by reviewed PR #185 / commit `c20f9c5f` to run the repo-local
  development context policy guard.

The protected files changed by this reconciliation itself are
`docs/impactshop-guard-hashes.json` and `docs/bastion-guard-status.md`.

No runtime, workflow, deploy script, policy or provider behavior is changed by
this reconciliation. The UNice runtime already has its own matching protected
hash and is not re-baselined here.

## Risk, coherence and security review

The package updates only the two stale entries and the registry timestamp.
Both bytes are proven Git history on clean `origin/main`; neither value is
computed from an uncommitted or remote runtime artifact. The review confirms
that the boot change admits only the two named affiliate sources and that the
CI change adds one fail-closed policy check. Broad reinitialization of the
registry is forbidden.

Smoke and four QA views:

1. provenance QA: each new digest equals the exact current `origin/main` file;
2. scope QA: registry diff contains exactly the two approved hash entries and
   timestamp;
3. mutation QA: changing either protected file is still detected after sync;
4. release QA: clean-main preflight and exact-file dry-run must pass before any
   production write.

Rollback is a normal revert of this reconciliation commit. It must not be used
to deploy older runtime bytes; any live rollback continues to use the guarded
exact-file release receipt and CAS path.
