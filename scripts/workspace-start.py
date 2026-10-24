#!/usr/bin/env python3
"""Optional native worktree start/resume. No fetch, controller, repin or home writes."""
import argparse
import json
from pathlib import Path
import subprocess

def git(repo, *args):
    result = subprocess.run(["git", "-C", str(repo), *args], capture_output=True, text=True)
    if result.returncode:
        raise ValueError(result.stderr.strip() or "Git operation unavailable")
    return result.stdout.strip()

def main():
    parser = argparse.ArgumentParser(description=__doc__, allow_abbrev=False)
    parser.add_argument("branch")
    parser.add_argument("--resume", action="store_true")
    parser.add_argument("--base", default="HEAD")
    parser.add_argument("--path")
    for name in ("doc-sync-label", "doc-sync-repo-id", "doc-sync-path-prefix",
                 "lifecycle-task-id", "lifecycle-selector", "lifecycle-brief", "plan-id"):
        parser.add_argument("--" + name)  # compatibility only; no authority or marker
    args = parser.parse_args()
    repo = Path(git(".", "rev-parse", "--show-toplevel")).resolve()
    git(repo, "check-ref-format", "--branch", args.branch)
    if args.branch in ("main", "master") or args.branch.startswith("-"):
        raise ValueError("Use a dedicated feature branch.")
    if args.resume:
        raw = git(repo, "worktree", "list", "--porcelain")
        current = None
        for line in raw.splitlines():
            if line.startswith("worktree "):
                current = Path(line[9:]).resolve()
            elif line == "branch refs/heads/" + args.branch:
                print(json.dumps({"status":"resumed", "path":str(current), "branch":args.branch,
                                  "head":git(current, "rev-parse", "HEAD"), "writes":False,
                                  "authorityGranted":False}))
                return
        raise ValueError("Requested branch has no existing worktree; no state changed.")
    base = git(repo, "rev-parse", "--verify", "--end-of-options", args.base + "^{commit}")
    destination = Path(args.path).expanduser().absolute() if args.path else repo.parent / ".worktrees" / (repo.name + "-" + args.branch.replace("/", "-"))
    if destination.exists() or destination.is_symlink():
        raise ValueError("Worktree destination exists; preserved.")
    git(repo, "worktree", "add", "-b", args.branch, str(destination), base)
    print(json.dumps({"status":"created", "path":str(destination), "branch":args.branch,
                      "base":base, "fetch":False, "authorityGranted":False,
                      "note":"No hooks, engine, marker, dependency or shared coordination installed."}))

if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError) as error:
        raise SystemExit(str(error))
