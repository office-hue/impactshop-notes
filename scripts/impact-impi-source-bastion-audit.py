#!/usr/bin/env python3
"""Maximum-bastion checks for the additive Impi source-owner projection."""

from __future__ import annotations

import hashlib
import json
import pathlib
import re
import sys


ROOT = pathlib.Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "wp-content/mu-plugins/impact-community-impi-source.php"
POLICY = ROOT / "config/impact-impi-source-authority.json"


def fail(message: str) -> None:
    print(f"FAIL {message}", file=sys.stderr)
    raise SystemExit(1)


def main() -> int:
    source = PLUGIN.read_text()
    source_sha = hashlib.sha256(source.encode()).hexdigest()
    policy = json.loads(POLICY.read_text())
    if policy.get("plugin_sha256") != source_sha:
        fail("plugin SHA drift")
    if policy["enabled_by_default"] is not False:
        fail("source must be default-off")
    if policy["publication"] is not False or policy["writers"]:
        fail("publication/writers must remain disabled")
    if policy["cron"] is not False:
        fail("source adapter must not add cron")
    if policy["raw_context_retention_days"] != 30:
        fail("retention bound drift")
    if policy["pilot_circles"] != ["Tamási", "Győztesek Egyesülete"]:
        fail("pilot circle drift")
    if policy["pilot_circle_ids"]:
        fail("real pilot IDs must be provisioned separately")
    if policy["max_activities"] != 24 or policy["max_activity_body_bytes"] != 4000:
        fail("context bounds drift")
    required = [
        "IMPACT_IMPI_COMMUNITY_SOURCE_TOKEN",
        "hash_equals",
        "permission_callback",
        "/internal/impi/circles/(?P<circle_id>\\d+)/context",
        "IC_IMPI_SOURCE_MAX_ACTIVITIES = 24",
        "IC_IMPI_SOURCE_MAX_RETENTION_DAYS = 30",
        "is_deleted=0",
    ]
    for marker in required:
        if marker not in source:
            fail(f"missing marker: {marker}")
    if source.count("ic_impi_source_error('context_not_found', 404)") < 3:
        fail("anonymous source disclosure must remain hidden behind 404")
    forbidden = [
        r"\$wpdb->(?:insert|update|delete)\s*\(",
        r"wp_remote_",
        r"IC_IMPI_(?:LEGAL|IMAGE|MARKETING)_",
        r"/publication",
        r"__return_true",
        r"getenv\s*\(",
        r"author_hash",
        r"pid_hash",
        r"register_rest_route\([^;]+['\"]POST",
    ]
    for pattern in forbidden:
        if re.search(pattern, source, re.S):
            fail(f"forbidden source pattern: {pattern}")
    if "Authorization" in source or "Bearer" not in source:
        fail("credential contract is not explicit")
    print("PASS impact-impi-source-bastion-audit")
    print(f"plugin_sha256={source_sha}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
