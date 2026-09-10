# Sharity Profile owner-policy method-map hotfix — Sol decision

Plan ID: `sharity-profile-summary-live-closure-20260910`.

Operator approval: `operator-approval:sharity-profile-owner-policy-method-map-20260910`.

## Maximum bastion source admission

The staging runtime proved that `WP_REST_Server::get_routes()` exposes handler
methods as associative boolean maps such as `POST => true`. Policy v3 accepted
only string and integer method forms, so all registered routes failed the
runtime self-test and the already enabled safe-disable remained active.

The bounded repair validates an array as one complete fail-closed value: it
must be non-empty, every key must be a string, every value must be the boolean
`true`, and the requested method must be an exact key. Indexed, mixed-key,
false-valued and non-boolean maps remain denied. Runtime inventory and the
self-test share this single helper and still require the exact callback.

This source lane uses the adapter's ordinary schema-v1 exact protected change
record. A new schema-v2 source profile is intentionally not introduced: that
would modify the protected adapter/contract that must admit itself and would
expand authority for a one-file compatibility fix. Provider deploy remains
false.

Staging currently has schema v2 in an InnoDB table and the six previously
reviewed CAS blobs, with `impactshop_owner_policy_safe_disable=1`. Production
has not changed. After source publication, staging may receive only the fixed
owner-policy blob through exact-file CAS, followed by the route/callback
self-test. Safe-disable may be cleared only on PASS. No old plaintext-capable
policy rollback is permitted after schema activation; failure stays
forward-safe with safe-disable enabled.

No cron, Cronos guard or watchdog is needed because grant expiry is checked
synchronously and cleanup is request-driven.
