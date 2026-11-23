<?php
/**
 * Plugin Name: ImpactShop Sharity Affiliate Runtime
 * Description: Opaque affiliate-intent correlation for the Sharity Shopping Assistant.
 * Version: 2.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_SHARITY_AFFILIATE_SCHEMA_VERSION = '2';
const IMPACTSHOP_SHARITY_AFFILIATE_TTL = 900;
const IMPACTSHOP_SHARITY_AFFILIATE_RETENTION = 3888000;
const IMPACTSHOP_SHARITY_AFFILIATE_CRON = 'impactshop_sharity_affiliate_retention_cleanup';
const IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER = 'unice';
const IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM = 'cj-5824323-15487360';
const IMPACTSHOP_SHARITY_AFFILIATE_CJ_LINK = 'https://www.tkqlhce.com/click-101302202-15487360';
const IMPACTSHOP_SHARITY_AFFILIATE_CJ_MERCHANT = 'https://www.unice.com/unice-hair-pre-plucked-virgin-hair-body-wave-hd-lace-closure-wigs-amazing-lace-melted-match-all-skin-color-bettyou-series.html';
const IMPACTSHOP_SHARITY_AFFILIATE_CJ_DISCLOSURE = 'shopping-affiliate-v1';
const IMPACTSHOP_SHARITY_AFFILIATE_NGO = 'gyoztesek-egyesulete';

function impactshop_sharity_affiliate_enabled()
{
    return (string) get_option('impactshop_sharity_affiliate_runtime_enabled', '0') === '1';
}

function impactshop_sharity_affiliate_cj_enabled()
{
    return impactshop_sharity_affiliate_enabled()
        && (string) get_option('impactshop_sharity_affiliate_cj_canary_enabled', '0') === '1';
}

function impactshop_sharity_affiliate_table()
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_affiliate_intents';
}

function impactshop_sharity_affiliate_secret()
{
    $secret = function_exists('wp_salt') ? (string) wp_salt('auth') : '';
    return strlen($secret) >= 32 ? $secret : '';
}

function impactshop_sharity_affiliate_b64url($binary)
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

function impactshop_sharity_affiliate_subject_ref($pseudo, $secret = null)
{
    $pseudo = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string) $pseudo));
    $pseudo = substr($pseudo, 0, 12);
    $secret = $secret === null ? impactshop_sharity_affiliate_secret() : (string) $secret;
    if ($pseudo === '' || strlen($secret) < 32) {
        return '';
    }
    return 'hmac-sha256:' . hash_hmac('sha256', "impactshop-subject-v1\0" . $pseudo, $secret);
}

function impactshop_sharity_affiliate_provider_token($activationId, $secret = null)
{
    $secret = $secret === null ? impactshop_sharity_affiliate_secret() : (string) $secret;
    if (!preg_match('/^act1_[a-f0-9]{32}$/', (string) $activationId) || strlen($secret) < 32) {
        return '';
    }
    return 'sat1_' . impactshop_sharity_affiliate_b64url(
        hash_hmac('sha256', "provider-token-v1\0" . $activationId, $secret, true)
    );
}

function impactshop_sharity_affiliate_validate_context($value)
{
    if (!is_array($value)) {
        return new WP_Error('invalid_context', 'Invalid affiliate context.');
    }
    $expected = ['ngo', 'provider', 'pseudo', 'shop', 'source'];
    $actual = array_keys($value);
    sort($actual);
    if ($actual !== $expected) {
        return new WP_Error('invalid_context', 'Invalid affiliate context.');
    }
    $shop = sanitize_title((string) $value['shop']);
    $ngo = sanitize_title((string) $value['ngo']);
    $pseudo = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string) $value['pseudo']));
    $provider = (string) $value['provider'];
    $source = (string) $value['source'];
    $pointsContext = $source === 'impactshop-points'
        && (string) get_option('sharity_points_impactshop_producer_enabled', '0') === '1'
        && function_exists('impactshop_identity_owner_authorized')
        && impactshop_identity_owner_authorized(strtolower($pseudo));
    if (
        $shop === '' || $ngo === '' || strlen($shop) > 128 || strlen($ngo) > 128
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $shop)
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $ngo)
        || !preg_match('/^[A-Z0-9]{6,12}$/', $pseudo)
        || (!in_array($source, ['shopping-assistant', 'vb2026-autobanner'], true) && !$pointsContext)
        || ($provider !== 'dognet' && !($pointsContext && in_array($provider, ['cj', 'partner'], true)))
    ) {
        return new WP_Error('unsupported_context', 'Unsupported affiliate context.');
    }
    return [
        'shop' => $shop,
        'ngo' => $ngo,
        'pseudo' => substr($pseudo, 0, 12),
        'provider' => $provider,
        'source' => $source,
    ];
}

function impactshop_sharity_affiliate_install_schema()
{
    global $wpdb;
    if ((string) get_option('impactshop_sharity_affiliate_schema_version', '') === IMPACTSHOP_SHARITY_AFFILIATE_SCHEMA_VERSION) {
        return true;
    }
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }
    $table = impactshop_sharity_affiliate_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
      activation_id varchar(37) NOT NULL,
      provider_token_hash char(64) NOT NULL,
      handoff_token_hash char(64) NULL,
      request_key_hash char(64) NOT NULL,
      subject_ref varchar(80) NOT NULL,
      ngo_ref varchar(128) NOT NULL,
      partner_key varchar(128) NOT NULL,
      provider_key varchar(32) NOT NULL,
      provider_scope_ref varchar(96) NULL,
      provider_program_ref varchar(160) NOT NULL,
      authority_snapshot_ref varchar(160) NOT NULL DEFAULT '',
      disclosure_version varchar(64) NOT NULL DEFAULT '',
      source_placement varchar(64) NOT NULL,
      status varchar(24) NOT NULL,
      created_at datetime NOT NULL,
      intent_expires_at datetime NOT NULL,
      redirected_at datetime NULL,
      delete_after datetime NOT NULL,
      PRIMARY KEY  (activation_id),
      UNIQUE KEY provider_token_hash (provider_token_hash),
      UNIQUE KEY handoff_token_hash (handoff_token_hash),
      UNIQUE KEY request_key_hash (request_key_hash),
      KEY retention (status, delete_after)
    ) {$charset};");
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return false;
    }
    foreach (['handoff_token_hash', 'authority_snapshot_ref', 'disclosure_version'] as $requiredColumn) {
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $requiredColumn));
        if ($column !== $requiredColumn) {
            return false;
        }
    }
    update_option('impactshop_sharity_affiliate_schema_version', IMPACTSHOP_SHARITY_AFFILIATE_SCHEMA_VERSION, false);
    return true;
}

function impactshop_sharity_affiliate_prepare($unused, $rawContext)
{
    global $wpdb;
    if (!impactshop_sharity_affiliate_enabled()) {
        return new WP_Error('runtime_disabled', 'Affiliate runtime is disabled.');
    }
    $context = impactshop_sharity_affiliate_validate_context($rawContext);
    if (is_wp_error($context) || !impactshop_sharity_affiliate_install_schema()) {
        return is_wp_error($context) ? $context : new WP_Error('schema_unavailable', 'Affiliate schema unavailable.');
    }
    $secret = impactshop_sharity_affiliate_secret();
    $subject = impactshop_sharity_affiliate_subject_ref($context['pseudo'], $secret);
    if ($subject === '') {
        return new WP_Error('issuer_unavailable', 'Affiliate issuer unavailable.');
    }
    if ($context['source'] === 'impactshop-points') {
        $binding = function_exists('sharity_points_impactshop_bind_subject')
            ? sharity_points_impactshop_bind_subject(strtolower($context['pseudo'])) : ['success' => false];
        if (empty($binding['success'])) {
            return new WP_Error('points_binding_unavailable', 'Point identity binding unavailable.');
        }
    }
    $now = time();
    $bucket = (string) floor($now / IMPACTSHOP_SHARITY_AFFILIATE_TTL);
    $requestProjection = implode("\0", [
        $subject, $context['ngo'], $context['shop'], $context['provider'], $context['source'], $bucket,
    ]);
    $requestHash = hash_hmac('sha256', "request-key-v1\0" . $requestProjection, $secret);
    $table = impactshop_sharity_affiliate_table();

    $wpdb->query('START TRANSACTION');
    try {
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT activation_id, status, intent_expires_at FROM {$table} WHERE request_key_hash = %s FOR UPDATE", $requestHash),
            ARRAY_A
        );
        if ($row) {
            if ($row['status'] !== 'ready_to_redirect' || strtotime($row['intent_expires_at'] . ' UTC') <= $now) {
                throw new RuntimeException('idempotency_replayed');
            }
            $activationId = $row['activation_id'];
            $replay = true;
        } else {
            $activationId = 'act1_' . bin2hex(random_bytes(16));
            $token = impactshop_sharity_affiliate_provider_token($activationId, $secret);
            $inserted = $wpdb->insert($table, [
                'activation_id' => $activationId,
                'provider_token_hash' => hash('sha256', $token),
                'handoff_token_hash' => null,
                'request_key_hash' => $requestHash,
                'subject_ref' => $subject,
                'ngo_ref' => $context['ngo'],
                'partner_key' => $context['shop'],
                'provider_key' => $context['provider'],
                'provider_program_ref' => $context['provider'] . ':' . $context['shop'],
                'authority_snapshot_ref' => $context['source'] === 'impactshop-points' ? 'points-attribution-v1' : 'legacy-dognet-v1',
                'disclosure_version' => IMPACTSHOP_SHARITY_AFFILIATE_CJ_DISCLOSURE,
                'source_placement' => $context['source'],
                'status' => 'ready_to_redirect',
                'created_at' => gmdate('Y-m-d H:i:s', $now),
                'intent_expires_at' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
                'delete_after' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            if ($inserted !== 1) {
                throw new RuntimeException('insert_failed');
            }
            $replay = false;
        }
        $token = impactshop_sharity_affiliate_provider_token($activationId, $secret);
        $wpdb->query('COMMIT');
        return [
            'authorized' => true,
            'activation_id' => $activationId,
            'provider_token' => $token,
            'provider_key' => $context['provider'],
            'idempotent_replay' => $replay,
        ];
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('intent_issue_failed', 'Affiliate intent issue failed.');
    }
}
add_filter('impactshop_sharity_affiliate_prepare', 'impactshop_sharity_affiliate_prepare', 10, 2);

function impactshop_sharity_affiliate_mark_redirected($unused, $activationId)
{
    global $wpdb;
    if (!impactshop_sharity_affiliate_enabled() || !preg_match('/^act1_[a-f0-9]{32}$/', (string) $activationId)) {
        return false;
    }
    $now = time();
    $table = impactshop_sharity_affiliate_table();
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET status = 'redirected', redirected_at = %s, delete_after = %s
         WHERE activation_id = %s AND status = 'ready_to_redirect' AND intent_expires_at > %s",
        gmdate('Y-m-d H:i:s', $now),
        gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_RETENTION),
        $activationId,
        gmdate('Y-m-d H:i:s', $now)
    ));
    return $updated === 1;
}
add_filter('impactshop_sharity_affiliate_mark_redirected', 'impactshop_sharity_affiliate_mark_redirected', 10, 2);

function impactshop_sharity_affiliate_bearer($header)
{
    return preg_match('/^Bearer\s+(.+)$/i', trim((string) $header), $matches)
        ? trim((string) $matches[1])
        : '';
}

function impactshop_sharity_affiliate_service_authorized($request)
{
    $expected = trim((string) get_option('impactshop_sharity_web_session_service_token', ''));
    if ($expected === '' && defined('IMPACTSHOP_SHARITY_WEB_SESSION_SERVICE_TOKEN')) {
        $expected = trim((string) IMPACTSHOP_SHARITY_WEB_SESSION_SERVICE_TOKEN);
    }
    if (strlen($expected) < 32) {
        return false;
    }
    $actual = impactshop_sharity_affiliate_bearer(
        (string) $request->get_header('x-sharity-service-authorization')
    );
    return $actual !== '' && hash_equals($expected, $actual);
}

function impactshop_sharity_affiliate_session_context($request)
{
    $token = impactshop_sharity_affiliate_bearer((string) $request->get_header('authorization'));
    if (!preg_match('/^sw_session_[A-Za-z0-9_-]{43}$/', $token)) {
        return null;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impact_sharity_web_sessions';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT subject_ref, status, expires_at_utc FROM {$table} WHERE session_hash = %s LIMIT 1",
        hash('sha256', $token)
    ), ARRAY_A);
    if (!is_array($row) || (string) ($row['status'] ?? '') !== 'active'
        || strtotime((string) ($row['expires_at_utc'] ?? '') . ' UTC') <= time()
        || !preg_match('/^hmac-sha256:[a-f0-9]{64}$/', (string) ($row['subject_ref'] ?? ''))) {
        return null;
    }
    return ['subject_ref' => (string) $row['subject_ref']];
}

function impactshop_sharity_affiliate_cj_payload($request)
{
    $value = $request->get_json_params();
    if (!is_array($value)) {
        return null;
    }
    $expected = [
        'authoritySnapshotId', 'disclosureVersion', 'ngoSlug', 'partnerKey',
        'programRef', 'providerKey',
    ];
    $actual = array_keys($value);
    sort($actual);
    if ($actual !== $expected
        || (string) ($value['partnerKey'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER
        || (string) ($value['providerKey'] ?? '') !== 'cj'
        || (string) ($value['programRef'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM
        || (string) ($value['ngoSlug'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_NGO
        || (string) ($value['disclosureVersion'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_DISCLOSURE
        || !preg_match(
            '/^sharity-shopping-production-v2-[0-9]{8}-[0-9]{2}$/',
            (string) ($value['authoritySnapshotId'] ?? '')
        )) {
        return null;
    }
    return [
        'authority_snapshot_ref' => (string) $value['authoritySnapshotId'],
        'disclosure_version' => IMPACTSHOP_SHARITY_AFFILIATE_CJ_DISCLOSURE,
        'ngo_ref' => IMPACTSHOP_SHARITY_AFFILIATE_NGO,
        'partner_key' => IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER,
        'provider_key' => 'cj',
        'provider_program_ref' => IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM,
    ];
}

function impactshop_sharity_affiliate_private_response($data, $status)
{
    $response = new WP_REST_Response($data, $status);
    $response->header('Cache-Control', 'private, no-store');
    $response->header('Vary', 'Authorization, X-Sharity-Service-Authorization');
    $response->header('Referrer-Policy', 'no-referrer');
    return $response;
}

function impactshop_sharity_affiliate_cj_issue($request)
{
    // Strictly separate contracts: confirmed requests can never fall back to the fixed legacy NGO.
    $body = $request->get_json_params();
    if (is_array($body) && (isset($body['confirmationId']) || isset($body['ngoId']) || isset($body['merchantInput'])
        || ($body['disclosureVersion'] ?? '') === 'shopping-purchase-v1')) {
        return impactshop_shopping_confirmed_activate($request, 'cj');
    }
    if (!impactshop_sharity_affiliate_cj_enabled()) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'provider_disabled'], 403);
    }
    if (!impactshop_sharity_affiliate_service_authorized($request)) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $session = impactshop_sharity_affiliate_session_context($request);
    if (!is_array($session)) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'login_required'], 401);
    }
    $payload = impactshop_sharity_affiliate_cj_payload($request);
    if (!is_array($payload) || !impactshop_sharity_affiliate_install_schema()) {
        return impactshop_sharity_affiliate_private_response([
            'ok' => false,
            'error' => is_array($payload) ? 'schema_unavailable' : 'invalid_payload',
        ], is_array($payload) ? 503 : 400);
    }

    global $wpdb;
    $now = time();
    $activationId = 'act1_' . bin2hex(random_bytes(16));
    $providerToken = impactshop_sharity_affiliate_provider_token($activationId);
    $handoffToken = 'shp1_' . impactshop_sharity_affiliate_b64url(random_bytes(32));
    $secret = impactshop_sharity_affiliate_secret();
    if ($providerToken === '' || strlen($secret) < 32) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'issuer_unavailable'], 503);
    }
    $requestHash = hash_hmac(
        'sha256',
        "cj-request-v1\0" . $session['subject_ref'] . "\0" . $activationId,
        $secret
    );
    $inserted = $wpdb->insert(impactshop_sharity_affiliate_table(), [
        'activation_id' => $activationId,
        'provider_token_hash' => hash('sha256', $providerToken),
        'handoff_token_hash' => hash('sha256', $handoffToken),
        'request_key_hash' => $requestHash,
        'subject_ref' => $session['subject_ref'],
        'ngo_ref' => $payload['ngo_ref'],
        'partner_key' => $payload['partner_key'],
        'provider_key' => $payload['provider_key'],
        'provider_program_ref' => $payload['provider_program_ref'],
        'authority_snapshot_ref' => $payload['authority_snapshot_ref'],
        'disclosure_version' => $payload['disclosure_version'],
        'source_placement' => 'shopping-assistant',
        'status' => 'ready_to_redirect',
        'created_at' => gmdate('Y-m-d H:i:s', $now),
        'intent_expires_at' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
        'delete_after' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
    ], array_fill(0, 16, '%s'));
    if ($inserted !== 1) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'intent_issue_failed'], 503);
    }
    $handoffUrl = home_url('/wp-json/sharity/v1/shopping/cj-handoff/' . rawurlencode($handoffToken));
    return impactshop_sharity_affiliate_private_response([
        'ok' => true,
        'data' => ['handoff_url' => $handoffUrl],
    ], 201);
}

function impactshop_sharity_affiliate_cj_redirect_url($providerToken)
{
    if (!preg_match('/^sat1_[A-Za-z0-9_-]{43}$/', (string) $providerToken)) {
        return '';
    }
    $url = IMPACTSHOP_SHARITY_AFFILIATE_CJ_LINK . '?sid=' . rawurlencode((string) $providerToken);
    $parts = parse_url($url);
    parse_str((string) ($parts['query'] ?? ''), $query);
    if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'www.tkqlhce.com'
        || ($parts['path'] ?? '') !== '/click-101302202-15487360'
        || array_keys($query) !== ['sid'] || (string) $query['sid'] !== (string) $providerToken) {
        return '';
    }
    return $url;
}

function impactshop_sharity_affiliate_cj_handoff($request)
{
    if (!impactshop_sharity_affiliate_cj_enabled()) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'provider_disabled'], 403);
    }
    $handoffToken = (string) $request->get_param('handoffToken');
    if (!preg_match('/^shp1_[A-Za-z0-9_-]{43}$/', $handoffToken)) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'invalid_handoff'], 400);
    }

    global $wpdb;
    $table = impactshop_sharity_affiliate_table();
    $now = time();
    $guarded = property_exists($wpdb, 'reconnect_retries');
    $previousReconnect = $guarded ? $wpdb->reconnect_retries : null;
    try {
        if ($guarded) { $wpdb->reconnect_retries = 0; }
        $connection = $guarded ? (string) $wpdb->get_var('SELECT CONNECTION_ID()') : '';
        if (($guarded && ($connection === '' || $wpdb->last_error !== '')) || $wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('begin_failed');
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE handoff_token_hash = %s FOR UPDATE",
            hash('sha256', $handoffToken)
        ), ARRAY_A);
        if (!is_array($row) || !in_array((string) ($row['status'] ?? ''), ['ready_to_redirect', 'confirmed_cj_ready'], true)
            || strtotime((string) ($row['intent_expires_at'] ?? '') . ' UTC') <= $now
            || (string) ($row['partner_key'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER
            || (string) ($row['provider_key'] ?? '') !== 'cj'
            || (string) ($row['provider_program_ref'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM) {
            throw new RuntimeException('handoff_unavailable');
        }
        if (($row['source_placement'] ?? '') === 'shopping-confirmed-v1') {
            if ($row['status'] !== 'confirmed_cj_ready') { throw new RuntimeException('confirmed_state_mismatch'); }
            $review = impactshop_shopping_cj_review();
            if (!$guarded || !impactshop_shopping_purchase_enabled() || !impactshop_shopping_purchase_schema_ready()
                || (string) get_option('impactshop_sharity_affiliate_binding_schema', '') !== '1'
                || !$review || ($row['provider_scope_ref'] ?? '') !== $review['scopeRef']) {
                throw new RuntimeException('confirmed_review_unavailable');
            }
        } elseif ($row['status'] !== 'ready_to_redirect') {
            throw new RuntimeException('legacy_state_mismatch');
        }
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'redirected', redirected_at = %s, delete_after = %s
             WHERE activation_id = %s AND handoff_token_hash = %s
               AND status = %s AND source_placement = %s AND intent_expires_at > %s",
            gmdate('Y-m-d H:i:s', $now),
            gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_RETENTION),
            (string) $row['activation_id'],
            hash('sha256', $handoffToken),
            $row['status'],
            $row['source_placement'],
            gmdate('Y-m-d H:i:s', $now)
        ));
        $providerToken = impactshop_sharity_affiliate_provider_token((string) $row['activation_id']);
        $location = impactshop_sharity_affiliate_cj_redirect_url($providerToken);
        if ($updated !== 1 || $location === '') {
            throw new RuntimeException('handoff_transition_failed');
        }
        if (($guarded && ((string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection || $wpdb->last_error !== ''))
            || $wpdb->query('COMMIT') === false
            || ($guarded && ((string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection || $wpdb->last_error !== ''))) {
            throw new RuntimeException('commit_unconfirmed');
        }
        $response = new WP_REST_Response(null, 303);
        $response->header('Location', $location);
        $response->header('Cache-Control', 'private, no-store');
        $response->header('Referrer-Policy', 'no-referrer');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'handoff_unavailable'], 409);
    } finally {
        if ($guarded) { $wpdb->reconnect_retries = $previousReconnect; }
    }
}


/** New shopping lane: legacy producers never imply an explicit purchase confirmation. */
function impactshop_shopping_purchase_enabled()
{
    return impactshop_sharity_affiliate_enabled()
        && (string) get_option('impactshop_sharity_shopping_purchase_enabled', '0') === '1';
}

function impactshop_shopping_purchase_schema_ready()
{
    global $wpdb;
    $table = impactshop_sharity_affiliate_table();
    foreach (['request_payload_hash' => 'char(64)', 'provider_scope_ref' => 'varchar(96)'] as $name => $type) {
        $column = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $name), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($column) || ($column['Field'] ?? '') !== $name
            || strtolower((string) $column['Type']) !== $type
            || strtoupper((string) $column['Null']) !== 'YES') { return false; }
    }
    return true;
}

/** Explicit CLI migration; normal request handling never runs ALTER. */
function impactshop_shopping_purchase_migrate(): bool
{
    global $wpdb;
    $table = impactshop_sharity_affiliate_table();
    update_option('impactshop_sharity_affiliate_binding_schema', '0', false);
    foreach (['request_payload_hash' => 'CHAR(64)', 'provider_scope_ref' => 'VARCHAR(96)'] as $name => $type) {
        $column = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $name), ARRAY_A);
        if ($wpdb->last_error !== '') { return false; }
        if ($column === null && $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$name} {$type} NULL") === false) { return false; }
    }
    if (!impactshop_shopping_purchase_schema_ready()) { return false; }
    update_option('impactshop_sharity_affiliate_binding_schema', '1', false);
    return (string) get_option('impactshop_sharity_affiliate_binding_schema', '') === '1';
}

function impactshop_shopping_purchase_session($request)
{
    if (!impactshop_sharity_affiliate_service_authorized($request)
        || !is_callable(['ImpactShop_Sharity_Web_Session', 'authenticate_request'])) {
        return null;
    }
    $session = ImpactShop_Sharity_Web_Session::authenticate_request($request);
    return is_array($session) && preg_match('/^[a-f0-9]{64}$/D', (string) ($session['owner_grant_hash'] ?? ''))
        && preg_match('/^[a-z0-9]{10,12}$/D', (string) ($session['pseudo_id'] ?? '')) ? $session : null;
}

/** Canonical opaque NGO token; do not decode or regenerate catalogue slugs. */
function impactshop_shopping_valid_ngo_slug($slug): bool
{
    return is_string($slug) && strlen($slug) <= 128
        && preg_match('/^(?:[a-z0-9]|%[0-9a-f]{2})+(?:-(?:[a-z0-9]|%[0-9a-f]{2})+)*$/D', $slug) === 1;
}

function impactshop_shopping_ngos($request)
{
    global $wpdb;
    if (!impactshop_shopping_purchase_session($request)) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'login_required'], 401);
    }
    $rows = $wpdb->get_results("SELECT sharity_ngo_id, slug, name FROM {$wpdb->prefix}sharity_ngo_catalog WHERE is_active=1 ORDER BY name", ARRAY_A);
    if (!is_array($rows) || $wpdb->last_error !== '') {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'catalog_unavailable'], 503);
    }
    $ngos = [];
    foreach ($rows as $row) {
        if (preg_match('/^[1-9][0-9]*$/D', (string) $row['sharity_ngo_id'])
            && impactshop_shopping_valid_ngo_slug($row['slug'])) {
            $ngos[] = ['ngoId' => (string) $row['sharity_ngo_id'], 'slug' => (string) $row['slug'], 'name' => (string) $row['name']];
        }
    }
    return impactshop_sharity_affiliate_private_response(['ok' => true, 'data' => [
        'ngos' => $ngos, 'enabled' => impactshop_shopping_purchase_enabled() && impactshop_shopping_purchase_schema_ready(),
    ]], 200);
}

/** Exact supported base only: no short links, extra parameters or lossy product query parsing. */
function impactshop_shopping_dognet_binding($shop, $merchantUrl)
{
    if (!is_array($shop) || !is_string($merchantUrl) || strlen($merchantUrl) > 2048
        || preg_match('/[\x00-\x20\x7f\\\\]/', $merchantUrl) || preg_match('/%(?![0-9a-f]{2})/i', $merchantUrl)) {
        return null;
    }
    $url = parse_url($merchantUrl);
    $site = parse_url((string) ($shop['site'] ?? ''));
    if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
        || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])
        || !preg_match('/^[a-z0-9.-]+$/iD', $url['host'])
        || filter_var($url['host'], FILTER_VALIDATE_IP)
        || preg_replace('/^www\./i', '', strtolower($url['host'])) !== preg_replace('/^www\./i', '', strtolower($site['host'] ?? ''))
        || preg_match('/(^|\.)dognet\.(com|sk|hu)$/iD', $url['host'])) {
        return null;
    }
    foreach (explode('&', $url['query'] ?? '') as $part) {
        $key = strtolower(rawurldecode(explode('=', $part, 2)[0]));
        if (in_array($key, ['cid','chid','refid','d1','d2','data1','data2','data5','a_aid','a_bid','a_cid','sid'], true)) { return null; }
    }
    $base = parse_url((string) ($shop['dognet_base'] ?? ''));
    if (!is_array($base) || ($base['scheme'] ?? '') !== 'https' || ($base['host'] ?? '') !== 'go.dognet.com'
        || ($base['path'] ?? '/') !== '/' || isset($base['user']) || isset($base['pass']) || isset($base['port']) || isset($base['fragment'])
        || ($shop['deeplink_param'] ?? 'url') !== 'url' || !in_array($shop['network_source'] ?? '', ['', 'dognet'], true)) { return null; }
    $params = [];
    foreach (explode('&', $base['query'] ?? '') as $part) {
        $pair = explode('=', $part, 2);
        if (count($pair) !== 2 || !in_array($pair[0], ['cid','chid','refid'], true) || isset($params[$pair[0]])) { return null; }
        $params[$pair[0]] = rawurldecode($pair[1]);
    }
    if (count($params) !== 3 || !preg_match('/^[1-9][0-9]{0,19}$/D', $params['cid'] ?? '')
        || !preg_match('/^[A-Za-z0-9_-]{1,96}$/D', $params['chid'] ?? '')
        || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $params['refid'] ?? '')) { return null; }
    return ['cid' => $params['cid'], 'chid' => $params['chid'], 'refid' => $params['refid']];
}


function impactshop_dognet_verified_choice_enabled(string $shop): bool
{
    $enabled = get_option('impactshop_dognet_verified_ngo_shops', []);
    return is_array($enabled) && in_array($shop, $enabled, true);
}

/** Prepare and commit a Dognet marker for the exact selected NGO before redirecting. */
function impactshop_dognet_verified_redirect_url(array $shop, string $slug, string $targetUrl): string
{
    global $wpdb;
    if (!function_exists('impactshop_ngo_selector_dognet_choice')
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
        wp_die('Az NGO-választó jelenleg nem érhető el.', '', ['response' => 503]);
    }
    $choice = impactshop_ngo_selector_dognet_choice();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        wp_die('Érvénytelen vásárlási kérés.', '', ['response' => 400]);
    }
    $merchant = $targetUrl !== '' ? $targetUrl : (string) ($shop['site'] ?? '');
    $binding = impactshop_shopping_dognet_binding($shop, $merchant);
    $secret = impactshop_sharity_affiliate_secret();
    if ($binding === null || $secret === '' || !impactshop_sharity_affiliate_enabled() || !impactshop_shopping_purchase_schema_ready()) {
        wp_die('Ehhez az ajánlathoz jelenleg nincs ellenőrzött partnerlink.', '', ['response' => 503]);
    }
    $id = 'act1_' . bin2hex(random_bytes(16));
    $token = impactshop_sharity_affiliate_provider_token($id, $secret);
    $now = time();
    $table = impactshop_sharity_affiliate_table();
    $deadline = gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_RETENTION);
    $subject = 'hmac-sha256:' . hash_hmac('sha256', "dognet-guest-v1\0" . $choice['browser_ref'], $secret);
    $pseudo = $_COOKIE['impactshop_pseudo_id'] ?? null;
    if ((string) get_option('sharity_points_impactshop_producer_enabled', '0') === '1'
        && is_string($pseudo) && preg_match('/^[a-z0-9]{10,12}$/Di', $pseudo)
        && function_exists('impactshop_identity_owner_authorized')
        && impactshop_identity_owner_authorized(strtolower($pseudo))) {
        $bound = function_exists('sharity_points_impactshop_bind_subject')
            ? sharity_points_impactshop_bind_subject(strtolower($pseudo)) : null;
        if (empty($bound['success'])) {
            wp_die('A vásárlói azonosítás nem érhető el.', '', ['response' => 503]);
        }
        $subject = impactshop_sharity_affiliate_subject_ref($pseudo, $secret);
    }
    $record = [
        'activation_id' => $id, 'provider_token_hash' => hash('sha256', $token),
        'request_key_hash' => hash('sha256', $id),
        'subject_ref' => $subject,
        'ngo_ref' => $choice['id'], 'partner_key' => $slug,
        'provider_key' => 'dognet', 'provider_program_ref' => 'dognet:' . $binding['cid'],
        'provider_scope_ref' => $binding['chid'],
        'authority_snapshot_ref' => 'dognet-verified-ngo-v1',
        'disclosure_version' => IMPACTSHOP_SHARITY_AFFILIATE_CJ_DISCLOSURE,
        'source_placement' => 'dognet-verified-ngo-v1', 'status' => 'redirected',
        'created_at' => gmdate('Y-m-d H:i:s', $now), 'redirected_at' => gmdate('Y-m-d H:i:s', $now),
        'intent_expires_at' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
        'delete_after' => $deadline,
    ];
    $inserted = $wpdb->insert($table, $record);
    $persisted = $inserted === 1 ? $wpdb->get_row($wpdb->prepare(
        "SELECT ngo_ref, provider_scope_ref, status FROM {$table} WHERE activation_id=%s AND provider_token_hash=%s",
        $id, $record['provider_token_hash']
    ), ARRAY_A) : null;
    if ($wpdb->last_error !== '' || $persisted !== [
        'ngo_ref' => $choice['id'], 'provider_scope_ref' => $binding['chid'], 'status' => 'redirected',
    ]) {
        wp_die('A partnerjelölőt nem sikerült menteni.', '', ['response' => 503]);
    }
    $params = $binding + ['d1' => $token];
    if ($targetUrl !== '') {
        $params['url'] = $targetUrl;
    }
    nocache_headers();
    return add_query_arg($params, 'https://go.dognet.com/');
}

/** Fresh operator-reviewed evidence only. Request bodies never supply site/account scope. */
function impactshop_shopping_cj_review()
{
    $review = get_option('impactshop_sharity_affiliate_cj_purchase_review', null);
    if (!is_array($review)) { return null; }
    $publisher = getenv('CJ_PUBLISHER_ID');
    if ($publisher === false && defined('CJ_PUBLISHER_ID')) { $publisher = CJ_PUBLISHER_ID; }
    $website = getenv('CJ_WEBSITE_ID');
    if ($website === false && defined('CJ_WEBSITE_ID')) { $website = CJ_WEBSITE_ID; }
    $publisher = is_string($publisher) ? trim($publisher) : '';
    $website = is_string($website) ? trim($website) : '';
    if ($website === '') { $website = $publisher; }
    if (!preg_match('/^[1-9][0-9]{0,19}$/D', $publisher) || $website !== '101302202'
        || ($review['publisherId'] ?? null) !== $publisher || ($review['websiteId'] ?? null) !== $website
        || ($review['advertiserId'] ?? null) !== '5824323' || ($review['linkId'] ?? null) !== '15487360'
        || ($review['merchantUrl'] ?? null) !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_MERCHANT
        || !is_string($review['reviewExpiresAt'] ?? null)
        || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $review['reviewExpiresAt'])) { return null; }
    $expiry = strtotime($review['reviewExpiresAt']);
    if ($expiry === false || $expiry <= time() || gmdate('Y-m-d\TH:i:s\Z', $expiry) !== $review['reviewExpiresAt']) { return null; }
    return ['scopeRef' => $publisher . ':' . $website, 'merchantUrl' => $review['merchantUrl'], 'reviewExpiresAt' => $review['reviewExpiresAt']];
}

function impactshop_shopping_cj_handoff_token($activationId)
{
    $secret = impactshop_sharity_affiliate_secret();
    return strlen($secret) < 32 ? '' : 'shp1_' . impactshop_sharity_affiliate_b64url(hash_hmac('sha256', "shopping-cj-handoff-v1\0" . $activationId, $secret, true));
}

/** An operator review is evidence metadata, never a request-supplied permission or credential. */
function impactshop_shopping_tt_review($storedReview = null)
{
    if (!class_exists('ImpactShop_TradeTracker', false)) { return null; }
    $review = func_num_args() === 0 ? get_option('impactshop_sharity_affiliate_tt_purchase_review', null) : $storedReview;
    $customer = getenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');
    if ($customer === false && defined('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID')) { $customer = IMPACTSHOP_TRADETRACKER_CUSTOMER_ID; }
    if (!is_array($review) || !is_string($customer) || !preg_match('/^[1-9][0-9]{0,15}$/D', $customer)
        || ($review['customerId'] ?? null) !== $customer || ($review['siteId'] ?? null) !== '481183'
        || ($review['campaignId'] ?? null) !== '18982'
        || ($review['partnerKey'] ?? null) !== ImpactShop_TradeTracker::SHOPPING_PARTNER
        || ($review['programRef'] ?? null) !== ImpactShop_TradeTracker::SHOPPING_PROGRAM
        || ($review['merchantUrl'] ?? null) !== ImpactShop_TradeTracker::SHOPPING_MERCHANT
        || ($review['referenceReportVerified'] ?? null) !== true || ($review['donationCompatible'] ?? null) !== true
        || !is_string($review['qualificationRef'] ?? null)
        || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $review['qualificationRef'])
        || !is_int($review['retentionSeconds'] ?? null) || !is_int($review['requiredRetentionSeconds'] ?? null)
        || $review['requiredRetentionSeconds'] < 1 || $review['retentionSeconds'] < $review['requiredRetentionSeconds']
        || $review['retentionSeconds'] > IMPACTSHOP_SHARITY_AFFILIATE_RETENTION
        || !is_string($review['reviewExpiresAt'] ?? null)
        || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $review['reviewExpiresAt'])) { return null; }
    $expiry = strtotime($review['reviewExpiresAt']);
    if ($expiry === false || $expiry <= time() || gmdate('Y-m-d\TH:i:s\Z', $expiry) !== $review['reviewExpiresAt']) { return null; }
    return [
        'scopeRef' => $customer . ':481183', 'merchantUrl' => $review['merchantUrl'],
        'qualificationRef' => $review['qualificationRef'], 'reviewExpiresAt' => $review['reviewExpiresAt'],
        'retentionSeconds' => $review['retentionSeconds'], 'requiredRetentionSeconds' => $review['requiredRetentionSeconds'],
    ];
}

function impactshop_shopping_tt_enabled()
{
    return impactshop_shopping_purchase_enabled()
        && (string) get_option('impactshop_sharity_affiliate_tt_outbound_enabled', '0') === '1';
}

/** TT revocation cannot use WordPress's per-request option/alloptions cache. */
function impactshop_shopping_tt_current_review($lock = false)
{
    global $wpdb;
    $names = ['impactshop_sharity_affiliate_runtime_enabled', 'impactshop_sharity_shopping_purchase_enabled',
        'impactshop_sharity_affiliate_tt_outbound_enabled', 'impactshop_sharity_affiliate_tt_purchase_review'];
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_name IN (%s,%s,%s,%s) ORDER BY option_name"
        . ($lock ? ' FOR UPDATE' : ''), ...$names
    ), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($rows) || count($rows) !== 4) { return null; }
    $values = [];
    foreach ($rows as $row) {
        $name = $row['option_name'] ?? null;
        if (!is_string($name) || !in_array($name, $names, true) || isset($values[$name])
            || !is_string($row['option_value'] ?? null)) { return null; }
        $values[$name] = $row['option_value'];
    }
    foreach (array_slice($names, 0, 3) as $name) { if (($values[$name] ?? null) !== '1') { return null; } }
    $raw = $values[$names[3]];
    if (strlen($raw) > 4096) { return null; }
    // This option contains a bounded array of review metadata, never objects or credentials.
    return impactshop_shopping_tt_review(@unserialize($raw, ['allowed_classes' => false]));
}

function impactshop_shopping_purchase_result($row, $payloadHash, $binding, $merchantUrl, $expectedRow = null)
{
    if (!is_array($row) || !hash_equals((string) ($row['request_payload_hash'] ?? ''), $payloadHash)) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'idempotency_conflict'], 409);
    }
    if (!in_array($row['status'], ($row['provider_key'] ?? '') === 'cj' ? ['confirmed_cj_ready', 'redirected'] : ['redirected'], true)
        || strtotime($row['intent_expires_at'] . ' UTC') <= time()) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'confirmation_expired'], 410);
    }
    $token = impactshop_sharity_affiliate_provider_token($row['activation_id']);
    if ($token === '' || !hash_equals($row['provider_token_hash'], hash('sha256', $token))) {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'token_changed'], 409);
    }
    if ($expectedRow !== null) {
        foreach ($expectedRow as $field => $value) {
            if (($row[$field] ?? null) !== $value) {
                return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'binding_changed'], 409);
            }
        }
        if (($row['provider_key'] ?? '') !== 'tradetracker'
            || impactshop_shopping_tt_current_review() !== $binding || $merchantUrl !== $binding['merchantUrl']
            || strtotime((string) ($row['delete_after'] ?? '') . ' UTC') <= time()) {
            return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'provider_disabled'], 403);
        }
        try { $url = ImpactShop_TradeTracker::merchant_redirect_for_token($token); }
        catch (Throwable $error) { return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'token_changed'], 409); }
        return impactshop_sharity_affiliate_private_response(['ok' => true, 'redirectUrl' => $url], 200);
    }
    // TT is never permitted to enter the legacy Dognet result branch without its expected binding.
    if (($row['provider_key'] ?? '') === 'tradetracker') {
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'binding_changed'], 409);
    }
    if (($row['provider_key'] ?? '') === 'cj') {
        $handoff = impactshop_shopping_cj_handoff_token($row['activation_id']);
        if (($row['source_placement'] ?? '') !== 'shopping-confirmed-v1'
            || impactshop_shopping_cj_review() !== $binding
            || $handoff === '' || !hash_equals((string) ($row['handoff_token_hash'] ?? ''), hash('sha256', $handoff))
            || ($row['provider_scope_ref'] ?? '') !== $binding['scopeRef']) {
            return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'token_changed'], 409);
        }
        return impactshop_sharity_affiliate_private_response(['ok' => true, 'contract' => 'shopping-confirmed-v1',
            'data' => ['handoff_url' => home_url('/wp-json/sharity/v1/shopping/cj-handoff/' . $handoff)]], 200);
    }
    $url = 'https://go.dognet.com/?' . http_build_query($binding + ['url' => $merchantUrl, 'd1' => $token], '', '&', PHP_QUERY_RFC3986);
    return impactshop_sharity_affiliate_private_response(['ok' => true, 'redirectUrl' => $url], 200);
}

function impactshop_shopping_dognet_activate($request)
{
    return impactshop_shopping_confirmed_activate($request, 'dognet');
}

function impactshop_shopping_tt_activate($request)
{
    return impactshop_shopping_confirmed_activate($request, 'tradetracker');
}

/** Confirmed providers share the owner lock, selected NGO and durable idempotency boundary. */
function impactshop_shopping_confirmed_activate($request, $provider)
{
    global $wpdb;
    $fail = static function ($code, $status = 503) { return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => $code], $status); };
    if (!in_array($provider, ['dognet', 'cj', 'tradetracker'], true)) { return $fail('unsupported_partner', 422); }
    if (!impactshop_shopping_purchase_enabled()) { return $fail('activation_disabled', 403); }
    if ($provider === 'cj' && !impactshop_sharity_affiliate_cj_enabled()) { return $fail('provider_disabled', 403); }
    if ($provider === 'tradetracker' && !impactshop_shopping_tt_enabled()) { return $fail('provider_disabled', 403); }
    $session = impactshop_shopping_purchase_session($request);
    if (!$session) { return $fail('login_required', 401); }
    if (!impactshop_shopping_purchase_schema_ready()
        || (string) get_option('impactshop_sharity_affiliate_binding_schema', '') !== '1') { return $fail('schema_unavailable'); }
    $p = $request->get_json_params();
    $keys = is_array($p) ? array_keys($p) : []; sort($keys);
    if ($keys !== ['authoritySnapshotId','confirmationId','disclosureVersion','merchantInput','ngoId','partnerKey','programRef','providerKey']) { return $fail('invalid_request', 400); }
    foreach ($p as $v) { if (!is_string($v)) { return $fail('invalid_request', 400); } }
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $p['confirmationId'])
        || !preg_match('/^[1-9][0-9]{0,9}$/D', $p['ngoId']) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $p['partnerKey'])
        || strlen($p['partnerKey']) > 128 || $p['partnerKey'] === 'decathlon' || $p['providerKey'] !== $provider
        || strlen($p['programRef']) > 160
        || $p['disclosureVersion'] !== 'shopping-purchase-v1'
        || !preg_match('/^sharity-shopping-production-(?:v2|dognet)-[0-9]{8}-[0-9]{2}$/D', $p['authoritySnapshotId'])) { return $fail('invalid_request', 400); }
    // The authenticated BFF verifies current signed authority; WP independently binds its shop/campaign/target.
    if ($provider === 'cj') {
        $binding = impactshop_shopping_cj_review();
        if ($p['partnerKey'] !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER || $p['programRef'] !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM
            || !$binding || $p['merchantInput'] !== $binding['merchantUrl']
            || !preg_match('/^sharity-shopping-production-v2-[0-9]{8}-[0-9]{2}$/D', $p['authoritySnapshotId'])) { return $fail('unsupported_partner', 422); }
    } elseif ($provider === 'tradetracker') {
        $binding = impactshop_shopping_tt_review();
        if (!$binding || $p['partnerKey'] !== ImpactShop_TradeTracker::SHOPPING_PARTNER
            || $p['programRef'] !== ImpactShop_TradeTracker::SHOPPING_PROGRAM || $p['merchantInput'] !== $binding['merchantUrl']
            || !preg_match('/^sharity-shopping-production-v2-[0-9]{8}-[0-9]{2}$/D', $p['authoritySnapshotId'])) { return $fail('unsupported_partner', 422); }
    } else {
        if ($p['programRef'] !== 'dognet:' . $p['partnerKey']) { return $fail('invalid_request', 400); }
        $shops = function_exists('isb_get_shops') ? isb_get_shops() : [];
        $matches = array_values(array_filter($shops, static function ($s) use ($p) { return ($s['shop_slug'] ?? '') === $p['partnerKey']; }));
        $binding = count($matches) === 1 ? impactshop_shopping_dognet_binding($matches[0], $p['merchantInput']) : null;
    }
    if (!$binding) { return $fail('unsupported_partner', 422); }
    $subject = impactshop_sharity_affiliate_subject_ref($session['pseudo_id']);
    if ($subject === '' || !function_exists('sharity_points_impactshop_bind_subject')) { return $fail('identity_unavailable'); }
    // This helper owns its transaction; a harmless account binding may remain if the later intent fails.
    $bound = sharity_points_impactshop_bind_subject($session['pseudo_id']);
    if (empty($bound['success'])) { return $fail('identity_unavailable'); }
    $key = hash_hmac('sha256', "shopping-confirmed-v1\0" . $subject . "\0" . strtolower($p['confirmationId']), impactshop_sharity_affiliate_secret());
    $table = impactshop_sharity_affiliate_table();
    $payloadHash = null;
    $expectedRow = null;
    if (!property_exists($wpdb, 'reconnect_retries')) { return $fail('transaction_unavailable'); }
    $previousReconnect = $wpdb->reconnect_retries;
    $readbackSafe = true;
    try {
        $wpdb->reconnect_retries = 0;
        $connection = (string) $wpdb->get_var('SELECT CONNECTION_ID()');
        if ($connection === '' || $wpdb->last_error !== '' || $wpdb->reconnect_retries !== 0) { throw new RuntimeException('connection_guard_unavailable'); }
        if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('begin_failed'); }
        $locked = $wpdb->get_row($wpdb->prepare("SELECT pseudo_id, owner_grant_hash, status, expires_at_utc FROM {$wpdb->prefix}impact_sharity_web_sessions WHERE session_hash=%s FOR UPDATE", $session['session_hash']), ARRAY_A);
        if ($wpdb->last_error !== '' || !$locked || $locked['status'] !== 'active'
            || strtotime($locked['expires_at_utc'] . ' UTC') <= time() || $locked['pseudo_id'] !== $session['pseudo_id']
            || !hash_equals($session['owner_grant_hash'], (string) $locked['owner_grant_hash'])
            || ImpactShop_Sharity_Web_Session::owner_grant_active($locked['owner_grant_hash'], $locked['pseudo_id'], true) !== true) {
            $wpdb->query('ROLLBACK'); return $fail('login_required', 401);
        }
        $ngos = $wpdb->get_results($wpdb->prepare("SELECT slug FROM {$wpdb->prefix}sharity_ngo_catalog WHERE sharity_ngo_id=%s AND is_active=1 FOR UPDATE", $p['ngoId']), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($ngos)) { throw new RuntimeException('ngo_unavailable'); }
        if (count($ngos) !== 1 || !impactshop_shopping_valid_ngo_slug($ngos[0]['slug'])) {
            $wpdb->query('ROLLBACK'); return $fail('ngo_inactive', 422);
        }
        $payloadHash = hash('sha256', json_encode([$subject,$p['ngoId'],$ngos[0]['slug'],$p['partnerKey'],$p['programRef'],$binding,$p['merchantInput'],$p['disclosureVersion']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if ($provider === 'tradetracker') {
            $expectedRow = ['subject_ref'=>$subject, 'ngo_ref'=>$ngos[0]['slug'], 'partner_key'=>$p['partnerKey'],
                'provider_key'=>'tradetracker', 'provider_program_ref'=>$p['programRef'], 'provider_scope_ref'=>$binding['scopeRef'],
                'authority_snapshot_ref'=>$p['authoritySnapshotId'], 'source_placement'=>'shopping-confirmed-v1'];
            $payloadHash = hash('sha256', $payloadHash . "\0" . $p['authoritySnapshotId']);
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_key_hash = %s FOR UPDATE", $key), ARRAY_A);
        if ($wpdb->last_error !== '') { throw new RuntimeException('read_failed'); }
        if (!$row) {
            if (!impactshop_shopping_purchase_enabled() || ($provider === 'cj' && (!impactshop_sharity_affiliate_cj_enabled()
                || impactshop_shopping_cj_review() !== $binding)) || ($provider === 'tradetracker'
                && impactshop_shopping_tt_current_review(true) !== $binding)) {
                $wpdb->query('ROLLBACK'); return $fail('activation_disabled', 403);
            }
            $id = 'act1_' . bin2hex(random_bytes(16)); $now = time();
            $row = ['activation_id'=>$id, 'provider_token_hash'=>hash('sha256', impactshop_sharity_affiliate_provider_token($id)),
                'request_key_hash'=>$key, 'request_payload_hash'=>$payloadHash, 'subject_ref'=>$subject, 'ngo_ref'=>$ngos[0]['slug'],
                'partner_key'=>$p['partnerKey'], 'provider_key'=>$provider, 'provider_program_ref'=>$p['programRef'],
                'provider_scope_ref'=>in_array($provider, ['cj', 'tradetracker'], true) ? $binding['scopeRef'] : $binding['chid'],
                'authority_snapshot_ref'=>$p['authoritySnapshotId'], 'disclosure_version'=>$p['disclosureVersion'],
                'source_placement'=>'shopping-confirmed-v1', 'status'=>$provider === 'cj' ? 'confirmed_cj_ready' : 'redirected', 'created_at'=>gmdate('Y-m-d H:i:s',$now),
                'intent_expires_at'=>gmdate('Y-m-d H:i:s',$now+IMPACTSHOP_SHARITY_AFFILIATE_TTL),
                'redirected_at'=>$provider === 'cj' ? null : gmdate('Y-m-d H:i:s',$now),
                'delete_after'=>gmdate('Y-m-d H:i:s',$now+($provider === 'tradetracker' ? $binding['retentionSeconds'] : ($provider === 'cj' ? IMPACTSHOP_SHARITY_AFFILIATE_TTL : IMPACTSHOP_SHARITY_AFFILIATE_RETENTION)))];
            if ($provider === 'cj') { $row['handoff_token_hash'] = hash('sha256', impactshop_shopping_cj_handoff_token($id)); }
            if ($wpdb->insert($table, $row) !== 1) { throw new RuntimeException('insert_failed'); }
        }
        if ((string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection || $wpdb->last_error !== '') {
            $readbackSafe = false; throw new RuntimeException('connection_changed');
        }
        if ($wpdb->query('COMMIT') === false) { throw new RuntimeException('commit_unconfirmed'); }
        // A reconnect/ambiguous COMMIT must not release a URL from an uncommitted in-memory row.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_key_hash = %s", $key), ARRAY_A);
        if (!$row || $wpdb->last_error !== '') { return $fail('commit_unconfirmed'); }
        return impactshop_shopping_purchase_result($row, $payloadHash, $binding, $p['merchantInput'], $expectedRow);
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        // Readback only, never a second write: resolves unique-key races and a lost COMMIT reply.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_key_hash = %s", $key), ARRAY_A);
        if ($readbackSafe && $payloadHash !== null && $row && $wpdb->last_error === '') {
            return impactshop_shopping_purchase_result($row, $payloadHash, $binding, $p['merchantInput'], $expectedRow);
        }
        return $fail('activation_unconfirmed');
    } finally {
        $wpdb->reconnect_retries = $previousReconnect;
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity affiliate migrate-shopping', static function () {
        if (!impactshop_shopping_purchase_migrate()) { WP_CLI::error('Shopping schema migration failed.'); }
        WP_CLI::success('Shopping request hash and binding scope verified; activation unchanged.');
    });
}

function impactshop_sharity_affiliate_register_routes()
{
    register_rest_route('sharity/v1', '/shopping/ngos', ['methods'=>'GET', 'callback'=>'impactshop_shopping_ngos', 'permission_callback'=>'__return_true']);
    register_rest_route('sharity/v1', '/shopping/dognet-activate', ['methods'=>'POST', 'callback'=>'impactshop_shopping_dognet_activate', 'permission_callback'=>'__return_true']);
    register_rest_route('sharity/v1', '/shopping/tradetracker-activate', ['methods'=>'POST', 'callback'=>'impactshop_shopping_tt_activate', 'permission_callback'=>'__return_true']);
    register_rest_route('sharity/v1', '/shopping/cj-intent', [
        'methods' => 'POST',
        'callback' => 'impactshop_sharity_affiliate_cj_issue',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('sharity/v1', '/shopping/cj-handoff/(?P<handoffToken>shp1_[A-Za-z0-9_-]{43})', [
        'methods' => 'GET',
        'callback' => 'impactshop_sharity_affiliate_cj_handoff',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'impactshop_sharity_affiliate_register_routes');

function impactshop_sharity_affiliate_correlate($providerToken)
{
    global $wpdb;
    if (!preg_match('/^sat1_[A-Za-z0-9_-]{43}$/', (string) $providerToken)) {
        return null;
    }
    $table = impactshop_sharity_affiliate_table();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT activation_id, subject_ref, ngo_ref, partner_key, provider_key,
                provider_program_ref, source_placement, redirected_at, delete_after
         FROM {$table}
         WHERE provider_token_hash = %s AND status = 'redirected' AND delete_after > %s",
        hash('sha256', $providerToken),
        gmdate('Y-m-d H:i:s')
    ), ARRAY_A);
    if (!$row) {
        return null;
    }
    return array_merge($row, [
        'correlation_only' => true,
        'purchase_confirmed' => false,
        'commission_confirmed' => false,
        'settlement_authorized' => false,
    ]);
}

function impactshop_sharity_affiliate_retention_cleanup()
{
    global $wpdb;
    $table = impactshop_sharity_affiliate_table();
    if (!impactshop_sharity_affiliate_install_schema()) {
        return false;
    }
    $now = gmdate('Y-m-d H:i:s');
    $expired = $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET status = 'expired'
         WHERE status IN ('ready_to_redirect', 'confirmed_cj_ready') AND intent_expires_at <= %s",
        $now
    ));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table}
         WHERE (status IN ('expired', 'blocked') AND intent_expires_at <= %s)
            OR (status = 'redirected' AND delete_after <= %s)",
        $now,
        $now
    ));
    update_option('impactshop_sharity_affiliate_last_cleanup', [
        'at' => gmdate('c'),
        'expired' => max(0, (int) $expired),
        'deleted' => max(0, (int) $deleted),
    ], false);
    return true;
}
add_action(IMPACTSHOP_SHARITY_AFFILIATE_CRON, 'impactshop_sharity_affiliate_retention_cleanup');

function impactshop_sharity_affiliate_schedule()
{
    if (!impactshop_sharity_affiliate_enabled()) {
        return;
    }
    impactshop_sharity_affiliate_install_schema();
    if (!wp_next_scheduled(IMPACTSHOP_SHARITY_AFFILIATE_CRON)) {
        wp_schedule_event(time() + 300, 'daily', IMPACTSHOP_SHARITY_AFFILIATE_CRON);
    }
}
add_action('init', 'impactshop_sharity_affiliate_schedule', 3);
