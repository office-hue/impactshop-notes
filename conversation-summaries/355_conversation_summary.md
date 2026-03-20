# 355

- Prod teszt ledger sorok torolve (partner-demo), partner-demo config/secrets visszavonva.
- Partner kulcsok szetvalasztva: `partner-stg` (staging) es `partner-prod` (production) kulcsok beallitva a `partner.env`-ben.
- Config sorok frissitve: `stg_impact_partner_config` -> partner-stg, `wp_impact_partner_config` -> partner-prod.
- Runner bovitve `--no-sign`, `--invalid-sign`, `PARTNER_CODE` override tamogatassal; fixtures partner_code `partner-stg`.
- Staging smoke: valid futas accepted/duplicate, invalid signature 401.
