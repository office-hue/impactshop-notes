#!/usr/bin/env python3
"""Isolated Notes retirement fixtures. No host, provider, SSH or real repository writes."""
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("notes_hooks", ROOT / "scripts/workspace-hooks.py")
hooks = importlib.util.module_from_spec(spec)
spec.loader.exec_module(hooks)

class Retirement(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.repo = Path(self.temp.name).resolve() / "repo"
        self.repo.mkdir()
        home = Path(self.temp.name) / "home"
        home.mkdir()
        self.env = {**os.environ, "HOME":str(home), "GIT_CONFIG_GLOBAL":os.devnull, "GIT_CONFIG_NOSYSTEM":"1",
                    "PYTHONDONTWRITEBYTECODE":"1"}
        for key in list(self.env):
            if key in {"GIT_DIR", "GIT_WORK_TREE", "GIT_INDEX_FILE"}:
                del self.env[key]
        self.run_cmd(["git", "init", "-b", "feat/test"])
        self.git("config", "user.email", "fixture@example.invalid")
        self.git("config", "user.name", "Fixture")
        (self.repo / "scripts").mkdir()
        for name in ("workspace-hooks.py", "workspace-guidance.py", "workspace-start.py",
                     "check-protected-file-touch.sh", "check-commit-lane.sh",
                     "guarded-push.sh",
                     "dev-delivery-v2-adapter.sh", "worktree-continuity-guard.sh"):
            shutil.copyfile(ROOT / "scripts" / name, self.repo / "scripts" / name)
        (self.repo / "docs").mkdir()
        (self.repo / "docs/impactshop-protected-files.json").write_text(json.dumps({
            "protected_globs":["wp-content/mu-plugins/protected.php"],
            "additive_globs":["wp-content/mu-plugins/new-*.php"],
            "smoke_group_globs":{}, "required_smoke_tags":{}}))
        (self.repo / "readme.md").write_text("fixture\n")
        self.commit("fixture base")
        self.base = self.git("rev-parse", "HEAD").stdout.strip()

    def run_cmd(self, argv, check=True, env=None, input=None):
        result = subprocess.run(argv, cwd=self.repo, env=env or self.env, input=input,
                                text=True, capture_output=True, timeout=20)
        if check:
            self.assertEqual(result.returncode, 0, result.stderr + result.stdout)
        return result

    def git(self, *args, **kw):
        return self.run_cmd(["git", *args], **kw)

    def commit(self, message):
        self.git("add", ".")
        return self.git("commit", "-m", message)

    def call(self, *args, **kw):
        return self.run_cmd([sys.executable, "-B", str(self.repo / "scripts/workspace-hooks.py"), *args], **kw)

    def install(self):
        prepared = json.loads(self.call("install", "--repo", str(self.repo)).stdout)
        result = self.call("install", "--repo", str(self.repo), "--apply", "--expect", prepared["beforeDigest"])
        return json.loads(result.stdout)

    def test_native_git_commit_without_engine_or_hook(self):
        (self.repo / "readme.md").write_text("normal source\n")
        self.commit("native feature commit")
        self.assertFalse((self.repo / ".git/dev-v4-lifecycle").exists())

    def test_compatibility_reports_retired_without_reading_unsafe_capsule(self):
        marker = self.repo / ".git/worktree-active.json"
        marker.write_text("malformed former capsule")
        before = marker.read_bytes()
        for script in ("dev-delivery-v2-adapter.sh", "worktree-continuity-guard.sh"):
            result = self.run_cmd(["bash", str(self.repo / "scripts" / script), "full-validate", "--json"])
            value = json.loads(result.stdout)
            self.assertEqual(value["status"], "retired")
            self.assertFalse(value["authorityGranted"])
            self.assertFalse(value["fullValidationEvidence"])
            self.assertEqual(value["sourceChecks"], "not-evaluated")
        self.assertEqual(marker.read_bytes(), before)

    def test_preparation_does_not_install_and_custom_hook_is_preserved(self):
        folder = self.repo / ".git/hooks"
        custom = folder / "pre-commit"
        custom.write_text("#!/bin/sh\n# custom owner content\nexit 0\n")
        custom.chmod(0o755)
        before = custom.read_bytes()
        report = json.loads(self.call("install", "--repo", str(self.repo)).stdout)
        self.assertEqual(report["status"], "prepared")
        self.assertIn("pre-commit", report["preservedCustomHooks"])
        self.assertEqual(custom.read_bytes(), before)
        self.assertFalse((folder / ".workspace-hook-backups").exists())

    def test_install_and_rollback_restore_exact_owned_prestate(self):
        report = self.install()
        folder = self.repo / ".git/hooks"
        self.assertTrue((folder / "pre-commit").exists())
        self.call("install", "--repo", str(self.repo), "--rollback", report["backup"])
        self.assertFalse((folder / "pre-commit").exists())
        self.assertFalse((folder / "pre-push").exists())

    def test_tracked_hooks_have_private_backup_no_publishable_bytes_and_exact_rollback(self):
        folder = self.repo / ".githooks"
        folder.mkdir()
        custom = folder / "pre-commit"
        body = b"#!/bin/sh\n# private owner hook content\nexit 0\n"
        custom.write_bytes(body)
        custom.chmod(0o751)
        self.commit("tracked custom hook fixture")
        self.git("config", "core.hooksPath", ".githooks")
        sha = hashlib.sha256(body).hexdigest()
        prepared_raw = self.call("install", "--repo", str(self.repo), "--replace", "pre-commit=" + sha).stdout
        prepared = json.loads(prepared_raw)
        applied_raw = self.call("install", "--repo", str(self.repo), "--apply", "--expect",
                                prepared["beforeDigest"], "--replace", "pre-commit=" + sha).stdout
        applied = json.loads(applied_raw)
        backup = Path(applied["backup"])
        self.assertEqual(backup.parent, self.repo / ".git/workspace-hook-backups")
        self.assertEqual(backup.stat().st_mode & 0o777, 0o700)
        self.assertEqual((backup / "manifest.json").stat().st_mode & 0o777, 0o600)
        for output in (prepared_raw, applied_raw):
            self.assertNotIn("before", json.loads(output))
            self.assertNotIn("after", json.loads(output))
            self.assertNotIn(body.decode(), output)
            self.assertNotIn(base64.b64encode(body).decode(), output)
        self.assertFalse((folder / ".workspace-hook-backups").exists())
        untracked = self.git("ls-files", "--others", "--exclude-standard").stdout.splitlines()
        self.assertFalse(any("backup" in path or "manifest.json" in path for path in untracked))
        self.call("install", "--repo", str(self.repo), "--rollback", str(backup))
        self.assertEqual(custom.read_bytes(), body)
        self.assertEqual(custom.stat().st_mode & 0o777, 0o751)
        self.assertEqual(self.git("status", "--porcelain").stdout, "")

    def test_rollback_resumes_partial_own_restore_and_rejects_hooks_path_drift(self):
        installed = self.install()
        backup = Path(installed["backup"])
        # Interrupted rollback: one exact original field is already restored.
        (self.repo / ".git/hooks/pre-commit").unlink()
        self.call("install", "--repo", str(self.repo), "--rollback", str(backup))
        self.call("install", "--repo", str(self.repo), "--rollback", str(backup))
        self.assertFalse((self.repo / ".git/hooks/pre-push").exists())
        installed = self.install()
        # The same resolved directory with a different literal config is drift.
        self.git("config", "core.hooksPath", ".git/hooks")
        before = (self.repo / ".git/hooks/pre-commit").read_bytes()
        denied = self.call("install", "--repo", str(self.repo), "--rollback", installed["backup"], check=False)
        self.assertEqual(denied.returncode, 2)
        self.assertIn("identity/configuration drift", denied.stderr)
        self.assertEqual((self.repo / ".git/hooks/pre-commit").read_bytes(), before)

    def test_private_backup_path_rejects_symlink_and_unchanged_hook_drift(self):
        foreign = Path(self.temp.name) / "foreign"
        foreign.mkdir()
        root = self.repo / ".git/workspace-hook-backups"
        root.symlink_to(foreign, target_is_directory=True)
        prepared = json.loads(self.call("install", "--repo", str(self.repo)).stdout)
        denied = self.call("install", "--repo", str(self.repo), "--apply", "--expect", prepared["beforeDigest"], check=False)
        self.assertEqual(denied.returncode, 2)
        self.assertEqual(list(foreign.iterdir()), [])
        self.assertFalse((self.repo / ".git/hooks/pre-commit").exists())
        root.unlink()
        unchanged = self.repo / ".git/hooks/post-commit"
        unchanged.write_text("owner hook\n")
        installed = self.install()
        unchanged.write_text("owner later hook\n")
        denied = self.call("install", "--repo", str(self.repo), "--rollback", installed["backup"], check=False)
        self.assertEqual(denied.returncode, 2)
        self.assertEqual(unchanged.read_text(), "owner later hook\n")
        self.assertTrue((self.repo / ".git/hooks/pre-commit").exists())

    def test_install_and_rollback_reject_concurrent_changes(self):
        prepared = json.loads(self.call("install", "--repo", str(self.repo)).stdout)
        file = self.repo / ".git/hooks/pre-commit"
        file.write_text("owner concurrent content\n")
        denied = self.call("install", "--repo", str(self.repo), "--apply", "--expect", prepared["beforeDigest"], check=False)
        self.assertEqual(denied.returncode, 2)
        self.assertEqual(file.read_text(), "owner concurrent content\n")
        file.unlink()
        installed = self.install()
        file.write_text("owner later content\n")
        denied = self.call("install", "--repo", str(self.repo), "--rollback", installed["backup"], check=False)
        self.assertEqual(denied.returncode, 2)
        self.assertEqual(file.read_text(), "owner later content\n")

    def test_external_hooks_path_is_not_written(self):
        external = Path(self.temp.name) / "foreign-hooks"
        external.mkdir()
        self.git("config", "core.hooksPath", str(external))
        prepared = json.loads(self.call("install", "--repo", str(self.repo)).stdout)
        denied = self.call("install", "--repo", str(self.repo), "--apply", "--expect", prepared["beforeDigest"], check=False)
        self.assertEqual(denied.returncode, 2)
        self.assertEqual(list(external.iterdir()), [])

    def test_installed_native_hook_blocks_secret_without_printing_value(self):
        self.install()
        value = "ghp_" + "A" * 36
        (self.repo / "secret-added.txt").write_text(value + "\n")
        self.git("add", "secret-added.txt")
        result = self.git("commit", "-m", "secret denied", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn(value, result.stdout + result.stderr)
        self.assertIn("secret-like-content", result.stdout + result.stderr)

    def test_optional_checker_missing_does_not_block_native_git_or_claim_pass(self):
        self.install()
        (self.repo / "scripts/workspace-hooks.py").rename(self.repo / "scripts/absent.py")
        (self.repo / "readme.md").write_text("source\n")
        self.git("add", "readme.md")
        result = self.git("commit", "-m", "without optional checker")
        self.assertIn("no audit evidence", result.stderr)
        self.assertNotIn("PASS", result.stdout + result.stderr)

    def test_symlinked_optional_checker_never_executes_foreign_source(self):
        self.install()
        target = self.repo / "scripts/workspace-hooks.py"
        target.unlink()
        foreign = Path(self.temp.name) / "foreign.py"
        sentinel = Path(self.temp.name) / "executed.txt"
        foreign.write_text("from pathlib import Path\nPath(" + repr(str(sentinel)) + ").write_text('executed')\n")
        target.symlink_to(foreign)
        (self.repo / "readme.md").write_text("source\n")
        self.git("add", "readme.md")
        result = self.git("commit", "-m", "unsafe helper denied", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(sentinel.exists())

    def test_index_content_checked_without_staging_foreign_wip(self):
        self.install()
        (self.repo / "readme.md").write_text("staged safe\n")
        self.git("add", "readme.md")
        value = "ghp_" + "B" * 36
        foreign = self.repo / "foreign.txt"
        foreign.write_text(value)
        before = foreign.read_bytes()
        self.git("commit", "-m", "only own index")
        self.assertEqual(foreign.read_bytes(), before)
        self.assertNotIn("foreign.txt", self.git("show", "--format=", "--name-only", "HEAD").stdout)

    def test_secret_introduced_then_removed_is_found_in_outgoing_history(self):
        value = "ghp_" + "C" * 36
        file = self.repo / "temporary.txt"
        file.write_text(value + "\n")
        self.commit("intermediate")
        file.unlink()
        self.commit("remove")
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push", "--base", self.base, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertIn("secret-like-content", result.stdout)
        self.assertNotIn(value, result.stdout + result.stderr)

    def test_protected_product_touch_still_denied_without_authority(self):
        self.install()
        file = self.repo / "wp-content/mu-plugins/protected.php"
        file.parent.mkdir(parents=True)
        file.write_text("<?php echo 1;\n")
        self.git("add", str(file))
        result = self.git("commit", "-m", "unauthorized product", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('"name": "check-protected-file-touch.sh"', result.stdout + result.stderr)

    def test_protected_product_record_does_not_grant_deploy(self):
        file = self.repo / "wp-content/mu-plugins/protected.php"
        file.parent.mkdir(parents=True)
        file.write_text("<?php echo 1;\n")
        record = self.repo / "docs/change.md"
        record.write_text("Protected files touched: protected.php\nRollback: restore fixture\nSmoke: local fixture\n")
        self.git("add", str(file), str(record))
        env = {**self.env, "BASTION_OVERRIDE":"1", "BASTION_CHANGE_RECORD":"docs/change.md",
               "BASTION_ROLLBACK_NOTE":"restore fixture", "BASTION_SMOKE_TAGS":"local"}
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "staged", env=env)
        report = json.loads(result.stdout)
        self.assertFalse(report["findings"])
        self.assertNotIn("authorityGranted", report)

    def test_native_start_resume_no_network_markers_or_repin(self):
        destination = Path(self.temp.name) / "feature"
        start = self.run_cmd([sys.executable, "-B", str(self.repo / "scripts/workspace-start.py"),
                              "feat/next", "--path", str(destination)])
        self.assertFalse(json.loads(start.stdout)["fetch"])
        self.assertFalse((destination / ".git/worktree-active.json").exists())
        resume = self.run_cmd([sys.executable, "-B", str(self.repo / "scripts/workspace-start.py"), "feat/next", "--resume"])
        self.assertEqual(json.loads(resume.stdout)["path"], str(destination.resolve()))

    def test_protected_push_target_is_a_real_finding_not_optional_advice(self):
        self.install()
        sha = self.git("rev-parse", "HEAD").stdout.strip()
        row = f"refs/heads/feat/test {sha} refs/heads/main {'0' * 40}\n"
        result = self.call("audit", "--repo", str(self.repo), "--mode", "push",
                           "--strict", "--optional-hook", "--stdin", input=row, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertIn("protected main/master", result.stderr)

    def test_native_push_hook_rejects_foreign_transport_without_network(self):
        self.install()
        self.git("remote", "add", "origin", "https://github.com/office-hue/impactshop-notes.git")
        hook = self.repo / ".git/hooks/pre-push"
        result = self.run_cmd(["sh", str(hook), "origin", "https://example.invalid/foreign.git"], check=False)
        self.assertEqual(result.returncode, 1)
        self.assertIn("unexpected Notes push transport", result.stderr)

    def test_product_guard_patterns_match_actual_runtime_inputs(self):
        mapping = dict(hooks.PRODUCT_GUARDS)
        self.assertTrue(re.search(mapping["sharity-affiliate-runtime-bastion-guard.sh"],
                                  "wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php"))
        self.assertTrue(re.search(mapping["impact-impi-source-bastion-audit.py"],
                                  "wp-content/mu-plugins/impact-community-impi-source.php"))
        self.assertFalse(re.search(mapping["sharity-affiliate-runtime-bastion-guard.sh"],
                                   "docs/unrelated.md"))
        self.assertFalse(re.search(hooks.PRODUCT_SCOPE, "scripts/workspace-hooks.py"))
        self.assertFalse(re.search(hooks.PRODUCT_SCOPE, "docs/pr-policy.md"))
        self.assertTrue(re.search(hooks.PRODUCT_SCOPE, "docs/impactshop-protected-files.json"))

    def test_actual_domain_and_deploy_sources_are_not_retired(self):
        for file in ("bin/impactshop-guard-deploy.sh", "scripts/hatas-korok-load-memory.sh",
                     "scripts/sharity-affiliate-runtime-bastion-guard.sh", ".github/workflows/codex-bridge.yml"):
            old = subprocess.check_output(["git", "-C", str(ROOT), "show",
                                          "bbf2345416fe4aa2f4b49c8f02e57fa4ceb1a77c:" + file])
            self.assertEqual(old, (ROOT / file).read_bytes(), file)

    def test_secret_lexical_boundaries_and_real_literals(self):
        harmless = b"legal-ask-faithfulness-bastion-example\nvalue = '[REDACTED_TOKEN]'\n"
        self.assertEqual(hooks.secret_findings("note.md", harmless), [])
        self.assertEqual(hooks.secret_findings("test.py", b"fixture = 'API_KEY=' + self.token()"), [])
        mixed = b'{token: "[REDACTED_TOKEN]", FIELD: "actual-long-literal-value"}'.replace(b"FIELD", b"password")
        self.assertTrue(hooks.secret_findings("source.js", mixed))
        for content in (("sk-" + "proj-" + "A" * 30).encode(),
                        b'const password = "actual-long-literal-value";',
                        b'OPENAI_API_KEY="actual-long-literal-value"'):
            self.assertTrue(hooks.secret_findings(".env", content))

    def prepare_local_push(self):
        """Only disposable local bare transports; no GitHub/provider contact."""
        remote = Path(self.temp.name).resolve() / "remote.git"
        self.run_cmd(["git", "init", "--bare", str(remote)])
        self.run_cmd(["git", "--git-dir", str(remote), "config", "receive.advertisePushOptions", "true"])
        for name in ("workspace-hooks.py", "guarded-push.sh"):
            file = self.repo / "scripts" / name
            source = file.read_text()
            source = source.replace("https://github.com/office-hue/impactshop-notes.git", str(remote))
            source = source.replace("git@github.com:office-hue/impactshop-notes.git", str(remote))
            file.write_text(source)
        self.commit("local transport fixture only")
        self.git("remote", "add", "origin", str(remote))
        self.git("push", "origin", "HEAD:refs/heads/main")
        return remote

    def push(self, *args, **kwargs):
        return self.run_cmd(["bash", "scripts/guarded-push.sh", *args], **kwargs)

    def test_guarded_push_uses_actual_refs_blocks_main_and_no_verify(self):
        remote = self.prepare_local_push()
        before = self.git("rev-parse", "HEAD").stdout.strip()
        (self.repo / "readme.md").write_text("actual outgoing update\n")
        self.commit("actual outgoing update")
        for destination in ("main", "master"):
            result = self.push("origin", "HEAD:refs/heads/" + destination, check=False)
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("protected main/master", result.stderr)
        for option in ("--no-verify", "--no-ver"):
            result = self.push("origin", "HEAD:refs/heads/bypass", option, check=False)
            self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.run_cmd(["git", "--git-dir", str(remote), "rev-parse", "refs/heads/main"]).stdout.strip(), before)
        self.assertEqual(self.run_cmd(["git", "--git-dir", str(remote), "for-each-ref", "--format=%(refname)"]).stdout.strip(), "refs/heads/main")

    def test_all_origin_push_urls_are_checked_before_any_push_transport(self):
        remote = self.prepare_local_push()
        foreign = Path(self.temp.name).resolve() / "foreign.git"
        self.run_cmd(["git", "init", "--bare", str(foreign)])
        self.git("config", "--add", "remote.origin.pushurl", str(remote))
        self.git("config", "--add", "remote.origin.pushurl", str(foreign))
        shim = Path(self.temp.name) / "shim"
        shim.mkdir()
        marker = Path(self.temp.name) / "push-was-invoked"
        real_git = shutil.which("git")
        command = shim / "git"
        command.write_text("#!/bin/sh\nfor arg do\nif [ \"$arg\" = push ]; then touch " + repr(str(marker)) + "; exit 87; fi\ndone\nexec " + repr(real_git) + " \"$@\"\n")
        command.chmod(0o755)
        env = {**self.env, "PATH": str(shim) + os.pathsep + self.env["PATH"]}
        for helper_missing in (False, True):
            if helper_missing:
                (self.repo / "scripts/workspace-hooks.py").unlink()
            denied = self.push("origin", "HEAD:refs/heads/review", check=False, env=env)
            self.assertNotEqual(denied.returncode, 0)
            self.assertIn("no transport contacted", denied.stderr)
            self.assertFalse(marker.exists())
        # The Python helper also validates all URLs independently of the shell.
        shutil.copyfile(ROOT / "scripts/workspace-hooks.py", self.repo / "scripts/workspace-hooks.py")
        source = (self.repo / "scripts/workspace-hooks.py").read_text().replace("https://github.com/office-hue/impactshop-notes.git", str(remote)).replace("git@github.com:office-hue/impactshop-notes.git", str(remote))
        (self.repo / "scripts/workspace-hooks.py").write_text(source)
        denied = self.call("push", "origin", "HEAD:refs/heads/review", check=False, env=env)
        self.assertNotEqual(denied.returncode, 0)
        self.assertIn("no transport contacted", denied.stderr)
        self.assertFalse(marker.exists())

    def test_renamed_rendered_pre_push_still_checks_actual_foreign_target(self):
        self.prepare_local_push()
        rendered = json.loads(self.call("render").stdout)["changes"]["pre-push"]
        renamed = Path(self.temp.name) / "notes-pre-push-renamed"
        renamed.write_bytes(base64.b64decode(rendered["bytes"]))
        # Filename is irrelevant: render-time hook kind determines protections.
        denied = self.run_cmd(["sh", str(renamed), "origin", "/local/foreign-target.git"], check=False)
        self.assertEqual(denied.returncode, 1)
        self.assertIn("unexpected Notes push transport", denied.stderr)
        self.assertNotIn('"kind": "source-audit"', denied.stdout)

    def test_guarded_push_scans_second_noncurrent_ref_and_chains_custom_hook(self):
        remote = self.prepare_local_push()
        self.git("checkout", "-b", "other")
        value = "ghp_" + "D" * 36
        (self.repo / "second-ref.txt").write_text(value + "\n")
        self.commit("second ref secret")
        other = self.git("rev-parse", "HEAD").stdout.strip()
        self.git("checkout", "feat/test")
        marker = Path(self.temp.name) / "custom-input"
        custom = self.repo / ".git/hooks/pre-push"
        custom.write_text("#!/bin/sh\ncat > " + repr(str(marker)) + "\n")
        custom.chmod(0o755)
        original = custom.read_bytes()
        config = self.git("config", "--local", "--list").stdout
        result = self.push("origin", "HEAD:refs/heads/review", "other:refs/heads/other", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("secret-like-content", result.stdout + result.stderr)
        self.assertNotIn(value, result.stdout + result.stderr)
        self.assertIn(other, marker.read_text())
        self.assertEqual(len(marker.read_text().splitlines()), 2)
        self.assertEqual(result.stdout.count('"name": "remote-outgoing-boundary"'), 1)
        self.assertEqual(custom.read_bytes(), original)
        self.assertEqual(self.git("config", "--local", "--list").stdout, config)
        self.assertEqual(self.run_cmd(["git", "--git-dir", str(remote), "for-each-ref", "--format=%(refname)"]).stdout.strip(), "refs/heads/main")

    def test_guarded_push_rejects_other_destination_rewrites_and_force_recovery(self):
        remote = self.prepare_local_push()
        foreign = Path(self.temp.name) / "never-contact.git"
        self.git("remote", "add", "other", str(foreign))
        for target in ("other", str(foreign)):
            result = self.push(target, "HEAD:refs/heads/review", check=False)
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("no transport contacted", result.stderr)
        self.git("config", "url." + str(foreign) + ".pushInsteadOf", str(remote))
        result = self.push("origin", "HEAD:refs/heads/review", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("Unexpected Notes origin", result.stderr)
        self.git("config", "--unset-all", "url." + str(foreign) + ".pushInsteadOf")
        (self.repo / "readme.md").write_text("remote descendant\n")
        self.commit("remote descendant")
        self.git("push", "origin", "HEAD:refs/heads/existing")
        self.git("checkout", "HEAD^")
        self.git("checkout", "-b", "feat/divergent")
        (self.repo / "readme.md").write_text("divergent source\n")
        self.commit("divergent")
        result = self.push("--force", "origin", "HEAD:refs/heads/existing", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("non-fast-forward", result.stderr)

    def test_guarded_push_success_custom_failure_and_missing_optional_checker(self):
        self.prepare_local_push()
        (self.repo / "readme.md").write_text("safe new source\n")
        self.commit("safe new source")
        result = self.push("--dry-run", "-o", "--no-verify", "origin", "HEAD:refs/heads/review")
        self.assertIn('"name": "remote-outgoing-boundary", "status": "verified"', result.stdout)
        custom = self.repo / ".git/hooks/pre-push"
        custom.write_text("#!/bin/sh\nexit 17\n")
        custom.chmod(0o755)
        result = self.push("origin", "HEAD:refs/heads/review", check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('"kind": "source-audit"', result.stdout)
        custom.unlink()
        (self.repo / "scripts/workspace-hooks.py").unlink()
        result = self.push("--dry-run", "origin", "HEAD:refs/heads/review")
        self.assertIn("No audit evidence", result.stderr)
        self.assertNotIn("PASS", result.stdout + result.stderr)
        denied = self.push("origin", "HEAD:refs/heads/main", check=False)
        self.assertNotEqual(denied.returncode, 0)
        self.assertIn("protected main/master", denied.stderr)

    def test_new_ref_boundary_excludes_remote_history_not_new_secret(self):
        value = "ghp_" + "E" * 36
        file = self.repo / "historical.txt"
        file.write_text(value + "\n")
        self.commit("historical remote content")
        file.unlink()
        self.commit("remove historical content")
        remote = self.prepare_local_push()
        (self.repo / "readme.md").write_text("safe delta\n")
        self.commit("safe delta")
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push", "--remote-url", str(remote))
        report = json.loads(result.stdout)
        self.assertFalse(report["findings"])
        self.assertTrue(report["scope"][0]["excludedRemoteTips"])
        explicit = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push", "--all-history", check=False)
        self.assertEqual(explicit.returncode, 1)
        missing = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push", check=False)
        self.assertEqual(missing.returncode, 2)
        self.assertEqual(json.loads(missing.stdout)["status"], "unavailable")
        new_value = "ghp_" + "F" * 36
        (self.repo / "new-secret.txt").write_text(new_value + "\n")
        self.commit("new outgoing secret")
        denied = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push", "--remote-url", str(remote), check=False)
        self.assertEqual(denied.returncode, 1)
        self.assertNotIn(new_value, denied.stdout + denied.stderr)

    def test_manifest_protected_deploy_source_not_in_old_partial_map_invokes_guard(self):
        model = self.repo / "docs/impactshop-protected-files.json"
        value = json.loads(model.read_text())
        path = "scripts/impactshop-exact-release-remote.py"
        value["protected_globs"].append(path)
        model.write_text(json.dumps(value))
        self.commit("manifest includes actual deploy contract")
        (self.repo / path).write_text("# changed real release contract\n")
        self.git("add", path)
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "staged", check=False)
        report = json.loads(result.stdout)
        self.assertEqual(result.returncode, 1)
        self.assertTrue(any(item["name"] == "check-protected-file-touch.sh" and item["status"] == "failed" for item in report["checks"]))
        # Removing the staged glob cannot remove guard selection from base.
        value["protected_globs"].remove(path)
        model.write_text(json.dumps(value))
        self.git("add", str(model))
        self.assertIn(path, hooks.product_paths(self.repo, {path}, "staged"))
        actual = {"scripts/impactshop-exact-release-remote.py", "docs/impactshop-deploy.md",
                  "tests/impactshop-exact-release-remote.test.sh"}
        self.assertEqual(hooks.product_paths(ROOT, actual, "local"), actual)

    def test_explicit_ci_content_only_is_not_product_permission_and_default_keeps_guards(self):
        path = self.repo / "wp-content/mu-plugins/protected.php"
        path.parent.mkdir(parents=True)
        path.write_text("<?php // protected source fixture\n")
        self.git("add", str(path))
        denied = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "staged", check=False)
        self.assertEqual(denied.returncode, 1)
        self.assertTrue(any(item["name"] == "check-protected-file-touch.sh" and item["status"] == "failed"
                            for item in json.loads(denied.stdout)["checks"]))
        report = json.loads(self.call("audit", "--repo", str(self.repo), "--strict", "--content-only", "--mode", "staged").stdout)
        self.assertTrue(report["contentOnly"])
        self.assertEqual(report["sourceContentStatus"], "checked")
        self.assertEqual(report["productGuards"], "not-evaluated")
        self.assertEqual(report["productPermission"], "not-evaluated")
        self.assertFalse(any(item["name"] == "check-protected-file-touch.sh" for item in report["checks"]))
        self.assertNotIn("authorityGranted", report)
        value = "ghp_" + "J" * 36
        (self.repo / "secret.txt").write_text(value + "\n")
        self.git("add", "secret.txt")
        secret = self.call("audit", "--repo", str(self.repo), "--strict", "--content-only", "--mode", "staged", check=False)
        self.assertEqual(secret.returncode, 1)
        self.assertEqual(json.loads(secret.stdout)["sourceContentStatus"], "findings")
        self.assertNotIn(value, secret.stdout + secret.stderr)
        workflow = (ROOT / ".github/workflows/safe-repo-audit.yml").read_text()
        self.assertIn('--content-only --mode push --base "$BASE_SHA" --head "$HEAD_SHA"', workflow)
        self.assertIn('ref: ${{ github.event.pull_request.head.sha || github.sha }}', workflow)
        self.assertIn('HEAD_SHA: ${{ github.event.pull_request.head.sha || github.sha }}', workflow)
        self.assertIn('test "$(git rev-parse HEAD)" = "$HEAD_SHA"', workflow)
        self.assertNotIn("BASTION_OVERRIDE", workflow)

    def test_new_ref_product_delta_is_explicitly_unavailable_not_permission(self):
        remote = self.prepare_local_push()
        path = self.repo / "wp-content/mu-plugins/protected.php"
        path.parent.mkdir(parents=True)
        path.write_text("<?php // fixture product delta\n")
        self.commit("product source on new ref")
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push",
                           "--remote-url", str(remote), check=False)
        report = json.loads(result.stdout)
        self.assertEqual(result.returncode, 2)
        self.assertTrue(any(item["name"] == "check-protected-file-touch.sh" and item["status"] == "unavailable" for item in report["checks"]))
        self.assertNotIn("authorityGranted", report)
        self.assertNotIn("PASS", result.stdout)

    def test_remote_advertised_object_missing_locally_is_unavailable_without_fetch(self):
        remote = self.prepare_local_push()
        blob = self.run_cmd(["git", "--git-dir", str(remote), "hash-object", "-w", "--stdin"], input="remote-only-object").stdout.strip()
        self.run_cmd(["git", "--git-dir", str(remote), "update-ref", "refs/tags/remote-only", blob])
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "push",
                           "--remote-url", str(remote), check=False)
        report = json.loads(result.stdout)
        self.assertEqual(result.returncode, 2)
        self.assertTrue(any(item["name"] == "secret-content-scan" and item["status"] == "unavailable" for item in report["checks"]))
        self.assertNotEqual(self.git("cat-file", "-t", blob, check=False).returncode, 0)

    def test_explicit_repo_ignores_git_redirects_but_honors_alternate_index(self):
        value = "ghp_" + "G" * 36
        (self.repo / "secret.txt").write_text(value + "\n")
        self.git("add", "secret.txt")
        foreign = Path(self.temp.name) / "foreign"
        self.run_cmd(["git", "init", "-b", "feat/foreign", str(foreign)])
        redirected = {**self.env, "GIT_DIR": str(foreign / ".git"), "GIT_WORK_TREE": str(foreign), "GIT_COMMON_DIR": str(foreign / ".git")}
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "staged", env=redirected, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(json.loads(result.stdout)["repo"], str(self.repo))
        self.git("reset", "--", "secret.txt")
        alternate = {**self.env, "GIT_INDEX_FILE": str(Path(self.temp.name) / "alternate-index")}
        self.git("read-tree", "HEAD", env=alternate)
        self.git("add", "secret.txt", env=alternate)
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "staged", env=alternate, check=False)
        self.assertEqual(result.returncode, 1)
        normal = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "staged")
        self.assertFalse(json.loads(normal.stdout)["findings"])

    def test_local_audit_does_not_read_external_parent_symlink_content(self):
        folder = self.repo / "content"
        folder.mkdir()
        (folder / "file.txt").write_text("tracked safe content\n")
        self.commit("tracked nested fixture")
        (folder / "file.txt").unlink()
        folder.rmdir()
        outside = Path(self.temp.name) / "outside"
        outside.mkdir()
        value = "ghp_" + "H" * 36
        (outside / "file.txt").write_text(value + "\n")
        folder.symlink_to(outside, target_is_directory=True)
        result = self.call("audit", "--repo", str(self.repo), "--strict", "--mode", "local", check=False)
        report = json.loads(result.stdout)
        self.assertEqual(result.returncode, 2)
        self.assertFalse(report["findings"])
        self.assertTrue(any(item.get("reason") == "outside-worktree path not read" for item in report["checks"]))
        self.assertNotIn(value, result.stdout + result.stderr)

if __name__ == "__main__":
    unittest.main()
