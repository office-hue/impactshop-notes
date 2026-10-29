<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Existing SAT1 correlation is identity evidence, never donation authority. */
function sharity_points_impactshop_attribution(string $token, string $provider, string $merchant): ?string
{
    if (!preg_match('/^sat1_[A-Za-z0-9_-]{43}$/D', $token) || $merchant === '') {
        return null;
    }
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT subject_ref, provider_key, partner_key, status, delete_after
         FROM {$wpdb->prefix}impactshop_affiliate_intents WHERE provider_token_hash=%s LIMIT 1",
        hash('sha256', $token)
    ), ARRAY_A);
    if (!is_array($row) || $row['provider_key'] !== $provider || $row['partner_key'] !== $merchant
        || $row['status'] !== 'redirected' || strtotime($row['delete_after'] . ' UTC') <= time()
        || !preg_match('/^hmac-sha256:[a-f0-9]{64}$/D', $row['subject_ref'])) {
        return null;
    }
    $digest = hash('sha256', $row['subject_ref']);
    $pseudo = $wpdb->get_var($wpdb->prepare(
        "SELECT pseudo_id FROM {$wpdb->prefix}user_points WHERE impactshop_subject_digest=UNHEX(%s) LIMIT 1", $digest
    ));
    return is_string($pseudo) && preg_match('/^[a-z0-9]{10,12}$/D', $pseudo) ? $digest : null;
}

/** Only call from a previously authenticated owner/session handoff. */
function sharity_points_impactshop_bind_subject(string $pseudo): array
{
    if (!preg_match('/^[a-z0-9]{10,12}$/D', $pseudo)
        || !function_exists('impactshop_sharity_affiliate_subject_ref')
        || !sharity_points_impactshop_schema_ready()) {
        return ['success' => false, 'error' => 'subject_binding_unavailable'];
    }
    $ref = impactshop_sharity_affiliate_subject_ref($pseudo);
    if (!preg_match('/^hmac-sha256:[a-f0-9]{64}$/D', $ref)) {
        return ['success' => false, 'error' => 'subject_binding_unavailable'];
    }
    $digest = hash('sha256', $ref);
    return (new Sharity_Points_Manager())->impactshop_transaction(function () use ($pseudo, $digest): array {
        global $wpdb;
        sharity_points_impactshop_sql($wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}user_points (pseudo_id, current_level, created_at, updated_at)
             VALUES (%s, 'basic', %s, %s) ON DUPLICATE KEY UPDATE pseudo_id=VALUES(pseudo_id)",
            $pseudo, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')
        )));
        $stored = sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare(
            "SELECT HEX(impactshop_subject_digest) FROM {$wpdb->prefix}user_points WHERE pseudo_id=%s FOR UPDATE", $pseudo
        )));
        if ($stored !== null && strtolower((string) $stored) !== $digest) {
            throw new RuntimeException('subject_binding_conflict');
        }
        sharity_points_impactshop_sql($wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}user_points SET impactshop_subject_digest=UNHEX(%s) WHERE pseudo_id=%s", $digest, $pseudo
        )));
        return ['success' => true, 'subject_digest' => $digest];
    });
}
