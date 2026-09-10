<?php
declare(strict_types=1);

$plugin = __DIR__ . '/../wp-content/mu-plugins/impactshop-sharity-ngo-preference-source.php.off';
require_once $plugin;

assert(sharity_ngo_pref_normalize_origin('https://Sharity.hu:443') === 'https://sharity.hu');
assert(sharity_ngo_pref_normalize_origin('http://sharity.hu') === null);
assert(sharity_ngo_pref_normalize_origin('https://evil-sharity.hu') === 'https://evil-sharity.hu');
assert(sharity_ngo_pref_normalize_origin('https://sharity.hu/path') === null);
assert(sharity_ngo_pref_exact_origin('https://WWW.sharity.hu', 'https://www.sharity.hu:443'));
assert(!sharity_ngo_pref_exact_origin('https://app.sharity.hu', 'https://www.sharity.hu'));
assert(!sharity_ngo_pref_exact_origin('', 'https://www.sharity.hu'));

$registry = ['bff-prod' => ['https://bff.example/callback']];
assert(sharity_ngo_pref_client_redirect_allowed($registry, 'bff-prod', 'https://bff.example/callback'));
assert(!sharity_ngo_pref_client_redirect_allowed($registry, 'bff-prod', 'https://bff.example/callback.evil'));
assert(!sharity_ngo_pref_client_redirect_allowed(['bff-*' => ['https://bff.example/callback']], 'bff-prod', 'https://bff.example/callback'));
assert(sharity_ngo_pref_redirect_uri_valid('https://bff.example/callback'));
assert(!sharity_ngo_pref_redirect_uri_valid('http://bff.example/callback'));
assert(!sharity_ngo_pref_redirect_uri_valid('https://bff.example/callback#fragment'));
assert(sharity_ngo_pref_subject_key('Profile-1', 'subject-secret') !== null);
assert(sharity_ngo_pref_subject_key('', 'subject-secret') === null);

$digest = sharity_ngo_pref_csrf_digest('secret', 'grant', 'bff-prod', 'https://bff.example/callback', 'pkce', 'nonce');
$tokens = [sharity_ngo_pref_token_key('csrf') => ['digest' => $digest, 'expires_at' => 100, 'used' => false]];
assert(sharity_ngo_pref_consume_csrf($tokens, 'csrf', $digest, 99));
assert($tokens[sharity_ngo_pref_token_key('csrf')]['used'] === true);
assert(!sharity_ngo_pref_consume_csrf($tokens, 'csrf', $digest, 99));
assert(!sharity_ngo_pref_consume_csrf($tokens, 'missing', $digest, 99));
$expired_tokens = [sharity_ngo_pref_token_key('expired') => ['digest' => $digest, 'expires_at' => 1, 'used' => false]];
assert(!sharity_ngo_pref_consume_csrf($expired_tokens, 'expired', $digest, 2));
assert(sharity_ngo_pref_origin_post_allowed('https://sharity.hu', 'https://sharity.hu:443', true));
assert(!sharity_ngo_pref_origin_post_allowed('https://app.sharity.hu', 'https://sharity.hu', true));
assert(!sharity_ngo_pref_origin_post_allowed(null, 'https://sharity.hu', true));
assert(!sharity_ngo_pref_origin_post_allowed('https://sharity.hu', 'https://sharity.hu', false));

$codes = [];
$claims = ['owner_grant_hash' => 'grant', 'subject' => 'v1:subject', 'client_id' => 'bff-prod', 'redirect_uri' => 'https://bff.example/callback', 'pkce_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=')];
$raw_code = str_repeat('C', 32);
$registry = ['bff-prod' => ['https://bff.example/callback']];
assert(sharity_ngo_pref_issue_code($codes, $raw_code, $claims, $registry, 10));
assert(sharity_ngo_pref_redeem_code($codes, $raw_code, 'wrong-grant', 'bff-prod', 'https://bff.example/callback', 'verifier', true, 20) === null);
assert(sharity_ngo_pref_redeem_code($codes, $raw_code, 'grant', 'bff-prod', 'https://bff.example/callback', 'verifier', true, 20) === null);
$bad_claims = $claims;
$bad_claims['redirect_uri'] = 'https://other.example/callback';
assert(!sharity_ngo_pref_issue_code($codes, str_repeat('E', 32), $bad_claims, $registry, 10));
assert(sharity_ngo_pref_issue_code($codes, str_repeat('D', 32), $claims, $registry, 10));
assert(sharity_ngo_pref_redeem_code($codes, str_repeat('D', 32), 'grant', 'bff-prod', 'https://bff.example/callback', 'verifier', false, 20) === null);
assert(sharity_ngo_pref_redeem_code($codes, str_repeat('D', 32), 'grant', 'bff-prod', 'https://bff.example/callback', 'verifier', true, 20) === null);
$success_code = str_repeat('S', 32);
assert(sharity_ngo_pref_issue_code($codes, $success_code, $claims, $registry, 10));
assert(sharity_ngo_pref_redeem_code($codes, $success_code, 'grant', 'bff-prod', 'https://bff.example/callback', 'verifier', true, 20) === 'v1:subject');
assert(sharity_ngo_pref_redeem_code($codes, $success_code, 'grant', 'bff-prod', 'https://bff.example/callback', 'verifier', true, 20) === null);
assert(sharity_ngo_pref_response_headers()['Cache-Control'] === 'private, no-store');
assert(count(sharity_ngo_pref_table_names()) === 5);
assert(sharity_ngo_pref_table_names()[0]['keys'] === ['code_hash']);
assert(sharity_ngo_pref_scope_allowed('vb2026'));
assert(!sharity_ngo_pref_scope_allowed('unknown'));
$routes = sharity_ngo_pref_source_routes();
assert($routes[0]['method'] === 'GET' && $routes[0]['auth'] === 'owner-grant-intent');
assert($routes[1]['auth'] === 'exact-origin-csrf-owner-grant');
assert($routes[2]['auth'] === 'confidential-bff-pkce');
assert($routes[6]['method'] === 'PUT' && $routes[6]['auth'] === 'bearer-cas' && $routes[6]['handler'] === 'fail_closed_until_enabled');

$session = ['owner_grant_hash' => 'grant', 'expires_at' => 10, 'revoked_at' => null];
assert(sharity_ngo_pref_bearer_active($session, 10, 'grant'));
assert(!sharity_ngo_pref_bearer_active($session, 11, 'grant'));
$session['revoked_at'] = 10;
assert(!sharity_ngo_pref_bearer_active($session, 10, 'grant'));

$rows = [
    ['sharity_ngo_id' => 2, 'master_active' => 1, 'source_row_hash' => 'b', 'policy_allow_user_selection' => 1, 'policy_state' => 'active', 'policy_version' => 1, 'campaign_state' => 'ignored'],
    ['sharity_ngo_id' => 1, 'master_active' => 1, 'source_row_hash' => 'a', 'policy_allow_user_selection' => 0, 'policy_state' => 'disabled', 'policy_version' => 2, 'campaign_state' => 'active'],
];
$revision = sharity_ngo_pref_catalog_revision($rows);
$reordered = [$rows[1], $rows[0]];
assert($revision === sharity_ngo_pref_catalog_revision($reordered));
$campaignOnlyChange = $rows;
$campaignOnlyChange[0]['campaign_state'] = 'inactive';
assert($revision === sharity_ngo_pref_catalog_revision($campaignOnlyChange));
$selectable_policy = ['allow_user_selection' => 1, 'state' => 'active'];
$disabled_policy = ['allow_user_selection' => 0, 'state' => 'disabled'];
assert(sharity_ngo_pref_selection_status($rows[0], $selectable_policy, 2) === 'selectable');
assert(sharity_ngo_pref_selection_status($rows[0], null, 2) === 'selection_required');
assert(sharity_ngo_pref_selection_status($rows[0], $disabled_policy, 2) === 'selection_required');
assert(sharity_ngo_pref_selection_status($rows[0], $rows[0], 9) === 'selection_required');

$preferences = [];
$idempotency = [];
$audit = [];
$master = ['sharity_ngo_id' => 2, 'master_active' => 1];
$policy = ['allow_user_selection' => 1, 'state' => 'active'];
assert(sharity_ngo_pref_cas_update($preferences, $idempotency, $audit, 'v1:subject', 'profile_default', 2, $revision, $revision, $master, $policy, 0, 'op-1', 20) === 'updated');
assert(sharity_ngo_pref_cas_update($preferences, $idempotency, $audit, 'v1:subject', 'profile_default', 2, $revision, $revision, $master, $policy, 0, 'op-1', 21) === 'idempotent_replay');
$other_master = ['sharity_ngo_id' => 1, 'master_active' => 1];
$other_policy = ['allow_user_selection' => 1, 'state' => 'active'];
assert(sharity_ngo_pref_cas_update($preferences, $idempotency, $audit, 'v1:subject', 'profile_default', 1, $revision, $revision, $other_master, $other_policy, 0, 'op-1', 21) === 'idempotency_conflict');
assert(sharity_ngo_pref_cas_update($preferences, $idempotency, $audit, 'v1:subject', 'profile_default', 1, 'stale', $revision, $master, $policy, 1, 'op-2', 21) === 'stale_revision');
assert(sharity_ngo_pref_cas_update($preferences, $idempotency, $audit, 'v1:subject', 'profile_default', 1, $revision, $revision, $master, $policy, 1, 'op-3', 21) === 'selection_required');
assert(count($audit) === 1);
assert($audit[0]['before_ngo_id'] === null && $audit[0]['after_ngo_id'] === 2 && $audit[0]['before_revision'] === null && $audit[0]['after_revision'] === $revision);

fwrite(STDOUT, "PASS sharity NGO preference source contract\n");
