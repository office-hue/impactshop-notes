# Sharity NGO preference Package B — Terra architecture QA

Status: `sol-revision-required`; no Luna implementation is approved.

Reviewed identity: `impactshop-notes` branch
`feat/sharity-ngo-preference-source-authority-terra-20260909`, Sol checkpoint
`b532e2d2f090ec457872bb47e641204122eb22f5`, base
`origin/main@073f2854d4e4bc01ad928636125b7a18dc43efa0`.

## Scope reviewed

- Sol source-authority decision and its planned authorization-code/BFF bridge.
- Current `impactshop_identity_owner_authorized()` owner-grant verifier.
- Current `sharity_ngo_catalog` plus campaign-flag selection model.
- Protected-file model: no runtime or protected file was changed in this QA phase.

## Blocking architecture findings

### QA-B1 — existing same-origin helper is too broad for authorization-code issue

`impactshop_identity_request_same_origin()` allows a fixed cross-host set including
`sharity.hu`, `www.sharity.hu`, `app.sharity.hu`, staging hosts, and the current
home host. That is suitable for its existing profile use, but it does not implement
the exact source-origin requirement of a bearer-session authorization-code issuer.

Package B must not reuse it as its only CSRF/origin control. Sol must decide the
exact canonical issuer origin(s), staging parity, and whether the additive module
uses a new exact-origin helper or a narrowly scoped identity API. The BFF callback
allowlist does not make a browser POST cross-origin-safe.

### QA-B2 — no canonical catalog mapping exists for global preference contexts

The current numeric catalog joins `sharity_ngo_catalog` to
`sharity_ngo_campaign_flags` by `campaign_key`; active/selectable state is therefore
campaign-specific. The Sol decision names four global preference contexts but does
not define their catalog campaign mapping or a new global catalog policy. Reusing
the VB2026 campaign implicitly would couple independent truth sources and violate the
Package A contract.

Sol must choose one explicit model before implementation:

1. an exact, versioned context-to-campaign mapping; or
2. a new global preference catalog policy with its own active/selectable flags and
   revision calculation.

Until then the source cannot calculate a meaningful current catalog revision, reject
unselectable preferences correctly, or supply an auditable no-fallback resolution.

## Positive evidence

- Owner grants are server-side hashed, cookie-bound, revocable, and pseudo-bound.
- The catalog has numeric NGO IDs and campaign-level active/selectable checks.
- The current worktree remains clean outside documentation; `git diff --check`
  passes.

## Required Sol revision

`gpt-5.6-sol`, high reasoning, must amend the architecture decision with the exact
issuer-origin policy and catalog policy/mapping. It must preserve the additive,
default-off, no-provider/no-deploy boundary. Terra will then define the exact Luna
allowlist and test matrix.
