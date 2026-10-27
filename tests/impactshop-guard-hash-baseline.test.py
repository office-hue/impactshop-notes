"""Local-only regression of the existing exact guard checker; no provider access."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile

root = Path(__file__).resolve().parents[1]
source = (root / "bin/impactshop-guard-deploy.sh").read_text()
marker = 'python3 - "$CONFIG_PATH" "$HASH_PATH" <<\'PY\' >/tmp/impactshop_guard_check.txt\n'
assert source.count(marker) == 1
checker = source.split(marker, 1)[1].split('\nPY\n', 1)[0]
empty = {"missing": [], "missing_wrong_repo": [], "invalid_paths": [], "changed": []}

def inspect(config, manifest):
    return json.loads(subprocess.check_output([sys.executable, "-c", checker, str(config), str(manifest)], text=True))

assert inspect(root / "docs/impactshop-guard-config.json", root / "docs/impactshop-guard-hashes.json") == empty, "full real protected inventory has no drift"
(root / "tmp").mkdir(exist_ok=True)
with tempfile.TemporaryDirectory(prefix="guard-hash-regression-", dir=root / "tmp") as directory:
    fixture = Path(directory)
    (fixture / "docs").mkdir()
    (fixture / ".github/workflows").mkdir(parents=True)
    ci = fixture / ".github/workflows/ci.yml"
    ci.write_bytes((root / ".github/workflows/ci.yml").read_bytes())
    sentinel = fixture / "protected-sentinel.php"
    sentinel.write_text("<?php // unchanged protected fixture\n")
    paths = [".github/workflows/ci.yml", "protected-sentinel.php"]
    config, manifest = fixture / "docs/config.json", fixture / "docs/hashes.json"
    config.write_text(json.dumps({"repo": {"root": str(fixture)}, "protected_files": paths}))
    known = json.loads((root / "docs/impactshop-guard-hashes.json").read_text())["hashes"][paths[0]]
    hashes = {paths[0]: known, paths[1]: hashlib.sha256(sentinel.read_bytes()).hexdigest()}
    def check_fixture():
        manifest.write_text(json.dumps({"hashes": hashes}))
        return inspect(config, manifest)
    assert hashlib.sha256(ci.read_bytes()).hexdigest() == known
    assert check_fixture() == empty, "approved current CI accepted"
    hashes[paths[0]] = "ab43c08a72c69d56187771b5b4f8cf81deda401d22685adf4cd84f0d52886015"
    assert check_fixture()["changed"] == [paths[0]], "old #197 baseline reproduces drift"
    hashes[paths[0]] = known
    original = ci.read_bytes()
    ci.write_bytes(original + b"\n# unapproved edit\n")
    assert check_fixture()["changed"] == [paths[0]], "unapproved CI edit rejected"
    ci.write_bytes(original)
    sentinel.write_text("<?php // unapproved protected fixture edit\n")
    assert check_fixture()["changed"] == [paths[1]], "unrelated protected edit rejected"
    ci.unlink()
    assert check_fixture()["missing"] == [paths[0]], "missing CI rejected"
checksum, label = (root / "docs/impactshop-guard-hashes.sha256").read_text().split()
assert label == "docs/impactshop-guard-hashes.json"
assert hashlib.sha256((root / label).read_bytes()).hexdigest() == checksum
print("PASS: full actual protected inventory has zero drift; 6 isolated hash/checksum cases; no network")
