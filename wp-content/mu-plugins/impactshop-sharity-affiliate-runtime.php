<?php
/**
 * Plugin Name: ImpactShop Sharity Affiliate Runtime
 * Description: Opaque affiliate-intent correlation for the Sharity Shopping Assistant.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_SHARITY_AFFILIATE_SCHEMA_VERSION = '1';
const IMPACTSHOP_SHARITY_AFFILIATE_TTL = 900;
const IMPACTSHOP_SHARITY_AFFILIATE_RETENTION = 3888000;
const IMPACTSHOP_SHARITY_AFFILIATE_CRON = 'impactshop_sharity_affiliate_retention_cleanup';

function impactshop_sharity_affiliate_enabled()
{
    return (string) get_option('impactshop_sharity_affiliate_runtime_enabled', '0') === '1';
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
    if (
        $shop === '' || $ngo === '' || strlen($shop) > 128 || strlen($ngo) > 128
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $shop)
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $ngo)
        || !preg_match('/^[A-Z0-9]{6,12}$/', $pseudo)
        || !in_array($source, ['shopping-assistant', 'vb2026-autobanner'], true)
        || $provider !== 'dognet'
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
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = impactshop_sharity_affiliate_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
      activation_id varchar(37) NOT NULL,
      provider_token_hash char(64) NOT NULL,
      request_key_hash char(64) NOT NULL,
      subject_ref varchar(80) NOT NULL,
      ngo_ref varchar(128) NOT NULL,
      partner_key varchar(128) NOT NULL,
      provider_key varchar(32) NOT NULL,
      provider_program_ref varchar(160) NOT NULL,
      source_placement varchar(64) NOT NULL,
      status varchar(24) NOT NULL,
      created_at datetime NOT NULL,
      intent_expires_at datetime NOT NULL,
      redirected_at datetime NULL,
      delete_after datetime NOT NULL,
      PRIMARY KEY  (activation_id),
      UNIQUE KEY provider_token_hash (provider_token_hash),
      UNIQUE KEY request_key_hash (request_key_hash),
      KEY retention (status, delete_after)
    ) {$charset};");
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return false;
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
                'request_key_hash' => $requestHash,
                'subject_ref' => $subject,
                'ngo_ref' => $context['ngo'],
                'partner_key' => $context['shop'],
                'provider_key' => $context['provider'],
                'provider_program_ref' => 'dognet:' . $context['shop'],
                'source_placement' => $context['source'],
                'status' => 'ready_to_redirect',
                'created_at' => gmdate('Y-m-d H:i:s', $now),
                'intent_expires_at' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
                'delete_after' => gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_TTL),
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
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
         WHERE status = 'ready_to_redirect' AND intent_expires_at <= %s",
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
