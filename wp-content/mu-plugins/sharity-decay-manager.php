<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Decay_Manager
{
    public const GRACE_PERIOD_DAYS = 5;
    public const DECAY_RATES = [
        6 => 0.005,
        15 => 0.010,
        31 => 0.015,
        61 => 0.020,
    ];

    public function calculate_and_apply_decay(int $user_id): int
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT points_total, last_activity_at, level_locked_until, freeze_until
             FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        if (!$row || !$row->last_activity_at) {
            return 0;
        }

        if ($row->level_locked_until && strtotime($row->level_locked_until) > time()) {
            return 0;
        }

        if ($row->freeze_until && strtotime($row->freeze_until) > time()) {
            return 0;
        }

        $now = (int) current_time('timestamp');
        $days_inactive = (int) floor(($now - strtotime($row->last_activity_at)) / 86400);
        if ($days_inactive <= self::GRACE_PERIOD_DAYS) {
            return 0;
        }

        if (!empty($row->last_decay_check_at)) {
            $last_decay_day = date('Y-m-d', strtotime((string) $row->last_decay_check_at));
            if ($last_decay_day === current_time('Y-m-d')) {
                return 0;
            }
        }

        $rates = $this->get_decay_rates();
        $rate = 0.0;
        foreach ($rates as $threshold => $threshold_rate) {
            if ($days_inactive >= $threshold) {
                $rate = $threshold_rate;
            }
        }

        if ($rate <= 0) {
            return 0;
        }

        $points_before = (int) $row->points_total;
        $decay_amount = (int) round($points_before * $rate);
        $max_decay_abs = (int) apply_filters('sharity_decay_max_amount', 10000);
        $max_decay_percent = (float) apply_filters('sharity_decay_max_percent', 0.10);
        $max_decay = $max_decay_abs > 0 ? $max_decay_abs : PHP_INT_MAX;
        if ($max_decay_percent > 0) {
            $max_decay = min($max_decay, (int) floor($points_before * $max_decay_percent));
        }
        if ($max_decay > 0) {
            $decay_amount = min($decay_amount, $max_decay);
        }
        if ($decay_amount <= 0) {
            return 0;
        }

        $floor_points = (int) apply_filters('sharity_decay_floor_points', 100);
        if ($floor_points > 0 && $points_before > $floor_points) {
            $points_after = max($floor_points, $points_before - $decay_amount);
        } else {
            $points_after = max(0, $points_before - $decay_amount);
        }
        $decay_amount = max(0, $points_before - $points_after);
        if ($decay_amount <= 0) {
            return 0;
        }

        $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $wpdb->query('START TRANSACTION');

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}user_points
             SET points_total = %d,
                 points_decayed = points_decayed + %d,
                 last_decay_check_at = %s
             WHERE user_id = %d",
            $points_after,
            $decay_amount,
            current_time('mysql'),
            $user_id
        ));
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', [
                'user_id' => $user_id,
                'error' => $wpdb->last_error,
            ]);
            return 0;
        }

        $wpdb->insert("{$wpdb->prefix}decay_logs", [
            'user_id' => $user_id,
            'points_before' => $points_before,
            'points_after' => $points_after,
            'decay_amount' => $decay_amount,
            'decay_percentage' => $rate * 100,
            'days_inactive' => $days_inactive,
            'last_activity_date' => $row->last_activity_at,
            'applied' => 1,
            'created_at' => current_time('mysql'),
        ]);
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', [
                'user_id' => $user_id,
                'error' => $wpdb->last_error,
            ]);
            return 0;
        }

        $wpdb->insert("{$wpdb->prefix}point_transactions", [
            'user_id' => $user_id,
            'points' => -$decay_amount,
            'type' => 'decay',
            'dedupe_key' => 'decay:' . gmdate('Y-m-d'),
            'metadata' => wp_json_encode([
                'days_inactive' => $days_inactive,
                'rate' => $rate,
            ]),
            'created_at' => current_time('mysql'),
        ]);
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', [
                'user_id' => $user_id,
                'error' => $wpdb->last_error,
            ]);
            return 0;
        }

        $wpdb->query('COMMIT');

        do_action('sharity_points_decayed', $user_id, $decay_amount, $days_inactive);
        return $decay_amount;
    }

    public function calculate_and_apply_decay_for_pseudo(string $pseudo_id): int
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT points_total, last_activity_at, level_locked_until, freeze_until
             FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        if (!$row || !$row->last_activity_at) {
            return 0;
        }

        if ($row->level_locked_until && strtotime($row->level_locked_until) > time()) {
            return 0;
        }

        if ($row->freeze_until && strtotime($row->freeze_until) > time()) {
            return 0;
        }

        $now = (int) current_time('timestamp');
        $days_inactive = (int) floor(($now - strtotime($row->last_activity_at)) / 86400);
        if ($days_inactive <= self::GRACE_PERIOD_DAYS) {
            return 0;
        }

        if (!empty($row->last_decay_check_at)) {
            $last_decay_day = date('Y-m-d', strtotime((string) $row->last_decay_check_at));
            if ($last_decay_day === current_time('Y-m-d')) {
                return 0;
            }
        }

        $rates = $this->get_decay_rates();
        $rate = 0.0;
        foreach ($rates as $threshold => $threshold_rate) {
            if ($days_inactive >= $threshold) {
                $rate = $threshold_rate;
            }
        }

        if ($rate <= 0) {
            return 0;
        }

        $points_before = (int) $row->points_total;
        $decay_amount = (int) round($points_before * $rate);
        $max_decay_abs = (int) apply_filters('sharity_decay_max_amount', 10000);
        $max_decay_percent = (float) apply_filters('sharity_decay_max_percent', 0.10);
        $max_decay = $max_decay_abs > 0 ? $max_decay_abs : PHP_INT_MAX;
        if ($max_decay_percent > 0) {
            $max_decay = min($max_decay, (int) floor($points_before * $max_decay_percent));
        }
        if ($max_decay > 0) {
            $decay_amount = min($decay_amount, $max_decay);
        }
        if ($decay_amount <= 0) {
            return 0;
        }

        $floor_points = (int) apply_filters('sharity_decay_floor_points', 100);
        if ($floor_points > 0 && $points_before > $floor_points) {
            $points_after = max($floor_points, $points_before - $decay_amount);
        } else {
            $points_after = max(0, $points_before - $decay_amount);
        }
        $decay_amount = max(0, $points_before - $points_after);
        if ($decay_amount <= 0) {
            return 0;
        }

        $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $wpdb->query('START TRANSACTION');

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}user_points
             SET points_total = %d,
                 points_decayed = points_decayed + %d,
                 last_decay_check_at = %s
             WHERE pseudo_id = %s",
            $points_after,
            $decay_amount,
            current_time('mysql'),
            $pseudo_id
        ));
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', [
                'pseudo_id' => $pseudo_id,
                'error' => $wpdb->last_error,
            ]);
            return 0;
        }

        $wpdb->insert("{$wpdb->prefix}decay_logs", [
            'user_id' => null,
            'pseudo_id' => $pseudo_id,
            'points_before' => $points_before,
            'points_after' => $points_after,
            'decay_amount' => $decay_amount,
            'decay_percentage' => $rate * 100,
            'days_inactive' => $days_inactive,
            'last_activity_date' => $row->last_activity_at,
            'applied' => 1,
            'created_at' => current_time('mysql'),
        ]);
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', [
                'pseudo_id' => $pseudo_id,
                'error' => $wpdb->last_error,
            ]);
            return 0;
        }

        $wpdb->insert("{$wpdb->prefix}point_transactions", [
            'user_id' => null,
            'pseudo_id' => $pseudo_id,
            'points' => -$decay_amount,
            'type' => 'decay',
            'dedupe_key' => 'decay:' . gmdate('Y-m-d'),
            'metadata' => wp_json_encode([
                'days_inactive' => $days_inactive,
                'rate' => $rate,
            ]),
            'created_at' => current_time('mysql'),
        ]);
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', [
                'pseudo_id' => $pseudo_id,
                'error' => $wpdb->last_error,
            ]);
            return 0;
        }

        $wpdb->query('COMMIT');

        do_action('sharity_points_decayed', $pseudo_id, $decay_amount, $days_inactive);
        return $decay_amount;
    }

    private function get_decay_rates(): array
    {
        $rates = apply_filters('sharity_points_decay_rates', self::DECAY_RATES);
        if (!is_array($rates) || $rates === []) {
            return self::DECAY_RATES;
        }

        $clean = [];
        foreach ($rates as $threshold => $rate) {
            $threshold = (int) $threshold;
            $rate = (float) $rate;
            if ($threshold <= 0 || $rate <= 0) {
                continue;
            }
            $clean[$threshold] = $rate;
        }

        if ($clean === []) {
            return self::DECAY_RATES;
        }

        ksort($clean);
        return $clean;
    }
}
