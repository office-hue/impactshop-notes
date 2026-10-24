#!/usr/bin/env python3
"""Explicit target input adapter for the unchanged packaged Impi checker.

The caller validates the Notes support package before invoking this adapter.
Only three data-path globals are rebound; all product assertions are unchanged.
"""
import argparse
import importlib.util
from pathlib import Path
import subprocess
import os


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True)
    args = parser.parse_args()
    root = Path(args.repo)
    environment = {key: value for key, value in os.environ.items()
                   if key not in ('GIT_DIR', 'GIT_WORK_TREE', 'GIT_COMMON_DIR')}
    actual = subprocess.run(['git', '-C', str(root), 'rev-parse', '--show-toplevel'],
                            capture_output=True, text=True, check=True, env=environment)
    if not root.is_absolute() or root != root.resolve() or actual.stdout.strip() != str(root):
        raise ValueError('explicit canonical target worktree required')
    plugin = root / 'wp-content/mu-plugins/impact-community-impi-source.php'
    policy = root / 'config/impact-impi-source-authority.json'
    for path in (plugin, policy):
        if path.resolve() != path or not path.is_file():
            raise ValueError('unsafe/missing target product input')
    checker = Path(__file__).resolve().parent / 'impact-impi-source-bastion-audit.py'
    spec = importlib.util.spec_from_file_location('notes_impi_check', checker)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    module.ROOT, module.PLUGIN, module.POLICY = root, plugin, policy
    return module.main()


if __name__ == '__main__':
    raise SystemExit(main())
