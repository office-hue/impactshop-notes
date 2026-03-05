<?php

if (!defined('ABSPATH')) {
    exit;
}

function impactshop_ads_quarter_get_row(string $quarter_key): ?array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_ads_quarters';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE quarter_key = %s LIMIT 1",
        $quarter_key
    ), ARRAY_A);

    return $row ?: null;
}

function impactshop_ads_quarter_insert(string $quarter_key, int $pool_amount, string $status = 'active'): bool
{
    if ($quarter_key === '') {
        return false;
    }
    $row = impactshop_ads_quarter_get_row($quarter_key);
    if ($row) {
        return false;
    }

    $bounds = impactshop_ads_get_quarter_bounds($quarter_key);
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_ads_quarters';

    $inserted = $wpdb->insert($table, [
        'quarter_key' => $quarter_key,
        'start_at' => $bounds['start_at'],
        'end_at' => $bounds['end_at'],
        'pool_amount' => $pool_amount,
        'status' => $status,
    ], ['%s', '%s', '%s', '%d', '%s']);

    return $inserted !== false;
}

function impactshop_ads_quarter_start_internal(string $quarter_key, int $pool_amount = IMPACTSHOP_ADS_DONATION_POOL): array
{
    if ($quarter_key === '') {
        return ['ok' => false, 'error' => 'missing_quarter'];
    }

    $previous_key = (string) get_option('impactshop_ads_current_quarter', '');
    $row = impactshop_ads_quarter_get_row($quarter_key);
    if (!$row) {
        if (!impactshop_ads_quarter_insert($quarter_key, $pool_amount, 'active')) {
            return ['ok' => false, 'error' => 'insert_failed'];
        }
    }

    if ($previous_key !== '' && $previous_key !== $quarter_key) {
        $previous_row = impactshop_ads_quarter_get_row($previous_key);
        if ($previous_row && ($previous_row['status'] ?? '') === 'active') {
            global $wpdb;
            $table = $wpdb->prefix . 'impactshop_ads_quarters';
            $wpdb->update($table, [
                'status' => 'closing',
            ], ['quarter_key' => $previous_key], ['%s'], ['%s']);
        }
    }

    update_option('impactshop_ads_current_quarter', $quarter_key, false);
    impactshop_ads_watch_clear_tally_cache();
    impactshop_ads_clear_quarter_lock();

    return ['ok' => true, 'created' => !$row];
}

function impactshop_ads_quarter_prev_key_for_timestamp(int $timestamp): string
{
    $year = (int) gmdate('Y', $timestamp);
    $month = (int) gmdate('n', $timestamp);
    $quarter = (int) ceil($month / 3);
    $prev_quarter = $quarter - 1;
    if ($prev_quarter <= 0) {
        $prev_quarter = 4;
        $year -= 1;
    }
    return sprintf('%dQ%d', $year, $prev_quarter);
}

function impactshop_ads_quarter_notify_admin(string $quarter_key, array $result, string $context): void
{
    $email = (string) get_option('admin_email');
    if ($email === '') {
        return;
    }

    $ok = (bool) ($result['ok'] ?? false);
    $subject = $ok
        ? sprintf('[Impactshop] Quarter closed: %s', $quarter_key)
        : sprintf('[Impactshop] Quarter close failed: %s', $quarter_key);

    $payload = $result;
    $payload['quarter_key'] = $quarter_key;
    $payload['context'] = $context;
    $message = "Quarter close result:\n" . wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    wp_mail($email, $subject, $message);
}

function impactshop_ads_quarter_close_internal(string $quarter_key, bool $force = false): array
{
    if ($quarter_key === '') {
        return ['ok' => false, 'error' => 'missing_quarter'];
    }

    $row = impactshop_ads_quarter_get_row($quarter_key);
    if (!$row) {
        return ['ok' => false, 'error' => 'quarter_not_found'];
    }
    if (($row['status'] ?? '') === 'closed' && !$force) {
        return ['ok' => false, 'error' => 'already_closed'];
    }
    if (impactshop_ads_is_quarter_locked()) {
        return ['ok' => false, 'error' => 'lock_active'];
    }

    impactshop_ads_set_quarter_lock(60);
    global $wpdb;
    $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';
    $table_results = $wpdb->prefix . 'impactshop_ads_quarter_results';

    $wpdb->update($table_quarters, [
        'status' => 'closing',
    ], ['quarter_key' => $quarter_key], ['%s'], ['%s']);

    sleep(5);

    $tally = impactshop_ads_calculate_tally_with_info($quarter_key);
    $total_votes = array_sum(array_column($tally, 'votes'));
    $pool = impactshop_ads_get_pool_for_quarter($quarter_key);

    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_results} WHERE quarter_key = %s",
        $quarter_key
    ));
    if ($existing > 0 && !$force) {
        impactshop_ads_clear_quarter_lock();
        return ['ok' => false, 'error' => 'snapshot_exists'];
    }

    $wpdb->query('START TRANSACTION');
    if ($existing > 0) {
        $wpdb->delete($table_results, ['quarter_key' => $quarter_key], ['%s']);
    }

    $rank = 1;
    foreach ($tally as $row_item) {
        $votes = (int) ($row_item['votes'] ?? 0);
        $percentage = $total_votes > 0 ? round(($votes / $total_votes) * 100, 2) : 0;
        $amount = $total_votes > 0 ? round(($votes / $total_votes) * $pool) : 0;

        $wpdb->insert($table_results, [
            'quarter_key' => $quarter_key,
            'ngo_slug' => (string) ($row_item['ngo_slug'] ?? ''),
            'ngo_name' => (string) ($row_item['ngo_name'] ?? ''),
            'votes' => $votes,
            'percentage' => $percentage,
            'amount' => $amount,
            'rank' => $rank,
        ], ['%s', '%s', '%s', '%d', '%f', '%d', '%d']);
        $rank++;
    }

    $wpdb->update($table_quarters, [
        'status' => 'closed',
        'total_votes' => $total_votes,
        'pool_amount' => $pool,
        'closed_at' => gmdate('Y-m-d H:i:s'),
    ], ['quarter_key' => $quarter_key], ['%s', '%d', '%d', '%s'], ['%s']);

    if ($wpdb->last_error) {
        $wpdb->query('ROLLBACK');
        impactshop_ads_clear_quarter_lock();
        return ['ok' => false, 'error' => 'db_error', 'message' => $wpdb->last_error];
    }

    $wpdb->query('COMMIT');
    impactshop_ads_clear_quarter_lock();
    impactshop_ads_watch_clear_tally_cache();

    return [
        'ok' => true,
        'total_votes' => $total_votes,
        'pool' => $pool,
    ];
}

function impactshop_ads_quarter_key_for_close_timestamp(int $timestamp): string
{
    if (gmdate('Y-m-d', $timestamp) === '2026-07-01') {
        return '2026Q1';
    }
    return impactshop_ads_quarter_prev_key_for_timestamp($timestamp);
}

function impactshop_ads_next_close_timestamp(int $from_timestamp): int
{
    $custom_close = gmmktime(0, 2, 0, 7, 1, 2026);
    if ($from_timestamp < $custom_close) {
        return $custom_close;
    }

    $year = (int) gmdate('Y', $from_timestamp);
    $month = (int) gmdate('n', $from_timestamp);
    $quarter = (int) ceil($month / 3);
    $next_quarter = $quarter + 1;
    if ($next_quarter > 4) {
        $next_quarter = 1;
        $year += 1;
    }
    $next_month = ($next_quarter - 1) * 3 + 1;
    $next_timestamp = gmmktime(0, 2, 0, $next_month, 1, $year);

    if ($next_timestamp <= $from_timestamp) {
        $next_quarter += 1;
        if ($next_quarter > 4) {
            $next_quarter = 1;
            $year += 1;
        }
        $next_month = ($next_quarter - 1) * 3 + 1;
        $next_timestamp = gmmktime(0, 2, 0, $next_month, 1, $year);
    }

    return $next_timestamp;
}

function impactshop_ads_next_start_timestamp(int $from_timestamp): int
{
    $custom_start = gmmktime(0, 0, 0, 3, 1, 2026);
    $custom_end = gmmktime(23, 59, 59, 6, 30, 2026);
    if ($from_timestamp < $custom_start) {
        return $custom_start;
    }
    if ($from_timestamp <= $custom_end) {
        return gmmktime(0, 0, 0, 7, 1, 2026);
    }

    $year = (int) gmdate('Y', $from_timestamp);
    $month = (int) gmdate('n', $from_timestamp);
    $quarter = (int) ceil($month / 3);
    $next_quarter = $quarter + 1;
    if ($next_quarter > 4) {
        $next_quarter = 1;
        $year += 1;
    }
    $next_month = ($next_quarter - 1) * 3 + 1;
    $next_timestamp = gmmktime(0, 0, 0, $next_month, 1, $year);

    if ($next_timestamp <= $from_timestamp) {
        $next_quarter += 1;
        if ($next_quarter > 4) {
            $next_quarter = 1;
            $year += 1;
        }
        $next_month = ($next_quarter - 1) * 3 + 1;
        $next_timestamp = gmmktime(0, 0, 0, $next_month, 1, $year);
    }

    return $next_timestamp;
}

function impactshop_ads_quarter_get_upcoming_close_timestamps(int $count = 4): array
{
    $timestamps = [];
    $cursor = time();
    for ($i = 0; $i < $count; $i++) {
        $next = impactshop_ads_next_close_timestamp($cursor);
        $timestamps[] = $next;
        $cursor = $next + 60;
    }
    return $timestamps;
}

function impactshop_ads_quarter_get_upcoming_start_timestamps(int $count = 4): array
{
    $timestamps = [];
    $cursor = time();
    for ($i = 0; $i < $count; $i++) {
        $next = impactshop_ads_next_start_timestamp($cursor);
        $timestamps[] = $next;
        $cursor = $next + 60;
    }
    return $timestamps;
}

function impactshop_ads_quarter_schedule_wp_cron(): void
{
    if (!function_exists('wp_schedule_single_event')) {
        return;
    }

    $stale_close_timestamp = gmmktime(0, 5, 0, 4, 1, 2026);
    $stale_close_key = impactshop_ads_quarter_key_for_close_timestamp($stale_close_timestamp);
    wp_unschedule_event($stale_close_timestamp, 'impactshop_quarter_close_event', [$stale_close_key]);

    $stale_start_timestamp = gmmktime(0, 0, 0, 4, 1, 2026);
    $stale_start_key = impactshop_ads_get_current_quarter_key($stale_start_timestamp);
    wp_unschedule_event($stale_start_timestamp, 'impactshop_quarter_start_event', [$stale_start_key]);

    $close_events = impactshop_ads_quarter_get_upcoming_close_timestamps(4);
    foreach ($close_events as $timestamp) {
        $quarter_key = impactshop_ads_quarter_key_for_close_timestamp($timestamp);
        if (!wp_next_scheduled('impactshop_quarter_close_event', [$quarter_key])) {
            wp_schedule_single_event($timestamp, 'impactshop_quarter_close_event', [$quarter_key]);
        }
    }

    $start_events = impactshop_ads_quarter_get_upcoming_start_timestamps(4);
    foreach ($start_events as $timestamp) {
        $quarter_key = impactshop_ads_get_current_quarter_key($timestamp);
        if (!wp_next_scheduled('impactshop_quarter_start_event', [$quarter_key])) {
            wp_schedule_single_event($timestamp, 'impactshop_quarter_start_event', [$quarter_key]);
        }
    }
}

add_action('init', 'impactshop_ads_quarter_schedule_wp_cron');

function impactshop_ads_quarter_close_event_handler(string $quarter_key = ''): void
{
    $resolved_key = $quarter_key !== '' ? $quarter_key : impactshop_ads_get_active_quarter();
    $result = impactshop_ads_quarter_close_internal($resolved_key);
    if (!$result['ok']) {
        error_log('[impactshop_quarter] close_failed ' . wp_json_encode($result));
        impactshop_ads_quarter_notify_admin($resolved_key, $result, 'cron');
        return;
    }
    error_log('[impactshop_quarter] closed ' . wp_json_encode($result));
    impactshop_ads_quarter_notify_admin($resolved_key, $result, 'cron');
}

add_action('impactshop_quarter_close_event', 'impactshop_ads_quarter_close_event_handler', 10, 1);

function impactshop_ads_quarter_start_event_handler(string $quarter_key = ''): void
{
    $resolved_key = $quarter_key !== '' ? $quarter_key : impactshop_ads_get_current_quarter_key();
    $result = impactshop_ads_quarter_start_internal($resolved_key);
    if (!$result['ok']) {
        error_log('[impactshop_quarter] start_failed ' . wp_json_encode($result));
        return;
    }
    error_log('[impactshop_quarter] started ' . wp_json_encode($result));
}

add_action('impactshop_quarter_start_event', 'impactshop_ads_quarter_start_event_handler', 10, 1);

if (defined('WP_CLI') && WP_CLI) {
    class ImpactShop_Ads_Quarter_CLI
    {
        public function status(array $args, array $assoc): void
        {
            $current = impactshop_ads_get_active_quarter();
            $lock = impactshop_ads_is_quarter_locked() ? 'locked' : 'open';
            $row = impactshop_ads_quarter_get_row($current);
            WP_CLI::line('Current quarter: ' . $current);
            WP_CLI::line('Lock: ' . $lock);
            if ($row) {
                WP_CLI::line('Status: ' . ($row['status'] ?? 'n/a'));
                WP_CLI::line('Pool: ' . (int) ($row['pool_amount'] ?? 0));
                WP_CLI::line('Total votes: ' . (int) ($row['total_votes'] ?? 0));
            }
        }

        public function close(array $args, array $assoc): void
        {
            set_time_limit(300);
            $quarter_key = (string) ($assoc['quarter'] ?? '');
            $dry_run = isset($assoc['dry-run']);
            $force = isset($assoc['force']);

            if ($quarter_key === '') {
                $quarter_key = impactshop_ads_get_active_quarter();
            }

            if ($dry_run) {
                WP_CLI::log('Dry run: closing ' . $quarter_key);
                return;
            }

            $result = impactshop_ads_quarter_close_internal($quarter_key, $force);
            if (!$result['ok']) {
                impactshop_ads_quarter_notify_admin($quarter_key, $result, 'cli');
                WP_CLI::error('Quarter close failed: ' . ($result['error'] ?? 'unknown'));
            }
            impactshop_ads_quarter_notify_admin($quarter_key, $result, 'cli');
            WP_CLI::success('Quarter closed: ' . $quarter_key . ' (votes=' . ($result['total_votes'] ?? 0) . ')');
        }

        public function start(array $args, array $assoc): void
        {
            set_time_limit(300);
            $quarter_key = (string) ($assoc['quarter'] ?? '');
            $pool = (int) ($assoc['pool'] ?? IMPACTSHOP_ADS_DONATION_POOL);

            if ($quarter_key === '') {
                $quarter_key = impactshop_ads_get_current_quarter_key();
            }

            $result = impactshop_ads_quarter_start_internal($quarter_key, $pool);
            if (!$result['ok']) {
                WP_CLI::error('Quarter start failed: ' . ($result['error'] ?? 'unknown'));
            }

            if (!empty($result['created'])) {
                WP_CLI::success('Quarter started: ' . $quarter_key);
                return;
            }
            WP_CLI::success('Quarter already active: ' . $quarter_key);
        }

        public function mark_paid(array $args, array $assoc): void
        {
            $quarter_key = (string) ($assoc['quarter'] ?? '');
            $force = isset($assoc['force']);
            if ($quarter_key === '') {
                $quarter_key = impactshop_ads_get_active_quarter();
            }

            $row = impactshop_ads_quarter_get_row($quarter_key);
            if (!$row) {
                WP_CLI::error('Quarter not found: ' . $quarter_key);
            }
            $status = (string) ($row['status'] ?? '');
            if ($status === 'paid' && !$force) {
                WP_CLI::log('Quarter already paid: ' . $quarter_key);
                return;
            }
            if ($status !== 'closed' && !$force) {
                WP_CLI::error('Quarter not closed. Use --force to override.');
            }

            global $wpdb;
            $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';
            $updated = $wpdb->update($table_quarters, [
                'status' => 'paid',
                'paid_at' => gmdate('Y-m-d H:i:s'),
            ], ['quarter_key' => $quarter_key], ['%s', '%s'], ['%s']);

            if ($updated === false) {
                WP_CLI::error('Failed to mark quarter paid: ' . $quarter_key);
            }

            WP_CLI::success('Quarter marked paid: ' . $quarter_key);
        }

        public function rollback(array $args, array $assoc): void
        {
            $quarter_key = (string) ($assoc['quarter'] ?? '');
            $force = isset($assoc['force']);
            if ($quarter_key === '') {
                WP_CLI::error('Missing --quarter=YYYYQn');
            }

            $current = (string) get_option('impactshop_ads_current_quarter', '');
            if ($current === $quarter_key) {
                WP_CLI::log('Already on quarter: ' . $quarter_key);
                return;
            }

            global $wpdb;
            $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
            $table_quarters = $wpdb->prefix . 'impactshop_ads_quarters';

            $votes = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_votes} WHERE quarter_key = %s",
                $current
            ));
            if ($votes > 0 && !$force) {
                WP_CLI::error('Current quarter has votes. Use --force to override.');
            }

            $wpdb->delete($table_quarters, ['quarter_key' => $current], ['%s']);
            update_option('impactshop_ads_current_quarter', $quarter_key, false);
            impactshop_ads_watch_clear_tally_cache();

            WP_CLI::success('Rolled back to quarter: ' . $quarter_key);
        }

        public function export(array $args, array $assoc): void
        {
            $quarter_key = (string) ($assoc['quarter'] ?? '');
            if ($quarter_key === '') {
                $quarter_key = impactshop_ads_get_active_quarter();
            }

            global $wpdb;
            $table_results = $wpdb->prefix . 'impactshop_ads_quarter_results';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT quarter_key, ngo_slug, ngo_name, votes, percentage, amount, rank, created_at
                 FROM {$table_results}
                 WHERE quarter_key = %s
                 ORDER BY rank ASC",
                $quarter_key
            ), ARRAY_A);

            $output = fopen('php://output', 'w');
            fputcsv($output, ['quarter_key', 'ngo_slug', 'ngo_name', 'votes', 'percentage', 'amount', 'rank', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['quarter_key'],
                    $row['ngo_slug'],
                    $row['ngo_name'],
                    $row['votes'],
                    $row['percentage'],
                    $row['amount'],
                    $row['rank'],
                    $row['created_at'],
                ]);
            }
            fclose($output);
        }
    }

    WP_CLI::add_command('impactshop quarter', 'ImpactShop_Ads_Quarter_CLI');
}
