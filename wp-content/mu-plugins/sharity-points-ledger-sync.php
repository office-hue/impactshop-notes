<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SHARITY_POINTS_LEDGER_CURSOR_OPTION = 'sharity_points_ledger_cursor';
const SHARITY_POINTS_LEDGER_BATCH = 200;
const SHARITY_POINTS_LEDGER_PENDING_OPTION = 'sharity_points_ledger_pending_ids';
const SHARITY_POINTS_LEDGER_PENDING_LIMIT = 2000;
const SHARITY_POINTS_LEDGER_PENDING_BATCH = 200;

function sharity_points_sync_ledger(): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'impact_ledger';
    $cursor = (int) get_option(SHARITY_POINTS_LEDGER_CURSOR_OPTION, 0);
    $pending_ids = sharity_points_get_pending_ledger_ids();

    if (!empty($pending_ids)) {
        $batch_ids = array_slice($pending_ids, 0, SHARITY_POINTS_LEDGER_PENDING_BATCH);
        $placeholders = implode(',', array_fill(0, count($batch_ids), '%d'));
        $pending_rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT id, source, status, ngo_slug, advertiser_code, amount_huf, amount_net, amount_gross,
                       currency, exchange_rate, meta, created_at, event_id
                FROM {$table}
                WHERE id IN ({$placeholders})
            ",
                $batch_ids
            ),
            ARRAY_A
        );

        $pending_lookup = array_fill_keys($batch_ids, true);
        $manager = new Sharity_Points_Manager();

        foreach ($pending_rows as $row) {
            $ledger_id = (int) ($row['id'] ?? 0);
            if ($ledger_id <= 0) {
                continue;
            }
            unset($pending_lookup[$ledger_id]);

            $status = sanitize_key((string) ($row['status'] ?? ''));
            if ($status !== 'approved') {
                continue;
            }

            sharity_points_award_from_ledger_row($row, $manager);
            $pending_ids = array_values(array_diff($pending_ids, [$ledger_id]));
        }

        if (!empty($pending_lookup)) {
            $pending_ids = array_values(array_unique(array_merge($pending_ids, array_keys($pending_lookup))));
        }

        sharity_points_store_pending_ledger_ids($pending_ids);
    }

    $sources = apply_filters('sharity_points_ledger_sources', ['shop', 'donation']);
    $sources = array_values(array_filter(array_map('sanitize_key', (array) $sources)));
    if (empty($sources)) {
        $sources = ['shop'];
    }

    $placeholders = implode(',', array_fill(0, count($sources), '%s'));

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT id, source, status, ngo_slug, advertiser_code, amount_huf, amount_net, amount_gross,
                   currency, exchange_rate, meta, created_at, event_id
            FROM {$table}
            WHERE id > %d
              AND status IN ('approved','pending')
              AND source IN ({$placeholders})
            ORDER BY id ASC
            LIMIT %d
        ",
            array_merge([$cursor], $sources, [SHARITY_POINTS_LEDGER_BATCH])
        ),
        ARRAY_A
    );

    if (empty($rows)) {
        return;
    }

    $manager = new Sharity_Points_Manager();
    $max_id = $cursor;
    $pending_ids = sharity_points_get_pending_ledger_ids();

    foreach ($rows as $row) {
        $ledger_id = (int) ($row['id'] ?? 0);
        if ($ledger_id <= 0) {
            continue;
        }
        $max_id = max($max_id, $ledger_id);

        $status = sanitize_key((string) ($row['status'] ?? ''));
        if ($status !== 'approved') {
            $pending_ids[] = $ledger_id;
            continue;
        }

        sharity_points_award_from_ledger_row($row, $manager);
    }

    sharity_points_store_pending_ledger_ids($pending_ids);
    update_option(SHARITY_POINTS_LEDGER_CURSOR_OPTION, $max_id, false);
}

function sharity_points_award_from_ledger_row(array $row, Sharity_Points_Manager $manager): void
{
    $ledger_id = (int) ($row['id'] ?? 0);
    if ($ledger_id <= 0) {
        return;
    }

    $meta = sharity_points_decode_meta($row['meta'] ?? null);
    $pseudo_id = sharity_points_extract_pseudo_id($meta);
    if ($pseudo_id === '') {
        return;
    }

    $amount_huf = sharity_points_extract_amount_huf($row, $meta);
    if ($amount_huf <= 0) {
        return;
    }

    $ngo_slug = sanitize_title((string) ($row['ngo_slug'] ?? ($meta['ngo_slug'] ?? ($meta['d1'] ?? ''))));
    $shop_slug = sanitize_title((string) ($meta['shop_slug'] ?? ($meta['shop'] ?? ($row['advertiser_code'] ?? ''))));

    $metadata = [
        'source_type' => 'impact_ledger',
        'ledger_id' => $ledger_id,
        'status' => (string) ($row['status'] ?? ''),
        'source' => (string) ($row['source'] ?? ''),
        'ngo_slug' => $ngo_slug,
        'shop_slug' => $shop_slug,
        'amount_huf' => $amount_huf,
        'currency' => (string) ($row['currency'] ?? ''),
        'exchange_rate' => isset($row['exchange_rate']) ? (float) $row['exchange_rate'] : null,
    ];

    $result = $manager->award_points_for_pseudo(
        $pseudo_id,
        (int) round($amount_huf),
        'purchase',
        (string) $ledger_id,
        $metadata,
        'purchase:ledger:' . $ledger_id
    );
    if (empty($result['success'])) {
        do_action('sharity_points_error', 'ledger_award_failed', [
            'ledger_id' => $ledger_id,
            'pseudo_id' => $pseudo_id,
            'type' => 'purchase',
            'error' => $result['error'] ?? 'unknown',
        ]);
    }

    $result = $manager->award_points_for_pseudo(
        $pseudo_id,
        100,
        'first_purchase',
        (string) $ledger_id,
        $metadata,
        'first_purchase:' . $pseudo_id
    );
    if (empty($result['success'])) {
        do_action('sharity_points_error', 'ledger_award_failed', [
            'ledger_id' => $ledger_id,
            'pseudo_id' => $pseudo_id,
            'type' => 'first_purchase',
            'error' => $result['error'] ?? 'unknown',
        ]);
    }

    if ($shop_slug !== '') {
        $result = $manager->award_points_for_pseudo(
            $pseudo_id,
            25,
            'shop_discovery',
            (string) $ledger_id,
            $metadata,
            'shop_discovery:' . $pseudo_id . ':' . $shop_slug
        );
        if (empty($result['success'])) {
            do_action('sharity_points_error', 'ledger_award_failed', [
                'ledger_id' => $ledger_id,
                'pseudo_id' => $pseudo_id,
                'type' => 'shop_discovery',
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }

    sharity_points_handle_referral($pseudo_id, $ledger_id, $metadata);
}

function sharity_points_get_pending_ledger_ids(): array
{
    $pending = get_option(SHARITY_POINTS_LEDGER_PENDING_OPTION, []);
    if (!is_array($pending)) {
        return [];
    }

    $pending = array_values(array_unique(array_map('intval', $pending)));
    $pending = array_filter($pending, static function ($value) {
        return $value > 0;
    });

    return array_slice($pending, 0, SHARITY_POINTS_LEDGER_PENDING_LIMIT);
}

function sharity_points_store_pending_ledger_ids(array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_filter($ids, static function ($value) {
        return $value > 0;
    });

    if (count($ids) > SHARITY_POINTS_LEDGER_PENDING_LIMIT) {
        $ids = array_slice($ids, 0, SHARITY_POINTS_LEDGER_PENDING_LIMIT);
    }

    update_option(SHARITY_POINTS_LEDGER_PENDING_OPTION, $ids, false);
}

function sharity_points_handle_referral(string $pseudo_id, int $ledger_id, array $metadata): void
{
    global $wpdb;

    $referrals_table = $wpdb->prefix . 'user_referrals';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$referrals_table}
         WHERE referred_pseudo_id = %s
           AND status IN ('pending','active')
           AND (first_purchase_at IS NULL OR first_purchase_at = '')",
        $pseudo_id
    ), ARRAY_A);

    if (!$row) {
        return;
    }

    $manager = new Sharity_Points_Manager();
    $referrer_points = (int) apply_filters('sharity_referral_points', 200);
    $referred_points = (int) apply_filters('sharity_referral_bonus_points', 50);
    $referrer_tx = null;
    $referred_tx = null;

    $referrer_pseudo = isset($row['referrer_pseudo_id']) ? (string) $row['referrer_pseudo_id'] : '';
    $referrer_user = isset($row['referrer_user_id']) ? (int) $row['referrer_user_id'] : 0;

    if ($referrer_pseudo !== '') {
        $result = $manager->award_points_for_pseudo(
            $referrer_pseudo,
            $referrer_points,
            'referral',
            (string) $ledger_id,
            $metadata,
            'referral:' . $pseudo_id
        );
        $referrer_tx = $result['transaction_id'] ?? null;
    } elseif ($referrer_user > 0) {
        $result = $manager->award_points(
            $referrer_user,
            $referrer_points,
            'referral',
            (string) $ledger_id,
            $metadata,
            'referral:' . $pseudo_id
        );
        $referrer_tx = $result['transaction_id'] ?? null;
    }

    $result = $manager->award_points_for_pseudo(
        $pseudo_id,
        $referred_points,
        'referral_bonus',
        (string) $ledger_id,
        $metadata,
        'referral_bonus:' . $pseudo_id
    );
    $referred_tx = $result['transaction_id'] ?? null;

    $wpdb->update(
        $referrals_table,
        [
            'status' => 'completed',
            'first_purchase_at' => current_time('mysql'),
            'referrer_transaction_id' => $referrer_tx ?: null,
            'referred_transaction_id' => $referred_tx ?: null,
        ],
        ['id' => (int) $row['id']]
    );
}

function sharity_points_decode_meta($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sharity_points_extract_pseudo_id(array $meta): string
{
    $candidates = [
        $meta['pseudo_id'] ?? '',
        $meta['pseudo'] ?? '',
        $meta['d2'] ?? '',
        $meta['data2'] ?? '',
        $meta['impactshop_pseudo_id'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $candidate = strtolower(trim((string) $candidate));
        if ($candidate !== '' && preg_match('/^[a-z0-9]{10,12}$/', $candidate)) {
            return $candidate;
        }
    }

    return '';
}

function sharity_points_extract_amount_huf(array $row, array $meta): float
{
    $amount = 0.0;
    if (isset($row['amount_huf'])) {
        $amount = (float) $row['amount_huf'];
    } elseif (isset($meta['amount_huf'])) {
        $amount = (float) $meta['amount_huf'];
    } elseif (isset($row['amount_net'])) {
        $amount = (float) $row['amount_net'];
    } elseif (isset($row['amount_gross'])) {
        $amount = (float) $row['amount_gross'];
    }

    if ($amount > 0) {
        return $amount;
    }

    if (isset($meta['donation_huf'])) {
        return (float) $meta['donation_huf'];
    }

    return 0.0;
}
