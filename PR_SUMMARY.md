PR: repo cleanup + docs + mu-plugins sync

Summary
- Identity panel + social logging fixes (copy/share + go click event logging).
- Missing MU plugins added under `wp-content/mu-plugins/`.
- Tooling/config and scripts added (bin/, tools/, apps/, types/, .github/, package*.json).
- Knowledge base + docs ingested (docs/, CJ links/, Google Ads/, Impi Tudásbázis/, images).
- Chat history + conversation summaries added.
- Status/snapshot docs refreshed.
- NGO data + User token docs added.

Risk/notes
- Very large PR, includes binaries and many docs.
- Includes potentially sensitive files under `User token/` and `NGO data/`.

Suggested checklist
- [ ] Validate Identity Panel UI on staging/prod (copy/share/restore/nickname).
- [ ] Confirm social ticker share logging + go click logging.
- [ ] Confirm docs and knowledge base files are intended for repo.
