# 353

- Implementaltam a non-affiliate partner API-t mu-plugin formaban: `wp-content/mu-plugins/impactshop-partner-api.php`.
- Letrejott a `wp_impact_partner_tx` es `wp_impact_partner_config` tabla dbDelta-vel (auth + idempotency + HMAC + timestamp ellenorzes, ledger insert/refund reject, audit log).
- OpenAPI staging URL es partner runner base URL canonical hostra igazitas, fixture pseudo_id minta javitas.
- Deploy staging + production scan-nel lefutott (`deploy-20260201-160643`, `deploy-20260201-160730`).
- Megjegyzes: a partner endpointokhoz aktiv config + secrets (IMPACT_PARTNER_SECRETS, config tabla) szuksegesek.
