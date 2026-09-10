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

$digest = sharity_ngo_pref_csrf_digest('secret', 'grant', 'bff-prod', 'https://bff.example/callback', 'pkce', 'nonce');
$tokens = ['csrf' => ['digest' => $digest, 'expires_at' => 100, 'used' => false]];
assert(sharity_ngo_pref_consume_csrf($tokens, 'csrf', $digest, 99));
assert($tokens['csrf']['used'] === true);
assert(!sharity_ngo_pref_consume_csrf($tokens, 'csrf', $digest, 99));
assert(!sharity_ngo_pref_consume_csrf($tokens, 'missing', $digest, 99));
assert(!sharity_ngo_pref_consume_csrf(['expired' => ['digest' => $digest, 'expires_at' => 1]], 'expired', $digest, 2));
assert(sharity_ngo_pref_origin_post_allowed('https://sharity.hu', 'https://sharity.hu:443', true));
assert(!sharity_ngo_pref_origin_post_allowed('https://app.sharity.hu', 'https://sharity.hu', true));
assert(!sharity_ngo_pref_origin_post_allowed('https://sharity.hu', 'https://sharity.hu', false));

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
assert(sharity_ngo_pref_selection_status($rows[0], $rows[0], 2) === 'selectable');
assert(sharity_ngo_pref_selection_status($rows[0], null, 2) === 'selection_required');
assert(sharity_ngo_pref_selection_status($rows[0], $rows[1], 2) === 'selection_required');
assert(sharity_ngo_pref_selection_status($rows[0], $rows[0], 9) === 'selection_required');

fwrite(STDOUT, "PASS sharity NGO preference source contract\n");
