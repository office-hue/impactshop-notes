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
