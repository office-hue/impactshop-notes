# Protect Critical Files — Tamper-Proof PR Guard

This repository blocks pull requests that modify files listed in `.github/protected-files.txt`.
The workflow runs with `pull_request_target` on the trusted base branch and loads the protection
list from the base. Missing or empty list -> fail-closed.

## Smoke test
```bash
git checkout main && git pull
git checkout -b test/protection-smoke-test
echo "# test" >> bin/impactctl
git add bin/impactctl
git commit -m "test: attempt to modify protected file"
git push -u origin test/protection-smoke-test
# Open PR → CI should fail with a list of protected files touched.
```
