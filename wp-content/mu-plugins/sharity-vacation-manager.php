<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Vacation_Manager
{
    public const VACATION_COST = 500;
    public const MAX_DURATION_DAYS = 14;
    public const MAX_YEARLY_COUNT = 2;

    public function activate_vacation(int $user_id, int $days = self::MAX_DURATION_DAYS): array
    {
        if ($user_id <= 0) {
            return ['success' => false, 'error' => 'invalid_user'];
        }
        return $this->activate_vacation_transactional('user_id', $user_id, $days);
    }

    public function activate_vacation_for_pseudo(string $pseudo_id, int $days = self::MAX_DURATION_DAYS): array
    {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '') {
            return ['success' => false, 'error' => 'invalid_pseudo'];
        }
        return $this->activate_vacation_transactional('pseudo_id', $pseudo_id, $days);
    }

    public function deactivate_vacation(int $user_id): array
    {
        global $wpdb;

        $wpdb->update(
            "{$wpdb->prefix}user_points",
            ['freeze_until' => null],
            ['user_id' => $user_id]
        );

        $wpdb->insert("{$wpdb->prefix}point_transactions", [
            'user_id' => $user_id,
            'points' => 0,
            'type' => 'vacation_end',
            'metadata' => wp_json_encode(['manual_end' => true]),
            'dedupe_key' => 'vacation_end:user:' . $user_id . ':' . gmdate('Y-m-d'),
            'created_at' => current_time('mysql'),
        ]);

        do_action('sharity_vacation_deactivated', $user_id);

        return ['success' => true];
    }

    public function deactivate_vacation_for_pseudo(string $pseudo_id): array
    {
        global $wpdb;

        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '') {
            return ['success' => false, 'error' => 'invalid_pseudo'];
        }

        $wpdb->update(
            "{$wpdb->prefix}user_points",
            ['freeze_until' => null],
            ['pseudo_id' => $pseudo_id]
        );

        $wpdb->insert("{$wpdb->prefix}point_transactions", [
            'user_id' => null,
            'pseudo_id' => $pseudo_id,
            'points' => 0,
            'type' => 'vacation_end',
            'metadata' => wp_json_encode(['manual_end' => true]),
            'dedupe_key' => 'vacation_end:pseudo:' . $pseudo_id . ':' . gmdate('Y-m-d'),
            'created_at' => current_time('mysql'),
        ]);

        do_action('sharity_vacation_deactivated', $pseudo_id);

        return ['success' => true];
    }

    public function get_vacation_status(int $user_id): array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT freeze_until, freeze_count_yearly FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        if (!$row) {
            return [
                'active' => false,
                'freeze_until' => null,
                'days_remaining' => 0,
                'yearly_count' => 0,
                'remaining_activations' => $this->get_max_yearly_count(),
            ];
        }

        $is_active = $row->freeze_until && strtotime($row->freeze_until) > time();
        $days_remaining = $is_active ? (int) ceil((strtotime($row->freeze_until) - time()) / 86400) : 0;

        return [
            'active' => $is_active,
            'freeze_until' => $row->freeze_until,
            'days_remaining' => $days_remaining,
            'yearly_count' => (int) $row->freeze_count_yearly,
            'remaining_activations' => max(0, $this->get_max_yearly_count() - (int) $row->freeze_count_yearly),
        ];
    }

    public function get_vacation_status_for_pseudo(string $pseudo_id): array
    {
        global $wpdb;

        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '') {
            return [
                'active' => false,
                'freeze_until' => null,
                'days_remaining' => 0,
                'yearly_count' => 0,
                'remaining_activations' => $this->get_max_yearly_count(),
            ];
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT freeze_until, freeze_count_yearly FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        if (!$row) {
            return [
                'active' => false,
                'freeze_until' => null,
                'days_remaining' => 0,
                'yearly_count' => 0,
                'remaining_activations' => $this->get_max_yearly_count(),
            ];
        }

        $is_active = $row->freeze_until && strtotime($row->freeze_until) > time();
        $days_remaining = $is_active ? (int) ceil((strtotime($row->freeze_until) - time()) / 86400) : 0;

        return [
            'active' => $is_active,
            'freeze_until' => $row->freeze_until,
            'days_remaining' => $days_remaining,
            'yearly_count' => (int) $row->freeze_count_yearly,
            'remaining_activations' => max(0, $this->get_max_yearly_count() - (int) $row->freeze_count_yearly),
        ];
    }

    private function activate_vacation_transactional(string $column, $subject, int $days): array
    {
        global $wpdb;
        if (!property_exists($wpdb, 'reconnect_retries')) {
            return ['success' => false, 'error' => 'transaction_unconfirmed'];
        }
        $previous_retries = $wpdb->reconnect_retries;
        try {
            $wpdb->reconnect_retries = 0;
            $connection = (string) $wpdb->get_var('SELECT CONNECTION_ID()');
            if ($wpdb->reconnect_retries !== 0 || $connection === '' || $wpdb->last_error !== '') {
                return ['success' => false, 'error' => 'transaction_unconfirmed'];
            }
            return $this->activate_vacation_transactional_locked($column, $subject, $days, $connection);
        } catch (Throwable $error) {
            try {
                $wpdb->query('ROLLBACK');
            } catch (Throwable $ignored) {
                // Lost connection remains unconfirmed; never claim success.
            }
            return ['success' => false, 'error' => 'transaction_unconfirmed'];
        } finally {
            $wpdb->reconnect_retries = $previous_retries;
        }
    }

    private function activate_vacation_transactional_locked(string $column, $subject, int $days, string $connection): array
    {
        $max_days = $this->get_max_duration_days();
        if ($days < 1 || $days > $max_days) {
            return ['success' => false, 'error' => sprintf('Érvénytelen időtartam (1-%d nap)', $max_days)];
        }

        global $wpdb;
        $format = $column === 'user_id' ? '%d' : '%s';
        if ($wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED') === false
            || $wpdb->query('START TRANSACTION') === false) {
            return ['success' => false, 'error' => 'transaction_start_failed'];
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT points_total, freeze_until, freeze_count_yearly
             FROM {$wpdb->prefix}user_points WHERE {$column} = {$format} FOR UPDATE",
            $subject
        ));
        if (!$row || $wpdb->last_error !== '') {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'error' => 'subject_not_found'];
        }

        $cost = $this->get_vacation_cost();
        if ((int) $row->points_total < $cost) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'error' => sprintf('Nincs elég pont (szükséges: %d)', $cost)];
        }
        if ($row->freeze_until && strtotime($row->freeze_until) > time()) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'error' => 'Már van aktív vakáció'];
        }
        if ((int) $row->freeze_count_yearly >= $this->get_max_yearly_count()) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'error' => sprintf('Éves limit elérve (max %d alkalom)', $this->get_max_yearly_count())];
        }

        $freeze_until = gmdate('Y-m-d H:i:s', strtotime("+{$days} days"));
        $metadata = wp_json_encode(['freeze_until' => $freeze_until, 'days' => $days]);
        if ($metadata === false) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'error' => 'metadata_encode_failed'];
        }
        $dedupe = 'vacation_start:' . ($column === 'user_id' ? 'user' : 'pseudo') . ':' . (string) $subject . ':' . gmdate('Y-m-d');
        $inserted = $column === 'user_id'
            ? $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}point_transactions
                    (user_id, pseudo_id, points, type, dedupe_key, metadata, created_at)
                 VALUES (%d, NULL, %d, 'vacation_start', %s, %s, %s)",
                (int) $subject,
                -$cost,
                $dedupe,
                $metadata,
                current_time('mysql')
            ))
            : $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}point_transactions
                    (user_id, pseudo_id, points, type, dedupe_key, metadata, created_at)
                 VALUES (NULL, %s, %d, 'vacation_start', %s, %s, %s)",
                (string) $subject,
                -$cost,
                $dedupe,
                $metadata,
                current_time('mysql')
            ));
        $updated = $inserted === 1 ? $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}user_points
             SET points_total = points_total - %d, freeze_until = %s,
                 freeze_count_yearly = freeze_count_yearly + 1
             WHERE {$column} = {$format}",
            $cost,
            $freeze_until,
            $subject
        )) : false;
        if ($inserted !== 1 || $updated !== 1
            || $wpdb->last_error !== ''
            || (string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection
            || $wpdb->query('COMMIT') === false
            || (string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $connection
            || $wpdb->last_error !== '') {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'error' => $inserted === 0 ? 'duplicate' : 'vacation_write_failed'];
        }

        do_action('sharity_vacation_activated', $subject, $freeze_until);
        return ['success' => true, 'freeze_until' => $freeze_until];
    }

    private function get_vacation_cost(): int
    {
        $cost = (int) apply_filters('sharity_vacation_cost', self::VACATION_COST);
        return $cost > 0 ? $cost : self::VACATION_COST;
    }

    private function get_max_yearly_count(): int
    {
        $count = (int) apply_filters('sharity_vacation_max_yearly', self::MAX_YEARLY_COUNT);
        return $count > 0 ? $count : self::MAX_YEARLY_COUNT;
    }

    private function get_max_duration_days(): int
    {
        $days = (int) apply_filters('sharity_vacation_max_duration', self::MAX_DURATION_DAYS);
        return $days > 0 ? $days : self::MAX_DURATION_DAYS;
    }

    private function normalize_pseudo_id(string $pseudo_id): string
    {
        if (function_exists('sharity_normalize_pseudo_id')) {
            return sharity_normalize_pseudo_id($pseudo_id);
        }

        $pseudo_id = strtolower(sanitize_text_field($pseudo_id));
        if ($pseudo_id === '') {
            return '';
        }
        if (function_exists('impactshop_identity_profile_valid_pseudo')) {
            return impactshop_identity_profile_valid_pseudo($pseudo_id) ? $pseudo_id : '';
        }
        return preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id) ? $pseudo_id : '';
    }
}

add_filter('sharity_can_earn_points', 'sharity_points_block_when_frozen', 10, 3);

function sharity_points_block_when_frozen(bool $can_earn, $subject, string $type): bool
{
    if (!$can_earn) {
        return false;
    }

    $allow = ['vacation_start', 'vacation_end', 'decay', 'admin_adjustment'];
    if (in_array($type, $allow, true)) {
        return true;
    }

    global $wpdb;

    if (is_numeric($subject)) {
        $user_id = (int) $subject;
        if ($user_id <= 0) {
            return $can_earn;
        }
        $freeze_until = $wpdb->get_var($wpdb->prepare(
            "SELECT freeze_until FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));
    } else {
        $pseudo_id = sanitize_text_field((string) $subject);
        if ($pseudo_id === '') {
            return $can_earn;
        }
        $freeze_until = $wpdb->get_var($wpdb->prepare(
            "SELECT freeze_until FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));
    }

    if ($freeze_until && strtotime((string) $freeze_until) > time()) {
        return false;
    }

    return true;
}
