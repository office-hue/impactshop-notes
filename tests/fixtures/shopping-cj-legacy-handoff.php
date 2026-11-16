<?php
/** Frozen mixed-version reader from 5411f85ca707e905ee4bb5664d277d09b6e23d1e.
 * Source: wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php
 * Only the function name is changed; keep the legacy acceptance/transition logic exact.
 */
function shopping_test_legacy_cj_handoff($request)
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
    $wpdb->query('START TRANSACTION');
    try {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT activation_id, status, intent_expires_at, partner_key, provider_key,
                    provider_program_ref FROM {$table} WHERE handoff_token_hash = %s FOR UPDATE",
            hash('sha256', $handoffToken)
        ), ARRAY_A);
        if (!is_array($row) || (string) ($row['status'] ?? '') !== 'ready_to_redirect'
            || strtotime((string) ($row['intent_expires_at'] ?? '') . ' UTC') <= $now
            || (string) ($row['partner_key'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PARTNER
            || (string) ($row['provider_key'] ?? '') !== 'cj'
            || (string) ($row['provider_program_ref'] ?? '') !== IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM) {
            throw new RuntimeException('handoff_unavailable');
        }
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'redirected', redirected_at = %s, delete_after = %s
             WHERE activation_id = %s AND handoff_token_hash = %s
               AND status = 'ready_to_redirect' AND intent_expires_at > %s",
            gmdate('Y-m-d H:i:s', $now),
            gmdate('Y-m-d H:i:s', $now + IMPACTSHOP_SHARITY_AFFILIATE_RETENTION),
            (string) $row['activation_id'],
            hash('sha256', $handoffToken),
            gmdate('Y-m-d H:i:s', $now)
        ));
        $providerToken = impactshop_sharity_affiliate_provider_token((string) $row['activation_id']);
        $location = impactshop_sharity_affiliate_cj_redirect_url($providerToken);
        if ($updated !== 1 || $location === '') {
            throw new RuntimeException('handoff_transition_failed');
        }
        $wpdb->query('COMMIT');
        $response = new WP_REST_Response(null, 303);
        $response->header('Location', $location);
        $response->header('Cache-Control', 'private, no-store');
        $response->header('Referrer-Policy', 'no-referrer');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        return impactshop_sharity_affiliate_private_response(['ok' => false, 'error' => 'handoff_unavailable'], 409);
    }
}
