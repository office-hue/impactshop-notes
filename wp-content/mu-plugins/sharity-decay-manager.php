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
        if ($user_id <= 0) {
            return 0;
        }
        return $this->calculate_and_apply_decay_subject('user_id', $user_id);
    }

    public function calculate_and_apply_decay_for_pseudo(string $pseudo_id): int
    {
        $pseudo_id = strtolower(trim($pseudo_id));
        if (!preg_match('/^[a-z0-9]{10,12}$/D', $pseudo_id)) {
            return 0;
        }
        return $this->calculate_and_apply_decay_subject('pseudo_id', $pseudo_id);
    }

    private function calculate_and_apply_decay_subject(string $column, $subject): int
    {
        global $wpdb;
        if (!property_exists($wpdb, 'reconnect_retries')) {
            return 0;
        }
        $previous_retries = $wpdb->reconnect_retries;
        try {
            $wpdb->reconnect_retries = 0;
            $connection = (string) $wpdb->get_var('SELECT CONNECTION_ID()');
            if ($wpdb->reconnect_retries !== 0 || $connection === '' || $wpdb->last_error !== '') {
                return 0;
            }
            return $this->calculate_and_apply_decay_subject_locked($column, $subject, $connection);
        } catch (Throwable $error) {
            try {
                $wpdb->query('ROLLBACK');
            } catch (Throwable $ignored) {
                // Lost connection remains unconfirmed; never claim success.
            }
            return 0;
        } finally {
            $wpdb->reconnect_retries = $previous_retries;
        }
    }

    private function calculate_and_apply_decay_subject_locked(string $column, $subject, string $connection): int
    {
        global $wpdb;
        $format = $column === 'user_id' ? '%d' : '%s';
        if ($wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED') === false
            || $wpdb->query('START TRANSACTION') === false) {
            return 0;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT points_total, last_activity_at, last_decay_check_at, level_locked_until, freeze_until
             FROM {$wpdb->prefix}user_points WHERE {$column} = {$format} FOR UPDATE",
            $subject
        ));
        if ($wpdb->last_error !== '' || !$row || !$row->last_activity_at
            || ($row->level_locked_until && strtotime($row->level_locked_until) > time())
            || ($row->freeze_until && strtotime($row->freeze_until) > time())
            || (!empty($row->last_decay_check_at)
                && date('Y-m-d', strtotime((string) $row->last_decay_check_at)) === current_time('Y-m-d'))) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        $days_inactive = (int) floor(((int) current_time('timestamp') - strtotime($row->last_activity_at)) / 86400);
        $rate = 0.0;
        foreach ($this->get_decay_rates() as $threshold => $threshold_rate) {
            if ($days_inactive >= $threshold) {
                $rate = $threshold_rate;
            }
        }
        if ($days_inactive <= self::GRACE_PERIOD_DAYS || $rate <= 0) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        $points_before = (int) $row->points_total;
        $decay_amount = (int) round($points_before * $rate);
        $max_decay = (int) apply_filters('sharity_decay_max_amount', 10000);
        $max_percent = (float) apply_filters('sharity_decay_max_percent', 0.10);
        $max_decay = $max_decay > 0 ? $max_decay : PHP_INT_MAX;
        if ($max_percent > 0) {
            $max_decay = min($max_decay, (int) floor($points_before * $max_percent));
        }
        if ($max_decay > 0) {
            $decay_amount = min($decay_amount, $max_decay);
        }
        $floor = (int) apply_filters('sharity_decay_floor_points', 100);
        $points_after = $floor > 0 && $points_before > $floor
            ? max($floor, $points_before - $decay_amount)
            : max(0, $points_before - $decay_amount);
        $decay_amount = max(0, $points_before - $points_after);
        if ($decay_amount <= 0) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}user_points
             SET points_total = points_total - %d,
                 points_decayed = points_decayed + %d,
                 last_decay_check_at = %s
             WHERE {$column} = {$format}",
            $decay_amount,
            $decay_amount,
            current_time('mysql'),
            $subject
        ));
        $log = [
            'user_id' => $column === 'user_id' ? (int) $subject : null,
            'pseudo_id' => $column === 'pseudo_id' ? (string) $subject : null,
            'points_before' => $points_before,
            'points_after' => $points_after,
            'decay_amount' => $decay_amount,
            'decay_percentage' => $rate * 100,
            'days_inactive' => $days_inactive,
            'last_activity_date' => $row->last_activity_at,
            'applied' => 1,
            'created_at' => current_time('mysql'),
        ];
        $logged = $updated === 1 ? $wpdb->insert("{$wpdb->prefix}decay_logs", $log) : false;
        $transaction = [
            'user_id' => $column === 'user_id' ? (int) $subject : null,
            'pseudo_id' => $column === 'pseudo_id' ? (string) $subject : null,
            'points' => -$decay_amount,
            'type' => 'decay',
            'dedupe_key' => 'decay:' . gmdate('Y-m-d'),
            'metadata' => wp_json_encode(['days_inactive' => $days_inactive, 'rate' => $rate]),
            'created_at' => current_time('mysql'),
        ];
        $recorded = $logged === 1 ? $wpdb->insert("{$wpdb->prefix}point_transactions", $transaction) : false;
        if ($updated !== 1 || $logged !== 1 || $recorded !== 1
            || $wpdb->last_error !== ''
            || (string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection
            || $wpdb->query('COMMIT') === false
            || (string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection
            || $wpdb->last_error !== '') {
            $wpdb->query('ROLLBACK');
            do_action('sharity_points_error', 'decay_failed', ['subject_type' => $column]);
            return 0;
        }

        do_action('sharity_points_decayed', $subject, $decay_amount, $days_inactive);
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
