# Repository Guidelines

## Project Structure & Module Organization
- Root scripts live under `bin/` (deploy, QA, rollback) and `impactctl` (local helper entrypoint).
- WordPress custom code sits in `wp-content/`: `mu-plugins/` carries always-on guards, diagnostics, and feature toggles (disabled variants end in `.off`), while `plugins/` holds deployable feature bundles.
- Operational notes are captured in `notes.md`, with supporting context in `chatgpt-history/` and `conversation-summaries/`; keep these folders up to date when shipping changes.

## Build, Test, and Development Commands
- `composer install` – fetch PHP tooling (notably `php-cs-fixer`); run after cloning or when `composer.json` changes.
- `./impactctl refresh` – regenerate local Codex context snapshots before large edits.
- `./bin/preflight-run.sh` – dry-run staging readiness checks; review output for blocking regressions.
- `./bin/staging-qa-suite.sh` – end-to-end staging smoke tests; use before handing off or requesting deploy.

## Coding Style & Naming Conventions
- PHP adheres to PSR-12: 4-space indentation, explicit namespaces where applicable, and descriptive function names (e.g., `impactshop_*`).
- Toggle hotfixes by suffixing `.off` rather than deleting files; document the switch in `notes.md`.
- Standardize formatting via `vendor/bin/php-cs-fixer fix --diff` before committing; prefer small, purpose-scoped patches.

## Testing Guidelines
- Automated coverage is limited; rely on the QA shell scripts plus targeted PHP unit checks where available inside plugin directories.
- Mirror production scenarios in staging: validate affiliate redirects, banner rendering, and Dognet API responses.
- Log findings (pass/fail, URLs, timestamps) in `notes.md` to preserve traceability.

## Commit & Pull Request Guidelines
- Follow the existing `scope: summary` pattern (`ops: raise production preflight latency fail to 10s`); keep subjects ≤72 chars.
- Explain intent, rollout plan, and rollback instructions in PR descriptions; link related diagnostics or `notes.md` entries.
- Attach screenshots or staging logs when visual or redirect behaviour changes; flag any manual steps required post-deploy.

## Agent Notes & Handover
- Start and end sessions by updating `notes.md` with what changed and outstanding risks.
- Store distilled ChatGPT takeaways in `conversation-summaries/` to help future agents ramp quickly.
- Use the Hungarian localisation already present when communicating with stakeholders in shared docs.
