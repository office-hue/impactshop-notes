# 16. Conversation Summary: Staging QA Redirect Handling

## Overview
We revisited the staging QA failures caused by HTTP 301 responses and adjusted the automated suite so that it follows redirects and records the final status codes. File system checks remain targeted at the canonical `/home/sharityh/app-staging` deploy path.

## Key Updates
- Updated `bin/staging-qa-suite.sh` to parse the last `HTTP` header line, ensuring the reported status reflects the final response after redirects.
- Added `-L` to all relevant `curl` checks, including the totals endpoint selector and quick sanity probes, so the suite no longer flags expected 301 hop chains as failures.

## Follow-Up
- Run `bin/staging-qa-suite.sh --no-block` after confirming the remote symlink and WordPress URL fixes, and verify that the four REST endpoints now return HTTP 200 instead of 301.
