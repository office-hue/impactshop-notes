#!/usr/bin/env python3
import json
import pathlib
import re
import shutil
import subprocess
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "wp-content/mu-plugins/impactshop-sharity-public-portal-redirects.php"
LEGACY_GUIDE = ROOT / "wp-content/mu-plugins/impactshop-ngo-guides.php"


class SharityPublicPortalRedirectsTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.source = PLUGIN.read_text(encoding="utf-8")

    def run_php(self, source: str, *, lint: bool = False) -> subprocess.CompletedProcess:
        if shutil.which("php"):
            command = ["php"]
        else:
            command = [
                "ssh",
                "-o",
                "BatchMode=yes",
                "mac-primary",
                "ssh",
                "-o",
                "BatchMode=yes",
                "s59",
                "php",
            ]
        if lint:
            command.extend(["-l", "/dev/stdin"])
        return subprocess.run(
            command,
            input=source,
            check=False,
            capture_output=True,
            text=True,
        )

    def run_route(
        self,
        host: str,
        uri: str,
        method: str = "GET",
        *,
        admin: bool = False,
        ajax: bool = False,
        rest: bool = False,
    ) -> str:
        plugin_body = self.source.removeprefix("<?php")
        php = f"""<?php
define('ABSPATH', __DIR__);
{'define("REST_REQUEST", true);' if rest else ''}
function is_admin() {{ return {'true' if admin else 'false'}; }}
function wp_doing_ajax() {{ return {'true' if ajax else 'false'}; }}
function nocache_headers() {{}}
function wp_redirect($location, $status, $agent) {{ echo "REDIRECT|$location|$status|$agent"; }}
function add_action($hook, $callback, $priority) {{}}
$_SERVER['HTTP_HOST'] = {json.dumps(host)};
$_SERVER['REQUEST_URI'] = {json.dumps(uri)};
$_SERVER['REQUEST_METHOD'] = {json.dumps(method)};
{plugin_body}
impactshop_sharity_public_portal_redirect();
echo 'NO_REDIRECT';
"""
        result = self.run_php(php)
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout

    def test_php_syntax_is_valid(self) -> None:
        result = self.run_php(self.source, lint=True)
        self.assertEqual(result.returncode, 0, result.stderr or result.stdout)

    def test_redirect_is_exact_early_and_reversible(self) -> None:
        self.assertIn("$host !== 'app.sharity.hu'", self.source)
        self.assertIn("'/adomany-automata-portal-1'", self.source)
        self.assertIn("'/adomany-automata-portal-2'", self.source)
        self.assertIn("['GET', 'HEAD']", self.source)
        self.assertRegex(
            self.source,
            re.compile(
                r"wp_redirect\('https://sharity\.hu/',\s*302,\s*'Sharity Public Portal'\)"
            ),
        )
        self.assertIn(
            "add_action('template_redirect', 'impactshop_sharity_public_portal_redirect', 1)",
            self.source,
        )

    def test_both_portals_redirect_without_query_forwarding(self) -> None:
        redirect = "REDIRECT|https://sharity.hu/|302|Sharity Public Portal"
        for uri in (
            "/adomany-automata-portal-1",
            "/adomany-automata-portal-1/",
            "/adomany-automata-portal-2",
            "/adomany-automata-portal-2/?impact_event_auction_embed=1&slug=secret",
        ):
            with self.subTest(uri=uri):
                self.assertEqual(self.run_route("app.sharity.hu", uri), redirect)

    def test_non_public_or_mutating_surfaces_are_excluded(self) -> None:
        for output in (
            self.run_route("app.sharity.hu", "/adomany-automata-portal-3"),
            self.run_route("app.sharity.hu", "/adomany-automata-portal-10"),
            self.run_route("sharity.hu", "/adomany-automata-portal-1"),
            self.run_route("app.sharity.hu", "/wp-json/adomany-automata-portal-1"),
            self.run_route("app.sharity.hu", "/adomany-automata-portal-1", "POST"),
            self.run_route("app.sharity.hu", "/adomany-automata-portal-1", admin=True),
            self.run_route("app.sharity.hu", "/adomany-automata-portal-1", ajax=True),
            self.run_route("app.sharity.hu", "/adomany-automata-portal-1", rest=True),
        ):
            self.assertEqual(output, "NO_REDIRECT")

    def test_request_values_cannot_enter_destination(self) -> None:
        redirect_line = next(line for line in self.source.splitlines() if "wp_redirect(" in line)
        self.assertNotIn("$_GET", self.source)
        self.assertNotIn("QUERY_STRING", self.source)
        for variable in ("$requestUri", "$path", "$host"):
            self.assertNotIn(variable, redirect_line)

    def test_legacy_guide_owner_remains_unchanged(self) -> None:
        self.assertEqual(
            LEGACY_GUIDE.read_bytes(),
            subprocess.check_output(
                ["git", "show", "origin/main:wp-content/mu-plugins/impactshop-ngo-guides.php"],
                cwd=ROOT,
            ),
        )


if __name__ == "__main__":
    unittest.main()
