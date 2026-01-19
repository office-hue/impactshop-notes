<?php
/**
 * WHAT: Skeleton az ad-metrikák (NormalizedAdMetric) ledger-be írásához és dedup-hoz.
 * WHY: Az ads/sync pipeline a NormalizedAdMetric-et hívja; itt vezetjük be a wp_impact_ledger-be.
 * HOW: impact_ledger_upsert_metric($metric) – dedup event_hash alapján; helper delta ellenőrzéshez.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NormalizedAdMetric → ledger insert/upsert.
 * Elvárás: $metric tartalmazza platform, date, campaign_id, ad_id, views/clicks/spend/est_donation,
 *          ledger_source ('view'|'click'), optional ngo_code/advertiser_code/cap/meta.
 */
function impact_ledger_upsert_metric(array $metric)
{
    global $wpdb;
    $ledger_table = $wpdb->prefix . 'impact_ledger';

    // Minimális mező ellenőrzés
    $required = ['platform', 'date', 'ledger_source'];
    foreach ($required as $key) {
        if (empty($metric[$key])) {
            return new WP_Error('missing_field', "Hiányzik: $key");
        }
    }

    $source = $metric['ledger_source'] === 'click' ? 'click' : 'view';
    $event_hash = impact_ledger_event_hash($metric);
    $amount = isset($metric['est_donation']) ? floatval($metric['est_donation']) : 0;

    // Dedup: ha létezik event_id hash-sel, nem írjuk újra
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ledger_table WHERE event_id = %s LIMIT 1", $event_hash));
    if ($existing) {
        return intval($existing);
    }

    $row = [
        'source' => $source,
        'status' => 'approved',
        'platform' => sanitize_text_field($metric['platform']),
        'ngo_code' => $metric['ngo_code'] ?? null,
        'advertiser_code' => $metric['advertiser_code'] ?? null,
        'campaign_id' => $metric['campaign_id'] ?? null,
        'ad_id' => $metric['ad_id'] ?? null,
        'currency' => 'HUF',
        'amount_gross' => $amount,
        'amount_huf' => $amount,
        'exchange_rate' => 1,
        'payout_batch' => null,
        'event_id' => $event_hash,
        'meta' => wp_json_encode([
            'date' => $metric['date'] ?? null,
            'views' => $metric['views'] ?? null,
            'clicks' => $metric['clicks'] ?? null,
            'spend' => $metric['spend'] ?? null,
            'cap' => $metric['cap'] ?? null,
            'meta' => $metric['meta'] ?? null,
        ]),
        'created_at' => isset($metric['date']) ? $metric['date'] . ' 00:00:00' : current_time('mysql'),
    ];

    $wpdb->insert($ledger_table, $row);
    return intval($wpdb->insert_id);
}

/**
 * Delta ellenőrzés: adott hash létezik-e.
 */
function impact_ledger_event_exists(string $event_hash): bool
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE event_id = %s LIMIT 1", $event_hash));
    return !empty($id);
}

/**
 * Event hash generálás a platform+campaign+ad+date kombinációból.
 */
function impact_ledger_event_hash(array $metric): string
{
    $key = implode('|', [
        $metric['platform'] ?? '',
        $metric['campaign_id'] ?? '',
        $metric['ad_id'] ?? '',
        $metric['date'] ?? '',
        $metric['ledger_source'] ?? '',
    ]);
    return 'ads-' . md5($key);
}
