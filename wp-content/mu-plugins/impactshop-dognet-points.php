<?php
/**
 * Plugin Name: ImpactShop Dognet Points
 * Description: Confirmed sales, exact affiliate donation, bounded resumable capture.
 */
if (!defined('ABSPATH')) { exit; }

/** Preserve JSON numeric lexemes before PHP can convert monetary values to float. */
function impactshop_dognet_points_decode(string $raw): array
{
    $exact = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/s',
        static fn(array $m): string => '"' . $m[0] . '"', $raw);
    $value = json_decode($exact ?? '', true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($value)) { throw new RuntimeException('dognet_response_invalid'); }
    return $value;
}

/** Dognet account wall time, with strict calendar and ambiguous DST rejection. */
function impactshop_dognet_points_utc($value): string
{
    if (!is_string($value)) { return ''; }
    $zone = new DateTimeZone('Europe/Bratislava');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) { return ''; }
    foreach ([-3600, 3600] as $delta) {
        if ($date->setTimestamp($date->getTimestamp() + $delta)->format('Y-m-d H:i:s') === $value) { return ''; }
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** Prefer frozen award, otherwise the first pending binding; no raw bearer is stored. */
function impactshop_dognet_points_snapshot(string $channel, string $purchase): ?array
{
    global $wpdb;
    $root = hash('sha256', wp_json_encode(['dognet', 'impactshop:dognet:' . $channel, $purchase]));
    $award = '%"kind":"award"%';
    $json = $wpdb->get_var($wpdb->prepare(
        "SELECT event_json FROM {$wpdb->prefix}impactshop_point_changes WHERE root_id=%s
         AND (event_json LIKE %s OR event_json LIKE %s) ORDER BY (event_json LIKE %s) DESC,id LIMIT 1",
        $root, $award, '%"kind":"pending"%', $award
    ));
    if ($wpdb->last_error !== '') { throw new RuntimeException('dognet_snapshot_unavailable'); }
    $event = is_string($json) ? json_decode($json, true) : null;
    return is_array($event) ? $event : null;
}

/** Authenticated sale authority is separate from SAT1 identity/cutover evidence. */
function impactshop_dognet_points_event(array $row, string $channel, ?array &$fx_days = null): array
{
    $id = is_scalar($row['id'] ?? null) ? (string) $row['id'] : '';
    $campaign = is_scalar($row['campaign_id'] ?? null) ? (string) $row['campaign_id'] : '';
    $order = is_string($row['order_id'] ?? null) ? $row['order_id'] : '';
    $purchase = $campaign . ':' . $order;
    if ($order === '' || strlen($purchase) > 256) { $purchase = 'unresolved:' . $id; }
    $token = is_string($row['data1'] ?? null) ? $row['data1'] : '';
    if (!preg_match('/^sat1_[A-Za-z0-9_-]{43}$/D', $token)) { $token = ''; }
    $status = is_string($row['rstatus'] ?? null) ? $row['rstatus'] : '';
    $event = ['provider' => 'dognet', 'namespace' => 'impactshop:dognet:' . $channel,
        'purchase_id' => $purchase, 'event_id' => $id, 'revision' => in_array($status, ['P','A','D'], true) ? $status : 'unknown',
        'original_purchase_at' => '', 'kind' => 'adjustment', 'donation_amount_huf' => '',
        'provider_token' => $token, 'merchant' => '', 'authority' => true, 'authority_ref' => '',
        'manual_reason' => 'dognet_sale_unverified', 'payload_ref' => '',
        'donation_basis' => wp_json_encode(['purchase' => $purchase])];
    if (strlen($event['donation_basis']) > 512) { unset($event['donation_basis']); }
    // A provider raw-id must not acquire a new root merely by changing order/status.
    global $wpdb;
    $logical = array_map(static fn(string $revision): string => hash('sha256', wp_json_encode(['dognet', $event['namespace'], $id, $revision])), ['P','A','D','unknown','final']);
    $priorJson = $wpdb->get_var($wpdb->prepare(
        "SELECT event_json FROM {$wpdb->prefix}impactshop_point_changes WHERE logical_id IN (%s,%s,%s,%s,%s) ORDER BY id LIMIT 1", ...$logical
    ));
    if ($wpdb->last_error !== '') { throw new RuntimeException('dognet_identity_read_unavailable'); }
    $prior = is_string($priorJson) ? json_decode($priorJson, true) : null;
    $root = hash('sha256', wp_json_encode(['dognet', $event['namespace'], $purchase]));
    if (is_array($prior) && ($prior['root_id'] ?? '') !== $root) {
        $oldBasis = json_decode($prior['donation_basis'] ?? '', true);
        $oldPurchase = is_string($oldBasis['purchase'] ?? null) ? $oldBasis['purchase'] : '';
        if ($oldPurchase !== '' && hash('sha256', wp_json_encode(['dognet', $event['namespace'], $oldPurchase])) !== ($prior['root_id'] ?? '')) { $oldPurchase = ''; }
        $event['purchase_id'] = $oldPurchase;
        $event['merchant'] = (string) ($prior['merchant'] ?? '');
        $event['original_purchase_at'] = (string) ($prior['original_purchase_at'] ?? '');
        $event['manual_reason'] = 'dognet_identity_changed';
        $event['payload_ref'] = hash('sha256', wp_json_encode([$campaign,$order,$row['rstatus'] ?? null]));
        return $event;
    }
    $snapshot = impactshop_dognet_points_snapshot($channel, $purchase);
    $basis = $snapshot ? json_decode($snapshot['donation_basis'] ?? '', true) : null;
    $correlation = $token !== '' && function_exists('impactshop_sharity_affiliate_correlate')
        ? impactshop_sharity_affiliate_correlate($token) : null;
    if ($snapshot) {
        $event['merchant'] = (string) $snapshot['merchant'];
        $event['original_purchase_at'] = (string) $snapshot['original_purchase_at'];
    } elseif (is_array($correlation) && ($correlation['provider_key'] ?? '') === 'dognet') {
        $event['merchant'] = (string) ($correlation['partner_key'] ?? '');
        $event['original_purchase_at'] = (string) ($correlation['redirected_at'] ?? '');
    }
    // Include economic/identity changes even in a manual event, never volatile metadata.
    $event['payload_ref'] = hash('sha256', wp_json_encode(array_intersect_key($row,
        array_flip(['id','campaign_id','ad_channel_id','order_id','rstatus','rtype','commission','total_cost','created_at','date_approved','data1']))));
    if (!ctype_digit($id) || $id === '0' || !ctype_digit($campaign) || $campaign === '0'
        || (string) ($row['ad_channel_id'] ?? '') !== $channel || $order === '' || strlen($campaign . ':' . $order) > 256) {
        $event['manual_reason'] = 'dognet_identity_unverified'; return $event;
    }
    $at = $event['original_purchase_at'];
    $cutover = (string) get_option('sharity_points_impactshop_cutover_utc', '');
    $created = impactshop_dognet_points_utc($row['created_at'] ?? null);
    $strict = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $at, new DateTimeZone('UTC'));
    if (!$strict || $strict->format('Y-m-d H:i:s') !== $at || $cutover === '' || $at < $cutover
        || $created === '' || $created < $at || $created > gmdate('Y-m-d H:i:s')) {
        $event['manual_reason'] = 'dognet_new_purchase_unverified'; return $event;
    }
    if ($snapshot) {
        if (!$basis || ($basis['raw'] ?? '') !== $id || ($basis['campaign'] ?? '') !== $campaign
            || ($snapshot['token_digest'] ?? hash('sha256', (string) ($snapshot['provider_token'] ?? ''))) !== hash('sha256', $token)) {
            $event['manual_reason'] = 'dognet_binding_changed'; return $event;
        }
    } else {
        $matched = false;
        if ($event['merchant'] !== '' && function_exists('impactshop_get_shops') && function_exists('dognet_extract_campaign_id_from_base')) {
            foreach (impactshop_get_shops() as $shop) {
                if (($shop['shop_slug'] ?? '') === $event['merchant']
                    && (string) dognet_extract_campaign_id_from_base($shop['dognet_base'] ?? '') === $campaign) { $matched = true; break; }
            }
        }
        if (!$matched) { $event['manual_reason'] = 'dognet_campaign_binding_unverified'; return $event; }
    }
    if (($row['rtype'] ?? '') !== 'S' || !in_array($status, ['P','A'], true)) { return $event; }
    $sale = sharity_points_money_parse($row['total_cost'] ?? null);
    if (!$sale['success'] || $sale['zero']) { return $event; }
    if ($basis && isset($basis['created']) && $basis['created'] !== $created) { $event['manual_reason'] = 'dognet_creation_changed'; return $event; }
    $binding = ['v' => 1, 'raw' => $id, 'campaign' => $campaign, 'purchase' => $purchase, 'created' => $created,
        'time_source' => 'sat1_redirect_lower_bound', 'actual_purchase_at' => 'unknown'];
    if ($status === 'P') {
        if ($snapshot && ($snapshot['kind'] ?? '') === 'award') { $event['manual_reason'] = 'dognet_status_regressed'; return $event; }
        $pendingBasis = wp_json_encode($binding);
        if (strlen($pendingBasis) > 512) {
            $event['manual_reason'] = 'dognet_basis_too_large'; unset($event['donation_basis']); return $event;
        }
        $event['kind'] = 'pending'; $event['manual_reason'] = '';
        $event['donation_basis'] = $pendingBasis;
        $event['payload_ref'] = hash('sha256', wp_json_encode([$id,$campaign,$order,$created]));
        return $event;
    }
    $commission = sharity_points_money_parse($row['commission'] ?? null);
    $sale = sharity_points_money_parse($row['total_cost'] ?? null);
    $final = impactshop_dognet_points_utc($row['date_approved'] ?? null);
    if (!$commission['success'] || !$sale['success'] || $commission['zero'] || $sale['zero']
        || $final === '' || $final < $created || $final > gmdate('Y-m-d H:i:s')) { return $event; }
    $decimal = static fn(array $n): string => str_contains($n['canonical'], '.') ? rtrim(rtrim($n['canonical'], '0'), '.') : $n['canonical'];
    $economics = ['commission' => $decimal($commission), 'sale' => $decimal($sale), 'final' => $final];
    if (($snapshot['kind'] ?? '') === 'award') {
        foreach ($economics as $key => $value) {
            if (($basis[$key] ?? null) !== $value) { $event['manual_reason'] = 'dognet_final_changed'; return $event; }
        }
        $event['donation_amount_huf'] = $snapshot['donation_amount_huf'];
        $event['donation_basis'] = $snapshot['donation_basis'];
        $event['authority_ref'] = $snapshot['authority_ref'];
    } else {
        $day = (new DateTimeImmutable($final, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d');
        $fx_days ??= [];
        if (!isset($fx_days[$day]) && count($fx_days) >= 4) { throw new RuntimeException('dognet_fx_budget'); }
        $fx_days[$day] = true;
        $quote = sharity_points_mnb_quote('EUR', str_replace(' ', 'T', $final) . 'Z');
        if (!$quote['success']) { throw new RuntimeException('dognet_mnb_unavailable'); }
        $money = sharity_points_money_affiliate_donation($economics['commission'], $quote['rate'], $quote['unit']);
        if (!$money['success'] || $money['points'] <= 0) { $event['manual_reason'] = 'dognet_donation_not_positive'; return $event; }
        $event['donation_amount_huf'] = (string) $money['points'];
        $event['authority_ref'] = 'affiliate50-mnb-v1:EUR:' . $quote['date'] . ':' . $quote['rate'] . '/' . $quote['unit'];
        $event['donation_basis'] = wp_json_encode($binding + $economics + ['fx' => [$quote['date'],$quote['rate'],$quote['unit']]]);
    }
    if (strlen($event['donation_basis']) > 512 || strlen($event['authority_ref']) > 160) {
        $event['manual_reason'] = 'dognet_basis_too_large'; unset($event['donation_basis']); return $event;
    }
    $event['kind'] = 'award'; $event['revision'] = 'final'; $event['manual_reason'] = '';
    $event['payload_ref'] = hash('sha256', wp_json_encode([$id,$campaign,$order,$created,$economics]));
    return $event;
}

/** Four pages per normal cron; repeat interrupted pages, and revisit completed sweeps. */
function impactshop_dognet_points_poll(): array
{
    if ((string) get_option('sharity_points_impactshop_producer_enabled', '0') !== '1'
        || (string) get_option('impactshop_dognet_points_scan_from', '') === '') {
        return ['success' => true, 'status' => 'disabled', 'captured' => 0];
    }
    global $wpdb;
    $channel = (string) get_option('impactshop_dognet_points_channel_id', '');
    $from = (string) get_option('impactshop_dognet_points_scan_from', '');
    $cutover = (string) get_option('sharity_points_impactshop_cutover_utc', '');
    if (!ctype_digit($channel) || (int) $channel <= 0 || $from !== substr($cutover, 0, 10)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $from)) {
        return ['success' => false, 'error' => 'dognet_scan_contract_unavailable'];
    }
    if (!function_exists('dognet_api_request') || !function_exists('sharity_points_impactshop_capture')) {
        return ['success' => false, 'error' => 'dognet_runtime_unavailable'];
    }
    $captured = 0; $fx_days = [];
    $lock = substr($wpdb->prefix . 'impactshop_dognet_points', 0, 64);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $lock)) !== 1) {
        return ['success' => true, 'status' => 'busy', 'captured' => 0];
    }
    try {
        $connection = (string) $wpdb->get_var('SELECT CONNECTION_ID()');
        $ownsLock = static function () use ($wpdb, $connection, $lock): bool {
            return (string) $wpdb->get_var('SELECT CONNECTION_ID()') === $connection
                && (string) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) === $connection;
        };
        $identity = ['v' => 1, 'channel' => $channel, 'cutover' => $cutover];
        $scan = get_option('impactshop_dognet_points_scan', null);
        if (is_array($scan) && array_intersect_key($scan, $identity) !== $identity) { throw new RuntimeException('dognet_scan_identity_changed'); }
        if (!is_array($scan) || ($scan['state'] ?? '') === 'completed') {
            $scan = $identity + ['upper' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Bratislava')))->format('Y-m-d H:i:s'),
                'last_id' => null, 'state' => 'active'];
        }
        if (!$ownsLock()) { throw new RuntimeException('dognet_lock_lost'); }
        update_option('impactshop_dognet_points_scan', $scan, false);
        if (get_option('impactshop_dognet_points_scan') !== $scan) { throw new RuntimeException('dognet_scan_unconfirmed'); }
        for ($page = 0; $page < 4; ++$page) {
            $body = ['per-page' => 200, 'sort' => 'id', 'filter' => [
                ['created_at' => ['gte' => $from . ' 00:00:00']], ['created_at' => ['lte' => $scan['upper']]],
                ['ad_channel_id' => ['eq' => (int) $channel]],
            ]];
            if ($scan['last_id'] !== null) { $body['last_id'] = $scan['last_id']; }
            $raw = dognet_api_request('POST', '/raw-transactions/filter', $body, false, true);
            if (!$ownsLock()) { throw new RuntimeException('dognet_lock_lost'); }
            if (is_wp_error($raw)) { throw new RuntimeException('dognet_provider_request_failed'); }
            $response = impactshop_dognet_points_decode($raw);
            $rows = $response['data'] ?? null;
            if (!is_array($rows) || !array_is_list($rows) || count($rows) > 200) { throw new RuntimeException('dognet_response_invalid'); }
            if (!$rows) {
                $scan['state'] = 'completed';
            } else {
                $last = (int) ($scan['last_id'] ?? 0); $deferred = '';
                foreach ($rows as $row) {
                    if (!is_array($row) || !ctype_digit((string) ($row['id'] ?? '')) || (int) $row['id'] <= $last) {
                        throw new RuntimeException('dognet_pagination_stalled');
                    }
                    $last = (int) $row['id'];
                    try { $event = impactshop_dognet_points_event($row, $channel, $fx_days); }
                    catch (RuntimeException $error) {
                        if (!in_array($error->getMessage(), ['dognet_fx_budget','dognet_mnb_unavailable'], true)) { throw $error; }
                        $deferred = $error->getMessage(); continue;
                    }
                    if (!$ownsLock()) { throw new RuntimeException('dognet_lock_lost'); }
                    $result = sharity_points_impactshop_capture($event);
                    if (!($result['success'] ?? false)) { throw new RuntimeException('dognet_capture_failed'); }
                    ++$captured;
                }
                if ($deferred !== '') {
                    return ['success' => $deferred === 'dognet_fx_budget', 'status' => 'in_progress', 'error' => $deferred, 'captured' => $captured];
                }
                if ((string) ($response['meta']['last_id'] ?? '') !== (string) $last) { throw new RuntimeException('dognet_pagination_invalid'); }
                $scan['last_id'] = $last;
            }
            if (!$ownsLock()) { throw new RuntimeException('dognet_lock_lost'); }
            update_option('impactshop_dognet_points_scan', $scan, false);
            if (get_option('impactshop_dognet_points_scan') !== $scan) { throw new RuntimeException('dognet_scan_unconfirmed'); }
            if ($scan['state'] === 'completed') { return ['success' => true, 'status' => 'completed', 'captured' => $captured]; }
        }
        return ['success' => true, 'status' => 'in_progress', 'captured' => $captured];
    } catch (Throwable $error) {
        return ['success' => false, 'error' => preg_match('/^dognet_[a-z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'dognet_capture_unconfirmed', 'captured' => $captured];
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}
