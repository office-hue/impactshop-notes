# DEV delivery v2 adapter protected change record

Scope: `.github/workflows/ci.yml` and bastion-status documentation only. The
workflow preserves the existing `CI / validate` job and adds a local static
maximum-bastion/fixture check. No WordPress, provider, remote write, deploy,
VPS, Cronos or watchdog surface changes.

Protected files touched: `.github/workflows/ci.yml`,
`docs/bastion-guard-status.md`. Smoke: the unchanged validate-equivalent runs
the maximum-bastion guard and hermetic fixture; no runtime/UI smoke applies.

Risk and rollback: a faulty check can block CI only; revert this checkpoint
commit to restore the previous workflow. The adapter itself has no mutation or
network command path. QA: contract SHA-256, bastion fixture, policy fixture,
shell syntax, candidate-index/checkpoint-tree equality and existing CI suite.
