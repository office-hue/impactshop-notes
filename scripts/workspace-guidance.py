#!/usr/bin/env python3
"""Read-only compatibility report for retired DEV entrypoints."""
import json
import os
from pathlib import Path
import subprocess
import sys

def report(argv):
    root = os.environ.get("DEV_V4_REPO_ROOT", ".")
    for flag in ("--repo", "--repo-root"):
        if flag in argv and argv.index(flag) + 1 < len(argv):
            root = argv[argv.index(flag) + 1]
    identity = {}
    for key, args in {"repo": ["rev-parse", "--show-toplevel"],
                      "branch": ["branch", "--show-current"],
                      "head": ["rev-parse", "HEAD"]}.items():
        result = subprocess.run(["git", "-C", root, *args], capture_output=True, text=True)
        if result.returncode == 0:
            identity[key] = result.stdout.strip()
    return {"schemaVersion": 1, "status": "retired", "decision": "advisory",
            "authorityGranted": False, "writes": False,
            "sourceMergeAdmission": False, "fullValidationEvidence": False,
            "providerDeployAllowed": False, "sourceChecks": "not-evaluated",
            "operation": argv[0] if argv else "status", "identity": identity,
            "next": "Use native Git and actual product checks. No engine, capsule, selector, model or repin required.",
            "guidance": "docs/dev-retirement-2026-10-24.md"}

if __name__ == "__main__":
    print(json.dumps(report(sys.argv[1:])))
