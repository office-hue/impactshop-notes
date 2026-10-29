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
    if ((string) get_option('sharity_points_legacy_consumer_enabled', '1') !== '1') {
        sharity_points_impactshop_consume();
        return;
    }
    sharity_points_sync_ledger_legacy();
}

function sharity_points_sync_ledger_legacy(): void
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

function sharity_points_handle_referral(string $pseudo_id, int $ledger_id, array $metadata): bool
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
        return $wpdb->last_error === '';
    }

    $manager = new Sharity_Points_Manager();
    $referrer_points = (int) apply_filters('sharity_referral_points', 200);
    $referred_points = (int) apply_filters('sharity_referral_bonus_points', 50);
    $referrer_tx = null;
    $referrer_ok = true;
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
        $referrer_ok = !empty($result['success']);
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
        $referrer_ok = !empty($result['success']);
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
    if (!$referrer_ok || empty($result['success'])) { return false; }

    $updated = $wpdb->update(
        $referrals_table,
        [
            'status' => 'completed',
            'first_purchase_at' => current_time('mysql'),
            'referrer_transaction_id' => $referrer_tx ?: null,
            'referred_transaction_id' => $referred_tx ?: null,
        ],
        ['id' => (int) $row['id']]
    );
    return $updated !== false && $wpdb->last_error === '';
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

function sharity_points_impactshop_schema_ready(): bool
{
    return (string) get_option('sharity_points_impactshop_schema', '') === '2026-10-29-simple1';
}

function sharity_points_impactshop_sql($value)
{
    global $wpdb;
    if ($value === false || $wpdb->last_error !== '') {
        throw new RuntimeException('point_storage_failure');
    }
    return $value;
}

/** Normalize authenticated adapter input; bearer tokens/raw provider bodies are not retained. */
function sharity_points_impactshop_prepare(array $input): array
{
    if (($input['authority'] ?? false) !== true) {
        return ['success' => false, 'error' => 'unauthenticated_event'];
    }
    foreach (['provider' => 16, 'namespace' => 96, 'purchase_id' => 256, 'event_id' => 256, 'revision' => 64, 'merchant' => 128] as $key => $limit) {
        if (!is_string($input[$key] ?? null) || strlen($input[$key]) > $limit) {
            return ['success' => false, 'error' => 'invalid_event_identity'];
        }
    }
    if (!in_array($input['provider'], ['cj', 'dognet', 'partner'], true)
        || $input['namespace'] === '' || $input['event_id'] === '' || $input['revision'] === '') {
        return ['success' => false, 'error' => 'invalid_event_identity'];
    }
    $kind = $input['kind'] ?? '';
    if (!in_array($kind, ['pending', 'award', 'refund', 'adjustment'], true)) {
        return ['success' => false, 'error' => 'invalid_event_kind'];
    }
    $original = (string) ($input['original_purchase_at'] ?? '');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.[0-9]{1,6})?(?:Z|\+00:00)?$/D', $original)
        ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', substr(str_replace('T', ' ', $original), 0, 19), new DateTimeZone('UTC')) : false;
    $original = $date && $date->format('Y-m-d H:i:s') === substr(str_replace('T', ' ', $original), 0, 19)
        ? $date->format('Y-m-d H:i:s') : '';
    $identity = [$input['provider'], $input['namespace'], $input['purchase_id']];
    $root = $input['purchase_id'] === '' ? null : hash('sha256', wp_json_encode($identity));
    $money = $kind === 'award' ? sharity_points_money_donation_huf_to_points($input['donation_amount_huf'] ?? '') : ['success' => true, 'points' => 0];
    $reason = (string) ($input['manual_reason'] ?? '');
    if ($reason !== '' && !preg_match('/^[a-z0-9_:-]{1,64}$/D', $reason)) {
        $reason = 'invalid_authority';
    }
    $token = is_string($input['provider_token'] ?? null) ? $input['provider_token'] : '';
    $event = [
        'root_id' => $root,
        'logical_id' => hash('sha256', wp_json_encode([$input['provider'], $input['namespace'], $input['event_id'], $input['revision']])),
        'provider' => $input['provider'], 'namespace' => $input['namespace'],
        'kind' => $kind, 'original_purchase_at' => $original, 'merchant' => $input['merchant'],
        'points' => $money['success'] ? $money['points'] : 0,
        'donation_amount_huf' => is_string($input['donation_amount_huf'] ?? null) || is_int($input['donation_amount_huf'] ?? null) ? (string) $input['donation_amount_huf'] : '',
        'payload_ref' => preg_match('/^[a-f0-9]{64}$/D', (string) ($input['payload_ref'] ?? '')) ? $input['payload_ref'] : '',
        'authority_ref' => substr((string) ($input['authority_ref'] ?? ''), 0, 160),
        'token_digest' => $token === '' ? '' : hash('sha256', $token),
        'reason' => $reason !== '' ? $reason : (!$money['success'] ? $money['error'] : ''),
    ];
    // The token is resolved only on first capture; retries can use the immutable root binding.
    $event['subject_digest'] = $token === '' ? null : sharity_points_impactshop_attribution($token, $input['provider'], $input['merchant']);
    $event['payload_digest'] = hash('sha256', wp_json_encode(array_diff_key($event, ['subject_digest' => true])));
    return ['success' => true, 'event' => $event];
}

function sharity_points_impactshop_capture(array $input): array
{
    if ((string) get_option('sharity_points_impactshop_producer_enabled', '0') !== '1') {
        return ['success' => false, 'error' => 'producer_disabled'];
    }
    if (!sharity_points_impactshop_schema_ready()) {
        return ['success' => false, 'error' => 'schema_unavailable'];
    }
    if ((string) get_option('sharity_points_legacy_consumer_enabled', '1') !== '0'
        || !sharity_points_impactshop_cutover()) {
        return ['success' => false, 'error' => 'cutover_unavailable'];
    }
    $prepared = sharity_points_impactshop_prepare($input);
    if (!$prepared['success']) {
        return $prepared;
    }
    $event = $prepared['event'];
    return (new Sharity_Points_Manager())->impactshop_transaction(function () use ($event): array {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        if ($event['root_id'] !== null) {
            sharity_points_impactshop_sql($wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}impactshop_point_settlements
                 (root_id, provider, namespace, subject_digest, original_purchase_at, merchant, updated_at)
                 VALUES (%s,%s,%s,UNHEX(NULLIF(%s,'')),NULLIF(%s,''),%s,%s)
                 ON DUPLICATE KEY UPDATE root_id=VALUES(root_id)",
                $event['root_id'], $event['provider'], $event['namespace'], $event['subject_digest'] ?? '',
                $event['original_purchase_at'], $event['merchant'], $now
            )));
            $root = sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare(
                "SELECT *, HEX(subject_digest) AS subject_hex FROM {$wpdb->prefix}impactshop_point_settlements WHERE root_id=%s FOR UPDATE", $event['root_id']
            ), ARRAY_A));
            if (!$root) {
                throw new RuntimeException('root_not_persisted');
            }
            $conflict = ($event['subject_digest'] !== null && $root['subject_hex'] !== null
                && strtolower($root['subject_hex']) !== $event['subject_digest'])
                || ($event['original_purchase_at'] !== '' && $root['original_purchase_at'] !== null && $event['original_purchase_at'] !== $root['original_purchase_at'])
                || ($event['merchant'] !== '' && $event['merchant'] !== $root['merchant']);
            if ($conflict) {
                sharity_points_impactshop_sql($wpdb->update($wpdb->prefix . 'impactshop_point_settlements', ['state' => 'manual'], ['root_id' => $event['root_id']]));
            }
        }
        sharity_points_impactshop_sql($wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}impactshop_point_changes
             (root_id,logical_id,payload_digest,event_json,created_at,updated_at)
             VALUES (NULLIF(%s,''),%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
            $event['root_id'] ?? '', $event['logical_id'], $event['payload_digest'], wp_json_encode($event), $now, $now
        )));
        $row = sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare(
            "SELECT id,status FROM {$wpdb->prefix}impactshop_point_changes WHERE logical_id=%s AND payload_digest=%s", $event['logical_id'], $event['payload_digest']
        ), ARRAY_A));
        if (!$row) {
            throw new RuntimeException('capture_unconfirmed');
        }
        return ['success' => true, 'event_id' => (int) $row['id'], 'status' => $row['status']];
    });
}

function sharity_points_impactshop_cutover(): ?string
{
    $value = (string) get_option('sharity_points_impactshop_cutover_utc', '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
}

/** Small explicit state machine: one award, one full reversal; every other correction is manual. */
function sharity_points_impactshop_decide(array $root, array $event, int $balance): array
{
    $manual = ['status' => 'manual', 'delta' => 0, 'forgiven' => 0, 'state' => 'manual'];
    if ($root['state'] === 'manual' || $event['reason'] !== '' || $root['subject_digest'] === null
        || !$root['original_purchase_at'] || $root['original_purchase_at'] < sharity_points_impactshop_cutover()) {
        return $manual + ['reason' => $event['reason'] ?: 'manual_review_required'];
    }
    if ($event['kind'] === 'pending' && $root['state'] === 'pending') {
        return ['status' => 'applied', 'delta' => 0, 'forgiven' => 0, 'state' => 'pending', 'reason' => 'pending_captured'];
    }
    if ($event['authority_ref'] === '') {
        return $manual + ['reason' => 'missing_donation_authority'];
    }
    if ($event['kind'] === 'award' && $root['state'] === 'pending' && $event['points'] > 0) {
        return ['status' => 'applied', 'delta' => $event['points'], 'forgiven' => 0, 'state' => 'awarded', 'reason' => ''];
    }
    if ($event['kind'] === 'refund' && $root['state'] === 'awarded') {
        $actual = min((int) $root['awarded_points'], max(0, $balance));
        return ['status' => 'applied', 'delta' => -$actual, 'forgiven' => (int) $root['awarded_points'] - $actual, 'state' => 'reversed', 'reason' => ''];
    }
    return $manual + ['reason' => 'complex_correction'];
}

function sharity_points_impactshop_consume(): void
{
    if ((string) get_option('sharity_points_impactshop_consumer_enabled', '0') !== '1'
        || (string) get_option('sharity_points_legacy_consumer_enabled', '1') !== '0'
        || !sharity_points_impactshop_schema_ready() || !sharity_points_impactshop_cutover()) {
        return;
    }
    global $wpdb;
    $lock = substr($wpdb->prefix . 'impactshop_points_consumer', 0, 64);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $lock)) !== 1) {
        return;
    }
    try {
        $connection = (string) sharity_points_impactshop_sql($wpdb->get_var('SELECT CONNECTION_ID()'));
        $ownsLock = static function () use ($wpdb, $lock, $connection): bool {
            return (string) $wpdb->get_var('SELECT CONNECTION_ID()') === $connection
                && (string) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) === $connection;
        };
        $run = get_option('sharity_points_impactshop_run', null);
        if (!is_array($run) || ($run['state'] ?? '') === 'completed') {
            $high = (int) sharity_points_impactshop_sql($wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}impactshop_point_changes"));
            $run = ['id' => bin2hex(random_bytes(16)), 'high_water' => $high, 'state' => 'active'];
            update_option('sharity_points_impactshop_run', $run, false);
            if (get_option('sharity_points_impactshop_run') !== $run) {
                throw new RuntimeException('run_not_persisted');
            }
        }
        $rows = sharity_points_impactshop_sql($wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}impactshop_point_changes
             WHERE id<=%d AND status IN ('queued','retry') AND (next_retry_at IS NULL OR next_retry_at<=UTC_TIMESTAMP())
             ORDER BY id LIMIT 200", $run['high_water']
        )));
        $manager = new Sharity_Points_Manager();
        foreach ($rows as $id) {
            if (!$ownsLock()) { return; }
            $result = $manager->apply_impactshop_event((int) $id, $run['id']);
            if (!$result['success']) {
                if (!$ownsLock()) { return; }
                // Unknown commit is resolved by the durable event outcome on the next cron; no immediate retry.
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}impactshop_point_changes SET status=IF(attempts>=2,'manual','retry'),
                     reason='transaction_unconfirmed', attempts=attempts+1, next_retry_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)
                     WHERE id=%d AND status IN ('queued','retry')", $id
                ));
            }
        }
        if (!$ownsLock()) { return; }
        $left = (int) sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}impactshop_point_changes WHERE id<=%d AND status IN ('queued','retry')", $run['high_water']
        )));
        if ($left === 0) {
            $run['state'] = 'completed';
            update_option('sharity_points_impactshop_run', $run, false);
        }
    } catch (Throwable $error) {
        do_action('sharity_points_error', 'impactshop_consumer_unconfirmed', []);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}
