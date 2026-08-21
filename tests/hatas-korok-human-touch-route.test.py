#!/usr/bin/env python3
import json
import pathlib
import re
import subprocess
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "wp-content/mu-plugins/impactshop-hatas-korok-human-touch-route.php"
SMOKE = ROOT / "scripts/hatas-korok-post-deploy-smoke.sh"


class HatasKorokHumanTouchRouteTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.source = PLUGIN.read_text(encoding="utf-8")
        cls.smoke = SMOKE.read_text(encoding="utf-8")

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
        php = f"""
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
require {json.dumps(str(PLUGIN))};
impactshop_hatas_korok_human_touch_redirect();
echo 'NO_REDIRECT';
"""
        result = subprocess.run(
            ["php", "-r", php],
            check=False,
            capture_output=True,
            text=True,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout

    def test_php_syntax_is_valid(self) -> None:
        result = subprocess.run(
            ["php", "-l", str(PLUGIN)],
            check=False,
            capture_output=True,
            text=True,
        )
        self.assertEqual(result.returncode, 0, result.stderr or result.stdout)

    def test_redirect_is_exact_and_early(self) -> None:
        self.assertIn("$host !== 'app.sharity.hu'", self.source)
        self.assertIn("~^/hatas-korok/?$~D", self.source)
        self.assertIn("['GET', 'HEAD']", self.source)
        self.assertRegex(
            self.source,
            re.compile(
                r"wp_redirect\('https://sharity\.hu/hatas-korok',\s*302,\s*'Sharity Hatas Korok'\)"
            ),
        )
        self.assertIn(
            "add_action('template_redirect', 'impactshop_hatas_korok_human_touch_redirect', 1)",
            self.source,
        )

    def test_protected_request_surfaces_are_excluded(self) -> None:
        self.assertIn("is_admin()", self.source)
        self.assertIn("REST_REQUEST", self.source)
        self.assertIn("wp_doing_ajax", self.source)
        self.assertNotIn("hatas-korok-dev", self.source)
        self.assertNotIn("impactshop-staging", self.source)

    def test_no_request_value_can_enter_destination(self) -> None:
        redirect_line = next(line for line in self.source.splitlines() if "wp_redirect(" in line)
        self.assertNotIn("$_GET", self.source)
        self.assertNotIn("QUERY_STRING", self.source)
        self.assertNotIn("$requestUri", redirect_line)
        self.assertNotIn("$path", redirect_line)
        self.assertNotIn("$host", redirect_line)

        self.assertEqual(
            self.run_route(
                "app.sharity.hu",
                "/hatas-korok/?ic_test_mode=1&impact_pseudo_id=SECRET",
            ),
            "REDIRECT|https://sharity.hu/hatas-korok|302|Sharity Hatas Korok",
        )

    def test_runtime_boundary_matrix(self) -> None:
        redirect = "REDIRECT|https://sharity.hu/hatas-korok|302|Sharity Hatas Korok"
        self.assertEqual(self.run_route("app.sharity.hu", "/hatas-korok"), redirect)
        self.assertEqual(self.run_route("app.sharity.hu", "/hatas-korok/", "HEAD"), redirect)
        for output in (
            self.run_route("app.sharity.hu", "/hatas-korok-dev"),
            self.run_route("app.sharity.hu", "/impactshop-staging/hatas-korok-dev"),
            self.run_route("sharity.hu", "/hatas-korok"),
            self.run_route("app.sharity.hu", "/hatas-korok", "POST"),
            self.run_route("app.sharity.hu", "/hatas-korok", admin=True),
            self.run_route("app.sharity.hu", "/hatas-korok", ajax=True),
            self.run_route("app.sharity.hu", "/hatas-korok", rest=True),
        ):
            self.assertEqual(output, "NO_REDIRECT")

    def test_smoke_contract_checks_cutover_and_legacy_boundaries(self) -> None:
        for marker in (
            "EXPECTED_LOCATION",
            "hk_route_probe=1",
            "hatas-korok-dev",
            "impactshop-staging/hatas-korok-dev",
            "Hatás Körök — Közösségek, nem követők",
            "AUTH_URL",
            "CIRCLES_URL",
        ):
            self.assertIn(marker, self.smoke)

    def test_smoke_location_parser_is_posix_awk_compatible(self) -> None:
        self.assertIn("tolower(substr($0, 1, 9))", self.smoke)
        self.assertNotIn("IGNORECASE", self.smoke)


if __name__ == "__main__":
    unittest.main()
