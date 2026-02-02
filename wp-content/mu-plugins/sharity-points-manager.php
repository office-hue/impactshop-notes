<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Points_Manager
{
    public function award_points_for_pseudo(
        string $pseudo_id,
        int $points,
        string $type,
        ?string $source_id = null,
        array $metadata = [],
        ?string $dedupe_key = null
    ): array {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '' || $points === 0) {
            return ['success' => false, 'error' => 'invalid_input'];
        }

        if (!apply_filters('sharity_can_earn_points', true, $pseudo_id, $type)) {
            return ['success' => false, 'error' => 'earning_blocked'];
        }

        global $wpdb;

        $this->ensure_pseudo_row($pseudo_id);

        $dedupe = $dedupe_key ?? $this->build_dedupe_key($type, $source_id, $metadata);
        $metadata_json = !empty($metadata) ? wp_json_encode($metadata) : null;

        $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $wpdb->query('START TRANSACTION');

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}point_transactions
                (user_id, pseudo_id, points, type, source_id, source_type, dedupe_key, metadata, created_at)
             VALUES (NULL, %s, %d, %s, %s, %s, %s, %s, %s)",
            $pseudo_id,
            $points,
            $type,
            $source_id,
            $metadata['source_type'] ?? null,
            $dedupe,
            $metadata_json,
            current_time('mysql')
        ));

        if ($inserted === 0) {
            $wpdb->query('ROLLBACK');
            return ['success' => true, 'duplicate' => true, 'transaction_id' => null];
        }

        $transaction_id = (int) $wpdb->insert_id;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_points
                 SET points_total = GREATEST(points_total + %d, 0),
                     points_lifetime = points_lifetime + %d,
                     points_decayed = points_decayed + %d,
                     last_activity_at = %s
                 WHERE pseudo_id = %s",
                $points,
                $points > 0 ? $points : 0,
                $type === 'decay' && $points < 0 ? abs($points) : 0,
                current_time('mysql'),
                $pseudo_id
            )
        );

        $new_total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        $level_manager = new Sharity_Level_Manager();
        $level_change = $level_manager->check_and_update_level_for_pseudo($pseudo_id, $new_total);

        $wpdb->query('COMMIT');

        do_action(
            'sharity_points_earned',
            null,
            $points,
            $type,
            array_merge($metadata, [
                'pseudo_id' => $pseudo_id,
                'transaction_id' => $transaction_id,
                'new_total' => $new_total,
            ])
        );

        return [
            'success' => true,
            'new_total' => $new_total,
            'level_changed' => $level_change['changed'],
            'level_up_to' => $level_change['new_level'],
            'transaction_id' => $transaction_id,
        ];
    }

    public function award_points(
        int $user_id,
        int $points,
        string $type,
        ?string $source_id = null,
        array $metadata = [],
        ?string $dedupe_key = null
    ): array {
        if ($user_id <= 0 || $points === 0) {
            return ['success' => false, 'error' => 'invalid_input'];
        }

        if (!apply_filters('sharity_can_earn_points', true, $user_id, $type)) {
            return ['success' => false, 'error' => 'earning_blocked'];
        }

        global $wpdb;

        $this->ensure_user_row($user_id);

        $dedupe = $dedupe_key ?? $this->build_dedupe_key($type, $source_id, $metadata);
        $metadata_json = !empty($metadata) ? wp_json_encode($metadata) : null;

        $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $wpdb->query('START TRANSACTION');

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}point_transactions
                (user_id, points, type, source_id, source_type, dedupe_key, metadata, created_at)
             VALUES (%d, %d, %s, %s, %s, %s, %s, %s)",
            $user_id,
            $points,
            $type,
            $source_id,
            $metadata['source_type'] ?? null,
            $dedupe,
            $metadata_json,
            current_time('mysql')
        ));

        if ($inserted === 0) {
            $wpdb->query('ROLLBACK');
            return ['success' => true, 'duplicate' => true, 'transaction_id' => null];
        }

        $transaction_id = (int) $wpdb->insert_id;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_points
                 SET points_total = GREATEST(points_total + %d, 0),
                     points_lifetime = points_lifetime + %d,
                     points_decayed = points_decayed + %d,
                     last_activity_at = %s
                 WHERE user_id = %d",
                $points,
                $points > 0 ? $points : 0,
                $type === 'decay' && $points < 0 ? abs($points) : 0,
                current_time('mysql'),
                $user_id
            )
        );

        $new_total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        $level_manager = new Sharity_Level_Manager();
        $level_change = $level_manager->check_and_update_level($user_id, $new_total);

        $wpdb->query('COMMIT');

        do_action(
            'sharity_points_earned',
            $user_id,
            $points,
            $type,
            array_merge($metadata, [
                'transaction_id' => $transaction_id,
                'new_total' => $new_total,
            ])
        );

        return [
            'success' => true,
            'new_total' => $new_total,
            'level_changed' => $level_change['changed'],
            'level_up_to' => $level_change['new_level'],
            'transaction_id' => $transaction_id,
        ];
    }

    public function get_points_snapshot(int $user_id): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return [];
        }

        return $row;
    }

    public function get_points_snapshot_for_pseudo(string $pseudo_id): array
    {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '') {
            return [];
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ), ARRAY_A);

        if (!$row) {
            return [];
        }

        return $row;
    }

    private function ensure_user_row(int $user_id): void
    {
        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        if ($exists > 0) {
            return;
        }

        $wpdb->insert("{$wpdb->prefix}user_points", [
            'user_id' => $user_id,
            'pseudo_id' => null,
            'points_total' => 0,
            'points_lifetime' => 0,
            'points_decayed' => 0,
            'current_level' => 'basic',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    private function ensure_pseudo_row(string $pseudo_id): void
    {
        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        if ($exists > 0) {
            return;
        }

        $wpdb->insert("{$wpdb->prefix}user_points", [
            'user_id' => null,
            'pseudo_id' => $pseudo_id,
            'points_total' => 0,
            'points_lifetime' => 0,
            'points_decayed' => 0,
            'current_level' => 'basic',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    private function build_dedupe_key(string $type, ?string $source_id, array $metadata): string
    {
        if (!empty($metadata['dedupe_key'])) {
            return (string) $metadata['dedupe_key'];
        }

        $parts = [$type];
        if ($source_id) {
            $parts[] = $source_id;
        }
        if (!empty($metadata['date'])) {
            $parts[] = (string) $metadata['date'];
        }

        return implode(':', $parts);
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
