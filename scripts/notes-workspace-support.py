#!/usr/bin/env python3
"""Prepare/export an inactive versioned Notes support release, using stdlib.

No default writes. build needs explicit --source, --output releases/VERSION,
and --expect VERSION from prepare. Never switches current, installs hooks,
edits Git config, downloads code or writes into a target worktree.
"""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
import tempfile


def prepare(source):
    # Never import arbitrary --source code: use this reviewed builder's sibling.
    spec = importlib.util.spec_from_file_location('notes_support_hooks', Path(__file__).resolve().with_name('workspace-hooks.py'))
    hooks = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(hooks)
    if source != source.resolve() or not source.is_dir():
        raise ValueError('canonical explicit source required')
    files = hooks.support_files(source)
    env = hooks.git_environment()
    def git(*args):
        return subprocess.run(['git', '-C', str(source), *args], env=env, capture_output=True, text=True, check=True).stdout.strip()
    report = {'kind': 'impactshop-notes-source-support-v1', 'version': hooks.digest(files), 'files': files,
              'source': str(source), 'sourceHead': git('rev-parse', 'HEAD'), 'sourceTree': git('rev-parse', 'HEAD^{tree}'),
              'sourceDirty': bool(git('status', '--porcelain')), 'activated': False, 'authorityGranted': False}
    return hooks, report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=('prepare', 'build'))
    parser.add_argument('--source', required=True)
    parser.add_argument('--output')
    parser.add_argument('--expect')
    args = parser.parse_args()
    source = Path(args.source)
    hooks, report = prepare(source)
    if args.command == 'prepare':
        if args.output or args.expect:
            raise ValueError('prepare is read-only; output/expect belong to build')
        report['status'] = 'prepared-inactive'
    else:
        if not args.output or args.expect != report['version']:
            raise ValueError('explicit output and matching prepared --expect required')
        output = Path(args.output)
        if (not output.is_absolute() or output.name != report['version'] or output.parent.name != 'releases'
                or output.exists() or output.is_symlink() or output.parent.resolve() != output.parent
                or output.is_relative_to(source)):
            raise ValueError('new inactive canonical releases/VERSION output required, outside source')
        # Parent creation is deliberately not implicit: the operator chooses and
        # reviews the host target separately. Fixtures create their own parent.
        for directory in (output.parent, *output.parent.parents):
            info = directory.lstat()
            if not stat.S_ISDIR(info.st_mode) or info.st_uid not in (0, os.getuid()) or info.st_mode & 0o022:
                if not (info.st_uid == 0 and info.st_mode & stat.S_ISVTX and directory == Path(tempfile.gettempdir()).resolve()):
                    raise ValueError('unsafe output parent')
        if output.parent.stat().st_uid != os.getuid():
            raise ValueError('output parent not owned')
        # Exclusive mkdir cannot replace even a concurrently created empty
        # directory. A partial failed export stays inactive without a manifest.
        output.mkdir(mode=0o700)
        (output / 'scripts').mkdir(mode=0o700)
        for name, sha in report['files'].items():
            data = (source / name).read_bytes()
            if hooks.support_files(source) != report['files'] or hashlib.sha256(data).hexdigest() != sha:
                raise ValueError('source changed since prepare/build readback')
            destination = output / name
            with destination.open('xb') as stream:
                stream.write(data)
            destination.chmod(0o600)
        manifest = output / 'manifest.json'
        with manifest.open('x') as stream:
            stream.write(json.dumps(report, indent=2, sort_keys=True) + '\n')
        manifest.chmod(0o600)
        hooks.validate_support(output, report['version'])
        report.update(status='built-inactive', output=str(output))
    print(json.dumps(report, sort_keys=True))
    return 0


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except (OSError, ValueError, subprocess.SubprocessError) as error:
        print(json.dumps({'status': 'unavailable', 'activated': False, 'reason': str(error)}), file=sys.stderr)
        raise SystemExit(2)
