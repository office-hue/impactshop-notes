<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('sharity_daily_decay', 'sharity_run_points_decay');
add_action('sharity_hourly_leaderboard', 'sharity_refresh_leaderboard_cache');
add_action('sharity_daily_inactive_warnings', 'sharity_warn_inactive_users');
add_action('sharity_daily_referral_cleanup', 'sharity_expire_old_referrals');
add_action('sharity_points_sync_ledger', 'sharity_points_sync_ledger_hook');
add_action('sharity_daily_streaks', 'sharity_award_streak_bonuses');
add_action('sharity_yearly_reset', 'sharity_reset_yearly_freeze_count');

function sharity_run_points_decay(): void
{
    global $wpdb;
    $manager = new Sharity_Decay_Manager();

    $batch = max(1, (int) apply_filters('sharity_points_cron_batch_size', 500));
    $offset = 0;

    do {
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}user_points
             WHERE user_id IS NOT NULL
             LIMIT %d OFFSET %d",
            $batch,
            $offset
        ));

        foreach ($user_ids as $user_id) {
            $manager->calculate_and_apply_decay((int) $user_id);
        }

        $offset += $batch;
    } while (!empty($user_ids));

    $offset = 0;
    do {
        $pseudo_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT pseudo_id FROM {$wpdb->prefix}user_points
             WHERE pseudo_id IS NOT NULL
             LIMIT %d OFFSET %d",
            $batch,
            $offset
        ));

        foreach ($pseudo_ids as $pseudo_id) {
            $pseudo_id = is_string($pseudo_id) ? $pseudo_id : '';
            if ($pseudo_id === '') {
                continue;
            }
            $manager->calculate_and_apply_decay_for_pseudo($pseudo_id);
        }

        $offset += $batch;
    } while (!empty($pseudo_ids));
}

function sharity_refresh_leaderboard_cache(): void
{
    global $wpdb;

    $wpdb->query("DELETE FROM {$wpdb->prefix}leaderboard_cache WHERE expires_at < NOW()");

    $batch = max(1, (int) apply_filters('sharity_points_cron_batch_size', 500));
    $level_manager = new Sharity_Level_Manager();
    $rank = 0;
    $total = 0;

    for ($offset = 0; ; $offset += $batch) {
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, pseudo_id, points_total, current_level
             FROM {$wpdb->prefix}user_points
             WHERE points_total >= 100
             ORDER BY points_total DESC
             LIMIT %d OFFSET %d",
            $batch,
            $offset
        ));

        if (empty($users)) {
            break;
        }

        $total += count($users);
        $placeholders = [];
        $values = [];

        foreach ($users as $user) {
            $rank++;
            $user_id = isset($user->user_id) ? (int) $user->user_id : 0;
            $pseudo_id = isset($user->pseudo_id) ? (string) $user->pseudo_id : '';
            $current_level = isset($user->current_level) ? (string) $user->current_level : '';
            if ($user_id > 0) {
                $new_level = $level_manager->calculate_level($user_id);
                if ($new_level !== $current_level) {
                    $wpdb->update(
                        "{$wpdb->prefix}user_points",
                        ['current_level' => $new_level],
                        ['user_id' => $user_id]
                    );
                }
            } else {
                $new_level = $level_manager->calculate_level_for_pseudo($pseudo_id);
                if ($new_level !== $current_level) {
                    $wpdb->update(
                        "{$wpdb->prefix}user_points",
                        ['current_level' => $new_level],
                        ['pseudo_id' => $pseudo_id]
                    );
                }
            }

            if ($user_id > 0) {
                $placeholders[] = '(%d, NULL, %d, %d, %s, %s, %s)';
                array_push(
                    $values,
                    $user_id,
                    $rank,
                    (int) $user->points_total,
                    $new_level,
                    current_time('mysql'),
                    gmdate('Y-m-d H:i:s', strtotime('+1 hour'))
                );
            } else {
                $placeholders[] = '(NULL, %s, %d, %d, %s, %s, %s)';
                array_push(
                    $values,
                    $pseudo_id,
                    $rank,
                    (int) $user->points_total,
                    $new_level,
                    current_time('mysql'),
                    gmdate('Y-m-d H:i:s', strtotime('+1 hour'))
                );
            }
        }

        if (!empty($placeholders)) {
            $sql = "REPLACE INTO {$wpdb->prefix}leaderboard_cache
                (user_id, pseudo_id, rank_global, points_total, current_level, cached_at, expires_at)
                VALUES " . implode(',', $placeholders);
            $wpdb->query($wpdb->prepare($sql, $values));
        }
    }

    do_action('sharity_leaderboard_refreshed', $total);
}

function sharity_warn_inactive_users(): void
{
    global $wpdb;

    $inactive_users = $wpdb->get_results(
        "SELECT user_id, points_total, current_level, last_activity_at
         FROM {$wpdb->prefix}user_points
         WHERE DATEDIFF(NOW(), last_activity_at) = 5"
    );

    foreach ($inactive_users as $user) {
        do_action('sharity_decay_warning', (int) $user->user_id, (int) $user->points_total);
    }

    $inactive_pseudos = $wpdb->get_results(
        "SELECT pseudo_id, points_total, current_level, last_activity_at
         FROM {$wpdb->prefix}user_points
         WHERE user_id IS NULL
           AND pseudo_id IS NOT NULL
           AND DATEDIFF(NOW(), last_activity_at) = 5"
    );

    foreach ($inactive_pseudos as $row) {
        $pseudo_id = isset($row->pseudo_id) ? (string) $row->pseudo_id : '';
        if ($pseudo_id === '') {
            continue;
        }
        do_action('sharity_decay_warning_pseudo', $pseudo_id, (int) $row->points_total);
    }
}

function sharity_expire_old_referrals(): void
{
    $manager = new Sharity_Referral_Manager();
    $manager->expire_old_referrals();
}

function sharity_points_sync_ledger_hook(): void
{
    if (class_exists('ImpactShop_CJ')) {
        $result = ImpactShop_CJ::points_poll();
        if (empty($result['success'])) { do_action('sharity_points_error', 'cj_points_capture_incomplete', ['error' => $result['error']]); }
    }
    if (function_exists('impactshop_dognet_points_poll')
        && (string) get_option('impactshop_dognet_points_scan_from', '') !== '') {
        $result = impactshop_dognet_points_poll();
        if (empty($result['success'])) { do_action('sharity_points_error', 'dognet_points_capture_incomplete', []); }
    }
    if (function_exists('sharity_points_sync_ledger')) {
        sharity_points_sync_ledger();
    }
}

function sharity_reset_yearly_freeze_count(): void
{
    global $wpdb;
    $wpdb->query("UPDATE {$wpdb->prefix}user_points SET freeze_count_yearly = 0");
}

function sharity_award_streak_bonuses(): void
{
    global $wpdb;

    $manager = new Sharity_Points_Manager();
    $batch = max(1, (int) apply_filters('sharity_points_cron_batch_size', 500));

    for ($offset = 0; ; $offset += $batch) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, pseudo_id FROM {$wpdb->prefix}user_points
             LIMIT %d OFFSET %d",
            $batch,
            $offset
        ), ARRAY_A);

        if (empty($rows)) {
            break;
        }

        foreach ($rows as $row) {
            $user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            $pseudo_id = isset($row['pseudo_id']) ? (string) $row['pseudo_id'] : '';

            if ($user_id > 0) {
                sharity_award_streaks_for_user($user_id, $manager);
                continue;
            }

            if ($pseudo_id !== '') {
                sharity_award_streaks_for_pseudo($pseudo_id, $manager);
            }
        }
    }
}

function sharity_award_streaks_for_user(int $user_id, Sharity_Points_Manager $manager): void
{
    $weeks = sharity_collect_purchase_weeks($user_id, null);
    $months = sharity_collect_purchase_months($user_id, null);
    $now = current_time('timestamp');

    $weekly_key = wp_date('Y-m', $now);
    if (sharity_has_consecutive_weeks($weeks, 4, $now)) {
        $manager->award_points(
            $user_id,
            30,
            'streak_bonus',
            $weekly_key,
            ['source_type' => 'streak', 'period' => 'weekly', 'weeks' => array_values($weeks)],
            'streak_weekly:' . $weekly_key
        );
    }

    $monthly_key = wp_date('Y-m', $now);
    if (sharity_has_consecutive_months($months, 2, $now)) {
        $manager->award_points(
            $user_id,
            50,
            'streak_bonus',
            $monthly_key,
            ['source_type' => 'streak', 'period' => 'monthly', 'months' => array_values($months)],
            'streak_monthly:' . $monthly_key
        );
    }
}

function sharity_award_streaks_for_pseudo(string $pseudo_id, Sharity_Points_Manager $manager): void
{
    $weeks = sharity_collect_purchase_weeks(0, $pseudo_id);
    $months = sharity_collect_purchase_months(0, $pseudo_id);
    $now = current_time('timestamp');

    $weekly_key = wp_date('Y-m', $now);
    if (sharity_has_consecutive_weeks($weeks, 4, $now)) {
        $manager->award_points_for_pseudo(
            $pseudo_id,
            30,
            'streak_bonus',
            $weekly_key,
            ['source_type' => 'streak', 'period' => 'weekly', 'weeks' => array_values($weeks)],
            'streak_weekly:' . $weekly_key
        );
    }

    $monthly_key = wp_date('Y-m', $now);
    if (sharity_has_consecutive_months($months, 2, $now)) {
        $manager->award_points_for_pseudo(
            $pseudo_id,
            50,
            'streak_bonus',
            $monthly_key,
            ['source_type' => 'streak', 'period' => 'monthly', 'months' => array_values($months)],
            'streak_monthly:' . $monthly_key
        );
    }
}

function sharity_collect_purchase_weeks(int $user_id, ?string $pseudo_id): array
{
    global $wpdb;

    if ($user_id > 0) {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEARWEEK(created_at, 1)
             FROM {$wpdb->prefix}point_transactions
             WHERE user_id = %d
               AND type = 'purchase'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 35 DAY)",
            $user_id
        ));
    } else {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEARWEEK(created_at, 1)
             FROM {$wpdb->prefix}point_transactions
             WHERE pseudo_id = %s
               AND type = 'purchase'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 35 DAY)",
            $pseudo_id
        ));
    }

    return array_map('strval', $rows);
}

function sharity_collect_purchase_months(int $user_id, ?string $pseudo_id): array
{
    global $wpdb;

    if ($user_id > 0) {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE_FORMAT(created_at, '%%Y-%%m')
             FROM {$wpdb->prefix}point_transactions
             WHERE user_id = %d
               AND type = 'purchase'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 75 DAY)",
            $user_id
        ));
    } else {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE_FORMAT(created_at, '%%Y-%%m')
             FROM {$wpdb->prefix}point_transactions
             WHERE pseudo_id = %s
               AND type = 'purchase'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 75 DAY)",
            $pseudo_id
        ));
    }

    return array_map('strval', $rows);
}

function sharity_has_consecutive_weeks(array $weeks, int $needed, int $now): bool
{
    if (count($weeks) < $needed) {
        return false;
    }

    $target = [];
    $cursor = new DateTimeImmutable('@' . $now);
    $cursor = $cursor->setTimezone(wp_timezone());
    for ($i = 0; $i < $needed; $i++) {
        $target[] = $cursor->format('oW');
        $cursor = $cursor->modify('-1 week');
    }

    return empty(array_diff($target, $weeks));
}

function sharity_has_consecutive_months(array $months, int $needed, int $now): bool
{
    if (count($months) < $needed) {
        return false;
    }

    $target = [];
    $cursor = new DateTimeImmutable('@' . $now);
    $cursor = $cursor->setTimezone(wp_timezone());
    for ($i = 0; $i < $needed; $i++) {
        $target[] = $cursor->format('Y-m');
        $cursor = $cursor->modify('first day of last month');
    }

    return empty(array_diff($target, $months));
}

add_action('init', function (): void {
    add_filter('cron_schedules', function ($schedules) {
        if (!isset($schedules['sharity_quarter_hour'])) {
            $schedules['sharity_quarter_hour'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => 'Sharity 15 percenként',
            ];
        }
        if (!isset($schedules['sharity_yearly'])) {
            $schedules['sharity_yearly'] = [
                'interval' => YEAR_IN_SECONDS,
                'display' => 'Sharity évente',
            ];
        }
        return $schedules;
    });

    if (!wp_next_scheduled('sharity_daily_decay')) {
        wp_schedule_event(strtotime('today 00:00'), 'daily', 'sharity_daily_decay');
    }

    if (!wp_next_scheduled('sharity_hourly_leaderboard')) {
        wp_schedule_event(time(), 'hourly', 'sharity_hourly_leaderboard');
    }

    if (!wp_next_scheduled('sharity_daily_inactive_warnings')) {
        wp_schedule_event(strtotime('today 09:00'), 'daily', 'sharity_daily_inactive_warnings');
    }

    if (!wp_next_scheduled('sharity_daily_referral_cleanup')) {
        wp_schedule_event(strtotime('today 03:00'), 'daily', 'sharity_daily_referral_cleanup');
    }

    if (!wp_next_scheduled('sharity_points_sync_ledger')) {
        wp_schedule_event(time(), 'sharity_quarter_hour', 'sharity_points_sync_ledger');
    }

    if (!wp_next_scheduled('sharity_points_webhook_retry')) {
        wp_schedule_event(time(), 'sharity_quarter_hour', 'sharity_points_webhook_retry');
    }

    if (!wp_next_scheduled('sharity_daily_streaks')) {
        wp_schedule_event(strtotime('today 00:30'), 'daily', 'sharity_daily_streaks');
    }

    if (!wp_next_scheduled('sharity_yearly_reset')) {
        $next = strtotime('first day of january 00:00');
        if ($next <= time()) {
            $next = strtotime('first day of january next year 00:00');
        }
        wp_schedule_event($next, 'sharity_yearly', 'sharity_yearly_reset');
    }
});

/** Existing hooks/one-off bonuses resume after commit; economic writes remain deduplicated. */
function sharity_points_impactshop_reconcile(): void
{
    if (!sharity_points_impactshop_schema_ready()
        || (string) get_option('sharity_points_impactshop_consumer_enabled', '0') !== '1') {
        return;
    }
    global $wpdb;
    $roots = $wpdb->get_results("SELECT *, HEX(subject_digest) AS subject_hex
        FROM {$wpdb->prefix}impactshop_point_settlements
        WHERE needs_reconciliation=1 ORDER BY updated_at LIMIT 200", ARRAY_A);
    foreach ((array) $roots as $root) {
        try {
            $pseudo = sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare(
                "SELECT pseudo_id FROM {$wpdb->prefix}user_points WHERE impactshop_subject_digest=UNHEX(%s)", $root['subject_hex']
            )));
            if (!is_string($pseudo) || $pseudo === '') { continue; }
            $events = sharity_points_impactshop_sql($wpdb->get_results($wpdb->prepare(
                "SELECT id,event_json,outcome_json,transaction_id FROM {$wpdb->prefix}impactshop_point_changes
                 WHERE root_id=%s AND transaction_id IS NOT NULL AND status IN ('applied','resolved') ORDER BY id", $root['root_id']
            ), ARRAY_A));
            foreach ($events as $event) {
                $outcome = json_decode($event['outcome_json'], true, 32, JSON_THROW_ON_ERROR);
                if (!empty($outcome['callbacks_completed'])) { continue; }
                $type = isset($outcome['resolution']) ? 'admin_adjustment' : ($outcome['actual_delta'] > 0 ? 'purchase' : 'purchase_reversal');
                $metadata = ['pseudo_id' => $pseudo, 'transaction_id' => (int) $event['transaction_id'],
                    'new_total' => $outcome['new_total'], 'root_id' => $root['root_id'], 'source_type' => 'impactshop'];
                if (!empty($outcome['level_change']['changed'])) {
                    do_action('sharity_level_changed', $pseudo, $outcome['level_change']['old_level'], $outcome['level_change']['new_level'], $outcome['new_total']);
                }
                do_action('sharity_points_earned', null, $outcome['actual_delta'], $type, $metadata);
                $outcome['callbacks_completed'] = true;
                sharity_points_impactshop_sql($wpdb->update($wpdb->prefix . 'impactshop_point_changes', ['outcome_json' => wp_json_encode($outcome)], ['id' => $event['id']]));
            }
            $first = $shop = ['success' => true];
            $referral = true;
            if ((int) $root['awarded_points'] > 0) {
                $manager = new Sharity_Points_Manager();
                $metadata = ['source_type' => 'impactshop_bonus', 'root_id' => $root['root_id']];
                $first = $manager->award_points_for_pseudo($pseudo, 100, 'first_purchase', $root['root_id'], $metadata, 'first_purchase:' . $pseudo);
                $shop = $root['merchant'] === '' ? ['success' => true] : $manager->award_points_for_pseudo(
                    $pseudo, 25, 'shop_discovery', $root['root_id'], $metadata, 'shop_discovery:' . $pseudo . ':' . $root['merchant']
                );
                $referral = sharity_points_handle_referral($pseudo, 0, $metadata);
            }
            if (!empty($first['success']) && !empty($shop['success']) && $referral) {
                sharity_points_impactshop_sql($wpdb->update($wpdb->prefix . 'impactshop_point_settlements', ['needs_reconciliation' => 0], ['root_id' => $root['root_id'], 'version' => $root['version']]));
            }
        } catch (Throwable $error) {
            do_action('sharity_points_error', 'impactshop_reconciliation_pending', []);
        }
    }
}
add_action('sharity_points_sync_ledger', 'sharity_points_impactshop_reconcile', 20);

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity points exceptions list', function ($args, $assoc): void {
        global $wpdb;
        if (!sharity_points_impactshop_schema_ready()) { WP_CLI::error('Points schema unavailable.'); }
        $limit = max(1, min(200, (int) ($assoc['limit'] ?? 50)));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id,c.root_id,c.status,c.reason,c.created_at,s.version AS expected_version
             FROM {$wpdb->prefix}impactshop_point_changes c LEFT JOIN {$wpdb->prefix}impactshop_point_settlements s ON s.root_id=c.root_id
             WHERE c.status IN ('manual','held') ORDER BY c.id LIMIT %d", $limit
        ), ARRAY_A);
        WP_CLI::line(wp_json_encode($rows));
    });
    WP_CLI::add_command('sharity points exceptions show', function ($args): void {
        global $wpdb;
        if (!sharity_points_impactshop_schema_ready()) { WP_CLI::error('Points schema unavailable.'); }
        $id = (int) ($args[0] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}impactshop_point_changes WHERE id=%d", $id), ARRAY_A);
        if (!$row) { WP_CLI::error('Event not found.'); }
        $row['settlement'] = $row['root_id'] ? $wpdb->get_row($wpdb->prepare(
            "SELECT s.state,s.version,s.merchant,s.original_purchase_at,s.awarded_points,s.reversed_points,s.forgiven_points,
                    u.points_total AS current_balance,u.pseudo_id
             FROM {$wpdb->prefix}impactshop_point_settlements s
             LEFT JOIN {$wpdb->prefix}user_points u ON u.impactshop_subject_digest=s.subject_digest WHERE s.root_id=%s", $row['root_id']
        ), ARRAY_A) : null;
        $row['expected_version'] = (int) ($row['settlement']['version'] ?? 0);
        WP_CLI::line(wp_json_encode($row));
    });
}
