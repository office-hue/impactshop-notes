#!/usr/bin/env python3
"""Real isolated Notes support commands. No host activation or remote access."""
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('notes_support_source', ROOT / 'scripts/workspace-hooks.py')
hooks = importlib.util.module_from_spec(spec)
spec.loader.exec_module(hooks)
BUILDER = ROOT / 'scripts/notes-workspace-support.py'


class Support(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.folder = Path(self.temp.name).resolve()
        self.repo = self.folder / 'old-worktree'
        self.repo.mkdir()
        self.home = self.folder / 'home'
        self.home.mkdir()
        self.env = {key: value for key, value in os.environ.items()
                    if key not in ('GIT_DIR', 'GIT_WORK_TREE', 'GIT_COMMON_DIR', 'GIT_INDEX_FILE',
                                   'BASTION_OVERRIDE', 'BASTION_CHANGE_RECORD', 'BASTION_SMOKE_TAGS',
                                   'BASTION_ROLLBACK_NOTE', 'SAFE_REPO_AUDIT_PUSH_RANGE', 'SAFE_REPO_AUDIT_UPSTREAM')}
        self.env.update(HOME=str(self.home), GIT_CONFIG_GLOBAL=os.devnull, GIT_CONFIG_NOSYSTEM='1',
                        PYTHONDONTWRITEBYTECODE='1', GIT_TERMINAL_PROMPT='0')
        self.git('init', '-b', 'feat/old-worktree')
        self.git('config', 'user.email', 'fixture@example.invalid')
        self.git('config', 'user.name', 'Fixture')
        self.write('docs/impactshop-protected-files.json', json.dumps({
            'protected_globs': ['wp-content/mu-plugins/protected.php'],
            'additive_globs': [], 'smoke_group_globs': {}, 'required_smoke_tags': {}}))
        self.write('wp-content/mu-plugins/protected.php', '<?php // protected fixture\n')
        self.write('readme.md', 'base\n')
        for name in ('wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php',
                     'wp-content/mu-plugins/impactshop-boot.php',
                     'wp-content/mu-plugins/impact-community-impi-source.php',
                     'config/impact-impi-source-authority.json'):
            self.write(name, (ROOT / name).read_text())
        self.git('add', '.')
        self.git('commit', '-m', 'fixture base')
        self.base = self.git('rev-parse', 'HEAD').stdout.strip()
        self.version = hooks.support_version()
        self.support = self.home / '.local/share/office-workspace/targets/impactshop-notes'
        (self.support / 'releases').mkdir(parents=True)
        self.release = self.support / 'releases' / self.version
        self.run_cmd([sys.executable, '-B', str(BUILDER), 'build', '--source', str(ROOT),
                  '--output', str(self.release), '--expect', self.version])
        (self.support / 'current').symlink_to('releases/' + self.version)

    def run_cmd(self, argv, check=True, env=None, input=None):
        result = subprocess.run(argv, cwd=self.repo, env=env or self.env, input=input,
                                capture_output=True, text=True, timeout=30)
        if check:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return result

    def git(self, *args, **kwargs):
        return self.run_cmd(['git', *args], **kwargs)

    def write(self, name, content):
        path = self.repo / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)

    def stage(self, name, content):
        self.write(name, content)
        self.git('add', name)

    def audit(self, *args, **kwargs):
        return self.run_cmd([sys.executable, '-B', str(self.release / 'scripts/workspace-hooks.py'),
                         'audit', '--strict', '--repo', str(self.repo), '--tool-root', str(self.release),
                         '--tool-version', self.version, *args], **kwargs)

    def hook(self, name='pre-commit', **kwargs):
        path = self.folder / ('renamed-' + name)
        path.write_bytes(base64.b64decode(hooks.desired_hook(name)['data']))
        return self.run_cmd(['sh', str(path)], **kwargs)

    def check_status(self, result, checker):
        report = json.loads(result.stdout)
        return next(item for item in report['checks'] if item['name'] == checker)

    def permit(self):
        record = 'docs/protected-change-records/fixture.md'
        self.stage(record, 'Protected files touched: protected.php. Rollback: restore fixture. Smoke: fixture.\n')
        return {**self.env, 'BASTION_OVERRIDE': '1', 'BASTION_CHANGE_RECORD': record,
                'BASTION_ROLLBACK_NOTE': 'restore fixture', 'BASTION_SMOKE_TAGS': 'fixture'}

    def test_builder_is_inactive_and_preserves_four_exact_checker_bytes(self):
        before = (self.support / 'current').readlink()
        report = json.loads(self.run_cmd([sys.executable, '-B', str(BUILDER), 'prepare', '--source', str(ROOT)]).stdout)
        self.assertFalse(report['activated'])
        self.assertFalse(report['authorityGranted'])
        self.assertEqual((self.support / 'current').readlink(), before)
        self.assertEqual(self.git('status', '--porcelain').stdout, '')
        for script, _ in hooks.PRODUCT_GUARDS:
            self.assertEqual((self.release / 'scripts' / script).read_bytes(), (ROOT / 'scripts' / script).read_bytes())
        denied = self.run_cmd([sys.executable, '-B', str(BUILDER), 'build', '--source', str(ROOT),
                           '--output', str(self.support / 'current'), '--expect', self.version], check=False)
        self.assertEqual(denied.returncode, 2)
        denied = self.run_cmd([sys.executable, '-B', str(BUILDER), 'build', '--source', str(ROOT),
                           '--output', str(self.release), '--expect', self.version], check=False)
        self.assertEqual(denied.returncode, 2)

    def test_old_worktree_hook_fallback_checks_staged_secret_without_local_helper(self):
        self.assertFalse((self.repo / 'scripts/workspace-hooks.py').exists())
        self.stage('readme.md', 'OPENAI_API_KEY=' + 'sk-proj-' + 'a' * 60 + '\n')
        result = self.hook(check=False)
        self.assertEqual(result.returncode, 1)
        report = json.loads(result.stdout)
        self.assertTrue(report['findings'])
        self.assertNotIn('sk-proj-', result.stdout + result.stderr)

    def test_secret_precedes_missing_or_malformed_product_manifest(self):
        manifest = 'docs/impactshop-protected-files.json'
        secret = 'sk-proj-' + 'q' * 60
        cases = (None, '{malformed', '[]', '{"protected_globs":"not-a-list"}',
                 '{"protected_globs":[["unhashable"]]}')
        for content in cases:
            with self.subTest(manifest=content):
                self.git('reset', '--hard', self.base)
                if content is None:
                    self.git('rm', manifest)
                    self.git('commit', '-m', 'fixture old ref without product manifest')
                else:
                    self.stage(manifest, content)
                self.stage('readme.md', 'OPENAI_API_KEY=' + secret + '\n')
                direct = self.audit('--mode', 'staged', check=False)
                self.assertEqual(direct.returncode, 1, direct.stdout + direct.stderr)
                report = json.loads(direct.stdout)
                self.assertEqual(report['status'], 'findings')
                self.assertEqual(report['sourceContentStatus'], 'findings')
                self.assertTrue(any(item['kind'] == 'secret-like-content' for item in report['findings']))
                self.assertEqual(self.check_status(direct, 'product-manifest-selection')['status'], 'unavailable')
                native = self.hook(check=False)
                self.assertEqual(native.returncode, 1, native.stdout + native.stderr)
                self.assertNotIn(secret, direct.stdout + direct.stderr + native.stdout + native.stderr)

    def test_missing_product_manifest_without_finding_is_honest_advisory(self):
        self.git('rm', 'docs/impactshop-protected-files.json')
        self.git('commit', '-m', 'fixture old ref without product manifest')
        self.stage('readme.md', 'ordinary source change\n')
        direct = self.audit('--mode', 'staged', check=False)
        self.assertEqual(direct.returncode, 2)
        self.assertEqual(json.loads(direct.stdout)['status'], 'unavailable')
        self.assertEqual(self.check_status(direct, 'product-manifest-selection')['status'], 'unavailable')
        native = self.hook()
        self.assertIn('advisory', native.stderr)
        self.assertNotIn('"status": "checked"', native.stdout)
        self.assertNotIn('"status": "PASS"', native.stdout)

    def test_later_product_input_exception_preserves_real_finding(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // proposed\n')
        secret = 'sk-proj-' + 'r' * 60
        self.stage('readme.md', 'OPENAI_API_KEY=' + secret + '\n')
        record = self.repo / 'docs/protected-change-records/loop'
        record.parent.mkdir(parents=True, exist_ok=True)
        record.symlink_to('loop')
        env = {**self.env, 'BASTION_CHANGE_RECORD': 'docs/protected-change-records/loop'}
        result = self.audit('--mode', 'staged', env=env, check=False)
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertEqual(self.check_status(result, 'check-protected-file-touch.sh')['status'], 'unavailable')
        self.assertTrue(any(item['kind'] == 'secret-like-content' for item in json.loads(result.stdout)['findings']))
        self.assertNotIn(secret, result.stdout + result.stderr)
        self.assertNotIn('Traceback', result.stderr)

    def test_old_worktree_outgoing_secret_scan_exact_range(self):
        self.stage('readme.md', 'OPENAI_API_KEY=' + 'sk-proj-' + 'b' * 60 + '\n')
        self.git('commit', '-m', 'synthetic secret fixture')
        result = self.audit('--mode', 'push', '--base', self.base, '--head', 'HEAD', check=False)
        self.assertEqual(result.returncode, 1)
        self.assertTrue(json.loads(result.stdout)['findings'])

    def test_rendered_prepush_preserves_real_stdin_for_stable_secret_scan(self):
        self.stage('readme.md', 'OPENAI_API_KEY=' + 'sk-proj-' + 'd' * 60 + '\n')
        self.git('commit', '-m', 'synthetic outgoing fixture')
        head = self.git('rev-parse', 'HEAD').stdout.strip()
        url = 'https://github.com/office-hue/impactshop-notes.git'
        self.git('remote', 'add', 'origin', url)
        path = self.folder / 'renamed-native-prepush'
        path.write_bytes(base64.b64decode(hooks.desired_hook('pre-push')['data']))
        result = self.run_cmd(['sh', str(path), 'origin', url], check=False,
                              input=f'refs/heads/feat {head} refs/heads/feat {self.base}\n')
        self.assertEqual(result.returncode, 1)
        self.assertTrue(json.loads(result.stdout)['findings'])

    def test_protected_change_no_permission_fails_and_real_record_passes(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // proposed protected\n')
        denied = self.audit('--mode', 'staged', check=False)
        self.assertEqual(denied.returncode, 1)
        self.assertEqual(self.check_status(denied, 'check-protected-file-touch.sh')['status'], 'failed')
        permitted = self.audit('--mode', 'staged', env=self.permit())
        check = self.check_status(permitted, 'check-protected-file-touch.sh')
        self.assertEqual(check['status'], 'passed')
        self.assertEqual(check['dataRoot'], str(self.repo))
        self.assertEqual(check['checkerRoot'], str(self.release))
        self.assertEqual(json.loads(permitted.stdout)['productPermission'], 'not-evaluated')

    def test_unrelated_foreign_wip_does_not_gate_or_change_index(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // protected proposal\n')
        env = self.permit()
        self.write('scripts/foreign-work.py', 'foreign unfinished source\n')
        self.write('readme.md', 'foreign tracked dirty text\n')
        before = self.git('status', '--porcelain').stdout
        index = (self.repo / '.git/index').read_bytes()
        result = self.audit('--mode', 'staged', env=env)
        self.assertEqual(self.check_status(result, 'check-commit-lane.sh')['status'], 'passed')
        self.assertEqual(before, self.git('status', '--porcelain').stdout)
        self.assertEqual(index, (self.repo / '.git/index').read_bytes())
        self.assertEqual((self.repo / 'scripts/foreign-work.py').read_text(), 'foreign unfinished source\n')

    def test_manifest_working_drift_is_not_used_as_index_permission(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // protected proposal\n')
        self.write('docs/impactshop-protected-files.json', '{"protected_globs": []}')
        result = self.audit('--mode', 'staged', check=False)
        self.assertEqual(result.returncode, 2)
        check = self.check_status(result, 'check-protected-file-touch.sh')
        self.assertEqual(check['status'], 'unavailable')
        self.assertIn('differs from checked index', check['reason'])

    def test_foreign_record_and_symlinked_manifest_are_not_read(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // protected proposal\n')
        env = {**self.permit(), 'BASTION_CHANGE_RECORD': '../foreign-record.md'}
        result = self.audit('--mode', 'staged', env=env, check=False)
        self.assertEqual(result.returncode, 2)
        self.assertIn('worktree-relative', self.check_status(result, 'check-protected-file-touch.sh')['reason'])
        model = self.repo / 'docs/impactshop-protected-files.json'
        outside = self.folder / 'foreign-model.json'
        outside.write_bytes(model.read_bytes())
        model.unlink()
        model.symlink_to(outside)
        result = self.audit('--mode', 'staged', check=False)
        self.assertEqual(result.returncode, 2)
        self.assertIn('symlinked', self.check_status(result, 'check-protected-file-touch.sh')['reason'])

    def test_affiliate_uses_target_not_env_or_package_source(self):
        if not shutil.which('php'):
            self.skipTest('PHP unavailable: actual affiliate checker not claimed')
        name = 'wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php'
        original = (self.repo / name).read_text()
        self.stage(name, original + '\n// target fixture\n')
        result = self.audit('--mode', 'staged')
        self.assertEqual(self.check_status(result, 'sharity-affiliate-runtime-bastion-guard.sh')['status'], 'passed')
        self.stage(name, original.replace('IMPACTSHOP_SHARITY_AFFILIATE_TTL = 900', 'IMPACTSHOP_SHARITY_AFFILIATE_TTL = 1'))
        env = {**self.env, 'AFFILIATE_RUNTIME_SOURCE': str(ROOT / name),
               'AFFILIATE_BOOT_SOURCE': str(ROOT / 'wp-content/mu-plugins/impactshop-boot.php')}
        result = self.audit('--mode', 'staged', env=env, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(self.check_status(result, 'sharity-affiliate-runtime-bastion-guard.sh')['status'], 'failed')
        self.assertFalse((self.release / 'wp-content').exists())

    def test_impi_reads_target_policy_and_unchanged_checker_assertions(self):
        name = 'config/impact-impi-source-authority.json'
        policy = json.loads((self.repo / name).read_text())
        self.stage(name, json.dumps(policy, indent=4) + '\n')
        result = self.audit('--mode', 'staged')
        check = self.check_status(result, 'impact-impi-source-bastion-audit.py')
        self.assertEqual(check['status'], 'passed')
        self.assertTrue(any('notes-product-check.py' in part for part in check['command']))
        policy['publication'] = True
        self.stage(name, json.dumps(policy))
        denied = self.audit('--mode', 'staged', check=False)
        self.assertEqual(denied.returncode, 1)
        self.assertEqual(self.check_status(denied, 'impact-impi-source-bastion-audit.py')['status'], 'failed')

    def test_impi_symlinked_data_and_staged_working_mismatch_unavailable(self):
        name = 'config/impact-impi-source-authority.json'
        original = (self.repo / name).read_text()
        self.stage(name, json.dumps(json.loads(original), indent=4) + '\n')
        self.write(name, original)
        result = self.audit('--mode', 'staged', check=False)
        self.assertEqual(result.returncode, 2)
        self.assertIn('index', self.check_status(result, 'impact-impi-source-bastion-audit.py')['reason'])
        (self.repo / name).unlink()
        (self.repo / name).symlink_to(ROOT / name)
        result = self.audit('--mode', 'staged', check=False)
        self.assertEqual(result.returncode, 2)
        self.assertIn('symlinked', self.check_status(result, 'impact-impi-source-bastion-audit.py')['reason'])

    def test_explicit_repo_survives_git_environment_redirection(self):
        self.stage('readme.md', 'OPENAI_API_KEY=' + 'sk-proj-' + 'c' * 60 + '\n')
        foreign = self.folder / 'foreign'
        foreign.mkdir()
        self.run_cmd(['git', '-C', str(foreign), 'init', '-b', 'feat/foreign'])
        env = {**self.env, 'GIT_DIR': str(foreign / '.git'), 'GIT_WORK_TREE': str(foreign), 'GIT_COMMON_DIR': str(foreign / '.git')}
        result = self.audit('--mode', 'staged', env=env, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(json.loads(result.stdout)['repo'], str(self.repo))

    def test_wrong_version_and_package_drift_are_unavailable_not_permission(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // proposal\n')
        checker = self.release / 'scripts/check-protected-file-touch.sh'
        checker.write_text('#!/bin/sh\nexit 0\n')
        result = self.audit('--mode', 'staged', check=False)
        self.assertEqual(result.returncode, 2)
        self.assertIn('support content drift', result.stderr)
        self.assertNotIn('passed', result.stdout)
        result = self.hook(check=False)
        self.assertEqual(result.returncode, 0)
        self.assertIn('no audit evidence', result.stderr)
        self.assertIn('Product checks', result.stderr)
        self.assertNotIn('"status": "checked"', result.stdout)

    def test_unsafe_package_paths_hardlinks_and_manifest_rejected(self):
        path = self.release / 'scripts/check-commit-lane.sh'
        original = path.read_bytes()
        path.unlink()
        path.symlink_to(ROOT / 'scripts/check-commit-lane.sh')
        with self.assertRaises(ValueError):
            hooks.validate_support(self.release, self.version)
        path.unlink()
        path.write_bytes(original)
        os.link(path, self.folder / 'hardlink')
        with self.assertRaises(ValueError):
            hooks.validate_support(self.release, self.version)
        (self.folder / 'hardlink').unlink()
        path.chmod(0o666)
        with self.assertRaises(ValueError):
            hooks.validate_support(self.release, self.version)
        path.chmod(0o600)
        manifest = self.release / 'manifest.json'
        value = json.loads(manifest.read_text())
        value['kind'] = 'central-empty-product-guards'
        manifest.write_text(json.dumps(value))
        with self.assertRaisesRegex(ValueError, 'not a Notes'):
            hooks.validate_support(self.release, self.version)

    def test_foreign_owner_metadata_is_rejected_without_host_chown(self):
        with mock.patch.object(hooks.os, 'getuid', return_value=os.getuid() + 1):
            with self.assertRaises(ValueError):
                hooks.validate_support(self.release, self.version)

    def test_missing_support_is_advisory_and_local_default_is_not_replaced(self):
        (self.support / 'current').unlink()
        self.stage('readme.md', 'plain source\n')
        result = self.hook()
        self.assertIn('no audit evidence', result.stderr)
        self.assertNotIn('"status": "checked"', result.stdout)
        self.write('scripts/workspace-hooks.py', 'print("local checker selected")\n')
        result = self.hook()
        self.assertEqual(result.stdout.strip(), 'local checker selected')
        self.assertNotIn('stable support', result.stderr)

    def test_rendered_hook_malformed_or_nonobject_manifest_is_advisory(self):
        path = self.release / 'manifest.json'
        for content in ('{malformed', '[]', 'null', '"not-an-object"'):
            with self.subTest(content=content):
                path.write_text(content)
                result = self.hook()
                self.assertIn('no audit evidence', result.stderr)
                self.assertNotIn('Traceback', result.stderr)
                self.assertNotIn('PASS', result.stdout + result.stderr)
                self.assertEqual(result.stdout, '')

    def test_rendered_hook_current_symlink_cycle_is_advisory(self):
        current = self.support / 'current'
        current.unlink()
        current.symlink_to('current')
        result = self.hook()
        self.assertIn('no audit evidence', result.stderr)
        self.assertNotIn('Traceback', result.stderr)
        self.assertNotIn('PASS', result.stdout + result.stderr)
        self.assertEqual(result.stdout, '')

    def test_stable_bootstrap_does_not_import_worktree_shadow_modules(self):
        self.stage('readme.md', 'plain source\n')
        self.write('json.py', 'raise RuntimeError("foreign worktree Python executed")\n')
        result = self.hook()
        self.assertNotIn('Traceback', result.stderr)
        self.assertEqual(json.loads(result.stdout)['sourceContentStatus'], 'checked')

    def test_support_shellguard_python_is_isolated_with_real_protected_permit(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // protected proposal\n')
        env = self.permit()
        marker = self.folder / 'foreign-python-imported'
        self.write('json.py', 'from pathlib import Path\nPath(' + repr(str(marker))
                   + ').write_text("foreign source executed")\nraise RuntimeError("target json imported")\n')
        env.update(PYTHONPATH=str(self.repo), PYTHONHOME=str(self.repo))
        before = self.git('status', '--porcelain').stdout
        index = (self.repo / '.git/index').read_bytes()
        result = self.hook(env=env)
        self.assertFalse(marker.exists(), 'target json.py must never execute in a support checker')
        for checker in ('check-protected-file-touch.sh', 'check-commit-lane.sh'):
            check = self.check_status(result, checker)
            self.assertEqual(check['status'], 'passed')
            self.assertEqual(check['exitCode'], 0)
            self.assertEqual(check['dataRoot'], str(self.repo))
        self.assertEqual(json.loads(result.stdout)['productPermission'], 'not-evaluated')
        self.assertEqual(before, self.git('status', '--porcelain').stdout)
        self.assertEqual(index, (self.repo / '.git/index').read_bytes())
        self.assertNotIn('Traceback', result.stderr)

    def test_tool_root_cannot_be_implicit_or_retargeted_by_selector(self):
        result = self.run_cmd([sys.executable, '-B', str(ROOT / 'scripts/workspace-hooks.py'), 'audit',
                           '--repo', str(self.repo), '--tool-root', str(self.release)], check=False)
        self.assertEqual(result.returncode, 2)
        with self.assertRaisesRegex(ValueError, 'trust binding'):
            hooks.validate_support(self.release, '0' * 64)
        (self.support / 'current').unlink()
        (self.support / 'current').symlink_to(ROOT)
        result = self.hook()
        self.assertIn('outside Notes releases', result.stderr)
        self.assertNotIn('"status": "checked"', result.stdout)

    def test_default_local_audit_still_uses_local_real_product_guards(self):
        self.stage('wp-content/mu-plugins/protected.php', '<?php // proposed\n')
        result = self.run_cmd([sys.executable, '-B', str(ROOT / 'scripts/workspace-hooks.py'), 'audit',
                           '--strict', '--mode', 'staged', '--repo', str(self.repo)], check=False)
        self.assertEqual(result.returncode, 2)
        self.assertEqual(self.check_status(result, 'check-protected-file-touch.sh')['status'], 'unavailable')
        self.assertNotIn('checkerRoot', self.check_status(result, 'check-protected-file-touch.sh'))


if __name__ == '__main__':
    unittest.main()
