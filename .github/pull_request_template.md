## Summary

- Scope:
- Why:
- Risk:
- Impact Challenge perimeter touched:
- Additive new-code solution used:
- Legacy/protected-file touch approved by:
- Coherence analysis summary:
- Risk analysis summary:
- Affected functions to verify after merge/deploy:
- Manual UI checklist for reviewer:

## Validation

- [ ] Relevant build/test commands passed
- [ ] `safe-repo-audit.sh --strict --mode push` passed
- [ ] Deploy/smoke verification documented (if applicable)
- [ ] Rollback path documented
- [ ] Impact Challenge guard/write-protection rule checked (if applicable)
- [ ] Protected-file coherence/risk review attached (if applicable)

## PR Exit Checklist (Required)

- [ ] 1. Work was done on dedicated branch/worktree (not `main`)
- [ ] 2. Relevant build/tests ran and are green
- [ ] 3. `safe-repo-audit.sh --strict --mode push` is green
- [ ] 4. `system-status-snapshot.md` updated for module change
- [ ] 5. At least one `docs/*.md` updated for module change
- [ ] 6. `notes.md` or `conversation-summaries/*` updated (notes-context repos)
- [ ] 7. `docs/bastion-guard-status.md` updated when new module file was added
- [ ] 8. Deploy guard + smoke checks are logged (if deploy happened)
- [ ] 9. Backup/rollback path is recorded
- [ ] 10. PR description includes scope, risk, validation, deploy/rollback notes
- [ ] 11. Impact Challenge changes are additive-first, or explicit legacy approval is documented
- [ ] 12. Legacy protected-file touch includes approval + why additive approach was insufficient
- [ ] 13. Deploy notes confirm protected files return to read-only after deploy
- [ ] 14. Protected-file coherence analysis and risk analysis are documented
- [ ] 15. Affected-function verification list is documented
- [ ] 16. Manual UI checklist for reviewer/user is documented
