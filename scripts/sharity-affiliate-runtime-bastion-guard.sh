#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "${ROOT_DIR}" ]]; then
  echo "[sharity-affiliate-bastion] not inside a git repository" >&2
  exit 1
fi

RUNTIME_SOURCE="${AFFILIATE_RUNTIME_SOURCE:-${ROOT_DIR}/wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php}"
BOOT_SOURCE="${AFFILIATE_BOOT_SOURCE:-${ROOT_DIR}/wp-content/mu-plugins/impactshop-boot.php}"

for source_path in "$RUNTIME_SOURCE" "$BOOT_SOURCE"; do
  if [[ ! -f "$source_path" ]]; then
    echo "[sharity-affiliate-bastion] missing source: $source_path" >&2
    exit 1
  fi
  php -l "$source_path" >/dev/null
done

python3 - "$RUNTIME_SOURCE" "$BOOT_SOURCE" <<'PY'
import re
import sys

runtime_path, boot_path = sys.argv[1], sys.argv[2]
runtime = open(runtime_path, "r", encoding="utf-8").read()
boot = open(boot_path, "r", encoding="utf-8").read()

def require(text, needle, label):
    if needle not in text:
        raise SystemExit(f"[sharity-affiliate-bastion] missing {label}")

def forbid(text, needle, label):
    if needle in text:
        raise SystemExit(f"[sharity-affiliate-bastion] forbidden {label}")

for needle, label in [
    ("IMPACTSHOP_SHARITY_AFFILIATE_TTL = 900", "15-minute intent TTL"),
    ("IMPACTSHOP_SHARITY_AFFILIATE_RETENTION = 3888000", "45-day retention"),
    ("impactshop_sharity_affiliate_runtime_enabled", "default-off runtime option"),
    ("=== '1'", "exact activation check"),
    ("impactshop_sharity_affiliate_retention_cleanup", "retention cron"),
    ("hash_hmac('sha256', \"impactshop-subject-v1\\0\"", "domain-separated subject HMAC"),
    ("hash_hmac('sha256', \"provider-token-v1\\0\"", "domain-separated provider token"),
    ("'provider' => $provider", "provider-neutral stored contract"),
    ("$provider !== 'dognet'", "Dognet-only live provider gate"),
    ("!in_array($source, ['shopping-assistant', 'vb2026-autobanner'], true)", "exact two-source runtime gate"),
    ("'source' => $source", "exact admitted source persistence"),
    ("'purchase_confirmed' => false", "non-economic purchase result"),
    ("'commission_confirmed' => false", "non-economic commission result"),
    ("'settlement_authorized' => false", "non-economic settlement result"),
    ("IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER = 'unice'", "single CJ partner"),
    ("IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM = 'cj-5824323-15487360'", "reviewed CJ program"),
    ("IMPACTSHOP_SHARITY_AFFILIATE_CJ_LINK = 'https://www.tkqlhce.com/click-101302202-15487360'", "reviewed CJ link"),
    ("impactshop_sharity_affiliate_cj_canary_enabled", "default-off CJ option"),
    ("x-sharity-service-authorization", "server-to-server credential boundary"),
    ("impact_sharity_web_sessions", "canonical web session authority"),
    ("SELECT subject_ref, status, expires_at_utc", "session privacy projection"),
    ("'status' => 'ready_to_redirect'", "one-use handoff initial state"),
    ("handoff_token_hash = %s FOR UPDATE", "locked handoff consumption"),
    ("array_keys($query) !== ['sid']", "sole CJ query parameter"),
    ("register_rest_route('sharity/v1', '/shopping/cj-intent'", "exact CJ intent route"),
    ("/shopping/cj-handoff/(?P<handoffToken>shp1_[A-Za-z0-9_-]{43})", "bounded CJ handoff route"),
]:
    require(runtime, needle, label)

for needle, label in [
    ("go.dognet.", "provider redirect ownership in correlation runtime"),
    ("wp_remote_", "outbound network request in correlation runtime"),
    ("update_user_meta", "profile writer"),
    ("INSERT INTO wp_impactshop_points", "points writer"),
]:
    forbid(runtime, needle, label)

schema_match = re.search(r"CREATE TABLE \{\$table\} \((.*?)\) \{\$charset\};", runtime, re.S)
if not schema_match:
    raise SystemExit("[sharity-affiliate-bastion] schema contract not found")
columns = []
for raw_line in schema_match.group(1).splitlines():
    line = raw_line.strip().rstrip(",")
    if not line or line.startswith(("PRIMARY ", "UNIQUE ", "KEY ")):
        continue
    columns.append(line.split()[0].lower())
for forbidden_column in {
    "pseudo", "provider_token", "url", "target_url", "session", "credential",
    "ip", "user_agent", "purchase", "commission", "reward", "settlement",
}:
    if forbidden_column in columns:
        raise SystemExit(f"[sharity-affiliate-bastion] forbidden stored column: {forbidden_column}")

for required_column in {
    "activation_id", "provider_token_hash", "request_key_hash", "subject_ref",
    "ngo_ref", "partner_key", "provider_key", "source_placement", "status",
    "intent_expires_at", "delete_after", "handoff_token_hash",
    "authority_snapshot_ref", "disclosure_version",
}:
    if required_column not in columns:
        raise SystemExit(f"[sharity-affiliate-bastion] missing stored column: {required_column}")

cj_start = runtime.find("function impactshop_sharity_affiliate_cj_redirect_url")
cj_end = runtime.find("function impactshop_sharity_affiliate_cj_handoff", cj_start)
if cj_start < 0 or cj_end < 0:
    raise SystemExit("[sharity-affiliate-bastion] CJ redirect builder region not found")
cj_builder = runtime[cj_start:cj_end]
for forbidden in ["destination", "target", "urlencode($request", "wp_remote_", "a_aid", "utm_"]:
    if forbidden in cj_builder:
        raise SystemExit(f"[sharity-affiliate-bastion] forbidden dynamic CJ redirect input: {forbidden}")
if cj_builder.count("?sid=") != 1:
    raise SystemExit("[sharity-affiliate-bastion] CJ redirect must contain exactly one sid construction")

for needle, label in [
    ("$sharityAffiliateSource = in_array($src, ['shopping-assistant', 'vb2026-autobanner'], true);", "exact two-source boot gate"),
    ("apply_filters('impactshop_sharity_affiliate_prepare'", "prepare delegation"),
    ("apply_filters('impactshop_sharity_affiliate_mark_redirected'", "one-time transition"),
    ("$affiliateNgo = $prepared['provider_token'];", "opaque provider attribution"),
    ("$affiliatePseudo = '';", "raw pseudo suppression"),
    ("'ISB-GO-ERROR: shop=%s source=%s'", "unknown-shop log redaction"),
    ("if ($sharityAffiliateRuntime) {\n      isb_error('Már becsomagolt partnerlink", "wrapped-link rejection"),
    ("'sid' => $sharityAffiliateRuntime ? '' : $sidForLog", "new-path SID log redaction"),
    ("'pseudo' => $sharityAffiliateRuntime ? '' : $pseudo", "new-path pseudo log redaction"),
    ("isb_redirect_with_propagation($final,$amb,$src);", "existing redirect owner"),
]:
    require(boot, needle, label)

start = boot.find("$isCj = (stripos($shop, 'cj-')")
end = boot.find("isb_redirect_with_propagation($final,$amb,$src);", start)
if start < 0 or end < 0:
    raise SystemExit("[sharity-affiliate-bastion] protected /go adapter region not found")
adapter = boot[start:end]
for legacy_leak in [
    "isb_dognet_api_generate_link($cid, $targetUrl, $ngo, '', $pseudo)",
    "isb_cj_generate_click_url($shop, $ngo, $pseudo",
    "$params = ['d1'=>$ngo]",
    "$extra['data5'] = $pseudo",
]:
    if legacy_leak in adapter:
        raise SystemExit(f"[sharity-affiliate-bastion] raw attribution remains in adapter: {legacy_leak}")

mark_pos = adapter.find("impactshop_sharity_affiliate_mark_redirected")
log_pos = adapter.find("isb_log_go_click")
if mark_pos < 0 or log_pos < 0 or mark_pos > log_pos:
    raise SystemExit("[sharity-affiliate-bastion] redirect transition must precede click log and redirect")

unknown_start = boot.find("if (!$row) {")
unknown_end = boot.find("isb_error('Ismeretlen shop:", unknown_start)
unknown_block = boot[unknown_start:unknown_end]
redacted_pos = unknown_block.find("if ($sharityAffiliateSource)")
legacy_pos = unknown_block.find("referer=%s pseudo=%s ip=%s")
if redacted_pos < 0 or legacy_pos < 0 or redacted_pos > legacy_pos:
    raise SystemExit("[sharity-affiliate-bastion] unknown-shop privacy branch is not fail-closed")

print("[sharity-affiliate-bastion] PASS")
PY
