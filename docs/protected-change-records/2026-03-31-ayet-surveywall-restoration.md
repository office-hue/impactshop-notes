# 2026-03-31 AyeT Surveywall Restoration

## Summary

This change restores the AyeT survey feed as a separate surveywall source while
keeping the existing AyeT offerwall/game inventory on its own adslot.

## Protected files touched

- `.deploy.production.env`
- `.deploy.staging.env`
- `wp-content/mu-plugins/impactshop-ayet-offerwall.php`
- `wp-content/mu-plugins/impactshop-offerwall.php`
- `wp-content/mu-plugins/impactshop-offerwall.js`

## Risk

- Before this change, the `Kérdőív -> AyeT` surface could fall back to the
  regular AyeT offerwall inventory, which surfaced games instead of surveys.
- The runtime now keeps offerwall and surveywall separated, but this also means
  both adslot configuration and the survey rendering path must stay aligned.
- The change updates protected env files, so a bad rollback could accidentally
  remove the dedicated surveywall slot or profile hash.

## Rollback

- Revert this commit on the `fix/ayet-surveywall-runtime` branch, or restore the
  previous versions of the five touched files from git history.
- If production rollback is needed after deploy, restore the pre-deploy backup
  created by the guarded runtime deploy and remove the dedicated
  `AYET_SURVEYWALL_ADSLOT` / `AYET_SURVEYWALL_PROFILE_HASH` entries from the env
  files.
- After rollback, the expected end state is: AyeT games remain under the
  offerwall tab and the survey tab no longer advertises a separate AyeT
  surveywall source.

## Smoke

- `route:impact-challenge`
- `flow:video-tasks-roundtrip`
- `flow:offerwall-tabs`
- `browser:webkit`
- `browser:chrome`

## Notes

- Offerwall/game inventory stays on adslot `25643`.
- Surveywall questionnaires use adslot `25740`.
- The surveywall profile hash is `b970533bbaf884d085d7c0e6734da1c2`.
- The runtime now exposes separate diagnostics for `ayet_adslot` and
  `ayet_surveywall` in the offerwall health endpoint.
- Review follow-up hardening:
  - `refresh=1` now clears the active survey cache entry too
  - pseudo-related error payloads are consistently returned as `surveys: []`
  - survey refresh is rate-limited per pseudo
  - the frontend no longer re-enables a server-disabled AyeT survey button
